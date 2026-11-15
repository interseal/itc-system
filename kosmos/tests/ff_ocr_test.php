<?php
// №627 · Проверка ускорения загрузки фото: снимки на шлюз «Космоса» по отпечатку (kosmos_lib.php), текст фото — после ответа
// телефону (flow.php), одно соединение на весь запрос (ai_lib.php, ks_gw_call). Запуск: php tests/ff_ocr_test.php
// Функции берём прямо из файлов магазина (целиком подключить нельзя — они сразу отвечают на запрос).
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }
function cut($src, $from, $to) { $a = strpos($src, $from); $b = $a === false ? false : strpos($src, $to, $a); return $a === false || $b === false ? '' : substr($src, $a, $b - $a); }

/* 1. Отпечатки снимков (kosmos_lib.php) */
$kl = (string)file_get_contents($root . '/store/api/kosmos_lib.php');
$code = cut($kl, 'function ks_gw_imgs(', '/* Спросить Claude');
t('kosmos_lib: функции отпечатков найдены', $code !== '');
$TMPD = sys_get_temp_dir() . '/ffocr' . getmypid(); @mkdir($TMPD);
function ks_dir($sub = '') { global $TMPD; $d = $TMPD . ($sub !== '' ? '/' . $sub : ''); if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
eval($code);
$imgs = [['m' => 'image/jpeg', 'd' => base64_encode('снимок-1')], ['m' => 'image/png', 'd' => base64_encode('снимок-2')]];
$r1 = ks_gw_imgs($imgs);
t('первый раз — снимки целиком (и отпечаток рядом)', count($r1) === 2 && $r1[0]['data'] === $imgs[0]['d'] && $r1[0]['sha'] === hash('sha256', $imgs[0]['d']), $r1);
ks_gw_imgs_sent($r1);
$r2 = ks_gw_imgs($imgs);
t('после ответа шлюза с img_cache — только отпечатки', !isset($r2[0]['data']) && !isset($r2[1]['data']) && $r2[1]['media_type'] === 'image/png', $r2);
$GLOBALS['KS_IMG_SENT'] = [];
$r3 = ks_gw_imgs($imgs);
t('в следующей реплике (новый запрос) — тоже по отпечатку (пометка на диске)', !isset($r3[0]['data']), $r3);
ks_gw_imgs_forget([$r1[0]['sha']]);
$r4 = ks_gw_imgs($imgs);
t('шлюз снимка не нашёл — этот снимок снова целиком, другой по отпечатку', isset($r4[0]['data']) && !isset($r4[1]['data']), $r4);
t('целиком по требованию (повтор после 409)', isset(ks_gw_imgs($imgs, true)[1]['data']));
array_map('unlink', glob("$TMPD/imgsent/*") ?: []); @rmdir("$TMPD/imgsent"); @rmdir($TMPD);
t('старый шлюз (без img_cache) пометок не получает', strpos($kl, "!empty(\$j['img_cache'])) ks_gw_imgs_sent") !== false);
t('ks_gw_call: одно соединение (curl_reset) и без «Expect: 100-continue»', strpos(cut($kl, 'function ks_gw_call(', "\n}\n"), 'curl_reset') !== false && strpos(cut($kl, 'function ks_gw_call(', "\n}\n"), "'Expect:'") !== false);

/* 2. Текст фото — после ответа телефону (flow.php) */
$fl = (string)file_get_contents($root . '/store/api/flow.php');
function fl_yk() { return 'тест-ключ'; }
eval(cut($fl, 'function ff_ocr_req(', 'function ff_ocr_save('));
$err = ''; $q = ff_ocr_req('JPEGDATA', 'image/jpeg', $err);
t('запрос к Яндексу собирается отдельно (для одновременного распознавания)', $q && strpos($q[0], 'ocr.api.cloud.yandex.net') !== false && json_decode($q[1], true)['content'] === base64_encode('JPEGDATA') && in_array('Expect:', $q[2], true), $q);
t('PDF-скан → mimeType PDF', json_decode(ff_ocr_req('x', 'application/pdf', $err)[1], true)['mimeType'] === 'PDF');
$e = ''; t('ответ Яндекса разбирается', ff_ocr_parse(200, json_encode(['result' => ['textAnnotation' => ['fullText' => " Счёт №5 \n"]]]), $e) === 'Счёт №5');
$e = ''; t('ошибка Яндекса — null и понятная причина', ff_ocr_parse(429, json_encode(['message' => 'лимит']), $e) === null && strpos($e, '429') !== false && strpos($e, 'лимит') !== false, $e);
t('фото без текста — честная пометка', strpos(ff_text_final('фото', '', '', 'лого.png', 'png', 2048), 'текста на нём нет') !== false);
t('файл без текста — сохранён, размер и причина', strpos($x = ff_text_final('PDF-скан', null, 'Яндекс ответил 500', 'скан.pdf', 'pdf', 4096), '4 КБ') !== false && strpos($x, 'Яндекс ответил 500') !== false, $x);
t('обычный текст — как есть', ff_text_final('фото', ' Накладная 12 ', '', 'a.jpg', 'jpg', 1) === 'Накладная 12');
$case = cut($fl, "case 'fc_file': {", "case 'fc_deal_voice'");
$p1 = strpos($case, "fl_out_keep(['ok' => true, 'id' => \$id"); $p2 = strpos($case, '$t = ff_ocr($bin, $ocrLater');
t('fc_file: телефон получает id ДО распознавания (fl_out_keep, потом ff_ocr)', $p1 !== false && $p2 !== false && $p1 < $p2);
t('fc_file: прежний путь остаётся, если колонки ocr нет или просят ждать (wait)', strpos($case, "(string)fl_in('wait') === '' && ff_ocr_on()") !== false && strpos($case, "elseif (\$fastImg) { \$kind = 'фото'; \$text = ff_ocr(") !== false);
t('перед вопросом текст дожидаемся (ff_attach_text → ff_ocr_wait)', strpos(cut($fl, 'function ff_attach_text(', "\n}\n"), 'ff_ocr_wait($me, $ids)') !== false);
t('несделанное распознаём одновременно (curl_multi)', strpos(cut($fl, 'function ff_ocr_many(', "\n}\n"), 'curl_multi_exec') !== false);
t('колонка ocr добавляется сама', strpos($fl, "'ocr TINYINT NOT NULL DEFAULT 0'") !== false);
t('уменьшенный снимок кешируется по id (fil_files/sm)', strpos($fl, 'function ff_img_cached(') !== false && strpos(cut($fl, 'function ff_attach_imgs(', "\n}\n"), 'ff_img_cached(') !== false);
t('kosmos_flow.php собран из flow.php с этими правками', strpos((string)file_get_contents($root . '/store/api/kosmos_flow.php'), 'function ff_ocr_wait(') !== false);

/* 3. Одно соединение для DeepSeek и Яндекса (ai_lib.php) */
$al = (string)file_get_contents($root . '/store/api/ai_lib.php');
$ip = cut($al, 'function itcai_post(', "\n}\n");
t('itcai_post: соединение с тем же сервером переиспользуется, обрыв — новое', strpos($ip, 'static $hs') !== false && strpos($ip, 'curl_reset') !== false && strpos($ip, 'unset($hs[$k])') !== false);

/* 4. Телефон: сжатие на белом, не больше 3 загрузок сразу, проценты */
foreach (['fil', 'kosmos'] as $app) {
  $h = (string)file_get_contents("$root/store/apps/$app/index.html");
  t("$app: фото на белом фоне, JPEG 84, 3 загрузки сразу, проценты", strpos($h, "g.fillStyle = '#fff'") !== false && strpos($h, "toDataURL('image/jpeg', 0.84)") !== false && strpos($h, 'UPN < 3') !== false && strpos($h, 'x.upload.onprogress') !== false);
  $f = (string)file_get_contents("$root/store/apps/$app/files.js");
  t("$app/files.js: то же для «Воронок сделок»", strpos($f, "toDataURL('image/jpeg', 0.84)") !== false && strpos($f, 'work(), work(), work()') !== false);
}

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
