<?php
// №631 · Проверка единой базы знаний ИТК (store/api/kb_lib.php) на временной базе SQLite:
// 1) перенос памятки и выученного из itc_secret — текст тот же, у записей дата, автор, источник;
// 2) все прежние места записи (sp_put) пишут в базу знаний, удалённое уходит в историю;
// 3) правила помощников из кода → в базе; «Космос» берёт общие правила с заменой имени, свой вариант — где код другой;
//    правка руководителя не затирается новым кодом; нет базы — работает запасной вариант из кода;
// 4) подсказка для Claude («Космос») и для DeepSeek («Фил») собирается одной функцией: все разделы, одно содержание;
// 5) выгрузка одним архивом (README, Markdown, kb.json) и восстановление из него; 6) история разработки с сервера;
// 7) другие модели (OpenAI-совместимые): ключ не отдаётся, ответ разбирается; 8) код магазина и сервера подключён к базе.
// Запуск: php tests/kb_test.php
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . mb_substr(json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 600) . "\n"; } }
function cst($file, $n) { $s = file_get_contents($file); $a = strpos($s, "\nconst $n = "); if ($a === false) return null; $b = strpos($s, ";\n", $a); return eval('return ' . substr($s, $a + strlen("\nconst $n = "), $b - $a - strlen("\nconst $n = ")) . ';'); }
$FIL = "$root/store/api/flow.php"; $KOS = "$root/store/api/kosmos_flow.php";
const RULES_NAMED = ['FIL_CORE', 'FIL_KP', 'FIL_CATALOG', 'FIL_SEARCH', 'CRM_RULES', 'RB_RULES', 'AG_MEMO_HOW', 'AG_LEARN_HOW', 'AG_FACT_RULES', 'AG_BG_RULES', 'AG_TASK_RULES', 'AG_DEEP_RULES', 'AG_FAST_RULES', 'FIL_EX', 'FIL_LAST'];
const RULES_SHARED = ['FE_RULES' => 'fil_edit.php', 'PL_RULES' => 'planner.php', 'ML_RULES' => 'mail_lib.php', 'MSP_RULES' => 'mail_spam.php'];

$mode = $argv[1] ?? 'main';
$dbf = sys_get_temp_dir() . '/kb_test_' . getmypid() . '.sqlite';
if ($mode !== 'main') $dbf = (string)($argv[2] ?? $dbf);
function db() { static $p = null; global $dbf; if (!$p) { $p = new PDO('sqlite:' . $dbf, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } return $p; }
require "$root/store/api/kb_lib.php";

/* ---- запасной режим: база знаний ещё не заполнена (нет памятки в коде этого процесса) — всё из кода и itc_secret ---- */
if ($mode === 'fallback') {
  $GLOBALS['KB_ME'] = 'Фил';
  $code = cst($FIL, 'FIL_CORE');
  $r = kb_rule('FIL_CORE', $code, true);
  echo json_encode(['rule_is_code' => $r === $code, 'memo' => kb_memo(), 'rows' => (int)db()->query("SELECT COUNT(*) FROM itc_kb")->fetchColumn()], JSON_UNESCAPED_UNICODE);
  exit;
}
/* ---- один помощник собирает свою подсказку (как ag_run): Фил (DeepSeek) или Космос (Claude) — отдельным процессом ---- */
if ($mode === 'prompt') {
  $who = (string)($argv[3] ?? 'Фил'); $GLOBALS['KB_ME'] = $who; $src = $who === 'Фил' ? $FIL : $KOS;
  define('FIL_MEMO0', cst($src, 'FIL_MEMO0'));
  define('AG_PEER', $who === 'Фил' ? ['log' => 'itc_ks_log', 'name' => 'Космос', 'gen' => 'Космоса', 'me' => 'Фил'] : ['log' => 'itc_fc_log', 'name' => 'Фил', 'gen' => 'Фила', 'me' => 'Космос']);
  /* журнал напарника и навыки — из тех же таблиц базы (как ag_peer_block и nvk_block у помощников) */
  function ag_peer_block($me) { $q = db()->query('SELECT who,said,reply FROM ' . AG_PEER['log'] . ' ORDER BY id DESC LIMIT 6'); $o = ''; foreach ($q->fetchAll() as $r) $o .= "\n- " . AG_PEER['name'] . ', просил(а) ' . $r['who'] . ': «' . $r['said'] . '» → ' . $r['reply']; return "\n\nОБЩАЯ ПАМЯТЬ (журнал " . AG_PEER['gen'] . '):' . $o; }
  function nvk_block($text) { $o = ''; foreach (db()->query('SELECT * FROM itc_skill_copy ORDER BY sid')->fetchAll() as $r) $o .= "\n№" . $r['sid'] . '. ' . $r['title'] . ' — ' . $r['steps']; return "\n\nНАВЫКИ:" . $o; }
  $kb = kb_prompt(['name' => 'Алексей', 'phone' => '+1', 'boss' => true], 'сделай КП для гостиницы');
  $rules = [];
  foreach (RULES_NAMED as $k) $rules[$k] = kb_rule($k, cst($src, $k), true);
  foreach (RULES_SHARED as $k => $f) $rules[$k] = kb_rule($k, cst("$root/store/api/$f", $k));
  $code = []; foreach (RULES_NAMED as $k) $code[$k] = cst($src, $k); foreach (RULES_SHARED as $k => $f) $code[$k] = cst("$root/store/api/$f", $k);
  echo json_encode(['know' => $kb['know'], 'dyn' => $kb['dyn'], 'rules' => $rules, 'code' => $code], JSON_UNESCAPED_UNICODE);
  exit;
}
function sub($mode, $who = '') { global $dbf; $r = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $mode . ' ' . escapeshellarg($dbf) . ' ' . escapeshellarg($who) . ' 2>&1'); $j = json_decode((string)$r, true); return is_array($j) ? $j : ['_raw' => $r]; }

