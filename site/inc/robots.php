<?php
// robots.txt: на тестовом адресе new.itcpartner.ru индексация запрещена полностью
header('Content-Type: text/plain; charset=utf-8');
if (strpos($_SERVER['HTTP_HOST'] ?? '', 'new.') === 0) {
    echo "User-agent: *\nDisallow: /\n";
    return;
}
echo "User-agent: *\n";
echo "Disallow: /newsbot.php\nDisallow: /send.php\nDisallow: /data/\nDisallow: /inc/\nDisallow: /pages/\n";
echo "Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&ysclid\n";
echo "\nSitemap: https://itcpartner.ru/sitemap.xml\n";
