<?php
/**
 * =====================================================================
 *  ИТК · Фото 1688 → S3  ·  api/ob_photo.php
 * =====================================================================
 *
 *  ЗАЧЕМ: ссылки на cbu01.alicdn.com из России не открываются — в карточке
 *  вместо товара пустой квадрат. Без переноса в S3 карточку продавать нельзя.
 *
 *  ЦЕПОЧКА:  alicdn → (напрямую или через китайский прокси) → сжатие webp →
 *            s3_put_object() → бакет itc-media → ссылка selstorage.ru
 *
 *  МАСШТАБ: у живого товара 709411502301 было 73 фото по ~2.4 МБ = 175 МБ
 *  на один товар. Поэтому: лимит OB_MAX_MAIN_IMGS и сжатие до ~250 КБ.
 *
 *  Подключение в api.php после ob_1688.php:
 *      require_once __DIR__ . '/ob_photo.php';
 * =====================================================================
 */

if (!defined('OB_PHOTO_LOADED')) {
define('OB_PHOTO_LOADED', 1);

define('OB_IMG_MAX_W',    1000);   // ширина карточки-квадрата
define('OB_IMG_QUALITY',  82);     // webp
define('OB_IMG_TARGET_KB', 300);   // цель по весу; если больше — жмём сильнее
define('OB_IMG_MIN_Q',    55);     // ниже не опускаемся


// ─────────────────────────────────────────────────────────────────────
//  ДИАГНОСТИКА СРЕДЫ
//  Проверяет всё, от чего зависит перенос фото. Вызывать через ob_photo_env.
// ─────────────────────────────────────────────────────────────────────
function ob_photo_env() {
    global $S3_ENABLED, $S3_BUCKET, $S3_PUBLIC_URL;

    $env = [];

    // 1. Чем сжимать
    $env['gd']       = extension_loaded('gd');
    $env['gd_webp']  = function_exists('imagewebp');
    $env['imagick']  = extension_loaded('imagick');
    $env['imagick_webp'] = false;
    if ($env['imagick']) {
        try { $env['imagick_webp'] = in_array('WEBP', Imagick::queryFormats('WEBP')); }
        catch (Exception $e) {}
    }

    // 2. S3
    $env['s3_enabled'] = !empty($S3_ENABLED);
    $env['s3_bucket']  = $S3_BUCKET ?? null;
    $env['s3_put_fn']  = function_exists('s3_put_object');

    // 3. Прокси
    $env['proxy_url'] = defined('PDD_PROXY_URL') ? PDD_PROXY_URL : null;
    $env['proxy_key'] = defined('PDD_PROXY_KEY') && PDD_PROXY_KEY ? 'задан' : 'НЕТ';

    // 4. Куда писать временные файлы
    $env['tmp_dir']      = sys_get_temp_dir();
    $env['tmp_writable'] = is_writable(sys_get_temp_dir());

    return $env;
}

/** Достаём ли alicdn напрямую с Reg.ru? Главный вопрос всей задачи. */
function ob_photo_probe($url) {
    $out = [];

    $t0 = microtime(true);
    $r = ob_img_download($url, false);
    $out['direct'] = [
        'ok'    => !empty($r['ok']),
        'bytes' => $r['bytes'] ?? 0,
        'ms'    => (int)round((microtime(true)-$t0)*1000),
        'error' => $r['error'] ?? null,
    ];
    if (!empty($r['path'])) @unlink($r['path']);

    $t1 = microtime(true);
    $r2 = ob_img_download($url, true);
    $out['via_proxy'] = [
        'ok'    => !empty($r2['ok']),
        'bytes' => $r2['bytes'] ?? 0,
        'ms'    => (int)round((microtime(true)-$t1)*1000),
        'error' => $r2['error'] ?? null,
    ];
    if (!empty($r2['path'])) @unlink($r2['path']);

    return $out;
}


// ─────────────────────────────────────────────────────────────────────
//  СКАЧИВАНИЕ
// ─────────────────────────────────────────────────────────────────────
function ob_img_download($url, $viaProxy = false) {
    $url = trim((string)$url);
    if ($url === '') return ['ok'=>false, 'error'=>'пустой URL'];
    if (strpos($url, '//') === 0) $url = 'https:' . $url;

    $target = $url;
    if ($viaProxy) {
        if (!defined('PDD_PROXY_URL') || !PDD_PROXY_URL) {
            return ['ok'=>false, 'error'=>'PDD_PROXY_URL не задан в config.php'];
        }
        $ep = PDD_PROXY_URL;
        $target = $ep . (strpos($ep,'?')===false ? '?' : '&')
                . 'key=' . urlencode(defined('PDD_PROXY_KEY') ? PDD_PROXY_KEY : '')
                . '&url=' . urlencode($url);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'obimg');
    $fh  = @fopen($tmp, 'wb');
    if (!$fh) return ['ok'=>false, 'error'=>'не создать временный файл'];

    $ch = curl_init($target);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        CURLOPT_HTTPHEADER     => ['Referer: https://detail.1688.com/'],
    ]);
    $ok   = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    fclose($fh);

    $size = @filesize($tmp) ?: 0;

    if (!$ok || $err) { @unlink($tmp); return ['ok'=>false, 'error'=>$err ?: 'нет ответа']; }
    if ($code !== 200) { @unlink($tmp); return ['ok'=>false, 'error'=>"HTTP $code"]; }
    if ($size < 500)   { @unlink($tmp); return ['ok'=>false, 'error'=>"пусто ($size байт)"]; }

    // Точно ли картинка
    $info = @getimagesize($tmp);
    if ($info === false) { @unlink($tmp); return ['ok'=>false, 'error'=>'не изображение']; }

    return ['ok'=>true, 'path'=>$tmp, 'bytes'=>$size,
            'w'=>$info[0], 'h'=>$info[1], 'mime'=>$info['mime']];
}


