<?php
// ==== ИТК · api/kosmos_lib.php (№533, №535 — 13.11.2026) · общее для «Космоса» в магазине ====
// Подключают api/kosmos.php (связь с сервером «Космоса») и api/kosmos_flow.php («Космос» в Экосистеме и CRM —
// копия «Фила», только думает Claude). Хранилище — uploads/kosmos: пакеты в pkg/, остальное — в закрытой папке
// со случайным именем (имя — в priv.php).
// Шлюз: магазин спрашивает Claude через сервер «Космоса» (https://kosmos.itcpartner.store/api.php?a=gw) с ключом шлюза.
// Ключ шлюза создаётся здесь (gw.json в закрытой папке) и уходит серверу в ответ на его ежеминутный отчёт.
// Ключи Claude живут только на сервере «Космоса» — сюда приходит лишь текст ответа.

const KS_HOST = 'kosmos.itcpartner.store';
define('KS_STORE', defined('KOSMOS_STORE_URL') ? KOSMOS_STORE_URL : 'https://itcpartner.store');   // для проверки у себя можно задать другой адрес

function ks_root() {
  $r = dirname(__DIR__) . '/uploads/kosmos';
  if (!is_dir($r)) @mkdir($r, 0755, true);
  if (!is_file($r . '/.htaccess')) @file_put_contents($r . '/.htaccess', "Require all denied\nDeny from all\n");
  if (!is_file($r . '/index.html')) @file_put_contents($r . '/index.html', '');
  return $r;
}
/* Закрытая папка со случайным именем (имя — в priv.php, он при открытии из интернета ничего не показывает).
   Даже если хостинг отдаёт файлы мимо .htaccess, копии базы и отчёты не найти. */
function ks_priv() {
  static $d = null;
  if ($d !== null) return $d;
  $r = ks_root(); $kf = $r . '/priv.php';
  $name = is_file($kf) ? (string)(include $kf) : '';
  if (!preg_match('~^[a-f0-9]{32}$~', $name)) { $name = bin2hex(random_bytes(16)); file_put_contents($kf, "<?php return '" . $name . "';\n", LOCK_EX); }
  $d = $r . '/' . $name;
  if (!is_dir($d)) @mkdir($d, 0755, true);
  if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
  return $d;
}
function ks_dir($sub = '') {
  $d = ks_priv() . ($sub !== '' ? '/' . $sub : '');
  if (!is_dir($d)) { @mkdir($d, 0755, true); @file_put_contents($d . '/index.html', ''); }
  return $d;
}
/* Пакеты «Космоса» кладёт сюда обновление магазина (store/uploads/kosmos/pkg/…): там только код, без секретов. */
function ks_pkg_dir() { $d = ks_root() . '/pkg'; if (!is_dir($d)) @mkdir($d, 0755, true); if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', ''); return $d; }
function ks_json($f) { $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : []; }
function ks_put($f, $a) { file_put_contents($f, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); }

define('KS_SERVER', defined('KOSMOS_SERVER_URL') ? KOSMOS_SERVER_URL : 'https://' . KS_HOST);   // для проверки у себя можно задать другой адрес

/* Пакеты «Космоса» в магазине: uploads/kosmos/pkg/<версия>.json — {ver, file, sha256, sig}. По возрастанию версии. */
function ks_pkgs() {
  $out = [];
  foreach (glob(ks_pkg_dir() . '/*.json') ?: [] as $f) {
    $m = ks_json($f);
    if (empty($m['ver']) || empty($m['file']) || empty($m['sha256']) || empty($m['sig']) || !is_file(ks_pkg_dir() . '/' . basename((string)$m['file']))) continue;
    $out[(string)$m['ver']] = ['ver' => (string)$m['ver'], 'file' => basename((string)$m['file']), 'sha256' => (string)$m['sha256'], 'sig' => (string)$m['sig'],
                              'notes' => (string)($m['notes'] ?? ''), 'pub' => (string)($m['pub'] ?? '')];
  }
  uksort($out, 'version_compare');
  return $out;
}

/* №533 · ключ шлюза и ключ фоновых задач — создаются один раз; удалить gw.json = выдать новые (сервер получит их через минуту). */
function ks_gw() {
  static $g = null;
  if ($g !== null) return $g;
  $f = ks_dir() . '/gw.json';
  $g = ks_json($f);
  if (!preg_match('~^[a-f0-9]{64}$~', (string)($g['gw'] ?? '')) || !preg_match('~^[a-f0-9]{32}$~', (string)($g['cron'] ?? ''))) {
    $g = ['gw' => bin2hex(random_bytes(32)), 'cron' => bin2hex(random_bytes(16)), 'at' => date('Y-m-d H:i:s')];
    ks_put($f, $g);
  }
  return $g;
}
function ks_cron_url() { return KS_STORE . '/api/kosmos_flow.php?action=ft_cron&k=' . ks_gw()['cron']; }
function ks_cron_ok($k) { return is_string($k) && strlen($k) === 32 && hash_equals(ks_gw()['cron'], $k); }

/* Запрос к шлюзу сервера «Космоса». Возвращает [код, ответ-массив]. 0 — нет связи. */
function ks_gw_call($data, $timeout = 150) {
  $ch = curl_init(KS_SERVER . '/api.php?a=gw');
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Kosmos-GW: ' . ks_gw()['gw']],
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE)]);
  $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $j = is_string($r) ? json_decode($r, true) : null;
  return [$code, is_array($j) ? $j : []];
}

