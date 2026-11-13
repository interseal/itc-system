<?php
// ==== ИТК Переводчик · api.php v1 · 02.11.2026 ====
// Один адрес для всего приложения. Запрос — POST JSON с полем a (что сделать):
//   tr   — перевести одну фразу      {t, f, to, topic}
//   trb  — перевести список строк    {items[], f, to, topic}   (фото, сайты; до 30 строк)
//   ocr  — найти текст на снимке     {img: data:image/jpeg;base64,…, f}
//   web  — прочитать страницу сайта  {url}
// Устройство передаёт свою метку d (случайный код) — по ней лимиты.
//
// Как выдерживает много людей сразу:
//   • голос распознаёт и озвучивает сам телефон — сервер этим не занят;
//   • общий кэш переводов: одну и ту же фразу переводим один раз, дальше ответ мгновенный и бесплатный;
//   • лимиты на устройство и общий дневной предел — один человек или бот не съест весь бюджет;
//   • переводит только DeepSeek: через Яндекс (AI Studio), запасной путь — напрямую; если путь молчит,
//     его 5 минут не трогаем и сразу идём к следующему — люди не ждут зря;
//   • когда людей станут тысячи, этот файл переносится в Яндекс Облако (функция сама масштабируется),
//     а приложению меняют только адрес в cfg.json — переустанавливать ничего не нужно.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
@set_time_limit(60);

function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function fail($e, $msg = '') { out(['ok' => false, 'error' => $e, 'msg' => $msg]); }

// ---- пускаем только своё приложение ----
$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
$okHosts = ['metro.itcpartner.store'];
$okRef = false; foreach ($okHosts as $h) if (strpos($ref, $h) !== false) $okRef = true;
if (!$okRef) fail('forbidden');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail('post_only');

$raw = file_get_contents('php://input', false, null, 0, 8 * 1024 * 1024);
$in = json_decode($raw, true);
if (!is_array($in)) fail('bad_json');
$act = (string)($in['a'] ?? '');
$dev = preg_replace('/[^a-z0-9]/', '', strtolower((string)($in['d'] ?? '')));
if (strlen($dev) < 12 || strlen($dev) > 48) fail('no_device');

// ---- рабочая папка (кэш, лимиты) — закрыта от просмотра снаружи ----
$DATA = __DIR__ . '/data';
if (!is_dir($DATA)) @mkdir($DATA, 0755, true);
if (!is_file("$DATA/.htaccess")) @file_put_contents("$DATA/.htaccess", "Require all denied\nDeny from all\n");
if (!is_file("$DATA/index.html")) @file_put_contents("$DATA/index.html", '');

// ---- ключи из магазина (те же, что переводят товары) ----
foreach ([dirname(__DIR__, 2) . '/itcpartner.store/api/config.php', dirname(__DIR__, 3) . '/itcpartner.store/api/config.php',
          dirname(__DIR__, 2) . '/api/config.php'] as $cfg) {
  if (is_file($cfg)) { ob_start(); try { @include_once $cfg; } catch (Throwable $e) {} ob_end_clean(); break; }
}
$YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
$YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
$DK = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
// Можно переопределить в config.php магазина, ничего здесь не меняя:
$DAY_CAP = defined('PEREVOD_DAY_CAP') ? (int)PEREVOD_DAY_CAP : 100000;   // всего обращений к переводчикам за сутки

// ---- лимиты: на устройство и общий ----
$LIM = ['tr' => [40, 2000], 'trb' => [20, 400], 'ocr' => [8, 120], 'web' => [10, 150]];   // [в минуту, в сутки]
if (!isset($LIM[$act])) fail('bad_action');
rate_check($DATA, $dev, $act, $LIM[$act]);

