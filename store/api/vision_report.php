<?php
// ИТК · vision_report.php · отчёт «чего не хватает в категориях» по фото и названию (08.10.2026)
// Временный файл. В МАГАЗИНЕ НИЧЕГО НЕ МЕНЯЕТ — только смотрит и считает.
// Берёт товары из «Прочего», без категории и из разделов «всё остальное»
// (Органайзеры, Предметы декора, Канцелярия › Канцелярия и т.п.), показывает
// модели Qwen3.6 фото + название и собирает: куда бы она их положила и каких
// категорий/подкатегорий не хватает. Порция ~20 товаров, страница сама
// продолжает; прогресс в api/.vision_report.json. Начать заново: ?reset=1
@set_time_limit(120);
@ignore_user_abort(true);
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ob_yandex.php';

define('VR_FILE', __DIR__ . '/.vision_report.json');
define('VR_MODEL', 'qwen3.6-35b-a3b');
// Разделы «всё остальное» внутри категорий (последняя строка в itc_store_subcat)
$CATCH = ['Одежда', 'Аксессуары для ванной', 'Кухонные принадлежности', 'Ручной инструмент', 'Органайзеры',
          'Предметы декора', 'Для сада', 'Техника для кухни', 'Столы', 'Новогодний декор', 'Канцелярия'];

function vr_load() { $j = json_decode((string)@file_get_contents(VR_FILE), true); return is_array($j) ? $j : null; }
function vr_save($st) {
  $j = json_encode($st, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
  if (!is_string($j) || $j === '') return;
  $tmp = VR_FILE . '.tmp'; if (@file_put_contents($tmp, $j) !== false) @rename($tmp, VR_FILE);
}
function vr_img_url($img) {
  $img = trim((string)$img);
  if ($img === '') return '';
  if ($img[0] === '[' || $img[0] === '{') { $j = json_decode($img, true); if (is_array($j)) { $f = reset($j); $img = is_array($f) ? (string)($f['url'] ?? $f['src'] ?? '') : (string)$f; } }
  if (strpos($img, '//') === 0) $img = 'https:' . $img;
  if ($img !== '' && $img[0] === '/') $img = 'https://itcpartner.store' . $img;
  return $img;
}
function vr_jpeg_b64($url) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
  $b = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($b === false || $code !== 200 || strlen($b) < 500 || !function_exists('imagecreatefromstring')) return '';
  $im = @imagecreatefromstring($b); if (!$im) return '';
  $w = imagesx($im); $h = imagesy($im); $k = min(1, 768 / max($w, $h));
  if ($k < 1) { $nw = (int)($w * $k); $nh = (int)($h * $k); $t = imagecreatetruecolor($nw, $nh);
    imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255));
    imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im); $im = $t; }
  ob_start(); imagejpeg($im, null, 85); $b = ob_get_clean(); imagedestroy($im);
  return 'data:image/jpeg;base64,' . base64_encode($b);
}
function vr_ask($imgData, $tree, $name) {
  $sys = "Ты товаровед российского маркетплейса ИТК. Тебе дают фото товара и его название.\n"
       . "Выбери категорию и подкатегорию СТРОГО из дерева ниже.\n"
       . "ПРАВИЛА:\n"
       . "- «Для гостиниц», «Для ресторанов», «Для салонов» — только товары явно для бизнеса (гостиничное бельё, мебель для кафе, оборудование салона). Обычный бытовой товар — в бытовую категорию.\n"
       . "- Подкатегорию-«всё остальное» (Органайзеры, Предметы декора, Ручной инструмент, Кухонные принадлежности и т.п.) выбирай, только если точнее ничего нет.\n"
       . "- Если ни одна категория не подходит по смыслу — предложи новую в new_cat.\n"
       . "- Если категория подходит, но точной подкатегории нет — предложи новую в new_sub.\n"
       . "- Новые называй ОБЩИМ словом, как раздел на Ozon/Wildberries: «Кольца», а не «Кольца с бриллиантами»; «Смесители», а не «Смесители для ванной LED».\n"
       . "Ответ — только JSON одной строкой: {\"cat\":\"...\",\"sub\":\"...\",\"new_cat\":\"\",\"new_sub\":\"\"}\n\n"
       . "Дерево категорий:\n" . $tree;
  $body = [
    'model' => yx_ds_uri(VR_MODEL),
    'messages' => [
      ['role' => 'system', 'content' => $sys],
      ['role' => 'user', 'content' => [
        ['type' => 'text', 'text' => 'Название: ' . $name],
        ['type' => 'image_url', 'image_url' => ['url' => $imgData]],
      ]],
    ],
    'temperature' => 0.1, 'max_tokens' => 300, 'reasoning_effort' => 'none',
  ];
  $ch = curl_init(YX_CHAT_URL);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 40,
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Api-Key ' . YC_API_KEY, 'x-folder-id: ' . YC_FOLDER_ID]]);
  $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $j = json_decode((string)$r, true);
  if ($code !== 200) return ['err' => 'ошибка ' . $code . ': ' . mb_substr((string)($j['error']['message'] ?? $j['message'] ?? $r), 0, 160)];
  $txt = trim(preg_replace('/<think>.*?<\/think>/su', '', (string)($j['choices'][0]['message']['content'] ?? '')));
  if (preg_match('/\{.*\}/su', $txt, $m)) { $o = json_decode($m[0], true); if (is_array($o)) return $o; }
  return ['err' => 'не разобрал ответ'];
}
$H = function ($x) { return htmlspecialchars((string)$x); };

