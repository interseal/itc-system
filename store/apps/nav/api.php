<?php
// ==== ИТК Навигатор · api.php v1 · 02.11.2026 ====
// POST JSON {a, d, ...}:
//   place — {q, city}: «Южный вокзал» → китайское слово для поиска на Amap (广州南站)
//   names — {items:[китайские названия]}: → русские названия (улицы, места) для подсказок и голоса
// Думает DeepSeek (общая библиотека магазина ai_lib.php). Ответы хранятся месяц и общие для всех —
// одинаковые запросы мгновенны и бесплатны. Лимит на устройство — от случайных повторов и ботов.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function nv_out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function nv_fail($e, $m = '') { nv_out(['ok' => false, 'error' => $e, 'msg' => $m]); }

$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
if (strpos($ref, 'itcpartner.store') === false) nv_fail('forbidden');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') nv_fail('post_only');
$in = json_decode(file_get_contents('php://input', false, null, 0, 200000), true);
if (!is_array($in)) nv_fail('bad_json');
$dev = preg_replace('/[^a-z0-9]/', '', strtolower((string)($in['d'] ?? '')));
if (strlen($dev) < 12 || strlen($dev) > 48) nv_fail('no_device');

$cfg = dirname(__DIR__, 2) . '/api/config.php';
if (is_file($cfg)) { ob_start(); try { @include_once $cfg; } catch (Throwable $e) {} ob_end_clean(); }
require_once dirname(__DIR__, 2) . '/api/ai_lib.php';

// лимит: 60 запросов в минуту на устройство
$rl = itcai_dir() . '/nav_rl'; if (!is_dir($rl)) @mkdir($rl, 0755, true);
$f = $rl . '/' . md5($dev); $cur = date('YmdHi'); $n = 0;
if (is_file($f)) { [$w, $c] = array_pad(explode(' ', (string)file_get_contents($f)), 2, 0); if ($w === $cur) $n = (int)$c; }
if ($n >= 60) nv_fail('slow_down', 'Слишком часто. Подождите минуту.');
@file_put_contents($f, $cur . ' ' . ($n + 1));

$a = (string)($in['a'] ?? '');
if ($a === 'place') {
  $q = trim(mb_substr((string)($in['q'] ?? ''), 0, 120));
  $city = trim(mb_substr((string)($in['city'] ?? ''), 0, 40));
  if ($q === '') nv_out(['ok' => true, 'kw' => '']);
  $key = md5('pl2|' . mb_strtolower($q) . '|' . $city);   // v2: старые ответы (pl1) не берём — там «Южный вокзал» мог стать вокзалом Фошаня
  if ($c = itcai_cache_get('nav', $key, 86400 * 30)) nv_out(['ok' => true] + $c);
  $sys = "Ты помогаешь русскоязычному человеку в Китае найти место на карте Amap (高德). Он пишет, куда хочет попасть, "
       . "по-русски или по-английски, иногда приблизительно или с ошибками. Сейчас он в городе: " . ($city ?: 'неизвестно') . ".\n"
       . "Верни ТОЛЬКО JSON {\"kw\":\"...\",\"alt\":[\"...\"],\"ru\":\"...\"}: kw — лучшее КИТАЙСКОЕ название для поиска на Amap "
       . "(официальное название места, станции, вокзала, рынка, ТЦ, улицы; без лишних слов), alt — до 3 запасных китайских вариантов, "
       . "ru — как это место правильно называется по-русски. Примеры: «Южный вокзал» в Гуанчжоу → 广州南站; «рынок Шарик» → 广州火车站 "
       . "(рынки у вокзала); «Кантонская ярмарка» → 中国进出口商品交易会展馆; «аэропорт» в Гуанчжоу → 广州白云国际机场.\n"
       . "ВАЖНО: Гуанчжоу и Фошань — один город (агломерация), человек часто живёт в Фошане (Бэйцзяо, Шуньдэ) и ездит в Гуанчжоу. "
       . "Вокзалы называй точно и НЕ подменяй ближайшим: «Южный вокзал», «Южно-железнодорожный вокзал», «Гуанчжоу Южный», «Наньчжань» → 广州南站 "
       . "(даже если человек сейчас в Фошане); «вокзал Гуанчжоу», «главный вокзал», «Шарик» → 广州站; «Восточный вокзал» → 广州东站; «Северный вокзал» → 广州北站; "
       . "«вокзал Байюнь», «новый вокзал» → 广州白云站; «вокзал Фошань» → 佛山站; «Фошань Западный» → 佛山西站; «Шуньдэ вокзал» → 顺德站; «Бэйцзяо» → 北滘站. "
       . "ru — по-русски, как назвал человек, но правильно (например «Южный вокзал Гуанчжоу»).";
  $j = itcai_json(itcai_ask($sys, $q, 200, 12, true));
  if (!$j || empty($j['kw'])) nv_fail('ai_failed', 'Не получилось понять адрес (' . itcai_err_text() . '). Попробуйте написать иначе или по-китайски.');
  $o = ['kw' => mb_substr((string)$j['kw'], 0, 60), 'alt' => array_slice(array_map('strval', (array)($j['alt'] ?? [])), 0, 3), 'ru' => mb_substr((string)($j['ru'] ?? ''), 0, 120)];
  itcai_cache_put('nav', $key, $o);
  nv_out(['ok' => true] + $o);
}