$LANGS = ['ru' => 'русский', 'zh' => 'китайский (упрощённые иероглифы, как говорят в материковом Китае)', 'en' => 'английский'];
$TOPICS = [
  'obshchaya' => '',
  'fabrika'   => 'Разговор с китайской фабрикой: заказ, образцы, цены, минимальная партия (MOQ), упаковка, сроки производства, качество, оплата, доставка.',
  'rynok'     => 'Оптовый рынок или магазин в Китае: торг, цена за штуку и за партию, скидка, наличие, цвета и размеры.',
  'restoran'  => 'Ресторан или кафе: меню, блюда, острота, заказ, «здесь или с собой» (这里吃还是打包 — «едите здесь или с собой?»), счёт.',
  'taksi'     => 'Такси или Didi: адрес, куда ехать, где остановиться, сколько стоит, ждать.',
  'gostinica' => 'Гостиница: заселение, бронь, паспорт, депозит, номер, завтрак, выезд.',
  'logistika' => 'Логистика и склад: отправка груза, карго, вес, объём (кубы), упаковка, маркировка, таможня, трек-номер.',
  'vrach'     => 'Аптека или врач: самочувствие, симптомы, лекарства, дозировка. Переводи особенно точно.',
];

switch ($act) {
  case 'tr': {
    $t = clean_text($in['t'] ?? '', 2000);
    if ($t === '') out(['ok' => true, 't' => '']);
    [$f, $to] = pick_langs($in, $t);
    $topic = isset($TOPICS[$in['topic'] ?? '']) ? $in['topic'] : 'obshchaya';
    $key = md5("v1|$f|$to|$topic|$t");
    if (($c = cache_get($DATA, $key)) !== null) out(['ok' => true, 't' => $c, 'f' => $f, 'to' => $to, 'c' => 1]);
    day_cap($DATA, $DAY_CAP);
    $sys = "Ты — профессиональный устный переводчик. Переведи с языка «{$LANGS[$f]}» на язык «{$LANGS[$to]}».\n"
         . ($TOPICS[$topic] ? "Обстановка разговора: {$TOPICS[$topic]}\n" : '')
         . "Это живая речь: текст мог быть распознан с голоса, без знаков препинания и с ошибками распознавания — восстанови смысл. "
         . "Переводи естественно, как сказал бы носитель языка в такой обстановке, а не дословно. Сохраняй вежливость и тон, числа, цены, размеры и единицы. "
         . "Верни ТОЛЬКО перевод — без пояснений, кавычек, транскрипции и вариантов.";
    $r = ask_ai($DATA, $sys, $t, 1200, 25);
    if ($r === null) fail('ai_failed', 'Переводчик сейчас не отвечает. Попробуйте ещё раз через минуту.' . ai_err());
    $r = trim(preg_replace('/^```\w*|```$/m', '', $r));
    $r = trim($r, " \t\n\r\"«»");
    if (mb_strlen($t) <= 600) cache_put($DATA, $key, $r);
    out(['ok' => true, 't' => $r, 'f' => $f, 'to' => $to]);
  }

  case 'trb': {
    $items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 30) : [];
    $items = array_map(fn($s) => clean_text($s, 1500), $items);
    if (!$items) out(['ok' => true, 'list' => []]);
    $joined = implode("\n", $items);
    [$f, $to] = pick_langs($in, $joined);
    $topic = isset($TOPICS[$in['topic'] ?? '']) ? $in['topic'] : 'obshchaya';
    $res = []; $todo = [];
    foreach ($items as $i => $s) {
      if ($s === '' || !preg_match('/\p{L}/u', $s)) { $res[$i] = $s; continue; }
      $k = md5("v1|$f|$to|$topic|$s");
      if (($c = cache_get($DATA, $k)) !== null) $res[$i] = $c; else $todo[$i] = $k;
    }
    if ($todo) {
      day_cap($DATA, $DAY_CAP);
      $lines = []; $n = 1; foreach ($todo as $i => $k) $lines[] = ['n' => $n++, 't' => $items[$i]];
      $sys = "Ты — профессиональный переводчик. Переведи каждую строку с языка «{$LANGS[$f]}» на язык «{$LANGS[$to]}».\n"
           . ($TOPICS[$topic] ? "Обстановка: {$TOPICS[$topic]}\n" : '')
           . "Строки — это надписи с фотографии (вывеска, меню, этикетка, упаковка, документ) или куски страницы сайта. Переводи по смыслу, кратко, как принято на языке перевода. "
           . "Названия брендов и модели оставляй как есть. Если строка уже на языке перевода или не требует перевода — верни её без изменений.\n"
           . "На входе JSON-массив объектов {n, t}. Ответь ТОЛЬКО JSON-массивом объектов {n, t} с переводом в t, столько же элементов и в том же порядке, без ```.";
      $r = ask_ai($DATA, $sys, json_encode($lines, JSON_UNESCAPED_UNICODE), 4000, 45);
      $arr = null;
      if ($r !== null) {
        $r = trim(preg_replace('/^```\w*|```$/m', '', $r));
        $a1 = strpos($r, '['); $a2 = strrpos($r, ']');
        if ($a1 !== false && $a2 !== false) $arr = json_decode(substr($r, $a1, $a2 - $a1 + 1), true);
      }
      if (!is_array($arr)) fail('ai_failed', 'Переводчик сейчас не отвечает. Попробуйте ещё раз через минуту.' . ai_err());
      $byN = []; foreach ($arr as $o) if (is_array($o) && isset($o['n'])) $byN[(int)$o['n']] = (string)($o['t'] ?? '');
      $n = 1;
      foreach ($todo as $i => $k) {
        $tt = $byN[$n] ?? ($arr[$n - 1]['t'] ?? null); $n++;
        if ($tt === null || $tt === '') { $res[$i] = null; continue; }
        $res[$i] = $tt; cache_put($DATA, $k, $tt);
      }
    }
    ksort($res); $list = []; foreach ($items as $i => $s) $list[] = $res[$i] ?? null;
    out(['ok' => true, 'list' => $list, 'f' => $f, 'to' => $to]);
  }

  case 'ocr': {
    $img = (string)($in['img'] ?? '');
    if (!preg_match('~^data:image/(jpeg|png);base64,~', $img, $m)) fail('not_image', 'Это не фотография');
    $b64 = substr($img, strpos($img, ',') + 1);
    if (strlen($b64) > 6 * 1024 * 1024) fail('too_big', 'Снимок слишком большой');
    if (!$YK || !$YF) fail('no_keys', 'Нет ключей Яндекса');
    day_cap($DATA, $DAY_CAP);
    $f = in_array($in['f'] ?? '', ['ru', 'en', 'zh'], true) ? $in['f'] : 'zh';
    $codes = $f === 'zh' ? ['zh', 'en', 'ru'] : ['ru', 'en'];
    $body = json_encode(['mimeType' => $m[1] === 'png' ? 'PNG' : 'JPEG', 'languageCodes' => $codes, 'model' => 'page', 'content' => $b64]);
    unset($b64, $img, $in, $raw);
    [$http, $resp] = http_post('https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText', $body,
      ['Content-Type: application/json', 'Authorization: ' . yc_auth($YK), 'x-folder-id: ' . $YF, 'x-data-logging-enabled: false'], 30);
    if ($http !== 200) { error_log("perevod ocr http=$http " . substr((string)$resp, 0, 300)); fail('ocr_failed', 'Не получилось распознать текст на фото (' . $http . ')'); }
    $j = json_decode($resp, true);
    $ta = $j['result']['textAnnotation'] ?? null;
    if (!$ta) out(['ok' => true, 'blocks' => [], 'w' => 0, 'h' => 0]);
    $blocks = [];
    foreach (($ta['blocks'] ?? []) as $b) {
      foreach (($b['lines'] ?? []) as $ln) {
        $txt = trim((string)($ln['text'] ?? ''));
        if ($txt === '') continue;
        $v = $ln['boundingBox']['vertices'] ?? [];
        $xs = array_map(fn($p) => (int)($p['x'] ?? 0), $v); $ys = array_map(fn($p) => (int)($p['y'] ?? 0), $v);
        if (!$xs) continue;
        $blocks[] = ['t' => $txt, 'x' => min($xs), 'y' => min($ys), 'w' => max($xs) - min($xs), 'h' => max($ys) - min($ys)];
        if (count($blocks) >= 120) break 2;
      }
    }
    out(['ok' => true, 'blocks' => $blocks, 'w' => (int)($ta['width'] ?? 0), 'h' => (int)($ta['height'] ?? 0)]);
  }

  case 'web': {
    $url = trim((string)($in['url'] ?? ''));
    if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) fail('bad_url', 'Ссылка не похожа на адрес сайта');
    [$html, $final, $err] = fetch_page($url);
    if ($html === null) fail('fetch_failed', $err ?: 'Сайт не открылся');
    out(['ok' => true] + parse_page($html, $final));
  }
}

