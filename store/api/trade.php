<?php
// ==== ИТК · ТОРГОВЫЙ СЕРВЕР «ИТК Трейдинг» · api/trade.php · №2 (10.11.2026) ====
// Только для босса. Хранит ключи Binance в зашифрованном виде и отправляет заявки на биржу.
// Секретный ключ никогда не отдаётся обратно в приложение и не пишется в журналы.
// Стопы и тейки на фьючерсах ставятся «условными заявками» самой биржи (/fapi/v1/algoOrder) —
// они срабатывают круглосуточно, даже когда телефон выключен.
//   tr_ip        адрес сервера (его надо добавить в список разрешённых адресов ключа на Binance)
//   tr_keys      подключённые ключи (без секретов)        tr_key_add / tr_key_del
//   tr_state     баланс, позиции, открытые заявки (обычные и условные)
//   tr_order     новая заявка: рынок, лимит, триггер       tr_cancel  отмена
//   tr_sltp      стоп, тейки (лесенка), трейлинг для позиции; старые стоп/тейки по паре снимаются
//   tr_close     закрыть позицию по рынку (спот — продать монету)
//   tr_income    прибыль по дням (фьючерсы)
//   tr_coins     капитализация, объём, выпуск монет (CoinGecko, запоминается на час)
//   tr_series    закрытые сделки по паре с момента начала серии (фьючерсы): прибыль, комиссия

require_once __DIR__ . '/config.php';
@set_time_limit(60);
$action = $_GET['action'] ?? '';
$IN = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
function tr_out($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function tr_fail($m, $c = 400) { http_response_code($c); tr_out(['ok' => false, 'error' => $m]); }
function tr_in($k, $d = '') { global $IN; return $IN[$k] ?? ($_GET[$k] ?? $d); }

$S = authUser('itc');
if ((string)($S['staff_role'] ?? '') !== 'boss') tr_fail('ИТК Трейдинг доступен только руководителю', 403);
$ME = (string)($S['phone'] ?? '');

function tr_ensure() {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_trade_keys (
    id VARCHAR(20) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL, exchange VARCHAR(20) NOT NULL DEFAULT 'binance',
    kind VARCHAR(10) NOT NULL DEFAULT 'fut', name VARCHAR(80) NOT NULL DEFAULT '', api_key VARCHAR(128) NOT NULL,
    secret_enc TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, last_ok DATETIME NULL,
    KEY idx_tk_ph (phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
}
// ключ шифрования создаётся один раз на сервере и лежит в отдельном файле (в обновления не входит)
function tr_master() {
  static $k = null; if ($k !== null) return $k;
  $f = __DIR__ . '/trade_secret.php';
  if (!is_file($f)) { $raw = base64_encode(random_bytes(32)); if (@file_put_contents($f, "<?php\nreturn '" . $raw . "';\n", LOCK_EX) === false) tr_fail('Сервер не может создать файл ключа шифрования', 500); @chmod($f, 0600); }
  $v = include $f; $k = base64_decode((string)$v); if (strlen($k) !== 32) tr_fail('Повреждён файл ключа шифрования', 500); return $k;
}
function tr_enc($s) { $iv = random_bytes(12); $tag = ''; $c = openssl_encrypt($s, 'aes-256-gcm', tr_master(), OPENSSL_RAW_DATA, $iv, $tag); return base64_encode($iv . $tag . $c); }
function tr_dec($b) { $r = base64_decode($b); $iv = substr($r, 0, 12); $tag = substr($r, 12, 16); $c = substr($r, 28); $p = openssl_decrypt($c, 'aes-256-gcm', tr_master(), OPENSSL_RAW_DATA, $iv, $tag); if ($p === false) tr_fail('Не удалось расшифровать ключ', 500); return $p; }
function tr_key($id) { global $ME; tr_ensure(); $q = db()->prepare('SELECT * FROM itc_trade_keys WHERE id=? AND phone=?'); $q->execute([(string)$id, $ME]); $r = $q->fetch(); if (!$r) tr_fail('Ключ не найден', 404); return $r; }

/* ---------- запрос к Binance ---------- */
function tr_base($kind) { return $kind === 'spot' ? 'https://api.binance.com' : 'https://fapi.binance.com'; }
function tr_http($method, $url, $hdr = []) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $hdr]);
  $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  if ($b === false) tr_fail('Нет связи с Binance: ' . $err, 502);
  return [$code, json_decode($b, true), $b];
}
function tr_time($kind) {
  static $off = []; if (isset($off[$kind])) return $off[$kind];
  [$c, $j] = tr_http('GET', tr_base($kind) . ($kind === 'spot' ? '/api/v3/time' : '/fapi/v1/time'));
  return $off[$kind] = ($c === 200 && isset($j['serverTime'])) ? ((int)$j['serverTime'] - (int)round(microtime(true) * 1000)) : 0;
}
function bn($k, $method, $path, $p = [], $soft = false) {
  $kind = $k['kind']; $secret = tr_dec($k['secret_enc']);
  $p['timestamp'] = (int)round(microtime(true) * 1000) + tr_time($kind); $p['recvWindow'] = 10000;
  $qs = http_build_query($p, '', '&', PHP_QUERY_RFC3986); $sig = hash_hmac('sha256', $qs, $secret); unset($secret);
  [$c, $j, $raw] = tr_http($method, tr_base($kind) . $path . '?' . $qs . '&signature=' . $sig, ['X-MBX-APIKEY: ' . $k['api_key']]);
  if ($c >= 400 || (is_array($j) && isset($j['code']) && (int)$j['code'] < 0)) {
    $msg = tr_bnmsg($j['code'] ?? 0, $j['msg'] ?? ('ошибка ' . $c));
    if ($soft) return ['_err' => $msg]; tr_fail($msg, 400);
  }
  return $j;
}
function tr_bnmsg($code, $msg) {
  $m = [-2015 => 'Binance не принял ключ: проверьте ключ, права («Чтение», «Торговля», для фьючерсов — «Фьючерсы») и список разрешённых адресов', -2014 => 'Ключ неверного формата', -1022 => 'Неверная подпись — проверьте секретный ключ',
    -2019 => 'Недостаточно средств для залога', -2010 => 'Биржа отклонила заявку: ' . $msg, -1013 => 'Неверное количество или цена: ' . $msg, -4164 => 'Сумма сделки меньше минимальной (обычно от 5 USDT)', -2021 => 'Цена срабатывания уже пройдена — заявка сработала бы сразу', -1021 => 'Время сервера разошлось с биржей, повторите', -4061 => 'Режим позиций на бирже не совпадает (нужен односторонний режим)'];
  return $m[(int)$code] ?? ('Binance: ' . $msg);
}
// правила пары: шаг цены и количества, минимальная сумма — кэш на час
function tr_rules($kind, $sym) {
  $f = sys_get_temp_dir() . '/itc_trade_ei_' . $kind . '.json';
  $d = is_file($f) && filemtime($f) > time() - 3600 ? json_decode(@file_get_contents($f), true) : null;
  if (!$d) { [$c, $j] = tr_http('GET', tr_base($kind) . ($kind === 'spot' ? '/api/v3/exchangeInfo?permissions=SPOT' : '/fapi/v1/exchangeInfo')); if ($c !== 200) tr_fail('Не удалось получить правила пар с биржи', 502);
    $d = []; foreach ($j['symbols'] as $s) { $r = ['tick' => '0.01', 'step' => '0.001', 'min' => 5]; foreach ($s['filters'] as $fl) { if ($fl['filterType'] === 'PRICE_FILTER') $r['tick'] = $fl['tickSize']; if ($fl['filterType'] === 'LOT_SIZE') $r['step'] = $fl['stepSize']; if ($fl['filterType'] === 'MIN_NOTIONAL' || $fl['filterType'] === 'NOTIONAL') $r['min'] = (float)($fl['notional'] ?? $fl['minNotional'] ?? 5); } $d[$s['symbol']] = $r; }
    @file_put_contents($f, json_encode($d)); }
  if (!isset($d[$sym])) tr_fail('Пара ' . $sym . ' не торгуется на ' . ($kind === 'spot' ? 'споте' : 'фьючерсах'), 400);
  return $d[$sym];
}
function tr_round($v, $step) { $step = (float)$step; if ($step <= 0) return (string)$v; $dec = max(0, (int)round(-log10($step))); $r = floor(((float)$v + 1e-12) / $step) * $step; return number_format($r, $dec, '.', ''); }
function tr_sym() { $s = strtoupper(preg_replace('~[^A-Za-z0-9]~', '', (string)tr_in('symbol'))); if ($s === '') tr_fail('Не указана пара'); if (!str_ends_with($s, 'USDT')) $s .= 'USDT'; return $s; }

