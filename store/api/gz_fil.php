<?php
/* ==== ИТК · Фил в приложении «Мой Гуанчжоу» · gz_fil.php v2 · 06.11.2026 · ссылки (Amap и др.) и «сохрани как …» ====
   v1 · 06.11.2026
   Помощник внизу приложения metro.itcpartner.store: человек пишет, говорит или фотографирует
   (станцию, табличку, вывеску, номер дома) — Фил читает надпись, понимает, что это, и предлагает:
   построить маршрут, адрес для такси, показать водителю, запомнить место.
   Действия:  ask  — вопрос (текст и/или фото) → ответ Фила + место для поиска
              hear — голос (16 кГц, моно) → текст   (Яндекс SpeechKit, тот же ключ, что у Фила)
              say  — текст → голос Фила (mp3)
   Ключи — те же, что у Фила (config.php магазина и настройки Фила); браузеру ничего не отдаём. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ai_lib.php';

$ORIGINS = ['https://metro.itcpartner.store', 'https://itcpartner.store', 'https://www.itcpartner.store'];
$org = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($org, $ORIGINS, true)) { header('Access-Control-Allow-Origin: ' . $org); header('Vary: Origin'); header('Access-Control-Allow-Headers: Content-Type'); header('Access-Control-Allow-Methods: POST, OPTIONS'); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

function gz_out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function gz_fail($m) { gz_out(['ok' => false, 'error' => $m]); }

/* только своё приложение — чтобы чужие не тратили лимиты Яндекса */
$ref = $org . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
if (strpos($ref, 'itcpartner.store') === false) gz_fail('forbidden');

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) gz_fail('Пустой запрос');
$act = (string)($in['action'] ?? '');

/* не больше 300 обращений в сутки с одного адреса */
$dir = itcai_dir() . '/gzfil'; if (!is_dir($dir)) @mkdir($dir, 0755, true);
$ip = preg_replace('~[^0-9a-f.:]~i', '', $_SERVER['REMOTE_ADDR'] ?? 'x');
$cf = $dir . '/' . date('Ymd') . '_' . md5($ip) . '.n'; $cnt = (int)@file_get_contents($cf);
if ($cnt > 300) gz_fail('На сегодня лимит вопросов Филу исчерпан — завтра снова');
@file_put_contents($cf, (string)($cnt + 1));
foreach (glob($dir . '/*.n') ?: [] as $f) if (filemtime($f) < time() - 3 * 86400) @unlink($f);

/* секреты Фила (ключ голоса, «ключ ума») лежат в базе магазина */
function gz_secret($k) {
  try { if (!function_exists('db')) return ''; $q = db()->prepare('SELECT v FROM itc_secret WHERE k=?'); $q->execute([$k]); $v = $q->fetchColumn(); return $v === false ? '' : (string)$v; }
  catch (Throwable $e) { return ''; }
}
function gz_yk() { $k = gz_secret('yc_main'); return $k !== '' ? $k : (defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '')); }
function gz_yf() { return defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? ''); }

/* фото → текст (Яндекс Vision), китайский в первую очередь */
function gz_ocr($bin, $mime, &$err) {
  $err = ''; $YK = gz_yk(); $YF = gz_yf();
  if (!$YK) { $err = 'нет ключа распознавания'; return ''; }
  foreach ([['zh', 'en', 'ru'], ['*']] as $langs) {
    $body = json_encode(['mimeType' => $mime === 'image/png' ? 'PNG' : 'JPEG', 'languageCodes' => $langs, 'model' => 'page', 'content' => base64_encode($bin)]);
    [$http, $r] = itcai_post('https://ocr.api.cloud.yandex.net/ocr/v1/recognizeText', $body, ['Content-Type: application/json', 'Authorization: Api-Key ' . $YK, 'x-folder-id: ' . $YF, 'x-data-logging-enabled: false'], 30);
    if ($http === 200) { $j = json_decode($r, true); return trim((string)($j['result']['textAnnotation']['fullText'] ?? '')); }
    $err = 'Яндекс ответил ' . $http;
  }
  return '';
}