/* ---- подготовка: как в магазине до №631 ---- */
db()->exec("CREATE TABLE itc_secret (k TEXT PRIMARY KEY, v TEXT, who TEXT)");
db()->exec("CREATE TABLE itc_skill_copy (sid INTEGER PRIMARY KEY, title TEXT, trig TEXT, steps TEXT, trace TEXT, author TEXT, who TEXT, manual INT, uses INT, used_at TEXT, created_at TEXT, updated_at TEXT, paused INT DEFAULT 0, claude INT DEFAULT 0, synced_at TEXT)");
foreach (['itc_fc_log', 'itc_ks_log'] as $tb) db()->exec("CREATE TABLE $tb (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT, who TEXT, said TEXT, reply TEXT, kind TEXT, deal_id TEXT, created_at TEXT, conv_id TEXT DEFAULT '')");
$M0 = cst($FIL, 'FIL_MEMO0');
$memo = $M0 . "\n- Счета клиентам — только в рублях. (12.11.2026)\n\n- Фил, отчёты пиши коротко.";
$learn = "- «с Нади» — это фабрика SNADI (12.11.2026, Анна)\n- Метрополь — гостиница в Москве (13.11.2026, Алексей)\n- Опечатка в дате не мешает (31.02.2026, Анна)";
kb_sp_put('fil_memo', $memo, 'Алексей'); kb_sp_put('fil_learn', $learn, 'Алексей');
db()->exec("INSERT INTO itc_skill_copy (sid,title,trig,steps,trace,author,who,manual,uses,used_at,created_at,updated_at) VALUES (7,'КП для гостиницы','гостиница, КП','suppliers → make_kp','suppliers → make_kp','Космос','Алексей',0,3,'','2026-11-10 10:00:00','2026-11-10 10:00:00')");
$now = date('Y-m-d H:i:s');
db()->exec("INSERT INTO itc_fc_log (phone,who,said,reply,kind,deal_id,created_at) VALUES ('+1','Алексей','Найди полотенца','Нашёл 3 фабрики','answer','','$now')");
db()->exec("INSERT INTO itc_ks_log (phone,who,said,reply,kind,deal_id,created_at) VALUES ('+1','Алексей','Сделай КП для Метрополя','КП готово','answer','','$now')");

echo "1. Запасной режим (база знаний ещё не заполнена)\n";
$fb = sub('fallback');
t('без базы знаний правило — из кода, памятка — из прежнего места, в базе ничего не появилось', !empty($fb['rule_is_code']) && ($fb['memo'] ?? '') === $memo && ($fb['rows'] ?? -1) === 0, $fb);

