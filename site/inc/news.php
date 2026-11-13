<?php
// ============================================================
//  Новости: ручные (inc/news-data.php) + автоматические
//  о выставках в Китае (data/news-auto.json, пишет newsbot.php)
// ============================================================

const NEWS_MONTHS = ['января','февраля','марта','апреля','мая','июня','июля','августа','сентября','октября','ноября','декабря'];

// Все новости, свежие сверху
function news_all() {
    static $all = null;
    if ($all !== null) return $all;
    $all = [];
    $ru = news_lang() === 'ru';
    // Ручные новости есть только по-русски
    if ($ru) foreach (require ROOT . '/inc/news-data.php' as $slug => $n) {
        $d = DateTime::createFromFormat('d.m.Y H:i', $n['date'] . ' 12:00');
        $n['ts']   = $d ? $d->getTimestamp() : 0;
        $n['auto'] = false;
        $all[$slug] = $n;
    }
    foreach (news_auto_load()['items'] as $slug => $n) {
        if (!empty($n['hidden'])) continue;
        // На английской и китайской версии — только переведённые новости
        if (!$ru && !($n = news_localize($slug, $n))) continue;
        $n['auto'] = true;
        $all[$slug] = $n;
    }
    uasort($all, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
    return $all;
}

// Только предстоящие выставки (прошедшие не показываются нигде), ближайшие первыми.
// Ручные новости без даты выставки показываются 60 дней после публикации.
function news_upcoming() {
    $today = strtotime('today');
    $list = array_filter(news_all(), function ($n) use ($today) {
        return !empty($n['auto']) ? ($n['to'] ?? 0) >= $today : $n['ts'] >= $today - 60 * 86400;
    });
    uasort($list, function ($a, $b) {
        return ($a['from'] ?? PHP_INT_MAX) <=> ($b['from'] ?? PHP_INT_MAX);
    });
    return $list;
}

function news_auto_file() { return ROOT . '/data/news-auto.json'; }

// ---------- Английская и китайская версии новостей ----------
// Перевод берётся из самой новости (поле i18n — его пишет newsbot.php)
// или из data/news-i18n.json (переводы новостей, опубликованных до появления языков).
function news_lang() { return function_exists('lang') ? lang() : 'ru'; }

function news_localize($slug, $n) {
    static $file = null;
    $l = news_lang();
    if ($l === 'ru') return $n;
    if ($file === null) {
        $f = ROOT . '/data/news-i18n.json';
        $file = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }
    $t = $n['i18n'][$l] ?? $file[$slug][$l] ?? null;
    if (empty($t['title']) || empty($t['html'])) return null;
    foreach (['title', 'city', 'topic', 'dates', 'html'] as $k) if (!empty($t[$k])) $n[$k] = $t[$k];
    return $n;
}

// Есть ли у новости перевод на язык
function news_has_lang($slug, $n, $l) {
    static $file = null;
    if ($file === null) {
        $f = ROOT . '/data/news-i18n.json';
        $file = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }
    return !empty($n['i18n'][$l]['title']) || !empty($file[$slug][$l]['title']);
}

// Даты на английском: «October 12–16, 2026», «October 30 – November 1, 2026»
function news_dates_en($from, $to) {
    $a = getdate($from); $b = getdate($to);
    if ($from === $to) return "{$a['month']} {$a['mday']}, {$a['year']}";
    if ($a['year'] !== $b['year']) return "{$a['month']} {$a['mday']}, {$a['year']} – {$b['month']} {$b['mday']}, {$b['year']}";
    if ($a['mon'] === $b['mon']) return "{$a['month']} {$a['mday']}–{$b['mday']}, {$a['year']}";
    return "{$a['month']} {$a['mday']} – {$b['month']} {$b['mday']}, {$a['year']}";
}

// Даты на китайском: «2026年10月12–16日», «2026年10月30日–11月1日»
function news_dates_zh($from, $to) {
    $a = getdate($from); $b = getdate($to);
    if ($from === $to) return "{$a['year']}年{$a['mon']}月{$a['mday']}日";
    if ($a['year'] !== $b['year']) return "{$a['year']}年{$a['mon']}月{$a['mday']}日–{$b['year']}年{$b['mon']}月{$b['mday']}日";
    if ($a['mon'] === $b['mon']) return "{$a['year']}年{$a['mon']}月{$a['mday']}–{$b['mday']}日";
    return "{$a['year']}年{$a['mon']}月{$a['mday']}日–{$b['mon']}月{$b['mday']}日";
}

function news_auto_load() {
    $f = news_auto_file();
    $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    if (!is_array($j)) $j = [];
    $j += ['items' => [], 'seen' => [], 'runs' => []];
    return $j;
}

// «12–16 октября 2026» / «28 октября – 1 ноября 2026»
function news_dates_ru($from, $to) {
    $a = getdate($from); $b = getdate($to);
    $ma = NEWS_MONTHS[$a['mon'] - 1]; $mb = NEWS_MONTHS[$b['mon'] - 1];
    if ($from === $to) return "{$a['mday']} $ma {$a['year']}";
    if ($a['year'] !== $b['year']) return "{$a['mday']} $ma {$a['year']} – {$b['mday']} $mb {$b['year']}";
    if ($a['mon'] === $b['mon']) return "{$a['mday']}–{$b['mday']} $ma {$a['year']}";
    return "{$a['mday']} $ma – {$b['mday']} $mb {$a['year']}";
}

// Обложка: у ручных — фото, у автоматических — фирменная плашка
function news_cover($n, $cls) {
    if (empty($n['auto'])) {
        return '<div class="' . $cls . '" style="background-image:url(\'' . e(timg($n['cover'])) . '\')"></div>';
    }
    if (!empty($n['photo']) && is_file(ROOT . $n['photo'])) {
        return '<div class="' . $cls . ' nphoto" style="background-image:url(\'' . e($n['photo']) . '\')">'
             . '<span class="nphoto__tag">' . e($n['city'] ?? '') . ' · ' . e($n['dates'] ?? '') . '</span></div>';
    }
    $v = 'ncover--' . (crc32($n['city'] ?? '') % 3);
    return '<div class="' . $cls . ' ncover ' . $v . '">'
         . '<span class="ncover__label">Выставка в Китае</span>'
         . '<span class="ncover__city">' . e($n['city'] ?? '') . '</span>'
         . '<span class="ncover__dates">' . e($n['dates'] ?? '') . '</span>'
         . '<span class="ncover__topic">' . e($n['topic'] ?? '') . '</span>'
         . '<span class="ncover__brand">ITC</span>'
         . '</div>';
}
