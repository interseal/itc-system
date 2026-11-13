<?php
// ==== ИТК · ПОТЕНЦИАЛЬНЫЕ КЛИЕНТЫ · leads.php · №4 (02.11.2026) · patch: звёзды и фото для карточек без фото ====
// Раздел Экосистемы «Потенциальные клиенты»: база компаний по сегментам (гостиницы, рестораны,
// бары, стройка, строительные магазины…) для коммерческих предложений и рассылок.
// Карточка — как страница сайта: фото объекта и номеров, описание, номера, услуги, все контакты.
//
//  Вызовы:  GET/POST /api/leads.php?action=...&t=<пропуск сотрудника>
//    lead_list       вся база + разделы + права
//    lead_save       создать / изменить карточку
//    lead_status     быстро сменить статус и заметку
//    lead_delete     убрать (только босс)
//    lead_seg_add    новый сегмент
//    lead_export     скачать базу в Excel (.xlsx) с учётом фильтров — только босс
//    lead_seed_run   перенос собранной базы из /eco/lead_seed/leads.php (только босс);
//                    фото с сайтов объектов сервер скачивает сам в хранилище, порциями
//
//  Таблицы заводятся сами: itc_lead, itc_lead_seg.
//  Фото лежат в том же хранилище, что и фото магазина (папка lead/).

require_once __DIR__ . '/config.php';
$action = $_GET['action'] ?? '';

function ld_me() {
  $s = authUser('itc');
  $r = $s['staff_role'] ?? '';
  $boss = ($r === 'boss' || $r === '');
  $who = trim((string)($s['name'] ?? '')) ?: (trim((string)($s['phone'] ?? '')) ?: 'сотрудник');
  return ['role' => $r, 'who' => $who, 'boss' => $boss,
          'edit' => $boss || in_array($r, ['manager', 'supervisor', 'moderator', 'sales'], true)];
}