if ($act === 'hear') {
  $k = gz_secret('yc_speech'); if ($k === '') gz_fail('Голос пока не подключён — напиши словами');
  $pcm = base64_decode((string)($in['pcm'] ?? ''), true);
  if ($pcm === false || strlen($pcm) < 3200) gz_fail('Слишком коротко — скажи ещё раз');
  if (strlen($pcm) > 960000) $pcm = substr($pcm, 0, 960000);
  [$http, $r] = itcai_post('https://stt.api.cloud.yandex.net/speech/v1/stt:recognize?lang=ru-RU&format=lpcm&sampleRateHertz=16000&topic=general', $pcm,
    ['Content-Type: application/octet-stream', 'Authorization: Api-Key ' . $k], 30);
  $j = json_decode($r, true);
  if ($http !== 200) gz_fail('Не удалось распознать голос');
  gz_out(['ok' => true, 'text' => trim((string)($j['result'] ?? ''))]);
}

if ($act === 'say') {
  $k = gz_secret('yc_speech'); if ($k === '') gz_fail('Голос пока не подключён');
  $t = trim(preg_replace('~\s+~u', ' ', strip_tags((string)($in['text'] ?? '')))); if ($t === '') gz_fail('Нет текста');
  $t = mb_substr($t, 0, 900);
  $v = gz_secret('sp_voice'); if (!preg_match('~^[a-z_]{2,20}$~', $v)) $v = 'kirill';
  $ck = md5($v . '|' . $t); $c = itcai_cache_get('tts', $ck, 86400 * 30);
  if ($c && !empty($c['a'])) gz_out(['ok' => true, 'audio' => $c['a'], 'type' => 'audio/mpeg']);
  $parts = []; $cur = '';
  foreach (preg_split('~(?<=[.!?…;:])\s+~u', $t) as $sn) {
    while (mb_strlen($sn) > 240) { $cut = mb_strrpos(mb_substr($sn, 0, 240), ' ') ?: 240; if ($cur !== '') { $parts[] = $cur; $cur = ''; } $parts[] = trim(mb_substr($sn, 0, $cut)); $sn = trim(mb_substr($sn, $cut)); }
    if (mb_strlen($cur . ' ' . $sn) > 240) { if ($cur !== '') $parts[] = $cur; $cur = $sn; } else $cur = trim($cur . ' ' . $sn);
  }
  if ($cur !== '') $parts[] = $cur;
  $a = '';
  foreach (array_slice($parts, 0, 5) as $pt) {
    $body = json_encode(['text' => $pt, 'hints' => [['voice' => $v]], 'outputAudioSpec' => ['containerAudio' => ['containerAudioType' => 'MP3']], 'loudnessNormalizationType' => 'LUFS'], JSON_UNESCAPED_UNICODE);
    [$http, $r] = itcai_post('https://tts.api.cloud.yandex.net/tts/v3/utteranceSynthesis', $body, ['Content-Type: application/json', 'Authorization: Api-Key ' . $k], 30);
    if ($http !== 200) break;
    foreach (preg_split('~\r?\n~', $r) as $line) { $j = json_decode(trim($line), true); $d = $j['result']['audioChunk']['data'] ?? ($j['audioChunk']['data'] ?? ''); if ($d !== '') $a .= base64_decode($d); }
  }
  if ($a === '') gz_fail('Не удалось озвучить');
  $b = base64_encode($a); if (strlen($b) < 600000) itcai_cache_put('tts', $ck, ['a' => $b]);
  gz_out(['ok' => true, 'audio' => $b, 'type' => 'audio/mpeg']);
}

if ($act !== 'ask') gz_fail('Неизвестное действие');

