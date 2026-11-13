<?php
// ==== ИТК · УМНЫЙ ПОИСК ПОСТАВЩИКОВ · sup_ai.php · №1 (02.11.2026) ====
// Вызывается из suppliers.php: action=sup_ai, q=<что ищем, своими словами>.
// Менеджер пишет «халаты для гостиниц» — получает фабрики, которые это делают,
// и смежные фабрики (полотенца, тапочки, постельное бельё…).
//
// Как устроено (быстро и недорого даже при тысячах поставщиков):
//   1) DeepSeek разбирает запрос: корни слов и синонимы (рус/англ/кит), разделы базы,
//      смежные товары. Это короткий запрос, ответ кэшируется на неделю.
//   2) Сервер сам отбирает по этим словам до 40 подходящих фабрик из всей базы (без ИИ).
//   3) DeepSeek смотрит только этих кандидатов и выбирает: кто делает это и кто смежный,
//      с одной строкой «почему». Готовый ответ кэшируется на сутки.
// Подключён после config.php и suppliers.php (sup_me, sup_ensure, sup_dec, sup_web_ensure).

require_once __DIR__ . '/ai_lib.php';

$me = sup_me(); sup_ensure(); sup_web_ensure();
$in = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
$q = trim(mb_substr((string)($in['q'] ?? ($_GET['q'] ?? '')), 0, 200));
if (mb_strlen($q) < 2) fail('Напишите, что ищем');

// лимит: 30 запросов в минуту на сотрудника — от случайных повторов
$rl = itcai_dir() . '/rl_' . md5($me['who']) . '.txt';
$cur = date('YmdHi'); $n = 0;
if (is_file($rl)) { [$w, $c] = array_pad(explode(' ', (string)file_get_contents($rl)), 2, 0); if ($w === $cur) $n = (int)$c; }
if ($n >= 30) fail('Слишком часто. Подождите минуту.');
@file_put_contents($rl, $cur . ' ' . ($n + 1));

/* ---------- индекс базы (пересобирается, когда база меняется) ---------- */
$ver = db()->query("SELECT CONCAT(COUNT(*),'|',IFNULL(MAX(updated_at),'')) v FROM itc_sup WHERE deleted=0")->fetch()['v']
     . '|' . db()->query("SELECT IFNULL(MAX(updated_at),'') v FROM itc_sup_web")->fetch()['v'];
$ixKey = md5('ix2|' . $ver);
$IX = itcai_cache_get('ix', $ixKey, 86400 * 30);
if (!$IX) {
  $segs = []; foreach (db()->query('SELECT code,name FROM itc_sup_seg')->fetchAll() as $g) $segs[$g['code']] = $g['name'];
  $web = [];
  foreach (db()->query('SELECT sup_id,data FROM itc_sup_web')->fetchAll() as $w) {
    $d = sup_dec($w['data']); $gn = []; $items = [];
    foreach (($d['groups'] ?? []) as $g) {
      $gn[] = (string)($g['name'] ?? '');
      foreach (($g['items'] ?? []) as $it) $items[] = (string)($it['name'] ?? '');
    }
    $web[$w['sup_id']] = [$gn, $items];
  }
  $IX = ['segs' => $segs, 'rows' => []];
  foreach (db()->query('SELECT id,name,full_name,seg,seg2,city,kind,what,products FROM itc_sup WHERE deleted=0')->fetchAll() as $r) {
    $prod = array_values(array_filter(array_map('strval', sup_dec($r['products']))));
    [$gn, $items] = $web[$r['id']] ?? [[], []];
    $head = mb_strtolower($r['name'] . ' ' . $r['full_name']);
    $mid = mb_strtolower(implode(' ', $prod) . ' ' . implode(' ', $gn));
    $tail = mb_strtolower(mb_substr((string)$r['what'], 0, 1500) . ' ' . mb_substr(implode(' ', $items), 0, 3000));
    $IX['rows'][] = [
      'id' => $r['id'], 'seg' => [$r['seg'], $r['seg2']], 'h' => $head, 'm' => $mid, 't' => $tail,
      // короткая карточка для DeepSeek
      'c' => $r['name'] . ' | ' . $r['city'] . ' | ' . ($r['kind'] ?: 'тип не указан') . ' | разделы: '
           . trim(($segs[$r['seg']] ?? $r['seg']) . ($r['seg2'] ? ', ' . ($segs[$r['seg2']] ?? $r['seg2']) : ''))
           . ' | товары: ' . mb_substr(implode(', ', array_slice(array_merge($prod, $gn), 0, 14)), 0, 260)
           . ($r['what'] ? ' | ' . mb_substr(preg_replace('/\s+/u', ' ', (string)$r['what']), 0, 160) : ''),
    ];
  }
  itcai_cache_put('ix', $ixKey, $IX);
}

