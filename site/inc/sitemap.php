<?php
// Карта сайта для Яндекса и Google: все страницы + предстоящие выставки
require_once ROOT . '/inc/news.php';
header('Content-Type: application/xml; charset=utf-8');
$base = 'https://itcpartner.ru';
$pages = [
  ['/', '1.0', 'weekly'], ['/kargo-iz-kitaya', '0.9', 'weekly'], ['/vykup-tovarov-iz-kitaya', '0.9', 'weekly'], ['/oplata-postavshchikam-v-kitay', '0.9', 'weekly'], ['/sklad-v-guanchzhou', '0.9', 'weekly'], ['/fulfilment-v-kitae', '0.9', 'weekly'], ['/avtozapchasti-iz-kitaya', '0.9', 'weekly'], ['/solnechnaya-energetika', '0.9', 'weekly'], ['/snadi', '0.8', 'weekly'], ['/carson', '0.8', 'weekly'], ['/lianli', '0.8', 'weekly'], ['/euromax', '0.8', 'weekly'], ['/gpsolar', '0.8', 'weekly'], ['/pntech', '0.8', 'weekly'], ['/kingfeels', '0.8', 'weekly'], ['/riwas', '0.8', 'weekly'], ['/selhoztehnika', '0.9', 'weekly'], ['/oborudovanie-dlya-kafe', '0.9', 'weekly'], ['/tehnika-dlya-volos', '0.9', 'weekly'], ['/fascar', '0.8', 'weekly'], ['/zhengtong', '0.8', 'weekly'], ['/onas', '0.6', 'monthly'],
  ['/mebel', '0.7', 'monthly'], ['/santehnika', '0.8', 'weekly'], ['/odezhda-obuv-tekstil', '0.8', 'weekly'], ['/horeca', '0.8', 'weekly'], ['/page126678496.html', '0.7', 'monthly'], ['/news', '0.7', 'daily'],
  ['/contacts', '0.6', 'monthly'], ['/kase-shini', '0.5', 'monthly'], ['/kase-diski', '0.5', 'monthly'],
  ['/kase-lamenirovannie-poli', '0.5', 'monthly'], ['/kase-elektronika', '0.5', 'monthly'], ['/kase-furniture', '0.5', 'monthly'], ['/mebelnaya-furnitura', '0.6', 'weekly'],
];
foreach (array_keys(require ROOT . '/inc/az-factories.php') as $azs) $pages[] = ['/avtozapchasti-iz-kitaya/' . $azs, '0.6', 'monthly'];
// разделы полных каталогов фабрик
foreach (glob(ROOT . '/inc/az-catalog/*.php') as $azc) {
  $azCat = require $azc; $azPer = (int)($azCat['per_page'] ?? 0); $azN = [];
  if ($azPer) foreach ($azCat['items'] as $it) $azN[$it[0]] = ($azN[$it[0]] ?? 0) + 1;
  foreach (array_keys($azCat['cats']) as $azcs) {
    $pages[] = ['/avtozapchasti-iz-kitaya/' . basename($azc, '.php') . '/' . $azcs, '0.6', 'monthly'];
    // большой раздел по страницам: ?p=2, ?p=3 …
    for ($n = 2; $azPer && $n <= ceil(($azN[$azcs] ?? 0) / $azPer); $n++) $pages[] = ['/avtozapchasti-iz-kitaya/' . basename($azc, '.php') . '/' . $azcs . '?p=' . $n, '0.4', 'monthly'];
  }
}
// страницы заводов из базы поставщиков Экосистемы
$have = array_column($pages, 0);
foreach (array_keys(sup_site_pages()) as $sps) if (!in_array('/' . $sps, $have, true)) $pages[] = ['/' . $sps, '0.8', 'weekly'];
// подразделы HoReCa и «Мебели» (3.45.0)
foreach (['horeca', 'mebel', 'santeh'] as $ssp) foreach (site_subsec_list($ssp) as $ss) if (!in_array($ss[0], $have, true)) { $pages[] = [$ss[0], '0.7', 'weekly']; $have[] = $ss[0]; }
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
// Каждая страница — на трёх языках: русская, /en/ и /zh/
foreach (['' , '/en', '/zh'] as $lp)
    foreach ($pages as $p) echo "<url><loc>$base$lp" . ($p[0] === '/' && $lp ? '/' : $p[0]) . "</loc><changefreq>{$p[2]}</changefreq><priority>" . ($lp ? '0.5' : $p[1]) . "</priority></url>\n";
foreach (news_upcoming() as $slug => $n) {
    $lm = date('Y-m-d', $n['ts']);
    echo "<url><loc>$base/tpost/" . htmlspecialchars($slug) . "</loc><lastmod>$lm</lastmod><priority>0.5</priority></url>\n";
    // переведённые новости — и на английском, и на китайском
    foreach (['en', 'zh'] as $l) if (!empty($n['auto']) && news_has_lang($slug, $n, $l))
        echo "<url><loc>$base/$l/tpost/" . htmlspecialchars($slug) . "</loc><lastmod>$lm</lastmod><priority>0.4</priority></url>\n";
}
echo "</urlset>\n";
