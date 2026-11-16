<?php
// №634 · «Фил» — диспетчер китайских моделей (store/api/fil_roles.php) на временной базе SQLite, без настоящих запросов:
// роли по правилам (фото, длинные тексты, китайский, простые ответы), запасная модель, отказ → DeepSeek,
// дневной лимит, учёт расхода и журнал, пробные запросы, ключи не утекают, «Космос» роли не использует.
// Запуск: php tests/fil_roles_test.php
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . mb_substr(json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 500) . "\n"; } }
$mode = $argv[1] ?? 'main';
$dbf = $mode === 'main' ? sys_get_temp_dir() . '/fr_test_' . getmypid() . '.sqlite' : (string)$argv[2];
function db() { static $p = null; global $dbf; if (!$p) $p = new PDO('sqlite:' . $dbf, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); return $p; }
if ($mode === 'kosmos') define('KS_MODEL0', 'claude-test');
require "$root/store/api/ai_lib.php";
require "$root/store/api/kb_lib.php";
require "$root/store/api/fil_roles.php";
if ($mode === 'kosmos') { echo json_encode(['photo' => fr_models('photo'), 'easy' => fr_easy(['boss' => 1, 'phone' => '1', 'name' => 'Б'], 'спасибо')]); exit; }

db()->exec("CREATE TABLE itc_secret (k TEXT PRIMARY KEY, v TEXT NOT NULL, who TEXT NOT NULL DEFAULT '', at TEXT)");
db()->exec("CREATE TABLE itc_fc_log (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT, who TEXT, said TEXT, reply TEXT, kind TEXT, deal_id TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
db()->exec("CREATE TABLE itc_fc_file (id TEXT, phone TEXT, name TEXT, kind TEXT, text TEXT, path TEXT, mime TEXT)");
$me = ['phone' => '79990001122', 'name' => 'Иван', 'boss' => 0, 'role' => 'sales'];
function fil_io($p, $n) { return 'Иван Петрович'; }
function ff_attach_imgs($me, $ids) { return $ids ? [['m' => 'image/jpeg', 'd' => base64_encode('JPEGDATA'), 'n' => 'счёт.jpg']] : []; }

/* поддельные поставщики: что отвечает каждая модель */
$CALLS = []; $FAIL = [];
$GLOBALS['FR_POST'] = function ($url, $body, $hdr, $to) use (&$CALLS, &$FAIL) {
  $j = json_decode($body, true); $m = $j['model']; $CALLS[] = ['url' => $url, 'm' => $m, 'to' => $to, 'hdr' => $hdr, 'body' => $j];
  if (isset($FAIL[$m])) return [$FAIL[$m], '{"error":{"message":"bad Bearer sk-SECRETSECRET12345"}}'];
  $u = json_encode($j['messages'], JSON_UNESCAPED_UNICODE); $c = 'ответ ' . $m; $tc = null;
  if (strpos($u, '17 плюс 25') !== false) $c = '42';
  elseif (strpos($u, '我们工厂') !== false) $c = 'Наша фабрика отправит груз завтра.';
  elseif (!empty($j['tools'])) { $c = ''; $tc = [['id' => '1', 'type' => 'function', 'function' => ['name' => 'weather', 'arguments' => '{"city":"Гуанчжоу"}']]]; }
  elseif (strpos($u, 'Какое число') !== false) $c = $m === 'glm-4.5v' ? 'не вижу' : '4827';
  elseif (strpos($u, 'ЧТО ЭТО') !== false) $c = 'ЧТО ЭТО: счёт фабрики Foshan Light. ТАБЛИЦЫ: лампа | L-20 | 100 шт | 3.5 | USD';
  elseif (strpos($u, 'Прочитай ЦЕЛИКОМ') !== false) $c = 'Главное: договор поставки, 12 позиций, оплата 30/70.';
  elseif (strpos($u, 'строго JSON') !== false) $c = '{"text":"您好，请报价","ru":"Здравствуйте, пришлите цену"}';
  elseif (strpos($u, 'спасибо') !== false) $c = 'Пожалуйста, Иван Петрович! Обращайтесь.';
  return [200, json_encode(['choices' => [['message' => ['role' => 'assistant', 'content' => $c, 'tool_calls' => $tc]]], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]], JSON_UNESCAPED_UNICODE)];
};

