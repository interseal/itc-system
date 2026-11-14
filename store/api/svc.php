<?php
// ==== «ИТК Сервис» · api/svc.php · №597 (14.11.2026) ====
// Одна основа для приложений сферы услуг: парикмахерские (salon), шиномонтаж (tire), цветы (flower).
// Бизнес заводит себя (юрлицо: ИП / ООО / самозанятый), мастеров или посты, услуги с ценами и вариантами цены
// (например, радиус колеса или длина волос), часы работы и правило предоплаты. Клиент видит рядом на карте,
// где свободно сейчас (зелёный / жёлтый / красный / серый), выбирает время и записывается.
// Деньги: 5% берём ТОЛЬКО с того, что прошло через нас (онлайн-оплата клиента). Прошло — 5% наши, 95% бизнесу
// (svc_pay: fee / payout). Платёжный терминал — подключаемая деталь (svc_pay_start / pay_hook): пока его нет,
// запись создаётся без онлайн-оплаты, предоплату бизнес берёт на месте.
//
//   Клиент:   near, biz, slots, book, my, cancel
//   Бизнес:   mybiz, biz_save, photo, staff_save, staff_del, svc_save, svc_del, day, walkin, bstatus, money, publish
//   Руководитель ИТК: admin, payout_mark
//   Терминал: pay_hook (позже)