/* ===================== помощники ===================== */

function clean_text($s, $max) {
  $s = (string)$s;
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
  return trim(mb_substr($s, 0, $max));
}

/* Языки: если человек выбрал «русский → китайский», а вставил китайский текст — сами меняем местами. */
function pick_langs($in, $t) {
  $ok = ['ru', 'zh', 'en'];
  $f = in_array($in['f'] ?? '', $ok, true) ? $in['f'] : 'ru';
  $to = in_array($in['to'] ?? '', $ok, true) ? $in['to'] : 'zh';
  $han = preg_match_all('/\p{Han}/u', $t); $cyr = preg_match_all('/\p{Cyrillic}/u', $t); $lat = preg_match_all('/[A-Za-z]/', $t);
  $det = null;
  if ($han > 0 && $han >= $cyr && $han * 2 >= $lat) $det = 'zh';
  elseif ($cyr > 0 && $cyr >= $lat) $det = 'ru';
  elseif ($lat > 3 && !$han && !$cyr) $det = 'en';
  if ($det && $det !== $f) { if ($det === $to) $to = $f; $f = $det; }
  if ($f === $to) $to = $f === 'ru' ? 'zh' : 'ru';
  return [$f, $to];
}

function shard($DATA, $key) { $d = "$DATA/c/" . substr($key, 0, 2); if (!is_dir($d)) @mkdir($d, 0755, true); return "$d/$key.txt"; }
function cache_get($DATA, $key) { $p = shard($DATA, $key); return is_file($p) ? (string)file_get_contents($p) : null; }
function cache_put($DATA, $key, $v) { $p = shard($DATA, $key); @file_put_contents($p . '.tmp', $v, LOCK_EX); @rename($p . '.tmp', $p); }