echo "Без ключей — роли не работают, всё делает DeepSeek:\n";
t('фото без ключа — пусто', fr_models('photo') === [] && fr_photo_att($me, ['f1'], 'что это') === '' && !$CALLS);
t('простой ответ без ключа — нет (дальше DeepSeek)', fr_easy($me, 'спасибо') === null);
t('инструмента cn_text нет без ключа', fr_tools($me) === []);
t('роль «Диспетчер» — всегда DeepSeek', fr_cfg()['roles']['main']['m'] === 'deepseek');

echo "Ключи:\n";
t('кривой ключ не принимается', empty(fr_key_put('qwen', 'abc', 'тест')['ok']));
foreach (['qwen' => 'sk-qwenKEY1234567890ABCD', 'zhipu' => 'zhipuKEY.1234567890WXYZ', 'moonshot' => 'sk-moonKEY1234567890MNOP', 'doubao' => 'doubaoKEY-1234567890QRST'] as $p => $k) fr_key_put($p, $k, 'тест');
$st = json_encode(fr_status(), JSON_UNESCAPED_UNICODE);
t('ключи наружу не отдаются (только 4 знака)', strpos($st, 'KEY1234567890') === false && strpos($st, 'ABCD') !== false, $st);
t('запрос идёт с ключом на международный адрес Alibaba', (function () use (&$CALLS, $me) { $CALLS = []; fr_photo_att($me, ['f1'], 'что это'); return strpos($CALLS[0]['url'], 'dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions') !== false && in_array('Authorization: Bearer sk-qwenKEY1234567890ABCD', $CALLS[0]['hdr'], true); })());

echo "Роль «Фото и документы»:\n";
$CALLS = []; $GLOBALS['FR_LOG'] = [];
$a = fr_photo_att($me, ['f1'], 'занеси счёт');
t('снимок прочитала Qwen-VL, текст для DeepSeek', strpos($a, 'Qwen-VL Max') !== false && strpos($a, 'Foshan Light') !== false && $CALLS[0]['m'] === 'qwen-vl-max', $a);
t('картинка ушла как image_url (base64)', strpos(json_encode($CALLS[0]['body']), 'data:image\/jpeg;base64,') !== false);
t('ждём не дольше 60 секунд', $CALLS[0]['to'] === 60);
$CALLS = []; $FAIL = ['qwen-vl-max' => 500];
$a = fr_photo_att($me, ['f1'], 'что это');
t('Qwen-VL отказала → GLM-4.5V', strpos($a, 'GLM-4.5V') !== false && count($CALLS) === 2 && $CALLS[1]['m'] === 'glm-4.5v', array_column($CALLS, 'm'));
$FAIL = ['qwen-vl-max' => 0, 'glm-4.5v' => 503];
t('обе отказали → пусто, DeepSeek по распознанному тексту', fr_photo_att($me, ['f1'], 'что это') === '');
$FAIL = [];
$lt = fr_log_text();
t('в журнал: кто что сделал, кто не ответил, сколько стоило', strpos($lt, 'Фото и документы: Qwen-VL Max — прочитала') !== false && strpos($lt, 'не ответила') !== false && strpos($lt, '$') !== false, $lt);
t('ключ не попадает в журнал даже из текста ошибки', strpos($lt, 'SECRETSECRET') === false, $lt);

echo "Роль «Длинные тексты»:\n";
db()->prepare('INSERT INTO itc_fc_file (id,phone,name,kind,text) VALUES (?,?,?,?,?)')->execute(['ffbig', $me['phone'], 'договор.pdf', 'PDF', str_repeat('Пункт договора. ', 2500)]);
db()->prepare('INSERT INTO itc_fc_file (id,phone,name,kind,text) VALUES (?,?,?,?,?)')->execute(['ffsm', $me['phone'], 'записка.txt', 'текст', 'короткий текст']);
$CALLS = [];
t('короткий файл — без Kimi', fr_long_att($me, ['ffsm'], 'что тут') === '' && !$CALLS);
$a = fr_long_att($me, ['ffbig'], 'что в договоре');
t('файл длиннее 30 000 знаков → Kimi читает целиком', strpos($a, 'Kimi K2') !== false && strpos($a, 'оплата 30/70') !== false && $CALLS[0]['m'] === 'kimi-k2-0905-preview', $a);
$CALLS = []; $GLOBALS["FR_LOG"] = []; $r = fr_tool_res('files', ['op' => 'pdf_text'], ['text' => str_repeat('каталог ', 5000)], 'найди цены');
t('длинный pdf_text → главное от Kimi + начало', is_array($r) && count($r) === 2 && strpos(json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'оплата 30/70') !== false);
t('другие инструменты не трогаем', fr_tool_res('files', ['op' => 'img'], 'x', 'q') === 'x' && fr_tool_res('deals', [], str_repeat('a', 40000), 'q') === str_repeat('a', 40000));

