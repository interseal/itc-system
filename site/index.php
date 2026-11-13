<?php
// Единая точка входа: по адресу страницы выбираем, что показать
require __DIR__ . '/inc/core.php';
// Английская и китайская версии: готовая страница переводится по словарю
ob_start('lang_translate');

$path = rawurldecode(page_url());
$path = '/' . trim($path, '/');

$routes = [
    '/'                                           => 'home.php',
    '/onas'                                       => 'onas.php',
    '/contacts'                                   => 'contacts.php',
    '/news'                                       => 'news.php',
    '/mebel'                                      => 'mebel.php',
    '/santehnika'                                 => 'santehnika.php',
    '/odezhda-obuv-tekstil'                       => 'odezhda.php',
    '/horeca'                                     => 'horeca.php',
    '/mebelnaya-furnitura'                        => 'mebelnaya-furnitura.php',
    '/kargo-iz-kitaya'                            => 'cargo.php',
    '/vykup-tovarov-iz-kitaya'                    => 'vykup.php',
    '/oplata-postavshchikam-v-kitay'              => 'oplata.php',
    '/sklad-v-guanchzhou'                         => 'sklad.php',
    '/fulfilment-v-kitae'                         => 'fulfilment.php',
    '/snadi'                                      => 'snadi.php',
    '/carson'                                     => 'carson.php',
    '/lianli'                                     => 'lianli.php',
    '/euromax'                                    => 'euromax.php',
    '/gpsolar'                                    => 'gpsolar.php',
    '/pntech'                                     => 'pntech.php',
    '/kingfeels'                                  => 'kingfeels.php',
    '/solnechnaya-energetika'                     => 'solnechnaya-energetika.php',
    '/riwas'                                      => 'riwas.php',
    '/fascar'                                     => 'fascar.php',
    '/zhengtong'                                  => 'zhengtong.php',
    '/selhoztehnika'                              => 'selhoztehnika.php',
    '/oborudovanie-dlya-kafe'                     => 'oborudovanie-dlya-kafe.php',
    '/tehnika-dlya-volos'                         => 'tehnika-dlya-volos.php',
    '/avtozapchasti-iz-kitaya'                    => 'avtozapchasti.php',
    '/page126678496.html'                         => 'chestniy-znak.php',
    '/chestniy-znak'                              => 'chestniy-znak.php',
    '/privacy'                                    => 'privacy',
    '/soglasie-na-obrabotku-personalnih-dannih'   => 'soglasie',
];

// Экосистема после публикации просит сайт обновить страницу — у нас запоминания нет, просто отвечаем «ок»
if ($path === '/brand-refresh') { header('Content-Type: text/plain; charset=utf-8'); echo 'ok'; exit; }
// Страница завода из базы поставщиков Экосистемы. Опубликованная в Экосистеме
// страница заменяет старую страницу с тем же адресом (так заводы переносятся в базу).
// Бланк заказа для печати (3.60.0)
if (preg_match('~^/([a-z0-9][a-z0-9-]{1,79})/blank$~', $path, $m) && ($supPage = sup_site_page($m[1]))) {
    require ROOT . '/pages/sup-blank.php';
    exit;
}
if (preg_match('~^/([a-z0-9][a-z0-9-]{1,79})$~', $path, $m) && ($supPage = sup_site_page($m[1]))) {
    require ROOT . '/pages/sup-page.php';
    exit;
}