/* Счётчик в файле под замком: вернуть новое значение (или текущее, если $add = 0). */
function bump($file, $add, $window) {
  $fh = @fopen($file, 'c+'); if (!$fh) return 0;
  flock($fh, LOCK_EX);
  $d = json_decode(stream_get_contents($fh), true);
  $now = time();
  if (!is_array($d) || ($d['w'] ?? '') !== $window) $d = ['w' => $window, 'n' => 0];
  $d['n'] += $add;
  ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($d)); fflush($fh);
  flock($fh, LOCK_UN); fclose($fh);
  return $d['n'];
}

function rate_check($DATA, $dev, $act, $lim) {
  $d = "$DATA/rl/" . substr(md5($dev), 0, 2); if (!is_dir($d)) @mkdir($d, 0755, true);
  $base = "$d/" . md5($dev) . "_$act";
  $m = bump("$base.m", 1, date('YmdHi'));
  $day = bump("$base.d", 1, date('Ymd'));
  if ($m > $lim[0]) fail('slow_down', 'Слишком часто. Подождите минуту.');
  if ($day > $lim[1]) fail('day_limit', 'На сегодня лимит этого устройства исчерпан. Завтра снова можно.');
}

function day_cap($DATA, $cap) {
  $n = bump("$DATA/day_total.json", 1, date('Ymd'));
  if ($n > $cap) fail('busy', 'Переводчик сегодня перегружен. Попробуйте позже.');
}

function yc_auth($k) { return (strpos($k, 't1.') === 0 ? 'Bearer ' : 'Api-Key ') . $k; }

function http_post($url, $body, $headers, $timeout) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5]);
  $r = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http, $r === false ? '' : $r];
}

/* Переводит ТОЛЬКО DeepSeek (YandexGPT не используем — переводит хуже).
   Пока проба — DeepSeek через Яндекс (AI Studio, оплата Яндексу); запасной путь — DeepSeek напрямую (когда пополним счёт).
   Отказавший путь пропускаем на время (5 мин; нет модели или денег на счёте — час). */