/* ---------- готовый ответ из кэша ---------- */
$qn = mb_strtolower(preg_replace('/\s+/u', ' ', $q));
$ansKey = md5('ans1|' . $ixKey . '|' . $qn);
if ($c = itcai_cache_get('ans', $ansKey, 86400)) jout(['ok' => true, 'cached' => true] + $c);

/* ---------- 1) разбор запроса ---------- */
$exKey = md5('ex1|' . $qn);
$EX = itcai_cache_get('ex', $exKey, 86400 * 7);
if (!$EX) {
  $segList = []; foreach ($IX['segs'] as $k => $v) $segList[] = "$k: $v";
  $sys = "Ты помогаешь менеджеру по закупкам в Китае искать фабрики в базе поставщиков. "
       . "Разбери запрос и верни ТОЛЬКО JSON без пояснений:\n"
       . "{\"kw\": [...], \"segs\": [...], \"rel\": [...], \"rel_segs\": [...], \"rel_note\": \"...\"}\n"
       . "kw — 10–25 КОРНЕЙ слов (без окончаний, нижний регистр) для поиска фабрик, которые делают именно это: "
       . "русские синонимы и родственные слова, английские слова, китайские иероглифы. Пример для «халаты для гостиниц»: "
       . "[\"халат\",\"махров\",\"банн\",\"bathrobe\",\"robe\",\"浴袍\",\"гостиничн\",\"hotel\",\"текстил\"].\n"
       . "segs — коды подходящих разделов базы из списка ниже.\n"
       . "rel — 8–15 корней слов для СМЕЖНЫХ товаров: что обычно закупают вместе с этим или делают на похожих производствах "
       . "(для халатов: полотенц, тапоч, постельн, простын, наволоч, одеял, подушк, towel, slipper, bedding).\n"
       . "rel_segs — коды разделов для смежных товаров. rel_note — 3–6 слов, что считаем смежным.\n"
       . "Разделы базы:\n" . implode("\n", $segList);
  $EX = itcai_json(itcai_ask($sys, $q, 700, 25, true));
  if (!$EX) fail('Умный поиск сейчас не отвечает (' . itcai_err_text() . '). Попробуйте через минуту.');
  itcai_cache_put('ex', $exKey, $EX);
}
$clean = function ($a) { $o = []; foreach ((array)$a as $w) { $w = mb_strtolower(trim((string)$w)); if (mb_strlen($w) >= 2 && mb_strlen($w) <= 40) $o[] = $w; } return array_slice(array_values(array_unique($o)), 0, 30); };
$kw = $clean($EX['kw'] ?? []); $rel = $clean($EX['rel'] ?? []);
// слова самого запроса тоже ищем (корень — без последних букв)
foreach (preg_split('/[\s,.;:!?«»"()]+/u', $qn) as $w) if (mb_strlen($w) >= 4) $kw[] = mb_substr($w, 0, max(4, mb_strlen($w) - 2));
$kw = array_values(array_unique($kw));
$segs = array_map('strval', (array)($EX['segs'] ?? [])); $rsegs = array_map('strval', (array)($EX['rel_segs'] ?? []));

