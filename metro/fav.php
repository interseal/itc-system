<?php
// ==== Мой Гуанчжоу · fav.php v4 · 16.10.2026 · у каждого пользователя свои «Мои места» и свой «Дом» ====
// Кто пользователь:
//   • из магазина приложение открывается с меткой u=<токен входа в магазин>; токен проверяем
//     в базе магазина (таблица sessions) — так узнаём телефон покупателя;
//   • босс (сотрудник ИТК с ролью boss) получает прежний общий список data/fav.json — это его места;
//   • без токена (открыли сайт напрямую) — список по метке устройства a=<случайный код>.
// Один пользователь = один файл data/u_<ключ>.json. Папка data закрыта от просмотра снаружи.
// «Дом» — место с ic='home'; у пользователя он один (при назначении нового старый снимается).

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function out($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

$ref = ($_SERVER['HTTP_ORIGIN'] ?? '') . ' ' . ($_SERVER['HTTP_REFERER'] ?? '');
if (strpos($ref, 'metro.itcpartner.store') === false) out(['ok' => false, 'error' => 'forbidden']);

$dir = __DIR__ . '/data';
if (!is_dir($dir)) @mkdir($dir, 0755);
if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\nDeny from all\n");

$in = ($_SERVER['REQUEST_METHOD'] === 'POST') ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];
$tok  = trim((string)($in['u'] ?? ($_GET['u'] ?? '')));
$anon = preg_replace('/[^a-z0-9]/', '', (string)($in['a'] ?? ($_GET['a'] ?? '')));

// ---- кто это ----
$who = null;   // ['key'=>..., 'kind'=>'boss'|'phone'|'anon', 'name'=>...]
if ($tok !== '' && preg_match('/^[A-Za-z0-9_\-]{8,200}$/', $tok)) {
    $cfg = dirname(__DIR__) . '/itcpartner.store/api/config.php';
    if (is_file($cfg)) {
        try {
            ob_start(); @include_once $cfg; ob_end_clean();
            if (function_exists('db')) {
                $st = db()->prepare('SELECT phone, role, staff_role FROM sessions WHERE token = ? LIMIT 1');
                $st->execute([$tok]);
                if ($s = $st->fetch()) {
                    $sr = (string)($s['staff_role'] ?? '');
                    if (($s['role'] ?? '') === 'itc' && ($sr === 'boss' || $sr === 'bossacct' || $sr === '')) $who = ['key' => 'boss', 'kind' => 'boss'];
                    elseif (!empty($s['phone'])) {
                        // №595 · один человек — одни места: руководитель, вошедший в отдельном приложении как покупатель
                        // (тем же номером), получает свой общий список, а не пустой
                        $pd = preg_replace('/\D+/', '', $s['phone']); $isBoss = false;
                        try { foreach (db()->query("SELECT DISTINCT phone FROM sessions WHERE role='itc' AND staff_role IN ('boss','bossacct')")->fetchAll(PDO::FETCH_COLUMN) as $bp) { $bd = preg_replace('/\D+/', '', (string)$bp); if ($bd !== '' && substr($bd, -10) === substr($pd, -10)) $isBoss = true; } } catch (Throwable $e) {}
                        $who = $isBoss ? ['key' => 'boss', 'kind' => 'boss'] : ['key' => 'p_' . md5($pd), 'kind' => 'phone'];
                    }
                }
            }
        } catch (Throwable $e) { /* база магазина недоступна — ниже попробуем метку устройства */ }
    }
}
if (!$who) {
    if (strlen($anon) >= 16 && strlen($anon) <= 48) $who = ['key' => 'a_' . md5($anon), 'kind' => 'anon'];
    else out(['ok' => false, 'error' => 'no_user', 'list' => []]);
}
$file = ($who['kind'] === 'boss') ? "$dir/fav.json" : "$dir/u_{$who['key']}.json";

