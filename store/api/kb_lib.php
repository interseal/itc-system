<?php
// ==== ИТК · api/kb_lib.php · ЕДИНАЯ БАЗА ЗНАНИЙ ИТК (№631, 16.11.2026) ====
// Всё, что знают помощники, хранится у нас — в базе магазина (MySQL на хостинге), а не в модели ИИ.
// Сменили модель (Claude → другая, DeepSeek → другая) — новая модель получает те же знания из этой базы.
//
// Таблица itc_kb (одна на все разделы):
//   memo   — памятка руководителя (правила «запомни»), по строке на запись;
//   learn  — выученное из поправок сотрудников, по строке на запись;
//   rule   — правила работы помощников (раньше были только в коде flow.php / kosmos_flow.php и др.).
//            Код читает их отсюда (kb_rule); в коде остаётся запасной вариант на случай пустой базы.
//            who='' — общее правило; who='Космос' — своё правило «Космоса», если его код отличается от «Фила»;
//   dev    — история разработки: поручения разработчику и их итоги (присылает сервер «Космоса» в Сингапуре);
//   ks_mem — память «Космоса» на его сервере (старое приложение kosmos.itcpartner.store).
//   У каждой записи: дата (created_at), автор (руководитель / Космос / Фил / Claude), источник (src).
//   Удалённое не стирается, а помечается deleted=1 — история правок сохраняется.
// Навыки и журнал дел — в своих таблицах той же базы (itc_skill_copy — копия навыков сервера «Космоса»;
// itc_fc_log / itc_ks_log — журналы «Фила» и «Космоса»); в выгрузке и копиях они идут своими разделами.
// Подсказка любого помощника берёт знания через kb_prompt() и kb_rule() — одинаково для Claude, DeepSeek и любой будущей модели.
// Выгрузка одним архивом (kb_export_zip) и восстановление (kb_import) — api/kb.php, страница «Обновления».

const KB_FORMAT = 'itc-kb-1';
const KB_SECS = ['memo' => 'Памятка руководителя', 'learn' => 'Выученное из поправок', 'rule' => 'Правила работы помощников',
  'dev' => 'История разработки (поручения разработчику и итоги)', 'ks_mem' => 'Память «Космоса» на сервере в Сингапуре'];
const KB_AUTHORS = ['руководитель', 'Космос', 'Фил', 'Claude'];

