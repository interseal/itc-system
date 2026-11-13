<?php
// Запрос по фабрике для менеджера (3.42.3): письмо с таблицей и фото + файл Excel с фото товаров.
// Подключается из send.php. Внешних библиотек не нужно: Excel собирается из XML в архив (ZipArchive).

// Фото берём только с фотобанка: адрес вида …/sup/<фабрика>/<файл>.jpg|png|webp, без локальных адресов
function order_img_ok($u) {
    if (order_img_local($u) !== '') return true;
    if (!preg_match('~^https?://([a-z0-9.-]+)(:\d+)?/(?:[^?#\s]*/)?sup/[A-Za-z0-9_-]+/[A-Za-z0-9_.%-]+\.(?:jpe?g|png|webp)$~i', $u, $m)) return false;
    if (getenv('ITC_STAND') === '1') return true;                       // тестовая копия сайта
    if (stripos($u, 'https://') !== 0) return false;
    $host = $m[1];
    if (filter_var($host, FILTER_VALIDATE_IP)) return false;            // адрес-цифры не принимаем
    $ip = gethostbyname($host);
    return $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

// Свои картинки сайта (/img/…): берём файл прямо с диска, только из папки img
function order_img_local($u) {
    if (!preg_match('~^https?://([a-z0-9.-]+)(:\d+)?(/img/[A-Za-z0-9_./-]+\.(?:jpe?g|png|webp))$~i', (string)$u, $m)) return '';
    $own = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $h = strtolower($m[1]);
    if ($h !== $own && !preg_match('~(^|\.)itcpartner\.ru$~', $h) && getenv('ITC_STAND') !== '1') return '';
    $root = realpath(dirname(__DIR__) . '/img'); $f = realpath(dirname(__DIR__) . $m[3]);
    return $root && $f && strpos($f, $root . DIRECTORY_SEPARATOR) === 0 && is_file($f) ? $f : '';
}

// Уменьшаем фото до 240 точек (если на сервере есть обработка картинок)
function order_shrink($b) {
    $sz = @getimagesizefromstring($b); if (!$sz) return null;
    $jpg = $b; $w = $sz[0]; $h = $sz[1];
    if (function_exists('imagecreatefromstring') && ($im = @imagecreatefromstring($b))) {
        $k = min(1, 240 / max($w, $h)); $nw = max(1, (int)round($w * $k)); $nh = max(1, (int)round($h * $k));
        $dst = imagecreatetruecolor($nw, $nh); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagejpeg($dst, null, 82); $jpg = ob_get_clean(); $w = $nw; $h = $nh;
        imagedestroy($im); imagedestroy($dst);
    } elseif ($sz[2] !== IMAGETYPE_JPEG) return null;
    return ['b' => $jpg, 'w' => $w, 'h' => $h];
}

// Скачиваем фото параллельно (не дольше 20 секунд всего), уменьшаем до 240 точек
function order_fetch_imgs($urls) {
    $out = []; $urls = array_slice(array_unique(array_filter($urls, 'order_img_ok')), 0, 80);
    foreach ($urls as $i => $u) if (($f = order_img_local($u)) !== '') { if (filesize($f) < 4000000 && ($r = order_shrink((string)file_get_contents($f)))) $out[$u] = $r; unset($urls[$i]); }
    if (!$urls || !function_exists('curl_multi_init')) return $out;
    $mh = curl_multi_init(); $hs = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 4000000, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
        curl_multi_add_handle($mh, $ch); $hs[$u] = $ch;
    }
    $t0 = microtime(true);
    do { $st = curl_multi_exec($mh, $run); if ($run) curl_multi_select($mh, 1.0); } while ($run && $st == CURLM_OK && microtime(true) - $t0 < 20);
    foreach ($hs as $u => $ch) {
        $b = curl_multi_getcontent($ch);
        if ($b && strlen($b) < 4000000 && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 && ($r = order_shrink($b))) $out[$u] = $r;
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

// Количество строкой: «3 уп. × 10 шт.» и «30 шт.»
// ---------- Запрос фабрике на китайском и ссылка на поставщика в базе (3.43.0) ----------
// Экосистема лежит на хостинге магазина; ссылка ?sup=ИД сразу открывает карточку поставщика (Экосистема 4.10.8+)
const ORDER_ECO_URL = 'https://itcpartner.store/eco/';

// Страница фабрики из базы: ИД поставщика и словарь «русский / английский / китайский → китайский»
// для названий товаров и вариантов. Нет базы или страницы — null, письмо уходит как раньше.
function order_sup_ctx($slug) {
    $slug = (string)$slug;
    if (!preg_match('~^[a-z0-9-]{1,80}$~', $slug)) return null;
    if (!function_exists('sup_site_db')) { if (!defined('ROOT')) define('ROOT', dirname(__DIR__)); require_once __DIR__ . '/supdb.php'; }
    $db = sup_site_db(); if (!$db) return null;
    try {
        $st = $db->prepare("SELECT w.sup_id, w.data, w.tr FROM itc_sup_web w JOIN itc_sup s ON s.id = w.sup_id AND s.deleted = 0
                            WHERE w.slug = ? AND w.status = 'live' LIMIT 1");
        $st->execute([$slug]); $r = $st->fetch();
    } catch (Throwable $e) { return null; }
    if (!$r) return null;
    $d = json_decode((string)$r['data'], true); $tr = json_decode((string)$r['tr'], true);
    if (!is_array($d)) return null;
    $en = (array)($tr['en'] ?? []); $zh = (array)($tr['zh'] ?? []);
    $map = []; $names = [];
    $put = function ($s) use (&$map, $en, $zh) {
        $s = (string)$s; if ($s === '') return;
        $z = (string)($zh[$s] ?? '') !== '' ? (string)$zh[$s] : $s;
        $map[$s] = $z; if ((string)($en[$s] ?? '') !== '') $map[(string)$en[$s]] = $z; $map[$z] = $z;
    };
    foreach ((array)($d['groups'] ?? []) as $g) foreach ((array)($g['items'] ?? []) as $it) {
        $put($it['name'] ?? '');
        foreach ((array)($it['opt'] ?? []) as $o) {
            $put($o['n'] ?? ''); $n = (string)($o['n'] ?? '');
            if ($n !== '') { $names[$n] = 1; if ((string)($en[$n] ?? '') !== '') $names[(string)$en[$n]] = 1; }
            foreach ((array)($o['v'] ?? []) as $v) $put($v);
        }
    }
    uksort($names, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });
    return ['sup' => (string)$r['sup_id'], 'brand' => (string)($d['brand'] ?? ''), 'map' => $map, 'names' => array_keys($names)];
}
// Строка варианта «Покрытие: Хром, Длина 1500 мм» — по частям на китайский; чего нет в словаре, остаётся как есть
function order_zh_v($v, $ctx) {
    if ((string)$v === '') return '';
    $out = [];
    foreach (explode(', ', $v) as $p) {
        if (isset($ctx['map'][$p])) { $out[] = $ctx['map'][$p]; continue; }
        $done = false;
        foreach ($ctx['names'] as $n) {
            if (strpos($p, $n . ': ') === 0 || strpos($p, $n . ' ') === 0) {
                $sep = strpos($p, $n . ': ') === 0 ? ': ' : ' '; $rest = substr($p, strlen($n) + strlen($sep));
                $out[] = ($ctx['map'][$n] ?? $n) . ($sep === ': ' ? '：' : ' ') . ($ctx['map'][$rest] ?? $rest); $done = true; break;
            }
        }
        if (!$done) $out[] = $p;
    }
    return implode('，', $out);
}
// Товары заявки на китайском: название и вариант по словарю страницы, количество — китайскими единицами
function order_zh_rows($rows, $ctx) {
    $out = [];
    foreach ($rows as $r) {
        $r['t'] = $ctx['map'][$r['t']] ?? $r['t'];
        $r['v'] = order_zh_v($r['v'], $ctx);
        $out[] = $r;
    }
    return $out;
}
function order_qty_zh($r) {
    $U = ['pcs' => '件', 'set' => '套', 'pair' => '双', 'm' => '米']; $u = $U[$r['u']] ?? '件';
    if (empty($r['q'])) return ['待定', '—'];
    return $r['k'] ? [$r['q'] . ' 箱 × ' . $r['k'] . ' ' . $u, ($r['q'] * $r['k']) . ' ' . $u] : [$r['q'] . ' ' . $u, $r['q'] . ' ' . $u];
}
// Просьба к фабрике: цена с завода (EXW) и всё, что нужно для расчёта доставки. Данных клиента здесь нет.
function order_zh_intro() {
    return ['您好！',
            '我们是俄罗斯进口公司 ITC GLOBAL。我们的客户对以下产品感兴趣，请帮忙报价：',
            '1. 出厂价（EXW，人民币），请注明是否含税；',
            '2. 最小起订量和生产周期（交期）；',
            '3. 包装方式和每箱数量；',
            '4. 外箱尺寸、每箱毛重和净重；',
            '5. 按下表数量计算的总箱数、总体积（立方米）和总毛重；',
            '6. 产品是否有认证证书（如 CE 等）。',
            '货物可能拼柜发运，先送到我们在佛山的仓库。',
            '谢谢！期待您的回复。'];
}
// Тот же запрос простым текстом — в конец письма и в сделку Битрикс24, чтобы скопировать в WeChat
function order_zh_text($zrows) {
    $t = implode("\n", order_zh_intro()) . "\n\n产品清单：\n";
    foreach ($zrows as $i => $r) { $q = order_qty_zh($r); $t .= ($i + 1) . '. ' . $r['t'] . ($r['v'] !== '' ? '（' . $r['v'] . '）' : '') . ' — ' . $q[1] . "\n"; }
    return $t . '共 ' . count($zrows) . ' 项';
}

function order_qty($r) {
    $U = ['pcs' => 'шт.', 'set' => 'компл.', 'pair' => 'пар', 'm' => 'м']; $u = $U[$r['u']] ?? 'шт.';
    if (empty($r['q'])) return ['уточнить', '—'];
    return $r['k'] ? [$r['q'] . ' уп. × ' . $r['k'] . ' ' . $u, ($r['q'] * $r['k']) . ' ' . $u] : [$r['q'] . ' ' . $u, $r['q'] . ' ' . $u];
}

// Файл Excel: шапка заявки и таблица (№, фото, товар, вариант, количество, всего)
// $zh = true — файл для фабрики на китайском (3.43.0): без данных клиента, с просьбой о цене и пустыми графами для ответа фабрики
function order_xlsx($info, $rows, $imgs, $zh = false) {
    if (!class_exists('ZipArchive')) return '';
    $x = function ($s) { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
    $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
    $cell = function ($ref, $v, $s = 0) use ($x) {
        return '<c r="' . $ref . '" t="inlineStr" s="' . $s . '"><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
    };
    $sd = ''; $r = 1;
    if ($zh) {
        $sd .= '<row r="1" ht="26" customHeight="1">' . $cell('A1', '询价单 — ' . $info['factory'], 1) . '</row>'; $r = 2;
        $sd .= '<row r="' . $r . '">' . $cell('A' . $r, '询价方', 2) . $cell('C' . $r, 'ITC GLOBAL（俄罗斯）', 3) . '</row>'; $r++;
        $sd .= '<row r="' . $r . '">' . $cell('A' . $r, '日期', 2) . $cell('C' . $r, date('Y-m-d'), 3) . '</row>'; $r += 2;
        foreach (order_zh_intro() as $ln) { $sd .= '<row r="' . $r . '">' . $cell('A' . $r, $ln, 3) . '</row>'; $r++; }
        $heads = ['序号', '图片', '产品', '规格', '数量', '合计', '出厂单价（元）', '每箱数量', '外箱尺寸（厘米）', '每箱毛重（公斤）'];
    } else {
        $sd .= '<row r="1" ht="26" customHeight="1">' . $cell('A1', 'Запрос по фабрике ' . $info['factory'], 1) . '</row>'; $r = 2;
        foreach ([['Клиент', $info['name']], ['Телефон', $info['phone']], ['Почта', $info['email']], ['Комментарий', $info['comment']],
                  ['Страница', $info['page']], ['Время', $info['time']]] as $p) {
            if ((string)$p[1] === '') continue;
            $sd .= '<row r="' . $r . '">' . $cell('A' . $r, $p[0], 2) . $cell('C' . $r, $p[1], 3) . '</row>'; $r++;
        }
        $heads = ['№', 'Фото', 'Товар', 'Артикул, вариант', 'Количество', 'Всего'];
    }
    $r++; $hr = $r;
    $sd .= '<row r="' . $r . '" ht="' . ($zh ? 34 : 22) . '" customHeight="1">';
    foreach ($heads as $i => $h) $sd .= $cell($cols[$i] . $r, $h, 4);
    $sd .= '</row>';
    $pics = []; $first = $r + 1;
    foreach ($rows as $i => $o) {
        $r++; $q = $zh ? order_qty_zh($o) : order_qty($o); $im = $imgs[$o['im'] ?? ''] ?? null;
        $sd .= '<row r="' . $r . '" ht="' . ($im ? 92 : 30) . '" customHeight="1">'
             . '<c r="A' . $r . '" s="5"><v>' . ($i + 1) . '</v></c>' . $cell('B' . $r, '', 5)
             . $cell('C' . $r, $o['t'], 6) . $cell('D' . $r, $o['v'], 9) . $cell('E' . $r, $q[0], 7) . $cell('F' . $r, $q[1], 8)
             . ($zh ? $cell('G' . $r, '', 5) . $cell('H' . $r, '', 5) . $cell('I' . $r, '', 5) . $cell('J' . $r, '', 5) : '') . '</row>';
        if ($im) $pics[] = ['row' => $r - 1, 'im' => $im];
    }
    $r += 2;
    $sd .= '<row r="' . $r . '">' . $cell('C' . $r, ($zh ? '共 ' . count($rows) . ' 项' : 'Всего позиций: ' . count($rows)), 2) . '</row>';
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr><sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $hr . '" topLeftCell="A' . $first . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols><col min="1" max="1" width="5" customWidth="1"/><col min="2" max="2" width="19" customWidth="1"/><col min="3" max="3" width="44" customWidth="1"/>'
        . '<col min="4" max="4" width="34" customWidth="1"/><col min="5" max="5" width="20" customWidth="1"/><col min="6" max="6" width="14" customWidth="1"/>'
        . ($zh ? '<col min="7" max="10" width="15" customWidth="1"/>' : '') . '</cols>'
        . '<sheetData>' . $sd . '</sheetData>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
        . ($pics ? '<drawing r:id="rId1"/>' : '') . '</worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="15"/><color rgb="FF112C93"/><name val="Arial"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF112C93"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFD0D4DC"/></left><right style="thin"><color rgb="FFD0D4DC"/></right><top style="thin"><color rgb="FFD0D4DC"/></top><bottom style="thin"><color rgb="FFD0D4DC"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="10">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                    // 0
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 1 заголовок
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                      // 2 подпись
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                    // 3 значение
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 4 шапка таблицы
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' // 5
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 6 товар
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 7
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center" wrapText="1"/></xf>' // 8
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 9 вариант
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Default Extension="jpeg" ContentType="image/jpeg"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . ($pics ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
        . '</Types>';
    $tmp = tempnam(sys_get_temp_dir(), 'itcx');
    $z = new ZipArchive();
    if ($z->open($tmp, ZipArchive::OVERWRITE) !== true) return '';
    $z->addFromString('[Content_Types].xml', $ct);
    $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . ($zh ? '询价' : 'Запрос') . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm.Print_Titles" localSheetId="0">\'' . ($zh ? '询价' : 'Запрос') . '\'!$' . $hr . ':$' . $hr . '</definedName></definedNames></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $z->addFromString('xl/styles.xml', $styles);
    $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
    if ($pics) {
        $z->addFromString('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/></Relationships>');
        $dr = ''; $rel = '';
        foreach ($pics as $n => $p) {
            $id = $n + 1; $k = min(1, 116 / max($p['im']['w'], $p['im']['h']));        // фото до 116 точек в ячейке
            $w = (int)round($p['im']['w'] * $k); $h = (int)round($p['im']['h'] * $k);
            $ox = max(0, (int)((138 - $w) / 2)) * 9525; $oy = max(0, (int)((122 - $h) / 2)) * 9525;
            $dr .= '<xdr:oneCellAnchor><xdr:from><xdr:col>1</xdr:col><xdr:colOff>' . $ox . '</xdr:colOff><xdr:row>' . $p['row'] . '</xdr:row><xdr:rowOff>' . $oy . '</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="' . ($w * 9525) . '" cy="' . ($h * 9525) . '"/><xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . ($id + 1) . '" name="Фото ' . $id . '"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId' . $id . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . ($w * 9525) . '" cy="' . ($h * 9525) . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
            $rel .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image' . $id . '.jpeg"/>';
            $z->addFromString('xl/media/image' . $id . '.jpeg', $p['im']['b']);
        }
        $z->addFromString('xl/drawings/drawing1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . $dr . '</xdr:wsDr>');
        $z->addFromString('xl/drawings/_rels/drawing1.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rel . '</Relationships>');
    }
    $z->close();
    $b = (string)@file_get_contents($tmp); @unlink($tmp);
    return $b;
}

// Письмо менеджеру с оформлением: данные клиента и таблица товаров с фото
// $ctx и $zrows (3.43.0): кнопка «Открыть поставщика в базе» и запрос фабрике на китайском
function order_html($info, $rows, $ctx = null, $zrows = []) {
    $h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $nw = function ($s) { return '<span style="white-space:nowrap">' . $s . '</span>'; };
    $kv = '';
    foreach ([['Клиент', $info['name']], ['Телефон', $info['phone']], ['Почта', $info['email']], ['Комментарий', $info['comment']]] as $p)
        if ((string)$p[1] !== '') $kv .= '<tr><td style="padding:3px 12px 3px 0;color:#777;font-size:14px;white-space:nowrap;vertical-align:top">' . $h($p[0]) . '</td><td style="padding:3px 0;font-size:15px;font-weight:bold">' . nl2br($h($p[1])) . '</td></tr>';
    $th = 'padding:8px 6px;border-bottom:2px solid #112C93;font-size:13px;color:#112C93;text-align:left;font-weight:bold';
    $td = 'padding:8px 6px;border-bottom:1px solid #e6e6e6;font-size:14px;line-height:1.4;color:#1b1b1b;vertical-align:middle';
    $li = '<tr><th style="' . $th . ';width:24px">№</th><th style="' . $th . ';width:84px">Фото</th><th style="' . $th . '">Товар</th><th style="' . $th . ';text-align:right">Количество</th><th style="' . $th . ';text-align:right">Всего</th></tr>';
    foreach ($rows as $i => $r) {
        $q = order_qty($r);
        $img = ($r['im'] ?? '') !== '' && order_img_ok($r['im']) ? '<img src="' . $h($r['im']) . '" width="80" alt="" style="display:block;width:80px;height:auto;border:0;border-radius:6px">' : '';
        $li .= '<tr><td style="' . $td . ';color:#777">' . ($i + 1) . '</td><td style="' . $td . '">' . $img . '</td>'
             . '<td style="' . $td . '"><b>' . $h($r['t']) . '</b>' . ($r['v'] !== '' ? '<br><span style="color:#555;font-size:13px">' . $h($r['v']) . '</span>' : '') . '</td>'
             . '<td style="' . $td . ';text-align:right">' . $nw($h($q[0])) . '</td><td style="' . $td . ';text-align:right;font-weight:bold">' . $nw($h($q[1])) . '</td></tr>';
    }
    $li .= '<tr><td colspan="5" style="padding:10px 6px 0;font-size:14px;color:#555;text-align:right">Всего позиций: <b>' . count($rows) . '</b></td></tr>';
    return '<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#f4f5f4">'
         . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f4"><tr><td align="center" style="padding:20px 10px">'
         . '<table width="720" cellpadding="0" cellspacing="0" style="max-width:720px;width:100%;background:#ffffff;border-radius:12px;font-family:Arial,Helvetica,sans-serif;color:#1b1b1b">'
         . '<tr><td style="padding:22px 26px 6px;font-size:20px;font-weight:bold;color:#112C93">Запрос по фабрике ' . $h($info['factory']) . '</td></tr>'
         . '<tr><td style="padding:6px 26px 14px"><table cellpadding="0" cellspacing="0">' . $kv . '</table></td></tr>'
         . (!empty($ctx['sup']) ? '<tr><td style="padding:0 26px 16px"><a href="' . $h(ORDER_ECO_URL . '?sup=' . rawurlencode($ctx['sup'])) . '" style="display:inline-block;background:#112C93;color:#ffffff;text-decoration:none;font-size:15px;font-weight:bold;padding:11px 20px;border-radius:4px">Открыть поставщика в базе</a>'
             . '<div style="font-size:12px;color:#777;padding-top:6px">Карточка ' . $h($ctx['brand'] ?: $info['factory']) . ' в Экосистеме: контакты, WeChat, каталоги. Нужен вход в Экосистему.</div></td></tr>' : '')
         . '<tr><td style="padding:0 26px 8px"><table width="100%" cellpadding="0" cellspacing="0">' . $li . '</table></td></tr>'
         . '<tr><td style="padding:14px 26px 22px;font-size:13px;line-height:1.6;color:#777;border-top:1px solid #e6e6e6">'
         . 'Тот же список с фото — в приложенном файле Excel.<br>'
         . ($info['page'] !== '' ? 'Страница: <a href="' . $h($info['page']) . '" style="color:#112C93">' . $h($info['page']) . '</a><br>' : '')
         . $h($info['extra']) . 'Время: ' . $h($info['time']) . '</td></tr>'
         . ($zrows ? order_zh_html($zrows, $h) : '')
         . '</table></td></tr></table></body></html>';
}

// Блок письма «Запрос фабрике на китайском»: просьба о цене и товары с фото — скопировать и переслать в WeChat
function order_zh_html($zrows, $h) {
    $td = 'padding:6px;border-bottom:1px solid #e6e6e6;font-size:14px;line-height:1.4;color:#1b1b1b;vertical-align:middle';
    $li = '';
    foreach ($zrows as $i => $r) {
        $q = order_qty_zh($r);
        $img = ($r['im'] ?? '') !== '' && order_img_ok($r['im']) ? '<img src="' . $h($r['im']) . '" width="64" alt="" style="display:block;width:64px;height:auto;border:0;border-radius:6px">' : '';
        $li .= '<tr><td style="' . $td . ';color:#777;width:22px">' . ($i + 1) . '</td><td style="' . $td . ';width:70px">' . $img . '</td><td style="' . $td . '"><b>' . $h($r['t']) . '</b>'
             . ($r['v'] !== '' ? '<br><span style="color:#555;font-size:13px">' . $h($r['v']) . '</span>' : '') . '</td><td style="' . $td . ';text-align:right;white-space:nowrap;font-weight:bold">' . $h($q[1]) . '</td></tr>';
    }
    return '<tr><td style="padding:18px 26px 6px;border-top:6px solid #f4f5f4"><div style="font-size:17px;font-weight:bold;color:#112C93">Запрос фабрике на китайском</div>'
         . '<div style="font-size:13px;color:#777;padding:4px 0 10px">Без данных клиента. Скопируйте текст ниже и перешлите фабрике в WeChat или отправьте ей второй файл Excel «询价…» — в нём есть графы для ответа фабрики.</div>'
         . '<div style="background:#f6f7fb;border-left:4px solid #112C93;padding:12px 14px;font-size:14px;line-height:1.7;color:#1b1b1b">' . implode('<br>', array_map($h, order_zh_intro())) . '</div></td></tr>'
         . '<tr><td style="padding:6px 26px 22px"><div style="font-size:14px;font-weight:bold;padding:4px 0">产品清单：</div><table width="100%" cellpadding="0" cellspacing="0">' . $li
         . '<tr><td colspan="4" style="padding:8px 6px 0;font-size:14px;color:#555;text-align:right">共 <b>' . count($zrows) . '</b> 项</td></tr></table></td></tr>';
}

// Отправка: текст + оформленное письмо + файл Excel (если собрался)
function order_send($to, $subject, $headers, $txt, $html, $xlsx, $fname, $param = '', $more = []) {
    $b1 = 'itm' . bin2hex(random_bytes(8)); $b2 = 'ita' . bin2hex(random_bytes(8));
    $alt = "--{$b2}\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($txt))
         . "--{$b2}\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
         . "--{$b2}--\r\n";
    // вложения: файл Excel менеджеру и (3.43.0) файл для фабрики на китайском
    $files = [];
    if ($xlsx !== '') $files[] = [$fname, $xlsx];
    foreach ($more as $m) if (($m[1] ?? '') !== '') $files[] = $m;
    if ($files) {
        $hd = $headers . "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$b1}\"\r\n";
        $body = "--{$b1}\r\nContent-Type: multipart/alternative; boundary=\"{$b2}\"\r\n\r\n" . $alt;
        foreach ($files as $f) {
            $fn = '=?UTF-8?B?' . base64_encode($f[0]) . '?=';
            $body .= "--{$b1}\r\nContent-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; name=\"{$fn}\"\r\n"
                   . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$fn}\"\r\n\r\n" . chunk_split(base64_encode($f[1]));
        }
        $body .= "--{$b1}--\r\n";
    } else {
        $hd = $headers . "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"{$b2}\"\r\n";
        $body = $alt;
    }
    return $param !== '' ? (bool)@mail($to, $subject, $body, $hd, $param) : (bool)@mail($to, $subject, $body, $hd);
}
