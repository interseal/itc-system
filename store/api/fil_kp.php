<?php
/* ==== ИТК · fil_kp.php · №36 (05.11.2026) — Фил делает коммерческие предложения (PDF и Excel) ====
   Два вида:
   • coop — КП на сотрудничество: о производстве, цифры, награды, линейки товаров с фото, как мы работаем;
   • full — полный каталог: все модели с фото, описанием и характеристиками, чтобы клиент выбрал.
   Цен в базе нет — везде «по запросу». От нашей компании (по умолчанию ООО «ИТК»), клиент — только если назвали.
   Контакты фабрики в КП не попадают. Файлы лежат в uploads/fil_kp (закрыто), скачиваются через kp_dl по пропуску. */
require_once __DIR__ . '/kp_pdf.php';

function kp_dir($sub = '') {
  $d = dirname(__DIR__) . '/uploads/fil_kp' . ($sub ? '/' . $sub : '');
  if (!is_dir($d)) @mkdir($d, 0755, true);
  $r = dirname(__DIR__) . '/uploads/fil_kp';
  if (!is_file("$r/.htaccess")) @file_put_contents("$r/.htaccess", "Require all denied\nDeny from all\n");
  return $d;
}
function kp_ensure() {
  static $d = false; if ($d) return; $d = true;
  db()->exec("CREATE TABLE IF NOT EXISTS itc_fil_kp (id VARCHAR(40) NOT NULL PRIMARY KEY, phone VARCHAR(40) NOT NULL DEFAULT '', title VARCHAR(300) NOT NULL DEFAULT '',
    kind VARCHAR(10) NOT NULL DEFAULT '', sup_id VARCHAR(40) NOT NULL DEFAULT '', client VARCHAR(300) NOT NULL DEFAULT '', files VARCHAR(100) NOT NULL DEFAULT '',
    items INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_kp_phone (phone, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/* картинка по адресу → JPEG до 900 px (сначала ищем файл сайта на диске, потом качаем) */
function kp_img_local($u) {
  $p = parse_url((string)$u); $host = strtolower($p['host'] ?? ''); $path = rawurldecode($p['path'] ?? '');
  if ($path === '' || strpos($path, '..') !== false) return '';
  if (strpos($host, 'itcpartner.ru') !== false && function_exists('bk_site_root')) { $r = bk_site_root(); if ($r !== '' && is_file($r . $path)) return $r . $path; }
  if (strpos($host, 'itcpartner.store') !== false) { $f = dirname(__DIR__) . $path; if (is_file($f)) return $f; }
  return '';
}
function kp_jpeg($bin, $max = 900) {
  if (!function_exists('imagecreatefromstring')) return '';
  $im = @imagecreatefromstring($bin); if (!$im) return '';
  $w = imagesx($im); $h = imagesy($im); $k = min(1, $max / max($w, $h)); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
  $o = imagecreatetruecolor($nw, $nh); imagefill($o, 0, 0, imagecolorallocate($o, 255, 255, 255));
  imagecopyresampled($o, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
  ob_start(); imagejpeg($o, null, 82); $j = ob_get_clean(); imagedestroy($im); imagedestroy($o); return $j;
}
function kp_images($urls) {
  $out = []; $need = [];
  foreach (array_unique(array_filter($urls)) as $u) {
    $c = kp_dir('img') . '/' . md5($u) . '.jpg';
    if (is_file($c)) { $out[$u] = (string)file_get_contents($c); continue; }
    $l = kp_img_local($u);
    if ($l !== '') { $j = kp_jpeg((string)file_get_contents($l)); if ($j !== '') { @file_put_contents($c, $j); $out[$u] = $j; } continue; }
    if (preg_match('~^https?://~i', $u)) $need[] = $u;
  }
  foreach (array_chunk($need, 8) as $part) {
    $mh = curl_multi_init(); $hs = [];
    foreach ($part as $u) { $ch = curl_init($u); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true]); curl_multi_add_handle($mh, $ch); $hs[$u] = $ch; }
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1); } while ($run && $st == CURLM_OK);
    foreach ($hs as $u => $ch) { $b = (string)curl_multi_getcontent($ch); curl_multi_remove_handle($mh, $ch); curl_close($ch);
      if ($b !== '') { $j = kp_jpeg($b); if ($j !== '') { @file_put_contents(kp_dir('img') . '/' . md5($u) . '.jpg', $j); $out[$u] = $j; } } }
    curl_multi_close($mh);
  }
  return $out;
}
function kp_logo() {
  $own = __DIR__ . '/kp_fonts/itc-logo.jpg'; if (is_file($own)) return (string)file_get_contents($own);
  foreach ([dirname(__DIR__) . '/eco/itc-logo.png', (function_exists('bk_site_root') ? bk_site_root() : '') . '/img/logo.png'] as $f)
    if ($f !== '' && is_file($f)) { $j = kp_jpeg((string)file_get_contents($f), 600); if ($j !== '') return $j; }
  return '';
}
function kp_company($want) {
  $all = function_exists('co_all') ? co_all() : []; $want = mb_strtolower(trim((string)$want));
  $score = function ($c) use ($want) {
    $s = mb_strtolower(($c['short'] ?? '') . ' ' . ($c['name'] ?? '')); $n = 0;
    if ($want !== '') { if (mb_strpos($s, $want) !== false) $n += 10; }
    else { if (mb_strpos($s, 'итк') !== false || mb_strpos($s, 'интернациональная') !== false) $n += 3; if (mb_strpos($s, 'глобал') === false) $n += 2; if (preg_match('~росси~iu', (string)($c['country'] ?? '')) || preg_match('~^ооо~iu', trim((string)($c['short'] ?? '')))) $n += 2; }
    return $n;
  };
  usort($all, function ($a, $b) use ($score) { return $score($b) - $score($a); });
  $c = $all[0] ?? [];
  return ['short' => trim(preg_replace('~\s*·.*$~u', '', (string)($c['short'] ?? 'ООО «ИТК»'))), 'name' => (string)($c['name'] ?? 'ООО «Интернациональная Торговая Компания»'), 'inn' => (string)($c['inn'] ?? ''),
    'addr' => (string)($c['addr'] ?? ''), 'phone' => (string)($c['phone'] ?? ''), 'email' => (string)($c['email'] ?? ''), 'site' => (string)($c['site'] ?? 'itcpartner.ru')];
}
function kp_find_sup($q) {
  $q = trim((string)$q); if ($q === '') return [];
  $st = db()->prepare('SELECT * FROM itc_sup WHERE deleted=0 AND id=?'); $st->execute([$q]); $r = $st->fetch(); if ($r) return [$r];
  $l = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
  $st = db()->prepare('SELECT * FROM itc_sup WHERE deleted=0 AND (name LIKE ? OR full_name LIKE ?) LIMIT 6'); $st->execute([$l, $l]); $r = $st->fetchAll(); if ($r) return $r;
  /* №37: голос мог написать название русскими буквами — ищем похожее по звучанию */
  if (!function_exists('sup_fuzzy')) return [];
  $fz = sup_fuzzy($q); if (!$fz) return [];
  if ($fz[0]['похоже на %'] >= 80 && (count($fz) == 1 || $fz[1]['похоже на %'] < $fz[0]['похоже на %'] - 8)) $fz = [$fz[0]];
  $ids = array_column($fz, 'id'); $in = implode(',', array_fill(0, count($ids), '?'));
  $st = db()->prepare("SELECT * FROM itc_sup WHERE deleted=0 AND id IN ($in)"); $st->execute($ids); return $st->fetchAll();
}
function kp_dec($v) { $d = json_decode((string)$v, true); return is_array($d) ? $d : []; }

