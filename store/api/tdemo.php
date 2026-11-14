<?php
// ==== ИТК · ДЕМО-СЕРИИ НА СЕРВЕРЕ · api/tdemo.php · №5 (10.11.2026) ====
// Серия сделок на демо-счёте работает на сервере: телефон можно выключить.
// Раз в минуту «будильник» сервера (планировщик хостинга) вызывает tdemo.php?action=cron:
// сервер берёт свежие свечи Binance, по правилам выбранной стратегии открывает сделку, следит за стопом и тейком
// по минутным свечам, закрывает и открывает следующую — пока серия не наберёт заданное число сделок.
// При открытии приложения (td_list) сервер тоже догоняет пропущенное — даже если будильник ещё не настроен.
//   td_ping   проверка: видит ли сервер биржу Binance
//   td_start  запустить серию          td_list  мои серии и сделки
//   td_stop   остановить серию         td_hide  убрать серию из списка
//   an_save / an_list / an_get / an_del — архив анализов (тесты систем, проверки)
//   kv_get / kv_set — каталог монет и свои группы; res_put / res_get / res_list / res_sigs — итоги тестов по каждой монете
//   td_bt     проверить серию на истории: разные стопы, свечи, фильтры (№565)
//   cron      шаг для всех серий (без входа; повторный запуск в ту же минуту ничего не делает)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/tdemo_lib.php';
require_once __DIR__ . '/tdemo_bt.php';
@set_time_limit(55);
$action = $_GET['action'] ?? (PHP_SAPI === 'cli' ? ($argv[1] ?? '') : '');
$IN = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
function td_out($a) { if (PHP_SAPI !== 'cli') { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); } echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function td_fail($m, $c = 400) { if (PHP_SAPI !== 'cli') http_response_code($c); td_out(['ok' => false, 'error' => $m]); }
function td_in($k, $d = '') { global $IN; return $IN[$k] ?? ($_GET[$k] ?? $d); }