/* ---------- 2) отбор кандидатов по всей базе (без ИИ) ---------- */
$score = function ($r, $words, $sg) {
  $s = 0;
  foreach ($words as $w) {
    if (mb_strpos($r['h'], $w) !== false) $s += 6;
    if (mb_strpos($r['m'], $w) !== false) $s += 4;
    if (mb_strpos($r['t'], $w) !== false) $s += 1 + min(3, substr_count($r['t'], $w)) * 0.5;
  }
  if ($s > 0 && array_intersect($sg, $r['seg'])) $s += 3;
  return $s;
};
$main = []; $side = [];
foreach ($IX['rows'] as $i => $r) {
  $a = $score($r, $kw, $segs); if ($a > 0) $main[$i] = $a;
  $b = $score($r, $rel, $rsegs); if ($b > 0) $side[$i] = $b;
}
arsort($main); arsort($side);
$cand = array_slice(array_keys($main), 0, 28, true);
foreach (array_keys($side) as $i) { if (count($cand) >= 42) break; if (!in_array($i, $cand, true)) $cand[] = $i; }
if (!$cand) {
  $out = ['q' => $q, 'main' => [], 'related' => [], 'hint' => 'В базе нет фабрик под этот запрос. Попробуйте сказать иначе или шире.', 'rel_note' => ''];
  itcai_cache_put('ans', $ansKey, $out); jout(['ok' => true] + $out);
}

/* ---------- 3) DeepSeek выбирает и объясняет ---------- */
$lines = []; $ids = [];
foreach ($cand as $n => $i) { $r = $IX['rows'][$i]; $ids[$n + 1] = $r['id']; $lines[] = ($n + 1) . '. ' . $r['c']; }
$sys = "Ты опытный закупщик российской импортной компании ИТК, работаешь с фабриками Китая. Менеджер ищет поставщика. "
     . "Ниже пронумерованные кандидаты из нашей базы (название | город | тип | разделы | товары | описание).\n"
     . "Выбери:\n"
     . "main — до 12 фабрик, которые ДЕЛАЮТ то, что ищут (сначала самые подходящие; прямые производители выше торговых компаний);\n"
     . "related — до 8 СМЕЖНЫХ фабрик (" . ($EX['rel_note'] ?? 'что берут вместе или похожее производство') . "), которых нет в main.\n"
     . "Для каждой — why: одна короткая строка по-русски, ЧТО именно у неё есть по запросу (без воды, до 90 знаков). "
     . "Не включай тех, кто явно не подходит. hint — одна строка-совет менеджеру, что уточнить у фабрик (или пустая строка).\n"
     . "Ответ ТОЛЬКО JSON: {\"main\":[{\"n\":1,\"why\":\"...\"}],\"related\":[{\"n\":5,\"why\":\"...\"}],\"hint\":\"...\"}";
$R = itcai_json(itcai_ask($sys, "Запрос: $q\n\nКандидаты:\n" . implode("\n", $lines), 1500, 40, true));
if (!$R) fail('Умный поиск сейчас не отвечает (' . itcai_err_text() . '). Попробуйте через минуту.');
$pick = function ($arr, $max, $skip) use ($ids) {
  $o = []; $seen = $skip;
  foreach ((array)$arr as $x) {
    $n = (int)($x['n'] ?? 0); if (!isset($ids[$n]) || isset($seen[$ids[$n]])) continue;
    $seen[$ids[$n]] = 1; $o[] = ['id' => $ids[$n], 'why' => mb_substr(trim((string)($x['why'] ?? '')), 0, 140)];
    if (count($o) >= $max) break;
  }
  return $o;
};
$m = $pick($R['main'] ?? [], 12, []);
$skip = []; foreach ($m as $x) $skip[$x['id']] = 1;
$out = ['q' => $q, 'main' => $m, 'related' => $pick($R['related'] ?? [], 8, $skip),
        'hint' => mb_substr(trim((string)($R['hint'] ?? '')), 0, 200), 'rel_note' => (string)($EX['rel_note'] ?? '')];
itcai_cache_put('ans', $ansKey, $out);
jout(['ok' => true] + $out);