/* собрать содержимое КП из карточки поставщика и страницы сайта */
function kp_collect($sup, $kind) {
  $web = []; try { $q = db()->prepare('SELECT data FROM itc_sup_web WHERE sup_id=?'); $q->execute([$sup['id']]); $web = kp_dec($q->fetchColumn()); } catch (Throwable $e) {}
  $segName = ''; try { $q = db()->prepare('SELECT name FROM itc_sup_seg WHERE code=?'); $q->execute([$sup['seg']]); $segName = (string)$q->fetchColumn(); } catch (Throwable $e) {}
  $brand = trim((string)($web['brand'] ?? '')) ?: (string)$sup['name'];
  $groups = [];
  foreach ((array)($web['groups'] ?? []) as $g) {
    $items = [];
    foreach ((array)($g['items'] ?? []) as $it) $items[] = ['name' => (string)($it['name'] ?? ''), 'desc' => (string)($it['desc'] ?? ''), 'photo' => (string)(($it['photos'] ?? [])[0] ?? ''), 'spec' => (array)($it['spec'] ?? [])];
    if ($items || !empty($g['name'])) $groups[] = ['name' => (string)($g['name'] ?? ''), 'sub' => (string)($g['sub'] ?? $g['intro'] ?? ''), 'cover' => (string)($g['cover'] ?? ''), 'items' => $items];
  }
  if (!$groups) {   // страницы на сайте нет — берём товары из карточки
    $items = []; foreach (kp_dec($sup['products']) as $p) $items[] = ['name' => (string)$p, 'desc' => '', 'photo' => '', 'spec' => []];
    if ($items) $groups[] = ['name' => $segName ?: 'Продукция', 'sub' => '', 'cover' => '', 'items' => $items];
  }
  $facts = array_values(array_unique(array_merge((array)($web['about_bullets'] ?? []), kp_dec($sup['facts']))));
  return ['brand' => $brand, 'seg' => $segName, 'city' => (string)($web['city'] ?? '') ?: (string)$sup['city'],
    'h1' => (string)($web['h1'] ?? ''), 'lead' => (string)($web['lead'] ?? '') ?: (string)$sup['what'], 'about' => (string)($web['about_lead'] ?? ''),
    'hero' => (string)($web['hero'] ?? '') ?: (string)($web['factory'] ?? ''), 'stats' => (array)($web['stats'] ?? []), 'facts' => array_slice($facts, 0, 12),
    'awards' => (array)($web['awards'] ?? []), 'projects' => (array)($web['projects'] ?? []), 'groups' => $groups,
    'items' => array_sum(array_map(function ($g) { return count($g['items']); }, $groups))];
}