echo "2. Перенос памятки и выученного\n";
define('FIL_MEMO0', $M0); $GLOBALS['KB_ME'] = 'Фил';
t('база знаний готова после первого обращения', kb_ready() && kb_sp_get('kb_migrated') === '1');
t('памятка — та же до знака', kb_memo() === $memo, [kb_memo()]);
t('выученное — то же до знака', kb_learn() === $learn);
$rows = kb_rows('memo'); $by = []; foreach ($rows as $r) $by[$r['text']] = $r;
t('строки начальной памятки — автор Claude', $by[explode("\n", $M0)[0]]['author'] === 'Claude');
$rub = $by['- Счета клиентам — только в рублях. (12.11.2026)'];
t('правило «запомни» — автор руководитель, дата из пометки 12.11.2026, источник указан', $rub['author'] === 'руководитель' && substr($rub['created_at'], 0, 10) === '2026-11-12' && $rub['src'] !== '', $rub);
$lr = kb_rows('learn');
t('выученное: дата и сотрудник из пометки, автор «Фил или Космос» (раньше не записывалось)', substr($lr[0]['created_at'], 0, 10) === '2026-11-12' && $lr[0]['src'] === 'поправка сотрудника: Анна' && $lr[0]['author'] === 'Фил или Космос', $lr[0]);
t('опечатка в дате (31.02) — запись всё равно перенесена, с датой переноса', substr(end($lr)['created_at'], 0, 10) === date('Y-m-d') && strpos(end($lr)['text'], 'Опечатка') !== false, end($lr));
t('пустые строки памятки сохранены (текст не изменился по смыслу и по виду)', count($rows) === count(explode("\n", $memo)));

echo "3. Запись через прежние места (sp_put → kb_from_sp)\n";
$memo2 = str_replace("\n- Фил, отчёты пиши коротко.", '', $memo) . "\n- Поставщикам пишем на китайском. (16.11.2026)";
kb_from_sp('fil_memo', $memo2, 'Алексей');
t('памятка после правки — как записано', kb_memo() === $memo2);
$gone = db()->query("SELECT * FROM itc_kb WHERE sec='memo' AND deleted=1")->fetchAll();
t('удалённая строка не стёрта, а в истории (deleted=1, кто удалил)', count($gone) === 1 && $gone[0]['text'] === '- Фил, отчёты пиши коротко.' && $gone[0]['deleted_by'] === 'Алексей', $gone);
$by2 = []; foreach (kb_rows('memo') as $r) $by2[$r['text']] = $r;
t('оставшиеся строки сохранили свои даты и авторов', $by2['- Счета клиентам — только в рублях. (12.11.2026)']['id'] === $rub['id']);
$GLOBALS['KB_AUTHOR'] = 'Космос'; kb_from_sp('fil_learn', $learn . "\n- КП — только по шаблону ИТК (16.11.2026, Павел)", 'Павел'); unset($GLOBALS['KB_AUTHOR']);
$l3 = kb_rows('learn');
t('выученное «Космосом» — автор Космос, источник — поправка Павла', end($l3)['author'] === 'Космос' && end($l3)['src'] === 'поправка сотрудника: Павел', end($l3));

