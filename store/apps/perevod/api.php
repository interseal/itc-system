<?php
// ==== ИТК Переводчик · api.php v3 · 05.11.2026 (DeepSeek напрямую — первым, Яндекс — запасной) ====
// Один адрес для всего приложения. Запрос — POST JSON с полем a (что сделать):
//   tr   — перевести одну фразу      {t, f, to, topic}
//   trb  — перевести список строк    {items[], f, to, topic}   (фото, сайты; до 30 строк)
//   ocr  — найти текст на снимке     {img: data:image/jpeg;base64,…, f}
//   web  — прочитать страницу сайта  {url}
//   tts  — озвучить фразу (mp3)      {t, l}
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
$T0 = microtime(true);

function pv_out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function pv_fail($e, $msg = '') { pv_out(['ok' => false, 'error' => $e, 'msg' => $msg]); }

// ---- пускаем только своё приложение ----
$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
$okHosts = ['itcpartner.store'];
$okRef = false; foreach ($okHosts as $h) if (strpos($ref, $h) !== false) $okRef = true;
if (!$okRef) pv_fail('forbidden');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') pv_fail('post_only');

$raw = file_get_contents('php://input', false, null, 0, 8 * 1024 * 1024);
$in = json_decode($raw, true);
if (!is_array($in)) pv_fail('bad_json');
$act = (string)($in['a'] ?? '');
$dev = preg_replace('/[^a-z0-9]/', '', strtolower((string)($in['d'] ?? '')));
if (strlen($dev) < 12 || strlen($dev) > 48) pv_fail('no_device');

// ---- рабочая папка (кэш, лимиты) — закрыта от просмотра снаружи ----
$DATA = __DIR__ . '/data';
if (!is_dir($DATA)) @mkdir($DATA, 0755, true);
if (!is_file("$DATA/.htaccess")) @file_put_contents("$DATA/.htaccess", "Require all denied\nDeny from all\n");
if (!is_file("$DATA/index.html")) @file_put_contents("$DATA/index.html", '');

// ---- ключи из магазина (те же, что переводят товары) ----
foreach ([dirname(__DIR__, 2) . '/api/config.php'] as $cfg) {   // папка магазина: /apps/perevod → /api/config.php
  if (is_file($cfg)) { ob_start(); try { @include_once $cfg; } catch (Throwable $e) {} ob_end_clean(); break; }
}
$YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
$YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
$DK = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
// Можно переопределить в config.php магазина, ничего здесь не меняя:
$DAY_CAP = defined('PEREVOD_DAY_CAP') ? (int)PEREVOD_DAY_CAP : 100000;   // всего обращений к переводчикам за сутки