$s = function ($v, $n) { return mb_substr(trim(preg_replace('~\s+~u', ' ', (string)$v)), 0, $n); };
$text = $s($in['text'] ?? '', 600);
/* v2 · ссылка (Amap, Dianping, карта, сайт): открываем, берём конечный адрес и заголовок; из ссылки Amap — точку на карте */
function gz_link($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 6, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36', CURLOPT_RANGE => '0-60000']);
  $html = (string)curl_exec($ch); $fin = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL); curl_close($ch);
  $t = ''; if (preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) $t = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
  $u = urldecode($fin ?: $url); $loc = null;
  if (preg_match_all('~(\d{2,3}\.\d{4,})\s*,\s*(\d{2,3}\.\d{4,})~', $u . ' ' . substr($html, 0, 20000), $mm, PREG_SET_ORDER)) foreach ($mm as $x) {
    $a = (float)$x[1]; $b = (float)$x[2];
    if ($a > 100 && $a < 125 && $b > 18 && $b < 30) { $loc = [$a, $b]; break; }
    if ($b > 100 && $b < 125 && $a > 18 && $a < 30) { $loc = [$b, $a]; break; }
  }
  return ['url' => mb_substr($u, 0, 400), 'title' => mb_substr($t, 0, 160), 'loc' => $loc];
}
$link = null;
if (preg_match('~https?://[^\s，。]+~u', $text, $lm)) $link = gz_link($lm[0]);
$ocr = ''; $err = '';
if (!empty($in['img'])) {
  $bin = base64_decode((string)$in['img'], true);
  if ($bin === false || strlen($bin) < 500) gz_fail('Фото не дошло — попробуй ещё раз');
  if (strlen($bin) > 6 * 1024 * 1024) gz_fail('Фото слишком большое');
  $ocr = $s(gz_ocr($bin, (string)($in['mime'] ?? 'image/jpeg'), $err), 1500);
}
if ($text === '' && $ocr === '' && empty($in['img'])) gz_fail('Напиши, скажи или сфотографируй, что нужно');

$places = [];
foreach (array_slice((array)($in['places'] ?? []), 0, 80) as $i => $p) {
  if (!is_array($p)) continue;
  $places[] = $i . ') ' . $s($p['ru'] ?? '', 60) . ' — ' . $s($p['zh'] ?? '', 80);
}
$near = [];
foreach (array_slice((array)($in['near'] ?? []), 0, 12) as $p) $near[] = $s(is_array($p) ? (($p['name'] ?? '') . ' (' . ($p['type'] ?? '') . ', ' . (int)($p['d'] ?? 0) . ' м)') : $p, 90);
$hist = [];
foreach (array_slice((array)($in['hist'] ?? []), -8) as $h) if (is_array($h)) $hist[] = (($h['r'] ?? '') === 'fil' ? 'Фил: ' : 'Человек: ') . $s($h['t'] ?? '', 300);
$last = is_array($in['last'] ?? null) ? $in['last'] : null;

