<?php
// ==== ИТК · pr_lib.php · №492 (12.11.2026) · Прайсы поставщиков → магазин (CRM ИТК) ====
// Менеджер заводит прайс (поставщик, валюта, раздел магазина), загружает файл — Excel, CSV, PDF или фото.
// Система находит в нём артикул, название, цену, остаток (ИИ), считает цену в магазине по формуле
// и выкладывает товары. Новый прайс того же поставщика: цены пересчитываются, позиций, которых
// нет в прайсе или нет в наличии, — скрываются в магазине (не удаляются), вернувшиеся — снова видны.
// Формула по умолчанию: цена × курс + 3% перевод + доставка + наценка 20% + НДС 22%, округление вверх.
// Цены таких товаров магазин сам не пересчитывает (объём в карточке 0 — общий пересчёт их не трогает).
// Остаток поставщика (он в Китае) в карточку магазина не пишется — по нему товар только показывается или скрывается.

const PR_DEF = ['pay' => 3, 'ship_mode' => 'pct', 'ship_pct' => 10, 'ship_kg' => 0, 'ship_m3' => 0, 'markup' => 20, 'vat' => 22, 'round' => 10];
const PR_CUR = ['CNY' => 'юань', 'USD' => 'доллар', 'RUB' => 'рубль'];

