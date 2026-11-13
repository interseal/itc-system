<?php
// ==== Мой Гуанчжоу · events_sync.php v2 · 16.10.2026 · переводы en/zh для новых событий, справочник ярмарки на трёх языках (canton_i18n) ====
// Сам собирает «События» для приложения: выставки Гуанчжоу и Фошаня,
// даты Кантонской ярмарки, концерты, шоу и спорт международного уровня.
//
// Откуда берёт (раз в сутки):
//   • tradefairdates.com — расписание комплекса Кантонской ярмарки и PWTC (англ.);
//   • jufair.com (聚展网) — все выставки Гуанчжоу и Фошаня (кит.);
//   • localhub.to — афиша концертных залов и стадионов (копия 大麦/Damai);
//   • songkick.com, bachtrack.com — зарубежные группы и классика.
// Китайские названия переводит YandexGPT (ключи — из config.php магазина, как у tr.php).
// Из афиши оставляет только зарубежных артистов и международные события.
//
// Запуск:
//   • планировщик хостинга раз в день:  wget -q -O /dev/null "https://metro.itcpartner.store/events_sync.php?cron=1"
//   • само приложение, если база старше суток (не чаще раза в час);
//   • вручную с ответом:  events_sync.php?cron=1&wait=1
// Отчёт о последнем запуске:  events_sync.php?report=1

$CLI = (PHP_SAPI === 'cli');
$DIR = __DIR__ . '/data';
if (!is_dir($DIR)) @mkdir($DIR, 0755);
if (!is_file("$DIR/.htaccess")) @file_put_contents("$DIR/.htaccess", "Require all denied\nDeny from all\n");
$FILE = "$DIR/events.json";
$BASE = "$DIR/events_base.json";
$LOGF = "$DIR/events_log.json";
$AIC  = "$DIR/events_ai.json";
$LOCK = "$DIR/events.lock";
$TRY  = "$DIR/events_try.txt";
date_default_timezone_set('Asia/Shanghai');

if (!$CLI && isset($_GET['report'])) { report($LOGF, $FILE); exit; }

