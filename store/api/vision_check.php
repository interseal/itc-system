<?php
// ИТК · vision_check.php (вариант 3: 20 товаров, категория+подкатегория, чего не хватает) · проверка: определяет ли ИИ категорию товара по фото (08.10.2026)
// Временный файл: после проверки удалить из папки api.
// Берёт 5 случайных товаров с фото, показывает фото модели Qwen3.6 (Яндекс)
// и сравнивает её ответ с нынешней категорией. Ключ не показывает.
@set_time_limit(170);
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ob_yandex.php';

echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><title>Категория по фото</title>'
   . '<body style="font:15px/1.45 Arial,sans-serif;max-width:760px;margin:16px auto;padding:0 14px;color:#1d2433">'
   . '<h2 style="color:#0D1B4B;margin:0 0 10px">Категория по фото — проверка</h2>';
if (!yx_ready()) { echo '<p>Нет ключей Яндекса в config.php</p>'; exit; }

$cats = [];
try { foreach (db()->query('SELECT name FROM categories ORDER BY sort, name')->fetchAll() as $c) { $n = trim((string)$c['name']); if ($n !== '') $cats[] = $n; } } catch (Throwable $e) {}
if (!$cats) { echo '<p>Справочник категорий пуст</p>'; exit; }

function vc_img_url($img) {
  $img = trim((string)$img);
  if ($img === '') return '';
  if ($img[0] === '[' || $img[0] === '{') { $j = json_decode($img, true); if (is_array($j)) { $f = reset($j); $img = is_array($f) ? (string)($f['url'] ?? $f['src'] ?? '') : (string)$f; } }
  if (strpos($img, '//') === 0) $img = 'https:' . $img;
  if ($img !== '' && $img[0] === '/') $img = 'https://itcpartner.store' . $img;
  return $img;
}
function vc_fetch_b64($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => false]);
  $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $ct = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE); curl_close($ch);
  if ($b === false || $code !== 200 || strlen($b) < 500) return '';
  // Модель Яндекса не читает WebP — перекодируем в JPEG и уменьшаем до 768 точек
  if (function_exists('imagecreatefromstring')) {
    $im = @imagecreatefromstring($b);
    if ($im) {
      $w = imagesx($im); $h = imagesy($im); $k = min(1, 768 / max($w, $h));
      if ($k < 1) { $nw = (int)($w * $k); $nh = (int)($h * $k); $t = imagecreatetruecolor($nw, $nh);
        imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
        imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im); $im = $t; }
      ob_start(); imagejpeg($im, null, 85); $b = ob_get_clean(); imagedestroy($im);
      return 'data:image/jpeg;base64,' . base64_encode($b);
    }
  }
  if (stripos($ct, 'image/') !== 0) $ct = 'image/jpeg';
  return 'data:' . $ct . ';base64,' . base64_encode($b);
}
function vc_ask($model, $imgData, $tree, $name, $extra) {
  $sys = "Ты товаровед российского маркетплейса ИТК. Тебе дают фото товара и его название.\n"
       . "Выбери категорию и подкатегорию СТРОГО из дерева ниже.\n"
       . "Если ни одна категория не подходит по смыслу — предложи новую в поле new_cat.\n"
       . "Если категория подходит, но ни одна её подкатегория не подходит — предложи новую в поле new_sub.\n"
       . "Ответ — только JSON одной строкой: {\"cat\":\"...\",\"sub\":\"...\",\"new_cat\":\"\",\"new_sub\":\"\"}\n"
       . "Названия новых — коротко, как на Ozon/Wildberries, по-русски.\n\nДерево категорий:\n" . $tree;
  $body = array_merge([
    'model' => yx_ds_uri($model),
    'messages' => [
      ['role' => 'system', 'content' => $sys],
      ['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Название: ' . $name],
        ['type' => 'image_url', 'image_url' => ['url' => $imgData]],
      ]],
    ],
    'temperature' => 0.1, 'max_tokens' => 300,
  ], $extra);
  $ch = curl_init(YX_CHAT_URL);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Api-Key ' . YC_API_KEY, 'x-folder-id: ' . YC_FOLDER_ID]]);
  $t = microtime(true);
  $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $s = round(microtime(true) - $t, 1);
  $j = json_decode((string)$r, true);
  if ($code !== 200) return ['ok' => false, 'txt' => 'ошибка ' . $code . ': ' . mb_substr((string)($j['error']['message'] ?? $j['message'] ?? $r), 0, 200), 's' => $s];
  $txt = trim(preg_replace('/<think>.*?<\/think>/su', '', (string)($j['choices'][0]['message']['content'] ?? '')));
  $txt = trim(preg_replace('/^```(?:json)?|```$/m', '', $txt));
  $o = null;
  if (preg_match('/\{.*\}/su', $txt, $m)) $o = json_decode($m[0], true);
  if (!is_array($o)) return ['ok' => false, 'txt' => 'не разобрал ответ: ' . mb_substr($txt, 0, 150), 's' => $s];
  return ['ok' => true, 'o' => $o, 's' => $s, 'tok' => $j['usage']['total_tokens'] ?? '?'];
}