function ask_ai($DATA, $sys, $user, $maxTok, $timeout) {
  global $YK, $YF, $DK;
  $chain = []; $GLOBALS['AI_ERR'] = [];
  if ($YK && $YF) {
    // ключ в заголовке: по справке Яндекса — «Bearer», запасной вариант — «Api-Key»
    $u = 'https://llm.api.cloud.yandex.net/v1/chat/completions';
    $hb = ['Authorization: Bearer ' . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF];
    $hk = ['Authorization: Api-Key ' . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF];
    $chain['yds41'] = [$u, "gpt://$YF/deepseek-v4.1-flash", $hb];
    $chain['yds41k'] = [$u, "gpt://$YF/deepseek-v4.1-flash", $hk];
    $chain['yds4'] = [$u, "gpt://$YF/deepseek-v4-flash", $hb];
    $chain['yds4k'] = [$u, "gpt://$YF/deepseek-v4-flash", $hk];
  }
  if ($DK) $chain['ds'] = ['https://api.deepseek.com/chat/completions', 'deepseek-v4-flash', ['Authorization: Bearer ' . $DK]];
  $start = microtime(true);
  foreach ($chain as $id => $p) {
    $brk = "$DATA/brk_$id.txt";
    if (is_file($brk) && (int)@file_get_contents($brk) > time()) continue;
    $left = (int)($timeout - (microtime(true) - $start)); if ($left < 4) break;
    $body = json_encode(['model' => $p[1], 'temperature' => 0.2, 'max_tokens' => $maxTok, 'stream' => false,
      'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]]], JSON_UNESCAPED_UNICODE);
    [$http, $r] = http_post($p[0], $body, array_merge(['Content-Type: application/json'], $p[2]), min($left, 30));
    $txt = $http === 200 ? (json_decode($r, true)['choices'][0]['message']['content'] ?? null) : null;

    if (is_string($txt) && trim($txt) !== '') return $txt;
    error_log("perevod $id http=$http " . substr((string)$r, 0, 200));
    $GLOBALS['AI_ERR'][] = $id . ':' . ($http ?: 'нет связи');
    @file_put_contents($brk, (string)(time() + (in_array($http, [401, 402, 403, 404], true) ? 3600 : 300)));
  }
  if (!$GLOBALS['AI_ERR']) $GLOBALS['AI_ERR'][] = $chain ? 'все пути на паузе' : 'нет ключей';
  return null;
}
function ai_err() { return ' (DeepSeek: ' . implode(', ', $GLOBALS['AI_ERR'] ?? []) . ')'; }

/* ---------- чтение сайта ---------- */
function host_is_public($host) {
  $ips = @gethostbynamel($host);
  if (!$ips) return false;
  foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
  return true;
}

function fetch_page($url) {
  for ($hop = 0; $hop < 4; $hop++) {
    $u = parse_url($url);
    if (!in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true) || empty($u['host'])) return [null, $url, 'Неверный адрес'];
    if (!host_is_public($u['host'])) return [null, $url, 'Этот адрес открыть нельзя'];
    $buf = ''; $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_ENCODING => '',
      CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: zh-CN,zh;q=0.9,ru;q=0.8,en;q=0.7'],
      CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36',
      CURLOPT_WRITEFUNCTION => function ($ch, $d) use (&$buf) { $buf .= $d; return strlen($buf) > 3000000 ? 0 : strlen($d); }]);
    curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $loc = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
    if ($http >= 300 && $http < 400 && $loc !== '') { $url = $loc; continue; }
    if ($http !== 200 || $buf === '') return [null, $url, 'Сайт ответил ошибкой (' . ($http ?: 'нет связи') . ')'];
    if ($ctype && stripos($ctype, 'html') === false) return [null, $url, 'По ссылке не страница сайта, а файл'];
    // кодировка: китайские сайты часто в GBK
    $cs = '';
    if (preg_match('/charset=([\w-]+)/i', $ctype, $m)) $cs = $m[1];
    elseif (preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($buf, 0, 4000), $m)) $cs = $m[1];
    $cs = strtoupper($cs);
    if ($cs && !in_array($cs, ['UTF-8', 'UTF8'], true)) {
      if (in_array($cs, ['GB2312', 'GBK'], true)) $cs = 'GB18030';
      $c = @mb_convert_encoding($buf, 'UTF-8', $cs); if ($c) $buf = $c;
    }
    return [$buf, $url, ''];
  }
  return [null, $url, 'Слишком много переадресаций'];
}

function abs_url($href, $base) {
  $href = trim($href);
  if ($href === '' || preg_match('~^(javascript|mailto|tel|data):~i', $href) || $href[0] === '#') return '';
  if (preg_match('~^https?://~i', $href)) return $href;
  $b = parse_url($base); $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
  if (strpos($href, '//') === 0) return $b['scheme'] . ':' . $href;
  if ($href[0] === '/') return $root . $href;
  $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');
  return $root . $dir . $href;
}

