<?php
// №623 · Проверка: «Космос» (Claude) видит сами снимки — уменьшение до 2000 точек (№624, было 1600), не больше 10 (было 4), ошибки не роняют ответ,
// id файлов из прошлых реплик и фоновой задачи находятся. Запуск: php tests/ff_img_test.php [куда записать images.json]
// Функции берём прямо из store/api/flow.php (весь файл подключить нельзя — он сразу отвечает на запрос).
$root = dirname(__DIR__, 2);
$src = (string)file_get_contents($root . '/store/api/flow.php');
$a = strpos($src, '/* №623 · снимок перед отправкой Claude'); $b = strpos($src, 'function ff_attach_imgs(');
$c = strpos($src, '/* №623 · id файлов из текста'); $d = strpos($src, "\n}\n", strpos($src, 'function ff_imgs_note('));
if ($a === false || $b === false || $c === false || $d === false) { echo "  ✗ не нашёл функции №623 в flow.php\n"; exit(1); }
eval(substr($src, $a, $b - $a) . substr($src, $c, $d + 3 - $c));

$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }

/* тестовый скриншот без единой буквы: серый экран, тёмная шапка, белые плашки, одна обведена красным */
function png($w, $h, $px) {
  $raw = ''; for ($y = 0; $y < $h; $y++) { $raw .= "\0"; for ($x = 0; $x < $w; $x++) $raw .= $px($x, $y); }
  $ch = function ($t, $d) { return pack('N', strlen($d)) . $t . $d . pack('N', crc32($t . $d)); };
  return "\x89PNG\r\n\x1a\n" . $ch('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)) . $ch('IDAT', gzcompress($raw, 6)) . $ch('IEND', '');
}
$shot = png(2400, 1350, function ($x, $y) {
  if ($y < 90) return "\x28\x3c\x5a";
  if ($x >= 770 && $x <= 2350 && $y >= 520 && $y <= 680 && ($x < 780 || $x > 2340 || $y < 530 || $y > 670)) return "\xff\x00\x00";   // «подсвечено»
  if (($x >= 60 && $x <= 700 && $y >= 150) || ($x >= 780 && $x <= 2340 && ($y - 160) % 190 < 140)) return "\xff\xff\xff";
  return "\xec\xef\xf3";
});
t('тестовый скриншот 2400×1350 собран', (getimagesizefromstring($shot)[0] ?? 0) === 2400, strlen($shot));

$r = ff_img_small($shot, 'image/png');
$sz = $r ? getimagesizefromstring($r[1]) : [0, 0];
if (function_exists('imagecreatefromstring')) {
  t('с GD: уменьшен до 2000 по длинной стороне, JPEG', $r && $r[0] === 'image/jpeg' && $sz[0] === 2000 && $sz[1] === 1125, [$r[0] ?? null, $sz]);
} else {
  t('без GD: снимок идёт как есть (Claude уменьшит сам)', $r && $r[0] === 'image/png' && $r[1] === $shot, $r[0] ?? null);
}
$small = png(800, 450, function () { return "\x10\x20\x30"; });
t('небольшой снимок — без изменений', ff_img_small($small, 'image/png') === ['image/png', $small]);
t('не картинка — null (работаем как раньше, по тексту)', ff_img_small('это не картинка', 'image/jpeg') === null && ff_img_small('', 'image/png') === null);
t('битая картинка — не падает', ff_img_small("\x89PNG\r\n\x1a\nобрывок", 'image/png') === null);

$ids = ff_ids_in('Найди фабрику [📎 приложены файлы: «72904.jpg» (id ff251115a1b2c3d4), «счёт.pdf» (id ff251115deadbeef)]' . "\n=== ПРИЛОЖЕН ФАЙЛ «72904.jpg» (фото, id файла ff251115a1b2c3d4) ===");
t('id файлов из реплики и фоновой задачи', $ids === ['ff251115a1b2c3d4', 'ff251115deadbeef'], $ids);
t('без файлов — пусто', ff_ids_in('просто вопрос про id 5') === []);
t('пометка: новые снимки', strpos(ff_imgs_note([['n' => '72904.jpg']]), 'ты их ВИДИШЬ: «72904.jpg»') !== false && ff_imgs_note([]) === '');
t('пометка: снимки из прошлых сообщений', strpos(ff_imgs_note([['n' => 'a.png']], true), 'предыдущих сообщений') !== false);
t('№624: не больше 10 снимков за запрос, сторона 2000, все вместе до 18 МБ (в base64 < 32 МБ предела Claude)', FF_IMG_MAX === 10 && FF_IMG_SIDE === 2000 && FF_IMG_TOT * 4 / 3 < 32000000);

/* для общей проверки шлюза: снимок в том виде, как его шлёт магазин (ks_gw_ask → images) */
if (!empty($argv[1]) && $r) file_put_contents($argv[1], json_encode([['media_type' => $r[0], 'data' => base64_encode($r[1])]]));

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