$sys = "Ты — Фил, помощник в приложении «Мой Гуанчжоу». Им пользуются русские, которые живут и ездят по Гуанчжоу и Фошаню (метро, такси, рынки) и НЕ читают по-китайски и по-английски.\n"
 . "Человек пишет, говорит голосом или присылает фото (табличка станции метро, вывеска магазина, номер дома, визитка, название улицы, экран телефона). С фото тебе дают распознанный текст.\n"
 . "Твоя задача: понять, что это за место, назвать его по-русски и дать китайское название/адрес, по которому приложение найдёт место на карте Amap. Потом предложить: построить маршрут, адрес для такси, показать водителю, запомнить место.\n"
 . "Если человек просит маршрут к своему месту («к бухгалтеру», «домой», «в гостиницу») — найди его в списке «Мои места» по смыслу и верни его номер в mine.\n"
 . "Если человек соглашается на твоё предложение («да», «построй», «давай такси») — выполни действие с последним найденным местом (do), место повторять не нужно.\n"
 . "ЧТО ЕСТЬ В ПРИЛОЖЕНИИ (ты знаешь его целиком и водишь по нему): вкладки — «Адрес» (поиск адреса и маршрут: метро, автобус, пешком; варианты от ближних станций, номера станций, поездка с предупреждением о выходе), «Места» (Мои места, справочник рынков и мест Гуанчжоу и Фошаня, «Запомнить, где я», карта мест), «События» (выставки, концерты, Кантонская ярмарка, фильтр Гуанчжоу/Фошань, поиск), «Схема» (схема метро), «Навигатор» (пеший навигатор с русским голосом). Настройки (шестерёнка): «Мои маршруты» (последние 40), как добираться, пешком до метро, голос Фила, новый разговор, «Дом», копия мест, оформление (фон и плашки), язык, крупный шрифт, подсказка про VPN, «О приложении».\n"
 . "ПЕРЕКЛЮЧИТЬ ЭКРАН: если просят открыть/показать раздел («покажи схему метро», «открой мои места», «открой настройки», «поменяй фон», «мои прошлые маршруты», «включи навигатор») — верни open: search|places|events|schema|nav|settings|routes|bg|about, а в say коротко скажи, что открываешь и что там можно сделать. Для поиска в разделе — open_q (например рынок в «Местах» или тема в «Событиях»).\n"
 . "СОБЫТИЯ: тебе дают список ближайших событий с номерами. На вопросы «какие события сегодня / на выходных / какие выставки / концерты / когда Кантонская ярмарка» — выбери подходящие и верни events: [номера], а в say — коротко по-русски, что нашлось (сегодня — это дата «Сегодня»). Если ничего не подходит — скажи честно и предложи открыть «События».\n"
 . "ТЕКУЩИЙ МАРШРУТ: если человек говорит про маршрут на экране («этот маршрут», «куда едем», «конечную точку», «пункт назначения», «это место», «сюда», «туда, куда строили») — это место из «Маршрут на экране сейчас». Тогда верни use=\"route\", place=null, и выполни просьбу: «занеси/сохрани/добавь в мои места как …» → save=true и label; «адрес для такси» → do=\"taxi\". НЕ переспрашивай, какое место, если маршрут на экране есть.\n"
 . "ГДЕ Я СЕЙЧАС: если человек говорит про место, где он стоит («запомни, где я», «определи эту точку», «занеси это место, я здесь», «гостиница, в которой я живу — я сейчас в ней», «тут вкусный ресторан с рыбой — сохрани») и не называет адрес — верни use=\"here\", place=null, save=true и label — его название по-русски, коротко и понятно («Ресторан с вкусной рыбой», «Моя гостиница»). Приложение само определит точку по местоположению.\n"
 . "ПОИСК РЯДОМ: если просят найти ближайшее («найди банкомат Bank of China», «ближайшее отделение банка», «продуктовый супермаркет рядом», «торговый центр поблизости», «аптека», «туалет») — верни find, а place=null. "
 . "find.kw — ключевое слово по-китайски для карты (марка или вид: 中国银行, 工商银行, 招商银行, 超市, 购物中心, 药店, 麦当劳, 711便利店), find.type — код вида места Amap: "
 . "160300 банкомат (ATM), 160100 отделение банка, 060400 супермаркет, 060100 торговый центр, 060200 магазин у дома, 050000 кафе и рестораны, 090100 больница, 090601 аптека, 100000 гостиница, 150500 станция метро, 200300 туалет, 070000 услуги, пусто — если не подходит. "
 . "Банкомат и отделение — разные: «банкомат», «снять наличные» → 160300; «отделение», «банк открыть счёт / обменять» → 160100. find.what — что ищем по-русски («банкоматы Bank of China»), find.radius — метры (обычно 2000, для редкого 5000).\n"
 . "Если перед этим Фил показал список найденных мест и человек выбирает («ко второму», «к ближайшему», «первый») — верни pick: номер из списка (1 = первый), do — что сделать.\n"
 . "Человек может попросить несколько действий сразу («построй маршрут и добавь в мои места как офис бухгалтерии») — тогда do=\"route\" и save=true. Просьба «сохрани / добавь / запомни в мои места» — ВСЕГДА save=true.\n"
 . "Если человек просит сохранить место и называет его по-своему («это офис бухгалтера», «запомни как моя гостиница») — do=\"save\", а его название запиши в label (с большой буквы, коротко: «Офис бухгалтера»). Адрес он может вставить текстом или ссылкой, а сказать голосом.\n"
 . "Станции метро: zh — «название + 地铁站» (например 萝岗地铁站). Номер дома: собери полный адрес иероглифами (город, район, улица, номер). Если на фото только номер дома или часть адреса — используй места рядом с человеком, чтобы понять улицу.\n"
 . "Не выдумывай адреса. Если текста мало и непонятно — честно скажи и попроси сфотографировать вывеску ближе или написать название.\n"
 . "Говори по-русски, коротко (1–3 предложения), на «ты», дружелюбно, без привязки к полу (не «понял», а «хорошо», «вижу»). Китайские названия в say пиши иероглифами и в скобках пиньинь, если это станция.\n"
 . "Ответ — ТОЛЬКО JSON без пояснений и без ```:\n"
 . '{"say":"что сказать человеку","place":{"zh":"китайское название или адрес для поиска","ru":"по-русски, коротко","what":"станция метро|здание|улица|магазин|рынок|гостиница|ресторан|адрес|другое","city":"广州|佛山|"} или null,'
 . '"find":{"kw":"","type":"","what":"","radius":2000} или null,"pick":0,"open":"","open_q":"","events":[],"use":"route" или "here" или "","save":true или false,"label":"как человек сам назвал место, или пусто","mine":номер из «Мои места» или -1,"do":"route|taxi|driver|map|save|none","from":"here|home","how":"mt|tr|wk|","offer":["route","taxi","driver","save"]}' . "\n"
 . "do — только если человек прямо попросил (маршрут/такси/показать водителю/на карте/запомнить). Иначе do=\"none\", а в offer перечисли, что предложить кнопками. from=home, только если сказал «от дома». how — как добираться, только если человек сам назвал: «на метро» → mt, «на автобусе» → tr, «пешком» → wk; иначе пусто.";