/* ---------- PDF ---------- */
function kp_pdf($d, $co, $kind, $client, $title) {
  $F = __DIR__ . '/kp_fonts/'; $p = new KpPdf($F . 'Roboto-Regular.ttf', $F . 'Roboto-Bold.ttf');
  $navy = '0a1a66'; $green = '2e9e0e'; $gray = '6b7590'; $text = '1f2533';
  $urls = [$d['hero']];
  foreach ($d['groups'] as $g) { $urls[] = $g['cover']; foreach ($g['items'] as $i => $it) if ($kind === 'full' || $i < 3) $urls[] = $it['photo']; }
  foreach (array_slice($d['awards'], 0, 4) as $a) $urls[] = $a['photo'] ?? '';
  $IM = kp_images($urls); $logo = kp_logo();
  $L = 15; $R = 195; $W = $R - $L; $y = 0; $pg = 0;
  $foot = function () use ($p, $co, $gray, &$pg) {
    $p->line(15, 282, 195, 282, 'd9deea', 0.3); $p->font('R', 7.5); $p->color($gray);
    $p->text(15, 286.5, trim($co['name'] . ($co['inn'] ? ' · ИНН ' . $co['inn'] : '')));
    $p->text(15, 290.5, trim(implode(' · ', array_filter([$co['phone'], $co['email'], $co['site']]))));
    $p->text(185, 290.5, 'стр. ' . $pg);
  };
  $newPage = function () use ($p, $logo, $navy, $gray, $co, $title, &$y, &$pg, $foot) {
    if ($pg) $foot(); $p->page(); $pg++;
    if ($logo !== '') $p->image($logo, 15, 8, 32, 13);
    $p->font('B', 14); $p->color($navy); $p->text(52, 15.5, 'Коммерческое предложение');
    $p->font('R', 8.5); $p->color($gray); $p->text(52, 20.5, $co['short'] . ' · ' . date('d.m.Y'));
    $p->line(15, 25, 195, 25, '8fe03a', 0.6); $y = 34;
  };
  $need = function ($h) use (&$y, $newPage) { if ($y + $h > 276) $newPage(); };
  $para = function ($s, $size = 10, $col = '1f2533', $lh = null, $font = 'R', $x = 15, $w = 180) use ($p, &$y, $need) {
    $s = trim((string)$s); if ($s === '') return; $p->font($font, $size); $p->color($col); $lh = $lh ?: $size * 0.47;
    foreach ($p->wrap($s, $w) as $l) { $need($lh); $p->text($x, $y, $l); $y += $lh; }
  };
  $h2 = function ($s, $min = 40) use ($p, &$y, $need, $navy) { $need($min); $y += 3; $p->font('B', 13); $p->color($navy); $p->text(15, $y, $s); $y += 3; $p->line(15, $y, 35, $y, '8fe03a', 0.9); $y += 6; };

  $newPage();
  $p->font('B', 18); $p->color($navy);
  $big = $title ?: ($kind === 'full' ? 'Каталог продукции ' . $d['brand'] : ($d['h1'] ?: 'Поставка продукции ' . $d['brand'] . ' напрямую с завода'));
  foreach ($p->wrap($big, 180) as $l) { $p->text(15, $y, $l); $y += 8; }
  $y += 1;
  if ($client !== '') $para('Для: ' . $client, 10, $gray);
  $para(($kind === 'full' ? 'Полный каталог моделей завода. ' : '') . 'Поставляем напрямую с завода' . ($d['city'] ? ' (' . $d['city'] . ', Китай)' : '') . ': проверяем товар на нашем складе в Китае и доставляем в Россию с полным пакетом документов. Цены — по запросу: пришлите модели и количество, подготовим расчёт с доставкой.', 9.5, $gray);
  $y += 3;
  if ($d['hero'] !== '' && isset($IM[$d['hero']]) && $kind === 'coop') { $need(72); $p->image($IM[$d['hero']], 15, $y, 180, 68); $y += 73; }
  $para($d['lead'], 10.5, $text, 5.2); if ($d['about'] !== '' && $kind === 'coop') { $y += 2; $para($d['about'], 10, $text, 5); }

  if ($kind === 'coop') {
    if ($d['stats']) { $y += 4; $need(24); $n = min(4, count($d['stats'])); $bw = (180 - ($n - 1) * 4) / $n;
      foreach (array_slice($d['stats'], 0, $n) as $i => $s) { $x = 15 + $i * ($bw + 4); $p->rect($x, $y, $bw, 20, 'f1f4fb'); $p->font('B', 15); $p->color($navy); $p->text($x + 4, $y + 9, (string)$s['n']); $p->font('R', 8); $p->color($gray); $ll = $p->wrap((string)$s['t'], $bw - 8); $p->text($x + 4, $y + 14.5, $ll[0] ?? ''); if (isset($ll[1])) $p->text($x + 4, $y + 18, $ll[1]); }
      $y += 25; }
    if ($d['facts']) { $h2('О производстве'); foreach ($d['facts'] as $f) { $need(6); $p->rect(15, $y - 2.2, 1.6, 1.6, '8fe03a'); $para($f, 10, $text, 5, 'R', 19, 176); $y += 0.8; } }
    if ($d['groups']) { $h2('Продукция', 80);
      foreach (array_slice($d['groups'], 0, 12) as $g) {
        $ph = []; foreach ($g['items'] as $it) if ($it['photo'] !== '' && isset($IM[$it['photo']])) { $ph[] = $IM[$it['photo']]; if (count($ph) >= 3) break; }
        if (!$ph && $g['cover'] !== '' && isset($IM[$g['cover']])) $ph[] = $IM[$g['cover']];
        $need($ph ? 62 : 16);
        $p->font('B', 11.5); $p->color($navy); $p->text(15, $y, $g['name'] ?: $d['brand']); $y += 5;
        if ($g['sub'] !== '') $para($g['sub'], 9, $gray, 4.3);
        if ($ph) { $y += 1; foreach ($ph as $i => $j) { $p->rect(15 + $i * 61, $y, 58, 42, 'f6f7fa'); $p->image($j, 15 + $i * 61, $y, 58, 42); } $y += 46; }
        $names = array_values(array_filter(array_map(function ($it) { return $it['name']; }, $g['items'])));
        if ($names) $para('Модели: ' . implode(', ', array_slice($names, 0, 12)) . (count($names) > 12 ? ' и ещё ' . (count($names) - 12) : ''), 9, $text, 4.5);
        $y += 4;
      } }
    if ($d['awards']) { $h2('Награды и сертификаты'); $cap = array_values(array_filter(array_map(function ($a) { return (string)($a['cap'] ?? ''); }, $d['awards'])));
      foreach (array_slice($cap, 0, 10) as $c) { $need(6); $p->rect(15, $y - 2.2, 1.6, 1.6, '8fe03a'); $para($c, 9.5, $text, 4.8, 'R', 19, 176); } }
    $h2('Как мы работаем');
    foreach (['Вы присылаете задачу: модели, количество, требования.', 'Подбираем товар и согласуем с заводом цены и сроки, присылаем расчёт.', 'Договор и оплата в рублях в России, заводу платим мы.', 'Завод изготавливает партию, мы принимаем и проверяем товар на нашем складе в Китае, присылаем фото.', 'Доставляем в Россию с документами до вашего города.'] as $i => $s) {
      $need(8); $p->font('B', 10); $p->color($green); $p->text(15, $y, ($i + 1) . '.'); $para($s, 10, $text, 5, 'R', 21, 174); $y += 1; }
  } else {
    foreach ($d['groups'] as $g) {
      $h2($g['name'] ?: 'Продукция'); if ($g['sub'] !== '') $para($g['sub'], 9, $gray, 4.3); $y += 1;
      foreach ($g['items'] as $it) {
        $p->font('R', 8.5); $sp = array_slice(array_map(function ($r) { return trim((string)($r[0] ?? '')) . ': ' . trim((string)($r[1] ?? '')); }, $it['spec']), 0, 6);
        $dl = $it['desc'] !== '' ? array_slice($p->wrap($it['desc'], 140), 0, 3) : [];
        $h = max(36, 7 + count($dl) * 4.2 + count($sp) * 3.9 + 4); $need($h);
        $p->rect(15, $y - 4, 36, 34, 'f6f7fa');
        if ($it['photo'] !== '' && isset($IM[$it['photo']])) $p->image($IM[$it['photo']], 16, $y - 3, 34, 32);
        $yy = $y; $p->font('B', 11); $p->color($navy); foreach (array_slice($p->wrap($it['name'], 140), 0, 2) as $l) { $p->text(55, $yy, $l); $yy += 5; }
        $p->font('R', 9); $p->color($text); foreach ($dl as $l) { $p->text(55, $yy, $l); $yy += 4.2; }
        $p->font('R', 8.5); $p->color($gray); foreach ($sp as $l) { $l = $p->wrap($l, 140)[0] ?? ''; $p->text(55, $yy, $l); $yy += 3.9; }
        $p->font('B', 8.5); $p->color($green); $p->text(55, $yy + 0.5, 'Цена — по запросу');
        $y = max($y + 36, $yy + 6); $p->line(15, $y - 2.5, 195, $y - 2.5, 'eceff5', 0.2);
      }
      $y += 3;
    }
  }
  $y += 4; $need(18); $p->rect(15, $y, 180, 15, 'f1f4fb'); $p->font('B', 10.5); $p->color($navy); $p->text(20, $y + 6.5, 'Пришлите список моделей и количество — подготовим расчёт с доставкой до вашего города.');
  $p->font('R', 9); $p->color($gray); $p->text(20, $y + 11.5, trim(($co['phone'] ? $co['phone'] . '   ' : '') . ($co['email'] ? $co['email'] . '   ' : '') . $co['site']));
  $foot();
  return $p->output();
}