function td_ensure() { static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_tdemo (id VARCHAR(24) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'run',
    data MEDIUMTEXT NOT NULL, created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0, KEY idx_td_st (status), KEY idx_td_ph (phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }

function td_load($where, $args) { td_ensure(); $q = db()->prepare("SELECT * FROM itc_tdemo WHERE $where"); $q->execute($args); return $q->fetchAll(); }
function td_save($r, $s) { db()->prepare('UPDATE itc_tdemo SET data=?, status=?, updated_at=? WHERE id=?')->execute([json_encode($s, JSON_UNESCAPED_UNICODE), $s['status'], time(), $r['id']]); }
function td_tick_rows($rows, $limit = 0) { $n = 0; $t0 = microtime(true); foreach ($rows as $r) { if ($limit && microtime(true) - $t0 > $limit) break; $s = json_decode($r['data'], true); if (!$s) continue; try { if (td_step($s)) { td_save($r, $s); $n++; } } catch (Throwable $e) { $s['err'] = 'Ошибка расчёта'; td_save($r, $s); } } return $n; }
function td_lock() { $f = fopen(sys_get_temp_dir() . '/itc_tdemo.lock', 'c'); if (!$f || !flock($f, LOCK_EX | LOCK_NB)) return null; return $f; }

/* ---------- будильник: без входа, безопасно повторять ---------- */
if ($action === 'cron') { $l = td_lock(); if (!$l) td_out(['ok' => true, 'busy' => 1]); td_ensure();
  $n = td_tick_rows(td_load("status='run'", [])); @file_put_contents(__DIR__ . '/tdemo_cron.txt', (string)time()); td_out(['ok' => true, 'stepped' => $n, 'src' => $GLOBALS['TD_SRC']]); }

/* ---------- для приложения: только руководитель ---------- */
$S = authUser('itc');
if ((string)($S['staff_role'] ?? '') !== 'boss') td_fail('ИТК Трейдинг доступен только руководителю', 403);
$ME = (string)($S['phone'] ?? '');
if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // не держим сессию: долгий расчёт не должен тормозить магазин

if ($action === 'td_ping') { $res = [];
  foreach ([['Фьючерсы Binance', 'https://fapi.binance.com/fapi/v1/klines?symbol=BTCUSDT&interval=1m&limit=1'], ['Спот Binance', 'https://api.binance.com/api/v3/klines?symbol=BTCUSDT&interval=1m&limit=1'], ['Запасной адрес Binance', 'https://data-api.binance.vision/api/v3/klines?symbol=BTCUSDT&interval=1m&limit=1']] as [$n, $u]) {
    $t = microtime(true); [$code, $b, $err] = td_get($u); $j = json_decode((string)$b, true); $res[] = ['name' => $n, 'ok' => $code === 200 && is_array($j) && !empty($j[0]), 'code' => $code, 'ms' => (int)((microtime(true) - $t) * 1000), 'err' => $err]; }
  $cf = __DIR__ . '/tdemo_cron.txt'; td_out(['ok' => true, 'res' => $res, 'cron' => is_file($cf) ? (int)file_get_contents($cf) : 0, 'now' => time()]); }
if ($action === 'td_start') { td_ensure();
  $sym = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)td_in('sym'))); $mkt = td_in('mkt') === 'spot' ? 'spot' : 'fut'; $tf = (string)td_in('tf', '1m');
  if (!in_array($tf, ['1m', '3m', '5m', '15m', '30m', '1h', '2h', '4h', '6h', '1d'], true)) td_fail('Неверное время свечи');
  $strat = (string)td_in('strat'); if (!isset(array_merge(td_defaults(), td_defaults2())[$strat])) td_fail('Эта стратегия пока работает только в телефоне');
  $amt = (float)td_in('amt'); $lev = $mkt === 'fut' ? max(1, min(50, (int)td_in('lev', 1))) : 1; $sl = (float)td_in('sl'); $tp = (float)td_in('tp'); $n = (int)td_in('n');
  $sys = td_in('sys', null); if (is_array($sys)) { $sys = ['k' => max(0, min(5, (int)($sys['k'] ?? 3))), 'dir' => in_array((int)($sys['dir'] ?? 0), [0, 1, -1], true) ? (int)($sys['dir'] ?? 0) : 0, 'htf' => empty($sys['htf']) ? 0 : 1, 'adx' => empty($sys['adx']) ? 0 : 1, 'maker' => empty($sys['maker']) ? 0 : 1, 'hrs' => empty($sys['hrs']) ? 0 : 1, 'vol' => empty($sys['vol']) ? 0 : 1, 'sm' => in_array((float)($sys['sm'] ?? 1), [1.5, 2.0, 3.0], true) ? (float)$sys['sm'] : 1];
    if ($sys['k'] >= 4) { $sl = $sl > 0 ? $sl : 0.01; $tp = 0; } elseif ($sys['k'] === 0) $tp = 0; else $tp = $sl * $sys['k']; } else $sys = null;
  if ($sym === '' || $amt < 10 || $sl <= 0 || $sl >= 50 || $n < 1 || $n > 500) td_fail('Проверьте ставку, стоп и число сделок');
  if (td_kl($mkt, $sym, $tf, 5) === null) td_fail('Сервер не получает свечи Binance — серия на сервере сейчас невозможна', 502);
  $id = 'td' . bin2hex(random_bytes(6)); $s = ['id' => $id, 'sym' => $sym, 'mkt' => $mkt, 'tf' => $tf, 'strat' => $strat, 'prm' => (array)td_in('prm', []), 'amt' => $amt, 'lev' => $lev, 'sl' => $sl, 'tp' => $sys ? $tp : ($tp > 0 ? $tp : $sl * 3), 'sys' => $sys,
    'n' => $n, 't0' => (int)round(microtime(true) * 1000), 'status' => 'run', 'opened' => 0, 'trades' => [], 'open' => null, 'lastSig' => 0, 'lastMin' => 0, 'acc' => (string)td_in('acc'), 'gid' => substr(preg_replace('~[^a-z0-9]~', '', (string)td_in('gid')), 0, 16), 'grp' => mb_substr(strip_tags((string)td_in('grp')), 0, 120)];
  db()->prepare('INSERT INTO itc_tdemo (id,phone,status,data,created_at,updated_at) VALUES (?,?,?,?,?,?)')->execute([$id, $ME, 'run', json_encode($s, JSON_UNESCAPED_UNICODE), time(), time()]);
  td_out(['ok' => true, 'series' => $s]); }
if ($action === 'td_list') { td_ensure(); $l = td_lock(); if ($l) { $rr = td_load("phone=? AND status='run'", [$ME]); shuffle($rr); td_tick_rows($rr, 12); }
  $rows = td_load('phone=? ORDER BY created_at DESC LIMIT 150', [$ME]); $out = []; foreach ($rows as $r) { $s = json_decode($r['data'], true); if ($s && empty($s['hidden'])) $out[] = $s; }
  $cf = __DIR__ . '/tdemo_cron.txt'; td_out(['ok' => true, 'series' => $out, 'cron' => is_file($cf) ? (int)file_get_contents($cf) : 0, 'now' => time()]); }