require_once __DIR__ . '/config.php';
@set_time_limit(30);
$IN = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : [];
$A = (string)($_GET['action'] ?? ($IN['action'] ?? ''));
function sv_out($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function sv_fail($m, $c = 400) { http_response_code($c); sv_out(['ok' => false, 'error' => $m]); }
function sv_in($k, $d = '') { global $IN; return $IN[$k] ?? ($_GET[$k] ?? $d); }
function sv_s($v, $n) { return mb_substr(trim(strip_tags((string)$v)), 0, $n); }
function sv_ph($p) { return preg_replace('~\D+~', '', (string)$p); }

const SVC_KINDS = ['salon', 'tire', 'flower'];
const SVC_FEE_PCT = 5.0;          // наша доля с денег, прошедших через нас
const SVC_STEP = 15;              // шаг свободных окон, минут
const SVC_STALE_DAYS = 3;         // бизнес не открывал кабинет дольше — на карте серый

/* ---------- таблицы ---------- */
function sv_ensure() { static $ok = false; if ($ok) return; $ok = true; $d = db();
  $d->exec("CREATE TABLE IF NOT EXISTS svc_biz (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(12) NOT NULL, owner VARCHAR(40) NOT NULL, admins TEXT,
    name VARCHAR(160) NOT NULL, legal_type VARCHAR(12) NOT NULL DEFAULT '', legal_name VARCHAR(200) NOT NULL DEFAULT '', inn VARCHAR(40) NOT NULL DEFAULT '',
    phone VARCHAR(40) NOT NULL DEFAULT '', address VARCHAR(300) NOT NULL DEFAULT '', city VARCHAR(80) NOT NULL DEFAULT '', lat DOUBLE NULL, lng DOUBLE NULL,
    tz VARCHAR(40) NOT NULL DEFAULT 'UTC', cur VARCHAR(8) NOT NULL DEFAULT '', about TEXT, photos TEXT, hours TEXT, prepay TEXT,
    status VARCHAR(10) NOT NULL DEFAULT 'draft', seen_at INT NOT NULL DEFAULT 0, created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0,
    KEY k_kind (kind, status), KEY k_owner (owner), KEY k_geo (lat, lng)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $d->exec("CREATE TABLE IF NOT EXISTS svc_staff (id INT AUTO_INCREMENT PRIMARY KEY, biz_id INT NOT NULL, name VARCHAR(120) NOT NULL, title VARCHAR(120) NOT NULL DEFAULT '',
    photo VARCHAR(300) NOT NULL DEFAULT '', hours TEXT, active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0, KEY k_biz (biz_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $d->exec("CREATE TABLE IF NOT EXISTS svc_service (id INT AUTO_INCREMENT PRIMARY KEY, biz_id INT NOT NULL, name VARCHAR(160) NOT NULL, cat VARCHAR(80) NOT NULL DEFAULT '',
    dur INT NOT NULL DEFAULT 30, price DECIMAL(12,2) NOT NULL DEFAULT 0, variants TEXT, note VARCHAR(300) NOT NULL DEFAULT '', active TINYINT NOT NULL DEFAULT 1, sort INT NOT NULL DEFAULT 0,
    KEY k_biz (biz_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $d->exec("CREATE TABLE IF NOT EXISTS svc_booking (id INT AUTO_INCREMENT PRIMARY KEY, biz_id INT NOT NULL, staff_id INT NOT NULL, service_id INT NOT NULL DEFAULT 0,
    svc_name VARCHAR(200) NOT NULL DEFAULT '', client VARCHAR(40) NOT NULL DEFAULT '', client_name VARCHAR(120) NOT NULL DEFAULT '', t0 INT NOT NULL, t1 INT NOT NULL,
    price DECIMAL(12,2) NOT NULL DEFAULT 0, prepay DECIMAL(12,2) NOT NULL DEFAULT 0, status VARCHAR(12) NOT NULL DEFAULT 'new', src VARCHAR(10) NOT NULL DEFAULT 'app',
    pay VARCHAR(12) NOT NULL DEFAULT 'none', note VARCHAR(300) NOT NULL DEFAULT '', created_at INT NOT NULL DEFAULT 0, updated_at INT NOT NULL DEFAULT 0,
    KEY k_staff (staff_id, t0), KEY k_biz (biz_id, t0), KEY k_client (client)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
  $d->exec("CREATE TABLE IF NOT EXISTS svc_pay (id INT AUTO_INCREMENT PRIMARY KEY, booking_id INT NOT NULL, biz_id INT NOT NULL, amount DECIMAL(12,2) NOT NULL,
    fee DECIMAL(12,2) NOT NULL DEFAULT 0, payout DECIMAL(12,2) NOT NULL DEFAULT 0, fee_pct DECIMAL(5,2) NOT NULL DEFAULT 5, cur VARCHAR(8) NOT NULL DEFAULT '',
    provider VARCHAR(20) NOT NULL DEFAULT '', ref VARCHAR(120) NOT NULL DEFAULT '', status VARCHAR(12) NOT NULL DEFAULT 'pending', payout_status VARCHAR(10) NOT NULL DEFAULT '',
    created_at INT NOT NULL DEFAULT 0, paid_at INT NOT NULL DEFAULT 0, payout_at INT NOT NULL DEFAULT 0, KEY k_biz (biz_id), KEY k_bk (booking_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/* ---------- кто спрашивает ---------- */
function sv_me() { static $me = null; if ($me !== null) return $me; $s = authUser(); $me = ['phone' => sv_ph($s['phone'] ?? ''), 'name' => (string)($s['name'] ?? ''),
  'boss' => (($s['role'] ?? 'itc') === 'itc' || !isset($s['role'])) && in_array((string)($s['staff_role'] ?? ''), ['boss', 'bossacct'], true)];
  if ($me['phone'] === '') sv_fail('Войдите по номеру телефона', 401); return $me; }
function sv_biz($id) { sv_ensure(); $q = db()->prepare('SELECT * FROM svc_biz WHERE id=?'); $q->execute([(int)$id]); return $q->fetch() ?: null; }
function sv_can($b) { $me = sv_me(); if (!$b) return false; if ($me['boss'] || $b['owner'] === $me['phone']) return true;
  return in_array($me['phone'], array_map('sv_ph', (array)json_decode((string)$b['admins'], true)), true); }
function sv_mine($id) { $b = sv_biz($id); if (!$b || !sv_can($b)) sv_fail('Нет доступа к этому бизнесу', 403);
  db()->prepare('UPDATE svc_biz SET seen_at=? WHERE id=?')->execute([time(), $b['id']]); return $b; }

/* ---------- время: часы работы ---------- */
function sv_tz($b) { try { return new DateTimeZone($b['tz'] ?: 'UTC'); } catch (Throwable $e) { return new DateTimeZone('UTC'); } }
function sv_hours_clean($h) { $o = []; for ($i = 1; $i <= 7; $i++) { $x = is_array($h) ? ($h[$i] ?? $h[(string)$i] ?? null) : null;
    if (is_array($x) && preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', (string)($x['o'] ?? '')) && preg_match('~^([01]\d|2[0-3]):[0-5]\d$|^24:00$~', (string)($x['c'] ?? '')) && $x['o'] < $x['c'])
      $o[$i] = ['o' => $x['o'], 'c' => $x['c']]; else $o[$i] = null; } return $o; }
/* часы мастера на дату: свои, иначе часы бизнеса. Возвращает [t_open, t_close] (unix) или null */
function sv_open($b, $st, $date) { $tz = sv_tz($b); $d = DateTime::createFromFormat('!Y-m-d', $date, $tz); if (!$d) return null;
  $dow = (int)$d->format('N'); $h = $st && $st['hours'] ? (json_decode((string)$st['hours'], true) ?: null) : null;
  $H = $h ? sv_hours_clean($h) : sv_hours_clean(json_decode((string)$b['hours'], true)); $x = $H[$dow] ?? null; if (!$x) return null;
  $o = DateTime::createFromFormat('Y-m-d H:i', "$date {$x['o']}", $tz); $c = $x['c'] === '24:00' ? (clone $d)->modify('+1 day') : DateTime::createFromFormat('Y-m-d H:i', "$date {$x['c']}", $tz);
  return ($o && $c) ? [$o->getTimestamp(), $c->getTimestamp()] : null; }
function sv_busy($staffId, $a, $z) { $q = db()->prepare("SELECT t0,t1 FROM svc_booking WHERE staff_id=? AND t1>? AND t0<? AND status IN ('new','ok','wait','come','done')"); $q->execute([(int)$staffId, $a, $z]);
  return array_map(function ($r) { return [(int)$r['t0'], (int)$r['t1']]; }, $q->fetchAll()); }
function sv_free($busy, $t, $dur) { foreach ($busy as [$a, $z]) if ($t < $z && $t + $dur > $a) return false; return true; }
/* свободные начала для мастера на дату */
function sv_slots_staff($b, $st, $date, $dur, $now = null) { $now = $now ?? time(); $op = sv_open($b, $st, $date); if (!$op) return [];
  [$a, $z] = $op; $busy = sv_busy($st['id'], $a, $z); $out = []; $min = $now + 10 * 60;
  for ($t = $a; $t + $dur <= $z; $t += SVC_STEP * 60) if ($t >= $min && sv_free($busy, $t, $dur)) $out[] = $t; return $out; }
function sv_staff_list($bizId, $onlyActive = true) { $q = db()->prepare('SELECT * FROM svc_staff WHERE biz_id=?' . ($onlyActive ? ' AND active=1' : '') . ' ORDER BY sort,id'); $q->execute([(int)$bizId]); return $q->fetchAll(); }
function sv_service_list($bizId, $onlyActive = true) { $q = db()->prepare('SELECT * FROM svc_service WHERE biz_id=?' . ($onlyActive ? ' AND active=1' : '') . ' ORDER BY sort,id'); $q->execute([(int)$bizId]); return $q->fetchAll(); }

/* загрузка «сейчас» для карты: green — свободно в ближайшие 15 мин, yellow — до 90 мин, red — дальше / занято, grey — закрыто или давно не обновлял */
function sv_load($b, $now = null) { $now = $now ?? time(); $tz = sv_tz($b); $date = (new DateTime('@' . $now))->setTimezone($tz)->format('Y-m-d');
  if ($b['seen_at'] && $now - (int)$b['seen_at'] > SVC_STALE_DAYS * 86400) return ['c' => 'grey', 't' => 'давно не обновлял расписание', 'next' => 0];
  $sv = sv_service_list($b['id']); $dur = $sv ? min(array_map(function ($s) { return max(5, (int)$s['dur']); }, $sv)) * 60 : 1800;
  $best = 0; $openAny = false; $tot = 0; $busyMin = 0;
  foreach (sv_staff_list($b['id']) as $st) { $op = sv_open($b, $st, $date); if (!$op) continue; if ($now < $op[1]) $openAny = true;
    $from = max($now, $op[0]); if ($from < $op[1]) { $tot += $op[1] - $from; foreach (sv_busy($st['id'], $from, $op[1]) as [$a, $z]) $busyMin += min($z, $op[1]) - max($a, $from); }
    $sl = sv_slots_staff($b, $st, $date, $dur, $now - 10 * 60); foreach ($sl as $t) if ($t >= $now - 60) { if (!$best || $t < $best) $best = $t; break; } }
  $load = $tot > 0 ? (int)round(100 * $busyMin / $tot) : 100;
  if (!$openAny) return ['c' => 'grey', 't' => 'сегодня закрыто', 'next' => 0, 'load' => 100];
  if (!$best) return ['c' => 'red', 't' => 'сегодня мест нет', 'next' => 0, 'load' => $load];
  $m = max(0, (int)round(($best - $now) / 60)); $hm = (new DateTime('@' . $best))->setTimezone($tz)->format('H:i');
  if ($m <= 15) return ['c' => 'green', 't' => 'свободно сейчас', 'next' => $best, 'load' => $load];
  if ($m <= 90) return ['c' => 'yellow', 't' => "свободно в $hm", 'next' => $best, 'load' => $load];
  return ['c' => 'red', 't' => "ближайшее в $hm", 'next' => $best, 'load' => $load]; }

/* ---------- вывод ---------- */
function sv_biz_out($b, $full = false) { $o = ['id' => (int)$b['id'], 'kind' => $b['kind'], 'name' => $b['name'], 'address' => $b['address'], 'city' => $b['city'],
  'lat' => $b['lat'] !== null ? (float)$b['lat'] : null, 'lng' => $b['lng'] !== null ? (float)$b['lng'] : null, 'phone' => $b['phone'], 'tz' => $b['tz'], 'cur' => $b['cur'],
  'photos' => (array)json_decode((string)$b['photos'], true), 'status' => $b['status']];
  if ($full) { $o['about'] = (string)$b['about']; $o['hours'] = sv_hours_clean(json_decode((string)$b['hours'], true)); $o['prepay'] = sv_prepay_clean(json_decode((string)$b['prepay'], true));
    $o['legal_type'] = $b['legal_type']; $o['legal_name'] = $b['legal_name']; } return $o; }
function sv_staff_out($s) { return ['id' => (int)$s['id'], 'name' => $s['name'], 'title' => $s['title'], 'photo' => $s['photo'], 'active' => (int)$s['active'], 'hours' => $s['hours'] ? sv_hours_clean(json_decode((string)$s['hours'], true)) : null]; }
function sv_svc_out($s) { return ['id' => (int)$s['id'], 'name' => $s['name'], 'cat' => $s['cat'], 'dur' => (int)$s['dur'], 'price' => (float)$s['price'], 'note' => $s['note'],
  'variants' => array_values((array)json_decode((string)$s['variants'], true)), 'active' => (int)$s['active']]; }
function sv_prepay_clean($p) { $p = is_array($p) ? $p : []; $m = in_array($p['mode'] ?? '', ['none', 'percent', 'fixed', 'full'], true) ? $p['mode'] : 'none';
  return ['mode' => $m, 'value' => max(0, min($m === 'percent' ? 100 : 1e7, (float)($p['value'] ?? 0))), 'cancel_h' => max(0, min(72, (int)($p['cancel_h'] ?? 2)))]; }
function sv_prepay_amount($b, $price) { $p = sv_prepay_clean(json_decode((string)$b['prepay'], true));
  if ($p['mode'] === 'full') return round($price, 2); if ($p['mode'] === 'percent') return round($price * $p['value'] / 100, 2); if ($p['mode'] === 'fixed') return round(min($price, $p['value']), 2); return 0.0; }
function sv_bk_out($r, $b = null) { $tz = $b ? sv_tz($b) : new DateTimeZone('UTC'); $f = function ($t) use ($tz) { return (new DateTime('@' . $t))->setTimezone($tz)->format('Y-m-d H:i'); };
  return ['id' => (int)$r['id'], 'biz_id' => (int)$r['biz_id'], 'staff_id' => (int)$r['staff_id'], 'service' => $r['svc_name'], 't0' => (int)$r['t0'], 't1' => (int)$r['t1'],
    'start' => $f((int)$r['t0']), 'end' => $f((int)$r['t1']), 'price' => (float)$r['price'], 'prepay' => (float)$r['prepay'], 'status' => $r['status'], 'src' => $r['src'], 'pay' => $r['pay'],
    'client_name' => $r['client_name'], 'client' => $r['client'], 'note' => $r['note']]; }

/* ---------- деньги: 5% / 95% ---------- */
function sv_split($amount, $pct = SVC_FEE_PCT) { $fee = round($amount * $pct / 100, 2); return [$fee, round($amount - $fee, 2)]; }
/* Платёжный терминал. Пока не подключён — возвращает null, и запись идёт без онлайн-оплаты.
   Подключение: вернуть ['provider'=>'…','ref'=>'…','url'=>'страница оплаты'] и принимать подтверждение в pay_hook → sv_pay_settle(). */
function sv_pay_start($pay, $b) { $f = __DIR__ . '/svc_pay_provider.php'; if (is_file($f)) { require_once $f; if (function_exists('svc_provider_start')) return svc_provider_start($pay, $b); } return null; }
function sv_pay_settle($payId, $ref = '') { sv_ensure(); $d = db(); $d->beginTransaction();
  $q = $d->prepare('SELECT * FROM svc_pay WHERE id=? FOR UPDATE'); $q->execute([(int)$payId]); $p = $q->fetch();
  if (!$p || $p['status'] === 'paid') { $d->commit(); return $p ? true : false; }
  [$fee, $payout] = sv_split((float)$p['amount'], (float)$p['fee_pct']);
  $d->prepare("UPDATE svc_pay SET status='paid', fee=?, payout=?, payout_status='due', ref=IF(?<>'',?,ref), paid_at=? WHERE id=?")->execute([$fee, $payout, $ref, $ref, time(), $p['id']]);
  $d->prepare("UPDATE svc_booking SET pay='paid', status=IF(status='wait','ok',status), updated_at=? WHERE id=?")->execute([time(), $p['booking_id']]); $d->commit(); return true; }
/* отмена: оплаченное возвращаем клиенту целиком (наши 5% тоже не берём), если отменил вовремя или отменил бизнес */
function sv_pay_refund($bookingId) { $q = db()->prepare("SELECT * FROM svc_pay WHERE booking_id=? AND status='paid'"); $q->execute([(int)$bookingId]);
  foreach ($q->fetchAll() as $p) db()->prepare("UPDATE svc_pay SET status='refund', fee=0, payout=0, payout_status='' WHERE id=?")->execute([$p['id']]);
  db()->prepare("UPDATE svc_booking SET pay=IF(pay='paid','refund',pay) WHERE id=?")->execute([(int)$bookingId]); }

/* ====================== терминал (позже) ====================== */
if ($A === 'pay_hook') { $f = __DIR__ . '/svc_pay_provider.php'; if (!is_file($f)) sv_fail('Платёжный терминал не подключён', 404);
  require_once $f; if (!function_exists('svc_provider_hook')) sv_fail('Платёжный терминал не подключён', 404);
  $r = svc_provider_hook(); if (!empty($r['pay_id']) && !empty($r['paid'])) sv_pay_settle((int)$r['pay_id'], (string)($r['ref'] ?? '')); sv_out(['ok' => true]); }

$me = sv_me(); sv_ensure();
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

/* ====================== клиент ====================== */
if ($A === 'near') { $kind = in_array(sv_in('kind'), SVC_KINDS, true) ? sv_in('kind') : 'salon';
  $lat = (float)sv_in('lat'); $lng = (float)sv_in('lng'); $r = max(1, min(50, (float)sv_in('r', 10))); $has = sv_in('lat', '') !== '' && sv_in('lng', '') !== '';
  $sql = "SELECT * FROM svc_biz WHERE kind=? AND status='active'"; $args = [$kind];
  if ($has) { $dl = $r / 111.0; $dg = $r / (111.0 * max(0.2, cos(deg2rad($lat)))); $sql .= ' AND lat BETWEEN ? AND ? AND lng BETWEEN ? AND ?'; array_push($args, $lat - $dl, $lat + $dl, $lng - $dg, $lng + $dg); }
  if ($me['boss'] && sv_in('all')) $sql = str_replace("status='active'", "status<>'off'", $sql);
  $q = db()->prepare($sql . ' LIMIT 300'); $q->execute($args); $L = [];
  foreach ($q->fetchAll() as $b) { $o = sv_biz_out($b); $o['load'] = sv_load($b);
    $o['km'] = ($has && $b['lat'] !== null) ? round(6371 * 2 * asin(sqrt(pow(sin(deg2rad($b['lat'] - $lat) / 2), 2) + cos(deg2rad($lat)) * cos(deg2rad($b['lat'])) * pow(sin(deg2rad($b['lng'] - $lng) / 2), 2))), 2) : null;
    $sv = sv_service_list($b['id']); $o['from'] = $sv ? min(array_map(function ($s) { $v = (array)json_decode((string)$s['variants'], true); $p = array_filter(array_merge([(float)$s['price']], array_map(function ($x) { return (float)($x['price'] ?? 0); }, $v))); return $p ? min($p) : 0; }, $sv)) : 0;
    $L[] = $o; }
  $rank = ['green' => 0, 'yellow' => 1, 'red' => 2, 'grey' => 3];
  usort($L, function ($a, $b) use ($rank) { return [$rank[$a['load']['c']], $a['km'] ?? 1e9] <=> [$rank[$b['load']['c']], $b['km'] ?? 1e9]; });
  sv_out(['ok' => true, 'list' => array_slice($L, 0, 100), 'now' => time()]); }

if ($A === 'biz') { $b = sv_biz(sv_in('id')); if (!$b || ($b['status'] !== 'active' && !sv_can($b))) sv_fail('Не нашли', 404);
  $o = sv_biz_out($b, true); $o['load'] = sv_load($b);
  sv_out(['ok' => true, 'biz' => $o, 'staff' => array_map('sv_staff_out', sv_staff_list($b['id'])), 'services' => array_map('sv_svc_out', sv_service_list($b['id']))]); }

if ($A === 'slots') { $b = sv_biz(sv_in('biz')); if (!$b) sv_fail('Не нашли', 404);
  $sv = null; foreach (sv_service_list($b['id']) as $x) if ((int)$x['id'] === (int)sv_in('service')) $sv = $x; if (!$sv) sv_fail('Выберите услугу');
  $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)sv_in('date')) ? sv_in('date') : (new DateTime('now', sv_tz($b)))->format('Y-m-d');
  $dur = max(5, (int)$sv['dur']) * 60; $sid = (int)sv_in('staff'); $by = [];
  foreach (sv_staff_list($b['id']) as $st) { if ($sid && (int)$st['id'] !== $sid) continue; foreach (sv_slots_staff($b, $st, $date, $dur) as $t) $by[$t][] = (int)$st['id']; }
  ksort($by); $tz = sv_tz($b); $out = []; foreach ($by as $t => $ids) $out[] = ['at' => $t, 'hm' => (new DateTime('@' . $t))->setTimezone($tz)->format('H:i'), 'staff' => $ids];
  sv_out(['ok' => true, 'date' => $date, 'slots' => $out]); }

if ($A === 'book') { $b = sv_biz(sv_in('biz')); if (!$b || $b['status'] !== 'active') sv_fail('Этот бизнес сейчас не принимает записи');
  $sv = null; foreach (sv_service_list($b['id']) as $x) if ((int)$x['id'] === (int)sv_in('service')) $sv = $x; if (!$sv) sv_fail('Выберите услугу');
  $price = (float)$sv['price']; $vname = ''; $vi = sv_in('variant', '');
  if ($vi !== '' && $vi !== null) { $vs = array_values((array)json_decode((string)$sv['variants'], true)); $v = $vs[(int)$vi] ?? null; if (!$v) sv_fail('Выберите вариант'); $price = (float)($v['price'] ?? 0); $vname = (string)($v['name'] ?? ''); }
  $t = (int)sv_in('at'); $dur = max(5, (int)$sv['dur']) * 60; if ($t < time()) sv_fail('Это время уже прошло');
  $date = (new DateTime('@' . $t))->setTimezone(sv_tz($b))->format('Y-m-d'); $want = (int)sv_in('staff'); $name = sv_s(sv_in('name', $me['name']), 120);
  $d = db(); $d->beginTransaction(); $pick = null;
  foreach (sv_staff_list($b['id']) as $st) { if ($want && (int)$st['id'] !== $want) continue;
    $d->prepare('SELECT id FROM svc_staff WHERE id=? FOR UPDATE')->execute([$st['id']]);   // двое не займут одно время
    if (in_array($t, sv_slots_staff($b, $st, $date, $dur), true)) { $pick = $st; break; } }
  if (!$pick) { $d->rollBack(); sv_fail('Это время уже заняли — выберите другое', 409); }
  $pre = sv_prepay_amount($b, $price); $now = time();
  $d->prepare('INSERT INTO svc_booking (biz_id,staff_id,service_id,svc_name,client,client_name,t0,t1,price,prepay,status,src,pay,note,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$b['id'], $pick['id'], $sv['id'], $sv['name'] . ($vname !== '' ? " · $vname" : ''), $me['phone'], $name, $t, $t + $dur, $price, $pre, 'new', 'app', 'none', sv_s(sv_in('note'), 300), $now, $now]);
  $bid = (int)$d->lastInsertId(); $pay = null;
  if ($pre > 0) { $d->prepare('INSERT INTO svc_pay (booking_id,biz_id,amount,fee_pct,cur,status,created_at) VALUES (?,?,?,?,?,?,?)')->execute([$bid, $b['id'], $pre, SVC_FEE_PCT, $b['cur'], 'pending', $now]);
    $pay = ['id' => (int)$d->lastInsertId(), 'amount' => $pre]; }
  $d->commit();
  $res = ['ok' => true, 'id' => $bid, 'staff' => $pick['name'], 'prepay' => $pre];
  if ($pay) { $st = sv_pay_start($pay, $b);
    if ($st && !empty($st['url'])) { db()->prepare("UPDATE svc_pay SET provider=?, ref=? WHERE id=?")->execute([sv_s($st['provider'] ?? '', 20), sv_s($st['ref'] ?? '', 120), $pay['id']]);
      db()->prepare("UPDATE svc_booking SET status='wait', pay='pending' WHERE id=?")->execute([$bid]); $res['pay_url'] = $st['url']; }
    else { db()->prepare("UPDATE svc_pay SET status='offline' WHERE id=?")->execute([$pay['id']]); db()->prepare("UPDATE svc_booking SET pay='onsite' WHERE id=?")->execute([$bid]); $res['pay_onsite'] = true; } }
  sv_out($res); }

if ($A === 'my') { $q = db()->prepare("SELECT * FROM svc_booking WHERE client=? AND t1>? ORDER BY t0 DESC LIMIT 100"); $q->execute([$me['phone'], time() - 60 * 86400]); $L = []; $B = [];
  foreach ($q->fetchAll() as $r) { $b = $B[$r['biz_id']] ?? ($B[$r['biz_id']] = sv_biz($r['biz_id'])); if (!$b) continue; $o = sv_bk_out($r, $b); $o['biz'] = ['id' => (int)$b['id'], 'name' => $b['name'], 'address' => $b['address'], 'phone' => $b['phone'], 'lat' => $b['lat'], 'lng' => $b['lng']];
    $o['cancel_h'] = sv_prepay_clean(json_decode((string)$b['prepay'], true))['cancel_h']; $L[] = $o; }
  sv_out(['ok' => true, 'list' => $L]); }

if ($A === 'cancel') { $q = db()->prepare('SELECT * FROM svc_booking WHERE id=? AND client=?'); $q->execute([(int)sv_in('id'), $me['phone']]); $r = $q->fetch();
  if (!$r || in_array($r['status'], ['cancel', 'done', 'noshow'], true)) sv_fail('Запись не найдена или уже закрыта');
  $b = sv_biz($r['biz_id']); $h = sv_prepay_clean(json_decode((string)$b['prepay'], true))['cancel_h']; $late = (int)$r['t0'] - time() < $h * 3600;
  db()->prepare("UPDATE svc_booking SET status='cancel', note=CONCAT(note, ?), updated_at=? WHERE id=?")->execute([$late ? ' · отменил поздно' : ' · отменил клиент', time(), $r['id']]);
  if (!$late) sv_pay_refund($r['id']);   // поздняя отмена: предоплата остаётся бизнесу (наши 5% тоже остаются)
  sv_out(['ok' => true, 'refund' => !$late && $r['pay'] === 'paid', 'late' => $late]); }

/* ====================== бизнес ====================== */
if ($A === 'mybiz') { $q = db()->prepare("SELECT * FROM svc_biz WHERE (owner=? OR admins LIKE ?) AND kind=? ORDER BY id"); $q->execute([$me['phone'], '%' . $me['phone'] . '%', in_array(sv_in('kind'), SVC_KINDS, true) ? sv_in('kind') : 'salon']);
  $L = []; foreach ($q->fetchAll() as $b) { if (!sv_can($b)) continue; $o = sv_biz_out($b, true); $o['inn'] = $b['inn']; $o['admins'] = (array)json_decode((string)$b['admins'], true);
    $o['staff'] = array_map('sv_staff_out', sv_staff_list($b['id'], false)); $o['services'] = array_map('sv_svc_out', sv_service_list($b['id'], false)); $o['load'] = sv_load($b); $L[] = $o; }
  sv_out(['ok' => true, 'list' => $L, 'fee_pct' => SVC_FEE_PCT]); }

if ($A === 'biz_save') { $id = (int)sv_in('id'); $b = $id ? sv_mine($id) : null; $kind = $b ? $b['kind'] : (in_array(sv_in('kind'), SVC_KINDS, true) ? sv_in('kind') : 'salon');
  $name = sv_s(sv_in('name'), 160); if ($name === '') sv_fail('Напишите название');
  $lt = in_array(sv_in('legal_type'), ['ip', 'ooo', 'self', 'other'], true) ? sv_in('legal_type') : '';
  $tz = (string)sv_in('tz', $b['tz'] ?? 'UTC'); try { new DateTimeZone($tz); } catch (Throwable $e) { $tz = 'UTC'; }
  $lat = sv_in('lat', ''); $lng = sv_in('lng', ''); $lat = is_numeric($lat) && abs($lat) <= 90 ? (float)$lat : null; $lng = is_numeric($lng) && abs($lng) <= 180 ? (float)$lng : null;
  $adm = array_values(array_unique(array_filter(array_map('sv_ph', (array)sv_in('admins', []))))); $adm = array_slice($adm, 0, 10);
  $f = ['name' => $name, 'legal_type' => $lt, 'legal_name' => sv_s(sv_in('legal_name'), 200), 'inn' => sv_s(preg_replace('~[^0-9A-Za-z]~', '', (string)sv_in('inn')), 40),
    'phone' => sv_s(sv_in('phone'), 40), 'address' => sv_s(sv_in('address'), 300), 'city' => sv_s(sv_in('city'), 80), 'lat' => $lat, 'lng' => $lng, 'tz' => $tz,
    'cur' => sv_s(strtoupper((string)sv_in('cur')), 8), 'about' => sv_s(sv_in('about'), 2000), 'hours' => json_encode(sv_hours_clean(sv_in('hours', []))),
    'prepay' => json_encode(sv_prepay_clean(sv_in('prepay', []))), 'admins' => json_encode($adm), 'updated_at' => time()];
  if ($b) { $set = implode(',', array_map(function ($k) { return "$k=?"; }, array_keys($f))); db()->prepare("UPDATE svc_biz SET $set WHERE id=?")->execute(array_merge(array_values($f), [$b['id']])); $id = (int)$b['id']; }
  else { $f += ['kind' => $kind, 'owner' => $me['phone'], 'status' => 'draft', 'photos' => '[]', 'seen_at' => time(), 'created_at' => time()];
    db()->prepare('INSERT INTO svc_biz (' . implode(',', array_keys($f)) . ') VALUES (' . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f)); $id = (int)db()->lastInsertId(); }
  sv_out(['ok' => true, 'id' => $id]); }

if ($A === 'photo') { $b = sv_mine(sv_in('biz')); $ph = (array)json_decode((string)$b['photos'], true);
  if (sv_in('del', '') !== '') { $u = (string)sv_in('del'); $ph = array_values(array_filter($ph, function ($x) use ($u) { return $x !== $u; }));
    if (preg_match('~^/uploads/svc/[a-z0-9_]+\.(jpg|png|webp)$~', $u)) @unlink(dirname(__DIR__) . $u); }
  else { if (count($ph) >= 8) sv_fail('Не больше 8 фото'); $data = (string)sv_in('data'); if (!preg_match('~^data:image/(jpeg|png|webp);base64,~', $data, $m)) sv_fail('Нужна картинка JPG, PNG или WEBP');
    $bin = base64_decode(substr($data, strpos($data, ',') + 1), true); if ($bin === false || strlen($bin) > 3 * 1048576 || !@getimagesizefromstring($bin)) sv_fail('Фото не подошло (до 3 МБ)');
    $dir = dirname(__DIR__) . '/uploads/svc'; @mkdir($dir, 0755, true); $fn = 'b' . $b['id'] . '_' . bin2hex(random_bytes(5)) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    file_put_contents("$dir/$fn", $bin); $ph[] = "/uploads/svc/$fn"; $who = sv_in('staff', ''); if ($who !== '') { db()->prepare('UPDATE svc_staff SET photo=? WHERE id=? AND biz_id=?')->execute(["/uploads/svc/$fn", (int)$who, $b['id']]); array_pop($ph); sv_out(['ok' => true, 'url' => "/uploads/svc/$fn"]); } }
  db()->prepare('UPDATE svc_biz SET photos=? WHERE id=?')->execute([json_encode(array_values($ph)), $b['id']]); sv_out(['ok' => true, 'photos' => $ph]); }

if ($A === 'staff_save') { $b = sv_mine(sv_in('biz')); $name = sv_s(sv_in('name'), 120); if ($name === '') sv_fail('Напишите имя');
  $h = sv_in('hours', null); $hj = is_array($h) ? json_encode(sv_hours_clean($h)) : null; $id = (int)sv_in('id');
  if ($id) db()->prepare('UPDATE svc_staff SET name=?, title=?, hours=?, active=?, sort=? WHERE id=? AND biz_id=?')->execute([$name, sv_s(sv_in('title'), 120), $hj, sv_in('active', 1) ? 1 : 0, (int)sv_in('sort'), $id, $b['id']]);
  else { db()->prepare('INSERT INTO svc_staff (biz_id,name,title,hours,active,sort) VALUES (?,?,?,?,1,?)')->execute([$b['id'], $name, sv_s(sv_in('title'), 120), $hj, (int)sv_in('sort')]); $id = (int)db()->lastInsertId(); }
  sv_out(['ok' => true, 'id' => $id]); }
if ($A === 'staff_del') { $b = sv_mine(sv_in('biz')); $q = db()->prepare("SELECT COUNT(*) FROM svc_booking WHERE staff_id=? AND t0>? AND status IN ('new','ok','wait')"); $q->execute([(int)sv_in('id'), time()]);
  if ((int)$q->fetchColumn() > 0) { db()->prepare('UPDATE svc_staff SET active=0 WHERE id=? AND biz_id=?')->execute([(int)sv_in('id'), $b['id']]); sv_out(['ok' => true, 'hidden' => true]); }
  db()->prepare('DELETE FROM svc_staff WHERE id=? AND biz_id=?')->execute([(int)sv_in('id'), $b['id']]); sv_out(['ok' => true]); }

if ($A === 'svc_save') { $b = sv_mine(sv_in('biz')); $name = sv_s(sv_in('name'), 160); if ($name === '') sv_fail('Напишите название услуги');
  $vs = []; foreach ((array)sv_in('variants', []) as $v) { $n = sv_s($v['name'] ?? '', 60); $p = (float)($v['price'] ?? 0); if ($n !== '' && $p > 0) $vs[] = ['name' => $n, 'price' => round($p, 2)]; }
  $f = [$name, sv_s(sv_in('cat'), 80), max(5, min(600, (int)sv_in('dur', 30))), max(0, round((float)sv_in('price'), 2)), json_encode(array_slice($vs, 0, 20), JSON_UNESCAPED_UNICODE), sv_s(sv_in('note'), 300), sv_in('active', 1) ? 1 : 0, (int)sv_in('sort')];
  $id = (int)sv_in('id');
  if ($id) db()->prepare('UPDATE svc_service SET name=?,cat=?,dur=?,price=?,variants=?,note=?,active=?,sort=? WHERE id=? AND biz_id=?')->execute(array_merge($f, [$id, $b['id']]));
  else { db()->prepare('INSERT INTO svc_service (name,cat,dur,price,variants,note,active,sort,biz_id) VALUES (?,?,?,?,?,?,?,?,?)')->execute(array_merge($f, [$b['id']])); $id = (int)db()->lastInsertId(); }
  sv_out(['ok' => true, 'id' => $id]); }
if ($A === 'svc_del') { $b = sv_mine(sv_in('biz')); db()->prepare('DELETE FROM svc_service WHERE id=? AND biz_id=?')->execute([(int)sv_in('id'), $b['id']]); sv_out(['ok' => true]); }

if ($A === 'publish') { $b = sv_mine(sv_in('biz')); $on = (int)sv_in('on') === 1;
  if ($on) { $miss = []; if ($b['lat'] === null) $miss[] = 'точка на карте'; if (!sv_staff_list($b['id'])) $miss[] = 'хотя бы один мастер'; if (!sv_service_list($b['id'])) $miss[] = 'хотя бы одна услуга';
    if (!array_filter(sv_hours_clean(json_decode((string)$b['hours'], true)))) $miss[] = 'часы работы'; if ($b['legal_type'] === '') $miss[] = 'форма (ИП, ООО или самозанятый)';
    if ($miss) sv_fail('Чтобы показываться клиентам, добавьте: ' . implode(', ', $miss)); }
  db()->prepare('UPDATE svc_biz SET status=? WHERE id=?')->execute([$on ? 'active' : 'draft', $b['id']]); sv_out(['ok' => true, 'status' => $on ? 'active' : 'draft']); }

if ($A === 'day') { $b = sv_mine(sv_in('biz')); $tz = sv_tz($b); $date = preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)sv_in('date')) ? sv_in('date') : (new DateTime('now', $tz))->format('Y-m-d');
  $d0 = DateTime::createFromFormat('!Y-m-d', $date, $tz)->getTimestamp(); $q = db()->prepare("SELECT * FROM svc_booking WHERE biz_id=? AND t0>=? AND t0<? ORDER BY t0"); $q->execute([$b['id'], $d0 - 3600, $d0 + 90000]);
  $L = array_values(array_filter(array_map(function ($r) use ($b) { return sv_bk_out($r, $b); }, $q->fetchAll()), function ($x) use ($date) { return substr($x['start'], 0, 10) === $date; }));
  $S = []; foreach (sv_staff_list($b['id']) as $st) { $op = sv_open($b, $st, $date); $o = sv_staff_out($st); $o['open'] = $op ? [(new DateTime('@' . $op[0]))->setTimezone($tz)->format('H:i'), (new DateTime('@' . $op[1]))->setTimezone($tz)->format('H:i')] : null; $S[] = $o; }
  sv_out(['ok' => true, 'date' => $date, 'staff' => $S, 'list' => $L, 'load' => sv_load($b)]); }

if ($A === 'walkin') { $b = sv_mine(sv_in('biz')); $sid = (int)sv_in('staff'); $st = null; foreach (sv_staff_list($b['id']) as $x) if ((int)$x['id'] === $sid) $st = $x; if (!$st) sv_fail('Выберите мастера');
  $dur = max(5, min(600, (int)sv_in('dur', 30))) * 60; $t = (int)sv_in('at', time()); $t = $t > 0 ? $t : time();
  foreach (sv_busy($sid, $t, $t + $dur) as $x) sv_fail('У мастера в это время уже запись', 409);
  $sv = null; foreach (sv_service_list($b['id']) as $x) if ((int)$x['id'] === (int)sv_in('service')) $sv = $x;
  db()->prepare('INSERT INTO svc_booking (biz_id,staff_id,service_id,svc_name,client,client_name,t0,t1,price,status,src,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
    ->execute([$b['id'], $sid, $sv ? $sv['id'] : 0, $sv ? $sv['name'] : sv_s(sv_in('what', 'Без записи'), 200), sv_ph(sv_in('phone')), sv_s(sv_in('name'), 120), $t, $t + $dur, $sv ? (float)$sv['price'] : (float)sv_in('price'), 'come', sv_in('src') === 'phone' ? 'phone' : 'walkin', time(), time()]);
  sv_out(['ok' => true, 'id' => (int)db()->lastInsertId()]); }

if ($A === 'bstatus') { $q = db()->prepare('SELECT * FROM svc_booking WHERE id=?'); $q->execute([(int)sv_in('id')]); $r = $q->fetch(); if (!$r) sv_fail('Запись не найдена', 404);
  sv_mine($r['biz_id']); $s = (string)sv_in('status'); if (!in_array($s, ['ok', 'come', 'done', 'noshow', 'cancel'], true)) sv_fail('Неверное состояние');
  db()->prepare('UPDATE svc_booking SET status=?, updated_at=? WHERE id=?')->execute([$s, time(), $r['id']]);
  if ($s === 'cancel') sv_pay_refund($r['id']);   // отменил бизнес — клиенту всё возвращается
  if ($s === 'done' && (int)sv_in('end_now') === 1 && time() < (int)$r['t1']) db()->prepare('UPDATE svc_booking SET t1=? WHERE id=?')->execute([max((int)$r['t0'] + 60, time()), $r['id']]);   // закончили раньше — мастер свободен
  sv_out(['ok' => true]); }

if ($A === 'money') { $b = sv_mine(sv_in('biz')); $q = db()->prepare("SELECT p.*, k.svc_name, k.t0, k.client_name FROM svc_pay p LEFT JOIN svc_booking k ON k.id=p.booking_id WHERE p.biz_id=? AND p.status IN ('paid','refund') ORDER BY p.id DESC LIMIT 300"); $q->execute([$b['id']]);
  $L = $q->fetchAll(); $sum = ['paid' => 0, 'fee' => 0, 'payout' => 0, 'due' => 0, 'sent' => 0];
  foreach ($L as $p) if ($p['status'] === 'paid') { $sum['paid'] += $p['amount']; $sum['fee'] += $p['fee']; $sum['payout'] += $p['payout']; $sum[$p['payout_status'] === 'sent' ? 'sent' : 'due'] += $p['payout']; }
  sv_out(['ok' => true, 'sum' => array_map(function ($v) { return round($v, 2); }, $sum), 'fee_pct' => SVC_FEE_PCT, 'cur' => $b['cur'],
    'list' => array_map(function ($p) { return ['id' => (int)$p['id'], 'what' => $p['svc_name'], 'client' => $p['client_name'], 't0' => (int)$p['t0'], 'amount' => (float)$p['amount'], 'fee' => (float)$p['fee'], 'payout' => (float)$p['payout'], 'status' => $p['status'], 'payout_status' => $p['payout_status']]; }, $L)]); }

/* ====================== руководитель ИТК ====================== */
if ($A === 'admin') { if (!$me['boss']) sv_fail('Только для руководителя', 403);
  $B = db()->query("SELECT b.id,b.kind,b.name,b.city,b.status,b.owner,b.legal_type,b.inn,b.seen_at,
     (SELECT COUNT(*) FROM svc_booking k WHERE k.biz_id=b.id AND k.created_at>UNIX_TIMESTAMP()-30*86400) AS bk30,
     (SELECT COALESCE(SUM(fee),0) FROM svc_pay p WHERE p.biz_id=b.id AND p.status='paid') AS fee,
     (SELECT COALESCE(SUM(payout),0) FROM svc_pay p WHERE p.biz_id=b.id AND p.status='paid' AND p.payout_status='due') AS due
     FROM svc_biz b ORDER BY b.id DESC LIMIT 500")->fetchAll();
  $T = db()->query("SELECT COALESCE(SUM(amount),0) a, COALESCE(SUM(fee),0) f, COALESCE(SUM(CASE WHEN payout_status='due' THEN payout ELSE 0 END),0) d FROM svc_pay WHERE status='paid'")->fetch();
  sv_out(['ok' => true, 'list' => $B, 'total' => ['through' => (float)$T['a'], 'fee' => (float)$T['f'], 'due' => (float)$T['d']], 'fee_pct' => SVC_FEE_PCT]); }
if ($A === 'payout_mark') { if (!$me['boss']) sv_fail('Только для руководителя', 403);
  db()->prepare("UPDATE svc_pay SET payout_status='sent', payout_at=? WHERE biz_id=? AND status='paid' AND payout_status='due'")->execute([time(), (int)sv_in('biz')]); sv_out(['ok' => true]); }

sv_fail('Неизвестное действие');
