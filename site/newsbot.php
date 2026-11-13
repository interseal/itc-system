<?php
// ============================================================
//  Обновлялка новостей о выставках в Китае
//  Запуск раз в неделю по расписанию хостинга.
//
//  Вручную в браузере (ключ — newsbot_key в config.php):
//    /newsbot.php?key=КЛЮЧ&test=1  — только посмотреть, что нашлось (ничего не пишет)
//    /newsbot.php?key=КЛЮЧ&dry=1   — написать одну новость и показать, не сохраняя
//    /newsbot.php?key=КЛЮЧ         — обычный запуск: пишет и публикует новости
//
//  Источник: календарь выставок Китая 聚展 jufair.com (по месяцам).
//  Тексты пишет YandexGPT (ключ берётся из настроек магазина или из config.php).
//  №340: картинки рисует Alibaba Wanxiang (ключ из магазина, api/config_art.php); нет ключа — Яндекс.
// ============================================================

define('ROOT', __DIR__);
$cfg = require ROOT . '/config.php';
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
require_once ROOT . '/inc/news.php';

$cli = (PHP_SAPI === 'cli');
$arg = $cli ? getopt('', ['test', 'dry', 'images', 'count:']) : $_GET;
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key = (string)($cfg['newsbot_key'] ?? '');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
        http_response_code(403); exit("Нет доступа\n");
    }
}
$TEST = isset($arg['test']);
$DRY  = isset($arg['dry']);
$IMGS = isset($arg['images']);

@set_time_limit(600);
@ignore_user_abort(true);
while (ob_get_level()) ob_end_flush();
ob_implicit_flush(true);

function say($s) { echo $s, "\n"; @flush(); }

$set = [
    // сколько новостей за запуск; разово можно больше: &count=10 (не больше 15 за раз)
    'per_run'   => isset($arg['count']) ? max(1, min(15, (int)$arg['count'])) : (int)($cfg['newsbot_per_run'] ?? 10),
    'min_area'  => (int)($cfg['newsbot_min_area']  ?? 50000),   // мин. площадь выставки, м²
    'min_firms' => (int)($cfg['newsbot_min_firms'] ?? 1000),    // или мин. число участников
    'from_days' => (int)($cfg['newsbot_from_days'] ?? 14),      // выставка начинается не раньше, чем через N дней
    'to_days'   => (int)($cfg['newsbot_to_days']   ?? 90),      // и не позже, чем через N дней
];