echo "4. Подсказка для Claude («Космос») и для DeepSeek («Фил») — одной общей функцией\n";
$pf = sub('prompt', 'Фил'); $pk = sub('prompt', 'Космос');
t('обе подсказки собрались', isset($pf['know'], $pk['know']), [$pf['_raw'] ?? '', $pk['_raw'] ?? '']);
t('знания (памятка + выученное) — одинаковые по содержанию', $pf['know'] === $pk['know'] && strpos($pf['know'], 'ПАМЯТКА РУКОВОДИТЕЛЯ') !== false && strpos($pf['know'], 'ВЫУЧЕНО ИЗ ПОПРАВОК') !== false && strpos($pf['know'], 'Поставщикам пишем на китайском') !== false);
t('в обеих — навыки и журнал дел (у каждого — дела напарника)', strpos($pf['dyn'], 'КП для гостиницы') !== false && strpos($pk['dyn'], 'КП для гостиницы') !== false && strpos($pf['dyn'], 'Сделай КП для Метрополя') !== false && strpos($pk['dyn'], 'Найди полотенца') !== false);
$diff = []; foreach ($pk['rules'] as $k => $v) if ($v !== $pk['code'][$k]) $diff[] = $k;
t('«Космос» получает из базы ровно свой текст правил (как в его коде)', !$diff, $diff);
$same = []; foreach ($pf['rules'] as $k => $v) if (kb_name($v) !== $pk['rules'][$k]) $same[] = $k;
t('правила обоих одинаковы по содержанию (отличие — только имя помощника и одно своё правило «Космоса»)', $same === ['FIL_LAST'], $same);
t('все 19 правил — в базе знаний', (int)db()->query("SELECT COUNT(*) FROM itc_kb WHERE sec='rule' AND who='' AND deleted=0")->fetchColumn() === 19 && (int)db()->query("SELECT COUNT(*) FROM itc_kb WHERE sec='rule' AND who='Космос'")->fetchColumn() === 1);
/* руководитель поправил правило в базе — оба помощника применяют, «Космос» — со своим именем */
db()->prepare("UPDATE itc_kb SET text=?, author='руководитель' WHERE sec='rule' AND who='' AND k='FIL_CORE'")->execute([cst($FIL, 'FIL_CORE') . "\nФил, всегда называй сроки в рабочих днях."]);
$pf = sub('prompt', 'Фил'); $pk = sub('prompt', 'Космос');
t('правка правила в базе — у «Фила» и у «Космоса» (с его именем)', strpos($pf['rules']['FIL_CORE'], 'Фил, всегда называй сроки') !== false && strpos($pk['rules']['FIL_CORE'], 'Космос, всегда называй сроки') !== false);
/* новый код правила (следующее обновление): неправленное — обновляется, правленное руководителем — остаётся */
db()->prepare("UPDATE itc_kb SET code_text=? WHERE sec='rule' AND who='' AND k IN ('FIL_KP','FIL_CORE')")->execute(['старый код']);
db()->prepare("UPDATE itc_kb SET text=? WHERE sec='rule' AND who='' AND k='FIL_KP'")->execute(['старый код']);
$pf = sub('prompt', 'Фил');
t('новое обновление кода: неправленое правило обновилось само', $pf['rules']['FIL_KP'] === cst($FIL, 'FIL_KP'));
t('правка руководителя новым кодом не затёрта', strpos($pf['rules']['FIL_CORE'], 'всегда называй сроки') !== false);