function parse_page($html, $base) {
  libxml_use_internal_errors(true);
  $doc = new DOMDocument();
  $html = preg_replace('/<meta[^>]+charset[^>]*>/i', '', $html);   // текст уже в UTF-8 — старое указание кодировки мешает
  $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
  $title = '';
  $tn = $doc->getElementsByTagName('title'); if ($tn->length) $title = trim($tn->item(0)->textContent);
  foreach (['script', 'style', 'noscript', 'svg', 'iframe', 'template', 'form', 'select', 'button', 'head'] as $tag) {
    $ns = $doc->getElementsByTagName($tag); for ($i = $ns->length - 1; $i >= 0; $i--) { $n = $ns->item($i); $n->parentNode->removeChild($n); }
  }
  $inline = array_flip(['a', 'span', 'b', 'strong', 'i', 'em', 'font', 'small', 'big', 'sup', 'sub', 'label', 'u', 'br', 'abbr', 'code', 'mark', 'q', 's', 'cite', 'time']);
  $items = []; $seen = []; $chars = 0;
  $push = function ($t, $kind, $u = '') use (&$items, &$seen, &$chars) {
    $t = trim(preg_replace('/\s+/u', ' ', $t));
    if ($t === '' || !preg_match('/\p{L}/u', $t) || isset($seen[$t]) || count($items) >= 400 || $chars > 40000) return;
    $seen[$t] = 1; $chars += mb_strlen($t);
    $o = ['k' => $kind, 's' => mb_substr($t, 0, 1500)]; if ($u) $o['u'] = $u; $items[] = $o;
  };
  $hasBlock = function ($n) use (&$hasBlock, $inline) {
    foreach ($n->childNodes as $c) if ($c->nodeType === XML_ELEMENT_NODE && (!isset($inline[strtolower($c->nodeName)]) || $hasBlock($c))) return true;
    return false;
  };
  $walk = function ($n) use (&$walk, &$push, $hasBlock, $inline, $base, &$items) {
    foreach ($n->childNodes as $c) {
      if (count($items) >= 400) return;
      if ($c->nodeType === XML_TEXT_NODE) { $push($c->textContent, 'p'); continue; }
      if ($c->nodeType !== XML_ELEMENT_NODE) continue;
      $tag = strtolower($c->nodeName);
      if ($tag === 'img') {
        $src = $c->getAttribute('data-src') ?: $c->getAttribute('data-original') ?: $c->getAttribute('src');
        $w = (int)$c->getAttribute('width'); $h = (int)$c->getAttribute('height');
        if (($w && $w < 80) || ($h && $h < 60)) continue;
        $src = abs_url($src, $base);
        if ($src && !preg_match('~\.(svg|gif)(\?|$)~i', $src)) $items[] = ['k' => 'img', 's' => $src];
        continue;
      }
      if (isset($inline[$tag]) || !$hasBlock($c)) {
        $kind = preg_match('/^h[1-4]$/', $tag) ? 'h' : ($tag === 'li' ? 'li' : 'p');
        $u = '';
        if ($tag === 'a') $u = abs_url($c->getAttribute('href'), $base);
        else {
          $as = $c->getElementsByTagName('a');
          if ($as->length === 1 && trim($as->item(0)->textContent) === trim($c->textContent)) $u = abs_url($as->item(0)->getAttribute('href'), $base);
        }
        $imgs = $c->getElementsByTagName('img');
        if ($tag !== 'a') {   // меню из нескольких ссылок — каждую ссылку отдельной строкой
          $as = $c->getElementsByTagName('a'); $lt = 0;
          foreach ($as as $a) $lt += mb_strlen(trim($a->textContent));
          if ($as->length >= 2 && $lt >= 0.7 * mb_strlen(trim($c->textContent))) {
            foreach ($as as $a) $push($a->textContent, 'li', abs_url($a->getAttribute('href'), $base));
            continue;
          }
        }
        if ($imgs->length && !isset($inline[$tag])) $walk($c);
        else $push($c->textContent, $kind, $u);
        continue;
      }
      $walk($c);
    }
  };
  $body = $doc->getElementsByTagName('body')->item(0);
  if ($body) $walk($body);
  // картинок не больше 40
  $ni = 0; $items = array_values(array_filter($items, function ($o) use (&$ni) { return $o['k'] !== 'img' || ++$ni <= 40; }));
  return ['title' => mb_substr($title, 0, 300), 'url' => $base, 'items' => $items];
}