$u = '';
if ($hist) $u .= "Разговор до этого:\n" . implode("\n", $hist) . "\n\n";
$found = [];
foreach (array_slice((array)($in['list'] ?? []), 0, 10) as $i => $p) $found[] = ($i + 1) . ') ' . $s(is_array($p) ? (($p['name'] ?? '') . ' · ' . (int)($p['d'] ?? 0) . ' м') : $p, 120);
if ($found) $u .= "Фил только что показал список:\n" . implode("\n", $found) . "\n";
$cur = is_array($in['route'] ?? null) ? $in['route'] : null;
if ($cur && !empty($cur['name'])) $u .= 'Маршрут на экране сейчас: конечная точка — ' . $s($cur['name'], 160) . (!empty($cur['addr']) ? ' (' . $s($cur['addr'], 160) . ')' : '') . (!empty($cur['from']) ? ', откуда — ' . $s($cur['from'], 80) : '') . "\n";
$ev = [];
foreach (array_slice((array)($in['events'] ?? []), 0, 45) as $i => $e) if (is_array($e)) $ev[] = $i . ') ' . $s(($e['ru'] ?? '') . ' · ' . ($e['k'] ?? '') . ' · ' . ($e['from'] ?? '') . (($e['to'] ?? '') && ($e['to'] ?? '') !== ($e['from'] ?? '') ? '—' . $e['to'] : '') . ' · ' . ($e['v'] ?? '') . ' · ' . ($e['city'] ?? ''), 200);
$td = preg_match('~^\d{4}-\d\d-\d\d$~', (string)($in['today'] ?? '')) ? $in['today'] : date('Y-m-d');
if ($ev) $u .= 'Сегодня: ' . $td . ' (' . ['вс','пн','вт','ср','чт','пт','сб'][(int)date('w', strtotime($td))] . ")\nБлижайшие события:\n" . implode("\n", $ev) . "\n";
if (!empty($in['tab'])) $u .= 'Сейчас открыт раздел: ' . $s($in['tab'], 20) . "\n";
if ($last) $u .= 'Последнее найденное место: ' . $s(($last['ru'] ?? '') . ' — ' . ($last['zh'] ?? ''), 200) . "\n";
if ($places) $u .= "Мои места:\n" . implode("\n", $places) . "\n";
if ($near) $u .= "Рядом с человеком сейчас: " . implode('; ', $near) . "\n";
if ($link) $u .= 'Человек вставил ссылку. Она ведёт на: ' . $link['url'] . ($link['title'] !== '' ? ' · заголовок страницы: ' . $link['title'] : '') . ($link['loc'] ? ' · точка на карте есть' : '') . "\n";
if (!empty($in['img'])) $u .= 'Человек прислал фото. Текст на фото: ' . ($ocr !== '' ? $ocr : '(текст не распознан' . ($err ? ', ' . $err : '') . ')') . "\n";
$u .= 'Человек: ' . ($text !== '' ? $text : '(без подписи — что это и куда это?)');

