<?php
// ==== ИТК · Регистрации на сайте itcpartner.ru — для босса (Управление → «Регистрации на сайте ИТК») · №864 (01.11.2026) ====
//  Пользователи сайта лежат в общей базе (таблица itc_site_users, её заводит сайт 3.51.0).
//    count — сколько всего и сколько новых (для значка на плитке)
//    list  — список с отбором: q (поиск), sphere, kind, mail (только с согласием на рассылку); отмечает «просмотрено»
//    csv   — выгрузка для сервиса рассылок (только подтвердившие почту и давшие согласие), с отбором по сфере
require_once __DIR__ . '/config.php';
@date_default_timezone_set('Europe/Moscow');

function sr_me() {
  $s = authUser('itc');
  $r = $s['staff_role'] ?? '';
  if (!($r === 'boss' || $r === '')) fail('Раздел доступен только боссу', 403);
  return $s;
}
function sr_has() {
  try { db()->query('SELECT 1 FROM itc_site_users LIMIT 1'); return true; } catch (Throwable $e) { return false; }
}
function sr_seen() {
  try { $q = db()->prepare("SELECT v FROM settings WHERE k = 'site_regs_seen'"); $q->execute(); return (int)$q->fetchColumn(); } catch (Throwable $e) { return 0; }
}
$SPH = ['auto' => 'Автотовары', 'dom' => 'Дом и стройка', 'santeh' => 'Сантехника', 'horeca' => 'HoReCa', 'mebel' => 'Мебель и фурнитура',
        'food' => 'Пищевое оборудование', 'prom' => 'Промышленность и сельское хозяйство', 'energy' => 'Энергетика', 'odezhda' => 'Одежда, обувь и текстиль', 'life' => 'Красота и уход',
        'sport' => 'Спорт и отдых', 'office' => 'Канцелярия и упаковка', 'other' => 'Другое'];

$action = $_GET['action'] ?? '';
try {
  switch ($action) {

  case 'count': {
    sr_me();
    if (!sr_has()) jout(['ok' => true, 'total' => 0, 'new' => 0]);
    $seen = sr_seen();
    $t = (int)db()->query('SELECT COUNT(*) FROM itc_site_users')->fetchColumn();
    $q = db()->prepare('SELECT COUNT(*) FROM itc_site_users WHERE id > ?'); $q->execute([$seen]);
    jout(['ok' => true, 'total' => $t, 'new' => (int)$q->fetchColumn()]);
  }

  case 'list': {
    sr_me();
    if (!sr_has()) jout(['ok' => true, 'rows' => [], 'stats' => ['total' => 0], 'spheres' => $SPH]);
    $w = []; $a = [];
    $qq = trim((string)($_GET['q'] ?? ''));
    if ($qq !== '') { $w[] = '(email LIKE ? OR company LIKE ? OR inn LIKE ? OR last_name LIKE ? OR first_name LIKE ? OR contact LIKE ? OR phone LIKE ?)'; $l = '%' . $qq . '%'; array_push($a, $l, $l, $l, $l, $l, $l, $l); }
    $sp = (string)($_GET['sphere'] ?? '');
    if ($sp !== '' && isset($SPH[$sp])) { $w[] = 'FIND_IN_SET(?, spheres)'; $a[] = $sp; }
    $k = (string)($_GET['kind'] ?? '');
    if ($k === 'yur' || $k === 'fiz') { $w[] = 'kind = ?'; $a[] = $k; }
    if (!empty($_GET['mail'])) $w[] = 'mail_ok = 1 AND verified = 1';
    $st = db()->prepare('SELECT id, kind, email, phone, last_name, first_name, middle_name, inn, company, ogrn, contact, post, spheres, mail_ok, verified, crm_client, page, created_at, last_login
                         FROM itc_site_users' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY id DESC LIMIT 500');
    $st->execute($a); $rows = $st->fetchAll();
    $seen = sr_seen();
    foreach ($rows as &$r) $r['is_new'] = (int)$r['id'] > $seen;
    unset($r);
    $S = db()->query("SELECT COUNT(*) total, SUM(created_at >= CURDATE()) today, SUM(created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)) week,
                      SUM(created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)) month, SUM(kind = 'yur') yur, SUM(kind = 'fiz') fiz,
                      SUM(mail_ok = 1) mail, SUM(mail_ok = 1 AND verified = 1) mail_ready, MAX(id) maxid FROM itc_site_users")->fetch();
    $bys = [];
    foreach (db()->query('SELECT spheres FROM itc_site_users') as $x) foreach (explode(',', (string)$x['spheres']) as $s1) if ($s1 !== '') $bys[$s1] = ($bys[$s1] ?? 0) + 1;
    // открыл список — все текущие регистрации считаются просмотренными
    if (!empty($_GET['seen']) && (int)$S['maxid'] > $seen) {
      try {
        db()->exec("CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) NOT NULL, v TEXT, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        db()->prepare("INSERT INTO settings (k, v) VALUES ('site_regs_seen', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")->execute([(string)(int)$S['maxid']]);
      } catch (Throwable $e) {}
    }
    foreach ($S as $kk => $vv) $S[$kk] = (int)$vv;
    jout(['ok' => true, 'rows' => $rows, 'stats' => $S, 'by_sphere' => $bys, 'spheres' => $SPH]);
  }

  case 'csv': {
    sr_me();
    if (!sr_has()) fail('Регистраций пока нет');
    $sp = (string)($_GET['sphere'] ?? '');
    $w = 'mail_ok = 1 AND verified = 1'; $a = [];
    if ($sp !== '' && isset($SPH[$sp])) { $w .= ' AND FIND_IN_SET(?, spheres)'; $a[] = $sp; }
    $st = db()->prepare("SELECT * FROM itc_site_users WHERE $w ORDER BY id"); $st->execute($a);
    $out = fopen('php://temp', 'w+');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Почта', 'Имя', 'Компания', 'ИНН', 'Телефон', 'Сферы', 'Тип', 'Дата регистрации', 'Согласие на рассылку'], ';');
    foreach ($st as $r) {
      $nm = $r['kind'] === 'yur' ? $r['contact'] : trim($r['first_name'] . ' ' . $r['last_name']);
      $sn = []; foreach (explode(',', (string)$r['spheres']) as $s1) if (isset($SPH[$s1])) $sn[] = $SPH[$s1];
      fputcsv($out, [$r['email'], $nm, $r['company'], $r['inn'], $r['phone'], implode(', ', $sn), $r['kind'] === 'yur' ? 'Юрлицо/ИП' : 'Физлицо',
                     substr((string)$r['created_at'], 0, 10), substr((string)$r['mail_ok_at'], 0, 16)], ';');
    }
    rewind($out); $csv = stream_get_contents($out); fclose($out);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rassylka_' . ($sp ?: 'vse') . '_' . date('Ymd') . '.csv"');
    echo $csv; exit;
  }

  default: fail('Неизвестное действие');
  }
} catch (Throwable $e) { fail('Ошибка сервера: ' . $e->getMessage(), 500); }