// №565 · проверка серии на истории с разными стопами, свечами и фильтрами (по одной монете за запрос)
if ($action === 'td_bt') { td_ensure(); $rows = td_load('id=? AND phone=?', [(string)td_in('id'), $ME]); if (!$rows) td_fail('Серия не найдена', 404);
  $s = json_decode($rows[0]['data'], true); $days = max(1, min(7, (int)td_in('days', 5))); $r = tdb_run($s, $days); if (empty($r['ok'])) td_fail($r['error'] ?? 'Не получилось', 502); td_out($r); }
if ($action === 'td_stop' || $action === 'td_hide') { td_ensure(); $rows = td_load('id=? AND phone=?', [(string)td_in('id'), $ME]); if (!$rows) td_fail('Серия не найдена', 404); $r = $rows[0]; $s = json_decode($r['data'], true);
  if ($action === 'td_stop' && $s['status'] === 'run') { td_step($s); if ($s['open']) { $p = td_price($s['mkt'], $s['sym']) ?? $s['open']['ep']; if (!empty($s['sys'])) tds_close($s, $p, time(), 'серия остановлена'); else td_close($s, $p, time(), 'серия остановлена'); } $s['status'] = 'stop'; $s['tdone'] = time(); }
  if ($action === 'td_hide') $s['hidden'] = 1; td_save($r, $s); td_out(['ok' => true, 'series' => $s]); }