$ans = itcai_ask($sys, $u, 700, 40, true);
$j = itcai_json($ans);
if (!$j) gz_fail('Фил сейчас не отвечает — попробуй ещё раз через минуту');
$pl = is_array($j['place'] ?? null) ? $j['place'] : null;
if ($pl) { $pl = ['zh' => $s($pl['zh'] ?? '', 160), 'ru' => $s($pl['ru'] ?? '', 120), 'what' => $s($pl['what'] ?? '', 30), 'city' => $s($pl['city'] ?? '', 10)]; if ($pl['zh'] === '') $pl = null; }
$do = in_array($j['do'] ?? '', ['route', 'taxi', 'driver', 'map', 'save'], true) ? $j['do'] : 'none';
$offer = array_values(array_intersect((array)($j['offer'] ?? []), ['route', 'taxi', 'driver', 'save']));
if ($pl && $link && $link['loc']) $pl['loc'] = $link['loc'];
if (!$pl && $link && $link['loc']) $pl = ['zh' => $link['title'] !== '' ? $link['title'] : 'Место по ссылке', 'ru' => '', 'what' => 'другое', 'city' => '', 'loc' => $link['loc']];
$fd = is_array($j['find'] ?? null) ? $j['find'] : null;
if ($fd) { $fd = ['kw' => $s($fd['kw'] ?? '', 40), 'type' => preg_replace('~[^0-9|]~', '', (string)($fd['type'] ?? '')), 'what' => $s($fd['what'] ?? '', 80), 'radius' => max(300, min(10000, (int)($fd['radius'] ?? 2000)))]; if ($fd['kw'] === '' && $fd['type'] === '') $fd = null; }
$op = in_array($j['open'] ?? '', ['search', 'places', 'events', 'schema', 'nav', 'settings', 'routes', 'bg', 'about'], true) ? $j['open'] : '';
$evs = array_values(array_filter(array_map('intval', (array)($j['events'] ?? [])), function ($x) { return $x >= 0 && $x < 45; }));
gz_out(['ok' => true, 'open' => $op, 'open_q' => $s($j['open_q'] ?? '', 60), 'events' => array_slice($evs, 0, 8), 'use' => (($j['use'] ?? '') === 'route' && $cur) ? 'route' : (($j['use'] ?? '') === 'here' ? 'here' : ''), 'find' => $fd, 'pick' => (int)($j['pick'] ?? 0), 'say' => $s($j['say'] ?? '', 600), 'place' => $fd ? null : $pl, 'label' => $s($j['label'] ?? '', 80), 'save' => !empty($j['save']) || ($j['do'] ?? '') === 'save' || (bool)preg_match('~(сохран|добав|запомн).{0,40}(мои|мест)~ui', $text), 'mine' => (int)($j['mine'] ?? -1), 'do' => $do,
  'from' => ($j['from'] ?? '') === 'home' ? 'home' : 'here', 'how' => gz_how($text, (string)($j['how'] ?? '')), 'offer' => $offer, 'ocr' => $ocr]);

function gz_how($t, $h) {
  if (preg_match('~на\s+метро|метром|подземк|地铁~ui', $t)) return 'mt';
  if (preg_match('~на\s+автобус|автобусом|公交~ui', $t)) return 'tr';
  if (preg_match('~пешком|пешеход|步行|走路~ui', $t)) return 'wk';
  return in_array($h, ['mt', 'tr', 'wk'], true) ? $h : '';
}