$model = trim((string)($_GET['m'] ?? 'qwen3.6-35b-a3b'));
$N = max(1, min(30, (int)($_GET['n'] ?? 20)));

// Дерево: категории из справочника + подкатегории, которые реально есть у товаров
$sub = [];
try { foreach (db()->query("SELECT cat, subcat, COUNT(*) c FROM products WHERE cat<>'' AND subcat IS NOT NULL AND subcat<>'' GROUP BY cat, subcat ORDER BY cat, c DESC")->fetchAll() as $r) $sub[$r['cat']][] = $r['subcat']; } catch (Throwable $e) {}
$tree = '';
foreach ($cats as $c) $tree .= '- ' . $c . (!empty($sub[$c]) ? ': ' . implode('; ', $sub[$c]) : '') . "\n";

echo '<p style="color:#6a7180">Модель: <b>' . htmlspecialchars($model) . '</b> · товаров: ' . $N . ' · категорий: ' . count($cats) . ' · смотрит фото и название</p>';

$rows = [];
try { $rows = db()->query("SELECT id, name, img, cat, subcat FROM products WHERE img IS NOT NULL AND img<>'' ORDER BY RAND() LIMIT " . $N)->fetchAll(); }
catch (Throwable $e) { echo '<p>Ошибка базы: ' . htmlspecialchars($e->getMessage()) . '</p>'; exit; }