// ─────────────────────────────────────────────────────────────────────
//  СЖАТИЕ в webp
//  Тестовый файл Алексея весил 2.44 МБ. При потоке это неподъёмно —
//  жмём до ~250 КБ. Если webp недоступен — jpeg.
// ─────────────────────────────────────────────────────────────────────
function ob_img_compress($srcPath, $maxW = OB_IMG_MAX_W) {
    if (!extension_loaded('gd')) {
        return ['ok'=>false, 'error'=>'расширение GD не установлено'];
    }
    $info = @getimagesize($srcPath);
    if (!$info) return ['ok'=>false, 'error'=>'не изображение'];

    [$w, $h, $type] = $info;

    $img = null;
    switch ($type) {
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($srcPath); break;
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($srcPath);  break;
        case IMAGETYPE_GIF:  $img = @imagecreatefromgif($srcPath);  break;
        case IMAGETYPE_WEBP: $img = function_exists('imagecreatefromwebp')
                                  ? @imagecreatefromwebp($srcPath) : null; break;
    }
    if (!$img) return ['ok'=>false, 'error'=>'не прочитать (тип '.$type.')'];

    // Уменьшаем, если больше нужного
    if ($w > $maxW) {
        $nh = (int)round($h * $maxW / $w);
        $dst = imagecreatetruecolor($maxW, $nh);
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0,0,0,0, $maxW, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst; $w = $maxW; $h = $nh;
    }

    $useWebp = function_exists('imagewebp');
    $ext = $useWebp ? 'webp' : 'jpg';
    // tempnam() СОЗДАЁТ файл. Приписав расширение, мы получаем другое имя и
    // пишем уже в него — а пустышка без расширения остаётся в /tmp навсегда:
    // ob_img_to_s3() удаляет только тот путь, что мы вернули. При 5000
    // карточек по 20 фото это 100 000 брошенных файлов в сутки.
    $tmpBase = tempnam(sys_get_temp_dir(), 'obc');
    $out     = $tmpBase . '.' . $ext;
    @unlink($tmpBase);

    // Подбираем качество, пока не уложимся в вес
    $q = OB_IMG_QUALITY;
    do {
        if ($useWebp) @imagewebp($img, $out, $q);
        else          @imagejpeg($img, $out, $q);
        $sz = @filesize($out) ?: 0;
        if ($sz <= OB_IMG_TARGET_KB * 1024 || $q <= OB_IMG_MIN_Q) break;
        $q -= 10;
    } while (true);

    imagedestroy($img);
    if (!file_exists($out) || filesize($out) < 100) {
        return ['ok'=>false, 'error'=>'сжатие не удалось'];
    }

    return ['ok'=>true, 'path'=>$out, 'bytes'=>filesize($out),
            'w'=>$w, 'h'=>$h, 'ext'=>$ext, 'quality'=>$q];
}


// ─────────────────────────────────────────────────────────────────────
//  ОДНО ФОТО: alicdn → S3
// ─────────────────────────────────────────────────────────────────────
function ob_img_to_s3($url, $num_iid, $kind = 'main', $sort = 0) {
    global $S3_ENABLED, $S3_PUBLIC_URL;

    if (empty($S3_ENABLED)) return ['ok'=>false, 'error'=>'S3 выключен ($S3_ENABLED)'];
    if (!function_exists('s3_put_object')) return ['ok'=>false, 'error'=>'нет s3_put_object()'];

    // 1. Качаем. Сначала напрямую, при неудаче — через китайский прокси.
    $dl = ob_img_download($url, false);
    $route = 'direct';
    if (empty($dl['ok'])) {
        $dl = ob_img_download($url, true);
        $route = 'proxy';
    }
    if (empty($dl['ok'])) {
        return ['ok'=>false, 'error'=>'не скачать: ' . ($dl['error'] ?? '?')];
    }

    // 2. Жмём
    $cp = ob_img_compress($dl['path']);
    @unlink($dl['path']);
    if (empty($cp['ok'])) return ['ok'=>false, 'error'=>'не сжать: ' . ($cp['error'] ?? '?')];

    // 3. В S3
    $key = 'ob/' . $num_iid . '/' . $kind . '_' . $sort . '_' . substr(md5($url), 0, 8)
         . '.' . $cp['ext'];
    $ctype = $cp['ext'] === 'webp' ? 'image/webp' : 'image/jpeg';
    $put = s3_put_object($key, $cp['path'], $ctype);
    @unlink($cp['path']);

    if (!$put) return ['ok'=>false, 'error'=>'S3 отклонил загрузку'];

    $publicUrl = rtrim($S3_PUBLIC_URL, '/') . '/' . $key;

    return ['ok'=>true, 'url'=>$publicUrl, 'key'=>$key,
            'bytes'=>$cp['bytes'], 'was'=>$dl['bytes'],
            'w'=>$cp['w'], 'h'=>$cp['h'], 'q'=>$cp['quality'], 'route'=>$route];
}