function ld_ensure() {
  static $done = false; if ($done) return; $done = true;
  $db = db();
  $db->exec("CREATE TABLE IF NOT EXISTS itc_lead (
    id VARCHAR(40) NOT NULL PRIMARY KEY,
    seg VARCHAR(30) NOT NULL DEFAULT 'hotel',
    name VARCHAR(250) NOT NULL DEFAULT '',
    type VARCHAR(80) NOT NULL DEFAULT '',
    region VARCHAR(120) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    locality VARCHAR(160) NOT NULL DEFAULT '',
    addr VARCHAR(400) NOT NULL DEFAULT '',
    stars VARCHAR(20) NOT NULL DEFAULT '',
    rooms VARCHAR(40) NOT NULL DEFAULT '',
    site VARCHAR(300) NOT NULL DEFAULT '',
    email VARCHAR(200) NOT NULL DEFAULT '',
    email2 VARCHAR(200) NOT NULL DEFAULT '',
    phone VARCHAR(120) NOT NULL DEFAULT '',
    phone2 VARCHAR(120) NOT NULL DEFAULT '',
    legal VARCHAR(300) NOT NULL DEFAULT '',
    chain VARCHAR(160) NOT NULL DEFAULT '',
    descr TEXT NULL,
    room_types TEXT NULL,
    services TEXT NULL,
    socials TEXT NULL,
    photos MEDIUMTEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    note TEXT NULL,
    source VARCHAR(400) NOT NULL DEFAULT '',
    created_by VARCHAR(190) NOT NULL DEFAULT '',
    updated_by VARCHAR(190) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted TINYINT NOT NULL DEFAULT 0,
    KEY idx_lead_seg (seg), KEY idx_lead_city (city)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $db->exec("CREATE TABLE IF NOT EXISTS itc_lead_seg (
    code VARCHAR(30) NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL DEFAULT '',
    sort INT NOT NULL DEFAULT 100
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $q = $db->prepare('INSERT IGNORE INTO itc_lead_seg (code,name,sort) VALUES (?,?,?)');
  foreach ([['hotel', 'Гостиницы и санатории', 10], ['rest', 'Рестораны и кафе', 20], ['bar', 'Бары', 30],
            ['build', 'Строительные компании', 40], ['bshop', 'Строительные магазины', 50], ['other', 'Другое', 999]] as $d) $q->execute($d);
}

function ld_s($v, $len) { return mb_substr(trim((string)($v ?? '')), 0, $len); }
function ld_dec($v) { $d = json_decode((string)$v, true); return is_array($d) ? $d : []; }
const LD_STATUS = ['new', 'sent', 'opened', 'replied', 'work', 'client', 'refused', 'unsub'];

function ld_out($r) {
  return ['id' => $r['id'], 'seg' => $r['seg'], 'name' => $r['name'], 'type' => $r['type'], 'region' => $r['region'],
    'city' => $r['city'], 'locality' => $r['locality'], 'addr' => $r['addr'], 'stars' => $r['stars'], 'rooms' => $r['rooms'],
    'site' => $r['site'], 'email' => $r['email'], 'email2' => $r['email2'], 'phone' => $r['phone'], 'phone2' => $r['phone2'],
    'legal' => $r['legal'], 'chain' => $r['chain'], 'descr' => (string)$r['descr'], 'room_types' => (string)$r['room_types'],
    'services' => (string)$r['services'], 'socials' => (string)$r['socials'],
        // пока фото не докачано в наше хранилище — показываем его с сайта объекта
    'photos' => array_values(array_filter(array_map(function ($p) { return $p['url'] ?? ($p['src'] ?? ($p['ext'] ?? '')); }, ld_dec($r['photos'])))),
    'status' => $r['status'], 'note' => (string)$r['note'], 'source' => $r['source'],
    'updated_by' => $r['updated_by'], 'updated_at' => $r['updated_at']];
}

/* Поля карточки из массива (форма или собранная база). Возвращает id. */
function ld_write($d, $who, $forceId = '') {
  $id = $forceId !== '' ? $forceId : ld_s($d['id'] ?? '', 40);
  $isNew = true;
  if ($id === '') $id = 'l' . bin2hex(random_bytes(6));
  else { $q = db()->prepare('SELECT COUNT(*) FROM itc_lead WHERE id=?'); $q->execute([$id]); $isNew = !$q->fetchColumn(); }
  $f = [
    'seg' => ld_s($d['seg'] ?? 'hotel', 30) ?: 'hotel', 'name' => ld_s($d['name'] ?? '', 250), 'type' => ld_s($d['type'] ?? '', 80),
    'region' => ld_s($d['region'] ?? '', 120), 'city' => ld_s($d['city'] ?? '', 120), 'locality' => ld_s($d['locality'] ?? '', 160),
    'addr' => ld_s($d['addr'] ?? ($d['address'] ?? ''), 400), 'stars' => ld_s($d['stars'] ?? '', 20), 'rooms' => ld_s($d['rooms'] ?? '', 40),
    'site' => ld_s($d['site'] ?? '', 300), 'email' => ld_s($d['email'] ?? '', 200), 'email2' => ld_s($d['email2'] ?? ($d['email_purchase'] ?? ''), 200),
    'phone' => ld_s($d['phone'] ?? '', 120), 'phone2' => ld_s($d['phone2'] ?? '', 120), 'legal' => ld_s($d['legal'] ?? '', 300),
    'chain' => ld_s($d['chain'] ?? '', 160), 'descr' => ld_s($d['descr'] ?? '', 4000), 'room_types' => ld_s($d['room_types'] ?? '', 1500),
    'services' => ld_s($d['services'] ?? '', 2000), 'socials' => ld_s($d['socials'] ?? '', 600),
    'note' => ld_s($d['note'] ?? '', 4000), 'source' => ld_s($d['source'] ?? '', 400),
  ];
  if ($f['name'] === '') fail('Укажите название');
  if ($isNew) {
    $f['status'] = in_array($d['status'] ?? '', LD_STATUS, true) ? $d['status'] : 'new';
    $f['photos'] = json_encode(array_map(function ($u) { return ['src' => (string)$u]; }, array_slice((array)($d['photos'] ?? []), 0, 8)), JSON_UNESCAPED_UNICODE);
    $cols = array_keys($f);
    db()->prepare('INSERT INTO itc_lead (id,' . implode(',', $cols) . ',created_by,updated_by) VALUES (?' . str_repeat(',?', count($cols)) . ',?,?)')
        ->execute(array_merge([$id], array_values($f), [$who, $who]));
  } else {
    if (isset($d['status']) && in_array($d['status'], LD_STATUS, true)) $f['status'] = $d['status'];
    $set = implode(',', array_map(function ($c) { return "$c=?"; }, array_keys($f)));
    db()->prepare("UPDATE itc_lead SET $set, updated_by=?, updated_at=NOW(), deleted=0 WHERE id=?")->execute(array_merge(array_values($f), [$who, $id]));
  }
  return $id;
}

/* ---------- хранилище (как у базы поставщиков) ---------- */
function ld_s3_put($key, $path, $ctype) {
  global $S3_ENABLED, $S3_ENDPOINT, $S3_REGION, $S3_BUCKET, $S3_ACCESS_KEY, $S3_SECRET_KEY;
  if (empty($S3_ENABLED)) return 'хранилище выключено';
  $size = @filesize($path); if (!$size) return 'пустой файл';
  $host = $S3_ENDPOINT; $region = $S3_REGION ?: 'ru-1'; $service = 's3';
  $hash = hash_file('sha256', $path); $amzDate = gmdate('Ymd\THis\Z'); $dateStamp = gmdate('Ymd'); $acl = 'public-read';
  $uri = '/' . $S3_BUCKET . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
  $ch_h = "content-type:$ctype\nhost:$host\nx-amz-acl:$acl\nx-amz-content-sha256:$hash\nx-amz-date:$amzDate\n";
  $signed = 'content-type;host;x-amz-acl;x-amz-content-sha256;x-amz-date';
  $creq = "PUT\n$uri\n\n$ch_h\n$signed\n$hash"; $scope = "$dateStamp/$region/$service/aws4_request";
  $sts = "AWS4-HMAC-SHA256\n$amzDate\n$scope\n" . hash('sha256', $creq);
  $k = hash_hmac('sha256', $dateStamp, 'AWS4' . $S3_SECRET_KEY, true); $k = hash_hmac('sha256', $region, $k, true);
  $k = hash_hmac('sha256', $service, $k, true); $k = hash_hmac('sha256', 'aws4_request', $k, true);
  $sig = hash_hmac('sha256', $sts, $k);
  $auth = "AWS4-HMAC-SHA256 Credential=$S3_ACCESS_KEY/$scope, SignedHeaders=$signed, Signature=$sig";
  $fp = fopen($path, 'rb'); if (!$fp) return 'не открыть файл';
  $ch = curl_init("https://$host$uri");
  curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fp, CURLOPT_INFILESIZE => $size, CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => ["Content-Type: $ctype", "x-amz-acl: $acl", "x-amz-content-sha256: $hash", "x-amz-date: $amzDate", "Authorization: $auth", 'Expect:']]);
  curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
  return ($code >= 200 && $code < 300) ? true : 'хранилище ответило ' . $code;
}
function ld_public_url($key) {
  global $S3_PUBLIC_URL;
  return rtrim((string)$S3_PUBLIC_URL, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
}
function ld_tmp() {
  $dir = dirname(__DIR__) . '/uploads/lead_tmp';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");
  return $dir;
}
/* Скачать фото с сайта объекта, ужать до 1600 px (jpeg) и положить в хранилище. Возвращает ссылку или текст ошибки. */
function ld_grab_photo($leadId, $url) {
  if (!preg_match('~^https?://~i', $url)) return 'не ссылка';
  $tmp = ld_tmp() . '/p_' . bin2hex(random_bytes(6));
  $fh = @fopen($tmp, 'wb'); if (!$fh) return 'нет места';
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
    CURLOPT_HTTPHEADER => ['Accept: image/avif,image/webp,image/*,*/*;q=0.8'], CURLOPT_NOPROGRESS => false,
    CURLOPT_PROGRESSFUNCTION => function ($c, $dt, $d) { return $d > 15 * 1024 * 1024 ? 1 : 0; }]);
  $ok = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
  if (!$ok || $code >= 400) { @unlink($tmp); return 'не скачалось (' . $code . ')'; }
  $info = @getimagesize($tmp);
  $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
  if (!$info || !isset($map[$info[2]])) { @unlink($tmp); return 'это не картинка'; }
  if ($info[0] < 400 || $info[1] < 250) { @unlink($tmp); return 'маленькая картинка'; }
  $ext = $map[$info[2]]; $ctype = $info['mime'];
  // ужимаем крупные фото: быстрее открываются на телефоне
  if (function_exists('imagecreatetruecolor') && ($info[0] > 1600 || filesize($tmp) > 900000)) {
    $src = $ext === 'jpg' ? @imagecreatefromjpeg($tmp) : ($ext === 'png' ? @imagecreatefrompng($tmp) : (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null));
    if ($src) {
      $w = imagesx($src); $h = imagesy($src); $nw = min(1600, $w); $nh = (int)round($h * $nw / $w);
      $dst = imagecreatetruecolor($nw, $nh); imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
      imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
      if (@imagejpeg($dst, $tmp . '.jpg', 82)) { @unlink($tmp); $tmp .= '.jpg'; $ext = 'jpg'; $ctype = 'image/jpeg'; }
      imagedestroy($src); imagedestroy($dst);
    }
  }
  $key = 'lead/' . preg_replace('/[^a-z0-9_\-]/i', '', $leadId) . '/' . substr(md5($url), 0, 12) . '.' . $ext;
  $put = ld_s3_put($key, $tmp, $ctype); @unlink($tmp);
  return $put === true ? ['url' => ld_public_url($key)] : $put;
}

