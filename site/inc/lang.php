<?php
// ============================================================
//  Три языка сайта: русский (основной), английский (/en/…), китайский (/zh/…)
//
//  Страницы пишутся только по-русски. Для английской и китайской версии
//  готовая страница переводится по словарям lang/en.php и lang/zh.php:
//  «русская фраза» => «перевод». Фраза, которой нет в словаре, остаётся русской.
//  Поменяли текст на русской странице — добавьте новую фразу в оба словаря.
// ============================================================

// код => [надпись в переключателе, язык для браузера, начало адреса]
const LANGS = [
    'ru' => ['RU',   'ru',    ''],
    'en' => ['EN',   'en',    '/en'],
    'zh' => ['中文', 'zh-CN', '/zh'],
];

// Язык берём из начала адреса: /en/… или /zh/…
$GLOBALS['LANG'] = preg_match('~^/(en|zh)(/|$)~', strtok($_SERVER['REQUEST_URI'] ?? '/', '?'), $m) ? $m[1] : 'ru';

function lang()        { return $GLOBALS['LANG']; }
function lang_code()   { return LANGS[lang()][1]; }
function lang_prefix() { return LANGS[lang()][2]; }

// Адрес текущей страницы на другом языке
function lang_switch_url($to) {
    $p = page_url();
    return LANGS[$to][2] . ($p === '/' && $to !== 'ru' ? '/' : $p);
}

// Словарь текущего языка
function lang_dict() {
    static $d = null;
    if ($d === null) {
        $f = ROOT . '/lang/' . lang() . '.php';
        $d = is_file($f) ? (require $f) : [];
    }
    return $d;
}

// Словарь делится на две части: фразы с разметкой внутри (<b>, <a> …) и простые фразы.
// Простая фраза заменяется только целиком (весь текст между тегами или кавычками),
// чтобы короткое слово «от» не заменилось внутри слова «высокотехнологичная».
function lang_dict_parts() {
    static $p = null;
    if ($p === null) {
        $p = [[], [], []];
        // фраза с {n} — образец для фраз с разными числами: «Найдено: {n}» подходит к «Найдено: 12»
        foreach (lang_dict() as $k => $v) $p[strpbrk($k, '<>') !== false ? 0 : (strpos($k, '{n}') !== false ? 2 : 1)][$k] = $v;
    }
    return $p;
}

// Перевод одной строки без разметки (для данных о компании в поиске)
function lang_plain($s) {
    $d = lang_dict();
    if (isset($d[$s])) return html_entity_decode($d[$s], ENT_QUOTES, 'UTF-8');
    $k = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    if (isset($d[$k])) return html_entity_decode($d[$k], ENT_QUOTES, 'UTF-8');
    return $s;
}

// Адрес страницы сайта на текущем языке (картинки, файлы и чужие сайты не трогаем)
function lang_href($h) {
    if (lang() === 'ru' || $h === '' || $h[0] !== '/' || (isset($h[1]) && $h[1] === '/')) return $h;
    if (preg_match('~^/(en|zh)(/|$|#)~', $h)) return $h;
    if (preg_match('~^/(assets|img|data|inc|lang|pages)/~', $h)) return $h;
    $path = strtok($h, '?#');
    if (preg_match('~\.([a-z0-9]+)$~i', $path, $m) && strtolower($m[1]) !== 'html') return $h;
    return lang_prefix() . $h;
}

// Перевод готовой страницы целиком (вызывается один раз в конце)
function lang_translate($html) {
    if (lang() === 'ru') return $html;
    $keep = [];
    $hold = function ($s) use (&$keep) { $keep[] = $s; return "\x01" . (count($keep) - 1) . "\x01"; };

    // 1. Данные о компании для поисковиков (JSON): переводим по строкам
    $html = preg_replace_callback('~(<script type="application/ld\+json">)(.*?)(</script>)~s', function ($m) use ($hold) {
        $j = json_decode($m[2], true);
        if (!is_array($j)) return $hold($m[0]);
        array_walk_recursive($j, function (&$v) {
            if (!is_string($v)) return;
            if (preg_match('~^https://itcpartner\.ru(/.*)?$~', $v, $u)) $v = 'https://itcpartner.ru' . lang_href($u[1] ?? '/');
            elseif (preg_match('/\p{Cyrillic}/u', $v)) $v = lang_plain($v);
        });
        return $hold($m[1] . json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $m[3]);
    }, $html);

    // 2. Название формы в заявке оставляем русским — менеджерам так понятнее
    $html = preg_replace_callback('~ data-form="[^"]*"~', function ($m) use ($hold) { return $hold($m[0]); }, $html);

    // 3. Переключатель языков не переводим и не переделываем
    $html = preg_replace_callback('~<a [^>]*hreflang=[^>]*>.*?</a>~s', function ($m) use ($hold) { return $hold($m[0]); }, $html);

    // 4. Сам перевод. Сначала фразы с разметкой (самые длинные — первыми),
    //    потом простые фразы — только если это весь текст между тегами или кавычками
    [$withTags, $plain, $nums] = lang_dict_parts();
    $html = strtr($html, $withTags);
    $done = preg_replace_callback('~([>"\'])(\s*)([^<>"\']+?)(\s*)(?=[<"\'])~u', function ($m) use ($plain, $nums) {
        if (isset($plain[$m[3]])) return $m[1] . $m[2] . $plain[$m[3]] . $m[4];
        // нет точной фразы — ищем образец, где числа заменены на {n}, и подставляем числа обратно по порядку
        if ($nums && preg_match_all('~\d+~', $m[3], $d)) {
            $k = preg_replace('~\d+~', '{n}', $m[3]);
            if (isset($nums[$k])) { $i = 0; return $m[1] . $m[2] . preg_replace_callback('~\{n\}~', function () use (&$i, $d) { return $d[0][$i++] ?? ''; }, $nums[$k]) . $m[4]; }
        }
        return $m[0];
    }, $html);
    if ($done !== null) $html = $done;

    // 5. Ссылки на страницы сайта ведут на ту же языковую версию
    $html = preg_replace_callback('~(<a\b[^>]*?\bhref=")([^"]*)"~', function ($m) {
        return $m[1] . lang_href($m[2]) . '"';
    }, $html);

    return preg_replace_callback("~\x01(\\d+)\x01~", function ($m) use (&$keep) { return $keep[(int)$m[1]]; }, $html);
}