/* ---------- адрес сервера ---------- */
if ($action === 'tr_ip') {
  [$c, $j, $raw] = tr_http('GET', 'https://api.ipify.org?format=json');
  tr_out(['ok' => true, 'ip' => $j['ip'] ?? trim((string)$raw)]);
}
/* ---------- №582 · другие биржи, которые принимают российский паспорт (ноябрь 2026): ключи на спот и фьючерсы ----------
   Торговля из приложения пока идёт через Binance; ключи остальных бирж хранятся зашифрованными и проверяются
   (чтение счёта и что право «Вывод» выключено), чтобы потом подключить торговлю на них без повторного ввода. */
const TR_EXCH = ['binance' => 'Binance', 'bybit' => 'Bybit', 'okx' => 'OKX', 'bingx' => 'BingX', 'phemex' => 'Phemex', 'pionex' => 'Pionex', 'toobit' => 'Toobit'];
function tr_ex_http($method, $url, $hdr = []) {   // мягкий запрос: нет связи — null, а не ошибка
  $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => $hdr]);
  $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $b === false ? null : [$code, json_decode((string)$b, true), (string)$b]; }
/* [true|false|null, сообщение]: true — ключ принят и вывод выключен; false — биржа отказала (ошибка ключа/прав); null — проверить не удалось */
function tr_ex_check($ex, $kind, $key, $sec, $pass) {
  $ms = (string)round(microtime(true) * 1000); $h = fn($m) => hash_hmac('sha256', $m, $sec);
  if ($ex === 'bybit') {
    $q = 'accountType=UNIFIED'; $r = tr_ex_http('GET', "https://api.bybit.com/v5/account/wallet-balance?$q", ['X-BAPI-API-KEY: ' . $key, 'X-BAPI-TIMESTAMP: ' . $ms, 'X-BAPI-RECV-WINDOW: 10000', 'X-BAPI-SIGN: ' . $h($ms . $key . '10000' . $q)]);
    if (!$r || !is_array($r[1])) return [null, 'Bybit не ответил'];
    if ((int)($r[1]['retCode'] ?? -1) !== 0) return [false, 'Bybit: ' . ($r[1]['retMsg'] ?? 'ключ не принят')];
    $ms = (string)round(microtime(true) * 1000); $r = tr_ex_http('GET', 'https://api.bybit.com/v5/user/query-api', ['X-BAPI-API-KEY: ' . $key, 'X-BAPI-TIMESTAMP: ' . $ms, 'X-BAPI-RECV-WINDOW: 10000', 'X-BAPI-SIGN: ' . $h($ms . $key . '10000')]);
    if ($r && is_array($r[1]) && in_array('Withdraw', (array)($r[1]['result']['permissions']['Wallet'] ?? []), true)) return [false, 'у ключа включено право «Вывод» — выключите его на Bybit'];
    return [true, ''];
  }
  if ($ex === 'okx') {
    $ts = gmdate('Y-m-d\TH:i:s') . '.' . substr($ms, -3) . 'Z'; $sg = fn($p) => base64_encode(hash_hmac('sha256', $ts . 'GET' . $p, $sec, true));
    $hd = fn($p) => ['OK-ACCESS-KEY: ' . $key, 'OK-ACCESS-SIGN: ' . $sg($p), 'OK-ACCESS-TIMESTAMP: ' . $ts, 'OK-ACCESS-PASSPHRASE: ' . $pass];
    $r = tr_ex_http('GET', 'https://www.okx.com/api/v5/account/config', $hd('/api/v5/account/config'));
    if (!$r || !is_array($r[1])) return [null, 'OKX не ответил'];
    if ((string)($r[1]['code'] ?? '') !== '0') return [false, 'OKX: ' . ($r[1]['msg'] ?? 'ключ не принят') . ' (проверьте и пароль ключа)'];
    if (strpos((string)($r[1]['data'][0]['perm'] ?? ''), 'withdraw') !== false) return [false, 'у ключа включено право «Вывод» — выключите его на OKX'];
    return [true, ''];
  }
  if ($ex === 'bingx') {
    $q = 'timestamp=' . $ms; $path = $kind === 'spot' ? '/openApi/spot/v1/account/balance' : '/openApi/swap/v2/user/balance';
    $r = tr_ex_http('GET', 'https://open-api.bingx.com' . $path . '?' . $q . '&signature=' . $h($q), ['X-BX-APIKEY: ' . $key]);
    if (!$r || !is_array($r[1])) return [null, 'BingX не ответил'];
    return (int)($r[1]['code'] ?? -1) === 0 ? [true, ''] : [false, 'BingX: ' . ($r[1]['msg'] ?? 'ключ не принят')];
  }
  if ($ex === 'phemex') {
    $exp = (string)(time() + 60); [$path, $q] = $kind === 'spot' ? ['/spot/wallets', ''] : ['/g-accounts/accountPositions', 'currency=USDT'];
    $r = tr_ex_http('GET', 'https://api.phemex.com' . $path . ($q !== '' ? '?' . $q : ''), ['x-phemex-access-token: ' . $key, 'x-phemex-request-expiry: ' . $exp, 'x-phemex-request-signature: ' . $h($path . $q . $exp)]);
    if (!$r || !is_array($r[1])) return [null, 'Phemex не ответил'];
    return (int)($r[1]['code'] ?? -1) === 0 ? [true, ''] : [false, 'Phemex: ' . ($r[1]['msg'] ?? 'ключ не принят')];
  }
  if ($ex === 'pionex') {
    $pq = '/api/v1/account/balances?timestamp=' . $ms; $r = tr_ex_http('GET', 'https://api.pionex.com' . $pq, ['PIONEX-KEY: ' . $key, 'PIONEX-SIGNATURE: ' . $h('GET' . $pq)]);
    if (!$r || !is_array($r[1])) return [null, 'Pionex не ответил'];
    return !empty($r[1]['result']) ? [true, ''] : [false, 'Pionex: ' . ($r[1]['message'] ?? 'ключ не принят')];
  }
  if ($ex === 'toobit') {
    $q = 'timestamp=' . $ms; $path = $kind === 'spot' ? '/api/v1/account' : '/api/v1/futures/balance';
    $r = tr_ex_http('GET', 'https://api.toobit.com' . $path . '?' . $q . '&signature=' . $h($q), ['X-BB-APIKEY: ' . $key]);
    if (!$r || !is_array($r[1])) return [null, 'Toobit не ответил'];
    return isset($r[1]['code']) && (int)$r[1]['code'] !== 0 && (int)$r[1]['code'] !== 200 ? [false, 'Toobit: ' . ($r[1]['msg'] ?? 'ключ не принят')] : [true, ''];
  }
  return [null, ''];
}
/* №582 · какие монеты (к USDT) есть на бирже — спот или фьючерсы; публичные списки бирж, запоминаем на 6 часов */
function tr_ex_coins($ex, $mkt) {
  $f = sys_get_temp_dir() . '/itc_excoins_' . $ex . '_' . $mkt . '.json';
  if (is_file($f) && filemtime($f) > time() - 21600) { $j = json_decode((string)file_get_contents($f), true); if (is_array($j) && $j) return $j; }
  $o = []; $fut = $mkt === 'fut'; $g = function ($u) { $r = tr_ex_http('GET', $u); return $r && is_array($r[1]) ? $r[1] : null; };
  if ($ex === 'bybit') { $cur = ''; for ($i = 0; $i < 5; $i++) { $j = $g('https://api.bybit.com/v5/market/instruments-info?category=' . ($fut ? 'linear' : 'spot') . '&limit=1000' . ($cur !== '' ? '&cursor=' . rawurlencode($cur) : '')); if (!$j) break;
      foreach ((array)($j['result']['list'] ?? []) as $x) if (($x['quoteCoin'] ?? '') === 'USDT' && ($x['status'] ?? 'Trading') === 'Trading') $o[] = (string)$x['baseCoin']; $cur = (string)($j['result']['nextPageCursor'] ?? ''); if ($cur === '') break; } }
  elseif ($ex === 'okx') { $j = $g('https://www.okx.com/api/v5/public/instruments?instType=' . ($fut ? 'SWAP' : 'SPOT'));
    foreach ((array)($j['data'] ?? []) as $x) { $id = (string)($x['instId'] ?? ''); if (preg_match($fut ? '~^([A-Z0-9]+)-USDT-SWAP$~' : '~^([A-Z0-9]+)-USDT$~', $id, $m) && ($x['state'] ?? 'live') === 'live') $o[] = $m[1]; } }
  elseif ($ex === 'bingx') { $j = $g($fut ? 'https://open-api.bingx.com/openApi/swap/v2/quote/contracts' : 'https://open-api.bingx.com/openApi/spot/v1/common/symbols');
    $L = $fut ? (array)($j['data'] ?? []) : (array)($j['data']['symbols'] ?? []); foreach ($L as $x) if (preg_match('~^([A-Z0-9]+)-USDT$~', (string)($x['symbol'] ?? ''), $m)) $o[] = $m[1]; }
  elseif ($ex === 'phemex') { $j = $g('https://api.phemex.com/public/products');
    foreach ((array)($j['data']['products'] ?? []) as $x) { $t = (string)($x['type'] ?? '');
      if (!$fut && $t === 'Spot' && ($x['quoteCurrency'] ?? '') === 'USDT') $o[] = (string)$x['baseCurrency'];
      if ($fut && $t === 'PerpetualV2' && preg_match('~^([A-Z0-9]+)USDT$~', (string)($x['symbol'] ?? ''), $m)) $o[] = $m[1]; }
    foreach ((array)($j['data']['perpProductsV2'] ?? []) as $x) if ($fut && preg_match('~^([A-Z0-9]+)USDT$~', (string)($x['symbol'] ?? ''), $m)) $o[] = $m[1]; }
  elseif ($ex === 'pionex') { $j = $g('https://api.pionex.com/api/v1/common/symbols' . ($fut ? '?type=PERP' : ''));
    foreach ((array)($j['data']['symbols'] ?? []) as $x) { $t = strtoupper((string)($x['type'] ?? 'SPOT')); if (($x['quoteCurrency'] ?? '') === 'USDT' && ($fut ? $t === 'PERP' : $t === 'SPOT')) $o[] = (string)$x['baseCurrency']; } }
  elseif ($ex === 'toobit') { $j = $g('https://api.toobit.com/api/v1/exchangeInfo');
    foreach ((array)($j[$fut ? 'contracts' : 'symbols'] ?? []) as $x) { $b = (string)($x['baseAsset'] ?? $x['underlying'] ?? ''); $q = (string)($x['quoteAsset'] ?? $x['marginToken'] ?? 'USDT'); if ($b !== '' && $q === 'USDT') $o[] = $b; } }
  $o = array_values(array_unique(array_map(function ($x) { return strtoupper(preg_replace('~[^A-Za-z0-9]~', '', $x)); }, $o)));
  if ($o) @file_put_contents($f, json_encode($o)); return $o;
}
if ($action === 'tr_ex_coins') {
  $ex = (string)tr_in('ex'); if (!isset(TR_EXCH[$ex]) || $ex === 'binance') tr_fail('Неизвестная биржа');
  $L = tr_ex_coins($ex, tr_in('mkt') === 'spot' ? 'spot' : 'fut');
  tr_out(['ok' => true, 'ex' => $ex, 'coins' => $L, 'err' => $L ? '' : 'биржа не отдала список монет — показываю все']);
}
/* ---------- ключи ---------- */
if ($action === 'tr_keys') {
  tr_ensure(); $q = db()->prepare('SELECT id,exchange,kind,name,api_key,created_at,last_ok FROM itc_trade_keys WHERE phone=? ORDER BY created_at'); $q->execute([$ME]);
  $out = []; foreach ($q->fetchAll() as $r) { $r['api_key'] = substr($r['api_key'], 0, 4) . '…' . substr($r['api_key'], -4); $out[] = $r; }
  tr_out(['ok' => true, 'keys' => $out]);
}
if ($action === 'tr_key_add') {
  tr_ensure(); $ex = (string)tr_in('exchange', 'binance'); if (!isset(TR_EXCH[$ex])) tr_fail('Такой биржи нет в списке');
  if ($ex !== 'binance') {   // №582
    try { db()->exec("ALTER TABLE itc_trade_keys ADD COLUMN pass_enc TEXT NULL"); } catch (Throwable $e) {}
    try { db()->exec("ALTER TABLE itc_trade_keys ADD COLUMN state VARCHAR(20) NOT NULL DEFAULT ''"); } catch (Throwable $e) {}
    $kind = tr_in('kind') === 'spot' ? 'spot' : 'fut'; $key = trim((string)tr_in('key')); $sec = trim((string)tr_in('secret')); $pass = trim((string)tr_in('pass'));
    $name = mb_substr(trim((string)tr_in('name')), 0, 80) ?: (TR_EXCH[$ex] . ' ' . ($kind === 'spot' ? 'спот' : 'фьючерсы'));
    if (!preg_match('~^[A-Za-z0-9_\-]{10,200}$~', $key) || !preg_match('~^[A-Za-z0-9_\-=+/]{10,300}$~', $sec)) tr_fail('Ключ или секретный ключ вставлены не полностью');
    if ($ex === 'okx' && $pass === '') tr_fail('Для OKX нужен ещё пароль ключа (Passphrase) — его задают при создании ключа');
    [$ok, $msg] = tr_ex_check($ex, $kind, $key, $sec, $pass);
    if ($ok === false) tr_fail($msg);
    $id = 'k' . bin2hex(random_bytes(6));
    db()->prepare('INSERT INTO itc_trade_keys (id,phone,exchange,kind,name,api_key,secret_enc,pass_enc,state,last_ok) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute([$id, $ME, $ex, $kind, $name, $key, tr_enc($sec), $pass !== '' ? tr_enc($pass) : null, $ok ? 'ok' : 'unchecked', $ok ? date('Y-m-d H:i:s') : null]);
    unset($sec, $pass);
    tr_out(['ok' => true, 'id' => $id, 'name' => $name, 'kind' => $kind, 'exchange' => $ex, 'checked' => $ok === true, 'note' => $ok === true ? 'ключ проверен: счёт читается, вывод выключен' : 'биржа сейчас не ответила — ключ сохранён, проверим позже']);
  }
  $kind = tr_in('kind') === 'spot' ? 'spot' : 'fut'; $key = trim((string)tr_in('key')); $sec = trim((string)tr_in('secret')); $name = mb_substr(trim((string)tr_in('name')), 0, 80) ?: ('Binance ' . ($kind === 'spot' ? 'спот' : 'фьючерсы'));
  if (!preg_match('~^[A-Za-z0-9]{20,128}$~', $key) || !preg_match('~^[A-Za-z0-9]{20,128}$~', $sec)) tr_fail('Ключ или секретный ключ вставлены не полностью');
  $k = ['kind' => $kind, 'api_key' => $key, 'secret_enc' => tr_enc($sec)]; unset($sec);
  // проверяем ключ: чтение счёта и права
  $acc = bn($k, 'GET', $kind === 'spot' ? '/api/v3/account' : '/fapi/v2/account');
  $perm = bn(['kind' => 'spot'] + $k, 'GET', '/sapi/v1/account/apiRestrictions', [], true);
  if (is_array($perm) && !isset($perm['_err'])) {
    if (!empty($perm['enableWithdrawals'])) tr_fail('У ключа включено право «Вывод средств». Отключите его на Binance — так безопаснее — и вставьте ключ снова');
    if ($kind === 'fut' && empty($perm['enableFutures'])) tr_fail('У ключа не включено право «Фьючерсы»');
    if ($kind === 'spot' && empty($perm['enableSpotAndMarginTrading'])) tr_fail('У ключа не включено право «Спот и маржинальная торговля»');
  }
  $id = 'k' . bin2hex(random_bytes(6));
  db()->prepare('INSERT INTO itc_trade_keys (id,phone,exchange,kind,name,api_key,secret_enc,last_ok) VALUES (?,?,?,?,?,?,?,NOW())')->execute([$id, $ME, $ex, $kind, $name, $key, $k['secret_enc']]);
  tr_out(['ok' => true, 'id' => $id, 'name' => $name, 'kind' => $kind]);
}
if ($action === 'tr_key_del') { tr_key(tr_in('id')); db()->prepare('DELETE FROM itc_trade_keys WHERE id=? AND phone=?')->execute([(string)tr_in('id'), $ME]); tr_out(['ok' => true]); }

/* ---------- дальше — действия по ключу ---------- */
if ($action === 'tr_coins') {
  // сведения о монетах: капитализация, объём торгов, сколько монет выпущено — с CoinGecko, раз в час
  $f = __DIR__ . '/trade_coins.json';
  if (is_file($f) && time() - filemtime($f) < 3600 && empty($IN['force'])) { header('Content-Type: application/json; charset=utf-8'); readfile($f); exit; }
  $out = [];
  for ($pg = 1; $pg <= 4; $pg++) {
    $u = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&order=market_cap_desc&per_page=250&page=' . $pg . '&price_change_percentage=7d,30d,1y';
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_USERAGENT => 'ITC-Trading/1.0', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $j = json_decode((string)$r, true);
    if ($code !== 200 || !is_array($j)) break;
    foreach ($j as $c) {
      $s = strtoupper((string)($c['symbol'] ?? '')); if ($s === '' || isset($out[$s])) continue;
      $out[$s] = ['n' => $c['name'] ?? '', 'r' => $c['market_cap_rank'] ?? null, 'mc' => $c['market_cap'] ?? null, 'fdv' => $c['fully_diluted_valuation'] ?? null,
        'v' => $c['total_volume'] ?? null, 'cs' => $c['circulating_supply'] ?? null, 'ts' => $c['total_supply'] ?? null, 'ms' => $c['max_supply'] ?? null,
        'ath' => $c['ath'] ?? null, 'athc' => $c['ath_change_percentage'] ?? null, 'athd' => substr((string)($c['ath_date'] ?? ''), 0, 10), 'atld' => substr((string)($c['atl_date'] ?? ''), 0, 10),
        'c7' => $c['price_change_percentage_7d_in_currency'] ?? null, 'c30' => $c['price_change_percentage_30d_in_currency'] ?? null, 'c1y' => $c['price_change_percentage_1y_in_currency'] ?? null];
    }
    usleep(1500000);
  }
  if (!$out) { if (is_file($f)) { header('Content-Type: application/json; charset=utf-8'); readfile($f); exit; } tr_fail('Сведения о монетах сейчас недоступны, попробуйте позже'); }
  $js = json_encode(['ok' => true, 't' => time(), 'coins' => $out], JSON_UNESCAPED_UNICODE);
  @file_put_contents($f, $js); header('Content-Type: application/json; charset=utf-8'); echo $js; exit;
}
$K = tr_key(tr_in('id')); $KIND = $K['kind'];

if ($action === 'tr_state') {
  if ($KIND === 'spot') {
    $a = bn($K, 'GET', '/api/v3/account', ['omitZeroBalances' => 'true']);
    $bal = []; foreach ($a['balances'] ?? [] as $b) { $f = (float)$b['free']; $l = (float)$b['locked']; if ($f + $l > 0) $bal[] = ['a' => $b['asset'], 'free' => $f, 'locked' => $l]; }
    $ord = bn($K, 'GET', '/api/v3/openOrders');
    $o = []; foreach ($ord as $x) $o[] = ['id' => (string)$x['orderId'], 'sym' => $x['symbol'], 'side' => $x['side'], 'type' => $x['type'], 'px' => (float)$x['price'], 'stop' => (float)($x['stopPrice'] ?? 0), 'qty' => (float)$x['origQty'], 'filled' => (float)$x['executedQty'], 'list' => (int)($x['orderListId'] ?? -1), 't' => (int)$x['time']];
    db()->prepare('UPDATE itc_trade_keys SET last_ok=NOW() WHERE id=?')->execute([$K['id']]);
    tr_out(['ok' => true, 'kind' => 'spot', 'balances' => $bal, 'orders' => $o, 'algo' => [], 'positions' => []]);
  }
  $a = bn($K, 'GET', '/fapi/v2/account');
  $pos = []; foreach ($a['positions'] ?? [] as $p) { $amt = (float)$p['positionAmt']; if ($amt == 0) continue; $pos[] = ['sym' => $p['symbol'], 'amt' => $amt, 'ep' => (float)$p['entryPrice'], 'upnl' => (float)$p['unrealizedProfit'], 'lev' => (int)$p['leverage'], 'margin' => (float)($p['initialMargin'] ?? 0), 'side' => $p['positionSide'] ?? 'BOTH'];}
  $ord = bn($K, 'GET', '/fapi/v1/openOrders');
  $o = []; foreach ($ord as $x) $o[] = ['id' => (string)$x['orderId'], 'sym' => $x['symbol'], 'side' => $x['side'], 'type' => $x['type'], 'px' => (float)$x['price'], 'stop' => (float)($x['stopPrice'] ?? 0), 'qty' => (float)$x['origQty'], 'filled' => (float)$x['executedQty'], 'reduce' => !empty($x['reduceOnly']), 'close' => !empty($x['closePosition']), 't' => (int)$x['time']];
  $al = bn($K, 'GET', '/fapi/v1/openAlgoOrders', [], true); $g = [];
  if (is_array($al) && !isset($al['_err'])) foreach (($al['orders'] ?? $al) as $x) { if (!is_array($x)) continue; $g[] = ['id' => (string)($x['algoId'] ?? ''), 'sym' => $x['symbol'] ?? '', 'side' => $x['side'] ?? '', 'type' => $x['orderType'] ?? ($x['type'] ?? ''), 'stop' => (float)($x['triggerPrice'] ?? 0), 'qty' => (float)($x['quantity'] ?? 0), 'close' => !empty($x['closePosition']) && $x['closePosition'] !== 'false', 'cb' => (float)($x['callbackRate'] ?? 0), 'act' => (float)($x['activatePrice'] ?? 0), 't' => (int)($x['createTime'] ?? 0)]; }
  db()->prepare('UPDATE itc_trade_keys SET last_ok=NOW() WHERE id=?')->execute([$K['id']]);
  tr_out(['ok' => true, 'kind' => 'fut', 'wallet' => (float)$a['totalWalletBalance'], 'equity' => (float)$a['totalMarginBalance'], 'avail' => (float)$a['availableBalance'], 'upnl' => (float)$a['totalUnrealizedProfit'],
    'balances' => array_values(array_map(fn($x) => ['a' => $x['asset'], 'free' => (float)$x['availableBalance'], 'locked' => (float)$x['walletBalance'] - (float)$x['availableBalance'], 'wallet' => (float)$x['walletBalance']], array_filter($a['assets'] ?? [], fn($x) => (float)$x['walletBalance'] != 0))),
    'positions' => $pos, 'orders' => $o, 'algo' => $g]);
}

if ($action === 'tr_order') {
  $sym = tr_sym(); $R = tr_rules($KIND, $sym); $side = tr_in('side') === 'SELL' ? 'SELL' : 'BUY'; $type = (string)tr_in('type', 'MARKET');
  $qty = tr_round(tr_in('qty'), $R['step']); if ((float)$qty <= 0) tr_fail('Количество меньше минимального шага для ' . $sym);
  $px = (float)tr_in('price'); $trig = (float)tr_in('trigger');
  if ($KIND === 'fut') {
    $lev = (int)tr_in('lev', 0); if ($lev > 0) bn($K, 'POST', '/fapi/v1/leverage', ['symbol' => $sym, 'leverage' => min(125, $lev)]);
    if ($trig > 0) { // вход по триггеру — условная заявка биржи
      $p = ['algoType' => 'CONDITIONAL', 'symbol' => $sym, 'side' => $side, 'quantity' => $qty, 'triggerPrice' => tr_round($trig, $R['tick']), 'workingType' => 'CONTRACT_PRICE'];
      if ($px > 0) { $p['type'] = 'STOP'; $p['price'] = tr_round($px, $R['tick']); $p['timeInForce'] = 'GTC'; } else $p['type'] = 'STOP_MARKET';
      $r = bn($K, 'POST', '/fapi/v1/algoOrder', $p); tr_out(['ok' => true, 'algo' => $r['algoId'] ?? null]);
    }
    $p = ['symbol' => $sym, 'side' => $side, 'type' => $type === 'LIMIT' ? 'LIMIT' : 'MARKET', 'quantity' => $qty, 'newOrderRespType' => 'RESULT'];
    if ($type === 'LIMIT') { $p['price'] = tr_round($px, $R['tick']); $p['timeInForce'] = 'GTC'; }
    if (tr_in('reduce')) $p['reduceOnly'] = 'true';
    $r = bn($K, 'POST', '/fapi/v1/order', $p); tr_out(['ok' => true, 'order' => ['id' => (string)$r['orderId'], 'status' => $r['status'], 'avg' => (float)($r['avgPrice'] ?? 0), 'filled' => (float)($r['executedQty'] ?? 0)]]);
  }
  // спот
  if ($trig > 0) { if ($px <= 0) $px = $side === 'BUY' ? $trig * 1.002 : $trig * 0.998;
    $r = bn($K, 'POST', '/api/v3/order', ['symbol' => $sym, 'side' => $side, 'type' => 'STOP_LOSS_LIMIT', 'quantity' => $qty, 'price' => tr_round($px, $R['tick']), 'stopPrice' => tr_round($trig, $R['tick']), 'timeInForce' => 'GTC']); tr_out(['ok' => true, 'order' => ['id' => (string)$r['orderId'], 'status' => $r['status'] ?? 'NEW']]); }
  $p = ['symbol' => $sym, 'side' => $side, 'type' => $type === 'LIMIT' ? 'LIMIT' : 'MARKET', 'quantity' => $qty, 'newOrderRespType' => 'FULL'];
  if ($type === 'LIMIT') { $p['price'] = tr_round($px, $R['tick']); $p['timeInForce'] = 'GTC'; }
  $r = bn($K, 'POST', '/api/v3/order', $p); $avg = 0; $fq = (float)($r['executedQty'] ?? 0); if ($fq > 0) $avg = (float)$r['cummulativeQuoteQty'] / $fq;
  tr_out(['ok' => true, 'order' => ['id' => (string)$r['orderId'], 'status' => $r['status'], 'avg' => $avg, 'filled' => $fq]]);
}

if ($action === 'tr_cancel') {
  $sym = tr_sym(); $oid = (string)tr_in('order'); $aid = (string)tr_in('algo');
  if ($aid !== '') { bn($K, 'DELETE', '/fapi/v1/algoOrder', ['algoId' => $aid]); tr_out(['ok' => true]); }
  if ($oid === 'all') { bn($K, 'DELETE', $KIND === 'spot' ? '/api/v3/openOrders' : '/fapi/v1/allOpenOrders', ['symbol' => $sym], true); if ($KIND === 'fut') bn($K, 'DELETE', '/fapi/v1/algoOpenOrders', ['symbol' => $sym], true); tr_out(['ok' => true]); }
  bn($K, 'DELETE', $KIND === 'spot' ? '/api/v3/order' : '/fapi/v1/order', ['symbol' => $sym, 'orderId' => $oid]); tr_out(['ok' => true]);
}

if ($action === 'tr_close') {
  $sym = tr_sym(); $R = tr_rules($KIND, $sym);
  if ($KIND === 'fut') {
    $a = bn($K, 'GET', '/fapi/v2/positionRisk', ['symbol' => $sym]); $amt = 0; foreach ($a as $p) $amt += (float)$p['positionAmt'];
    if ($amt == 0) tr_fail('Позиции по ' . $sym . ' нет');
    bn($K, 'DELETE', '/fapi/v1/algoOpenOrders', ['symbol' => $sym], true);
    $r = bn($K, 'POST', '/fapi/v1/order', ['symbol' => $sym, 'side' => $amt > 0 ? 'SELL' : 'BUY', 'type' => 'MARKET', 'quantity' => tr_round(abs($amt), $R['step']), 'reduceOnly' => 'true', 'newOrderRespType' => 'RESULT']);
    tr_out(['ok' => true, 'avg' => (float)($r['avgPrice'] ?? 0)]);
  }
  $base = substr($sym, 0, -4); $a = bn($K, 'GET', '/api/v3/account'); $free = 0; foreach ($a['balances'] as $b) if ($b['asset'] === $base) $free = (float)$b['free'];
  $q = tr_round($free, $R['step']); if ((float)$q <= 0) tr_fail('Свободных ' . $base . ' для продажи нет');
  $r = bn($K, 'POST', '/api/v3/order', ['symbol' => $sym, 'side' => 'SELL', 'type' => 'MARKET', 'quantity' => $q]); tr_out(['ok' => true]);
}

if ($action === 'tr_sltp') {
  // позиция: side (1 — лонг, -1 — шорт), qty; sl (цена), trail (% отката), tps: [{px, pct}]
  $sym = tr_sym(); $R = tr_rules($KIND, $sym); $dir = (int)tr_in('side', 1) >= 0 ? 1 : -1; $close = $dir > 0 ? 'SELL' : 'BUY';
  $qty = (float)tr_in('qty'); $sl = (float)tr_in('sl'); $tr = (float)tr_in('trail'); $tps = is_array(tr_in('tps', [])) ? tr_in('tps', []) : [];
  $done = []; $errs = [];
  if ($KIND === 'fut') {
    // снимаем прежние стопы и тейки по паре
    $al = bn($K, 'GET', '/fapi/v1/openAlgoOrders', ['symbol' => $sym], true);
    if (is_array($al) && !isset($al['_err'])) foreach (($al['orders'] ?? $al) as $x) if (is_array($x) && in_array($x['orderType'] ?? ($x['type'] ?? ''), ['STOP_MARKET', 'TAKE_PROFIT_MARKET', 'TRAILING_STOP_MARKET'], true)) bn($K, 'DELETE', '/fapi/v1/algoOrder', ['algoId' => $x['algoId']], true);
    $base = ['algoType' => 'CONDITIONAL', 'symbol' => $sym, 'side' => $close, 'workingType' => 'MARK_PRICE', 'priceProtect' => 'true'];
    if ($tr > 0) { $tb = $base; unset($tb['priceProtect']); $r = bn($K, 'POST', '/fapi/v1/algoOrder', $tb + ['type' => 'TRAILING_STOP_MARKET', 'quantity' => tr_round($qty, $R['step']), 'reduceOnly' => 'true', 'callbackRate' => max(0.1, min(10, round($tr, 1)))] + ($sl > 0 ? ['activatePrice' => tr_round($sl, $R['tick'])] : []), true); isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'трейлинг-стоп'; }
    elseif ($sl > 0) { $r = bn($K, 'POST', '/fapi/v1/algoOrder', $base + ['type' => 'STOP_MARKET', 'triggerPrice' => tr_round($sl, $R['tick']), 'closePosition' => 'true'], true); isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'стоп-лосс'; }
    $tps = array_values(array_filter($tps, fn($t) => (float)($t['px'] ?? 0) > 0)); usort($tps, fn($a, $b) => $dir > 0 ? ((float)$a['px'] <=> (float)$b['px']) : ((float)$b['px'] <=> (float)$a['px']));
    $n = count($tps); $left = $qty;
    foreach (array_values($tps) as $i => $t) { $px = (float)($t['px'] ?? 0); if ($px <= 0) continue; $last = $i === $n - 1;
      $p = $base + ['type' => 'TAKE_PROFIT_MARKET', 'triggerPrice' => tr_round($px, $R['tick'])];
      if ($last) $p['closePosition'] = 'true'; else { $q = tr_round($qty * (float)($t['pct'] ?? 0) / 100, $R['step']); if ((float)$q <= 0) continue; $left -= (float)$q; $p['quantity'] = $q; $p['reduceOnly'] = 'true'; }
      $r = bn($K, 'POST', '/fapi/v1/algoOrder', $p, true); isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'тейк ' . ($i + 1); }
    tr_out(['ok' => !$errs || $done, 'done' => $done, 'errors' => $errs]);
  }
  // спот: снимаем прежние продажи по паре и ставим заново
  bn($K, 'DELETE', '/api/v3/openOrders', ['symbol' => $sym], true);
  $q = tr_round($qty, $R['step']); $tp = array_values(array_filter($tps, fn($t) => (float)($t['px'] ?? 0) > 0)); usort($tp, fn($a, $b) => (float)$a['px'] <=> (float)$b['px']);
  if ($sl > 0 && count($tp) === 1) { // стоп и тейк сразу — связанная пара заявок (OCO)
    $r = bn($K, 'POST', '/api/v3/orderList/oco', ['symbol' => $sym, 'side' => 'SELL', 'quantity' => $q, 'aboveType' => 'LIMIT_MAKER', 'abovePrice' => tr_round($tp[0]['px'], $R['tick']), 'belowType' => 'STOP_LOSS_LIMIT', 'belowStopPrice' => tr_round($sl, $R['tick']), 'belowPrice' => tr_round($sl * 0.995, $R['tick']), 'belowTimeInForce' => 'GTC'], true);
    isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'стоп и тейк'; tr_out(['ok' => !$errs, 'done' => $done, 'errors' => $errs]); }
  if ($tp) { $left = (float)$q; $n = count($tp); foreach ($tp as $i => $t) { $qq = $i === $n - 1 ? tr_round($left, $R['step']) : tr_round((float)$q * (float)($t['pct'] ?? 0) / 100, $R['step']); if ((float)$qq <= 0) continue; $left -= (float)$qq;
      $r = bn($K, 'POST', '/api/v3/order', ['symbol' => $sym, 'side' => 'SELL', 'type' => 'LIMIT', 'timeInForce' => 'GTC', 'quantity' => $qq, 'price' => tr_round($t['px'], $R['tick'])], true); isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'тейк ' . ($i + 1); }
    if ($sl > 0) $errs[] = 'На споте стоп вместе с несколькими тейками биржа не принимает — поставлены только тейки';
  } elseif ($sl > 0) { $r = bn($K, 'POST', '/api/v3/order', ['symbol' => $sym, 'side' => 'SELL', 'type' => 'STOP_LOSS_LIMIT', 'timeInForce' => 'GTC', 'quantity' => $q, 'stopPrice' => tr_round($sl, $R['tick']), 'price' => tr_round($sl * 0.995, $R['tick'])], true); isset($r['_err']) ? $errs[] = $r['_err'] : $done[] = 'стоп-лосс'; }
  tr_out(['ok' => !$errs || $done, 'done' => $done, 'errors' => $errs]);
}

if ($action === 'tr_income') {
  if ($KIND !== 'fut') tr_out(['ok' => true, 'days' => []]);
  $from = (int)round(microtime(true) * 1000) - 90 * 86400000; $rows = bn($K, 'GET', '/fapi/v1/income', ['startTime' => $from, 'limit' => 1000]);
  $d = []; foreach ($rows as $r) { if (!in_array($r['incomeType'], ['REALIZED_PNL', 'COMMISSION', 'FUNDING_FEE'], true)) continue; $day = gmdate('Y-m-d', (int)($r['time'] / 1000)); $d[$day] = ($d[$day] ?? 0) + (float)$r['income']; }
  ksort($d); tr_out(['ok' => true, 'days' => $d]);
}
if ($action === 'tr_series') {
  if ($KIND !== 'fut') tr_out(['ok' => true, 'closes' => [], 'fee' => 0]);
  $sym = tr_sym(); $from = max(0, (int)tr_in('from', 0));
  $rows = bn($K, 'GET', '/fapi/v1/income', ['symbol' => $sym, 'startTime' => $from, 'limit' => 1000]);
  $cl = []; $fee = 0.0;
  foreach ($rows as $r) {
    $t = (int)$r['time']; $v = (float)$r['income'];
    if ($r['incomeType'] === 'COMMISSION' || $r['incomeType'] === 'FUNDING_FEE') { $fee += $v; continue; }
    if ($r['incomeType'] !== 'REALIZED_PNL') continue;
    $n = count($cl);
    if ($n && $t - $cl[$n - 1]['t'] < 3000) $cl[$n - 1]['pnl'] += $v; else $cl[] = ['t' => $t, 'pnl' => $v];
  }
  tr_out(['ok' => true, 'closes' => $cl, 'fee' => $fee]);
}
tr_fail('Неизвестное действие');