echo "5. Выгрузка одним архивом и восстановление\n";
kb_ext_put('dev', [['id' => 22, 'text' => "ПОРУЧЕНИЕ: переключатель модели\n\nИТОГ: выпущено №630", 'created_at' => '2026-11-16 10:00:00', 'author' => 'руководитель', 'src' => 'сервер «Космоса», поручение №22', 'status' => 'done', 'release_n' => 630]]);
t('история разработки: поручение записано, повтор не задваивает', kb_ext_put('dev', [['id' => 22, 'text' => "ПОРУЧЕНИЕ: переключатель модели\n\nИТОГ: выпущено №630", 'status' => 'done', 'release_n' => 630, 'author' => 'руководитель', 'src' => 'сервер «Космоса», поручение №22']]) === 0 && count(kb_rows('dev')) === 1);
$f = kb_files();
foreach (['README.md', '00_podskazka_dlya_ii.md', '01_pamyatka.md', '02_vyuchennoe.md', '03_navyki.md', '04_pravila_pomoshchnikov.md', '05_zhurnal.md', '06_istoriya_razrabotki.md', '07_pamyat_kosmosa.md', 'kb.json', 'zhurnal.jsonl'] as $n) if (!isset($f[$n])) t("в архиве есть $n", false);
t('в архиве все разделы: README, подсказка для любого ИИ, Markdown по разделам, kb.json, журнал', count($f) === 11 && strpos($f['README.md'], 'Как подключить к новому ИИ') !== false && strpos($f['README.md'], 'Как восстановить') !== false);
t('подсказка для нового ИИ: памятка, выученное, правила', strpos($f['00_podskazka_dlya_ii.md'], 'Счета клиентам — только в рублях') !== false && strpos($f['00_podskazka_dlya_ii.md'], 'SNADI') !== false && strpos($f['00_podskazka_dlya_ii.md'], '### FIL_CORE') !== false);
$kj = json_decode($f['kb.json'], true); $noMeta = [];
foreach ($kj['sections'] as $s => $rs) foreach ($rs as $r) if ($r['created_at'] === '' || $r['author'] === '' || !array_key_exists('src', $r)) $noMeta[] = $s . ':' . $r['id'];
t('у каждой записи — дата, автор, источник', !$noMeta && count($kj['sections']['memo']) > 5, $noMeta);
t('навыки, журнал и история разработки — в архиве', strpos($f['03_navyki.md'], 'КП для гостиницы') !== false && substr_count($f['zhurnal.jsonl'], "\n") === 2 && strpos($f['06_istoriya_razrabotki.md'], 'Поручение №22 → обновление №630') !== false);
$zip = kb_zip_build($f); $back = kb_zip_read($zip);
t('архив .zip открывается и читается обратно без потерь', $back === $f);
if (class_exists('ZipArchive')) { $tf = tempnam(sys_get_temp_dir(), 'z'); file_put_contents($tf, $zip); $z = new ZipArchive(); t('архив открывает и обычная программа (ZipArchive)', $z->open($tf) === true && $z->getFromName('kb.json') === $f['kb.json']); @unlink($tf); }
/* восстановление: всё поменяли — загрузили архив — вернулось как было */
$memoBefore = kb_memo(); $learnBefore = kb_learn();
kb_from_sp('fil_memo', "- совсем другая памятка", 'Тест'); kb_from_sp('fil_learn', '', 'Тест');
db()->exec("DELETE FROM itc_fc_log");
db()->exec("UPDATE itc_kb SET text='испорчено' WHERE sec='rule' AND k='FIL_EX'");
$r = kb_import($zip, 'Алексей');
t('восстановление из архива прошло', !empty($r['ok']) && $r['journal'] === 1 && $r['rule'] === 20, $r);
t('памятка и выученное — как в архиве (и в запасном месте тоже)', kb_memo() === $memoBefore && kb_learn() === $learnBefore && kb_sp_get('fil_memo') === $memoBefore);
t('правила — как в архиве', db()->query("SELECT text FROM itc_kb WHERE sec='rule' AND k='FIL_EX' AND deleted=0")->fetchColumn() === cst($FIL, 'FIL_EX'));
t('то, что было до восстановления, — в истории (не пропало)', (int)db()->query("SELECT COUNT(*) FROM itc_kb WHERE sec='memo' AND deleted=1 AND text='- совсем другая памятка'")->fetchColumn() === 1);
t('журнал вернулся без повторов', (int)db()->query("SELECT COUNT(*) FROM itc_fc_log")->fetchColumn() === 1 && kb_import($zip, 'Алексей')['journal'] === 0);
t('чужой архив не принимается', empty(kb_import(kb_zip_build(['a.txt' => 'x']), 'Алексей')['ok']) && empty(kb_import('не архив', 'Алексей')['ok']));

echo "6. Другие модели ИИ (OpenAI-совместимые)\n";
t('адрес без https не принимается', empty(kb_prov_save(['name' => 'X', 'url' => 'http://x.ru/v1', 'model' => 'm', 'key' => 'k'], 'Алексей')['ok']));
$p = kb_prov_save(['name' => 'OpenRouter', 'url' => 'https://openrouter.ai/api/v1', 'model' => 'qwen/qwen3', 'key' => 'sk-or-test-1234'], 'Алексей');
$all = kb_prov_all();
t('модель подключена, ключ наружу не отдаётся (только 4 последних знака)', !empty($p['ok']) && $all[$p['id']]['key'] === '' && $all[$p['id']]['key4'] === '1234' && kb_prov_all(true)[$p['id']]['key'] === 'sk-or-test-1234');
function itcai_post($url, $body, $h, $tmo) { $GLOBALS['POST'] = [$url, json_decode($body, true), $h]; return [200, json_encode(['choices' => [['message' => ['content' => '{"do":"answer","say":"Готово"}']]]])]; }
function itcai_step($t) { $j = json_decode($t, true); return is_array($j) ? $j : null; }
[$j, $e] = kb_prov_ask($p['id'], 'ПРАВИЛА И ЗНАНИЯ', 'вопрос', 1500);
t('шаг на подключённой модели: те же правила в system, ответ разобран, видно, какая модель', ($j['say'] ?? '') === 'Готово' && $GLOBALS['POST'][0] === 'https://openrouter.ai/api/v1/chat/completions' && $GLOBALS['POST'][1]['messages'][0]['content'] === 'ПРАВИЛА И ЗНАНИЯ' && strpos($j['_model'], 'OpenRouter') === 0, [$j, $e]);