// ---- лимиты: на устройство и общий ----
$LIM = ['tr' => [90, 4000], 'trb' => [20, 400], 'ocr' => [8, 120], 'web' => [10, 150], 'tts' => [60, 3000]];   // [в минуту, в сутки]
if (!isset($LIM[$act])) pv_fail('bad_action');
pv_rate_check($DATA, $dev, $act, $LIM[$act]);

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
    $t = pv_clean_text($in['t'] ?? '', 2000);
    if ($t === '') pv_out(['ok' => true, 't' => '']);
    [$f, $to] = pv_pick_langs($in, $t);
    $topic = isset($TOPICS[$in['topic'] ?? '']) ? $in['topic'] : 'obshchaya';
    $key = md5("v2|$f|$to|$topic|$t");   // v2 · старые ответы, где перевод остался на исходном языке, больше не берём
    if (($c = pv_cache_get($DATA, $key)) !== null && pv_lang_ok($c, $to)) pv_out(['ok' => true, 't' => $c, 'f' => $f, 'to' => $to, 'c' => 1]);
    pv_day_cap($DATA, $DAY_CAP);
    $sys = "Ты — профессиональный устный переводчик. Переведи с языка «{$LANGS[$f]}» на язык «{$LANGS[$to]}».\n"
         . ($TOPICS[$topic] ? "Обстановка разговора: {$TOPICS[$topic]}\n" : '')
         . "Это живая речь: текст мог быть распознан с голоса, без знаков препинания и с ошибками распознавания — восстанови смысл. "
         . "Переводи естественно, как сказал бы носитель языка в такой обстановке, а не дословно. Сохраняй вежливость и тон, числа, цены, размеры и единицы. "
         . "Верни ТОЛЬКО перевод — без пояснений, кавычек, транскрипции и вариантов.";
    $r = pv_ask_ai($DATA, $sys, $t, 1200, 25);
    if ($r === null) pv_fail('ai_failed', 'Переводчик сейчас не отвечает. Попробуйте ещё раз через минуту.' . pv_ai_err());
    $r = trim(preg_replace('/^```\w*|```$/m', '', $r));
    $r = trim($r, " \t\n\r\"«»");
    /* №… · модель иногда «исправляет» распознанную фразу на том же языке вместо перевода (китайский → китайский).
       Проверяем, что ответ на нужном языке; если нет — переспрашиваем строже. */
    if (!pv_lang_ok($r, $to)) {
      $sys2 = "Переведи текст на язык «{$LANGS[$to]}». Ответ должен быть написан ТОЛЬКО на языке «{$LANGS[$to]}»"
            . ($to === 'ru' ? ' — русскими буквами, без иероглифов' : ($to === 'zh' ? ' — иероглифами' : ' — латиницей')) . ". Текст — живая речь с ошибками распознавания, передай смысл. Верни только перевод.";
      $r2 = pv_ask_ai($DATA, $sys2, $t, 1200, 20);
      if ($r2 !== null) { $r2 = trim(trim(preg_replace('/^```\w*|```$/m', '', $r2)), " \t\n\r\"«»"); if (pv_lang_ok($r2, $to)) $r = $r2; }
    }
    if (mb_strlen($t) <= 600 && pv_lang_ok($r, $to)) pv_cache_put($DATA, $key, $r);
    pv_out(['ok' => true, 't' => $r, 'f' => $f, 'to' => $to, 'ms' => (int)((microtime(true) - $T0) * 1000), 'via' => $GLOBALS['AI_VIA'] ?? '']);
  }

  case 'trb': {
    $items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 30) : [];
    $items = array_map(fn($s) => pv_clean_text($s, 1500), $items);
    if (!$items) pv_out(['ok' => true, 'list' => []]);
    $joined = implode("\n", $items);
    [$f, $to] = pv_pick_langs($in, $joined);
    $topic = isset($TOPICS[$in['topic'] ?? '']) ? $in['topic'] : 'obshchaya';
    $res = []; $todo = [];
    foreach ($items as $i => $s) {
      if ($s === '' || !preg_match('/\p{L}/u', $s)) { $res[$i] = $s; continue; }
      $k = md5("v1|$f|$to|$topic|$s");
      if (($c = pv_cache_get($DATA, $k)) !== null) $res[$i] = $c; else $todo[$i] = $k;
    }
    if ($todo) {
      pv_day_cap($DATA, $DAY_CAP);
      $lines = []; $n = 1; foreach ($todo as $i => $k) $lines[] = ['n' => $n++, 't' => $items[$i]];
      $sys = "Ты — профессиональный переводчик. Переведи каждую строку с языка «{$LANGS[$f]}» на язык «{$LANGS[$to]}».\n"
           . ($TOPICS[$topic] ? "Обстановка: {$TOPICS[$topic]}\n" : '')
           . "Строки — это надписи с фотографии (вывеска, меню, этикетка, упаковка, документ) или куски страницы сайта. Переводи по смыслу, кратко, как принято на языке перевода. "
           . "Названия брендов и модели оставляй как есть. Если строка уже на языке перевода или не требует перевода — верни её без изменений.\n"
           . "На входе JSON-массив объектов {n, t}. Ответь ТОЛЬКО JSON-массивом объектов {n, t} с переводом в t, столько же элементов и в том же порядке, без ```.";
      $r = pv_ask_ai($DATA, $sys, json_encode($lines, JSON_UNESCAPED_UNICODE), 4000, 45);
      $arr = null;
      if ($r !== null) {
        $r = trim(preg_replace('/^```\w*|```$/m', '', $r));
        $a1 = strpos($r, '['); $a2 = strrpos($r, ']');
        if ($a1 !== false && $a2 !== false) $arr = json_decode(substr($r, $a1, $a2 - $a1 + 1), true);
      }
      if (!is_array($arr)) pv_fail('ai_failed', 'Переводчик сейчас не отвечает. Попробуйте ещё раз через минуту.' . pv_ai_err());
      $byN = []; foreach ($arr as $o) if (is_array($o) && isset($o['n'])) $byN[(int)$o['n']] = (string)($o['t'] ?? '');
      $n = 1;
      foreach ($todo as $i => $k) {
        $tt = $byN[$n] ?? ($arr[$n - 1]['t'] ?? null); $n++;
        if ($tt === null || $tt === '') { $res[$i] = null; continue; }
        $res[$i] = $tt; pv_cache_put($DATA, $k, $tt);
      }
    }
    ksort($res); $list = []; foreach ($items as $i => $s) $list[] = $res[$i] ?? null;
    pv_out(['ok' => true, 'list' => $list, 'f' => $f, 'to' => $to]);
  }

  case 'ocr': {
    $img = (string)($in['img'] ?? '');
    if (!preg_match('~^data:image/(jpeg|png);base64,~', $img, $m)) pv_fail('not_image', 'Это не фотография');
    $b64 = substr($img, strpos($img, ',') + 1);
    if (strlen($b64) > 6 * 1024 * 1024) pv_fail('too_big', 'Снимок слишком большой');
    if (!$YK || !$YF) pv_fail('no_keys', 'Нет ключей Яндекса');
    pv_day_cap($DATA, $DAY_CAP);
    $f = in_array($in['f'] ?? '', ['ru', 'en', 'zh'], true) ? $in['f'] : 'zh';
    $codes = $f === 'zh' ? ['zh', 'en', 'ru'] : ['ru', 'en'];
    $body = json_encode(['mimeType' => $m[1] === 'png' ? 'PNG' : 'JPEG', 'languageCodes' => $codes, 'model' => 'page', 'content' => $b64]);
    unset($b64, $img, $in, $raw);
    [$http, $resp] = pv_http_post('https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText', $body,
      ['Content-Type: application/json', 'Authorization: ' . pv_yc_auth($YK), 'x-folder-id: ' . $YF, 'x-data-logging-enabled: false'], 30);
    if ($http !== 200) { error_log("perevod ocr http=$http " . substr((string)$resp, 0, 300)); pv_fail('ocr_failed', 'Не получилось распознать текст на фото (' . $http . ')'); }
    $j = json_decode($resp, true);
    $ta = $j['result']['textAnnotation'] ?? null;
    if (!$ta) pv_out(['ok' => true, 'blocks' => [], 'w' => 0, 'h' => 0]);
    $blocks = [];
    foreach (($ta['blocks'] ?? []) as $b) {
      foreach (($b['lines'] ?? []) as $ln) {
        $txt = trim((string)($ln['text'] ?? ''));
        if ($txt === '') continue;
        $v = $ln['boundingBox']['vertices'] ?? [];
        $xs = array_map(fn($p) => (int)($p['x'] ?? 0), $v); $ys = array_map(fn($p) => (int)($p['y'] ?? 0), $v);
        if (!$xs) continue;
        // q — четыре угла строки как есть: строка на фото бывает наклонной, перевод кладём под тем же углом
        $q = []; foreach (array_slice($v, 0, 4) as $pp) { $q[] = (int)($pp['x'] ?? 0); $q[] = (int)($pp['y'] ?? 0); }
        $blocks[] = ['t' => $txt, 'x' => min($xs), 'y' => min($ys), 'w' => max($xs) - min($xs), 'h' => max($ys) - min($ys), 'q' => count($q) === 8 ? $q : null];
        if (count($blocks) >= 120) break 2;
      }
    }
    pv_out(['ok' => true, 'blocks' => $blocks, 'w' => (int)($ta['width'] ?? 0), 'h' => (int)($ta['height'] ?? 0)]);
  }

  case 'tts': {
    // Озвучка на сервере — для телефонов и окон, где нет своего голоса (приложение 1.7, часть браузеров).
    // Отдаём mp3; одна и та же фраза озвучивается один раз и дальше берётся из хранилища.
    $t = pv_clean_text($in['t'] ?? '', 600);
    $l = in_array($in['l'] ?? '', ['ru', 'zh', 'en'], true) ? $in['l'] : 'zh';
    if ($t === '') pv_fail('empty');
    $key = md5("tts1|$l|$t");
    $d = "$DATA/tts/" . substr($key, 0, 2); if (!is_dir($d)) @mkdir($d, 0755, true);
    $fp = "$d/$key.mp3";
    if (!is_file($fp) || filesize($fp) < 200) {
      pv_day_cap($DATA, $DAY_CAP);
      $mp3 = pv_tts($t, $l, $YK, $YF);
      if ($mp3 === null) pv_fail('tts_failed', 'Озвучка сейчас не отвечает. Попробуйте ещё раз.');
      @file_put_contents("$fp.tmp", $mp3, LOCK_EX); @rename("$fp.tmp", $fp);
    }
    header('Content-Type: audio/mpeg');
    header('Cache-Control: private, max-age=2592000');
    header('Content-Length: ' . filesize($fp));
    readfile($fp); exit;
  }

  case 'web': {
    $url = trim((string)($in['url'] ?? ''));
    if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
    if (!filter_var($url, FILTER_VALIDATE_URL)) pv_fail('bad_url', 'Ссылка не похожа на адрес сайта');
    [$html, $final, $err] = pv_fetch_page($url);
    if ($html === null) pv_fail('fetch_failed', $err ?: 'Сайт не открылся');
    pv_out(['ok' => true] + pv_parse_page($html, $final));
  }
}