/* База лежит в .php-файле с первой строкой «<?php exit; ?>» — снаружи её не прочитать. */
function ld_seed_file() { return dirname(__DIR__) . '/eco/lead_seed/leads.php'; }

/* №2 · Выгрузка базы в Excel (.xlsx) — только босс. Учитывает выбранный сегмент, город и статус. */
function ld_xlsx($head, $rows, $title) {
  $col = function ($i) { $s = ''; $i++; while ($i > 0) { $m = ($i - 1) % 26; $s = chr(65 + $m) . $s; $i = intdiv($i - 1, 26); } return $s; };
  $x = function ($v) { return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string)$v), ENT_XML1 | ENT_QUOTES, 'UTF-8'); };
  $cols = ''; foreach ($head as $i => $h) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $h[1] . '" customWidth="1"/>';
  $sd = '<row r="1">'; foreach ($head as $i => $h) $sd .= '<c r="' . $col($i) . '1" t="inlineStr" s="1"><is><t>' . $x($h[0]) . '</t></is></c>'; $sd .= '</row>';
  foreach ($rows as $n => $r) {
    $rn = $n + 2; $sd .= '<row r="' . $rn . '">';
    foreach ($r as $i => $v) if ((string)$v !== '') $sd .= '<c r="' . $col($i) . $rn . '" t="inlineStr" s="2"><is><t xml:space="preserve">' . $x($v) . '</t></is></c>';
    $sd .= '</row>';
  }
  $last = $col(count($head) - 1) . (count($rows) + 1);
  $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0"><pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
    . '<cols>' . $cols . '</cols><sheetData>' . $sd . '</sheetData><autoFilter ref="A1:' . $last . '"/>'
    . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
  $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts>'
    . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF112C93"/></patternFill></fill></fills>'
    . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
    . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment wrapText="1" vertical="center"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
  $tmp = ld_tmp() . '/x_' . bin2hex(random_bytes(6)) . '.xlsx';
  $z = new ZipArchive(); $z->open($tmp, ZipArchive::CREATE);
  $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
  $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
  $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $x(mb_substr($title, 0, 31)) . '" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $x(mb_substr($title, 0, 31)) . '\'!$A$1:$' . preg_replace('/\d+/', '', $last) . '$' . (count($rows) + 1) . '</definedName></definedNames></workbook>');
  $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
  $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
  $z->addFromString('xl/styles.xml', $styles);
  $z->close();
  return $tmp;
}