/* ---------- архив анализов: тесты систем и проверки, чтобы вернуться к ним и скачать ---------- */
function an_ensure() { static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_tanalysis (id VARCHAR(24) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL, kind VARCHAR(12) NOT NULL, title VARCHAR(255) NOT NULL DEFAULT '',
    meta TEXT NOT NULL, data MEDIUMTEXT NOT NULL, enc VARCHAR(8) NOT NULL DEFAULT 'json', size INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0, KEY idx_an_ph (phone, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
if ($action === 'an_save') { an_ensure(); $kind = preg_replace('~[^a-z]~', '', (string)td_in('kind')); if (!in_array($kind, ['multi', 'single', 'sys'], true)) td_fail('Неверный вид анализа');
  $data = (string)td_in('data'); $enc = td_in('enc') === 'gz64' ? 'gz64' : 'json'; if ($data === '' || strlen($data) > 12000000) td_fail('Слишком большой анализ');
  $meta = json_encode(td_in('meta', []), JSON_UNESCAPED_UNICODE); $title = mb_substr((string)td_in('title'), 0, 250); $id = preg_replace('~[^a-z0-9]~', '', (string)td_in('id'));
  if ($id) { $q = db()->prepare('UPDATE itc_tanalysis SET title=?, meta=?, data=?, enc=?, size=?, updated_at=? WHERE id=? AND phone=?'); $q->execute([$title, $meta, $data, $enc, strlen($data), time(), $id, $ME]); if ($q->rowCount()) td_out(['ok' => true, 'id' => $id]); }
  $id = 'an' . bin2hex(random_bytes(6));
  db()->prepare('INSERT INTO itc_tanalysis (id,phone,kind,title,meta,data,enc,size,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$id, $ME, $kind, $title, $meta, $data, $enc, strlen($data), time(), time()]);
  td_out(['ok' => true, 'id' => $id]); }
if ($action === 'an_list') { an_ensure(); $q = db()->prepare('SELECT id,kind,title,meta,size,created_at,updated_at FROM itc_tanalysis WHERE phone=? ORDER BY created_at DESC LIMIT 300'); $q->execute([$ME]);
  $out = []; foreach ($q->fetchAll() as $r) { $r['meta'] = json_decode($r['meta'], true); $out[] = $r; } td_out(['ok' => true, 'items' => $out]); }
if ($action === 'an_get') { an_ensure(); $q = db()->prepare('SELECT * FROM itc_tanalysis WHERE id=? AND phone=?'); $q->execute([(string)td_in('id'), $ME]); $r = $q->fetch(); if (!$r) td_fail('Анализ не найден', 404);
  $r['meta'] = json_decode($r['meta'], true); td_out(['ok' => true, 'item' => $r]); }
if ($action === 'an_del') { an_ensure(); db()->prepare('DELETE FROM itc_tanalysis WHERE id=? AND phone=?')->execute([(string)td_in('id'), $ME]); td_out(['ok' => true]); }
/* ---------- база монет и результатов тестов: каталог, свои группы, итоги по каждой монете ---------- */
function cr_ensure() { static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_tkv (phone VARCHAR(40) NOT NULL, k VARCHAR(40) NOT NULL, v MEDIUMTEXT NOT NULL, updated_at INT NOT NULL DEFAULT 0, PRIMARY KEY (phone, k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_tcoinres (phone VARCHAR(40) NOT NULL, sym VARCHAR(30) NOT NULL, tf VARCHAR(6) NOT NULL, sig VARCHAR(80) NOT NULL, data MEDIUMTEXT NOT NULL, t INT NOT NULL DEFAULT 0, PRIMARY KEY (phone, sym, tf, sig), KEY idx_cr_sig (phone, sig)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); }
if ($action === 'kv_get') { cr_ensure(); $k = preg_replace('~[^a-z0-9_]~', '', (string)td_in('k')); $q = db()->prepare('SELECT v, updated_at FROM itc_tkv WHERE phone=? AND k=?'); $q->execute([$ME, $k]); $r = $q->fetch();
  td_out(['ok' => true, 'v' => $r ? json_decode($r['v'], true) : null, 't' => $r ? (int)$r['updated_at'] : 0]); }
if ($action === 'kv_set') { cr_ensure(); $k = preg_replace('~[^a-z0-9_]~', '', (string)td_in('k')); if ($k === '') td_fail('Нет ключа'); $v = json_encode(td_in('v', null), JSON_UNESCAPED_UNICODE); if (strlen($v) > 12000000) td_fail('Слишком много данных');
  db()->prepare('REPLACE INTO itc_tkv (phone,k,v,updated_at) VALUES (?,?,?,?)')->execute([$ME, $k, $v, time()]); td_out(['ok' => true]); }
if ($action === 'res_put') { cr_ensure(); $rows = (array)td_in('rows', []); $n = 0; $q = db()->prepare('REPLACE INTO itc_tcoinres (phone,sym,tf,sig,data,t) VALUES (?,?,?,?,?,?)');
  foreach ($rows as $r) { $sym = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)($r['sym'] ?? ''))); $tf = preg_replace('~[^0-9a-z]~', '', (string)($r['tf'] ?? '')); $sig = mb_substr((string)($r['sig'] ?? ''), 0, 80); if ($sym === '' || $tf === '') continue;
    $q->execute([$ME, $sym, $tf, $sig, json_encode($r['data'] ?? [], JSON_UNESCAPED_UNICODE), time()]); $n++; } td_out(['ok' => true, 'n' => $n]); }
if ($action === 'res_list') { cr_ensure(); $sig = (string)td_in('sig'); $q = db()->prepare('SELECT sym, tf, t FROM itc_tcoinres WHERE phone=? AND sig=?'); $q->execute([$ME, $sig]); td_out(['ok' => true, 'items' => $q->fetchAll()]); }
if ($action === 'res_sigs') { cr_ensure(); $q = db()->prepare('SELECT sig, COUNT(DISTINCT sym) n, MAX(t) t FROM itc_tcoinres WHERE phone=? GROUP BY sig ORDER BY t DESC'); $q->execute([$ME]); td_out(['ok' => true, 'items' => $q->fetchAll()]); }
if ($action === 'res_get') { cr_ensure(); $sig = (string)td_in('sig'); $syms = array_slice(array_map(fn($x) => strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)$x)), (array)td_in('syms', [])), 0, 800); $tfs = array_map(fn($x) => preg_replace('~[^0-9a-z]~', '', (string)$x), (array)td_in('tfs', []));
  if (!$syms || !$tfs) td_out(['ok' => true, 'items' => []]); $in1 = implode(',', array_fill(0, count($syms), '?')); $in2 = implode(',', array_fill(0, count($tfs), '?'));
  $q = db()->prepare("SELECT sym, tf, data, t FROM itc_tcoinres WHERE phone=? AND sig=? AND sym IN ($in1) AND tf IN ($in2)"); $q->execute(array_merge([$ME, $sig], $syms, $tfs));
  $out = []; foreach ($q->fetchAll() as $r) { $r['data'] = json_decode($r['data'], true); $out[] = $r; } td_out(['ok' => true, 'items' => $out]); }
td_fail('Неизвестное действие');