/* ===================== помощники ===================== */

function pv_clean_text($s, $max) {
  $s = (string)$s;
  $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';
  return trim(mb_substr($s, 0, $max));
}

/* Языки: если человек выбрал «русский → китайский», а вставил китайский текст — сами меняем местами. */
/* ответ на нужном языке? (по буквам: кириллица / иероглифы / латиница) */
function pv_lang_ok($r, $to) {
  $han = preg_match_all('/\p{Han}/u', $r); $cyr = preg_match_all('/\p{Cyrillic}/u', $r); $lat = preg_match_all('/[A-Za-z]/', $r);
  if ($han + $cyr + $lat === 0) return true;            // одни цифры и знаки
  if ($to === 'ru') return $cyr > 0 && $cyr >= $han;
  if ($to === 'zh') return $han > 0 && $han * 2 >= $cyr;
  return $lat > 0 && $lat >= $han && $lat >= $cyr;
}
function pv_pick_langs($in, $t) {
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

function pv_shard($DATA, $key) { $d = "$DATA/c/" . substr($key, 0, 2); if (!is_dir($d)) @mkdir($d, 0755, true); return "$d/$key.txt"; }
function pv_cache_get($DATA, $key) { $p = pv_shard($DATA, $key); return is_file($p) ? (string)file_get_contents($p) : null; }
function pv_cache_put($DATA, $key, $v) { $p = pv_shard($DATA, $key); @file_put_contents($p . '.tmp', $v, LOCK_EX); @rename($p . '.tmp', $p); }

/* Счётчик в файле под замком: вернуть новое значение (или текущее, если $add = 0). */
function pv_bump($file, $add, $window) {
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

function pv_rate_check($DATA, $dev, $act, $lim) {
  $d = "$DATA/rl/" . substr(md5($dev), 0, 2); if (!is_dir($d)) @mkdir($d, 0755, true);
  $base = "$d/" . md5($dev) . "_$act";
  $m = pv_bump("$base.m", 1, date('YmdHi'));
  $day = pv_bump("$base.d", 1, date('Ymd'));
  if ($m > $lim[0]) pv_fail('slow_down', 'Слишком часто. Подождите минуту.');
  if ($day > $lim[1]) pv_fail('day_limit', 'На сегодня лимит этого устройства исчерпан. Завтра снова можно.');
}

function pv_day_cap($DATA, $cap) {
  $n = pv_bump("$DATA/day_total.json", 1, date('Ymd'));
  if ($n > $cap) pv_fail('busy', 'Переводчик сегодня перегружен. Попробуйте позже.');
}

function pv_yc_auth($k) { return (strpos($k, 't1.') === 0 ? 'Bearer ' : 'Api-Key ') . $k; }

function pv_http_post($url, $body, $headers, $timeout) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5]);
  $r = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http, $r === false ? '' : $r];
}