echo "Роль «Китайский и переводы»:\n";
$tools = fr_tools($me);
t('у DeepSeek есть инструмент cn_text', isset($tools['cn_text']));
$r = ($tools['cn_text'][1])(['text' => 'Пришлите цену на лампы', 'mode' => 'zh', 'to' => 'фабрика']);
t('письмо на китайском + обратный перевод (Qwen Plus)', ($r['text'] ?? '') === '您好，请报价' && ($r['ru'] ?? '') !== '' && ($r['model'] ?? '') === 'Qwen Plus', $r);
$FAIL = ['qwen-plus' => 500, 'glm-4.6' => 500];
$r = ($tools['cn_text'][1])(['text' => 'Пришлите цену', 'mode' => 'zh']);
t('Qwen и запасная отказали → DeepSeek пишет сам', is_string($r) && strpos($r, 'составь или переведи текст сам') !== false, $r);
$FAIL = [];

echo "Роль «Простые ответы»:\n";
t('«Спасибо, Фил!» — простой', fr_is_easy('Спасибо, Фил!') && fr_is_easy('добрый день') && fr_is_easy('ок') && fr_is_easy('понял, спасибо'));
t('вопрос с делом — не простой', !fr_is_easy('спасибо, а где счёт по Метрополю?') && !fr_is_easy('привет, найди поставщика ламп') && !fr_is_easy('ок, переведи сделку дальше'));
$CALLS = [];
$j = fr_easy($me, 'спасибо');
t('ответила Doubao, обращение по имени-отчеству', ($j['do'] ?? '') === 'answer' && strpos($j['say'], 'Иван Петрович') !== false && $CALLS[0]['m'] === 'seed-1-6-flash-250715' && strpos(json_encode($CALLS[0]['body'], JSON_UNESCAPED_UNICODE), 'Иван Петрович') !== false, [$j, $CALLS[0]['m'] ?? '']);
$FAIL = ['seed-1-6-flash-250715' => 500, 'qwen-flash' => 500];
t('Doubao и запасная молчат → null (дальше DeepSeek)', fr_easy($me, 'спасибо') === null);
$FAIL = [];

echo "Пробные запросы:\n";
$r = fr_test('qwen-vl-max');
t('Qwen-VL: текст, китайский, инструменты, картинка — работает', !empty($r['works']) && $r['tests'] === ['текст' => true, 'китайский' => true, 'инструменты' => true, 'картинка' => true], $r);
$r = fr_test('glm-4.5v');
t('GLM-4.5V не прочла картинку — «не подходит», в роли не зовём', empty($r['works']) && strpos($r['error'], 'картинка') !== false && !fr_ready('glm-4.5v'), $r);
t('статус в окне: ошибка', (function () { foreach (fr_status()['models'] as $m) if ($m['id'] === 'glm-4.5v') return $m['state'] === 'bad'; return false; })());
t('модель без ключа не проверяется', empty(fr_test('MiniMax-M2')['ok']));
fr_key_put('zhipu', 'zhipuKEY.1234567890NEW1', 'тест');
t('новый ключ — проверка заново', fr_ready('glm-4.5v'));

echo "Настройки:\n";
t('фото-роли нельзя дать модель без зрения', empty(fr_set(['role' => 'photo', 'm' => 'qwen-plus'], 'тест')['ok']));
t('сменить модель роли', !empty(fr_set(['role' => 'photo', 'm' => 'qwen3-vl-plus'], 'тест')['ok']) && fr_cfg()['roles']['photo']['m'] === 'qwen3-vl-plus');
t('диспетчера сменить нельзя', empty(fr_set(['role' => 'main', 'm' => 'qwen-plus'], 'тест')['ok']));
fr_set(['role' => 'easy', 'off' => 1], 'тест');
t('выключенная роль не работает', fr_models('easy') === []);
fr_set(['role' => 'easy', 'off' => 0], 'тест');