echo "7. Код магазина и сервера подключён к базе знаний\n";
foreach (['flow.php' => $FIL, 'kosmos_flow.php' => $KOS] as $n => $file) {
  $s = file_get_contents($file); $a = strpos($s, 'function ag_run('); $run = substr($s, $a, strpos($s, '$GLOBALS[\'FIL_MODEL\']', $a) - $a);
  $raw = []; foreach (array_merge(RULES_NAMED, array_keys(RULES_SHARED)) as $k) if (!preg_match("~kb_rule\('$k', $k(, true)?\)~", $run)) $raw[] = $k;
  t("$n: подсказка берёт все правила из базы знаний (kb_rule), знания — через kb_prompt", !$raw && strpos($run, '$kb = kb_prompt($me, $text);') !== false && strpos($run, "\$kb['know']") !== false && strpos($run, "\$kb['dyn']") !== false && strpos($run, 'fil_memo()') === false && strpos($run, 'nvk_block(') === false, $raw);
  t("$n: памятка и выученное читаются из базы, запись через sp_put попадает в базу", strpos($s, "require_once __DIR__ . '/kb_lib.php';") !== false && preg_match('~function fil_memo\(\) \{ (ks_mem_merge\(\); )?return kb_memo_text\(\); \}~', $s) && preg_match('~function fil_learn\(\) \{ (ks_mem_merge\(\); )?return kb_learn\(\); \}~', $s) && strpos($s, "kb_from_sp(\$k, \$v, \$who)") !== false);
}
$fs = file_get_contents($FIL);
t('ночная копия: база знаний отдельным архивом (хостинг, S3, забирают другие серверы)', strpos($fs, "kb_export_zip(\$kbp)") !== false && strpos($fs, "['db', 'code', 'kb']") !== false && strpos($fs, '(db|code|kb)_') !== false);
t('«Фил» умеет думать подключённой моделью (oa:…), не ответила — DeepSeek и Яндекс как раньше', strpos($fs, "if (strpos(\$fm, 'oa:') === 0) {") !== false && strpos($fs, 'kb_prov_ask(substr($fm, 3)') !== false);
$ksp = file_get_contents("$root/store/api/kosmos.php"); $ag = file_get_contents("$root/kosmos/bin/agent.php");
t('сервер «Космоса»: забирает копию базы знаний раз в сутки и шлёт историю поручений', strpos($ksp, "case 'kb_copy':") !== false && strpos($ksp, "case 'kb_put':") !== false && strpos($ag, 'function a_kb(bool $force)') !== false && strpos($ag, "a_kb(false);") !== false && strpos($ag, "'kb' => (string)(a_state()['kb_day'] ?? '')") !== false);
$up = file_get_contents("$root/store/eco/upd.html");
t('страница «Обновления»: выгрузка, загрузка, копии по местам, другие модели', strpos($up, 'Выгрузить базу знаний') !== false && strpos($up, 'Загрузить из архива') !== false && strpos($up, "api('kb.php','status')") !== false && strpos($up, 'копия базы знаний') !== false && strpos($up, 'Подключить другую модель') !== false);
$fsK = file_get_contents($KOS);
t('бюджет, защита от зацикливания и учёт стоимости не тронуты', strpos($fs, 'const AG_REPEAT = 3; const AG_STALL = 12;') !== false && strpos($fsK, 'const AG_REPEAT = 3; const AG_STALL = 12;') !== false && strpos($fsK, 'const AG_MAX_STEPS = 60;') !== false && strpos(file_get_contents("$root/kosmos/lib/price.php"), 'cost_u') !== false);

@unlink($dbf);
echo "\nИтог: $ok прошло, $bad не прошло\n";
exit($bad ? 1 : 0);