$hitC = 0; $hitS = 0; $n = 0; $extra = ['reasoning_effort' => 'none'];
$newCat = []; $newSub = []; $moved = [];
$low = function ($x) { return mb_strtolower(trim((string)$x, " .«»\"")); };
foreach ($rows as $p) {
  $url = vc_img_url($p['img']);
  $name = mb_substr((string)$p['name'], 0, 120);
  echo '<div style="border:1px solid #e3e6ee;border-radius:10px;padding:10px;margin:10px 0;display:flex;gap:10px">';
  echo '<img src="' . htmlspecialchars($url) . '" style="width:84px;height:84px;object-fit:cover;border-radius:8px;flex:0 0 auto">';
  echo '<div style="min-width:0;font-size:14px"><div style="font-size:13px;color:#6a7180">' . htmlspecialchars(mb_substr($name, 0, 80)) . '</div>';
  echo '<div>Сейчас: <b>' . htmlspecialchars($p['cat'] ?: '—') . '</b>' . ($p['subcat'] ? ' › ' . htmlspecialchars($p['subcat']) : '') . '</div>';
  $b64 = vc_fetch_b64($url);
  if ($b64 === '') $a = ['ok' => false, 'txt' => 'не удалось скачать фото', 's' => 0];
  else {
    $a = vc_ask($model, $b64, $tree, $name, $extra);
    if (!$a['ok'] && strpos($a['txt'], 'reasoning') !== false) { $extra = []; $a = vc_ask($model, $b64, $tree, $name, $extra); }
  }
  if (!$a['ok']) {
    echo '<div style="color:#c0392b">ИИ: ' . htmlspecialchars($a['txt']) . '</div>';
  } else {
    $n++; $o = $a['o'];
    $c = trim((string)($o['cat'] ?? '')); $sb = trim((string)($o['sub'] ?? ''));
    $nc = trim((string)($o['new_cat'] ?? '')); $ns = trim((string)($o['new_sub'] ?? ''));
    $sameC = $low($c) === $low($p['cat']); $sameS = $sameC && $low($sb) === $low($p['subcat']);
    if ($sameC) $hitC++; if ($sameS) $hitS++;
    if (!$sameC && $c !== '') $moved[] = [$name, $p['cat'], $c];
    echo '<div>ИИ: <b style="color:' . ($sameC ? '#128A04' : '#b45309') . '">' . htmlspecialchars($c ?: '—') . '</b>'
       . ($sb !== '' ? ' › <span style="color:' . ($sameS ? '#128A04' : '#b45309') . '">' . htmlspecialchars($sb) . '</span>' : '')
       . ' <span style="font-size:12px;color:#8a93a3">· ' . $a['s'] . ' сек · токенов ' . $a['tok'] . '</span></div>';
    if ($nc !== '') { $newCat[$nc][] = $name; echo '<div style="color:#1a56db">Предлагает новую категорию: <b>' . htmlspecialchars($nc) . '</b></div>'; }
    if ($ns !== '') { $k = ($c ?: '?') . ' › ' . $ns; $newSub[$k][] = $name; echo '<div style="color:#1a56db">Предлагает новую подкатегорию: <b>' . htmlspecialchars($k) . '</b></div>'; }
  }
  echo '</div></div>';
  @ob_flush(); @flush();
}

echo '<div style="background:#f5f7ff;border:1px solid #d7ddf5;border-radius:10px;padding:12px;margin-top:14px">';
echo '<h3 style="margin:0 0 8px;color:#0D1B4B">Итог по ' . $n . ' товарам</h3>';
echo '<p style="margin:4px 0">Категория совпала: <b>' . $hitC . ' из ' . $n . '</b> · подкатегория совпала: <b>' . $hitS . ' из ' . $n . '</b></p>';
$list = function ($title, $arr) {
  if (!$arr) { echo '<p style="margin:8px 0 0"><b>' . $title . ':</b> нет предложений</p>'; return; }
  uasort($arr, function ($x, $y) { return count($y) - count($x); });
  echo '<p style="margin:10px 0 4px"><b>' . $title . ':</b></p><ul style="margin:0;padding-left:20px">';
  foreach ($arr as $k => $items) echo '<li><b>' . htmlspecialchars($k) . '</b> — ' . count($items) . ' тов. <span style="color:#6a7180;font-size:13px">(' . htmlspecialchars(implode('; ', array_map(function ($x) { return mb_substr($x, 0, 40); }, array_slice($items, 0, 3)))) . ')</span></li>';
  echo '</ul>';
};
$list('Не хватает категорий', $newCat);
$list('Не хватает подкатегорий', $newSub);
if ($moved) {
  echo '<p style="margin:10px 0 4px"><b>ИИ перенёс бы в другую категорию:</b></p><ul style="margin:0;padding-left:20px;font-size:13px">';
  foreach ($moved as $m) echo '<li>' . htmlspecialchars(mb_substr($m[0], 0, 50)) . ': ' . htmlspecialchars($m[1] ?: '—') . ' → <b>' . htmlspecialchars($m[2]) . '</b></li>';
  echo '</ul>';
}
echo '</div>';
echo '<p style="font-size:13px;color:#6a7180">Обнови страницу — возьмутся другие товары. После проверки этот файл удалить.</p>';