/* Спросить Claude: [текст или null, ошибка, какая модель ответила]. $me — кто спрашивает (для доступа и лимитов «Космоса»). */
function ks_gw_ask($sys, $user, $maxTok, $model, $me) {
  $who = ['phone' => (string)($me['phone'] ?? ''), 'name' => (string)($me['name'] ?? ''), 'role' => (string)($me['role'] ?? ''), 'boss' => !empty($me['boss'])];
  $GLOBALS['KS_STOP'] = '';
  $req = ['a' => 'ask', 'system' => (string)$sys, 'user' => (string)$user, 'max_tokens' => (int)$maxTok, 'model' => (string)$model, 'who' => $who];
  if (!empty($GLOBALS['FC_IMGS'])) $req['images'] = array_map(function ($x) { return ['media_type' => $x['m'], 'data' => $x['d']]; }, (array)$GLOBALS['FC_IMGS']);   // №575 · фото — самому Claude
  [$code, $j] = ks_gw_call($req, !empty($req['images']) ? 240 : 150);
  if ($code === 200 && !empty($j['ok']) && is_string($j['text'] ?? null)) {
    $GLOBALS['KS_STOP'] = (string)($j['stop'] ?? '');   // №535: max_tokens — ответ не уместился
    if (!empty($j['switched'])) $GLOBALS['KS_SWITCHED'] = (string)$j['switched'];
    return [$j['text'], '', ks_model_title((string)($j['model'] ?? $model)) . (!empty($j['key']) ? ' · ключ «' . $j['key'] . '»' : '')];
  }
  if ($code === 0) return [null, 'нет связи с сервером «Космоса» в Сингапуре', ''];
  $e = (string)($j['error'] ?? '');
  if ($code === 403 && $e === '') $e = 'сервер «Космоса» ещё не связан с магазином — подождите минуту';
  return [null, $e !== '' ? $e : 'сервер «Космоса» ответил ' . $code, ''];
}
/* №535 · Ответ Claude → шаг «Космоса» (JSON). Claude бывает пишет пояснение до или после JSON, сразу несколько шагов
   или переводы строк прямо внутри строки — берём первый целый объект {…}, при нужде экранируем переводы строк.
   Ответ совсем без JSON — это просто ответ человеку. null — разобрать нельзя (например, ответ обрезан). */
function ks_ai_json($txt) {
  $t = trim(preg_replace('~<think>.*?</think>~s', '', (string)$txt));
  $t = trim(preg_replace('~^[ \t]*```[a-zA-Z]*[ \t]*$~m', '', $t));
  if ($t === '') return null;
  if (strpos($t, '{') === false) return ['do' => 'answer', 'say' => $t];
  $n = strlen($t); $a = strpos($t, '{');
  while ($a !== false) {
    $d = 0; $in = false; $esc = false; $end = -1;
    for ($i = $a; $i < $n; $i++) {
      $c = $t[$i];
      if ($in) { if ($esc) $esc = false; elseif ($c === '\\') $esc = true; elseif ($c === '"') $in = false; continue; }
      if ($c === '"') $in = true;
      elseif ($c === '{') $d++;
      elseif ($c === '}' && --$d === 0) { $end = $i; break; }
    }
    if ($end < 0) return null;
    $s = substr($t, $a, $end - $a + 1);
    $j = json_decode($s, true);
    if (!is_array($j)) $j = json_decode(ks_ai_json_fix($s), true);
    if (is_array($j) && $j) return $j;
    $a = strpos($t, '{', $end + 1);
  }
  return null;
}
/* Переводы строк и табуляция прямо внутри строк JSON → \n, \r, \t. */
function ks_ai_json_fix($s) {
  $o = ''; $in = false; $esc = false; $n = strlen($s);
  for ($i = 0; $i < $n; $i++) {
    $c = $s[$i];
    if ($in) {
      if ($esc) { $esc = false; $o .= $c; continue; }
      if ($c === '\\') { $esc = true; $o .= $c; continue; }
      if ($c === '"') { $in = false; $o .= $c; continue; }
      if ($c === "\n") { $o .= '\n'; continue; }
      if ($c === "\r") { $o .= '\r'; continue; }
      if ($c === "\t") { $o .= '\t'; continue; }
      $o .= $c; continue;
    }
    if ($c === '"') $in = true;
    $o .= $c;
  }
  return $o;
}
function ks_model_title($id) {
  $n = ['claude-sonnet-5-5' => 'Claude Sonnet 5.5', 'claude-opus-5-5' => 'Claude Opus 5.5', 'claude-haiku-5-5' => 'Claude Haiku 5.5', 'claude-fable-5-2' => 'Claude Fable 5.2'];
  foreach ($n as $k => $v) if (strpos($id, $k) === 0) return $v;
  return $id;
}
/* Состояние сервера и ключей — для настроек «Космоса». */
function ks_gw_status() {
  [$code, $j] = ks_gw_call(['a' => 'status'], 8);
  if ($code === 200 && !empty($j['ok'])) return $j + ['online' => true];
  return ['ok' => false, 'online' => $code !== 0, 'error' => $code === 0 ? 'Сервер «Космоса» не отвечает' : (string)($j['error'] ?? ('ответ ' . $code))];
}
