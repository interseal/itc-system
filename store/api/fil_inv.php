<?php
/* ==== ИТК · fil_inv.php · №237 (07.11.2026) — счёт на оплату клиенту ====
   Фил выписывает счёт голосом: «выпиши Ромашке счёт на 20 стульев по 4500».
   • Продавец — наша компания из «Наши компании» (по умолчанию ООО «ИТК»), её банк в рублях.
   • Покупатель — рабочий клиент из «Клиентов» (реквизиты из карточки; пустые Фил добирает по ИНН).
   • Номер — по порядку внутри года и компании. Цены — с НДС (по умолчанию 22%), можно «без НДС».
   • Файлы PDF и Excel лежат рядом с КП (uploads/fil_kp, закрыто), скачиваются кнопками под ответом Фила
     и из истории клиента. Подпись и печать не ставим — подписывают руководитель и бухгалтер. */
require_once __DIR__ . '/kp_pdf.php';

function inv_ensure() {
  static $d = false; if ($d) return; $d = true;
  db()->exec("CREATE TABLE IF NOT EXISTS crm_inv (
    id VARCHAR(20) NOT NULL PRIMARY KEY, no INT NOT NULL DEFAULT 0, yr SMALLINT NOT NULL DEFAULT 0, dt DATE NULL,
    co_id VARCHAR(40) NOT NULL DEFAULT '', company VARCHAR(190) NOT NULL DEFAULT '',
    client_id VARCHAR(20) NOT NULL DEFAULT '', client VARCHAR(255) NOT NULL DEFAULT '', inn VARCHAR(20) NOT NULL DEFAULT '',
    total DECIMAL(16,2) NOT NULL DEFAULT 0, vat SMALLINT NOT NULL DEFAULT 0, items MEDIUMTEXT, note VARCHAR(500) NOT NULL DEFAULT '',
    deal_id VARCHAR(40) NOT NULL DEFAULT '', file_id VARCHAR(40) NOT NULL DEFAULT '',
    created_by VARCHAR(190) NOT NULL DEFAULT '', created_phone VARCHAR(40) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY k_inv_client (client_id), KEY k_inv_no (co_id, yr, no)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* наша компания для счёта: полная карточка с рублёвым банком */
function inv_company($want) {
  $all = function_exists('co_all') ? co_all(false) : []; $want = mb_strtolower(trim((string)$want));
  $score = function ($c) use ($want) {
    $s = mb_strtolower(($c['short'] ?? '') . ' ' . ($c['name'] ?? '')); $n = 0;
    if ($want !== '') { if (mb_strpos($s, $want) !== false) $n += 10; if (mb_strpos($want, 'глобал') === false && mb_strpos($s, 'глобал') !== false) $n -= 3; }
    else { if (mb_strpos($s, 'итк') !== false || mb_strpos($s, 'интернациональная') !== false) $n += 3; if (mb_strpos($s, 'глобал') === false) $n += 2; }
    if (preg_match('~росси~iu', (string)($c['country'] ?? ''))) $n += 2;
    return $n;
  };
  usort($all, function ($a, $b) use ($score) { return $score($b) - $score($a); });
  $c = $all[0] ?? null; if (!$c) return null;
  $bank = null; foreach ((array)$c['banks'] as $b) if (strtoupper((string)($b['cur'] ?? 'RUB')) === 'RUB' && !empty($b['acc'])) { $bank = $b; break; }
  $c['bank'] = $bank; $c['short'] = trim(preg_replace('~\s*·.*$~u', '', (string)$c['short']));
  return $c;
}

/* сумма прописью: 12 345,60 → «Двенадцать тысяч триста сорок пять рублей 60 копеек» */
function inv_words($sum) {
  $sum = round((float)$sum, 2); $rub = (int)floor($sum); $kop = (int)round(($sum - $rub) * 100);
  $u = [['', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'], ['', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять']];
  $t = ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'];
  $d = ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'];
  $h = ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'];
  $forms = [['рубль', 'рубля', 'рублей', 0], ['тысяча', 'тысячи', 'тысяч', 1], ['миллион', 'миллиона', 'миллионов', 0], ['миллиард', 'миллиарда', 'миллиардов', 0]];
  $plural = function ($n, $f) { $n = abs($n) % 100; $n1 = $n % 10; if ($n > 10 && $n < 20) return $f[2]; if ($n1 > 1 && $n1 < 5) return $f[1]; if ($n1 == 1) return $f[0]; return $f[2]; };
  if ($rub === 0) $out = 'ноль рублей';
  else {
    $parts = []; $i = 0; $n = $rub;
    while ($n > 0 && $i < 4) {
      $g = $n % 1000; $n = intdiv($n, 1000);
      if ($g > 0 || $i === 0) {
        $w = [];
        if ($g >= 100) $w[] = $h[intdiv($g, 100)];
        $r = $g % 100;
        if ($r >= 10 && $r < 20) $w[] = $t[$r - 10];
        else { if ($r >= 20) $w[] = $d[intdiv($r, 10)]; if ($r % 10) $w[] = $u[$forms[$i][3]][$r % 10]; }
        if ($g > 0 || $i === 0) $w[] = $plural($g, $forms[$i]);
        array_unshift($parts, implode(' ', array_filter($w)));
      }
      $i++;
    }
    $out = implode(' ', $parts);
  }
  $out = trim(preg_replace('~\s+~u', ' ', $out));
  return mb_strtoupper(mb_substr($out, 0, 1)) . mb_substr($out, 1) . ' ' . sprintf('%02d', $kop) . ' ' . $plural($kop, ['копейка', 'копейки', 'копеек']);
}
function inv_money($v) { return number_format((float)$v, 2, ',', ' '); }
function inv_date_words($ymd) {
  $m = ['', 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
  $t = strtotime($ymd); return (int)date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . date('Y', $t) . ' г.';
}

function inv_pdf($co, $cl, $no, $dt, $items, $vat, $note) {
  $F = __DIR__ . '/kp_fonts/'; $p = new KpPdf($F . 'Roboto-Regular.ttf', $F . 'Roboto-Bold.ttf');
  $ink = '1f2533'; $gray = '6b7590'; $navy = '0a1a66'; $bd = '1f2533';
  $p->page(); $L = 15; $R = 195; $y = 14;
  $logo = function_exists('kp_logo') ? kp_logo() : '';
  if ($logo !== '') $p->image($logo, $L, 9, 28, 11);
  $p->font('R', 8); $p->color($gray);
  $hd = array_values(array_filter([$co['name'], $co['addr'], trim(($co['phone'] ?? '') . '  ' . ($co['email'] ?? ''))]));
  $yy = 12; foreach ($hd as $l) { foreach ($p->wrap($l, 120) as $ll) { $p->text($R - min(120, $p->tw($ll)), $yy, $ll); $yy += 3.6; } }
  $y = max(26, $yy + 3);
  $p->font('R', 7.5); $p->color($gray);
  foreach ($p->wrap('Внимание! Оплата данного счёта означает согласие с условиями поставки товара. Товар отпускается по факту прихода денег на расчётный счёт поставщика.', 180) as $l) { $p->text($L, $y, $l); $y += 3.4; }
  $y += 2;
  // банковский блок
  $b = $co['bank']; $x1 = $L; $x2 = 115; $x3 = 130; $top = $y; $rh = 6;
  $cell = function ($x, $yy, $s, $f = 'R', $sz = 8.5) use ($p, $ink) { $p->font($f, $sz); $p->color($ink); $p->text($x, $yy, $s); };
  $p->line($x1, $top, $R, $top, $bd, 0.3);
  $cell($x1 + 1.5, $top + 4, mb_substr((string)($b['bank'] ?? ''), 0, 70)); $cell($x2 + 1.5, $top + 4, 'БИК'); $cell($x3 + 1.5, $top + 4, (string)($b['bik'] ?? ''));
  $cell($x1 + 1.5, $top + 10.5, 'Банк получателя', 'R', 7); $cell($x2 + 1.5, $top + 10.5, 'Сч. №'); $cell($x3 + 1.5, $top + 10.5, (string)($b['corr'] ?? ''));
  $p->line($x1, $top + 12, $x2, $top + 12, $bd, 0.3); $p->line($x2, $top + $rh, $R, $top + $rh, $bd, 0.3); $p->line($x2, $top + 12, $R, $top + 12, $bd, 0.3);
  $cell($x1 + 1.5, $top + 16, 'ИНН ' . ($co['inn'] ?? '')); $cell(60, $top + 16, 'КПП ' . ($co['kpp'] ?? ''));
  $cell($x2 + 1.5, $top + 16, 'Сч. №'); $cell($x3 + 1.5, $top + 16, (string)($b['acc'] ?? ''));
  $p->line($x1, $top + 18, $x2, $top + 18, $bd, 0.3); $p->line(58, $top + 12, 58, $top + 18, $bd, 0.3);
  $p->font('R', 8); $nm = $p->wrap((string)$co['name'], 96);
  foreach (array_slice($nm, 0, 2) as $i => $l) $cell($x1 + 1.5, $top + 22 + $i * 3.6, $l, 'R', 8);
  $cell($x1 + 1.5, $top + 28.5, 'Получатель', 'R', 7);
  $bot = $top + 30;
  $p->line($x1, $bot, $R, $bot, $bd, 0.3);
  foreach ([$x1, $x2, $x3, $R] as $x) $p->line($x, $top, $x, $bot, $bd, 0.3);
  $y = $bot + 9;
  $p->font('B', 14); $p->color($ink); $p->text($L, $y, 'Счёт на оплату № ' . $no . ' от ' . inv_date_words($dt)); $y += 3;
  $p->line($L, $y, $R, $y, $bd, 0.6); $y += 6;
  $party = function ($label, $text) use ($p, &$y, $L, $ink) {
    $p->font('R', 9); $p->color($ink); $p->text($L, $y, $label);
    $p->font('B', 9); $first = true;
    foreach ($p->wrap($text, 150) as $l) { $p->text($L + 30, $y, $l); $y += 4.4; $first = false; }
    $y += 1.5;
  };
  $party('Поставщик:', implode(', ', array_filter([$co['name'], $co['inn'] ? 'ИНН ' . $co['inn'] : '', $co['kpp'] ? 'КПП ' . $co['kpp'] : '', $co['addr']])));
  $party('Покупатель:', implode(', ', array_filter([$cl['company'] ?: $cl['alias'], $cl['inn'] ? 'ИНН ' . $cl['inn'] : '', $cl['kpp'] ? 'КПП ' . $cl['kpp'] : '', $cl['addr']])));
  if ($note !== '') $party('Основание:', $note);
  $y += 2;
  // таблица товаров
  $cols = [[$L, 9, '№', 'C'], [$L + 9, 95, 'Товары (работы, услуги)', 'L'], [$L + 104, 16, 'Кол-во', 'R'], [$L + 120, 12, 'Ед.', 'C'], [$L + 132, 23, 'Цена', 'R'], [$L + 155, 25, 'Сумма', 'R']];
  $head = function () use ($p, &$y, $cols, $bd, $ink) {
    $p->rect(15, $y, 180, 7, 'eef1f7'); $p->font('B', 8.5); $p->color($ink);
    foreach ($cols as $c) { $tx = $c[3] === 'R' ? $c[0] + $c[1] - 1.5 - $p->tw($c[2]) : ($c[3] === 'C' ? $c[0] + ($c[1] - $p->tw($c[2])) / 2 : $c[0] + 1.5); $p->text($tx, $y + 4.8, $c[2]); }
    $p->line(15, $y, 195, $y, $bd, 0.3); $p->line(15, $y + 7, 195, $y + 7, $bd, 0.3);
    foreach ($cols as $c) $p->line($c[0], $y, $c[0], $y + 7, $bd, 0.3); $p->line(195, $y, 195, $y + 7, $bd, 0.3);
    $y += 7;
  };
  $head(); $total = 0;
  foreach ($items as $i => $it) {
    $p->font('R', 8.5); $nl = $p->wrap($it['name'], 92); $h = max(6, 2 + count($nl) * 4);
    if ($y + $h > 265) { $p->page(); $y = 15; $head(); }
    $sum = round($it['qty'] * $it['price'], 2); $total += $sum;
    $vals = [(string)($i + 1), '', rtrim(rtrim(number_format($it['qty'], 3, ',', ' '), '0'), ','), $it['unit'], inv_money($it['price']), inv_money($sum)];
    $p->color($ink);
    foreach ($cols as $k => $c) {
      if ($k === 1) { $yy = $y + 4.2; foreach ($nl as $l) { $p->text($c[0] + 1.5, $yy, $l); $yy += 4; } continue; }
      $s = $vals[$k]; $tx = $c[3] === 'R' ? $c[0] + $c[1] - 1.5 - $p->tw($s) : ($c[3] === 'C' ? $c[0] + ($c[1] - $p->tw($s)) / 2 : $c[0] + 1.5);
      $p->text($tx, $y + 4.2, $s);
    }
    foreach ($cols as $c) $p->line($c[0], $y, $c[0], $y + $h, $bd, 0.3); $p->line(195, $y, 195, $y + $h, $bd, 0.3); $p->line(15, $y + $h, 195, $y + $h, $bd, 0.3);
    $y += $h;
  }
  $y += 5;
  $vatSum = $vat > 0 ? round($total * $vat / (100 + $vat), 2) : 0;
  $tot = function ($label, $val) use ($p, &$y, $ink) { $p->font('B', 9.5); $p->color($ink); $p->text(170 - $p->tw($label), $y, $label); $p->text(195 - $p->tw($val), $y, $val); $y += 5; };
  $tot('Итого:', inv_money($total));
  $tot($vat > 0 ? 'В том числе НДС (' . $vat . '%):' : 'Без НДС', $vat > 0 ? inv_money($vatSum) : '—');
  $tot('Всего к оплате:', inv_money($total));
  $y += 2;
  $p->font('R', 9); $p->color($ink); $p->text($L, $y, 'Всего наименований ' . count($items) . ', на сумму ' . inv_money($total) . ' руб.'); $y += 4.8;
  $p->font('B', 9.5); foreach ($p->wrap(inv_words($total), 180) as $l) { $p->text($L, $y, $l); $y += 4.8; }
  $y += 2; $p->line($L, $y, $R, $y, $bd, 0.6); $y += 14;
  if ($y > 270) { $p->page(); $y = 30; }
  $dir = trim(preg_replace('~,.*$~u', '', (string)($co['director'] ?? '')));
  $p->font('B', 9.5); $p->text($L, $y, 'Руководитель'); $p->line(42, $y + 0.8, 95, $y + 0.8, $bd, 0.3);
  $p->font('R', 8.5); if ($dir !== '') { $parts = preg_split('~\s+~u', $dir); $short = $parts[0] . (isset($parts[1]) ? ' ' . mb_substr($parts[1], 0, 1) . '.' : '') . (isset($parts[2]) ? ' ' . mb_substr($parts[2], 0, 1) . '.' : ''); $p->text(70 - $p->tw($short) / 2, $y + 4.5, $short); }
  $p->font('B', 9.5); $p->text(105, $y, 'Бухгалтер'); $p->line(126, $y + 0.8, 195, $y + 0.8, $bd, 0.3);
  return $p->output();
}

function inv_xlsx($co, $cl, $no, $dt, $items, $vat, $note) {
  $esc = function ($s) { return htmlspecialchars(preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', (string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
  $b = $co['bank'] ?? [];
  $rows = [];
  $rows[] = [['Счёт на оплату № ' . $no . ' от ' . inv_date_words($dt), 1]];
  $rows[] = [['Поставщик: ' . implode(', ', array_filter([$co['name'], $co['inn'] ? 'ИНН ' . $co['inn'] : '', $co['kpp'] ? 'КПП ' . $co['kpp'] : '', $co['addr']])), 0]];
  $rows[] = [['Банк: ' . implode(', ', array_filter([$b['bank'] ?? '', !empty($b['bik']) ? 'БИК ' . $b['bik'] : '', !empty($b['acc']) ? 'р/с ' . $b['acc'] : '', !empty($b['corr']) ? 'к/с ' . $b['corr'] : ''])), 0]];
  $rows[] = [['Покупатель: ' . implode(', ', array_filter([$cl['company'] ?: $cl['alias'], $cl['inn'] ? 'ИНН ' . $cl['inn'] : '', $cl['kpp'] ? 'КПП ' . $cl['kpp'] : '', $cl['addr']])), 0]];
  if ($note !== '') $rows[] = [['Основание: ' . $note, 0]];
  $rows[] = [];
  $rows[] = array_map(function ($h) { return [$h, 2]; }, ['№', 'Товары (работы, услуги)', 'Кол-во', 'Ед.', 'Цена', 'Сумма']);
  $first = count($rows) + 1; $total = 0;
  foreach ($items as $i => $it) { $s = round($it['qty'] * $it['price'], 2); $total += $s; $rows[] = [[$i + 1, 3], [$it['name'], 3], [(float)$it['qty'], 3], [$it['unit'], 3], [(float)$it['price'], 4], [$s, 4]]; }
  $rows[] = [];
  $rows[] = [['', 0], ['Итого', 1], ['', 0], ['', 0], ['', 0], [round($total, 2), 4]];
  $rows[] = [['', 0], [$vat > 0 ? 'В том числе НДС (' . $vat . '%)' : 'Без НДС', 0], ['', 0], ['', 0], ['', 0], [$vat > 0 ? round($total * $vat / (100 + $vat), 2) : 0, 4]];
  $rows[] = [['', 0], ['Сумма прописью: ' . inv_words($total), 0]];
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="60" customWidth="1"/><col min="3" max="4" width="10" customWidth="1"/><col min="5" max="6" width="16" customWidth="1"/></cols><sheetData>';
  foreach ($rows as $ri => $r) {
    $sheet .= '<row r="' . ($ri + 1) . '">';
    foreach ($r as $ci => $c) { $ref = chr(65 + $ci) . ($ri + 1); $v = $c[0];
      $sheet .= (is_int($v) || is_float($v)) ? '<c r="' . $ref . '" s="' . $c[1] . '"><v>' . $v . '</v></c>' : '<c r="' . $ref . '" s="' . $c[1] . '" t="inlineStr"><is><t xml:space="preserve">' . $esc($v) . '</t></is></c>'; }
    $sheet .= '</row>';
  }
  $sheet .= '</sheetData></worksheet>';
  $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
    . '<fonts count="3"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="12"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font></fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEEF1F7"/></patternFill></fill></fills>'
    . '<borders count="2"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="5"><xf/><xf fontId="1" applyFont="1"/><xf fontId="2" fillId="2" borderId="1" applyFont="1" applyFill="1" applyBorder="1"/><xf borderId="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="164" borderId="1" applyNumberFormat="1" applyBorder="1"/></cellXfs></styleSheet>';
  $tmp = tempnam(sys_get_temp_dir(), 'inv'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
  $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Счёт" sheetId="1" r:id="rId1"/></sheets></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $z->addFromString('xl/worksheets/sheet1.xml', $sheet); $z->addFromString('xl/styles.xml', $styles); $z->close();
  $bin = (string)file_get_contents($tmp); @unlink($tmp); return $bin;
}

/* инструмент Фила: выписать счёт */
function inv_make($me, $a) {
  inv_ensure();
  if (!crm_tbl_ok('crm_clients')) return 'Раздел «Рабочие клиенты» ещё не заведён — откройте его в Экосистеме один раз.';
  $cl = null; $cid = trim((string)($a['client_id'] ?? ''));
  if ($cid !== '') { $st = db()->prepare('SELECT * FROM crm_clients WHERE id=?'); $st->execute([$cid]); $cl = $st->fetch() ?: null; }
  if (!$cl) {
    $f = crm_find((string)($a['client'] ?? ''), 5);
    if (!$f) return 'Клиента «' . ($a['client'] ?? '') . '» нет в рабочих клиентах. Сначала заведи его через client_save (название, ИНН — реквизиты подтянутся), потом выписывай счёт.';
    if (count($f) > 1 && $f[0]['_score'] < 100 && $f[1]['_score'] >= $f[0]['_score'] - 10)
      return ['несколько похожих клиентов — уточни у человека или передай client_id' => array_map(function ($r) { return ['client_id' => $r['id'], 'название' => crm_name($r), 'ИНН' => $r['inn']]; }, $f)];
    $cl = $f[0];
  }
  if (trim((string)$cl['company']) === '' && trim((string)$cl['inn']) === '')
    return 'У клиента «' . crm_name($cl) . '» нет ни юридического названия, ни ИНН — счёт выписать не на кого. Спроси ИНН и сохрани его через client_save, реквизиты подтянутся сами.';
  $items = [];
  foreach ((array)($a['items'] ?? []) as $x) {
    if (!is_array($x)) continue;
    $n = trim(mb_substr((string)($x['name'] ?? ''), 0, 300)); $q = (float)str_replace(',', '.', (string)($x['qty'] ?? 1)); $pr = (float)str_replace([',', ' '], ['.', ''], (string)($x['price'] ?? 0));
    if ($n === '' || $q <= 0) continue;
    if ($pr <= 0) return 'Нет цены у позиции «' . $n . '». Спроси цену (do=ask) или возьми из КП/сделки клиента (client_card).';
    $items[] = ['name' => $n, 'qty' => $q, 'unit' => trim(mb_substr((string)($x['unit'] ?? 'шт'), 0, 10)) ?: 'шт', 'price' => round($pr, 2)];
  }
  if (!$items) return 'Нет позиций для счёта: нужны название, количество и цена каждой. Спроси (do=ask) или возьми из сделки/КП клиента.';
  if (count($items) > 60) $items = array_slice($items, 0, 60);
  $co = inv_company($a['company'] ?? ''); if (!$co) return 'Не нашёл карточку нашей компании в «Наши компании».';
  if (empty($co['bank'])) return 'В карточке «' . $co['short'] . '» нет рублёвого банковского счёта — счёт выписать нельзя. Пусть руководитель добавит банк в «Наши компании».';
  $vat = isset($a['vat']) && $a['vat'] !== '' ? max(0, min(30, (int)$a['vat'])) : 22;
  $note = trim(mb_substr((string)($a['note'] ?? ''), 0, 300)); $dt = date('Y-m-d'); $yr = (int)date('Y');
  kp_ensure();   // таблицы создаём ДО транзакции: CREATE TABLE в MySQL молча закрывает транзакцию
  $pdo = db(); $pdo->beginTransaction();
  try {
    $q = $pdo->prepare('SELECT IFNULL(MAX(no),0)+1 FROM crm_inv WHERE co_id=? AND yr=? FOR UPDATE'); $q->execute([$co['id'], $yr]); $no = (int)$q->fetchColumn();
    $id = crm_gen_id('iv'); $fid = 'kp' . bin2hex(random_bytes(6));
    $total = 0; foreach ($items as $it) $total += round($it['qty'] * $it['price'], 2);
    file_put_contents(kp_dir() . "/$fid.pdf", inv_pdf($co, $cl, $no, $dt, $items, $vat, $note));
    file_put_contents(kp_dir() . "/$fid.xlsx", inv_xlsx($co, $cl, $no, $dt, $items, $vat, $note));
    $pdo->prepare('INSERT INTO crm_inv (id,no,yr,dt,co_id,company,client_id,client,inn,total,vat,items,note,deal_id,file_id,created_by,created_phone) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$id, $no, $yr, $dt, $co['id'], $co['short'], $cl['id'], crm_name($cl), (string)$cl['inn'], $total, $vat, json_encode($items, JSON_UNESCAPED_UNICODE), $note, (string)($a['deal'] ?? ''), $fid, $me['name'], $me['phone']]);
    $title = 'Счёт № ' . $no . ' от ' . date('d.m.Y');
    $pdo->prepare('INSERT INTO itc_fil_kp (id,phone,title,kind,sup_id,client,files,items) VALUES (?,?,?,?,?,?,?,?)')->execute([$fid, $me['phone'], $title, 'inv', '', crm_name($cl), 'pdf,xlsx', count($items)]);
    $pdo->commit();
  } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); return 'Не получилось выписать счёт: ' . $e->getMessage(); }
  if (!empty($a['deal']) && function_exists('fl_log')) { try { fl_log((string)$a['deal'], 'comment', 'Выписан ' . $title . ' на ' . inv_money($total) . ' ₽', $me['name'] . ' (через Фила)'); } catch (Throwable $e) {} }
  $GLOBALS['KP_MADE'][] = ['id' => $fid, 't' => $title];
  return ['готово' => $title, 'покупатель' => $cl['company'] ?: crm_name($cl), 'от компании' => $co['short'], 'позиций' => count($items), 'сумма' => inv_money($total) . ' ₽',
    'НДС' => $vat > 0 ? $vat . '%, в том числе ' . inv_money(round($total * $vat / (100 + $vat), 2)) : 'без НДС',
    'реквизиты покупателя' => (trim((string)$cl['inn']) === '' ? 'нет ИНН — счёт неполный, попроси ИНН' : 'есть'),
    'как выдать' => 'PDF и Excel уже прикреплены к ответу кнопками и лежат в истории клиента; скажи номер и сумму; подпись и печать ставят руководитель и бухгалтер'];
}