/* Завести карточки из файла базы, который пришёл с обновлением. Кто уже есть — не трогаем,
   кроме полей из «patch» (например, звёзды). Возвращает число заведённых и исправленных. */
function ld_import_seed($who) {
  $f = ld_seed_file(); if (!is_file($f)) return 0;
  $lk = @fopen(ld_tmp() . '/seed.lock', 'c'); if ($lk && !flock($lk, LOCK_EX | LOCK_NB)) return 0;
  if (!is_file($f)) return 0;
  $raw = (string)file_get_contents($f); $raw = substr($raw, (int)strpos($raw, "\n") + 1);
  $seed = json_decode($raw, true);
  if (!is_array($seed)) { @rename($f, $f . '.bad'); return 0; }
  $n = 0;
  $sq = db()->prepare('INSERT IGNORE INTO itc_lead_seg (code,name,sort) VALUES (?,?,?)');
  foreach (($seed['segs'] ?? []) as $sg) $sq->execute([ld_s($sg['code'] ?? '', 30), ld_s($sg['name'] ?? '', 120), (int)($sg['sort'] ?? 500)]);
  $ex = db()->prepare('SELECT COUNT(*) FROM itc_lead WHERE id=?');
  foreach (($seed['leads'] ?? []) as $l) {
    $lid = ld_s($l['id'] ?? '', 40); if ($lid === '') continue;
    $ex->execute([$lid]);
    if (!$ex->fetchColumn() || !empty($l['replace'])) { ld_write($l, $who, $lid); $n++; }
  }
  $allow = ['stars' => 20, 'rooms' => 40, 'type' => 80, 'chain' => 160, 'legal' => 300, 'email' => 200, 'email2' => 200, 'phone' => 120, 'site' => 300];
  foreach (($seed['patch'] ?? []) as $pt) {
    $lid = ld_s($pt['id'] ?? '', 40); if ($lid === '') continue;
    foreach ($allow as $k => $len) if (isset($pt[$k])) {
      db()->prepare("UPDATE itc_lead SET $k=? WHERE id=?")->execute([ld_s($pt[$k], $len), $lid]); $n++;
    }
    // фото — только тем карточкам, где своих фото ещё нет (сервер потом сам скачает их в хранилище)
    if (!empty($pt['photos']) && is_array($pt['photos'])) {
      $q = db()->prepare('SELECT photos FROM itc_lead WHERE id=?'); $q->execute([$lid]); $cur = ld_dec($q->fetchColumn());
      if (!$cur) {
        $ph = []; foreach (array_slice($pt['photos'], 0, 6) as $u) if (preg_match('~^https?://~i', (string)$u)) $ph[] = ['src' => (string)$u];
        if ($ph) { db()->prepare('UPDATE itc_lead SET photos=? WHERE id=?')->execute([json_encode($ph, JSON_UNESCAPED_UNICODE), $lid]); $n++; }
      }
    }
  }
  @unlink($f); @rmdir(dirname($f));
  return $n;
}
/* Порция фото: скачать с сайтов объектов в наше хранилище, не дольше $sec секунд.
   Если порцию уже качает другой вызов — сразу выходим. */