// Старый адрес страницы Карго — навсегда переносим на новый
if ($path === '/cargo') { header('Location: ' . lang_prefix() . '/kargo-iz-kitaya', true, 301); exit; }
// Старая страница услуг с Тильды — ведём на главную, к блоку услуг
if ($path === '/pecron') { header('Location: ' . lang_prefix() . '/', true, 302); exit; } // Pecron скрыт (страница pages/pecron.php сохранена)
if ($path === '/services-itc') { header('Location: ' . lang_prefix() . '/#services', true, 301); exit; }
// IndexNow (3.49.0): файл-ключ для Яндекса и запуск отправки новых страниц (зовёт Экосистема после обновления)
require_once ROOT . '/inc/indexnow.php';
if ($path === '/' . INDEXNOW_KEY . '.txt') { header('Content-Type: text/plain; charset=utf-8'); echo INDEXNOW_KEY; exit; }
if ($path === '/indexnow-ping' && lang() === 'ru') { header('Content-Type: application/json; charset=utf-8'); echo json_encode(indexnow_run(), JSON_UNESCAPED_UNICODE); exit; }
// Регистрация и кабинет (3.51.0) — только на русской версии
if (in_array($path, ['/registraciya', '/vhod', '/vyhod', '/kabinet', '/podtverzhdenie', '/zabyli-parol', '/novyj-parol', '/acc-inn'], true)) {
    if (lang() !== 'ru') { header('Location: ' . $path, true, 302); exit; }
    $accRoute = $path; require ROOT . '/pages/acc.php'; exit;
}
// Карта сайта и robots.txt для поисковиков
if ($path === '/sitemap.xml' && lang() === 'ru') { require ROOT . '/inc/sitemap.php'; exit; }
if ($path === '/robots.txt' && lang() === 'ru')  { require ROOT . '/inc/robots.php'; exit; }

if (isset($routes[$path])) {
    $p = $routes[$path];
    if ($p === 'privacy') {
        $title = 'Политика обработки персональных данных'; $crumb = 'Политика обработки персональных данных';
        $docFile = 'privacy-text.php'; require ROOT . '/pages/doc.php';
    } elseif ($p === 'soglasie') {
        $title = 'Согласие на обработку персональных данных'; $crumb = 'Согласие на обработку персональных данных';
        $docFile = 'soglasie-text.php'; require ROOT . '/pages/doc.php';
    } else {
        require ROOT . '/pages/' . $p;
    }
} elseif (preg_match('~^/kase-[a-z-]+$~', $path)) {
    $slug = substr($path, 1);
    require ROOT . '/pages/kase.php';
} elseif (preg_match('~^/avtozapchasti-iz-kitaya/([a-z0-9-]+)$~', $path, $m)) {
    // фабрики без страницы ведут на общий список
    if (!isset((require ROOT . '/inc/az-factories.php')[$m[1]])) { header('Location: ' . lang_prefix() . '/avtozapchasti-iz-kitaya', true, 302); exit; }
    $azSlug = $m[1];
    require ROOT . '/pages/az-factory.php';
} elseif (preg_match('~^/avtozapchasti-iz-kitaya/([a-z0-9-]+)/([a-z0-9-]+)$~', $path, $m)) {
    // раздел полного каталога фабрики; неизвестный раздел ведёт на страницу фабрики
    $azCatFile = ROOT . '/inc/az-catalog/' . $m[1] . '.php';
    if (!is_file($azCatFile) || !isset((require $azCatFile)['cats'][$m[2]])) { header('Location: ' . lang_prefix() . '/avtozapchasti-iz-kitaya/' . $m[1], true, 302); exit; }
    $azSlug = $m[1]; $azCatSlug = $m[2];
    require ROOT . '/pages/az-cat.php';
} elseif (preg_match('~^/(horeca|mebel|santehnika)/([a-z0-9-]+)$~', $path, $m) && ($ssParent = ($m[1] === 'santehnika' ? 'santeh' : $m[1])) && isset(site_subsecs()[$ssParent][2][$m[2]]) && empty(site_subsecs()[$ssParent][2][$m[2]][2]['@link'])) {
    // подраздел HoReCa, «Мебели» (3.45.0) или «Сантехники» (3.65.0)
    $ssKey = $m[2];
    require ROOT . '/pages/subsec.php';
} elseif (preg_match('~^/tpost/([a-z0-9-]+)$~', $path, $m)) {
    $slug = $m[1];
    require ROOT . '/pages/post.php';
} else {
    http_response_code(404);
    require ROOT . '/pages/404.php';
}