if ($a === 'names') {   // №330 · русское и английское название; по 10 штук за раз, чтобы ИИ успевал
  $items = is_array($in['items'] ?? null) ? array_slice($in['items'], 0, 40) : [];
  $ru = []; $en = []; $todo = [];
  foreach ($items as $i => $s) {
    $s = trim(mb_substr((string)$s, 0, 80));
    if ($s === '' || !preg_match('/\p{Han}/u', $s)) { $ru[$i] = $s; $en[$i] = $s; continue; }
    $k = md5('nm2|' . $s);
    if ($c = itcai_cache_get('nav', $k, 86400 * 60)) { $ru[$i] = $c['ru']; $en[$i] = $c['en'] ?? ''; } else $todo[$i] = [$s, $k];
  }
  $sys = "Переведи китайские названия мест (магазины, рестораны, ТЦ, аптеки, улицы, здания) для туриста. "
       . "ru — по-русски: родовое слово по-русски (ресторан, чайная, аптека, торговый центр), имя собственное — по Палладию, известные сети — как принято (肯德基 → KFC, 麦当劳 → Макдоналдс). "
       . "en — по-английски: официальное английское название, если есть, иначе понятный перевод с пиньинем имени. Коротко, без пояснений. "
       . "Ответ ТОЛЬКО JSON {\"t\":[{\"ru\":\"...\",\"en\":\"...\"}]} — столько же элементов и в том же порядке.";
  foreach (array_chunk($todo, 10, true) as $part) {
    $lines = []; $n = 1; foreach ($part as $t) $lines[] = ($n++) . '. ' . $t[0];
    $j = itcai_json(itcai_ask($sys, implode("\n", $lines), 1500, 25, true));
    $arr = is_array($j['t'] ?? null) ? array_values($j['t']) : [];
    $n = 0;
    foreach ($part as $i => $t) { $x = $arr[$n++] ?? null; $r = is_array($x) ? trim((string)($x['ru'] ?? '')) : trim((string)$x); $e = is_array($x) ? trim((string)($x['en'] ?? '')) : '';
      if ($r !== '') { $ru[$i] = $r; $en[$i] = $e; itcai_cache_put('nav', $t[1], ['ru' => $r, 'en' => $e]); } else { $ru[$i] = null; $en[$i] = null; } }
  }
  $list = []; $le = []; foreach ($items as $i => $s) { $list[] = $ru[$i] ?? null; $le[] = $en[$i] ?? null; }
  nv_out(['ok' => true, 'list' => $list, 'en' => $le]);
}
/* v3 · название места на трёх языках: {zh, addr} → {ru, en, addr_ru} */
if ($a === 'place3') {
  $zh = trim(mb_substr((string)($in['zh'] ?? ''), 0, 80)); $ad = trim(mb_substr((string)($in['addr'] ?? ''), 0, 120));
  if ($zh === '') nv_out(['ok' => true, 'ru' => '', 'en' => '', 'addr_ru' => '']);
  $key = md5('p3|' . $zh . '|' . $ad);
  if ($c = itcai_cache_get('nav', $key, 86400 * 60)) nv_out(['ok' => true] + $c);
  $sys = "Переведи китайское название места и адрес для туриста. Ответ ТОЛЬКО JSON {\"ru\":\"...\",\"en\":\"...\",\"addr_ru\":\"...\"}. "
       . "ru — как место называется по-русски (вокзалы, аэропорты, ТЦ, рынки — общепринятым русским названием: 广州南站 → Южный вокзал Гуанчжоу, 佛山站 → вокзал Фошань; "
       . "имена собственные — по Палладию); en — официальное английское название, если оно есть (Guangzhou South Railway Station), иначе на пиньине с английским родовым словом; "
       . "addr_ru — адрес по-русски (улицы по Палладию, номер дома цифрами), пусто, если адреса нет. Коротко, без пояснений.";
  $j = itcai_json(itcai_ask($sys, $zh . ($ad !== '' ? "\nАдрес: " . $ad : ''), 300, 12, true));
  if (!$j) nv_fail('ai_failed');
  $o = ['ru' => mb_substr((string)($j['ru'] ?? ''), 0, 120), 'en' => mb_substr((string)($j['en'] ?? ''), 0, 120), 'addr_ru' => mb_substr((string)($j['addr_ru'] ?? ''), 0, 200)];
  itcai_cache_put('nav', $key, $o);
  nv_out(['ok' => true] + $o);
}

nv_fail('bad_action');