function ld_photo_batch($sec) {
  $lk = @fopen(ld_tmp() . '/photos.lock', 'c');
  if ($lk && !flock($lk, LOCK_EX | LOCK_NB)) return ['photos' => 0, 'bad' => 0, 'left' => 1, 'busy' => true, 'finished' => false];
  $t0 = time(); $got = 0; $bad = 0; $left = 0;
  $q = db()->query("SELECT id,photos FROM itc_lead WHERE deleted=0 AND photos LIKE '%\"src\"%'");
  $upd = db()->prepare('UPDATE itc_lead SET photos=? WHERE id=?');
  foreach ($q->fetchAll() as $r) {
    $ph = ld_dec($r['photos']); $ch = false; $out = [];
    foreach ($ph as $p) {
      if (!empty($p['url']) || empty($p['src'])) { if (!empty($p['url']) || !empty($p['ext'])) $out[] = $p; continue; }
      if (time() - $t0 > $sec) { $out[] = $p; $left++; continue; }
      $res = ld_grab_photo($r['id'], $p['src']); $ch = true;
      // не скачалось (сайт не отдаёт) — оставляем ссылку на сайт объекта, повторно не пытаемся
      if (is_array($res)) { $out[] = ['url' => $res['url']]; $got++; } else { $out[] = ['ext' => $p['src']]; $bad++; }
    }
    if ($ch) $upd->execute([json_encode($out, JSON_UNESCAPED_UNICODE), $r['id']]);
  }
  return ['photos' => $got, 'bad' => $bad, 'left' => $left, 'finished' => $left === 0];
}