/* Переводит ТОЛЬКО DeepSeek (YandexGPT не используем — переводит хуже).
   Пока проба — DeepSeek через Яндекс (AI Studio, оплата Яндексу); запасной путь — DeepSeek напрямую (когда пополним счёт).
   СКОРОСТЬ (v2): DeepSeek по умолчанию сначала «думает» (рассуждает про себя) и только потом отвечает — для перевода
   это лишние 5–20 секунд. Просим отвечать сразу, без рассуждений. Способ выключить у разных путей свой —
   пробуем по очереди, рабочий запоминаем на сутки. Последний удачный путь пробуем первым.
   Отказавший путь пропускаем на время (5 мин; нет модели или денег на счёте — час). */
function pv_fast_variants($id) {
  if ($id === 'ds') return [['thinking' => ['type' => 'disabled']], []];
  return [
    ['chat_template_kwargs' => ['enable_thinking' => false], 'thinking' => ['type' => 'disabled']],
    ['chat_template_kwargs' => ['enable_thinking' => false]],
    ['reasoning_effort' => 'low'],
    [],
  ];
}
function pv_ask_ai($DATA, $sys, $user, $maxTok, $timeout) {
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
  // v3 · 05.11.2026: DeepSeek напрямую — всегда первым (счёт пополнен), Яндекс — запасной
  if ($DK) $chain = ['ds' => ['https://api.deepseek.com/chat/completions', 'deepseek-v4-flash', ['Authorization: Bearer ' . $DK]]] + $chain;
  // без прямого ключа — последний удачный путь Яндекса первым
  $good = (string)@file_get_contents("$DATA/good_path.txt");
  if (!$DK && $good !== '' && isset($chain[$good])) $chain = [$good => $chain[$good]] + $chain;
  if ($DK && !is_file("$DATA/v3_reset.txt")) { @unlink("$DATA/brk_ds.txt"); @file_put_contents("$DATA/v3_reset.txt", date("c")); }   // старая пометка «нет денег» больше не действует
  $start = microtime(true);
  foreach ($chain as $id => $p) {
    $brk = "$DATA/brk_$id.txt";
    if (is_file($brk) && (int)@file_get_contents($brk) > time()) continue;
    $vars = pv_fast_variants($id);
    $vf = "$DATA/fast_$id.txt";                       // какой способ «без рассуждений» подошёл этому пути
    $vi = is_file($vf) && filemtime($vf) > time() - 86400 ? max(0, min(count($vars) - 1, (int)@file_get_contents($vf))) : 0;
    $http = 0; $r = ''; $txt = null;
    for (; $vi < count($vars); $vi++) {
      $left = (int)($timeout - (microtime(true) - $start)); if ($left < 4) break 2;
      $req = ['model' => $p[1], 'temperature' => 0.2, 'max_tokens' => $maxTok, 'stream' => false,
        'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]]] + $vars[$vi];
      [$http, $r] = pv_http_post($p[0], json_encode($req, JSON_UNESCAPED_UNICODE), array_merge(['Content-Type: application/json'], $p[2]), min($left, 30));
      if ($http === 400 && $vi < count($vars) - 1) { error_log("perevod $id fast$vi 400 " . substr((string)$r, 0, 160)); continue; }   // не понял параметр — следующий способ
      break;
    }
    $J = $http === 200 ? json_decode($r, true) : null;
    $txt = $J['choices'][0]['message']['content'] ?? null;
    if (is_string($txt) && trim($txt) !== '') {
      // путь молча проигнорировал просьбу и всё равно «думал» — в следующий раз пробуем другой способ
      $thought = !empty($J['choices'][0]['message']['reasoning_content']) || (int)($J['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0) > 0;
      $save = ($thought && $vi < count($vars) - 2) ? $vi + 1 : $vi;
      if (!is_file($vf) || (int)@file_get_contents($vf) !== $save || filemtime($vf) < time() - 3600) @file_put_contents($vf, (string)$save);
      if ($thought) error_log("perevod $id fast$vi still reasoning");
      if ($good !== $id) @file_put_contents("$DATA/good_path.txt", $id);
      $GLOBALS['AI_VIA'] = $id . '/' . $vi . ($thought ? '/думал' : '');
      return $txt;
    }
    error_log("perevod $id http=$http " . substr((string)$r, 0, 200));
    $GLOBALS['AI_ERR'][] = $id . ':' . ($http ?: 'нет связи');
    @file_put_contents($brk, (string)(time() + (in_array($http, [401, 402, 403, 404], true) ? 3600 : 300)));
  }
  if (!$GLOBALS['AI_ERR']) $GLOBALS['AI_ERR'][] = $chain ? 'все пути на паузе' : 'нет ключей';
  return null;
}
function pv_ai_err() { return ' (DeepSeek: ' . implode(', ', $GLOBALS['AI_ERR'] ?? []) . ')'; }

/* ---------- озвучка: несколько служб по очереди, отказавшую не трогаем 10 минут ---------- */
function pv_tts_parts($t, $max) {   // режем длинный текст по знакам препинания на куски до $max знаков
  $out = []; $cur = '';
  foreach (preg_split('/(?<=[。！？!?.;；,，、\n])/u', $t) as $p) {
    $p = trim($p); if ($p === '') continue;
    while (mb_strlen($p) > $max) { $out[] = mb_substr($p, 0, $max); $p = mb_substr($p, $max); }
    if ($cur !== '' && mb_strlen($cur . ' ' . $p) > $max) { $out[] = $cur; $cur = $p; } else $cur = $cur === '' ? $p : $cur . ' ' . $p;
  }
  if ($cur !== '') $out[] = $cur;
  return $out;
}
function pv_get_audio($url, $headers = []) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 2, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_HTTPHEADER => array_merge(['User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36'], $headers)]);
  $r = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
  if ($http !== 200 || !is_string($r) || strlen($r) < 200 || stripos($ct, 'html') !== false || stripos($ct, 'json') !== false) return null;
  return $r;
}
function pv_tts($t, $l, $YK, $YF) {
  global $DATA;
  $gl = ['ru' => 'ru', 'zh' => 'zh-CN', 'en' => 'en'][$l];
  $svc = [
    'g' => function () use ($t, $gl) {          // Google: все три языка, кусками до 180 знаков
      $buf = '';
      foreach (pv_tts_parts($t, 180) as $p) {
        $a = pv_get_audio('https://translate.google.com/translate_tts?ie=UTF-8&client=tw-ob&tl=' . $gl . '&q=' . rawurlencode($p), ['Referer: https://translate.google.com/']);
        if ($a === null) return null; $buf .= $a;
      }
      return $buf;
    },
    'bd' => function () use ($t, $l) {          // Baidu: китайский, английский, русский
      $buf = '';
      foreach (pv_tts_parts($t, 200) as $p) {
        $a = pv_get_audio('https://fanyi.baidu.com/gettts?lan=' . $l . '&text=' . rawurlencode($p) . '&spd=' . ($l === 'zh' ? 4 : 5) . '&source=web', ['Referer: https://fanyi.baidu.com/']);
        if ($a === null) return null; $buf .= $a;
      }
      return $buf;
    },
    'yd' => function () use ($t, $l) {          // Youdao: китайский и английский
      if ($l === 'ru') return null;
      $buf = '';
      foreach (pv_tts_parts($t, 200) as $p) {
        $a = pv_get_audio('https://dict.youdao.com/dictvoice?audio=' . rawurlencode($p) . '&le=' . ($l === 'zh' ? 'zh' : 'eng'));
        if ($a === null) return null; $buf .= $a;
      }
      return $buf;
    },
    'ya' => function () use ($t, $l, $YK, $YF) { // Яндекс SpeechKit: русский и английский (китайского у него нет)
      if ($l === 'zh' || !$YK || !$YF) return null;
      $ch = curl_init('https://tts.api.cloud.yandex.net/speech/v1/tts:synthesize');
      curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_POSTFIELDS => http_build_query(['text' => $t, 'lang' => $l === 'ru' ? 'ru-RU' : 'en-US', 'voice' => $l === 'ru' ? 'alena' : 'john', 'format' => 'mp3', 'folderId' => $YF]),
        CURLOPT_HTTPHEADER => ['Authorization: ' . pv_yc_auth($YK)]]);
      $r = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
      return ($http === 200 && is_string($r) && strlen($r) > 200) ? $r : null;
    },
  ];
  $good = (string)@file_get_contents("$DATA/tts_good.txt");
  if (isset($svc[$good])) $svc = [$good => $svc[$good]] + $svc;
  foreach ($svc as $id => $fn) {
    $brk = "$DATA/tts_brk_$id" . "_$l.txt";
    if (is_file($brk) && (int)@file_get_contents($brk) > time()) continue;
    $a = $fn();
    if ($a !== null) { if ($good !== $id) @file_put_contents("$DATA/tts_good.txt", $id); return $a; }
    if (!($id === 'yd' && $l === 'ru') && !($id === 'ya' && $l === 'zh')) { error_log("perevod tts $id/$l failed"); @file_put_contents($brk, (string)(time() + 600)); }
  }
  return null;
}