function pr_ensure() {
  static $ok = false; if ($ok) return; $ok = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_pr_list (
    id VARCHAR(30) NOT NULL PRIMARY KEY, name VARCHAR(190) NOT NULL, supplier VARCHAR(190) NOT NULL DEFAULT '', cur VARCHAR(3) NOT NULL DEFAULT 'CNY',
    rate DECIMAL(12,4) NOT NULL DEFAULT 0, formula TEXT NULL, cat VARCHAR(190) NOT NULL DEFAULT '', subcat VARCHAR(190) NOT NULL DEFAULT '',
    hide_zero TINYINT NOT NULL DEFAULT 1, last_file VARCHAR(250) NOT NULL DEFAULT '', last_at DATETIME NULL, last_by VARCHAR(190) NOT NULL DEFAULT '',
    created_by VARCHAR(190) NOT NULL DEFAULT '', created_phone VARCHAR(40) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted TINYINT NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_pr_item (
    id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY, list_id VARCHAR(30) NOT NULL, k VARCHAR(190) NOT NULL, sku VARCHAR(120) NOT NULL DEFAULT '',
    name VARCHAR(400) NOT NULL DEFAULT '', price DECIMAL(14,4) NOT NULL DEFAULT 0, stock INT NULL, unit VARCHAR(30) NOT NULL DEFAULT '',
    weight DECIMAL(10,3) NOT NULL DEFAULT 0, vol DECIMAL(10,4) NOT NULL DEFAULT 0, descr TEXT NULL, img VARCHAR(600) NOT NULL DEFAULT '',
    price_rub INT NOT NULL DEFAULT 0, prev_rub INT NOT NULL DEFAULT 0, product_id VARCHAR(64) NOT NULL DEFAULT '',
    st VARCHAR(6) NOT NULL DEFAULT 'new', off_manual TINYINT NOT NULL DEFAULT 0, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pri (list_id, k), KEY idx_pri_p (product_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  db()->exec("CREATE TABLE IF NOT EXISTS itc_pr_parse (
    id VARCHAR(30) NOT NULL PRIMARY KEY, list_id VARCHAR(30) NOT NULL, name VARCHAR(250) NOT NULL DEFAULT '', mode VARCHAR(6) NOT NULL DEFAULT 'text',
    chunks MEDIUMTEXT NULL, n INT NOT NULL DEFAULT 0, done INT NOT NULL DEFAULT 0, items MEDIUMTEXT NULL, cur VARCHAR(3) NOT NULL DEFAULT '',
    created_by VARCHAR(190) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- формула ---------- */
function pr_fdef() { $j = json_decode(sp_get('pr_formula'), true); return array_merge(PR_DEF, is_array($j) ? array_intersect_key($j, PR_DEF) : []); }
function pr_fclean($f) {
  $o = []; foreach (PR_DEF as $k => $d) { if (!isset($f[$k]) || $f[$k] === '') continue; $o[$k] = $k === 'ship_mode' ? (in_array($f[$k], ['pct', 'kg', 'm3'], true) ? $f[$k] : 'pct') : max(0, min(100000, (float)$f[$k])); }
  if (isset($o['round'])) $o['round'] = max(1, (int)$o['round']); return $o;
}
function pr_formula($l) { $j = json_decode((string)($l['formula'] ?? ''), true); return array_merge(pr_fdef(), is_array($j) ? pr_fclean($j) : []); }
function pr_rates() {
  static $r = null; if ($r !== null) return $r; $r = ['RUB' => 1.0, 'CNY' => 0.0, 'USD' => 0.0];
  try { foreach (db()->query("SELECT k,v FROM settings WHERE k IN ('kurs_cny','kurs_usd')")->fetchAll() as $x) $r[$x['k'] === 'kurs_cny' ? 'CNY' : 'USD'] = (float)$x['v']; } catch (Throwable $e) {}
  if ($r['CNY'] <= 0) $r['CNY'] = 12.5; if ($r['USD'] <= 0) $r['USD'] = 90; return $r;
}
function pr_rate($l) { return (float)$l['rate'] > 0 ? (float)$l['rate'] : (pr_rates()[$l['cur']] ?? 1); }
function pr_calc($l, $it, $f = null) {
  $f = $f ?: pr_formula($l); $p = (float)$it['price']; if ($p <= 0) return 0;
  $base = $p * pr_rate($l);
  $ship = $base * $f['ship_pct'] / 100;
  if ($f['ship_mode'] === 'kg' && (float)$it['weight'] > 0) $ship = (float)$it['weight'] * $f['ship_kg'];
  if ($f['ship_mode'] === 'm3' && (float)$it['vol'] > 0) $ship = (float)$it['vol'] * $f['ship_m3'];
  $rub = ($base * (1 + $f['pay'] / 100) + $ship) * (1 + $f['markup'] / 100) * (1 + $f['vat'] / 100);
  $r = max(1, (int)$f['round']); return (int)(ceil($rub / $r) * $r);
}
function pr_ftext($f) {
  return 'цена × курс + ' . rtrim(rtrim(number_format($f['pay'], 2, '.', ''), '0'), '.') . '% перевод + доставка '
    . ($f['ship_mode'] === 'kg' ? $f['ship_kg'] . ' ₽/кг' : ($f['ship_mode'] === 'm3' ? $f['ship_m3'] . ' ₽/м³' : $f['ship_pct'] . '%'))
    . ' → наценка ' . $f['markup'] . '% → НДС ' . $f['vat'] . '% → вверх до ' . $f['round'] . ' ₽';
}

/* ---------- доступ ---------- */
function pr_list_get($me, $id) {
  pr_ensure(); $q = db()->prepare('SELECT * FROM itc_pr_list WHERE id=? AND deleted=0'); $q->execute([(string)$id]); $l = $q->fetch();
  if (!$l) throw new RuntimeException('Прайс не найден'); return $l;
}
function pr_can_edit($me, $l) { return $me['boss'] || $l['created_phone'] === $me['phone'] || $l['created_phone'] === ''; }

/* ---------- чтение файлов ---------- */
function pr_col_n($c) { $n = 0; foreach (str_split(strtoupper($c)) as $ch) $n = $n * 26 + (ord($ch) - 64); return $n; }
function pr_media_dir() { $d = dirname(__DIR__) . '/uploads/pr'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function pr_img_save($bin) {   // фото из прайса → JPEG до 1000 точек в /uploads/pr
  if (!function_exists('imagecreatefromstring')) return '';
  $im = @imagecreatefromstring($bin); if (!$im) return '';
  $w = imagesx($im); $h = imagesy($im); if ($w < 40 || $h < 40) { imagedestroy($im); return ''; }
  $k = min(1, 1000 / max($w, $h)); $nw = max(1, (int)($w * $k)); $nh = max(1, (int)($h * $k));
  $t = imagecreatetruecolor($nw, $nh); imagefill($t, 0, 0, imagecolorallocate($t, 255, 255, 255)); imagecopyresampled($t, $im, 0, 0, 0, 0, $nw, $nh, $w, $h); imagedestroy($im);
  $fn = md5($bin) . '.jpg'; $path = pr_media_dir() . '/' . $fn;
  if (!is_file($path)) imagejpeg($t, $path, 85); imagedestroy($t);
  return '/uploads/pr/' . $fn;
}
/* Excel .xlsx: строки с буквами столбцов и картинки, привязанные к строкам */
function pr_xlsx($bin) {
  $tmp = tempnam(sys_get_temp_dir(), 'prx'); file_put_contents($tmp, $bin); $z = new ZipArchive(); $rows = []; $imgs = [];
  if ($z->open($tmp) !== true) { @unlink($tmp); throw new RuntimeException('Файл Excel не открылся — сохраните его заново как .xlsx'); }
  $ss = [];
  if (($s = $z->getFromName('xl/sharedStrings.xml')) !== false && preg_match_all('~<si>(.*?)</si>~s', $s, $m))
    foreach ($m[1] as $si) { preg_match_all('~<t[^>]*>(.*?)</t>~s', $si, $tt); $ss[] = html_entity_decode(implode('', $tt[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'); }
  /* первый лист, где больше всего строк */
  $best = null; $bestN = 0;
  for ($i = 1; $i <= 12; $i++) { $x = $z->getFromName("xl/worksheets/sheet$i.xml"); if ($x === false) break; $n = substr_count($x, '<row'); if ($n > $bestN) { $best = $i; $bestN = $n; } }
  if ($best === null) { $z->close(); @unlink($tmp); throw new RuntimeException('В файле Excel нет листов'); }
  $x = $z->getFromName("xl/worksheets/sheet$best.xml");
  preg_match_all('~<row[^>]*?r="(\d+)"[^>]*>(.*?)</row>~s', $x, $rr, PREG_SET_ORDER);
  foreach (array_slice($rr, 0, 6000) as $row) {
    preg_match_all('~<c r="([A-Z]+)\d+"([^>]*?)(?:/>|>(.*?)</c>)~s', $row[2], $cs, PREG_SET_ORDER); $cells = [];
    foreach ($cs as $c) {
      $v = ''; $in = $c[3] ?? '';
      if (preg_match('~<v>(.*?)</v>~s', $in, $vv)) $v = $vv[1]; elseif (preg_match_all('~<t[^>]*>(.*?)</t>~s', $in, $vv)) $v = implode('', $vv[1]);
      if (strpos($c[2], 't="s"') !== false) $v = $ss[(int)$v] ?? '';
      $v = trim(html_entity_decode(strip_tags((string)$v), ENT_QUOTES | ENT_XML1, 'UTF-8')); if ($v !== '') $cells[$c[1]] = mb_substr($v, 0, 600);
    }
    if ($cells) $rows[(int)$row[1]] = $cells;
  }
  /* картинки: лист → drawing → media, по номеру строки якоря (с приставкой xdr: и без) */
  $rel = $z->getFromName("xl/worksheets/_rels/sheet$best.xml.rels");
  if ($rel !== false && preg_match('~Target="(?:/xl/|\.\./)drawings/(drawing\d+\.xml)"~', $rel, $dm)) {
    $dx = (string)$z->getFromName('xl/drawings/' . $dm[1]); $drel = (string)$z->getFromName('xl/drawings/_rels/' . $dm[1] . '.rels'); $map = [];
    if (preg_match_all('~<Relationship\b[^>]*>~', $drel, $rl)) foreach ($rl[0] as $t) if (preg_match('~Id="([^"]+)"~', $t, $ii) && preg_match('~Target="(?:/xl/|\.\./)media/([^"]+)"~', $t, $tt)) $map[$ii[1]] = $tt[1];
    if (preg_match_all('~<(?:xdr:)?(twoCellAnchor|oneCellAnchor)\b.*?</(?:xdr:)?\1>~s', $dx, $an)) {
      $cnt = 0;
      foreach ($an[0] as $a) {
        if ($cnt >= 1500) break;
        if (!preg_match('~<(?:xdr:)?from>.*?<(?:xdr:)?row>(\d+)</(?:xdr:)?row>~s', $a, $ro) || !preg_match('~r:embed="([^"]+)"~', $a, $em) || !isset($map[$em[1]])) continue;
        $r1 = (int)$ro[1] + 1;
        if (!isset($rows[$r1]) && preg_match('~<(?:xdr:)?to>.*?<(?:xdr:)?row>(\d+)</(?:xdr:)?row>~s', $a, $rt)) { $r2 = (int)$rt[1] + 1; for ($q = $r1; $q <= min($r2, $r1 + 3); $q++) if (isset($rows[$q])) { $r1 = $q; break; } }
        if (isset($imgs[$r1])) continue;
        $b = $z->getFromName('xl/media/' . $map[$em[1]]); if ($b === false || strlen($b) > 8 * 1048576) continue;
        $u = pr_img_save($b); if ($u !== '') { $imgs[$r1] = $u; $cnt++; }
      }
    }
  }
  $z->close(); @unlink($tmp);
  return [$rows, $imgs];
}
function pr_csv($bin) {
  $t = mb_check_encoding($bin, 'UTF-8') ? $bin : mb_convert_encoding($bin, 'UTF-8', 'Windows-1251');
  $t = preg_replace('~^\xEF\xBB\xBF~', '', $t); $lines = preg_split("~\r?\n~", $t); $sep = substr_count($lines[0] ?? '', ';') >= substr_count($lines[0] ?? '', ',') ? ';' : ',';
  $rows = []; $i = 0;
  foreach ($lines as $ln) { $i++; if (trim($ln) === '') continue; $c = str_getcsv($ln, $sep); $r = [];
    foreach ($c as $j => $v) { $v = trim($v); if ($v !== '') { $col = ''; $n = $j + 1; while ($n > 0) { $m = ($n - 1) % 26; $col = chr(65 + $m) . $col; $n = (int)(($n - $m - 1) / 26); } $r[$col] = mb_substr($v, 0, 600); } }
    if ($r) $rows[$i] = $r; if (count($rows) >= 6000) break; }
  return [$rows, []];
}
function pr_num($v) {
  $v = str_replace([' ', "\xC2\xA0", '¥', '$', '₽', 'руб', 'RMB', 'CNY', 'USD', '元'], '', (string)$v);
  if (preg_match('~^-?\d{1,3}(\.\d{3})+,\d+$~', $v)) $v = str_replace(['.', ','], ['', '.'], $v);
  $v = str_replace(',', '.', $v); return preg_match('~-?\d+(\.\d+)?~', $v, $m) ? (float)$m[0] : 0.0;
}
function pr_key($sku, $name) { $s = mb_strtolower(trim($sku)); if ($s !== '') return mb_substr('a:' . $s, 0, 190); return mb_substr('n:' . preg_replace('~\s+~u', ' ', mb_strtolower(trim($name))), 0, 190); }

/* ---------- ИИ: столбцы таблицы / позиции из текста ---------- */
function pr_ai_cols($rows) {
  $smp = []; $n = 0;
  foreach ($rows as $r => $cells) { $p = []; foreach ($cells as $c => $v) $p[] = $c . '=' . mb_substr($v, 0, 80); $smp[] = 'Строка ' . $r . ': ' . implode('; ', $p); if (++$n >= 30) break; }
  $sys = 'Ты разбираешь прайс-лист поставщика (таблица Excel). Найди строку заголовков и столбцы. '
    . 'Ответ строго JSON: {"head":номер строки заголовков или 0,"sku":"буква столбца артикула/модели или пусто","name":"буква названия товара","price":"буква цены за единицу (если цен несколько — оптовая/самая крупная партия, без доставки)",'
    . '"stock":"буква остатка/наличия или пусто","unit":"буква единицы или пусто","weight":"буква веса кг или пусто","vol":"буква объёма м³ или пусто","descr":"буква описания/характеристик или пусто",'
    . '"img":"буква ссылки на фото или пусто","extra":["буквы других полезных столбцов: мощность, размер, цвет"],"cur":"CNY|USD|RUB — валюта цен"}';
  $j = fl_ai($sys, implode("\n", $smp), 800, true);
  if (!is_array($j) || empty($j['name']) || empty($j['price'])) throw new RuntimeException('ИИ не смог понять таблицу: не нашёл столбцы названия и цены. ' . ($GLOBALS['FL_AI_ERR'] ?? ''));
  return $j;
}
function pr_ai_text($chunk) {
  $sys = 'Ты выписываешь товарные позиции из прайс-листа поставщика (текст из PDF или фото, строки могут быть перемешаны). '
    . 'Бери только товары с ценой. Название — как в прайсе, с ключевыми характеристиками (мощность, объём, размер). Цена — за единицу, число без валюты. '
    . 'Ответ строго JSON: {"cur":"CNY|USD|RUB","items":[{"sku":"артикул/модель или пусто","name":"название","price":число,"stock":число или null,"unit":"шт","weight":число кг или 0,"descr":"характеристики коротко"}]}';
  $j = fl_ai($sys, $chunk, 6000, true);
  if (!is_array($j)) return null;
  return $j;
}

/* ---------- разбор загруженного файла ---------- */
function pr_parse_start($me, $l, $a) {
  $name = fl_s($a['name'] ?? 'прайс', 250); $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); $id = fl_id('pp');
  if (in_array($ext, ['xlsx', 'csv'], true)) {
    $bin = base64_decode((string)($a['data'] ?? ''), true); if ($bin === false || $bin === '') throw new RuntimeException('Файл не дошёл — попробуйте ещё раз');
    if (strlen($bin) > 25 * 1048576) throw new RuntimeException('Файл больше 25 МБ');
    [$rows, $imgs] = $ext === 'xlsx' ? pr_xlsx($bin) : pr_csv($bin);
    if (!$rows) throw new RuntimeException('В файле не нашлось строк');
    $m = pr_ai_cols($rows); $head = (int)($m['head'] ?? 0); $items = [];
    $g = function ($cells, $k) use ($m) { $c = strtoupper(trim((string)($m[$k] ?? ''))); return $c !== '' ? (string)($cells[$c] ?? '') : ''; };
    foreach ($rows as $r => $cells) {
      if ($r <= $head) continue;
      $nm = trim($g($cells, 'name')); $pr = pr_num($g($cells, 'price')); if ($nm === '' || $pr <= 0) continue;
      $ex = []; foreach ((array)($m['extra'] ?? []) as $c) { $c = strtoupper(trim((string)$c)); if ($c !== '' && isset($cells[$c]) && isset($rows[$head][$c])) $ex[] = $rows[$head][$c] . ': ' . $cells[$c]; }
      $st = $g($cells, 'stock'); $img = $imgs[$r] ?? ''; $iu = trim($g($cells, 'img')); if ($img === '' && preg_match('~^https?://~', $iu)) $img = $iu;
      $items[] = ['sku' => mb_substr(trim($g($cells, 'sku')), 0, 120), 'name' => mb_substr($nm, 0, 400), 'price' => $pr, 'stock' => $st === '' ? null : (int)pr_num($st),
                  'unit' => mb_substr(trim($g($cells, 'unit')), 0, 30), 'weight' => pr_num($g($cells, 'weight')), 'vol' => pr_num($g($cells, 'vol')),
                  'descr' => mb_substr(trim(implode("\n", array_filter([trim($g($cells, 'descr')), implode("\n", $ex)]))), 0, 3000), 'img' => $img];
    }
    if (!$items) throw new RuntimeException('Не нашлось ни одной строки с названием и ценой');
    $cur = strtoupper((string)($m['cur'] ?? '')); if (!isset(PR_CUR[$cur])) $cur = '';
    db()->prepare('INSERT INTO itc_pr_parse (id,list_id,name,mode,n,done,items,cur,created_by) VALUES (?,?,?,?,1,1,?,?,?)')->execute([$id, $l['id'], $name, 'table', json_encode($items, JSON_UNESCAPED_UNICODE), $cur, $me['name']]);
    return ['ok' => true, 'pid' => $id, 'n' => 1, 'done' => 1, 'count' => count($items), 'photos' => count($imgs)];
  }
  /* PDF, фото, Word: текст уже прочитан (как для Фила) — режем на куски для ИИ */
  $fid = (string)($a['file_id'] ?? ''); $q = db()->prepare('SELECT text FROM itc_fc_file WHERE id=? AND phone=?'); $q->execute([$fid, $me['phone']]); $text = (string)$q->fetchColumn();
  if (trim($text) === '') throw new RuntimeException('Текст файла не прочитался');
  $lines = preg_split("~\n~", $text); $chunks = []; $cur = '';
  foreach ($lines as $ln) { if (mb_strlen($cur) + mb_strlen($ln) > 4500 && $cur !== '') { $chunks[] = $cur; $cur = ''; } $cur .= $ln . "\n"; }
  if (trim($cur) !== '') $chunks[] = $cur; $chunks = array_slice($chunks, 0, 60);
  db()->prepare('INSERT INTO itc_pr_parse (id,list_id,name,mode,chunks,n,done,items,created_by) VALUES (?,?,?,?,?,?,0,?,?)')->execute([$id, $l['id'], $name, 'text', json_encode($chunks, JSON_UNESCAPED_UNICODE), count($chunks), '[]', $me['name']]);
  return ['ok' => true, 'pid' => $id, 'n' => count($chunks), 'done' => 0, 'count' => 0];
}
function pr_parse_get($me, $pid) { $q = db()->prepare('SELECT * FROM itc_pr_parse WHERE id=?'); $q->execute([(string)$pid]); $p = $q->fetch(); if (!$p) throw new RuntimeException('Разбор не найден — загрузите файл заново'); return $p; }
function pr_parse_step($me, $pid) {
  $p = pr_parse_get($me, $pid); if ((int)$p['done'] >= (int)$p['n']) return ['ok' => true, 'done' => (int)$p['done'], 'n' => (int)$p['n'], 'count' => count(json_decode($p['items'], true) ?: [])];
  $chunks = json_decode($p['chunks'], true) ?: []; $i = (int)$p['done']; $items = json_decode($p['items'], true) ?: []; $cur = $p['cur'];
  $j = pr_ai_text((string)($chunks[$i] ?? ''));
  if ($j === null) $j = pr_ai_text((string)($chunks[$i] ?? ''));   // вторая попытка
  if (is_array($j)) {
    $c = strtoupper((string)($j['cur'] ?? '')); if ($cur === '' && isset(PR_CUR[$c])) $cur = $c;
    foreach ((array)($j['items'] ?? []) as $x) { $nm = trim((string)($x['name'] ?? '')); $pr = pr_num($x['price'] ?? 0); if ($nm === '' || $pr <= 0) continue;
      $items[] = ['sku' => mb_substr(trim((string)($x['sku'] ?? '')), 0, 120), 'name' => mb_substr($nm, 0, 400), 'price' => $pr, 'stock' => isset($x['stock']) && $x['stock'] !== null && $x['stock'] !== '' ? (int)pr_num($x['stock']) : null,
                  'unit' => mb_substr((string)($x['unit'] ?? ''), 0, 30), 'weight' => pr_num($x['weight'] ?? 0), 'vol' => 0, 'descr' => mb_substr((string)($x['descr'] ?? ''), 0, 3000), 'img' => '']; }
  }
  db()->prepare('UPDATE itc_pr_parse SET done=?, items=?, cur=? WHERE id=?')->execute([$i + 1, json_encode($items, JSON_UNESCAPED_UNICODE), $cur, $p['id']]);
  return ['ok' => true, 'done' => $i + 1, 'n' => (int)$p['n'], 'count' => count($items), 'miss' => is_array($j) ? 0 : 1];
}
/* что изменится: новые, цена изменилась, пропали */
function pr_parse_preview($me, $pid) {
  $p = pr_parse_get($me, $pid); $l = pr_list_get($me, $p['list_id']); $items = json_decode($p['items'], true) ?: []; $f = pr_formula($l);
  if ($p['cur'] !== '' && $p['cur'] !== $l['cur']) $l['cur_hint'] = $p['cur'];
  $old = []; $q = db()->prepare('SELECT k,price,price_rub,st,stock FROM itc_pr_item WHERE list_id=?'); $q->execute([$l['id']]); foreach ($q->fetchAll() as $x) $old[$x['k']] = $x;
  $seen = []; $rows = []; $nNew = 0; $nUp = 0; $nDown = 0; $nZero = 0;
  foreach ($items as $it) {
    $k = pr_key($it['sku'], $it['name']); if (isset($seen[$k])) continue; $seen[$k] = 1;
    $rub = pr_calc($l, $it, $f); $o = $old[$k] ?? null; $ch = $o ? ($rub > (int)$o['price_rub'] ? 'up' : ($rub < (int)$o['price_rub'] ? 'down' : '')) : 'new';
    if ($ch === 'new') $nNew++; elseif ($ch === 'up') $nUp++; elseif ($ch === 'down') $nDown++;
    $zero = $l['hide_zero'] && $it['stock'] !== null && (int)$it['stock'] <= 0; if ($zero) $nZero++;
    $rows[] = ['sku' => $it['sku'], 'name' => $it['name'], 'price' => $it['price'], 'stock' => $it['stock'], 'rub' => $rub, 'was' => $o ? (int)$o['price_rub'] : 0, 'ch' => $ch, 'zero' => $zero, 'img' => $it['img']];
  }
  $gone = 0; foreach ($old as $k => $x) if (!isset($seen[$k]) && $x['st'] === 'on') $gone++;
  return ['ok' => true, 'pid' => $p['id'], 'file' => $p['name'], 'cur' => $l['cur'], 'cur_file' => $p['cur'], 'rate' => pr_rate($l), 'formula' => pr_ftext($f),
          'total' => count($rows), 'new' => $nNew, 'up' => $nUp, 'down' => $nDown, 'zero' => $nZero, 'gone' => $gone, 'rows' => array_slice($rows, 0, 400)];
}

/* ---------- запись в магазин ---------- */
function pr_own_supplier() { try { $r = db()->query('SELECT id FROM suppliers WHERE is_own=1 ORDER BY created_at LIMIT 1')->fetchColumn(); return $r ?: ''; } catch (Throwable $e) { return ''; } }
function pr_shop_put($l, $it) {   // создать или обновить карточку в магазине; вернуть id товара
  $rub = (int)$it['price_rub']; $cny = $l['cur'] === 'CNY' ? (int)round((float)$it['price']) : (int)round((float)$it['price'] * pr_rate($l) / max(0.01, pr_rates()['CNY']));
  $pid = (string)$it['product_id'];
  if ($pid !== '') {
    $q = db()->prepare('SELECT id FROM products WHERE id=?'); $q->execute([$pid]);
    if ($q->fetchColumn()) {
      db()->prepare('UPDATE products SET price=?, goods_cny=?, stock=?, blocked=0 WHERE id=?')->execute([$rub, $cny, 0, $pid]);
      if ($it['img'] !== '') try { db()->prepare("UPDATE products SET img=? WHERE id=? AND (img IS NULL OR img='')")->execute([$it['img'], $pid]); } catch (Throwable $e) {}
      return $pid;
    }
  }
  $pid = 'p' . substr(md5(uniqid('', true) . $it['k']), 0, 12);
  $name = $it['name']; $descr = trim(($it['sku'] !== '' ? 'Артикул: ' . $it['sku'] . "\n" : '') . (string)$it['descr']);
  db()->prepare('INSERT INTO products (id,supplier_id,cat,subcat,name,price,old_price,goods_cny,volume_m3,descr,img,gallery,video,stock,warehouse_id,original) VALUES (?,?,?,?,?,?,0,?,0,?,?,?,\'\',?,\'\',1)')
    ->execute([$pid, pr_own_supplier(), $l['cat'] ?: 'Прочее', $l['subcat'], mb_substr($name, 0, 250), $rub, $cny, $descr, $it['img'], json_encode($it['img'] !== '' ? [$it['img']] : [], JSON_UNESCAPED_UNICODE), 0]);
  try { db()->prepare("UPDATE products SET mod_status='approved', blocked=0 WHERE id=?")->execute([$pid]); } catch (Throwable $e) {}
  if ((float)$it['weight'] > 0) try { db()->prepare('UPDATE products SET weight_kg=? WHERE id=?')->execute([(float)$it['weight'], $pid]); } catch (Throwable $e) {}
  try { db()->prepare('UPDATE products SET goods_cny_at=CURDATE() WHERE id=?')->execute([$pid]); } catch (Throwable $e) {}
  return $pid;
}
function pr_shop_hide($pid) { if ($pid !== '') try { db()->prepare('UPDATE products SET blocked=1 WHERE id=?')->execute([$pid]); } catch (Throwable $e) {} }

/* применить разобранный прайс: сохранить позиции, пересчитать цены, скрыть пропавшие; publish — сразу выложить */
function pr_apply($me, $pid, $publish) {
  $p = pr_parse_get($me, $pid); $l = pr_list_get($me, $p['list_id']); if (!pr_can_edit($me, $l)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
  $items = json_decode($p['items'], true) ?: []; $f = pr_formula($l); $seen = []; $o = ['new' => 0, 'upd' => 0, 'shown' => 0, 'hidden' => 0, 'shop' => 0];
  $get = db()->prepare('SELECT * FROM itc_pr_item WHERE list_id=? AND k=?');
  foreach ($items as $it) {
    $k = pr_key($it['sku'], $it['name']); if (isset($seen[$k])) continue; $seen[$k] = 1;
    $it['k'] = $k; $rub = pr_calc($l, $it, $f); $get->execute([$l['id'], $k]); $x = $get->fetch();
    $zero = $l['hide_zero'] && $it['stock'] !== null && (int)$it['stock'] <= 0;
    if (!$x) {
      db()->prepare('INSERT INTO itc_pr_item (list_id,k,sku,name,price,stock,unit,weight,vol,descr,img,price_rub,st) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$l['id'], $k, $it['sku'], $it['name'], $it['price'], $it['stock'], $it['unit'], $it['weight'], $it['vol'], $it['descr'], $it['img'], $rub, $zero ? 'zero' : 'new']);
      $o['new']++; $get->execute([$l['id'], $k]); $x = $get->fetch();
    } else {
      db()->prepare('UPDATE itc_pr_item SET sku=?, name=?, price=?, stock=?, unit=?, weight=IF(?>0,?,weight), vol=IF(?>0,?,vol), descr=IF(?<>\'\',?,descr), img=IF(?<>\'\',?,img), prev_rub=price_rub, price_rub=?, updated_at=NOW() WHERE id=?')
        ->execute([$it['sku'], $it['name'], $it['price'], $it['stock'], $it['unit'], $it['weight'], $it['weight'], $it['vol'], $it['vol'], $it['descr'], $it['descr'], $it['img'], $it['img'], $rub, $x['id']]);
      $o['upd']++; $get->execute([$l['id'], $k]); $x = $get->fetch();
    }
    /* в магазине: выложен раньше — обновить цену/показать; новый — выложить, если просили */
    if ($zero || $x['off_manual']) { if ($x['st'] === 'on') { pr_shop_hide($x['product_id']); $o['hidden']++; } db()->prepare("UPDATE itc_pr_item SET st=? WHERE id=?")->execute([$zero ? 'zero' : 'off', $x['id']]); continue; }
    if ($x['product_id'] !== '' || $publish) {
      $was = $x['st']; $pidS = pr_shop_put($l, $x); db()->prepare("UPDATE itc_pr_item SET product_id=?, st='on' WHERE id=?")->execute([$pidS, $x['id']]); $o['shop']++;
      if ($was !== 'on' && $was !== 'new') $o['shown']++;
    } elseif ($x['st'] !== 'new') db()->prepare("UPDATE itc_pr_item SET st='new' WHERE id=?")->execute([$x['id']]);
  }
  /* пропавшие из прайса — скрыть */
  $q = db()->prepare("SELECT id,k,product_id,st FROM itc_pr_item WHERE list_id=?"); $q->execute([$l['id']]);
  foreach ($q->fetchAll() as $x) if (!isset($seen[$x['k']]) && $x['st'] !== 'gone') { if ($x['st'] === 'on') { pr_shop_hide($x['product_id']); $o['hidden']++; } db()->prepare("UPDATE itc_pr_item SET st='gone' WHERE id=?")->execute([$x['id']]); }
  $cur = $p['cur'];
  db()->prepare('UPDATE itc_pr_list SET last_file=?, last_at=NOW(), last_by=? WHERE id=?')->execute([$p['name'], $me['name'], $l['id']]);
  db()->prepare('DELETE FROM itc_pr_parse WHERE id=? OR created_at < NOW() - INTERVAL 2 DAY')->execute([$p['id']]);
  return ['ok' => true] + $o;
}
/* пересчитать все цены прайса (после смены формулы, курса) и обновить магазин */
function pr_recalc($me, $l) {
  $f = pr_formula($l); $n = 0; $q = db()->prepare('SELECT * FROM itc_pr_item WHERE list_id=?'); $q->execute([$l['id']]);
  foreach ($q->fetchAll() as $x) { $rub = pr_calc($l, $x, $f); if ($rub === (int)$x['price_rub']) continue;
    db()->prepare('UPDATE itc_pr_item SET prev_rub=price_rub, price_rub=? WHERE id=?')->execute([$rub, $x['id']]); $n++;
    if ($x['st'] === 'on' && $x['product_id'] !== '') db()->prepare('UPDATE products SET price=? WHERE id=?')->execute([$rub, $x['product_id']]); }
  return $n;
}

/* ---------- запросы из CRM ---------- */
function pr_action($me, $action) {
  pr_ensure();
  try {
    switch ($action) {
      case 'pr_lists': {
        $rows = db()->query("SELECT l.*, (SELECT COUNT(*) FROM itc_pr_item i WHERE i.list_id=l.id) items, (SELECT COUNT(*) FROM itc_pr_item i WHERE i.list_id=l.id AND i.st='on') onshop FROM itc_pr_list l WHERE l.deleted=0 ORDER BY l.last_at IS NULL, l.last_at DESC, l.created_at DESC")->fetchAll();
        $cats = []; try { foreach (db()->query('SELECT name FROM categories ORDER BY sort, name')->fetchAll() as $c) $cats[] = $c['name']; } catch (Throwable $e) {}
        foreach ($rows as &$r) { $r['edit'] = pr_can_edit($me, $r); $r['ftext'] = pr_ftext(pr_formula($r)); $r['rate_now'] = pr_rate($r); $r['own'] = (string)$r['formula'] !== '' && $r['formula'] !== '[]' && $r['formula'] !== '{}'; } unset($r);
        fl_out(['ok' => true, 'lists' => $rows, 'def' => pr_fdef(), 'deftext' => pr_ftext(pr_fdef()), 'rates' => pr_rates(), 'boss' => (bool)$me['boss'], 'cats' => $cats, 'curs' => PR_CUR]);
      }
      case 'pr_list_save': {
        $id = (string)fl_in('id', ''); $old = $id !== '' ? pr_list_get($me, $id) : null;
        if ($old && !pr_can_edit($me, $old)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
        $name = fl_s(fl_in('name', ''), 190); if ($name === '') throw new RuntimeException('Впишите название прайса, например «SNADI — инверторы»');
        $cur = strtoupper((string)fl_in('cur', 'CNY')); if (!isset(PR_CUR[$cur])) $cur = 'CNY';
        $fo = fl_in('formula', null); $fj = is_array($fo) ? json_encode(pr_fclean($fo)) : ($old['formula'] ?? '');
        $v = [$name, fl_s(fl_in('supplier', ''), 190), $cur, max(0, (float)fl_in('rate', 0)), $fj, fl_s(fl_in('cat', ''), 190), fl_s(fl_in('subcat', ''), 190), fl_in('hide_zero', 1) ? 1 : 0];
        if ($old) { $v[] = $id; db()->prepare('UPDATE itc_pr_list SET name=?, supplier=?, cur=?, rate=?, formula=?, cat=?, subcat=?, hide_zero=? WHERE id=?')->execute($v);
          $l = pr_list_get($me, $id); $n = pr_recalc($me, $l);
          if ($old['cat'] !== $l['cat'] || $old['subcat'] !== $l['subcat']) { $q = db()->prepare("SELECT product_id FROM itc_pr_item WHERE list_id=? AND product_id<>''"); $q->execute([$id]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $p) db()->prepare('UPDATE products SET cat=?, subcat=? WHERE id=?')->execute([$l['cat'] ?: 'Прочее', $l['subcat'], $p]); }
          fl_out(['ok' => true, 'id' => $id, 'recalc' => $n]); }
        $id = fl_id('pl'); array_unshift($v, $id); $v[] = $me['name']; $v[] = $me['phone'];
        db()->prepare('INSERT INTO itc_pr_list (id,name,supplier,cur,rate,formula,cat,subcat,hide_zero,created_by,created_phone) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($v);
        fl_out(['ok' => true, 'id' => $id]);
      }
      case 'pr_list_del': {
        $l = pr_list_get($me, fl_in('id', '')); if (!pr_can_edit($me, $l)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
        $q = db()->prepare("SELECT product_id FROM itc_pr_item WHERE list_id=? AND st='on'"); $q->execute([$l['id']]); $n = 0; foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $p) { pr_shop_hide($p); $n++; }
        db()->prepare('UPDATE itc_pr_list SET deleted=1 WHERE id=?')->execute([$l['id']]); fl_out(['ok' => true, 'hidden' => $n]);
      }
      case 'pr_formula_save': {   // формула по умолчанию — руководитель
        if (!$me['boss']) throw new RuntimeException('Формулу по умолчанию меняет руководитель. Для одного прайса можно задать свою — в настройках прайса');
        $f = pr_fclean((array)fl_in('formula', [])); sp_put('pr_formula', json_encode(array_merge(PR_DEF, $f)), $me['name']);
        $n = 0; foreach (db()->query("SELECT * FROM itc_pr_list WHERE deleted=0 AND (formula IS NULL OR formula='' OR formula='[]' OR formula='{}')")->fetchAll() as $l) $n += pr_recalc($me, $l);
        fl_out(['ok' => true, 'recalc' => $n, 'text' => pr_ftext(pr_fdef())]);
      }
      case 'pr_items': {
        $l = pr_list_get($me, fl_in('id', '')); $st = (string)fl_in('st', ''); $qs = trim((string)fl_in('q', ''));
        $w = 'list_id=?'; $a = [$l['id']]; if (in_array($st, ['on', 'new', 'off', 'zero', 'gone'], true)) { $w .= ' AND st=?'; $a[] = $st; }
        if ($qs !== '') { $w .= ' AND (name LIKE ? OR sku LIKE ?)'; $a[] = '%' . $qs . '%'; $a[] = '%' . $qs . '%'; }
        $q = db()->prepare("SELECT id,sku,name,price,stock,unit,weight,img,price_rub,prev_rub,product_id,st,off_manual FROM itc_pr_item WHERE $w ORDER BY st='on' DESC, name LIMIT 500"); $q->execute($a);
        $c = []; $q2 = db()->prepare('SELECT st, COUNT(*) n FROM itc_pr_item WHERE list_id=? GROUP BY st'); $q2->execute([$l['id']]); foreach ($q2->fetchAll() as $x) $c[$x['st']] = (int)$x['n'];
        $l['ftext'] = pr_ftext(pr_formula($l)); $l['f'] = pr_formula($l); $l['rate_now'] = pr_rate($l); $l['edit'] = pr_can_edit($me, $l);
        fl_out(['ok' => true, 'list' => $l, 'items' => $q->fetchAll(), 'counts' => $c]);
      }
      case 'pr_parse_start': { $l = pr_list_get($me, fl_in('list', '')); if (!pr_can_edit($me, $l)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
        fl_out(pr_parse_start($me, $l, ['name' => fl_in('name', ''), 'data' => fl_in('data', ''), 'file_id' => fl_in('file_id', '')])); }
      case 'pr_parse_step': @set_time_limit(150); fl_out(pr_parse_step($me, fl_in('pid', '')));
      case 'pr_preview': fl_out(pr_parse_preview($me, fl_in('pid', '')));
      case 'pr_apply': @set_time_limit(300); fl_out(pr_apply($me, fl_in('pid', ''), (bool)fl_in('publish', 0)));
      case 'pr_publish': {   // выложить выбранные (или все новые) позиции
        @set_time_limit(300); $l = pr_list_get($me, fl_in('list', '')); if (!pr_can_edit($me, $l)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
        $ids = array_map('intval', (array)fl_in('ids', [])); $all = (bool)fl_in('all', 0); $n = 0;
        $q = db()->prepare("SELECT * FROM itc_pr_item WHERE list_id=? AND st IN ('new','off')"); $q->execute([$l['id']]);
        foreach ($q->fetchAll() as $x) { if (!$all && !in_array((int)$x['id'], $ids, true)) continue;
          $pid = pr_shop_put($l, $x); db()->prepare("UPDATE itc_pr_item SET product_id=?, st='on', off_manual=0 WHERE id=?")->execute([$pid, $x['id']]); $n++; }
        fl_out(['ok' => true, 'n' => $n]);
      }
      case 'pr_item_off': case 'pr_item_on': {
        $q = db()->prepare('SELECT * FROM itc_pr_item WHERE id=?'); $q->execute([(int)fl_in('id', 0)]); $x = $q->fetch(); if (!$x) throw new RuntimeException('Позиция не найдена');
        $l = pr_list_get($me, $x['list_id']); if (!pr_can_edit($me, $l)) throw new RuntimeException('Этот прайс ведёт другой сотрудник');
        if ($action === 'pr_item_off') { pr_shop_hide($x['product_id']); db()->prepare("UPDATE itc_pr_item SET st='off', off_manual=1 WHERE id=?")->execute([$x['id']]); fl_out(['ok' => true]); }
        $pid = pr_shop_put($l, $x); db()->prepare("UPDATE itc_pr_item SET product_id=?, st='on', off_manual=0 WHERE id=?")->execute([$pid, $x['id']]); fl_out(['ok' => true, 'product' => $pid]);
      }
      case 'pr_item_img': {
        $q = db()->prepare('SELECT * FROM itc_pr_item WHERE id=?'); $q->execute([(int)fl_in('id', 0)]); $x = $q->fetch(); if (!$x) throw new RuntimeException('Позиция не найдена');
        $u = fl_s(fl_in('url', ''), 600); if (!preg_match('~^(https?://|/)~', $u)) throw new RuntimeException('Фото не загрузилось');
        db()->prepare('UPDATE itc_pr_item SET img=? WHERE id=?')->execute([$u, $x['id']]);
        if ($x['product_id'] !== '') try { db()->prepare('UPDATE products SET img=?, gallery=? WHERE id=?')->execute([$u, json_encode([$u]), $x['product_id']]); } catch (Throwable $e) {}
        fl_out(['ok' => true]);
      }
    }
  } catch (RuntimeException $e) { fl_fail($e->getMessage()); }
  fl_fail('Неизвестное действие');
}

/* ---------- для Фила ---------- */
function pr_tools($me) {
  return [
    'price_lists' => ['{} — ПРАЙСЫ ПОСТАВЩИКОВ В МАГАЗИНЕ: список прайсов, сколько позиций выложено, когда обновлялись, формула цены',
      function ($a) use ($me) { pr_ensure(); $o = [];
        foreach (db()->query("SELECT l.*, (SELECT COUNT(*) FROM itc_pr_item i WHERE i.list_id=l.id AND i.st='on') onshop, (SELECT COUNT(*) FROM itc_pr_item i WHERE i.list_id=l.id) items FROM itc_pr_list l WHERE l.deleted=0")->fetchAll() as $l)
          $o[] = $l['name'] . ' · поставщик ' . ($l['supplier'] ?: '—') . ' · в магазине ' . $l['onshop'] . ' из ' . $l['items'] . ' · обновлён ' . ($l['last_at'] ?: 'ещё не загружали') . ' · формула: ' . pr_ftext(pr_formula($l));
        return $o ?: 'прайсов пока нет — их заводят в CRM ИТК → «Товары в магазин»'; }],
  ];
}