echo '<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1">';
if (!yx_ready()) { echo 'Нет ключей Яндекса в config.php'; exit; }

// ---- справочник и дерево ----
$cats = [];
try { foreach (db()->query('SELECT name FROM categories ORDER BY sort, name')->fetchAll() as $c) { $n = trim((string)$c['name']); if ($n !== '') $cats[] = $n; } } catch (Throwable $e) {}
$sub = [];
try { foreach (db()->query("SELECT cat, subcat, COUNT(*) c FROM products WHERE cat<>'' AND subcat IS NOT NULL AND subcat<>'' GROUP BY cat, subcat ORDER BY cat, c DESC")->fetchAll() as $r) $sub[$r['cat']][] = $r['subcat']; } catch (Throwable $e) {}
$tree = '';
foreach ($cats as $c) $tree .= '- ' . $c . (!empty($sub[$c]) ? ': ' . implode('; ', $sub[$c]) : '') . "\n";

// ---- очередь: один товар на семейство (варианты одного товара — одно фото) ----
$st = vr_load();
if (!$st || !empty($_GET['reset'])) {
  $in = implode(',', array_map(function ($x) { return db()->quote($x); }, $CATCH));
  $rows = db()->query("SELECT id, name, cat, subcat FROM products WHERE img IS NOT NULL AND img<>'' AND
                       (cat IS NULL OR cat='' OR cat='Прочее' OR subcat IN ($in)) ORDER BY id")->fetchAll();
  $seen = []; $q = [];
  foreach ($rows as $r) {
    $base = mb_strtolower(trim(explode(' · ', (string)$r['name'])[0]));
    $key = $r['cat'] . '|' . $base;
    if (isset($seen[$key])) continue; $seen[$key] = 1;
    $q[] = $r['id'];
  }
  $st = ['queue' => $q, 'pos' => 0, 'res' => [], 'errs' => 0, 'started' => date('Y-m-d H:i'), 'rows_total' => count($rows)];
  vr_save($st);
}

// ---- порция ----
$t0 = microtime(true); $total = count($st['queue']);
while ($st['pos'] < $total && microtime(true) - $t0 < 35) {
  $id = $st['queue'][$st['pos']]; $st['pos']++;
  $q = db()->prepare('SELECT id, name, img, cat, subcat FROM products WHERE id=?'); $q->execute([$id]); $p = $q->fetch();
  if (!$p) continue;
  $b64 = vr_jpeg_b64(vr_img_url($p['img']));
  if ($b64 === '') { $st['errs']++; continue; }
  $o = vr_ask($b64, $tree, mb_substr((string)$p['name'], 0, 120));
  if (isset($o['err'])) { $st['errs']++; $st['last_err'] = $o['err']; continue; }
  $st['res'][] = ['n' => mb_substr((string)$p['name'], 0, 60), 'c0' => (string)$p['cat'], 's0' => (string)$p['subcat'],
                  'c' => trim((string)($o['cat'] ?? '')), 's' => trim((string)($o['sub'] ?? '')),
                  'nc' => trim((string)($o['new_cat'] ?? '')), 'ns' => trim((string)($o['new_sub'] ?? ''))];
  vr_save($st);
}
vr_save($st);
$done = $st['pos'] >= $total;
$pct = $total ? round($st['pos'] * 100 / $total) : 100;

if (!$done) echo '<meta http-equiv="refresh" content="2">';
echo '<title>Отчёт по категориям</title><body style="font:15px/1.45 Arial,sans-serif;max-width:760px;margin:16px auto;padding:0 14px;color:#1d2433">';
echo '<h2 style="color:#0D1B4B;margin:0 0 8px">Чего не хватает в категориях</h2>';
echo '<p style="color:#6a7180;margin:0 0 6px">Товары из «Прочего» и разделов «всё остальное» · ' . (int)$st['rows_total'] . ' карточек, ' . $total . ' разных товаров · ИИ смотрит фото и название · в магазине ничего не меняется</p>';
echo '<p><b>' . $st['pos'] . '</b> из <b>' . $total . '</b> (' . $pct . '%) · не удалось: ' . (int)$st['errs']
   . (!empty($st['last_err']) && !$done ? ' <span style="color:#b45309;font-size:13px">(' . $H($st['last_err']) . ')</span>' : '') . '</p>';
echo '<div style="background:#e6e9ef;border-radius:6px;height:10px;margin:6px 0 10px"><div style="background:#0D1B4B;height:10px;border-radius:6px;width:' . $pct . '%"></div></div>';
echo $done ? '<p style="color:#128A04;font-weight:bold">Готово. Ниже — итог.</p>'
           : '<p>Идёт проверка — страница сама продолжит. Оборвётся — просто открой её снова.</p>';

// ---- итог ----
$newCat = []; $newSub = []; $moves = []; $toSub = [];
foreach ($st['res'] as $r) {
  if ($r['nc'] !== '') $newCat[$r['nc']][] = $r['n'];
  if ($r['ns'] !== '') $newSub[($r['c'] ?: '?') . ' › ' . $r['ns']][] = $r['n'];
  if ($r['nc'] === '' && $r['c'] !== '' && mb_strtolower($r['c']) !== mb_strtolower($r['c0'])) $moves[($r['c0'] ?: 'без категории') . ' → ' . $r['c']][] = $r['n'];
  elseif ($r['ns'] === '' && $r['s'] !== '' && mb_strtolower($r['s']) !== mb_strtolower($r['s0']) && mb_strtolower($r['c']) === mb_strtolower($r['c0'])) $toSub[$r['c'] . ': ' . ($r['s0'] ?: '—') . ' → ' . $r['s']][] = $r['n'];
}
$list = function ($title, $hint, $arr, $limit = 40) use ($H) {
  echo '<h3 style="color:#0D1B4B;margin:16px 0 4px">' . $title . '</h3><div style="font-size:13px;color:#6a7180;margin-bottom:4px">' . $hint . '</div>';
  if (!$arr) { echo '<p style="margin:2px 0">нет</p>'; return; }
  uasort($arr, function ($x, $y) { return count($y) - count($x); });
  echo '<ul style="margin:0;padding-left:20px">';
  foreach (array_slice($arr, 0, $limit, true) as $k => $items)
    echo '<li><b>' . $H($k) . '</b> — ' . count($items) . ' тов. <span style="color:#6a7180;font-size:12.5px">(' . $H(implode('; ', array_map(function ($x) { return mb_substr($x, 0, 35); }, array_slice($items, 0, 3)))) . ')</span></li>';
  echo '</ul>';
};
echo '<div style="background:#f5f7ff;border:1px solid #d7ddf5;border-radius:10px;padding:12px;margin-top:12px">';
echo '<p style="margin:0">Проверено товаров: <b>' . count($st['res']) . '</b>' . ($done ? '' : ' (итог растёт по ходу)') . '</p>';
$list('Не хватает категорий', 'ИИ не нашёл подходящей категории и предлагает новую', $newCat);
$list('Не хватает подкатегорий', 'категория есть, а точного раздела внутри нет', $newSub);
$list('Из «Прочего» и не той категории — куда переложить', 'существующие категории, куда ИИ положил бы товар', $moves);
$list('Из раздела «всё остальное» — в точный раздел', 'внутри той же категории', $toSub, 25);
echo '</div>';
echo '<p style="font-size:13px;color:#6a7180">Начать проверку заново: добавь к адресу <b>?reset=1</b>. После решения файл удалить.</p>';