// ---------- Защита от двойного запуска ----------
$lock = fopen(ROOT . '/data/newsbot.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Уже идёт другой запуск\n");

say('Обновлялка новостей · ' . date('d.m.Y H:i') . ($TEST ? ' · ПРОВЕРКА (ничего не сохраняю)' : ($DRY ? ' · ПРОБА ОДНОЙ НОВОСТИ' : '')));

// ---------- 0. Только дорисовать картинки к уже опубликованным ----------
if ($IMGS) {
    $yc = nb_yc_keys();
    if (!$yc) exit("Нет ключа YandexGPT\n");
    $store = news_auto_load(); $n = 0;
    foreach ($store['items'] as $slug => $it) {
        if (!empty($it['photo']) && is_file(ROOT . $it['photo'])) continue;
        $pr = $it['picture'] ?? ('выставочный павильон крупной выставки в Китае, тематика: ' . ($it['topic'] ?? ''));
        $ph = nb_art($yc, $pr, $slug, $it);
        say(($ph ? '  + картинка: ' : '  ! не получилось: ') . $it['title']);
        if (!$ph) { say('    причина: ' . $GLOBALS['nb_art_err']); break; }   // одна и та же причина — дальше не мучаем
        if ($ph) { $store['items'][$slug]['photo'] = $ph; $n++; }
    }
    nb_save($store);
    say('Готово. Нарисовано картинок: ' . $n);
    exit;
}

// ---------- 1. Собираем выставки с календаря ----------
$now   = time();
$tFrom = strtotime('today') + $set['from_days'] * 86400;
$tTo   = strtotime('today') + $set['to_days'] * 86400;
$months = [];
for ($t = $tFrom; $t <= $tTo + 86400 * 31; $t = strtotime('+1 month', strtotime(date('Y-m-01', $t)))) {
    if (strtotime(date('Y-m-01', $t)) > $tTo) break;
    $months[(int)date('n', $t)] = true;
}

$found = [];
foreach (array_keys($months) as $m) {
    $prevSig = '';
    for ($p = 1; $p <= 15; $p++) {
        $url = 'https://www.jufair.com/n-cn/m-' . $m . '/' . ($p > 1 ? 'p-' . $p . '/' : '');
        $html = nb_get($url);
        if ($html === null) { if ($p === 1) say("  ! не открылась страница $url"); break; }  // дальше страниц нет — это нормально
        $cards = jf_parse($html);
        $sig = md5(implode('|', array_keys($cards)));
        if (!$cards || $sig === $prevSig) break;
        $prevSig = $sig;
        $found += $cards;
        usleep(700000);
    }
    say("Месяц $m: всего найдено выставок " . count($found));
}
if (!$found) { say('Ни одной выставки не найдено — календарь не открылся или поменял вид.'); nb_log('нет данных'); exit; }

// ---------- 2. Отбор: крупные, в нужные даты, ещё не опубликованные ----------
$store = news_auto_load();
$cand = [];
foreach ($found as $id => $x) {
    if ($x['from'] < $tFrom || $x['from'] > $tTo) continue;
    if (preg_match('/台湾|台北|高雄|台中/u', $x['venue'] . $x['name'])) continue;
    $big = ($x['area'] >= $set['min_area']) || ($x['firms'] >= $set['min_firms']);
    if (!$big) continue;
    $key = $id . '-' . date('Y', $x['from']);
    if (isset($store['seen'][$key])) continue;
    $x['key'] = $key;
    $cand[] = $x;
}
usort($cand, function ($a, $b) { return [$b['area'], $b['firms']] <=> [$a['area'], $a['firms']]; });
// Одна и та же выставка часто стоит в календаре под двумя-тремя названиями
// (тот же павильон и те же даты) — оставляем одну, самую крупную
$uniq = [];
foreach ($cand as $x) {
    $k = $x['venue'] . '|' . date('Ymd', $x['from']);
    if (!isset($uniq[$k])) $uniq[$k] = $x;
}
$cand = array_values($uniq);
say('Подходят по датам и размеру, ещё не опубликованы: ' . count($cand));

if ($TEST) {
    foreach (array_slice($cand, 0, 30) as $x) {
        say(sprintf('  %s  %s · %s · площадь %s м² · участников %s',
            date('d.m', $x['from']) . '–' . date('d.m', $x['to']), $x['name'],
            $x['venue'] ?: '?', $x['area'] ?: '?', $x['firms'] ?: '?'));
    }
    $yc = nb_yc_keys();
    say('');
    say('Ключ YandexGPT: ' . ($yc ? 'найден (' . $yc['from'] . ')' : 'НЕ НАЙДЕН'));
    if ($yc) say('Проверка YandexGPT: ' . (nb_gpt($yc, 'Ответь одним словом: ОК', 'Проверка связи', 20) ?? 'НЕ ОТВЕЧАЕТ'));
    exit;
}

// ---------- 3. Пишем новости ----------
$yc = nb_yc_keys();
if (!$yc) { say('Нет ключа YandexGPT — новости не написать.'); nb_log('нет ключа'); exit; }

// Английская и китайская версии сайта: переводим новости, у которых ещё нет перевода
// (не больше 5 за запуск, чтобы запуск не затягивался)
if (!$DRY) {
    $tn = 0;
    foreach ($store['items'] as $slug => &$it) {
        if ($tn >= 5) break;
        if (!empty($it['hidden']) || ($it['to'] ?? 0) < strtotime('today')) continue;
        if (news_has_lang($slug, $it, 'en') && news_has_lang($slug, $it, 'zh')) continue;
        $tr = nb_translate($yc, $it);
        if ($tr) { $it['i18n'] = $tr; $tn++; say('  перевод на английский и китайский: ' . $it['title']); }
    }
    unset($it);
    if ($tn) { nb_save($store); nb_log('переведено ' . $tn); }
}

// Сколько предстоящих выставок уже на сайте — добираем до цели
$target = (int)($cfg['newsbot_target'] ?? 40);
$have = 0;
foreach ($store['items'] as $it) if (($it['to'] ?? 0) >= strtotime('today')) $have++;
if (!$DRY && !isset($arg['count'])) {
    $set['per_run'] = max(0, min($set['per_run'], $target - $have));
    say("На сайте предстоящих выставок: $have из $target. Добавлю: " . $set['per_run']);
    if ($set['per_run'] === 0) { say('Новостей достаточно, ничего не добавляю.'); nb_log('достаточно'); exit; }
}

$made = 0; $tries = 0;
foreach ($cand as $x) {
    if ($made >= ($DRY ? 1 : $set['per_run']) || $tries >= $set['per_run'] * 3) break;
    $tries++;
    $r = nb_write($yc, $x);
    if ($r === null) { say('  ! нейросеть не ответила: ' . $x['name']); continue; }
    if (empty($r['relevant'])) {
        say('  — пропуск (не для бизнеса): ' . $x['name']);
        if (!$DRY) $store['seen'][$x['key']] = 'skip';
        continue;
    }
    $item = nb_item($x, $r);
    if ($DRY) {
        say('');
        say('ЗАГОЛОВОК: ' . $item['title']);
        say('ГОРОД: ' . $item['city'] . ' · ТЕМА: ' . $item['topic']);
        say('КАРТИНКА: ' . $item['picture']);
        say('');
        say(trim(html_entity_decode(strip_tags(str_replace(['</p>', '</li>'], "\n", $item['html'])))));
        $made++;
        break;
    }
    // Перевод на английский и китайский; не получилось — переведём при следующем запуске
    $tr = nb_translate($yc, $item);
    if ($tr) $item['i18n'] = $tr; else say('    (перевод не удался — попробую при следующем запуске)');
    $ph = nb_art($yc, $item['picture'], $item['slug'], $item);
    if ($ph && nb_ds()) $item['photo_ai'] = 'wanx';
    if ($ph) $item['photo'] = $ph; else say('    (картинку нарисовать не удалось — будет плашка: ' . $GLOBALS['nb_art_err'] . ')');
    $store['items'][$item['slug']] = $item;
    $store['seen'][$x['key']] = $item['slug'];
    $made++;
    say('  + ' . $item['title']);
}

if (!$DRY) {
    $store['runs'][] = date('d.m.Y H:i') . ' · опубликовано ' . $made;
    $store['runs'] = array_slice($store['runs'], -30);
    nb_save($store);
    nb_log('опубликовано ' . $made);
}
say('Готово. Новых новостей: ' . $made);


// ============================================================
//  Функции
// ============================================================

function nb_save($store) {
    $tmp = news_auto_file() . '.tmp';
    file_put_contents($tmp, json_encode($store, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    rename($tmp, news_auto_file());
}

function nb_log($s) { @file_put_contents(ROOT . '/data/newsbot.log', date('d.m.Y H:i') . ' ' . $s . "\n", FILE_APPEND); }

function nb_get($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept-Language: zh-CN,zh;q=0.9,ru;q=0.6'],
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($r !== false && $code === 200) ? $r : null;
}

// Разбор страницы календаря: карточки выставок
function jf_parse($html) {
    $out = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xp = new DOMXPath($dom);
    foreach ($xp->query('//h2[.//a[contains(@href,"/exhibition/")]]') as $h2) {
        $a = $xp->query('.//a[contains(@href,"/exhibition/")]', $h2)->item(0);
        if (!preg_match('~/exhibition/([A-Za-z0-9_.-]+?)(?:\.html)?/?$~', $a->getAttribute('href'), $m)) continue;
        $id = 'jf-' . strtolower(preg_replace('/[^A-Za-z0-9-]/', '-', $m[1]));
        // Поднимаемся до карточки: блок, где ровно одна строка «开展时间»
        $card = $h2;
        for ($i = 0; $i < 6 && $card->parentNode; $i++) {
            $card = $card->parentNode;
            $n = substr_count($card->textContent, '开展时间');
            if ($n >= 1) break;
        }
        $txt = preg_replace('/\s+/u', ' ', $card->textContent);
        if (substr_count($txt, '开展时间') !== 1) continue;
        if (mb_strpos($txt, '时间待定') !== false) continue;
        if (!preg_match('/开展时间\s*(\d{4})\.(\d{1,2})\.(\d{1,2})\s*-\s*(?:(\d{4})\.)?(\d{1,2})\.(\d{1,2})/u', $txt, $d)) continue;
        $y2 = $d[4] !== '' ? (int)$d[4] : (int)$d[1];
        $from = mktime(12, 0, 0, (int)$d[2], (int)$d[3], (int)$d[1]);
        $to   = mktime(12, 0, 0, (int)$d[5], (int)$d[6], $y2);
        if ($to < $from) $to = mktime(12, 0, 0, (int)$d[5], (int)$d[6], $y2 + 1);

        $full = trim($a->getAttribute('title')) ?: trim($a->textContent);
        $img = $xp->query('.//a[contains(@href,"/exhibition/")][@title]', $card)->item(0);
        if ($img && mb_strlen($img->getAttribute('title')) > mb_strlen($full)) $full = trim($img->getAttribute('title'));
        $short = trim($h2->textContent);

        $en = '';
        $p1 = mb_strpos($txt, $short);
        $p2 = mb_strpos($txt, '开展时间');
        if ($p1 !== false && $p2 !== false && $p2 > $p1) {
            $en = trim(str_replace(['热门', '精选'], '', mb_substr($txt, $p1 + mb_strlen($short), $p2 - $p1 - mb_strlen($short))));
            if (!preg_match('/[A-Za-z]/', $en) || mb_strlen($en) > 80) $en = '';
        }
        $area = 0;
        if (preg_match('/展览面积[：:]\s*([\d.]+)\s*(万平方米|平方米|㎡|m²)/u', $txt, $q))
            $area = (int)round((float)$q[1] * ($q[2] === '万平方米' ? 10000 : 1));
        $firms = 0;
        if (preg_match('/展商数量[：:]\s*([\d.]+)\s*(万)?\s*家/u', $txt, $q))
            $firms = (int)round((float)$q[1] * (!empty($q[2]) ? 10000 : 1));
        $visit = 0;
        if (preg_match('/观众人数[：:]\s*([\d.]+)\s*(万)?/u', $txt, $q))
            $visit = (int)round((float)$q[1] * (!empty($q[2]) ? 10000 : 1));
        $venue = '';
        if (preg_match('/举办展馆\s*(.+?)\s*(?:[\d.]+w?\s*人浏览|距离开展|门票|开票|订阅|$)/u', $txt, $q)) $venue = trim($q[1]);

        $out[$id] = ['id' => $id, 'name' => $full, 'short' => $short, 'en' => $en, 'from' => $from, 'to' => $to,
                     'area' => $area, 'firms' => $firms, 'visit' => $visit, 'venue' => $venue, 'src' => $a->getAttribute('href')];
    }
    return $out;
}

// Ключ YandexGPT: из своего config.php, иначе из настроек магазина (читаем как текст)
function nb_yc_keys() {
    global $cfg;
    if (!empty($cfg['yc_api_key']) && !empty($cfg['yc_folder_id']))
        return ['key' => $cfg['yc_api_key'], 'folder' => $cfg['yc_folder_id'], 'from' => 'config.php сайта'];
    foreach ([dirname(ROOT) . '/itcpartner.store/api/config.php', dirname(ROOT) . '/www.itcpartner.store/api/config.php'] as $f) {
        $s = @file_get_contents($f);
        if (!$s) continue;
        if (preg_match("/define\(\s*'YC_API_KEY'\s*,\s*'([^']+)'/", $s, $k) && preg_match("/define\(\s*'YC_FOLDER_ID'\s*,\s*'([^']+)'/", $s, $fo))
            return ['key' => $k[1], 'folder' => $fo[1], 'from' => 'настройки магазина'];
    }
    return null;
}

function nb_gpt($yc, $system, $user, $maxTokens = 1500, $model = 'yandexgpt/latest') {
    $ch = curl_init('https://llm.api.cloud.yandex.net/foundationModels/v1/completion');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Api-Key ' . $yc['key']],
        CURLOPT_POSTFIELDS => json_encode([
            'modelUri' => 'gpt://' . $yc['folder'] . '/' . $model,
            'completionOptions' => ['stream' => false, 'temperature' => 0.4, 'maxTokens' => (string)$maxTokens],
            'messages' => [['role' => 'system', 'text' => $system], ['role' => 'user', 'text' => $user]],
        ], JSON_UNESCAPED_UNICODE),
    ]);
    $r = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $j = json_decode((string)$r, true);
    $t = $j['result']['alternatives'][0]['message']['text'] ?? null;
    if ($code !== 200 || $t === null) {
        nb_log('gpt ' . $code . ' ' . mb_substr((string)$r, 0, 200));
        // Pro-модель недоступна — пробуем Lite
        if ($model !== 'yandexgpt-lite/latest') return nb_gpt($yc, $system, $user, $maxTokens, 'yandexgpt-lite/latest');
        return null;
    }
    return trim($t);
}

// Просим нейросеть описать выставку. Факты (даты, площадь) не выдумывает — их ставим сами.
function nb_write($yc, $x) {
    $sys = "Ты редактор сайта российской компании ИТК (Интернациональная Торговая Компания): прямые поставки товаров "
         . "из Китая в Россию под ключ — поиск фабрик, переговоры, контроль качества, логистика, таможня. "
         . "Пишешь новости о крупных выставках в Китае. ЧИТАТЕЛИ — российские покупатели: импортёры, оптовики, "
         . "селлеры маркетплейсов, владельцы магазинов, строительные и производственные компании, которые закупают в Китае. "
         . "Пиши по-русски, живо, просто и по делу, без восторгов и канцелярита.\n"
         . "ПРАВИЛА: не выдумывай конкретные факты — цифры, бренды, имена, номера павильонов, события. "
         . "Можно и нужно рассказать, какие группы товаров обычно показывают на выставках такой тематики. "
         . "Если у выставки есть известное международное название (Canton Fair, Guzhen Lighting Fair, Hi-Tech Fair, CMEF и т.п.), "
         . "укажи его в названии в скобках. Иероглиф 灯 в названии означает освещение и светильники.\n"
         . "Ответь ТОЛЬКО JSON без пояснений и без ```:\n"
         . '{"relevant": true или false — false, если выставка для широкой публики, а не для бизнеса (аниме, свадьбы, религия, развлечения, автосалон для покупателей машин, авиашоу), '
         . '"name": "принятое название выставки по-русски, до 80 символов, без года и без города", '
         . '"venue": "павильон по-русски", '
         . '"topic": "тематика в 2–4 словах, с маленькой буквы", '
         . '"about": "2–3 предложения: что это за выставка и чем она известна в своей отрасли", '
         . '"goods": ["5–7 групп товаров, которые обычно там показывают"], '
         . '"who": ["кому из российских покупателей она полезна — 3–5 коротких пунктов"], '
         . '"why": "1–2 предложения: чем выставка полезна для закупок — сравнить фабрики, увидеть новинки, договориться о цене", '
         . '"picture": "описание фотографии для обложки, 1–2 предложения: интерьер большого выставочного павильона и стенды с товарами этой тематики; без надписей и логотипов"}';
    $city = nb_city($x);
    $facts = "Название (кит.): {$x['name']}\n"
           . ($x['en'] ? "Название (англ.): {$x['en']}\n" : '')
           . "Короткое название: {$x['short']}\n"
           . ($city ? "Город: $city\n" : '')
           . 'Даты: ' . date('d.m.Y', $x['from']) . ' – ' . date('d.m.Y', $x['to']) . "\n"
           . "Павильон: {$x['venue']}\n";
    $t = nb_gpt($yc, $sys, $facts);
    if ($t === null) return null;
    $t = preg_replace('/^```(?:json)?|```$/m', '', $t);
    if (preg_match('/\{.*\}/su', $t, $m)) $t = $m[0];
    $j = json_decode($t, true);
    if (!is_array($j) || empty($j['name'])) { nb_log('gpt bad json: ' . mb_substr($t, 0, 200)); return null; }
    return $j;
}

// Перевод новости на английский и упрощённый китайский (YandexGPT). Даты ставим сами, без нейросети.
function nb_translate($yc, $item) {
    $src = ['title' => $item['title'], 'city' => $item['city'] ?? '', 'topic' => $item['topic'] ?? '', 'html' => $item['html']];
    $out = [];
    foreach (['en' => 'английский', 'zh' => 'упрощённый китайский'] as $l => $name) {
        $sys = "Ты профессиональный переводчик сайта российской импортной компании ИТК (по-английски и по-китайски — ITC). "
             . "Переведи значения JSON с русского на $name язык. Ключи JSON не меняй. "
             . "HTML-теги и атрибуты сохрани точно как есть, переводи только текст между ними. "
             . "Названия выставок, городов и выставочных центров пиши так, как они официально приняты на этом языке. "
             . 'Даты пиши так: ' . ($l === 'en' ? 'October 12–16, 2026' : '2026年10月12–16日') . '. '
             . 'Ответь ТОЛЬКО JSON без пояснений и без ```.';
        $t = nb_gpt($yc, $sys, json_encode($src, JSON_UNESCAPED_UNICODE), 4000);
        if ($t === null) return null;
        $t = preg_replace('/^```(?:json)?|```$/m', '', $t);
        if (preg_match('/\{.*\}/su', $t, $m)) $t = $m[0];
        $j = json_decode($t, true);
        if (!is_array($j) || empty($j['title']) || empty($j['html'])) { nb_log("перевод $l: плохой ответ " . mb_substr($t, 0, 200)); return null; }
        // Проверка: русских букв не осталось, пункты списков на месте
        $all = $j['title'] . ($j['city'] ?? '') . ($j['topic'] ?? '') . $j['html'];
        if (preg_match('/\p{Cyrillic}/u', $all) || substr_count($j['html'], '<li>') !== substr_count($item['html'], '<li>')) {
            nb_log("перевод $l не прошёл проверку: " . mb_substr($j['title'], 0, 100)); return null;
        }
        $dates = $l === 'en' ? news_dates_en($item['from'], $item['to']) : news_dates_zh($item['from'], $item['to']);
        $out[$l] = ['title' => trim($j['title']), 'city' => trim((string)($j['city'] ?? '')), 'topic' => trim((string)($j['topic'] ?? '')),
                    'dates' => $dates, 'html' => $j['html']];
    }
    return $out;
}

// Город по названию павильона/выставки (надёжнее, чем угадывание нейросетью)
function nb_city($x) {
    $s = $x['venue'] . ' ' . $x['name'] . ' ' . $x['short'];
    $special = ['广交会' => 'Гуанчжоу', '琶洲' => 'Гуанчжоу', '保利世贸' => 'Гуанчжоу', '中国国际展览中心' => 'Пекин',
                '国家会议中心' => 'Пекин', '首都国际会展' => 'Пекин', '虹桥' => 'Шанхай', '宝安' => 'Шэньчжэнь', '福田会展' => 'Шэньчжэнь',
                '古镇' => 'Чжуншань (Гучжэнь)', '乐从' => 'Фошань', '顺德' => 'Фошань', '潭洲' => 'Фошань', '厚街' => 'Дунгуань', '长安' => 'Дунгуань'];
    foreach ($special as $k => $v) if (mb_strpos($s, $k) !== false) return $v;
    $map = ['广州' => 'Гуанчжоу', '深圳' => 'Шэньчжэнь', '上海' => 'Шанхай', '北京' => 'Пекин', '香港' => 'Гонконг', '澳门' => 'Макао',
            '中山' => 'Чжуншань', '佛山' => 'Фошань', '东莞' => 'Дунгуань', '珠海' => 'Чжухай', '厦门' => 'Сямэнь', '福州' => 'Фучжоу',
            '义乌' => 'Иу', '杭州' => 'Ханчжоу', '宁波' => 'Нинбо', '温州' => 'Вэньчжоу', '台州' => 'Тайчжоу', '金华' => 'Цзиньхуа', '永康' => 'Юнкан',
            '南京' => 'Нанкин', '苏州' => 'Сучжоу', '无锡' => 'Уси', '常州' => 'Чанчжоу', '天津' => 'Тяньцзинь', '青岛' => 'Циндао',
            '济南' => 'Цзинань', '临沂' => 'Линьи', '潍坊' => 'Вэйфан', '郑州' => 'Чжэнчжоу', '武汉' => 'Ухань', '长沙' => 'Чанша',
            '成都' => 'Чэнду', '重庆' => 'Чунцин', '西安' => 'Сиань', '昆明' => 'Куньмин', '南宁' => 'Наньнин', '沈阳' => 'Шэньян',
            '大连' => 'Далянь', '哈尔滨' => 'Харбин', '长春' => 'Чанчунь', '合肥' => 'Хэфэй', '南昌' => 'Наньчан', '石家庄' => 'Шицзячжуан',
            '乌鲁木齐' => 'Урумчи', '新疆' => 'Урумчи', '海口' => 'Хайкоу', '三亚' => 'Санья', '贵阳' => 'Гуйян', '太原' => 'Тайюань',
            '呼和浩特' => 'Хух-Хото', '兰州' => 'Ланьчжоу', '汕头' => 'Шаньтоу', '泉州' => 'Цюаньчжоу', '晋江' => 'Цзиньцзян'];
    foreach ($map as $k => $v) if (mb_strpos($s, $k) !== false) return $v;
    return '';
}

// Картинка к новости — рисует Alice AI ART (Яндекс, ImageAPI). Возвращает адрес файла или null.
// Причину неудачи кладёт в $GLOBALS['nb_art_err'], чтобы показать её на экране.
// №340 · рисовалка Alibaba Wanxiang (ключ кладёт магазин в api/config_art.php). Нет ключа — рисует Яндекс, как раньше.
function nb_ds() {
    foreach ([dirname(ROOT) . '/itcpartner.store/api/config_art.php', dirname(ROOT) . '/www.itcpartner.store/api/config_art.php'] as $f)
        if (is_file($f)) { $c = @include $f; if (is_array($c) && !empty($c['key'])) return $c; }
    return null;
}
function nb_wanx($ds, $it) {
    $cn = (string)($it['name_cn'] ?? ''); $topic = (string)($it['topic'] ?? '');
    $views = ['宽阔的大型展馆全景，高处俯视，人流如织', '一个精致展台的近景，产品整齐陈列，柔和灯光', '展馆通道视角，两侧是各具特色的展台', '采购商在展台前洽谈、查看样品的场景（人物为背影或远景）', '产品特写陈列墙，层次丰富，色彩鲜明', '现代化展馆入口和巨大的展厅，玻璃穹顶，阳光照入'];
    $v = $views[hexdec(substr(md5((string)($it['slug'] ?? $cn)), 0, 2)) % count($views)];
    $prompt = '真实摄影照片：中国' . ($cn ? '「' . $cn . '」' : '大型') . '展览会现场，主题：' . $topic . '。' . $v . '。展台上清晰展示该展会主题的典型商品。专业商业摄影，自然光，高清细节，色彩真实。';
    $base = rtrim((string)($ds['base'] ?: 'https://dashscope.aliyuncs.com/api/v1'), '/');
    $ch = curl_init($base . '/services/aigc/text2image/image-synthesis');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $ds['key'], 'X-DashScope-Async: enable'],
        CURLOPT_POSTFIELDS => json_encode(['model' => 'wanx2.1-t2i-plus', 'input' => ['prompt' => $prompt, 'negative_prompt' => 'text, letters, words, chinese characters, watermark, logo, close-up faces, blurry, cartoon'], 'parameters' => ['size' => '1280*800', 'n' => 1]], JSON_UNESCAPED_UNICODE)]);
    $j = json_decode((string)curl_exec($ch), true); curl_close($ch);
    $task = (string)($j['output']['task_id'] ?? '');
    if ($task === '') { $GLOBALS['nb_art_err'] = 'Alibaba: ' . mb_substr((string)($j['message'] ?? 'нет ответа'), 0, 200); return null; }
    for ($i = 0; $i < 35; $i++) {
        sleep(3);
        $ch = curl_init($base . '/tasks/' . rawurlencode($task));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $ds['key']]]);
        $t = json_decode((string)curl_exec($ch), true); curl_close($ch);
        $st = (string)($t['output']['task_status'] ?? '');
        if ($st === 'SUCCEEDED') { $u = (string)($t['output']['results'][0]['url'] ?? ''); $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40, CURLOPT_FOLLOWLOCATION => true]); $img = curl_exec($ch); curl_close($ch); return $img ?: null; }
        if (in_array($st, ['FAILED', 'CANCELED', 'UNKNOWN'], true)) { $GLOBALS['nb_art_err'] = 'Alibaba: не нарисовалось'; return null; }
    }
    $GLOBALS['nb_art_err'] = 'Alibaba: долго думает'; return null;
}
function nb_art($yc, $prompt, $slug, $item = null) {
    $GLOBALS['nb_art_err'] = '';
    $ds = nb_ds();
    if ($ds && is_array($item)) {
        $img = nb_wanx($ds, $item + ['slug' => $slug]);
        if ($img) {
            $dir = ROOT . '/img/news'; if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $file = '/img/news/' . preg_replace('/[^a-z0-9-]/', '', $slug) . '-w.jpg';
            if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($img))) {
                $w = imagesx($im); $h = imagesy($im); $nw = min(900, $w); $nh = (int)round($nw * $h / $w);
                $sm = imagecreatetruecolor($nw, $nh); imagecopyresampled($sm, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                $ok = imagejpeg($sm, ROOT . $file, 84); imagedestroy($im); imagedestroy($sm);
            } else $ok = file_put_contents(ROOT . $file, $img);
            if ($ok) return $file;
        }
        if (!$yc) return null;   // Яндекса нет — значит без картинки
    }
    if (!$yc) return null;
    $prompt = trim($prompt) . '. Фотореалистичная фотография, дневной свет, без надписей, без логотипов, без крупных лиц людей.';
    $body = json_encode([
        'model'  => 'art://' . $yc['folder'] . '/aliceai-image-art-3.0/latest',
        'prompt' => mb_substr($prompt, 0, 490),
        'size'   => '1024x1024',
    ], JSON_UNESCAPED_UNICODE);
    $r = null; $code = 0; $raw = '';
    foreach (['Bearer ', 'Api-Key '] as $auth) {           // пробуем оба способа входа
        $ch = curl_init('https://ai.api.cloud.yandex.net/v1/images/generations');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: ' . $auth . $yc['key'],
                                   'OpenAI-Project: ' . $yc['folder'], 'x-folder-id: ' . $yc['folder']],
            CURLOPT_POSTFIELDS => $body]);
        $raw = (string)curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $r = json_decode($raw, true);
        if ($code !== 401) break;
    }
    $b64 = $r['data'][0]['b64_json'] ?? null;
    $img = $b64 ? base64_decode($b64) : null;
    if (!$img && !empty($r['data'][0]['url'])) $img = @file_get_contents($r['data'][0]['url']);
    if (!$img) {
        $GLOBALS['nb_art_err'] = 'ответ ' . $code . ': ' . mb_substr($raw, 0, 300);
        nb_log('art ' . $GLOBALS['nb_art_err']);
        return null;
    }
    $dir = ROOT . '/img/news';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $file = '/img/news/' . preg_replace('/[^a-z0-9-]/', '', $slug) . '.jpg';
    // Обрезаем квадрат до 16:10 и уменьшаем до 900 px, чтобы страница грузилась быстро
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($img))) {
        $w = imagesx($im); $h = imagesy($im);
        $ch = (int)round($w * 10 / 16); if ($ch > $h) $ch = $h;
        $nw = min(900, $w); $nh = (int)round($nw * $ch / $w);
        $sm = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($sm, $im, 0, 0, 0, (int)(($h - $ch) / 2), $nw, $nh, $w, $ch);
        $ok = imagejpeg($sm, ROOT . $file, 82);
        imagedestroy($im); imagedestroy($sm);
    } else {
        $ok = file_put_contents(ROOT . $file, $img);
    }
    if (!$ok) $GLOBALS['nb_art_err'] = 'не удалось записать файл в img/news';
    return $ok ? $file : null;
}