/* ---------- чтение сайта ---------- */
function pv_host_is_public($host) {
  $ips = @gethostbynamel($host);
  if (!$ips) return false;
  foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
  return true;
}

function pv_fetch_page($url) {
  for ($hop = 0; $hop < 4; $hop++) {
    $u = parse_url($url);
    if (!in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true) || empty($u['host'])) return [null, $url, 'Неверный адрес'];
    if (!pv_host_is_public($u['host'])) return [null, $url, 'Этот адрес открыть нельзя'];
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

function pv_abs_url($href, $base) {
  $href = trim($href);
  if ($href === '' || preg_match('~^(javascript|mailto|tel|data):~i', $href) || $href[0] === '#') return '';
  if (preg_match('~^https?://~i', $href)) return $href;
  $b = parse_url($base); $root = $b['scheme'] . '://' . $b['host'] . (isset($b['port']) ? ':' . $b['port'] : '');
  if (strpos($href, '//') === 0) return $b['scheme'] . ':' . $href;
  if ($href[0] === '/') return $root . $href;
  $dir = preg_replace('~/[^/]*$~', '/', $b['path'] ?? '/');
  return $root . $dir . $href;
}

function pv_parse_page($html, $base) {
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
        $src = pv_abs_url($src, $base);
        if ($src && !preg_match('~\.(svg|gif)(\?|$)~i', $src)) $items[] = ['k' => 'img', 's' => $src];
        continue;
      }
      if (isset($inline[$tag]) || !$hasBlock($c)) {
        $kind = preg_match('/^h[1-4]$/', $tag) ? 'h' : ($tag === 'li' ? 'li' : 'p');
        $u = '';
        if ($tag === 'a') $u = pv_abs_url($c->getAttribute('href'), $base);
        else {
          $as = $c->getElementsByTagName('a');
          if ($as->length === 1 && trim($as->item(0)->textContent) === trim($c->textContent)) $u = pv_abs_url($as->item(0)->getAttribute('href'), $base);
        }
        $imgs = $c->getElementsByTagName('img');
        if ($tag !== 'a') {   // меню из нескольких ссылок — каждую ссылку отдельной строкой
          $as = $c->getElementsByTagName('a'); $lt = 0;
          foreach ($as as $a) $lt += mb_strlen(trim($a->textContent));
          if ($as->length >= 2 && $lt >= 0.7 * mb_strlen(trim($c->textContent))) {
            foreach ($as as $a) $push($a->textContent, 'li', pv_abs_url($a->getAttribute('href'), $base));
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