function out($a) { global $CLI; if (!$CLI && !headers_sent()) header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n"; exit; }

$cron = $CLI || isset($_GET['cron']);
$wait = $CLI || isset($_GET['wait']);
if (!$CLI) header('Cache-Control: no-store');

// не чаще, чем нужно: свежая база — ничего не делаем
if (!$cron && is_file($FILE) && time() - filemtime($FILE) < 20 * 3600) out(['ok' => true, 'skipped' => 'fresh']);
if (!$CLI && is_file($TRY) && time() - filemtime($TRY) < ($cron ? 600 : 3600)) out(['ok' => true, 'skipped' => 'recent']);
@touch($TRY);
$lk = @fopen($LOCK, 'c');
if (!$lk || !flock($lk, LOCK_EX | LOCK_NB)) out(['ok' => true, 'skipped' => 'busy']);
ignore_user_abort(true);
@set_time_limit(900);
if (!$wait) {   // отвечаем сразу, работаем дальше в фоне
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo '{"ok":true,"started":true}';
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { @ob_end_flush(); @flush(); }
}

$T0 = microtime(true);
$TODAY = date('Y-m-d');
$LOG = ['started' => date('c'), 'sources' => [], 'ai' => ['calls' => 0, 'ok' => 0, 'err' => ''], 'notes' => []];

// ---------- база (ручная проверка) ----------
$base = is_file($BASE) ? json_decode(file_get_contents($BASE), true) : null;
if (!$base) $base = ['canton' => null, 'events' => []];
$canton = $base['canton'];
$EV = [];
foreach (($base['events'] ?? []) as $e) { $e['vk'] = vkey($e['vzh'] ?? ''); $EV[] = $e; }

$AI = is_file($AIC) ? (json_decode(file_get_contents($AIC), true) ?: []) : [];

// ---------- 1. tradefairdates ----------
$TFD = [];
foreach ([
    ['cf',   'https://www.tradefairdates.com/Exhibition-Centre-Canton-Fair-Complex-Guangzhou-ZS7743.html'],
    ['pwtc', 'https://www.tradefairdates.com/Exhibition-Centre-PWTC-Poly-World-Trade-Center-Guangzhou-ZS3855.html'],
] as [$vk, $url]) {
    $h = http_get($url, $err);
    $list = $h ? p_tfd($h, $vk, $url) : [];
    if ($h && !$list) $list = ai_extract($h, $url, 'en', $vk);
    srclog('tradefairdates · ' . ($vk === 'cf' ? 'комплекс Кантонской ярмарки' : 'PWTC'), $url, $h, $list, $err);
    $TFD = array_merge($TFD, $list);
}

// ---------- 2. jufair ----------
$JUF = [];
foreach (['guangzhou' => 12, 'foshan' => 5] as $city => $maxp) {
    $got = []; $okp = 0; $err = ''; $first = null;
    for ($p = 1; $p <= $maxp; $p++) {
        $url = "https://www.jufair.com/t-$city/" . ($p > 1 ? "p-$p/" : '');
        $h = http_get($url, $e1);
        if (!$h) { $err = $e1; break; }
        if ($first === null) $first = $h;
        $okp++;
        $l = p_jufair($h);
        if (!$l && $p === 1) $l = ai_extract($h, $url, 'zh', '');
        if (!$l) break;
        $got = array_merge($got, $l);
        if (strpos($h, "/t-$city/p-" . ($p + 1) . '/') === false) break;
        usleep(400000);
    }
    $LOG['sources'][] = ['name' => "jufair · $city ($okp стр.)", 'url' => "https://www.jufair.com/t-$city/", 'ok' => $okp > 0, 'n' => count($got), 'err' => $err];
    $JUF = array_merge($JUF, $got);
}

// ---------- 3. афиша: localhub (Damai), songkick, bachtrack ----------
$FUN = [];
$LH_FALLBACK = ['guangzhou' => ['guangzhou-xinghai-concert-hall', 'guangzhou-opera-house-opera-hall', 'guangzhou-grand-theatre-experimental-theatre',
    'guangzhou-sun-yat-sen-memorial-hall', 'guangzhou-baiyun-international-convention-center-century-hall', 'guangdong-art-theatre',
    'guangzhou-tower-theatre', 'guangzhou-hongxiannv-grand-theatre', 'guangdong-olympic-sports-center-stadium',
    'baoneng-guangzhou-international-sports-and-performance-center', 'guangzhou-asian-games-city-comprehensive-gymnasium',
    'guangzhou-university-town-sports-center-stadium', 'guangdong-friendship-theatre', 'guangzhou-zhengjia-grand-theatre',
    'music-tangren-hall', 'guangzhou-beilei-theatre', 'zhuying-no-1-new-space-hall-a', 'omni-space-guangzhou-hall-1', '广州体育馆'], 'foshan' => []];
foreach (['guangzhou', 'foshan'] as $city) {
    $h = http_get("https://localhub.to/$city?lang=zh", $err);
    $slugs = [];
    if ($h && preg_match_all('~/rss/' . $city . '/venue/([^"\'<>\s?#]+?)\.xml~u', $h, $m)) $slugs = array_map('rawurldecode', $m[1]);
    $slugs = array_values(array_unique(array_merge($slugs, $LH_FALLBACK[$city])));
    $n = 0; $okv = 0;
    foreach ($slugs as $s) {
        $u = "https://localhub.to/rss/$city/venue/" . rawurlencode($s) . '.xml';
        $x = http_get($u, $e2);
        if (!$x) continue;
        $okv++;
        $l = p_rss($x, $city === 'foshan' ? 'fs' : 'gz', 'LocalHub (Damai)');
        $n += count($l); $FUN = array_merge($FUN, $l);
        usleep(250000);
    }
    $LOG['sources'][] = ['name' => "LocalHub афиша · $city ($okv площадок)", 'url' => "https://localhub.to/$city?lang=zh", 'ok' => $okv > 0 || ($city === 'foshan' && $h), 'n' => $n, 'err' => $okv ? '' : $err];
}
foreach ([
    ['Songkick · Гуанчжоу', 'https://www.songkick.com/metro-areas/27728-china-guangzhou'],
    ['Bachtrack · Гуанчжоу', 'https://bachtrack.com/city/guangzhou'],
] as [$nm, $url]) {
    $h = http_get($url, $err);
    $l = $h ? p_jsonld($h, $nm) : [];
    srclog($nm, $url, $h, $l, $err);
    $FUN = array_merge($FUN, $l);
}

// ---------- 4. Кантонская ярмарка: даты фаз ----------
$cfPh = [];
foreach ($TFD as $i => $e) {
    if (preg_match('~Canton\s*Fair\s*Phase\s*(\d)~i', $e['en'], $m)) {
        $y = (int)substr($e['from'], 0, 4); $mo = (int)substr($e['from'], 5, 2);
        $n = 2 * ($y - 2026) + ($mo >= 8 ? 140 : 139);
        $cfPh[$n][(int)$m[1]] = ['p' => (int)$m[1], 'from' => $e['from'], 'to' => $e['to']];
        unset($TFD[$i]);
    } elseif (preg_match('~Canton\s*Fair~i', $e['en'])) unset($TFD[$i]);
}
if ($canton && $cfPh) {
    $ss = [];
    foreach ($canton['sessions'] as $s) $ss[$s['n']] = $s;
    foreach ($cfPh as $n => $ph) {
        ksort($ph);
        if (!isset($ss[$n])) $ss[$n] = ['n' => $n, 'phases' => []];
        $old = []; foreach ($ss[$n]['phases'] as $p) $old[$p['p']] = $p;
        foreach ($ph as $k => $p) $old[$k] = $p;
        ksort($old); $ss[$n]['phases'] = array_values($old);
        $LOG['notes'][] = "Кантонская ярмарка: $n-я сессия — даты фаз с tradefairdates";
    }
    ksort($ss); $canton['sessions'] = array_values($ss);
}
if ($canton) {   // прошедшие сессии убираем, но одну оставляем
    $keep = array_values(array_filter($canton['sessions'], function ($s) use ($TODAY) { $l = end($s['phases']); return $l && $l['to'] >= $TODAY; }));
    if ($keep) $canton['sessions'] = $keep; else $canton['sessions'] = [end($canton['sessions'])];
}

// ---------- 5. перевод и отбор (YandexGPT) ----------
$auto = [];
foreach (array_merge(array_values($TFD), $JUF) as $e) {
    if ($e['to'] < $TODAY) continue;
    if (preg_match('~广交会|进出口商品交易会(?!展馆)~u', $e['zh'] ?? '') || preg_match('~Canton\s*Fair~i', $e['en'] ?? '')) continue;
    $e['k'] = 'expo';
    $auto[] = $e;
}
$fun = [];
foreach ($FUN as $e) { if ($e['to'] >= $TODAY && $e['from'] <= date('Y-m-d', strtotime('+10 months'))) $fun[] = $e; }

// что уже есть в базе — не переводим (сэкономим)
$autoNew = []; foreach ($auto as $e) if (!find_match($EV, $e, 'expo')) $autoNew[] = $e;
$funNew = [];  foreach ($fun as $e) if (!find_match($EV, $e, 'fun')) $funNew[] = $e;
ai_enrich($autoNew, 'expo');
ai_enrich($funNew, 'fun');
file_put_contents($AIC . '.tmp', json_encode($AI, JSON_UNESCAPED_UNICODE)); @rename($AIC . '.tmp', $AIC);

// ---------- 6. слияние ----------
$added = ['expo' => 0, 'fun' => 0, 'upd' => 0];
foreach ($auto as $e) merge_in($EV, $e, 'expo');
foreach ($fun as $e) merge_in($EV, $e, 'fun');
$EV = array_values(array_filter($EV, function ($e) use ($TODAY) { return ($e['to'] ?? $e['from']) >= $TODAY && ($e['ru'] ?? '') !== ''; }));
usort($EV, function ($a, $b) { return strcmp($a['from'] . $a['ru'], $b['from'] . $b['ru']); });
foreach ($EV as &$e) unset($e['vk'], $e['cache']); unset($e);

$LOG['added'] = $added;
$LOG['total'] = count($EV);
$LOG['sec'] = round(microtime(true) - $T0, 1);
$LOG['finished'] = date('c');
$anyOk = false; foreach ($LOG['sources'] as $s) if ($s['ok'] && $s['n']) $anyOk = true;
$res = ['v' => 1, 'updated' => date('c'), 'canton' => $canton, 'canton_i18n' => ($base['canton_i18n'] ?? null), 'events' => $EV,
        'sources' => array_map(function ($s) { return ['name' => $s['name'], 'ok' => $s['ok'], 'n' => $s['n']]; }, $LOG['sources'])];
if ($anyOk || !is_file($FILE)) {
    file_put_contents($FILE . '.tmp', json_encode($res, JSON_UNESCAPED_UNICODE));
    @rename($FILE . '.tmp', $FILE);
} else $LOG['notes'][] = 'Ни один источник не ответил — старая база оставлена как есть.';
file_put_contents($LOGF, json_encode($LOG, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
flock($lk, LOCK_UN);
if ($wait) out(['ok' => true, 'total' => count($EV), 'added' => $added, 'sec' => $LOG['sec'], 'sources' => $LOG['sources'], 'ai' => $LOG['ai'], 'notes' => $LOG['notes']]);
exit;

// =====================================================================

function srclog($name, $url, $h, $list, $err) {
    global $LOG; $LOG['sources'][] = ['name' => $name, 'url' => $url, 'ok' => (bool)$h, 'n' => count($list), 'err' => $h ? '' : (string)$err];
}

function http_get($url, &$err = null) {
    $fx = getenv('EVENTS_FIXTURES');   // только для проверки на компьютере разработчика
    if ($fx) { $f = $fx . '/' . md5($url) . '.html'; if (is_file($f)) return file_get_contents($f); $err = 'нет файла'; return null; }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 35, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,ru;q=0.7'],
    ]);
    $b = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $ce = curl_error($ch); curl_close($ch);
    if ($b === false || $code !== 200 || strlen($b) < 200) { $err = $b === false ? $ce : "HTTP $code"; return null; }
    if (!preg_match('~charset\s*=\s*["\']?utf-?8~i', substr($b, 0, 3000)) && preg_match('~charset\s*=\s*["\']?(gbk|gb2312|gb18030)~i', substr($b, 0, 3000))) {
        $c = @mb_convert_encoding($b, 'UTF-8', 'GB18030'); if ($c) $b = $c;
    }
    return $b;
}

function h2t($h) {
    $h = preg_replace('~<(script|style|noscript|svg)\b.*?</\1>~is', ' ', $h);
    $h = preg_replace('~<br\s*/?>|</(p|div|li|tr|h\d|dd|dt|td|section|article|ul|ol)>~i', "\n", $h);
    $t = html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);
    $lines = array_filter(array_map(function ($l) { return trim(preg_replace('~[ \t]+~u', ' ', $l)); }, explode("\n", $t)), 'strlen');
    return implode("\n", $lines);
}
function txt($h) { return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }

// ----- площадки -----
function vkey($v) {
    $v = (string)$v;
    $map = ['cf' => '广交会|进出口商品交易会|Canton Fair Complex', 'pwtc' => '保利世贸|PWTC|Poly World', 'tz' => '潭洲|Tanzhou', 'kg' => '空港博览|Airport Expo',
        'hz' => '海珠国际会展', 'fsi' => '佛山国际会议展览|Foshan International Conference', 'nhi' => '南海国际会展', 'by' => '白云国际会议', 'nf' => '南丰',
        'lj' => '伦教展览', 'xh' => '星海音乐厅', 'opera' => '广州大剧院', 'zs' => '中山纪念堂'];
    foreach ($map as $k => $rx) if (preg_match('~' . $rx . '~iu', $v)) return $k;
    return $v === '' ? '' : 'v' . substr(md5(preg_replace('~\s+~u', '', $v)), 0, 6);
}
function vinfo($vk) {
    $V = ['cf' => ['中国进出口商品交易会展馆（广交会展馆）', 'Комплекс Кантонской ярмарки (Пачжоу)', 'gz'],
          'pwtc' => ['广州保利世贸博览馆', 'Poly World Trade Center Expo (PWTC, Пачжоу)', 'gz'],
          'tz' => ['广东潭洲国际会展中心', 'Таньчжоу (Шуньдэ, Фошань)', 'fs'],
          'kg' => ['广州空港博览中心', 'Выставочный центр аэропорта Гуанчжоу (Байюнь)', 'gz'],
          'hz' => ['广州海珠国际会展中心', 'Хайчжу Международный выставочный центр', 'gz'],
          'fsi' => ['佛山国际会议展览中心', 'Международный выставочный центр Фошаня', 'fs'],
          'nhi' => ['佛山南海国际会展中心', 'Международный выставочный центр Наньхай (Фошань)', 'fs'],
          'by' => ['广州白云国际会议中心', 'Конгресс-центр Байюнь', 'gz'],
          'nf' => ['广州南丰国际会展中心', 'Выставочный центр Наньфэн (Пачжоу)', 'gz'],
          'lj' => ['佛山顺德伦教展览馆', 'Выставочный зал Луньцзяо (Шуньдэ)', 'fs'],
          'xh' => ['广州星海音乐厅', 'Концертный зал Синхай', 'gz'],
          'zs' => ['广州中山纪念堂', 'Мемориальный зал Сунь Ятсена', 'gz']];
    return $V[$vk] ?? null;
}
function city_of($s, $def = '') {
    if (preg_match('~佛山|顺德|南海|禅城|潭洲|三水|高明|北滘|乐从|伦教|Foshan~iu', $s)) return 'fs';
    if (preg_match('~广州|广交会|进出口商品交易会|琶洲|保利世贸|白云|海珠|番禺|南沙|增城|花都|天河|黄埔|越秀|荔湾|从化|空港|南丰|Guangzhou~iu', $s)) return 'gz';
    return $def;
}