function nb_num($n) { return number_format($n, 0, ',', ' '); }

function nb_item($x, $r) {
    $dates = news_dates_ru($x['from'], $x['to']);
    $city  = nb_city($x) ?: 'Китай';
    $name  = rtrim(trim($r['name']), '.');
    // Этап (一期/二期/三期) — иначе этапы Кантонской ярмарки выглядят одинаково
    if (preg_match('/([一二三])期/u', $x['short'] . $x['name'], $q) && mb_stripos($name, 'этап') === false)
        $name .= ', ' . ['一' => 1, '二' => 2, '三' => 3][$q[1]] . '-й этап';
    $title = $name . ' — ' . $dates . ', ' . $city;
    $h  = '<p>' . htmlspecialchars(trim($r['about'] ?? '')) . '</p>';
    $h .= '<p><strong>Когда и где:</strong> ' . $dates . ', ' . htmlspecialchars($city)
        . (!empty($r['venue']) ? ', ' . htmlspecialchars(trim($r['venue'])) : '') . '.</p>';
    $scale = [];
    if ($x['area'])  $scale[] = 'площадь ' . nb_num($x['area']) . ' м²';
    if ($x['firms']) $scale[] = 'около ' . nb_num($x['firms']) . ' участников';
    if ($scale) $h .= '<p><strong>Масштаб:</strong> ' . implode(', ', $scale) . ' (по данным календаря выставок).</p>';
    $goods = array_filter(array_map('trim', (array)($r['goods'] ?? [])));
    if ($goods) $h .= '<p><strong>Что обычно показывают:</strong></p><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $goods)) . '</li></ul>';
    $who = array_filter(array_map('trim', (array)($r['who'] ?? [])));
    if ($who) $h .= '<p><strong>Кому будет полезна:</strong></p><ul><li>' . implode('</li><li>', array_map('htmlspecialchars', $who)) . '</li></ul>';
    if (!empty($r['why'])) $h .= '<p>' . htmlspecialchars(trim($r['why'])) . '</p>';
    $h .= '<p>Планируете посетить выставку или ищете поставщика по этой тематике? Оставьте заявку — специалисты ИТК подскажут, '
        . 'как подготовиться, помогут с переговорами на месте и организуют поставку из Китая под ключ.</p>';
    $h .= '<p class="post__note">Даты и место указаны по календарю выставок Китая; перед поездкой уточняйте их на официальном сайте выставки.</p>';

    $slug = nb_translit($name . ' ' . date('Y', $x['from']));
    $slug = substr($slug, 0, 64) . '-' . substr(md5($x['key']), 0, 4);
    return [
        'slug' => $slug, 'title' => $title, 'date' => date('d.m.Y'), 'ts' => time(),
        'city' => $city, 'topic' => trim($r['topic'] ?? ''), 'dates' => $dates,
        'from' => $x['from'], 'to' => $x['to'], 'name_cn' => $x['name'], 'source' => $x['src'],
        'picture' => trim($r['picture'] ?? '') ?: ('интерьер большого выставочного павильона в Китае, стенды: ' . trim($r['topic'] ?? '')),
        'html' => $h,
    ];
}

function nb_translit($s) {
    $map = ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m',
            'н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
            'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya'];
    $s = strtr(mb_strtolower($s), $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'expo';
}