/* ---------- Excel (.xlsx) ---------- */
function kp_xlsx($d, $co, $kind, $client, $title) {
  $esc = function ($s) { return htmlspecialchars(preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', (string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
  $rows = []; $rows[] = [[$title ?: 'Коммерческое предложение — ' . $d['brand'], 1]];
  $rows[] = [[$co['name'] . ($co['inn'] ? ', ИНН ' . $co['inn'] : '') . ' · ' . date('d.m.Y') . ($client !== '' ? ' · для: ' . $client : ''), 0]];
  $rows[] = [['Цены — по запросу: впишите количество, мы подготовим расчёт с доставкой.', 0]]; $rows[] = [];
  $rows[] = array_map(function ($h) { return [$h, 2]; }, ['№', 'Раздел', 'Модель', 'Описание', 'Характеристики', 'Фото', 'Количество', 'Цена']);
  $n = 0;
  foreach ($d['groups'] as $g) foreach ($g['items'] as $it) {
    $n++; $sp = implode("\n", array_map(function ($r) { return trim((string)($r[0] ?? '')) . ': ' . trim((string)($r[1] ?? '')); }, $it['spec']));
    $rows[] = [[$n, 3], [$g['name'], 3], [$it['name'], 3], [$it['desc'], 3], [$sp, 3], [$it['photo'], 3], ['', 3], ['по запросу', 3]];
  }
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="5" topLeftCell="A6" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
    . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="50" customWidth="1"/><col min="5" max="5" width="45" customWidth="1"/><col min="6" max="6" width="40" customWidth="1"/><col min="7" max="8" width="13" customWidth="1"/></cols><sheetData>';
  foreach ($rows as $ri => $r) {
    $sheet .= '<row r="' . ($ri + 1) . '">';
    foreach ($r as $ci => $c) { $ref = chr(65 + $ci) . ($ri + 1); $v = $c[0];
      $sheet .= is_int($v) ? '<c r="' . $ref . '" s="' . $c[1] . '"><v>' . $v . '</v></c>' : '<c r="' . $ref . '" s="' . $c[1] . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>'; }
    $sheet .= '</row>';
  }
  $sheet .= '</sheetData></worksheet>';
  $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="3"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="14"/><color rgb="FF0A1A66"/><name val="Arial"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0A1A66"/></patternFill></fill></fills>'
    . '<borders count="2"><border/><border><left style="thin"><color rgb="FFD9DEEA"/></left><right style="thin"><color rgb="FFD9DEEA"/></right><top style="thin"><color rgb="FFD9DEEA"/></top><bottom style="thin"><color rgb="FFD9DEEA"/></bottom></border></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="4"><xf/><xf fontId="1" applyFont="1"/><xf fontId="2" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs></styleSheet>';
  $tmp = tempnam(sys_get_temp_dir(), 'kpx'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
  $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="КП" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $z->addFromString('xl/worksheets/sheet1.xml', $sheet); $z->addFromString('xl/styles.xml', $styles); $z->close();
  $b = (string)file_get_contents($tmp); @unlink($tmp); return $b;
}

/* ---------- инструмент Фила ---------- */
function kp_make($me, $a) {
  kp_ensure();
  $kind = (string)($a['kind'] ?? '');
  if ($kind !== 'coop' && $kind !== 'full') return 'Не указан вид КП. Спроси сотрудника (do=ask): «Сделать КП на сотрудничество (о заводе, линейки товаров с фото, без цен) или полный каталог всех моделей, чтобы клиент выбрал?»';
  $sups = kp_find_sup($a['sup'] ?? ''); if (!$sups) return 'Не нашёл поставщика «' . ($a['sup'] ?? '') . '» — найди его через suppliers и передай id';
  if (count($sups) > 1) return ['несколько похожих поставщиков — уточни или передай id' => array_map(function ($s) { return ['id' => $s['id'], 'название' => $s['name'], 'город' => $s['city']]; }, $sups)];
  $sup = $sups[0]; $d = kp_collect($sup, $kind);
  if (!$d['items'] && $kind === 'full') return 'У поставщика «' . $sup['name'] . '» нет списка моделей (ни страницы на сайте, ни товаров в карточке) — полный каталог сделать не из чего. Можно сделать КП на сотрудничество или сначала загрузить каталог фабрики.';
  $fm = array_values(array_intersect(['pdf', 'xlsx'], array_map('strtolower', (array)($a['formats'] ?? ['pdf', 'xlsx'])))); if (!$fm) $fm = ['pdf', 'xlsx'];
  $client = mb_substr(trim((string)($a['client'] ?? '')), 0, 200); $title = mb_substr(trim((string)($a['title'] ?? '')), 0, 200);
  $co = kp_company($a['company'] ?? '');
  $id = 'kp' . bin2hex(random_bytes(6));
  try {
    if (in_array('pdf', $fm, true)) file_put_contents(kp_dir() . "/$id.pdf", kp_pdf($d, $co, $kind, $client, $title));
    if (in_array('xlsx', $fm, true)) file_put_contents(kp_dir() . "/$id.xlsx", kp_xlsx($d, $co, $kind, $client, $title));
  } catch (Throwable $e) { return 'Не получилось собрать файл: ' . $e->getMessage(); }
  $nice = ($kind === 'full' ? 'Каталог ' : 'КП ') . $d['brand'];
  db()->prepare('INSERT INTO itc_fil_kp (id,phone,title,kind,sup_id,client,files,items) VALUES (?,?,?,?,?,?,?,?)')->execute([$id, $me['phone'], $nice, $kind, $sup['id'], $client, implode(',', $fm), $d['items']]);
  $GLOBALS['KP_MADE'][] = ['id' => $id, 't' => $nice];
  return ['готово' => $nice, 'вид' => $kind === 'full' ? 'полный каталог' : 'на сотрудничество', 'от компании' => $co['short'], 'моделей' => $d['items'], 'файлы' => $fm,
    'как выдать' => 'файлы уже прикреплены к твоему ответу кнопками — просто скажи, что КП готово; ссылки не придумывай'];
}
/* кнопки КП в тексте ответа: [[kp:id|название]] */
function kp_marks() { $o = ''; foreach ((array)($GLOBALS['KP_MADE'] ?? []) as $k) $o .= "\n[[kp:" . $k['id'] . '|' . str_replace([']', '|'], '', $k['t']) . ']]'; return $o; }
function kp_download($me, $id, $f) {
  kp_ensure(); $id = preg_replace('~[^a-z0-9]~', '', (string)$id); $f = $f === 'xlsx' ? 'xlsx' : 'pdf';
  $q = db()->prepare('SELECT * FROM itc_fil_kp WHERE id=?'); $q->execute([$id]); $r = $q->fetch();
  // №237: счета на оплату (kind=inv) скачивает ещё и бухгалтерия — она их проводит
  $acc = $r && $r['kind'] === 'inv' && in_array($me['role'] ?? '', ['accountant', 'chief_accountant', 'bossacct', 'supervisor'], true);
  if (!$r || ($r['phone'] !== $me['phone'] && !$me['boss'] && !$acc)) { http_response_code(404); exit('нет файла'); }
  $p = kp_dir() . "/$id.$f"; if (!is_file($p)) { http_response_code(404); exit('этот формат не делали'); }
  $name = preg_replace('~[^\p{L}\p{N} _.-]~u', '', $r['title']) . ($r['client'] ? ' — ' . preg_replace('~[^\p{L}\p{N} _.-]~u', '', $r['client']) : '') . '.' . $f;
  while (ob_get_level() > 0) @ob_end_clean();
  header('Content-Type: ' . ($f === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
  $ascii = trim(preg_replace('~[^A-Za-z0-9_-]+~', '_', (string)@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', strtr($name, ['КП' => 'KP', 'Каталог' => 'Katalog', 'Счёт' => 'Schet']))), '_'); $ascii = preg_replace('~_(pdf|xlsx)$~', '', $ascii) ?: 'KP';
  header("Content-Disposition: attachment; filename=\"$ascii.$f\"; filename*=UTF-8''" . rawurlencode($name));
  header('Content-Length: ' . filesize($p)); readfile($p); exit;
}