function load($file) {
  if (!is_file($file)) return [];
  $a = json_decode(file_get_contents($file), true);
  return is_array($a) ? $a : [];
}
function save($file, $a) {
  $tmp = $file . '.tmp';
  file_put_contents($tmp, json_encode(array_values($a), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
  rename($tmp, $file);
}
function clean($s, $n) { return mb_substr(trim(strip_tags((string)$s)), 0, $n); }

$list = load($file);
// №595 · места, сохранённые на телефоне без входа (по метке устройства), переносим в общий список человека — один раз
if ($who['kind'] !== 'anon' && strlen($anon) >= 16 && strlen($anon) <= 48) {
  $af = "$dir/u_a_" . md5($anon) . '.json';
  if (is_file($af)) {
    $have = []; foreach ($list as $r) $have[preg_replace('/\s+/u', '', (string)($r['zh'] ?? ''))] = 1;
    foreach (load($af) as $r) { $k = preg_replace('/\s+/u', '', (string)($r['zh'] ?? '')); if ($k === '' || isset($have[$k]) || count($list) >= 300) continue;
      if (($r['ic'] ?? '') === 'home') foreach ($list as $x) if (($x['ic'] ?? '') === 'home') { $r['ic'] = 'location_on'; break; }
      $list[] = $r; $have[$k] = 1; }
    save($file, $list); @rename($af, $af . '.merged');
  }
}
// №595 · настройки и история (фон, язык, город, прошлые маршруты, поиски) — общие для всех приложений человека
$cfgf = ($who['kind'] === 'boss') ? "$dir/cfg_boss.json" : "$dir/cfg_{$who['key']}.json";
if (isset($_GET['cfg']) || ($in['op'] ?? '') === 'cfg') {
  $c = is_file($cfgf) ? (json_decode((string)file_get_contents($cfgf), true) ?: []) : [];
  if (($in['op'] ?? '') === 'cfg' && is_array($in['cfg'] ?? null) && (int)($in['t'] ?? 0) >= (int)($c['t'] ?? 0)) {
    $v = []; foreach ($in['cfg'] as $k => $x) if (preg_match('/^metro_[a-z0-9_]{1,30}$/', (string)$k) && is_string($x) && strlen($x) <= 60000) $v[$k] = $x;
    $c = ['t' => (int)$in['t'], 'v' => $v];
    $j = json_encode($c, JSON_UNESCAPED_UNICODE); if (strlen($j) < 400000) { file_put_contents($cfgf . '.tmp', $j, LOCK_EX); rename($cfgf . '.tmp', $cfgf); }
  }
  out(['ok' => true, 'cfg' => $c ?: (object)[], 'who' => $who['kind']]);
}
// v3: одинаковые названия — оставляем первое, дубли убираем
$seen = []; $clean = []; $changed = false;
foreach ($list as $r) { $k = preg_replace('/\s+/u', '', (string)($r['zh'] ?? '')); if ($k !== '' && isset($seen[$k])) { $changed = true; continue; } $seen[$k] = 1; $clean[] = $r; }
$list = $clean;
if ($changed) save($file, $list);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['ok' => true, 'list' => $list, 'who' => $who['kind']]);

$op = $in['op'] ?? '';

if ($op === 'add' || $op === 'update') {
  $it = $in['item'] ?? [];
  $id = preg_replace('/[^a-z0-9_]/i', '', (string)($it['id'] ?? '')) ?: ('p' . time() . mt_rand(100, 999));
  $loc = (isset($it['loc'][0], $it['loc'][1]) && is_numeric($it['loc'][0]) && is_numeric($it['loc'][1])) ? [(float)$it['loc'][0], (float)$it['loc'][1]] : null;
  $ic  = preg_replace('/[^a-z_]/', '', (string)($it['ic'] ?? ''));
  $row = ['id' => $id, 'zh' => clean($it['zh'] ?? '', 120), 'q' => clean($it['q'] ?? ($it['zh'] ?? ''), 160), 'city' => clean($it['city'] ?? '', 10),
          'ru' => clean($it['ru'] ?? '', 160), 'en' => clean($it['en'] ?? '', 160), 'addr' => clean($it['addr'] ?? '', 300),
          'loc' => $loc, 'photo' => clean($it['photo'] ?? '', 500), 'ic' => $ic];
  if ($op === 'update' && $row['zh'] === '') { foreach ($list as $r0) if ($r0['id'] === $id) $row['zh'] = $r0['zh']; }
  if ($row['zh'] === '') out(['ok' => false, 'error' => 'empty']);
  $found = false;
  $key = preg_replace('/\s+/u', '', $row['zh']);
  foreach ($list as $r0) { if (preg_replace('/\s+/u', '', (string)$r0['zh']) === $key && $r0['id'] !== $id) out(['ok' => true, 'list' => $list, 'id' => $r0['id'], 'dup' => true]); }
  // «Дом» один: назначили новый — у прежнего значок снимаем
  if ($ic === 'home') { foreach ($list as &$r1) { if (($r1['ic'] ?? '') === 'home' && $r1['id'] !== $id) $r1['ic'] = 'location_on'; } unset($r1); }
  foreach ($list as &$r) { if ($r['id'] === $id) {
      $upd = array_filter($row, function ($v) { return $v !== '' && $v !== null; });
      if (array_key_exists('ic', $it)) $upd['ic'] = $ic;   // значок можно и снять
      $r = array_merge($r, $upd); $found = true; } }
  unset($r);
  if (!$found) { if (count($list) >= 300) out(['ok' => false, 'error' => 'too_many']); $list[] = $row; }
  save($file, $list);
  out(['ok' => true, 'list' => $list, 'id' => $id]);
}
if ($op === 'del') {
  $id = (string)($in['id'] ?? '');
  $list = array_values(array_filter($list, function ($r) use ($id) { return $r['id'] !== $id; }));
  save($file, $list);
  out(['ok' => true, 'list' => $list]);
}
out(['ok' => false, 'error' => 'bad_op']);