function kb_drv() { static $d = null; if ($d === null) { try { $d = (string)db()->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (Throwable $e) { $d = 'mysql'; } } return $d; }
function kb_now() { return date('Y-m-d H:i:s'); }
/* кто сейчас собирает подсказку: «Фил» (flow.php) или «Космос» (kosmos_flow.php) — по AG_PEER; для проверок — $GLOBALS['KB_ME'] */
function kb_me() { if (!empty($GLOBALS['KB_ME'])) return (string)$GLOBALS['KB_ME']; return defined('AG_PEER') ? (string)AG_PEER['me'] : 'Фил'; }

/* itc_secret без sp_get/sp_put «Фила»: kb.php и kosmos.php работают и без flow.php, а sp_put вызывает базу знаний сам (нельзя по кругу) */
function kb_sp_get($k) {
  try { $q = db()->prepare('SELECT v FROM itc_secret WHERE k=?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? '' : (string)$v; }
  catch (Throwable $e) { return ''; }
}
function kb_sp_put($k, $v, $who) {
  try { db()->prepare('REPLACE INTO itc_secret (k,v,who) VALUES (?,?,?)')->execute([$k, (string)$v, mb_substr((string)$who, 0, 80)]); } catch (Throwable $e) { error_log('kb_sp_put: ' . $e->getMessage()); }
}

function kb_ensure() {
  static $st = null; if ($st !== null) return $st;
  try {
    if (kb_drv() === 'sqlite')
      db()->exec("CREATE TABLE IF NOT EXISTS itc_kb (id INTEGER PRIMARY KEY AUTOINCREMENT, sec TEXT NOT NULL, k TEXT NOT NULL DEFAULT '', who TEXT NOT NULL DEFAULT '',
        pos INTEGER NOT NULL DEFAULT 0, text TEXT NOT NULL, code_text TEXT NULL, author TEXT NOT NULL DEFAULT '', src TEXT NOT NULL DEFAULT '', meta TEXT NULL,
        created_at TEXT NOT NULL, updated_at TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0, deleted_by TEXT NOT NULL DEFAULT '')");
    else
      db()->exec("CREATE TABLE IF NOT EXISTS itc_kb (id INT AUTO_INCREMENT PRIMARY KEY, sec VARCHAR(20) NOT NULL, k VARCHAR(80) NOT NULL DEFAULT '', who VARCHAR(20) NOT NULL DEFAULT '',
        pos INT NOT NULL DEFAULT 0, text MEDIUMTEXT NOT NULL, code_text MEDIUMTEXT NULL, author VARCHAR(60) NOT NULL DEFAULT '', src VARCHAR(255) NOT NULL DEFAULT '', meta TEXT NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted TINYINT NOT NULL DEFAULT 0, deleted_by VARCHAR(80) NOT NULL DEFAULT '',
        KEY idx_kb (sec, deleted, pos), KEY idx_kb_k (sec, k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $st = true;
    kb_migrate();
  } catch (Throwable $e) { error_log('kb_ensure: ' . $e->getMessage()); $st = false; }
  return $st;
}
/* база готова: таблица есть и старая памятка/выученное перенесены. Иначе всё работает по-старому (itc_secret и код) */
function kb_ready() { static $ok = false; if (!$ok) $ok = kb_ensure() && kb_sp_get('kb_migrated') === '1'; return $ok; }

/* Один раз: памятка и выученное из itc_secret → база знаний (по строке; даты и авторы — из пометок в скобках).
   Переносим только там, где известна начальная памятка (FIL_MEMO0 — в коде помощников). */
function kb_migrate() {
  if (kb_sp_get('kb_migrated') === '1' || !defined('FIL_MEMO0')) return;
  $lock = kb_drv() !== 'sqlite';
  if ($lock && !(int)db()->query("SELECT GET_LOCK('itc_kb_migrate', 3)")->fetchColumn()) return;
  try {
    if (kb_sp_get('kb_migrated') === '1') return;
    if (function_exists('ks_mem_merge')) ks_mem_merge();   // «Космос»: сначала его старая отдельная память → общая (№536)
    $n = (int)db()->query("SELECT COUNT(*) FROM itc_kb WHERE sec IN ('memo','learn')")->fetchColumn();
    if (!$n) {
      $memo = kb_sp_get('fil_memo');
      $okM = kb_lines_put('memo', $memo !== '' ? $memo : FIL_MEMO0, 'перенос', null, 'перенесено из прежней памятки (itc_secret.fil_memo) ' . date('d.m.Y'));
      $learn = kb_sp_get('fil_learn');
      $okL = trim($learn) === '' || kb_lines_put('learn', $learn, 'перенос', null, '');
      if (!$okM || !$okL) { db()->exec("DELETE FROM itc_kb WHERE sec IN ('memo','learn')"); return; }   // не получилось — работаем по-старому (itc_secret), попробуем в следующий раз
    }
    kb_sp_put('kb_migrated', '1', 'база знаний №631');
  } finally { if ($lock) db()->query("SELECT RELEASE_LOCK('itc_kb_migrate')"); }
}

/* ---------- разделы «по строке»: памятка и выученное ---------- */
function kb_rows($sec, $all = false) {
  if (!kb_ensure()) return [];
  $q = db()->prepare('SELECT * FROM itc_kb WHERE sec=?' . ($all ? '' : ' AND deleted=0') . ' ORDER BY pos, id'); $q->execute([$sec]); return $q->fetchAll(PDO::FETCH_ASSOC);
}
function kb_text($sec) {
  // в одном ответе читаем раз в 30 с (фоновая задача идёт часами — правки руководителя подхватит по ходу)
  if (isset($GLOBALS['KB_TXT'][$sec]) && time() - (int)($GLOBALS['KB_TXT_AT'][$sec] ?? 0) < 30) return $GLOBALS['KB_TXT'][$sec];
  $t = implode("\n", array_map(function ($r) { return (string)$r['text']; }, kb_rows($sec)));
  $GLOBALS['KB_TXT'][$sec] = $t; $GLOBALS['KB_TXT_AT'][$sec] = time(); return $t;
}
/* дата и имя из пометки в конце строки: «… (12.11.2026)» или «… (12.11.2026, Алексей)» */
function kb_line_tag($l) {
  if (preg_match('~\((\d\d)\.(\d\d)\.(\d{4})(?:,\s*([^()]{1,80}))?\)\s*$~u', (string)$l, $m))
    return [checkdate((int)$m[2], (int)$m[1], (int)$m[3]) && (int)$m[3] >= 2000 ? $m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00' : '', trim((string)($m[4] ?? ''))];   // опечатка в дате — дата записи
  return ['', ''];
}
function kb_author_of($sec, $l, $who, $author) {
  if ($author !== null && $author !== '') return $author;
  if (!empty($GLOBALS['KB_AUTHOR'])) return (string)$GLOBALS['KB_AUTHOR'];
  if ($sec === 'memo' && defined('FIL_MEMO0') && in_array($l, explode("\n", FIL_MEMO0), true)) return 'Claude';   // начальная памятка — из кода
  if (mb_stripos((string)$who, 'Космос') !== false) return 'Космос';
  if ($sec === 'learn' && $who === 'перенос') return 'Фил или Космос';   // до №631 не записывалось, кто из двоих выучил
  return 'руководитель';
}
/* записать раздел целиком: совпавшие строки остаются со своими датами и авторами, новые добавляются, пропавшие помечаются удалёнными */
function kb_lines_put($sec, $text, $who, $author = null, $src = '') {
  if (!kb_ensure()) return false;
  $text = str_replace("\r", '', (string)$text);
  $lines = $text === '' ? [] : explode("\n", $text);
  $pool = []; foreach (kb_rows($sec) as $r) $pool[(string)$r['text']][] = $r;
  $d = db(); $now = kb_now();
  $up = $d->prepare('UPDATE itc_kb SET pos=? WHERE id=?');
  $ins = $d->prepare('INSERT INTO itc_kb (sec,k,who,pos,text,author,src,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
  $del = $d->prepare('UPDATE itc_kb SET deleted=1, deleted_by=?, updated_at=? WHERE id=?');
  try {
    $own = !$d->inTransaction(); if ($own) $d->beginTransaction();   // внутри чужой транзакции — пишем в ней
    foreach ($lines as $i => $l) {
      if (!empty($pool[$l])) { $r = array_shift($pool[$l]); if ((int)$r['pos'] !== $i) $up->execute([$i, $r['id']]); continue; }
      [$at, $name] = kb_line_tag($l);
      $s = $src !== '' ? $src : ($sec === 'learn' ? 'поправка сотрудника' . ($name !== '' ? ': ' . $name : '') : 'настройки помощника · ' . $who);
      $ins->execute([$sec, '', '', $i, $l, mb_substr(kb_author_of($sec, $l, $who, $author), 0, 60), mb_substr($s, 0, 250), $at !== '' ? $at : $now, $now]);
    }
    foreach ($pool as $rs) foreach ($rs as $r) $del->execute([mb_substr((string)$who, 0, 80), $now, $r['id']]);
    if ($own) $d->commit();
  } catch (Throwable $e) { if (!empty($own) && $d->inTransaction()) $d->rollBack(); error_log('kb_lines_put: ' . $e->getMessage()); return false; }
  unset($GLOBALS['KB_TXT'][$sec]);
  return true;
}
/* sp_put «Фила»/«Космоса» сообщает сюда о каждой записи памятки и выученного — все прежние места записи работают как раньше */
function kb_from_sp($k, $v, $who) {
  $sec = ['fil_memo' => 'memo', 'fil_learn' => 'learn'][$k] ?? '';
  if ($sec === '' || !kb_ready()) return;
  kb_lines_put($sec, (string)$v, (string)$who);
}
/* памятка и выученное для подсказки; база недоступна — прежнее место (itc_secret) */
function kb_memo() { return kb_ready() ? kb_text('memo') : kb_sp_get('fil_memo'); }
function kb_learn() { return kb_ready() ? kb_text('learn') : kb_sp_get('fil_learn'); }

/* ---------- правила работы помощников ---------- */
/* «Космос» — копия «Фила» (tools/make_kosmos_flow.py): в правилах из flow.php его имя и адреса вместо «Фила» */
function kb_name($t) {
  $t = preg_replace_callback('~(?<![\p{L}\p{N}_])Фил(а|у|ом|е)?(?![\p{L}\p{N}_])~u', function ($m) { return 'Космос' . ($m[1] ?? ''); }, (string)$t);
  return str_replace(['/apps/fil/', '/api/flow.php', 'uploads/fil_prog'], ['/apps/kosmos/', '/api/kosmos_flow.php', 'uploads/ks_prog'], $t);
}
function kb_rules_all($fresh = false) {
  static $all = null;
  if ($all === null || $fresh) { $all = []; foreach (kb_rows('rule') as $r) if (!isset($all[(string)$r['who']][(string)$r['k']])) $all[(string)$r['who']][(string)$r['k']] = $r; }   // повтор — берём первое
  return $all;
}
/* правило из кода → в базе: новое — записать; код поменялся (новое обновление) — обновить, если руководитель его не правил;
   правил — оставить его текст (и запомнить новый текст кода) */
function kb_rule_sync($row, $who, $key, $code) {
  $d = db(); $now = kb_now();
  if (!$row) {
    $d->prepare('INSERT INTO itc_kb (sec,k,who,pos,text,code_text,author,src,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute(['rule', $key, $who, 0, $code, $code, 'Claude', 'код помощников: ' . $key . ($who !== '' ? ' (свой вариант «' . $who . '»)' : ''), $now, $now]);
    $row = ['id' => (int)$d->lastInsertId(), 'sec' => 'rule', 'k' => $key, 'who' => $who, 'text' => $code, 'code_text' => $code];
    /* два ответа одновременно записали одно правило — остаётся первое (меньший номер), своё помечаем удалённым */
    $q = $d->prepare("SELECT MIN(id) FROM itc_kb WHERE sec='rule' AND who=? AND k=? AND deleted=0"); $q->execute([$who, $key]); $min = (int)$q->fetchColumn();
    if ($min && $min !== $row['id']) { $d->prepare("UPDATE itc_kb SET deleted=1, deleted_by='повтор' WHERE id=?")->execute([$row['id']]); $row['id'] = $min; }
  } elseif ((string)$row['code_text'] !== $code) {
    $same = (string)$row['text'] === (string)$row['code_text'];
    $d->prepare('UPDATE itc_kb SET text=?, code_text=?, updated_at=?' . ($same ? ", author='Claude'" : '') . ' WHERE id=?')->execute([$same ? $code : (string)$row['text'], $code, $now, $row['id']]);
    if ($same) $row['text'] = $code;
    $row['code_text'] = $code;
  }
  $GLOBALS['KB_RULES_SEEN'][$who][$key] = $row;
  return $row;
}
/* $named — правило из flow.php: у «Космоса» в нём его имя (копия делается заменой имени) */
function kb_rule($key, $code, $named = false) {
  $code = (string)$code;
  if (!kb_ready()) return $code;
  try {
    $all = kb_rules_all(); $me = kb_me(); $kos = $named && $me !== 'Фил';
    $g = $GLOBALS['KB_RULES_SEEN'][''][$key] ?? ($all[''][$key] ?? null);
    if (!$kos) { $g = kb_rule_sync($g, '', $key, $code); return (string)$g['text']; }
    if ($g && kb_name((string)$g['code_text']) === $code) return kb_name((string)$g['text']);   // общее правило «Фила» с именем «Космоса»
    $o = kb_rule_sync($GLOBALS['KB_RULES_SEEN'][$me][$key] ?? ($all[$me][$key] ?? null), $me, $key, $code);   // код «Космоса» отличается — его свой вариант
    return (string)$o['text'];
  } catch (Throwable $e) { error_log('kb_rule ' . $key . ': ' . $e->getMessage()); return $code; }
}

/* ---------- ОДНА ОБЩАЯ СБОРКА ЗНАНИЙ ДЛЯ ПОДСКАЗКИ ЛЮБОГО ПОМОЩНИКА И ЛЮБОЙ МОДЕЛИ ----------
   know — постоянная часть (памятка, выученное): одинакова у всех запросов, модели берут её из кеша;
   dyn  — что меняется от запроса к запросу: дела напарника (журнал) и навыки, похожие на запрос. */
function kb_memo_text() { $m = kb_memo(); return $m !== '' ? $m : (defined('FIL_MEMO0') ? FIL_MEMO0 : ''); }
function kb_prompt($me, $text) {
  $learn = function_exists('fil_learn') ? fil_learn() : kb_learn();   // fil_memo/fil_learn помощников читают ту же базу (у «Космоса» — после переноса его старой памяти)
  $know = "\n\nПАМЯТКА РУКОВОДИТЕЛЯ (правила ИТК — соблюдай всегда):\n" . (function_exists('fil_memo') ? fil_memo() : kb_memo_text())
    . ($learn !== '' ? "\n\nВЫУЧЕНО ИЗ ПОПРАВОК СОТРУДНИКОВ (применяй всегда):\n" . $learn : '');
  $dyn = (function_exists('ag_peer_block') ? ag_peer_block($me) : '')   // журнал дел второго помощника (№536)
       . (function_exists('nvk_block') ? nvk_block($text) : '');      // навыки (№538)
  return ['know' => $know, 'dyn' => $dyn];
}

/* ---------- записи с сервера «Космоса»: поручения разработчику и его память ---------- */
function kb_ext_put($sec, $items) {
  if (!in_array($sec, ['dev', 'ks_mem'], true) || !kb_ensure()) return 0;
  $d = db(); $n = 0; $now = kb_now();
  $have = []; $q = $d->prepare('SELECT id,k,text,meta,deleted FROM itc_kb WHERE sec=?'); $q->execute([$sec]);
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $have[(string)$r['k']] = $r;
  foreach (array_slice((array)$items, 0, 2000) as $x) {
    if (!is_array($x) || (int)($x['id'] ?? 0) < 1) continue;
    $k = 'sg:' . (int)$x['id'];
    $text = mb_substr(trim((string)($x['text'] ?? '')), 0, 60000); if ($text === '') continue;
    $meta = json_encode(array_intersect_key($x, array_flip(['status', 'release_n', 'engine', 'model', 'finished_at', 'scope', 'phone', 'name', 'deleted'])), JSON_UNESCAPED_UNICODE);
    $at = preg_match('~^\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d~', (string)($x['created_at'] ?? '')) ? substr(str_replace('T', ' ', (string)$x['created_at']), 0, 19) : $now;
    $au = mb_substr((string)($x['author'] ?? ($sec === 'dev' ? 'руководитель' : 'Космос')), 0, 60);
    $src = mb_substr((string)($x['src'] ?? ''), 0, 250);
    $gone = !empty($x['deleted']) ? 1 : 0;
    if (isset($have[$k])) {
      $r = $have[$k];
      if ((string)$r['text'] === $text && (string)$r['meta'] === $meta && (int)$r['deleted'] === $gone) continue;
      $d->prepare('UPDATE itc_kb SET text=?, meta=?, deleted=?, updated_at=? WHERE id=?')->execute([$text, $meta, $gone, $now, $r['id']]);
    } else {
      $d->prepare('INSERT INTO itc_kb (sec,k,who,pos,text,author,src,meta,created_at,updated_at,deleted) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$sec, $k, '', (int)$x['id'], $text, $au, $src, $meta, $at, $now, $gone]);
    }
    $n++;
  }
  return $n;
}

/* ---------- навыки и журнал (свои таблицы той же базы) ---------- */
function kb_skills() {
  if (function_exists('ks_gw_call') && defined('KS_SERVER')) {   // свежие — с сервера «Космоса» (главное место навыков)
    [$c, $j] = ks_gw_call(['a' => 'skills', 'op' => 'list'], 20);
    if ($c === 200 && !empty($j['ok']) && is_array($j['items'] ?? null)) return [$j['items'], 'сервер «Космоса» (Сингапур)'];
  }
  try { $r = db()->query('SELECT * FROM itc_skill_copy ORDER BY uses DESC, sid DESC')->fetchAll(PDO::FETCH_ASSOC);
    return [array_map(function ($x) { $x['id'] = (int)$x['sid']; unset($x['sid'], $x['synced_at']); return $x; }, $r), 'копия навыков в магазине'];
  } catch (Throwable $e) { return [[], 'навыков нет']; }
}
const KB_LOGS = ['itc_fc_log' => 'Фил', 'itc_ks_log' => 'Космос'];
const KB_LOG_MAX = 6000000;    // журнал в выгрузке — до ~6 МБ текста (хватает памяти хостинга) (свежие записи первыми); весь журнал — в ночной копии базы
function kb_journal($days = 365, $limit = 20000) {
  $out = []; $size = 0; $from = date('Y-m-d H:i:s', time() - $days * 86400);
  foreach (KB_LOGS as $t => $name) {
    try { $q = db()->prepare("SELECT phone,who,said,reply,kind,deal_id,created_at FROM $t WHERE created_at > ? ORDER BY id DESC LIMIT " . (int)$limit); $q->execute([$from]); }
    catch (Throwable $e) { continue; }
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
      $r['said'] = mb_substr((string)$r['said'], 0, 4000); $r['reply'] = mb_substr((string)$r['reply'], 0, 6000);
      $size += strlen($r['said']) + strlen($r['reply']); if ($size > KB_LOG_MAX) break 2;
      $r['helper'] = $name; $out[] = $r;
    }
  }
  usort($out, function ($a, $b) { return strcmp((string)$a['created_at'], (string)$b['created_at']); });
  return $out;
}

/* ---------- выгрузка ---------- */
function kb_data() {
  $sec = [];
  foreach (array_keys(KB_SECS) as $s) $sec[$s] = array_map(function ($r) {
    $o = ['id' => (int)$r['id'], 'k' => (string)$r['k'], 'who' => (string)$r['who'], 'pos' => (int)$r['pos'], 'text' => (string)$r['text'],
          'author' => (string)$r['author'], 'src' => (string)$r['src'], 'created_at' => (string)$r['created_at'], 'updated_at' => (string)$r['updated_at']];
    if ((string)($r['code_text'] ?? '') !== '' && (string)$r['code_text'] !== (string)$r['text']) $o['code_text'] = (string)$r['code_text'];
    $m = json_decode((string)($r['meta'] ?? ''), true); if (is_array($m) && $m) $o['meta'] = $m;
    return $o;
  }, kb_rows($s));
  [$sk, $sksrc] = kb_skills();
  return ['format' => KB_FORMAT, 'exported_at' => kb_now(), 'from' => 'itcpartner.store', 'sections' => $sec, 'skills' => array_values($sk), 'skills_src' => $sksrc];
}
function kb_md_list($rows, $titleFn = null) {
  $o = '';
  foreach ($rows as $r) {
    if (trim((string)$r['text']) === '') continue;
    $o .= ($titleFn ? $titleFn($r) : '- ' . trim((string)$r['text'])) . "\n  _" . substr((string)$r['created_at'], 0, 10) . ' · автор: ' . $r['author'] . ($r['src'] !== '' ? ' · источник: ' . $r['src'] : '') . "_\n";
  }
  return $o !== '' ? $o : "_пока пусто_\n";
}
function kb_rules_text($rows, $who) {   // правила одним текстом, как их видит помощник
  $o = ''; foreach ($rows as $r) if ((string)$r['who'] === '') $o .= "\n### " . $r['k'] . "\n" . trim($who === 'Космос' ? kb_name((string)$r['text']) : (string)$r['text']) . "\n";
  return $o;
}
function kb_files() {
  $d = kb_data(); $s = $d['sections']; $date = date('d.m.Y H:i');
  $f = [];
  $f['README.md'] = "# База знаний ИТК\n\nВыгружено: $date с itcpartner.store.\n\n"
    . "Здесь всё, что знают помощники ИТК «Фил» и «Космос»: памятка руководителя, выученное из поправок, навыки, правила работы, журнал дел и история разработки. "
    . "Знания принадлежат компании и не зависят от модели ИИ.\n\n"
    . "## Как подключить к новому ИИ\n\n"
    . "1. Откройте файл `00_podskazka_dlya_ii.md` и вставьте его целиком как системную подсказку (system prompt, «инструкции», «custom instructions») новой модели. В нём правила, памятка и выученное.\n"
    . "2. Файлы `03_navyki.md`, `05_zhurnal.md`, `06_istoriya_razrabotki.md` загрузите как документы знаний (knowledge files, «проект», «файлы»), если модель это умеет, — или вставляйте нужное по ходу разговора.\n"
    . "3. Для программистов: всё то же в машинном виде — `kb.json` (разделы, у каждой записи дата, автор и источник) и `zhurnal.jsonl` (журнал по строке).\n"
    . "4. В системе ИТК модель меняется в настройках помощника (Фил — DeepSeek и другие, «Космос» — Claude) или кнопкой «Подключить другую модель» на странице «Обновления». Знания остаются те же: подсказка любой модели собирается из этой базы.\n\n"
    . "## Как восстановить\n\nСтраница «Обновления» → «База знаний ИТК» → «Загрузить из архива» → выберите этот архив. Памятка, выученное, правила, история разработки и память «Космоса» вернутся как в архиве "
    . "(текущие записи не стираются, а помечаются удалёнными — их видно в истории). Навыки вернутся на сервер «Космоса», если их там нет. Журнал добавится без повторов.\n\n"
    . "## Что в архиве\n\n| Файл | Что внутри |\n|---|---|\n"
    . "| 00_podskazka_dlya_ii.md | готовая подсказка для любой модели: правила + памятка + выученное |\n"
    . "| 01_pamyatka.md | памятка руководителя — " . count($s['memo']) . " строк |\n| 02_vyuchennoe.md | выученное из поправок — " . count($s['learn']) . " |\n"
    . "| 03_navyki.md | навыки — " . count($d['skills']) . " (" . $d['skills_src'] . ") |\n| 04_pravila_pomoshchnikov.md | правила работы помощников — " . count($s['rule']) . " |\n"
    . "| 05_zhurnal.md | журнал дел «Фила» и «Космоса» за 30 дней (полный — в zhurnal.jsonl) |\n| 06_istoriya_razrabotki.md | поручения разработчику и итоги — " . count($s['dev']) . " |\n"
    . "| 07_pamyat_kosmosa.md | память «Космоса» на его сервере — " . count($s['ks_mem']) . " |\n| kb.json, zhurnal.jsonl | всё то же для программ |\n";
  $rules = kb_rules_text($s['rule'], 'Фил');
  $f['00_podskazka_dlya_ii.md'] = "# Подсказка для помощника ИТК (любая модель ИИ)\n\nТы — помощник компании ИТК (ООО «Интернациональная Торговая Компания»): прямые поставки из Китая в Россию. "
    . "Ниже — правила работы, памятка руководителя и выученное из поправок. Соблюдай их всегда. К руководителю обращайся «Босс», пиши по-русски, просто и коротко.\n"
    . "(Правила написаны для помощника «Фил»; если тебя зовут иначе — читай своё имя вместо «Фил».)\n\n"
    . "## ПАМЯТКА РУКОВОДИТЕЛЯ\n" . implode("\n", array_filter(array_map(function ($r) { return (string)$r['text']; }, $s['memo']), 'strlen')) . "\n\n"
    . "## ВЫУЧЕНО ИЗ ПОПРАВОК СОТРУДНИКОВ\n" . (implode("\n", array_filter(array_map(function ($r) { return (string)$r['text']; }, $s['learn']), 'strlen')) ?: '—') . "\n\n"
    . "## ПРАВИЛА РАБОТЫ\n" . ($rules !== '' ? $rules : "_правила попадут в базу после первого ответа помощника_\n");
  $f['01_pamyatka.md'] = "# Памятка руководителя\n\n" . kb_md_list($s['memo']);
  $f['02_vyuchennoe.md'] = "# Выученное из поправок сотрудников\n\n" . kb_md_list($s['learn']);
  $sk = "# Навыки помощников\n\nИсточник: " . $d['skills_src'] . ".\n";
  foreach ($d['skills'] as $x) $sk .= "\n## " . ($x['title'] ?? '') . "\n_№" . ($x['id'] ?? '') . ' · автор: ' . ($x['author'] ?? '') . ' · записан ' . substr((string)($x['created_at'] ?? ''), 0, 10)
    . (!empty($x['paused']) ? ' · приостановлен руководителем' : '') . "_\n\n**Когда:** " . ($x['trig'] ?? '') . "\n\n" . ($x['steps'] ?? '') . "\n" . (!empty($x['trace']) ? "\nИнструменты по порядку: " . $x['trace'] . "\n" : '');
  $f['03_navyki.md'] = $sk;
  $rm = "# Правила работы помощников\n\nРаньше были только в коде; с №631 хранятся в базе знаний, код берёт их оттуда (в коде — запасной вариант).\n";
  foreach ($s['rule'] as $r) $rm .= "\n## " . $r['k'] . ($r['who'] !== '' ? ' (свой вариант «' . $r['who'] . '»)' : '') . "\n_автор: " . $r['author'] . ' · с ' . substr((string)$r['created_at'], 0, 10) . ' · обновлено ' . substr((string)$r['updated_at'], 0, 10) . "_\n\n" . trim((string)$r['text']) . "\n";
  $f['04_pravila_pomoshchnikov.md'] = $rm;
  $jr = kb_journal(); $jm = "# Журнал дел «Фила» и «Космоса» — последние 30 дней\n\n"; $from = date('Y-m-d', time() - 30 * 86400); $jl = '';
  foreach ($jr as $r) {
    $jl .= json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
    if ((string)$r['created_at'] >= $from) $jm .= '- ' . substr((string)$r['created_at'], 0, 16) . ' · ' . $r['helper'] . ', просил(а) ' . $r['who'] . ': «' . mb_substr(trim((string)$r['said']), 0, 300) . '» → ' . mb_substr(trim(preg_replace('~\s+~u', ' ', (string)$r['reply'])), 0, 500) . "\n";
  }
  $f['05_zhurnal.md'] = $jm;
  $f['06_istoriya_razrabotki.md'] = "# История разработки — поручения разработчику и итоги\n\n" . kb_md_list(array_reverse($s['dev']), function ($r) { $m = (array)($r['meta'] ?? []);
    return '## Поручение №' . substr((string)$r['k'], 3) . (!empty($m['release_n']) ? ' → обновление №' . $m['release_n'] : '') . (!empty($m['status']) ? ' (' . $m['status'] . ')' : '') . "\n" . trim((string)$r['text']); });
  $f['07_pamyat_kosmosa.md'] = "# Память «Космоса» на его сервере\n\n" . kb_md_list($s['ks_mem']);
  $f['kb.json'] = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  $f['zhurnal.jsonl'] = $jl;
  return $f;
}

/* ---------- архив .zip без расширений PHP (ZipArchive есть не везде) ---------- */
function kb_zip_build($files) {
  $data = ''; $cd = ''; $n = 0; $t = getdate();
  $dt = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2); $dd = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
  foreach ($files as $name => $c) {
    $c = (string)$c; $crc = crc32($c); $z = gzdeflate($c, 6); $m = 8;
    if ($z === false || strlen($z) >= strlen($c)) { $z = $c; $m = 0; }
    $off = strlen($data);
    $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, $m, $dt, $dd, $crc, strlen($z), strlen($c), strlen($name), 0) . $name . $z;
    $cd .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, $m, $dt, $dd, $crc, strlen($z), strlen($c), strlen($name), 0, 0, 0, 0, 32, $off) . $name;
    $n++;
  }
  return $data . $cd . pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, strlen($cd), strlen($data), 0);
}
function kb_zip_read($bin) {
  $bin = (string)$bin; $e = strrpos($bin, "PK\x05\x06"); if ($e === false || strlen($bin) < $e + 22) return null;
  $h = unpack('Vsig/vd/vds/ven/vn/Vsize/Voff/vcl', substr($bin, $e, 22));
  $p = (int)$h['off']; $out = [];
  for ($i = 0; $i < (int)$h['n']; $i++) {
    if (substr($bin, $p, 4) !== "PK\x01\x02") return null;
    $c = unpack('Vsig/vv1/vv2/vfl/vm/vt/vd/Vcrc/Vcs/Vus/vnl/vxl/vcml/vdisk/via/Vea/Voff', substr($bin, $p, 46));
    $name = substr($bin, $p + 46, $c['nl']); $p += 46 + $c['nl'] + $c['xl'] + $c['cml'];
    $lo = (int)$c['off']; if (substr($bin, $lo, 4) !== "PK\x03\x04") return null;
    $l = unpack('vnl/vxl', substr($bin, $lo + 26, 4));
    $raw = substr($bin, $lo + 30 + $l['nl'] + $l['xl'], $c['cs']);
    if ((int)$c['us'] > 100 * 1048576) return null;   // больше 100 МБ в одном файле — не наш архив
    if ((int)$c['m'] === 0) $v = $raw; elseif ((int)$c['m'] === 8) $v = @gzinflate($raw, max(1, (int)$c['us'])); else $v = false;
    if ($v === false || strlen($v) !== (int)$c['us']) return null;
    if (substr($name, -1) !== '/') $out[$name] = $v;
  }
  return $out;
}
function kb_export_zip($path) {
  $f = kb_files();
  if (@file_put_contents($path, kb_zip_build($f), LOCK_EX) === false) throw new Exception('не записался архив базы знаний');
  $s = json_decode($f['kb.json'], true)['sections'];
  return 'памятка ' . count($s['memo']) . ', выученное ' . count($s['learn']) . ', правила ' . count($s['rule']) . ', навыки ' . count(json_decode($f['kb.json'], true)['skills']) . ', история разработки ' . count($s['dev']);
}

/* ---------- восстановление из архива ---------- */
function kb_import($bin, $who) {
  $files = kb_zip_read($bin);
  if ($files === null && class_exists('ZipArchive')) {   // архив пересобран другой программой (7-Zip, Проводник)
    $tmp = tempnam(sys_get_temp_dir(), 'kb'); file_put_contents($tmp, $bin); $z = new ZipArchive(); $files = [];
    if ($z->open($tmp) === true) { for ($i = 0; $i < $z->numFiles; $i++) { $n = (string)$z->getNameIndex($i); if (substr($n, -1) !== '/') $files[$n] = (string)$z->getFromIndex($i); } $z->close(); } else $files = null;
    @unlink($tmp);
  }
  if (!$files) return ['ok' => false, 'error' => 'Это не архив базы знаний (не открылся как .zip)'];
  $kj = null; $jl = '';
  foreach ($files as $n => $v) { $b = basename($n); if ($b === 'kb.json') $kj = json_decode($v, true); if ($b === 'zhurnal.jsonl') $jl = $v; }
  if (!is_array($kj) || ($kj['format'] ?? '') !== KB_FORMAT) return ['ok' => false, 'error' => 'В архиве нет kb.json базы знаний ИТК'];
  if (!kb_ensure()) return ['ok' => false, 'error' => 'База знаний недоступна'];
  $d = db(); $now = kb_now(); $res = ['ok' => true, 'from' => (string)($kj['exported_at'] ?? '')];
  $d->beginTransaction();
  try {
    foreach (['memo', 'learn', 'rule'] as $s) {   // целиком как в архиве; нынешние — в историю (deleted=1)
      $rows = array_values(array_filter((array)($kj['sections'][$s] ?? []), 'is_array'));
      $d->prepare('UPDATE itc_kb SET deleted=1, deleted_by=?, updated_at=? WHERE sec=? AND deleted=0')->execute(['восстановление из архива · ' . $who, $now, $s]);
      $ins = $d->prepare('INSERT INTO itc_kb (sec,k,who,pos,text,code_text,author,src,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
      foreach ($rows as $i => $r) {
        $at = preg_match('~^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$~', (string)($r['created_at'] ?? '')) ? $r['created_at'] : $now;
        $ins->execute([$s, mb_substr((string)($r['k'] ?? ''), 0, 80), mb_substr((string)($r['who'] ?? ''), 0, 20), $s === 'rule' ? 0 : (int)($r['pos'] ?? $i), (string)($r['text'] ?? ''),
          $s === 'rule' ? (string)($r['code_text'] ?? ($r['text'] ?? '')) : null, mb_substr((string)($r['author'] ?? ''), 0, 60), mb_substr((string)($r['src'] ?? ''), 0, 250), $at, $now]);
      }
      $res[$s] = count($rows);
    }
    $d->commit();
  } catch (Throwable $e) { $d->rollBack(); return ['ok' => false, 'error' => 'Не записалось: ' . $e->getMessage()]; }
  unset($GLOBALS['KB_TXT']); kb_rules_all(true);
  kb_sp_put('fil_memo', kb_text('memo'), $who); kb_sp_put('fil_learn', kb_text('learn'), $who);   // запасное место — как в базе
  foreach (['dev', 'ks_mem'] as $s) {
    $items = []; foreach ((array)($kj['sections'][$s] ?? []) as $r) if (is_array($r) && preg_match('~^sg:(\d+)$~', (string)($r['k'] ?? ''), $m))
      $items[] = ['id' => (int)$m[1], 'text' => (string)($r['text'] ?? ''), 'created_at' => (string)($r['created_at'] ?? ''), 'author' => (string)($r['author'] ?? ''), 'src' => (string)($r['src'] ?? '')] + (array)($r['meta'] ?? []);
    $res[$s] = kb_ext_put($s, $items);
  }
  /* навыки — на сервер «Космоса» (он добавит только тех, что у него нет; удалённые руководителем не вернёт) */
  $sk = array_values(array_filter((array)($kj['skills'] ?? []), 'is_array')); $res['skills'] = count($sk); $res['skills_added'] = null;
  if ($sk && function_exists('ks_gw_call') && defined('KS_SERVER')) { [$c, $j] = ks_gw_call(['a' => 'skills', 'op' => 'import', 'items' => $sk], 30); if ($c === 200 && !empty($j['ok'])) $res['skills_added'] = (int)($j['added'] ?? 0); }
  $res['journal'] = $jl !== '' ? kb_journal_restore($jl) : 0;
  return $res;
}
/* журнал: добавляем только то, чего нет (по времени и словам) */
function kb_journal_restore($jl) {
  $n = 0; $back = array_flip(KB_LOGS); $have = [];
  foreach (KB_LOGS as $t => $_) { try { foreach (db()->query("SELECT created_at, said FROM $t")->fetchAll(PDO::FETCH_NUM) as $r) $have[$t][(string)$r[0] . '|' . md5((string)$r[1])] = 1; } catch (Throwable $e) {} }
  foreach (array_slice(explode("\n", (string)$jl), 0, 60000) as $line) {
    $r = json_decode($line, true); if (!is_array($r) || empty($r['created_at']) || !isset($back[$r['helper'] ?? ''])) continue;
    $t = $back[$r['helper']]; $key = (string)$r['created_at'] . '|' . md5((string)($r['said'] ?? ''));
    if (isset($have[$t][$key])) continue;
    try {
      db()->prepare("INSERT INTO $t (phone,who,said,reply,kind,deal_id,created_at) VALUES (?,?,?,?,?,?,?)")
        ->execute([(string)($r['phone'] ?? ''), (string)($r['who'] ?? ''), (string)($r['said'] ?? ''), (string)($r['reply'] ?? ''), (string)($r['kind'] ?? ''), (string)($r['deal_id'] ?? ''), (string)$r['created_at']]);
      $have[$t][$key] = 1; $n++;
    } catch (Throwable $e) { continue; }
  }
  return $n;
}

/* ---------- сводка для страницы «Обновления» ---------- */
function kb_status() {
  $o = ['ready' => kb_ready(), 'sec' => []];
  foreach (KB_SECS as $s => $t) $o['sec'][] = ['k' => $s, 'title' => $t, 'n' => kb_ensure() ? kb_count($s, 0) : 0, 'del' => kb_ensure() ? kb_count($s, 1) : 0];
  try { $o['sec'][] = ['k' => 'skills', 'title' => 'Навыки (копия в магазине)', 'n' => (int)db()->query('SELECT COUNT(*) FROM itc_skill_copy')->fetchColumn(), 'del' => 0]; } catch (Throwable $e) {}
  foreach (KB_LOGS as $t => $name) { try { $o['sec'][] = ['k' => $t, 'title' => 'Журнал дел «' . $name . '»', 'n' => (int)db()->query("SELECT COUNT(*) FROM $t")->fetchColumn(), 'del' => 0]; } catch (Throwable $e) {} }
  return $o;
}
function kb_count($s, $del) { $q = db()->prepare('SELECT COUNT(*) FROM itc_kb WHERE sec=? AND deleted=?'); $q->execute([$s, (int)$del]); return (int)$q->fetchColumn(); }

/* ---------- ДРУГИЕ МОДЕЛИ: подключить любого поставщика ИИ с OpenAI-совместимым входом (OpenAI, OpenRouter, Qwen, Mistral, GigaChat-шлюзы и т. п.) ----------
   Хранится в itc_secret «ai_prov»: {id: {name, url, model, key, at, by}}. Ключ наружу не отдаётся никогда.
   Фил думает такой моделью, если в его настройках выбрана модель «oa:<id>» (кнопка на странице «Обновления»);
   не ответила — Фил сам переходит на DeepSeek и Яндекс, как раньше. Знания те же — из этой базы. */
function kb_prov_all($keys = false) {
  $j = json_decode(kb_sp_get('ai_prov'), true); $j = is_array($j) ? $j : [];
  if (!$keys) foreach ($j as $id => $p) { $j[$id]['key'] = ''; $j[$id]['has_key'] = !empty($p['key']); $j[$id]['key4'] = !empty($p['key']) ? substr((string)$p['key'], -4) : ''; }
  return $j;
}
function kb_prov_save($a, $who) {
  $name = trim(mb_substr((string)($a['name'] ?? ''), 0, 60)); $url = rtrim(trim((string)($a['url'] ?? '')), '/'); $model = trim(preg_replace('~[^a-zA-Z0-9._:/@-]~', '', (string)($a['model'] ?? '')));
  if ($name === '' || $model === '') return ['ok' => false, 'error' => 'Нужны название и модель'];
  if (!preg_match('~^https://[a-z0-9.-]+(:\d+)?(/[A-Za-z0-9._/-]*)?$~i', $url)) return ['ok' => false, 'error' => 'Адрес должен начинаться с https:// (например, https://api.openai.com/v1)'];
  $all = kb_prov_all(true); $id = preg_replace('~[^a-z0-9]~', '', (string)($a['id'] ?? '')) ?: substr(md5($url . '|' . $model . '|' . microtime()), 0, 8);
  $key = trim((string)($a['key'] ?? '')); if ($key === '' && isset($all[$id]) && $all[$id]['url'] === $url) $key = (string)$all[$id]['key'];   // новый адрес — ключ заново
  if ($key === '') return ['ok' => false, 'error' => 'Вставьте ключ этой модели'];
  $all[$id] = ['name' => $name, 'url' => $url, 'model' => $model, 'key' => $key, 'at' => date('d.m.Y H:i'), 'by' => mb_substr((string)$who, 0, 60)];
  kb_sp_put('ai_prov', json_encode($all, JSON_UNESCAPED_UNICODE), $who);
  return ['ok' => true, 'id' => $id];
}
function kb_prov_del($id, $who) { $all = kb_prov_all(true); unset($all[(string)$id]); kb_sp_put('ai_prov', json_encode($all, JSON_UNESCAPED_UNICODE), $who); return ['ok' => true]; }
/* один шаг помощника на подключённой модели: та же подсказка (правила + база знаний), тот же ответ-JSON */
function kb_prov_ask($id, $sys, $user, $maxTok) {
  $p = kb_prov_all(true)[(string)$id] ?? null;
  if (!$p || empty($p['key'])) return [null, 'модель «' . $id . '» не подключена'];
  if (!function_exists('itcai_post')) return [null, 'нет ai_lib.php'];
  $mt = min(16000, max(2000, (int)$maxTok * 2));
  for ($try = 0; $try < 2; $try++) {
    $req = json_encode(['model' => $p['model'], 'temperature' => 0.3, 'max_tokens' => $mt, 'stream' => false,
      'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user . ($try && defined('ITCAI_ONE_JSON') ? ITCAI_ONE_JSON : '')]]], JSON_UNESCAPED_UNICODE);
    [$http, $r] = itcai_post($p['url'] . '/chat/completions', $req, ['Content-Type: application/json', 'Authorization: Bearer ' . $p['key']], 110);
    if ($http !== 200) { $em = (string)(json_decode((string)$r, true)['error']['message'] ?? ''); return [null, $p['name'] . ': ошибка ' . ($http ?: 'связи') . ($em !== '' ? ' — ' . mb_substr($em, 0, 140) : '')]; }
    $t = trim(preg_replace('~<think>.*?</think>~s', '', (string)(json_decode((string)$r, true)['choices'][0]['message']['content'] ?? '')));
    $j = $t !== '' && function_exists('itcai_step') ? itcai_step($t) : null;
    if ($j) { $j['_model'] = $p['name'] . ' · ' . $p['model']; return [$j, '']; }
  }
  return [null, $p['name'] . ': ответ без разметки'];
}
/* проверка ключа: короткий вопрос без знаний и правил (копейки) */
function kb_prov_test($id) {
  $p = kb_prov_all(true)[(string)$id] ?? null; if (!$p) return ['ok' => false, 'error' => 'нет такой модели'];
  if (!function_exists('itcai_post')) require_once __DIR__ . '/ai_lib.php';
  $req = json_encode(['model' => $p['model'], 'max_tokens' => 20, 'messages' => [['role' => 'user', 'content' => 'Ответь одним словом: работает']]], JSON_UNESCAPED_UNICODE);
  [$http, $r] = itcai_post($p['url'] . '/chat/completions', $req, ['Content-Type: application/json', 'Authorization: Bearer ' . $p['key']], 40);
  if ($http === 200 && trim((string)(json_decode((string)$r, true)['choices'][0]['message']['content'] ?? '')) !== '') return ['ok' => true];
  $em = (string)(json_decode((string)$r, true)['error']['message'] ?? '');
  return ['ok' => false, 'error' => 'Модель ответила ' . ($http ?: 'нет связи') . ($em !== '' ? ' — ' . mb_substr($em, 0, 160) : '')];
}
