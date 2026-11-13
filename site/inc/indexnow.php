<?php
// IndexNow (3.49.0): сообщаем Яндексу о новых и изменённых страницах сразу после обновления.
// Ключ — не секрет: он же лежит в корне сайта файлом /КЛЮЧ.txt, так Яндекс проверяет, что сайт наш.
const INDEXNOW_KEY = 'd6a80f05bf3d773d8e0b4bba13465143';

// Все страницы сайта с «отметкой изменения»: если отметка поменялась — страницу надо переобойти
function indexnow_pages() {
    $base = 'https://itcpartner.ru'; $out = [];
    $stat = ['/', '/news', '/horeca', '/mebel', '/santehnika', '/odezhda-obuv-tekstil'];
    foreach (sup_site_pages() as $slug => $p) {
        $stamp = ($p['updated'] ?? '') . ':' . md5(json_encode($p['data'] ?? []));
        foreach (['', '/en', '/zh'] as $lp) $out[$base . $lp . '/' . $slug] = $stamp;
    }
    foreach (['horeca', 'mebel', 'santeh'] as $ssp) foreach (site_subsec_list($ssp) as $ss) {
        $stamp = md5(json_encode($ss));
        foreach (['', '/en', '/zh'] as $lp) $out[$base . $lp . $ss[0]] = $stamp;
    }
    // главная и разделы меняются, когда появляются новые фабрики
    $all = md5(implode('|', array_keys(sup_site_pages())));
    foreach ($stat as $s) foreach (['', '/en', '/zh'] as $lp) $out[$base . $lp . ($s === '/' && $lp ? '/' : $s)] = $all;
    return $out;
}

// Отправить новые и изменённые страницы. Первый запуск отправляет всё.
function indexnow_run() {
    $f = ROOT . '/data/indexnow.json';
    $st = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    $sent = $st['sent'] ?? [];
    if (!empty($st['last']) && time() - (int)$st['last'] < 60) return ['ok' => true, 'skipped' => 'слишком часто', 'last' => $st['log'][0] ?? null];
    $now = indexnow_pages(); $urls = [];
    foreach ($now as $u => $stamp) if (($sent[$u] ?? null) !== $stamp) $urls[] = $u;
    $res = ['ok' => true, 'sent' => 0, 'total' => count($now), 'code' => 0];
    foreach (array_chunk($urls, 9000) as $part) {
        $body = json_encode(['host' => 'itcpartner.ru', 'key' => INDEXNOW_KEY,
            'keyLocation' => 'https://itcpartner.ru/' . INDEXNOW_KEY . '.txt', 'urlList' => $part], JSON_UNESCAPED_SLASHES);
        $ch = curl_init('https://yandex.com/indexnow');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8']]);
        $answer = (string)curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $res['code'] = $code;
        if ($code === 200 || $code === 202) { foreach ($part as $u) $sent[$u] = $now[$u]; $res['sent'] += count($part); }
        else { $res['ok'] = false; $res['error'] = $code ? 'Яндекс ответил ' . $code . ': ' . mb_substr($answer, 0, 200) : 'нет связи с Яндексом'; break; }
    }
    $log = $st['log'] ?? [];
    array_unshift($log, ['t' => date('Y-m-d H:i'), 'sent' => $res['sent'], 'code' => $res['code'], 'error' => $res['error'] ?? '']);
    @file_put_contents($f, json_encode(['sent' => $sent, 'last' => time(), 'log' => array_slice($log, 0, 30)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $res;
}