// ----- даты -----
function en_mon($s) { $m = ['jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12]; return $m[strtolower(substr($s, 0, 3))] ?? 0; }
function ymd($y, $m, $d) { return checkdate((int)$m, (int)$d, (int)$y) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : null; }
function parse_en_range($t) {
    if (preg_match('~(\d{1,2})\.?\s*(?:([A-Za-z]{3,9})\.?\s*)?(?:(20\d\d)\s*)?[-–—]\s*(\d{1,2})\.?\s*([A-Za-z]{3,9})\.?\s*(20\d\d)~', $t, $m)) {
        $m2 = en_mon($m[5]); $y2 = (int)$m[6]; $m1 = $m[2] !== '' ? en_mon($m[2]) : $m2;
        if ($m1 && $m2) { $y1 = $m[3] !== '' ? (int)$m[3] : ($m1 > $m2 ? $y2 - 1 : $y2); $a = ymd($y1, $m1, $m[1]); $b = ymd($y2, $m2, $m[4]); if ($a && $b && $a <= $b) return [$a, $b]; }
    }
    if (preg_match('~(\d{1,2})\.?\s*([A-Za-z]{3,9})\.?\s*(20\d\d)~', $t, $m) && en_mon($m[2])) { $a = ymd($m[3], en_mon($m[2]), $m[1]); if ($a) return [$a, $a]; }
    if (preg_match('~(20\d\d)-(\d\d)-(\d\d)(?:.{0,12}?(20\d\d)-(\d\d)-(\d\d))?~s', $t, $m)) { $a = ymd($m[1], $m[2], $m[3]); $b = !empty($m[4]) ? ymd($m[4], $m[5], $m[6]) : $a; if ($a && $b) return [$a, max($a, $b)]; }
    return null;
}
function parse_zh_range($t) {
    if (preg_match('~(20\d\d)\s*[.\-/年]\s*(\d{1,2})\s*[.\-/月]\s*(\d{1,2})\s*日?\s*(?:[-\~～至—–]+|到)\s*(?:(20\d\d)\s*[.\-/年]\s*)?(?:(\d{1,2})\s*[.\-/月]\s*)?(\d{1,2})\s*日?~u', $t, $m)) {
        $y1 = (int)$m[1]; $m1 = (int)$m[2]; $y2 = !empty($m[4]) ? (int)$m[4] : $y1; $m2 = !empty($m[5]) ? (int)$m[5] : $m1;
        if (empty($m[4]) && $m2 < $m1) $y2++;
        $a = ymd($y1, $m1, $m[3]); $b = ymd($y2, $m2, $m[6]); if ($a && $b && $a <= $b) return [$a, $b];
    }
    if (preg_match('~(20\d\d)\s*[.\-/年]\s*(\d{1,2})\s*[.\-/月]\s*(\d{1,2})~u', $t, $m)) { $a = ymd($m[1], $m[2], $m[3]); if ($a) return [$a, $a]; }
    return null;
}

// ----- разбор: tradefairdates -----
function blocks($html, $rx) {   // [slug => html-кусок от первой ссылки до следующей карточки]
    if (!preg_match_all($rx, $html, $m, PREG_OFFSET_CAPTURE)) return [];
    $first = [];
    foreach ($m[1] as $i => $g) { $slug = $g[0]; if (!isset($first[$slug])) $first[$slug] = $m[0][$i][1]; }
    $pos = array_values($first); $keys = array_keys($first); $out = [];
    foreach ($keys as $i => $k) {
        $st = max(0, strrpos(substr($html, 0, $pos[$i]), '<a') ?: $pos[$i]);
        $en = isset($pos[$i + 1]) ? (strrpos(substr($html, 0, $pos[$i + 1]), '<a') ?: $pos[$i + 1]) : $pos[$i] + 5000;
        if ($en <= $st) $en = $st + 3000;
        $out[$k] = substr($html, $st, min($en - $st, 12000));
    }
    return $out;
}
function p_tfd($html, $vk, $page) {
    $out = [];
    foreach (blocks($html, '~href="[^"]*?/([A-Za-z0-9\-]+-M\d+)/(?:Guangzhou|Foshan)\.html"~i') as $slug => $b) {
        $name = '';
        if (preg_match('~(?:alt|title)="([^"]{3,140})"~i', $b, $m)) $name = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        if ($name === '' && preg_match('~<a\b[^>]*>(.*?)</a>~is', $b, $m)) $name = txt($m[1]);
        if ($name === '') $name = str_replace('-', ' ', preg_replace('~-M\d+$~', '', $slug));
        $name = trim(preg_replace('~\s+(Guangzhou|Foshan)$~i', '', $name));
        $d = parse_en_range(h2t($b));
        if (!$d) continue;
        $vi = vinfo($vk);
        $url = 'https://www.tradefairdates.com/' . $slug . '/Guangzhou.html';
        $out[] = ['en' => $name, 'zh' => '', 'from' => $d[0], 'to' => $d[1], 'vzh' => $vi[0], 'vru' => $vi[1], 'city' => $vi[2], 'url' => $url, 'src' => 'tradefairdates.com'];
    }
    return $out;
}
// ----- разбор: jufair -----
function p_jufair($html) {
    $out = [];
    foreach (blocks($html, '~href="(?:https?:)?(?://(?:www\.|en\.)?jufair\.com)?/exhibition/([^"/?#]+)/?"~i') as $slug => $b) {
        $cands = [];
        if (preg_match_all('~<a\b[^>]*/exhibition/' . preg_quote($slug, '~') . '/?"[^>]*>(.*?)</a>~is', $b, $m)) foreach ($m[1] as $x) $cands[] = txt($x);
        if (preg_match_all('~(?:title|alt)="([^"]*\p{Han}[^"]*)"~u', $b, $m)) foreach ($m[1] as $x) $cands[] = trim(html_entity_decode($x, ENT_QUOTES, 'UTF-8'));
        $title = '';
        foreach ($cands as $c) if (preg_match('~\p{Han}~u', $c) && mb_strlen($c) > mb_strlen($title) && mb_strlen($c) < 90) $title = $c;
        if ($title === '') continue;
        $t = h2t($b);
        if (preg_match('~(?<![票价])取消|已延期|延期举办|停办~u', $t)) continue;
        $d = parse_zh_range($t);
        if (!$d) continue;
        $v = '';
        if (preg_match('~(?:举办展馆|展馆|展会地点|举办地点)\s*[：:]?\s*([^\n]{2,60})~u', $t, $m)) $v = trim($m[1]);
        $city = city_of($v);
        if ($city === '') continue;          // не Гуанчжоу и не Фошань (Дунгуань, Шэньчжэнь…)
        $vk = vkey($v); $vi = vinfo($vk);
        $en = '';
        if (preg_match('~\n([A-Z][A-Za-z0-9 &+\-\.]{2,40})\n~', "\n$t\n", $m) && !preg_match('~^(VIP|NEW|HOT)$~', trim($m[1]))) $en = trim($m[1]);
        $out[] = ['en' => $en, 'zh' => $title, 'from' => $d[0], 'to' => $d[1], 'vzh' => $vi ? $vi[0] : $v, 'vru' => $vi ? $vi[1] : '', 'city' => $city,
                  'url' => 'https://www.jufair.com/exhibition/' . $slug . '/', 'src' => 'jufair.com'];
    }
    return $out;
}
// ----- разбор: RSS афиши -----
function p_rss($xml, $city, $src) {
    libxml_use_internal_errors(true);
    $x = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$x || !isset($x->channel)) return [];
    $chan = trim((string)$x->channel->title);
    $out = [];
    foreach ($x->channel->item as $it) {
        $title = trim(txt((string)$it->title)); $desc = trim(txt((string)$it->description)); $link = trim((string)$it->link);
        if ($title === '') continue;
        $dates = [];
        if (preg_match_all('~(20\d\d)[/.\-年](\d{1,2})[/.\-月](\d{1,2})~u', $desc, $m, PREG_SET_ORDER)) foreach ($m as $g) { $d = ymd($g[1], $g[2], $g[3]); if ($d) $dates[] = $d; }
        if (!$dates && (string)$it->pubDate !== '') { $ts = strtotime((string)$it->pubDate); if ($ts) $dates[] = date('Y-m-d', $ts + 8 * 3600); }
        if (!$dates) continue;
        sort($dates);
        $parts = array_map('trim', preg_split('~\s*[·|]\s*~u', $desc));
        $venue = ($parts[0] ?? '') !== '' && !preg_match('~^20\d\d~', $parts[0]) ? $parts[0] : preg_replace('~^.*?[-–|:]\s*~u', '', $chan);
        $who = '';
        foreach (array_slice($parts, 1) as $p) if ($p !== '' && !preg_match('~^20\d\d[/.\-]~', $p)) $who = $p;
        $out[] = ['zh' => $title, 'en' => '', 'who' => $who, 'from' => $dates[0], 'to' => end($dates), 'vzh' => $venue, 'vru' => '',
                  'city' => city_of($venue . $chan, $city), 'url' => $link, 'src' => $src];
    }
    return $out;
}
// ----- разбор: JSON-LD (Songkick, Bachtrack и др.) -----
function p_jsonld($html, $src) {
    $out = [];
    if (!preg_match_all('~<script[^>]+application/ld\+json[^>]*>(.*?)</script>~is', $html, $m)) return [];
    $walk = function ($o) use (&$walk, &$out, $src) {
        if (!is_array($o)) return;
        $type = $o['@type'] ?? '';
        if (is_array($type)) $type = implode(' ', $type);
        if (stripos((string)$type, 'Event') !== false && !empty($o['startDate'])) {
            $loc = $o['location'] ?? []; if (isset($loc[0])) $loc = $loc[0];
            $addr = json_encode($loc['address'] ?? '', JSON_UNESCAPED_UNICODE) . ' ' . ($loc['name'] ?? '');
            $city = city_of($addr, '');
            if ($city === '' && preg_match('~Shenzhen|Shanghai|Beijing|Hong Kong|Macau|深圳|上海|北京|香港|澳门~iu', $addr)) return;
            $from = substr((string)$o['startDate'], 0, 10); $to = substr((string)($o['endDate'] ?? $from), 0, 10);
            if (!preg_match('~^20\d\d-\d\d-\d\d$~', $from)) return;
            if (!preg_match('~^20\d\d-\d\d-\d\d$~', $to) || $to < $from) $to = $from;
            $perf = [];
            foreach ((isset($o['performer'][0]) ? $o['performer'] : (isset($o['performer']) ? [$o['performer']] : [])) as $p) if (!empty($p['name'])) $perf[] = $p['name'];
            $out[] = ['zh' => '', 'en' => html_entity_decode(trim((string)($o['name'] ?? '')), ENT_QUOTES, 'UTF-8'), 'who' => implode(', ', $perf), 'from' => $from, 'to' => $to,
                      'vzh' => html_entity_decode((string)($loc['name'] ?? ''), ENT_QUOTES, 'UTF-8'), 'vru' => '', 'city' => $city ?: 'gz', 'url' => (string)($o['url'] ?? ''), 'src' => $src];
            return;
        }
        foreach ($o as $v) if (is_array($v)) $walk($v);
    };
    foreach ($m[1] as $j) { $d = json_decode(trim($j), true); if ($d) $walk($d); }
    return $out;
}

// ----- сравнение -----
function normt($s) {
    $s = mb_strtolower((string)$s);
    $s = preg_replace('~20\d\d年?|第[\d一二三四五六七八九十百零]+届|\d+(st|nd|rd|th)\b|guangzhou|foshan|china|international|exhibition|expo|fair|trade|show|广州|佛山|中国|国际|广东|华南|展览会|博览会|交易会|展会|展览|展|会|[\s\p{P}\p{S}]+~u', '', $s);
    return $s;
}
function sim($a, $b) {   // похожесть названий 0–100: общие пары соседних букв (работает и для иероглифов)
    $a = normt($a); $b = normt($b);
    if ($a === '' || $b === '') return 0;
    $la = mb_strlen($a); $lb = mb_strlen($b); $mn = min($la, $lb);
    if ($mn >= (preg_match('~\p{Han}~u', $a . $b) ? 3 : 5) && $mn / max($la, $lb) >= 0.34 && (mb_strpos($a, $b) !== false || mb_strpos($b, $a) !== false)) return 100;
    $g = function ($s) { $c = preg_split('~~u', $s, -1, PREG_SPLIT_NO_EMPTY); if (count($c) < 2) return $c; $r = []; for ($i = 0; $i < count($c) - 1; $i++) $r[] = $c[$i] . $c[$i + 1]; return $r; };
    $x = $g($a); $y = $g($b); if (!$x || !$y) return 0;
    $cnt = array_count_values($y); $hit = 0;
    foreach ($x as $t) if (!empty($cnt[$t])) { $hit++; $cnt[$t]--; }
    return 200 * $hit / (count($x) + count($y));
}
function ddiff($a, $b) { return abs((strtotime($a) - strtotime($b)) / 86400); }
function damai_id($u) { return preg_match('~damai\.cn/item\.htm\?id=(\d+)~', (string)$u, $m) ? $m[1] : ''; }
function find_match($EV, $e, $kind) {
    $vk = vkey($e['vzh'] ?? ''); $id = damai_id($e['url'] ?? '');
    foreach ($EV as $i => $x) {
        if (($x['k'] === 'expo') !== ($kind === 'expo')) continue;
        if ($id !== '' && damai_id($x['url'] ?? '') === $id) return $i + 1;
        $near = ddiff($x['from'], $e['from']) <= 3;
        $sameV = ($vk === '' || ($x['vk'] ?? '') === '' || $x['vk'] === $vk);
        $sz = ($e['zh'] ?? '') !== '' ? max(sim($x['zh'] ?? '', $e['zh']), sim($x['en'] ?? '', $e['zh'])) : 0;
        $se = ($e['en'] ?? '') !== '' ? max(sim($x['en'] ?? '', $e['en']), sim($x['zh'] ?? '', $e['en'])) : 0;
        if ($near && $sameV && ($sz >= 60 || $se >= 70)) return $i + 1;
        if (($sz >= 88 || $se >= 92) && ddiff($x['from'], $e['from']) <= 150) return $i + 1;
    }
    return 0;
}
function merge_in(&$EV, $e, $kind) {
    global $AI, $added;
    $e['vk'] = vkey($e['vzh'] ?? '');
    $c = $AI[aikey($e, $kind)] ?? null;
    $i = find_match($EV, $e, $kind);
    if ($i) {   // уже есть: дополняем, даты берём свежие
        $x = &$EV[$i - 1];
        foreach (['en', 'zh', 'url', 'vzh'] as $f) if (($x[$f] ?? '') === '' && ($e[$f] ?? '') !== '') $x[$f] = $e[$f];
        if ($kind === 'expo' && ($x['from'] !== $e['from'] || $x['to'] !== $e['to']) && $e['src'] !== 'tradefairdates.com') { $x['from'] = $e['from']; $x['to'] = $e['to']; $added['upd']++; }
        if (strpos($x['src'] ?? '', $e['src']) === false) $x['src'] = trim(($x['src'] ?? '') . ', ' . $e['src'], ', ');
        unset($x);
        return;
    }
    if ($kind === 'expo' && ($e['zh'] ?? '') === '') {   // только английское название: не дублируем, если в этот день на площадке уже столько же выставок
        $same = 0; foreach ($EV as $x) if (($x['vk'] ?? '') === $e['vk'] && $x['from'] === $e['from'] && $x['to'] === $e['to']) $same++;
        $mine = 0; foreach ($GLOBALS['auto'] as $y) if (($y['zh'] ?? '') === '' && vkey($y['vzh']) === $e['vk'] && $y['from'] === $e['from'] && $y['to'] === $e['to']) $mine++;
        if ($same >= $mine && $same > 0) return;
    }
    if ($kind === 'fun') {
        if (!$c || empty($c['intl'])) return;       // только зарубежные артисты и международные события
        $e['k'] = in_array($c['k'] ?? '', ['concert', 'show', 'sport', 'fest'], true) ? $c['k'] : 'show';
        $e['t'] = ''; $e['intl'] = true;
    } else {
        $e['k'] = 'expo';
        $e['t'] = $c['t'] ?? topic_guess(($e['zh'] ?? '') . ' ' . ($e['en'] ?? ''));
    }
    $e['ru'] = trim((string)($c['ru'] ?? '')) ?: ($e['en'] ?: $e['zh']);
    $e['what'] = trim((string)($c['what'] ?? '')) ?: ($e['who'] ?? '');
    if (($e['en'] ?? '') === '') $e['en'] = trim((string)($c['en'] ?? ''));
    if (($e['zh'] ?? '') === '') $e['zh'] = trim((string)($c['zh'] ?? ''));
    $e['what_en'] = trim((string)($c['what_en'] ?? '')); $e['what_zh'] = trim((string)($c['what_zh'] ?? ''));
    if (($e['vru'] ?? '') === '') $e['vru'] = trim((string)($c['vru'] ?? ''));
    unset($e['who']);
    $e['id'] = 'a' . substr(md5(($e['zh'] ?: $e['en']) . '|' . $e['from'] . '|' . $e['vzh']), 0, 9);
    $EV[] = $e;
    $added[$kind]++;
}
function topic_guess($s) {
    $R = ['en' => '新能源|氢能|储能|光伏|电池|energy|solar|battery', 'au' => '汽车|摩托|房车|汽配|auto|motor', 'li' => '照明|灯光|演艺|LED|light', 'pk' => '印刷|包装|纸业|pack|print|paper',
          'bt' => '美博|美发|美容|化妆|beauty|cosmet|hair', 'md' => '医疗|口腔|老龄|健康|medic|dental|health', 'ho' => '酒店|餐饮|hotel', 'fd' => '茶|食品|调味|粮油|饮料|酒|food|tea',
          'tx' => '鞋|皮革|服装|纺织|面料|缝制|shoe|leather|textile|apparel', 'fu' => '家具|家居设计|定制家居|furniture|interzum', 'el' => '电子|无人机|线缆|电气|家电|智能|electr|cable|smart',
          'bu' => '卫浴|陶瓷|门窗|建筑|地坪|建材|ceramic|build|door|window', 'ma' => '机床|泵阀|设备|机械|弹簧|加工|模具|machin|equipment|pump',
          'hm' => '家居用品|礼品|日用|五金|gift|home', 'sp' => '户外|自行车|宠物|乐园|体育|玩具|outdoor|bike|pet|toy|sport'];
    foreach ($R as $k => $rx) if (preg_match('~' . $rx . '~iu', $s)) return $k;
    return 'ot';
}

// ----- YandexGPT -----
function aikey($e, $kind) { return $kind[0] . '|' . md5(($e['zh'] ?? '') . '|' . ($e['en'] ?? '') . '|' . ($kind === 'fun' ? ($e['vzh'] ?? '') : '')); }
function ai_keys() {
    static $k = null; if ($k !== null) return $k;
    $cfg = dirname(__DIR__) . '/itcpartner.store/api/config.php';
    if (!defined('YC_API_KEY') && is_file($cfg)) { ob_start(); @include_once $cfg; ob_end_clean(); }
    $key = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
    $fold = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
    return $k = ($key && $fold) ? [$key, $fold] : [];
}
function ask_ai($sys, $user, $maxTok = 4000) {
    global $LOG;
    if (getenv('EVENTS_AI_MOCK')) { $LOG['ai']['calls']++; $f = getenv('EVENTS_AI_MOCK'); $r = include $f; $LOG['ai']['ok']++; return $r; }
    $kk = ai_keys(); if (!$kk) { $LOG['ai']['err'] = 'нет ключей YandexGPT в config.php магазина'; return null; }
    [$key, $folder] = $kk;
    $auth = (strpos($key, 't1.') === 0) ? 'Bearer ' . $key : 'Api-Key ' . $key;
    $body = json_encode(['modelUri' => 'gpt://' . $folder . '/yandexgpt/latest',
        'completionOptions' => ['stream' => false, 'temperature' => 0.1, 'maxTokens' => (string)$maxTok],
        'messages' => [['role' => 'system', 'text' => $sys], ['role' => 'user', 'text' => $user]]], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://llm.api.cloud.yandex.net/foundationModels/v1/completion');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 90,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth, 'x-folder-id: ' . $folder], CURLOPT_POSTFIELDS => $body]);
    $raw = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $LOG['ai']['calls']++;
    if ($raw === false || $http !== 200) { $LOG['ai']['err'] = "YandexGPT HTTP $http " . mb_substr((string)$raw, 0, 160); return null; }
    $j = json_decode($raw, true); $LOG['ai']['ok']++;
    return $j['result']['alternatives'][0]['message']['text'] ?? null;
}
function ai_arr($sys, $user, $n = null) {
    $t = ask_ai($sys, $user); if ($t === null) return null;
    $t = preg_replace('~^```(?:json)?|```$~m', '', trim($t));
    $a = strpos($t, '['); $b = strrpos($t, ']'); if ($a === false || $b === false) return null;
    $r = json_decode(substr($t, $a, $b - $a + 1), true);
    if (!is_array($r) || ($n !== null && count($r) !== $n)) return null;
    return $r;
}
function ai_enrich($list, $kind) {
    global $AI, $LOG;
    $todo = []; $seen = [];
    foreach ($list as $e) { $k = aikey($e, $kind); if (isset($AI[$k]) || isset($seen[$k])) continue; $seen[$k] = 1; $todo[$k] = $e; }
    if (!$todo) return;
    $TOP = 'el электроника и электротехника; en энергетика, солнечные панели, аккумуляторы; ma станки и промышленное оборудование; au авто, мото, автозапчасти; hm товары для дома, подарки, хозтовары, метизы; fu мебель и интерьер; bu стройматериалы, сантехника, керамика, двери, окна; li освещение, звук, сцена; pk упаковка, печать, бумага; bt красота, косметика, волосы; md медицина и здоровье; fd еда, напитки, чай, ингредиенты; ho отели и рестораны; tx одежда, обувь, ткани, кожа; sp спорт, отдых, животные, игрушки; ot другое';
    if ($kind === 'expo') {
        $sys = "Ты помогаешь русскому закупщику, который ездит на выставки в Гуанчжоу и Фошане. Тебе дают пронумерованный список выставок: китайское и/или английское название и площадку.\n"
             . "Для КАЖДОЙ строки верни объект с полями:\n"
             . "ru — короткое понятное русское название: что за выставка + бренд или аббревиатура латиницей, если есть (например «Выставка освещения GILE», «Выставка мебели CIFF»), без номера сессии и года;\n"
             . "en — то же по-английски; zh — то же по-китайски (краткая форма китайского названия, если оно дано); what_en — what по-английски; what_zh — what по-китайски;\n"
             . "t — один код темы: $TOP;\n"
             . "what — одна строка по-русски до 90 знаков: что там показывают;\n"
             . "vru — площадка по-русски коротко (например «Комплекс Кантонской ярмарки», «PWTC, Пачжоу», «Таньчжоу, Фошань»).\n"
             . "Не выдумывай фактов. Ответ — ТОЛЬКО JSON-массив из N объектов в том же порядке, без пояснений и без ```.";
    } else {
        $sys = "Ты помогаешь русскому предпринимателю в Гуанчжоу выбрать развлечения международного уровня. Тебе дают пронумерованный список событий из афиши: название (обычно китайское), исполнитель, площадка, дата.\n"
             . "Для КАЖДОЙ строки верни объект с полями:\n"
             . "intl — true, только если выступает зарубежный артист, группа, оркестр, театр, балет, шоу или мюзикл из-за рубежа (не из материкового Китая, Гонконга, Макао и Тайваня), или это международный турнир, марафон, фестиваль. "
             . "Китайские поп-звёзды, китайские оркестры и театры, кантонская опера, стендап, детские спектакли, трибьют-концерты китайских музыкантов — false. Если не уверен — false;\n"
             . "k — concert (концерт), show (спектакль, мюзикл, балет, цирк, шоу), sport (спорт), fest (фестиваль);\n"
             . "ru — название по-русски: имя артиста или коллектива русскими буквами + что это (например «Дайана Кролл — джазовый концерт», «Американский театр балета — „Лебединое озеро“»);\n"
             . "en — то же по-английски (имя артиста латиницей); what_en — what по-английски; what_zh — what по-китайски;\n"
             . "what — одна строка по-русски до 90 знаков: кто это и из какой страны;\n"
             . "vru — площадка по-русски коротко.\n"
             . "Ответ — ТОЛЬКО JSON-массив из N объектов в том же порядке, без пояснений и без ```.";
    }
    $keys = array_keys($todo); $batches = array_chunk($keys, 12); $calls = 0;
    foreach ($batches as $bk) {
        if (++$calls > 25) { $LOG['notes'][] = "Перевод: остальное — в следующий запуск"; break; }
        $lines = []; $n = 1;
        foreach ($bk as $k) { $e = $todo[$k];
            $lines[] = $n++ . '. ' . trim(($e['zh'] ?? '') . ' / ' . ($e['en'] ?? ''), ' /') . (($e['who'] ?? '') !== '' ? ' || исполнитель: ' . $e['who'] : '') . ' || площадка: ' . ($e['vzh'] ?? '') . ' || дата: ' . $e['from']; }
        $r = ai_arr(str_replace('N объектов', count($bk) . ' объектов', $sys), implode("\n", $lines), count($bk));
        if ($r === null) { if ($LOG['ai']['err'] === '') $LOG['ai']['err'] = 'ответ ИИ не разобран'; if (!ai_keys() && !getenv('EVENTS_AI_MOCK')) break; continue; }
        foreach ($bk as $i => $k) { $o = is_array($r[$i] ?? null) ? $r[$i] : []; $o['at'] = date('Y-m-d'); $AI[$k] = $o; }
    }
}
// если вёрстка сайта поменялась и разбор ничего не нашёл — просим ИИ вытащить список из текста страницы
function ai_extract($html, $url, $lang, $vk) {
    global $LOG;
    $t = mb_substr(h2t($html), 0, 9000);
    if (mb_strlen($t) < 500) return [];
    $sys = "Из текста страницы с расписанием выставок выпиши все выставки с датами. Для каждой верни объект: name — название точно как в тексте; from и to — даты в формате ГГГГ-ММ-ДД; venue — площадка как в тексте (если есть). Не выдумывай. Ответ — ТОЛЬКО JSON-массив.";
    $r = ai_arr($sys, $t);
    if (!$r) return [];
    $out = []; $vi = $vk ? vinfo($vk) : null;
    foreach ($r as $o) {
        $nm = trim((string)($o['name'] ?? '')); $f = (string)($o['from'] ?? ''); $to = (string)($o['to'] ?? $f);
        if ($nm === '' || !preg_match('~^20\d\d-\d\d-\d\d$~', $f) || mb_strpos($t, mb_substr($nm, 0, 6)) === false) continue;
        if (!preg_match('~^20\d\d-\d\d-\d\d$~', $to) || $to < $f) $to = $f;
        $v = $vi ? $vi[0] : trim((string)($o['venue'] ?? '')); $city = $vi ? $vi[2] : city_of($v);
        if ($city === '') continue;
        $vv = $vi ?: vinfo(vkey($v));
        $out[] = ['en' => $lang === 'en' ? $nm : '', 'zh' => $lang === 'zh' ? $nm : '', 'from' => $f, 'to' => $to, 'vzh' => $vv ? $vv[0] : $v, 'vru' => $vv ? $vv[1] : '', 'city' => $city, 'url' => $url, 'src' => parse_url($url, PHP_URL_HOST) . ' (ИИ)'];
    }
    $LOG['notes'][] = 'Разбор страницы через ИИ: ' . $url . ' — ' . count($out);
    return $out;
}

function report($LOGF, $FILE) {
    header('Content-Type: text/html; charset=utf-8');
    $L = is_file($LOGF) ? json_decode(file_get_contents($LOGF), true) : null;
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>События — отчёт</title>'
       . '<style>body{font:16px system-ui,sans-serif;margin:16px;color:#17202b}td,th{padding:6px 8px;border-bottom:1px solid #ddd;text-align:left;vertical-align:top}.ok{color:#128A04;font-weight:700}.no{color:#c62828;font-weight:700}</style>';
    echo '<h2>Мой Гуанчжоу · обновление «Событий»</h2>';
    if (!$L) { echo '<p>Ещё ни разу не запускалось.</p>'; return; }
    echo '<p>Последний запуск: <b>' . htmlspecialchars($L['finished'] ?? $L['started']) . '</b> · ' . ($L['sec'] ?? '?') . ' с · событий в базе: <b>' . (int)($L['total'] ?? 0) . '</b>';
    if (!empty($L['added'])) echo ' · добавлено выставок: ' . (int)$L['added']['expo'] . ', развлечений: ' . (int)$L['added']['fun'] . ', уточнено дат: ' . (int)$L['added']['upd'];
    echo '</p><table><tr><th>Источник</th><th>Ответил</th><th>Найдено</th><th>Ошибка</th></tr>';
    foreach ($L['sources'] as $s) echo '<tr><td>' . htmlspecialchars($s['name']) . '</td><td class="' . ($s['ok'] ? 'ok">да' : 'no">нет') . '</td><td>' . (int)$s['n'] . '</td><td>' . htmlspecialchars($s['err'] ?? '') . '</td></tr>';
    echo '</table><p>Перевод (YandexGPT): запросов ' . (int)$L['ai']['calls'] . ', успешно ' . (int)$L['ai']['ok'] . ($L['ai']['err'] ? ' · <span class="no">' . htmlspecialchars($L['ai']['err']) . '</span>' : '') . '</p>';
    if (!empty($L['notes'])) echo '<ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $L['notes'])) . '</li></ul>';
}