echo "Учёт и дневной лимит:\n";
t('лимит по умолчанию 5 $', fr_cfg()['limit'] == 5);
$GLOBALS['FR_DS'] = null; fr_ds_usage(['prompt_tokens' => 20000, 'completion_tokens' => 1000]); fr_ds_usage(['prompt_tokens' => 22000, 'completion_tokens' => 800]);
db()->prepare('INSERT INTO itc_fc_log (phone,who,said,reply,kind,deal_id) VALUES (?,?,?,?,?,?)')->execute([$me['phone'], 'Иван', 'занеси счёт', 'Занёс', 'answer', '']);
$GLOBALS['FR_LOG'] = []; fr_note('photo', 'qwen-vl-max', 0.0031, 'прочитала 1 снимок');
fr_log_done((int)db()->lastInsertId());
$ai = (string)db()->query('SELECT ai FROM itc_fc_log ORDER BY id DESC LIMIT 1')->fetchColumn();
t('запись журнала: модели и стоимость (и DeepSeek-диспетчер)', strpos($ai, 'Qwen-VL Max — прочитала 1 снимок, $0.0031') !== false && strpos($ai, 'Диспетчер: DeepSeek — 2 шаг') !== false, $ai);
$day = fr_spent('day');
t('расход привязан к записи журнала', (int)db()->query("SELECT COUNT(*) FROM itc_fr_use WHERE log_id>0 AND model='deepseek'")->fetchColumn() === 1);
t('расход ролей считается без DeepSeek', $day > 0 && abs($day - (float)db()->query("SELECT SUM(usd) FROM itc_fr_use WHERE model<>'deepseek'")->fetchColumn()) < 1e-9);
t('в окне — расход по моделям за день и месяц', (function () { foreach (fr_status()['by_model'] as $x) if ($x['model'] === 'deepseek' && $x['day']['n'] === 1 && $x['month']['n'] === 1) return true; return false; })());
fr_set(['limit' => 0.001], 'тест'); $CALLS = []; $GLOBALS['FR_LOG'] = [];
t('лимит дня исчерпан → роли молчат, всё на DeepSeek', fr_photo_att($me, ['f1'], 'что это') === '' && fr_easy($me, 'спасибо') === null && !$CALLS);
t('в журнале — пометка про лимит', strpos(fr_log_text(), 'лимит') !== false, fr_log_text());
fr_set(['limit' => 5], 'тест');

echo "«Космос» ролями не пользуется:\n";
$k = json_decode((string)shell_exec('php ' . escapeshellarg(__FILE__) . ' kosmos ' . escapeshellarg($dbf)), true);
t('при KS_MODEL0 роли выключены', is_array($k) && $k['photo'] === [] && $k['easy'] === null, $k);

echo "Код подключён:\n";
$F = file_get_contents("$root/store/api/flow.php"); $K = file_get_contents("$root/store/api/kosmos_flow.php");
t('flow.php: фото/длинные, простые, cn_text, длинный результат, журнал, токены DeepSeek',
  strpos($F, "fr_photo_att(\$me, fl_in('files', []), \$t) . fr_long_att(") !== false && strpos($F, 'fr_easy($me, $t, $talk)') !== false && strpos($F, "fr_tools(\$me)") !== false
  && strpos($F, 'fr_tool_res($n, $args, $res, $text)') !== false && substr_count($F, 'fr_log_done((int)db()->lastInsertId())') === 2 && strpos($F, 'fr_ds_usage(') !== false);
t('kosmos_flow.php пересобран и роли там за !defined(KS_MODEL0)', strpos($K, "!defined('KS_MODEL0') && fl_in('files')") !== false);
t('kb.php: окно «Модели Фила»', strpos(file_get_contents("$root/store/api/kb.php"), "case 'fr_status'") !== false);
t('страница «Обновления»: карточка «Модели Фила»', strpos(file_get_contents("$root/store/eco/upd.html"), 'id="cFr"') !== false);
t('в коде нет ключей', !preg_match('~sk-[A-Za-z0-9]{20,}~', file_get_contents("$root/store/api/fil_roles.php")));

@unlink($dbf);
echo "\nИтого: $ok ✓ / $bad ✗\n";
exit($bad ? 1 : 0);