// ─────────────────────────────────────────────────────────────────────
//  ВСЕ ФОТО ТОВАРА
//  desc_img НЕ трогаем — там китайский текст, их путь через 象寄.
// ─────────────────────────────────────────────────────────────────────
function ob_photos_process($num_iid, $opts = []) {
    $kinds = $opts['kinds'] ?? ['main', 'sku'];
    $limit = (int)($opts['limit'] ?? 20);

    $in = implode(',', array_fill(0, count($kinds), '?'));
    $rows = ob_all(
        "SELECT id, kind, sort, url_src, url_s3 FROM ob_img
          WHERE num_iid = ? AND kind IN ($in)
            AND (url_s3 IS NULL OR url_s3 = '')
          ORDER BY kind, sort LIMIT " . $limit,
        array_merge([$num_iid], $kinds)
    );

    $done = []; $fail = []; $totalWas = 0; $totalNow = 0;
    foreach ($rows as $r) {
        $res = ob_img_to_s3($r['url_src'], $num_iid, $r['kind'], (int)$r['sort']);
        if (!empty($res['ok'])) {
            ob_exec("UPDATE ob_img SET url_s3=?, bytes=?, width=?, height=?, status='stored'
                      WHERE id=?",
                    [$res['url'], $res['bytes'], $res['w'], $res['h'], $r['id']]);
            $done[] = ['kind'=>$r['kind'], 'sort'=>$r['sort'], 'url'=>$res['url'],
                       'was_kb'=>round($res['was']/1024), 'now_kb'=>round($res['bytes']/1024),
                       'route'=>$res['route']];
            $totalWas += $res['was']; $totalNow += $res['bytes'];
        } else {
            ob_exec("UPDATE ob_img SET status='error' WHERE id=?", [$r['id']]);
            $fail[] = ['sort'=>$r['sort'], 'error'=>$res['error']];
        }
    }

    // Связь «фото ↔ вариант». Без неё у SKU остаётся пустой img_s3, и карточка
    // берёт общую обложку: покупатель видит «40 белый», а на фото коричневый
    // шестислойный. Функция была, но её звал только ob_recalc_item — то есть
    // после переноса в S3 связь не обновлялась никогда.
    if ($done && function_exists('ob_link_sku_photos')) ob_link_sku_photos($num_iid);

    // Обложка и галерея — в карточки этого товара
    if ($done) ob_photos_sync_products($num_iid);

    return ['ok'=>true, 'done'=>count($done), 'failed'=>count($fail),
            'items'=>$done, 'errors'=>$fail,
            'was_mb'=>round($totalWas/1048576, 2), 'now_mb'=>round($totalNow/1048576, 2),
            'saved'=>$totalWas > 0 ? round((1-$totalNow/$totalWas)*100) . '%' : '—'];
}

/** Прописываем ссылки S3 в products: обложка + галерея. */
function ob_photos_sync_products($num_iid) {
    // Общие фото товара — те, что на 1688 висят на карточке целиком
    $imgs = ob_all("SELECT url_s3 FROM ob_img
                     WHERE num_iid=? AND kind='main' AND url_s3<>''
                     ORDER BY is_cover DESC, sort ASC", [$num_iid]);
    $common = array_values(array_column($imgs, 'url_s3'));

    $rows = ob_all("SELECT product_id, img_s3 FROM ob_sku
                     WHERE num_iid=? AND product_id IS NOT NULL", [$num_iid]);
    $n = 0;
    foreach ($rows as $r) {
        $own = trim((string)($r['img_s3'] ?? ''));

        // ГАЛЕРЕЯ У КАЖДОГО ВАРИАНТА СВОЯ: сначала его собственное фото,
        // потом общие. Раньше галерея была ОДНА на все 19 карточек — человек
        // выбирал белый четырёхслойный, открывал фото и листал коричневые
        // шестислойные. Карточка врала о том, что продаёт.
        $urls = $own !== ''
              ? array_merge([$own], array_values(array_diff($common, [$own])))
              : $common;
        if (!$urls) continue;

        ob_exec("UPDATE products SET img=?, gallery=? WHERE id=?",
                [$urls[0], json_encode(array_slice($urls, 0, 10), JSON_UNESCAPED_UNICODE),
                 $r['product_id']]);
        $n++;
    }
    return $n;
}

} // OB_PHOTO_LOADED