try {
  switch ($action) {
  case 'lead_export': {
    $me = ld_me(); ld_ensure();
    if (!$me['boss']) fail('Выгрузка базы — только для босса', 403);
    if (!class_exists('ZipArchive')) fail('На сервере нет ZipArchive');
    $seg = ld_s($_GET['seg'] ?? '', 30); $city = ld_s($_GET['city'] ?? '', 120); $st = ld_s($_GET['st'] ?? '', 20);
    $sql = 'SELECT * FROM itc_lead WHERE deleted=0'; $p = [];
    if ($seg !== '' && $seg !== 'all') { $sql .= ' AND seg=?'; $p[] = $seg; }
    if ($city !== '') { $sql .= ' AND city=?'; $p[] = $city; }
    if ($st !== '') { $sql .= ' AND status=?'; $p[] = $st; }
    $q = db()->prepare($sql . ' ORDER BY city,locality,name'); $q->execute($p);
    $segs = []; foreach (db()->query('SELECT code,name FROM itc_lead_seg')->fetchAll() as $g) $segs[$g['code']] = $g['name'];
    $stn = ['new' => 'Не писали', 'sent' => 'КП отправлено', 'opened' => 'Открыл КП', 'replied' => 'Ответил', 'work' => 'В работе', 'client' => 'Клиент', 'refused' => 'Отказ', 'unsub' => 'Отписался'];
    $head = [['№', 5], ['Название', 32], ['Сегмент', 16], ['Тип', 14], ['Город', 14], ['Посёлок / район', 16], ['Адрес', 30], ['Звёзды', 7], ['Номеров', 8], ['Сеть', 14],
             ['Телефон', 18], ['Второй телефон', 16], ['Почта', 26], ['Почта закупок', 22], ['Сайт', 24], ['Соцсети', 20], ['Юрлицо', 28],
             ['Статус', 13], ['Заметки', 30], ['Описание', 45], ['Номера', 30], ['Услуги', 35], ['Откуда данные', 28], ['Изменено', 16]];
    $rows = []; $n = 0;
    foreach ($q->fetchAll() as $r) {
      $rows[] = [++$n, $r['name'], $segs[$r['seg']] ?? $r['seg'], $r['type'], $r['city'], $r['locality'], $r['addr'], $r['stars'], $r['rooms'], $r['chain'],
        $r['phone'], $r['phone2'], $r['email'], $r['email2'], $r['site'], $r['socials'], $r['legal'],
        $stn[$r['status']] ?? $r['status'], $r['note'], $r['descr'], $r['room_types'], $r['services'], $r['source'], $r['updated_at']];
    }
    $f = ld_xlsx($head, $rows, 'Потенциальные клиенты');
    $name = 'Potencialnye_klienty_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: no-store');
    readfile($f); @unlink($f); exit;
  }
  case 'lead_list': {
    $me = ld_me(); ld_ensure();
    ld_import_seed('Claude (обновление)');   // база, пришедшая с обновлением, заводится сама
    $segs = db()->query('SELECT code,name,sort FROM itc_lead_seg ORDER BY sort,name')->fetchAll();
    $rows = [];
    foreach (db()->query('SELECT * FROM itc_lead WHERE deleted=0 ORDER BY city,name')->fetchAll() as $r) $rows[] = ld_out($r);
    $pend = (int)db()->query("SELECT COUNT(*) FROM itc_lead WHERE deleted=0 AND photos LIKE '%\"src\"%'")->fetchColumn();
    jout(['ok' => true, 'rows' => $rows, 'segs' => $segs, 'me' => ['edit' => $me['edit'], 'boss' => $me['boss'], 'who' => $me['who']],
          'photos_wait' => $pend]);
  }
  case 'lead_save': {
    $me = ld_me(); ld_ensure();
    if (!$me['edit']) fail('Изменять базу могут менеджер, супервайзер, модератор и босс', 403);
    $id = ld_write(body()['lead'] ?? [], $me['who']);
    $q = db()->prepare('SELECT * FROM itc_lead WHERE id=?'); $q->execute([$id]);
    jout(['ok' => true, 'lead' => ld_out($q->fetch())]);
  }
  case 'lead_status': {
    $me = ld_me(); ld_ensure();
    $d = body(); $id = ld_s($d['id'] ?? '', 40);
    if (isset($d['status'])) {
      if (!in_array($d['status'], LD_STATUS, true)) fail('Нет такого статуса');
      db()->prepare('UPDATE itc_lead SET status=?, updated_by=?, updated_at=NOW() WHERE id=?')->execute([$d['status'], $me['who'], $id]);
    }
    if (isset($d['note'])) db()->prepare('UPDATE itc_lead SET note=?, updated_by=?, updated_at=NOW() WHERE id=?')->execute([ld_s($d['note'], 4000), $me['who'], $id]);
    jout(['ok' => true]);
  }
  case 'lead_delete': {
    $me = ld_me(); ld_ensure();
    if (!$me['boss']) fail('Удалять может только босс', 403);
    db()->prepare('UPDATE itc_lead SET deleted=1, updated_by=?, updated_at=NOW() WHERE id=?')->execute([$me['who'], ld_s(body()['id'] ?? '', 40)]);
    jout(['ok' => true]);
  }
  case 'lead_seg_add': {
    $me = ld_me(); ld_ensure();
    if (!$me['edit']) fail('Недостаточно прав', 403);
    $name = ld_s(body()['name'] ?? '', 120); if ($name === '') fail('Впишите название сегмента');
    $q = db()->prepare('SELECT code FROM itc_lead_seg WHERE name=?'); $q->execute([$name]); $code = $q->fetchColumn();
    if (!$code) { $code = 'g' . bin2hex(random_bytes(4)); db()->prepare('INSERT INTO itc_lead_seg (code,name,sort) VALUES (?,?,500)')->execute([$code, $name]); }
    jout(['ok' => true, 'seg' => ['code' => $code, 'name' => $name, 'sort' => 500]]);
  }
  /* Перенос собранной базы. Первый вызов заводит карточки (кого уже нет), дальше каждый вызов
     скачивает порцию фото (до ~70 секунд). Страница зовёт, пока не скажем finished. */
  /* Перенос базы и фото. Карточки заводятся сами при первом открытии раздела (lead_list),
     фото докачиваются в фоне (lead_photos), а страница «Обновления» после установки
     зовёт lead_seed_run, пока не скажем finished. Руками ничего нажимать не нужно. */
  case 'lead_seed_run': {
    $me = ld_me(); ld_ensure(); @set_time_limit(180);
    $added = ld_import_seed($me['who']);
    $r = ld_photo_batch(70);
    jout(['ok' => true, 'added' => $added] + $r);
  }
  case 'lead_photos': {
    ld_me(); ld_ensure(); @set_time_limit(60);
    jout(['ok' => true] + ld_photo_batch(25));
  }
  default: fail('Неизвестное действие', 404);
  }
} catch (Throwable $e) {
  fail('Ошибка сервера: ' . $e->getMessage(), 500);
}
