<?php
// ==== ИТК · api/kosmos.php (13.11.2026) · связь магазина с сервером «Космоса» (Сингапур) ====
// «Космос» — помощник ИТК на Claude, живёт на своём сервере (kosmos.itcpartner.store).
// Этот файл — его «посольство» в магазине:
//   who      — сервер «Космоса» спрашивает: чей это пропуск, какая роль, руководитель ли (как hk_who.php);
//   status   — для страницы /apps/kosmos/: на связи ли сервер, его адрес, версия;
//   connect  — руководитель получает одноразовую команду установки (30 минут);
//   install  — по этой команде сервер скачивает установщик; служебный ключ создаётся тут же, в магазине
//              остаётся только его отпечаток (sha256), сам ключ уходит один раз на сервер;
//   pkgs/pkg — сервер забирает новые версии «Космоса» (их подпись проверяет сам сервер);
//   report   — сервер раз в минуту сообщает, что жив, и получает ключ шлюза и адрес фоновых задач (№533);
//   backup/backups/backup_get — ночные копии его базы (7 последних).
// Всё хранится в uploads/kosmos: пакеты — в pkg/, остальное — в закрытой папке со случайным именем.

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/kosmos_lib.php';   // №533 · общие функции «Космоса» (их же использует api/kosmos_flow.php)

function ks_out($a, $code = 200) {
  if (!headers_sent()) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); }
  echo json_encode($a, JSON_UNESCAPED_UNICODE); exit;
}
function ks_body() { static $b = null; if ($b === null) { $j = json_decode((string)file_get_contents('php://input'), true); $b = is_array($j) ? $j : []; } return $b; }

/* Сервер «Космоса» предъявляет служебный ключ. Здесь хранится только его отпечаток. */
function ks_server() {
  $k = (string)($_SERVER['HTTP_X_KOSMOS_TOKEN'] ?? '');
  $sha = (string)(ks_json(ks_dir() . '/auth.json')['sha'] ?? '');
  if ($sha === '' || strlen($k) < 32 || !hash_equals($sha, hash('sha256', $k))) ks_out(['ok' => false, 'error' => 'нет доступа'], 403);
}
/* Сотрудник по пропуску Экосистемы (пропуск — в адресе ?t= или в теле запроса). */
function ks_staff() {
  if (empty($_GET['t']) && !empty(ks_body()['t'])) { $_GET['t'] = (string)ks_body()['t']; $_REQUEST['t'] = $_GET['t']; }
  return authUser('itc');
}
function ks_boss($s) { $r = (string)($s['staff_role'] ?? ''); return $r === 'boss' || $r === ''; }
function ks_online($st) { return !empty($st['at']) && time() - (int)$st['at'] < 180; }

/* №541 · деньги Claude. Остатка на счёте Claude не отдаёт (в API такого запроса нет), поэтому сервер «Космоса»
   считает расход каждого ключа сам (стоимость каждого ответа по ценам Claude, в миллионных долях доллара) и раз
   в минуту присылает его в отчёте. Руководитель один раз указывает остаток со страницы Billing на
   platform.claude.com — дальше отсюда вычитается расход. Ключи одного счёта (организации) тратят одни деньги,
   поэтому остаток — по счёту. Отметки остатка — claude_bal.json: {счёт: {amount_u, base: {id ключа: расход всего}, at, by}}. */
/* №545 · обновления, собранные сервером «Космоса»: лежат в закрытой папке ready/, ставит руководитель одной кнопкой */
function ks_upd_key() {
  $f = __DIR__ . '/upd_key.php';
  return is_file($f) && preg_match('~([0-9a-f]{64})~', (string)file_get_contents($f), $m) ? $m[1] : '';
}
function ks_ready_dir() { $d = ks_dir() . '/ready'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }
function ks_ready_list() {
  $o = [];
  foreach (glob(ks_ready_dir() . '/obnovlenie_*.json') ?: [] as $f) { $m = ks_json($f); if ((int)($m['n'] ?? 0) > 0 && is_file(ks_ready_dir() . '/obnovlenie_' . (int)$m['n'] . '.zip')) $o[] = $m; }
  usort($o, function ($a, $b) { return (int)$a['n'] - (int)$b['n']; });
  return $o;
}
function ks_money_clean($list) {
  $out = [];
  foreach (array_slice(is_array($list) ? $list : [], 0, 30) as $k) {
    if (!is_array($k) || (int)($k['id'] ?? 0) <= 0) continue;
    $r = ['id' => (int)$k['id'], 'name' => mb_substr((string)($k['name'] ?? ''), 0, 60), 'last4' => preg_replace('~[^A-Za-z0-9_\-]~', '', substr((string)($k['last4'] ?? ''), -4)),
          'active' => !empty($k['active']), 'del' => !empty($k['del']), 'org' => preg_replace('~[^a-f0-9\-]~', '', strtolower(substr((string)($k['org'] ?? ''), 0, 64))),
          'out' => !empty($k['out']), 'st' => mb_substr((string)($k['st'] ?? ''), 0, 200)];
    foreach (['t', 'w', 'm', 'a'] as $f) $r[$f] = max(0, (int)($k[$f] ?? 0));
    $r['d7'] = max(0, min(7, (int)($k['d7'] ?? 7)));   // сколько из последних 7 дней ключ уже работал
    $out[] = $r;
  }
  return $out;
}
function ks_money_groups($money) {
  $g = [];
  foreach ($money as $k) { $id = $k['org'] !== '' ? $k['org'] : 'k' . $k['id']; $g[$id][] = $k; }
  return $g;
}
function ks_money_mark($marks, $gid, $keys) {
  if (isset($marks[$gid])) return $marks[$gid];
  foreach ($keys as $k) if (isset($marks['k' . $k['id']])) return $marks['k' . $k['id']];   // отметили до того, как стал известен счёт
  return null;
}
function ks_money_view($st) {
  if (!isset($st['money']) || !is_array($st['money'])) return null;
  $marks = ks_json(ks_dir() . '/claude_bal.json');
  $usd = function ($u) { return round($u / 1000000, 2); };
  $out = [];
  foreach (ks_money_groups(ks_money_clean($st['money'])) as $gid => $keys) {   // ещё раз через проверку: в файле могут быть старые записи
    $live = array_values(array_filter($keys, function ($k) { return !$k['del']; }));
    if (!$live) continue;
    $sum = ['t' => 0, 'w' => 0, 'm' => 0]; $span = 1;
    foreach ($keys as $k) { foreach ($sum as $f => $v) $sum[$f] += $k[$f]; $span = max($span, (int)$k['d7']); }
    $daily = $sum['w'] / $span;   // в среднем в день: за 7 дней, а если счёт работает меньше недели — за эти дни
    $g = ['g' => (string)$gid, 'keys' => array_map(function ($k) { return ['id' => $k['id'], 'name' => $k['name'], 'last4' => $k['last4'], 'active' => $k['active'], 'out' => $k['out'], 'st' => $k['st']]; }, $live),
          'active' => (bool)array_filter($live, function ($k) { return $k['active']; }), 'out' => (bool)array_filter($live, function ($k) { return $k['out']; }),
          'today' => $usd($sum['t']), 'week' => $usd($sum['w']), 'month' => $usd($sum['m']), 'per_day' => $usd($daily), 'span' => $span, 'mark' => null];
    $m = ks_money_mark($marks, $gid, $keys);
    if ($m) {
      $spent = 0;
      foreach ($keys as $k) $spent += max(0, $k['a'] - (int)($m['base'][(string)$k['id']] ?? 0));
      $left = ((int)$m['amount_u'] - $spent) / 1000000;
      $g['mark'] = ['amount' => $usd((int)$m['amount_u']), 'at' => date('d.m.Y H:i', (int)$m['at']), 'at_ts' => (int)$m['at'], 'spent' => $usd($spent), 'left' => round($left, 2),
                    'days' => $daily > 0 ? (int)floor(max(0, $left) / ($daily / 1000000)) : null];
    }
    $out[] = $g;
  }
  usort($out, function ($a, $b) { return (int)$b['active'] - (int)$a['active']; });
  return $out;
}

$a = (string)($_GET['a'] ?? '');

switch ($a) {

  case 'who': {
    ks_server();
    $S = ks_staff();
    $role = (string)($S['staff_role'] ?? '');
    $name = ''; $active = true;
    try {
      $q = db()->prepare("SELECT name, active FROM staff WHERE REPLACE(phone,' ','')=REPLACE(?,' ','') LIMIT 1");
      $q->execute([(string)($S['phone'] ?? '')]);
      if ($r = $q->fetch()) { $name = (string)$r['name']; $active = (int)$r['active'] === 1; }
    } catch (Throwable $e) {}
    if (!$active && !ks_boss($S)) ks_out(['ok' => false, 'error' => 'Доступ сотрудника отключён'], 403);
    if ($name === '') $name = trim((string)($S['staff_name'] ?? $S['name'] ?? '')) ?: (ks_boss($S) ? 'Руководитель' : '');
    ks_out(['ok' => true, 'role' => 'itc', 'phone' => (string)($S['phone'] ?? ''), 'staff_role' => $role, 'name' => $name, 'boss' => ks_boss($S)]);
  }

  case 'status': {
    $S = ks_staff();
    $st = ks_json(ks_dir() . '/status.json');
    $on = ks_online($st);
    $out = ['ok' => true, 'boss' => ks_boss($S), 'online' => $on, 'ready' => $on && !empty($st['https']),
            'url' => !empty($st['https']) ? 'https://' . KS_HOST . '/' : '', 'ver' => (string)($st['ver'] ?? ''),
            'linked' => (string)(ks_json(ks_dir() . '/auth.json')['sha'] ?? '') !== ''];
    if (ks_boss($S)) {
      $out['detail'] = ['at' => !empty($st['at']) ? date('d.m.Y H:i', (int)$st['at']) : '', 'ip' => (string)($st['ip'] ?? ''),
        'dns_ok' => !empty($st['dns_ok']), 'https' => !empty($st['https']), 'worker' => !empty($st['worker']), 'key' => !empty($st['key']),
        'disk_free_gb' => $st['disk_free_gb'] ?? null, 'backup' => (string)($st['backup'] ?? ''), 'host' => KS_HOST,
        'pkgs' => array_values(array_map(function ($m) { return $m['ver']; }, ks_pkgs())),
        // №541 · для строки «Сингапур» на странице обновлений и денег Claude
        'at_ts' => (int)($st['at'] ?? 0), 'now' => time(), 'load' => $st['load'] ?? null, 'up' => (string)($st['up'] ?? ''),
        'git' => $st['git'] ?? null, 'money' => ks_money_view($st), 'bw' => $st['bw'] ?? null];
    }
    ks_out($out);
  }

  case 'upd_put': {                                  // №545 · сервер отдаёт собранное и подписанное обновление
    ks_server();
    $n = (int)($_GET['n'] ?? 0); $zip = (string)file_get_contents('php://input'); $uk = ks_upd_key();
    if ($n < 1 || strlen($zip) < 100 || strlen($zip) > 60 * 1048576) ks_out(['ok' => false, 'error' => 'нет файла'], 400);
    if ($uk === '') ks_out(['ok' => false, 'error' => 'в магазине нет ключа обновлений'], 500);
    $tmp = tempnam(sys_get_temp_dir(), 'ksu'); file_put_contents($tmp, $zip);
    $z = new ZipArchive(); if ($z->open($tmp) !== true) { @unlink($tmp); ks_out(['ok' => false, 'error' => 'это не архив'], 400); }
    $man = (string)$z->getFromName('manifest.json'); $sig = trim((string)$z->getFromName('sig.txt')); $z->close();
    $m = json_decode($man, true);
    if (!is_array($m) || (int)($m['n'] ?? 0) !== $n || !hash_equals(hash_hmac('sha256', $man, $uk), $sig)) { @unlink($tmp); ks_out(['ok' => false, 'error' => 'подпись или номер не сходятся'], 400); }
    $d = ks_ready_dir(); rename($tmp, "$d/obnovlenie_$n.zip"); @chmod("$d/obnovlenie_$n.zip", 0600);
    ks_put("$d/obnovlenie_$n.json", ['n' => $n, 'needs' => (int)($m['needs'] ?? 0), 'title' => (string)($m['title'] ?? ''), 'by' => (string)($m['by'] ?? ''), 'what' => array_values(array_map('strval', (array)($m['what'] ?? []))), 'size' => strlen($zip), 'at' => time()]);
    $all = ks_ready_list(); foreach (array_slice($all, 0, max(0, count($all) - 10)) as $old) { @unlink("$d/obnovlenie_{$old['n']}.zip"); @unlink("$d/obnovlenie_{$old['n']}.json"); }
    ks_out(['ok' => true]);
  }

  case 'upd_ready': {                                // №545 · что сервер подготовил к установке (только руководитель)
    $S = ks_staff(); if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    $r = ks_json(ks_dir() . '/releases.json');
    ks_out(['ok' => true, 'items' => ks_ready_list(), 'err' => (string)($r['last_err'] ?? '')]);
  }

  /* №553 · правки разработчиков, которые ждут решения руководителя, — прямо на странице «Обновления» */
  case 'dev_pending': {
    $S = ks_staff(); if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    [$c, $j] = ks_gw_call(['a' => 'dev', 'op' => 'list'], 10);
    if ($c !== 200 || empty($j['ok'])) ks_out(['ok' => true, 'items' => [], 'off' => true]);
    $it = [];
    foreach ((array)($j['tasks'] ?? []) as $t) if (in_array($t['status'] ?? '', ['needs_ok', 'question'], true))
      $it[] = ['id' => (int)$t['id'], 'kind' => $t['status'] === 'question' ? 'q' : 'ok', 'q' => (string)($t['question'] ?? ''),
               'by' => ($t['engine'] ?? '') === 'deepseek' ? 'Фил' : 'Космос', 'text' => mb_substr((string)$t['text'], 0, 600),
               'result' => mb_substr((string)$t['result'], 0, 1500), 'note' => (string)$t['error'], 'n' => (int)$t['release_n']];
    usort($it, function ($a, $b) { return $a['id'] - $b['id']; });   // старые — первыми
    ks_out(['ok' => true, 'items' => $it]);
  }
  case 'dev_answer': {   // №554 · ответ на вопрос разработчика — со страницы «Обновления»
    $S = ks_staff(); if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    [$c, $j] = ks_gw_call(['a' => 'dev', 'op' => 'answer', 'id' => (int)($in['id'] ?? 0), 'text' => (string)($in['text'] ?? ''), 'who' => (string)($S['name'] ?? $S['phone'] ?? 'руководитель')], 15);
    if ($c !== 200 || empty($j['ok'])) ks_out(['ok' => false, 'error' => (string)($j['error'] ?? 'нет связи с сервером «Космоса»')], 400);
    ks_out(['ok' => true]);
  }
  case 'dev_decide': {
    $S = ks_staff(); if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    $in = json_decode((string)file_get_contents('php://input'), true) ?: [];
    [$c, $j] = ks_gw_call(['a' => 'dev', 'op' => 'decide', 'id' => (int)($in['id'] ?? 0), 'ok' => !empty($in['ok']), 'who' => (string)($S['name'] ?? $S['phone'] ?? 'руководитель')], 15);
    if ($c !== 200 || empty($j['ok'])) ks_out(['ok' => false, 'error' => (string)($j['error'] ?? 'нет связи с сервером «Космоса»')], 400);
    ks_out(['ok' => true]);
  }

  case 'upd_get': {
    $S = ks_staff(); if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    $n = (int)($_GET['n'] ?? 0); $f = ks_ready_dir() . "/obnovlenie_$n.zip";
    if ($n < 1 || !is_file($f)) ks_out(['ok' => false, 'error' => 'Такого обновления нет'], 404);
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($f)); header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="obnovlenie_' . $n . '.zip"'); readfile($f); exit;
  }

  case 'claude_bal': {                               // №541 · руководитель указывает остаток на счёте Claude
    $S = ks_staff();
    if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Только для руководителя'], 403);
    $b = ks_body();
    $st = ks_json(ks_dir() . '/status.json');
    if (!isset($st['money']) || !is_array($st['money'])) ks_out(['ok' => false, 'error' => 'Сервер «Космоса» ещё не прислал расход по ключам — подождите пару минут после его обновления до 0.3.3']);
    $gid = (string)($b['g'] ?? '');
    $groups = ks_money_groups($st['money']);
    if (!isset($groups[$gid])) ks_out(['ok' => false, 'error' => 'Такого счёта нет — обновите страницу']);
    $marks = ks_json(ks_dir() . '/claude_bal.json');
    foreach ($groups[$gid] as $k) unset($marks['k' . $k['id']]);
    $raw = trim(str_replace([' ', ',', '$'], ['', '.', ''], (string)($b['amount'] ?? '')));
    if ($raw === '' || !empty($b['clear'])) unset($marks[$gid]);
    else {
      if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100000) ks_out(['ok' => false, 'error' => 'Напишите сумму в долларах, например 37.50']);
      $base = [];
      foreach ($st['money'] as $k) $base[(string)$k['id']] = $k['a'];
      $marks[$gid] = ['amount_u' => (int)round((float)$raw * 1000000), 'base' => $base, 'at' => time(), 'by' => (string)($S['phone'] ?? $S['login'] ?? '')];
    }
    ks_put(ks_dir() . '/claude_bal.json', $marks);
    ks_out(['ok' => true, 'money' => ks_money_view($st)]);
  }

  case 'connect': {
    $S = ks_staff();
    if (!ks_boss($S)) ks_out(['ok' => false, 'error' => 'Подключает сервер только руководитель'], 403);
    if (!ks_pkgs()) ks_out(['ok' => false, 'error' => 'В магазине нет установочного пакета «Космоса» — сначала установите обновление с ним'], 400);
    $code = bin2hex(random_bytes(16));
    foreach (glob(ks_dir('pending') . '/*.json') ?: [] as $f) if (filemtime($f) < time() - 1800) @unlink($f);
    ks_put(ks_dir('pending') . '/' . hash('sha256', $code) . '.json', ['exp' => time() + 1800, 'by' => (string)($S['phone'] ?? '')]);
    ks_out(['ok' => true, 'cmd' => "curl -fsSL '" . KS_STORE . "/api/kosmos.php?a=install&c=" . $code . "' | sudo bash", 'minutes' => 30]);
  }

  case 'install': {
    $code = preg_replace('~[^a-f0-9]~', '', (string)($_GET['c'] ?? ''));
    $f = ks_dir('pending') . '/' . hash('sha256', $code) . '.json';
    $p = ks_json($f);
    header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store');
    if (strlen($code) !== 32 || !$p || (int)$p['exp'] < time()) { echo "echo 'Команда устарела или уже использована. Получите новую на странице «Космоса» в Экосистеме.'; exit 1\n"; exit; }
    @unlink($f);                                     // одноразовая
    $pkgs = ks_pkgs();
    if (!$pkgs) { echo "echo 'В магазине нет установочного пакета «Космоса».'; exit 1\n"; exit; }
    $m = end($pkgs);
    $token = bin2hex(random_bytes(32));             // служебный ключ сервера «Космоса»
    ks_put(ks_dir() . '/auth.json', ['sha' => hash('sha256', $token), 'at' => date('Y-m-d H:i:s'), 'by' => (string)$p['by']]);
    @unlink(ks_dir() . '/status.json');
    echo ks_install_script($token, $m);
    exit;
  }

  case 'pkgs': {
    ks_server();
    ks_out(['ok' => true, 'pkgs' => array_values(ks_pkgs())]);
  }

  case 'pkg': {
    ks_server();
    $name = basename((string)($_GET['f'] ?? ''));
    $file = ks_pkg_dir() . '/' . $name;
    if (!preg_match('~^kosmos-[0-9.]+-[a-z0-9]+\.tar\.gz$~', $name) || !is_file($file)) ks_out(['ok' => false, 'error' => 'нет такого пакета'], 404);
    header('Content-Type: application/gzip'); header('Content-Length: ' . filesize($file)); header('Cache-Control: no-store');
    readfile($file); exit;
  }

  case 'report': {
    ks_server();
    $b = ks_body();
    $st = ['at' => time()];
    foreach (['ver', 'ip', 'backup', 'up'] as $k) $st[$k] = mb_substr((string)($b[$k] ?? ''), 0, 80);
    foreach (['dns_ok', 'https', 'worker', 'key'] as $k) $st[$k] = !empty($b[$k]);
    $st['disk_free_gb'] = isset($b['disk_free_gb']) ? round((float)$b['disk_free_gb'], 1) : null;
    // №541 · нагрузка, рабочая копия GitHub и расход денег Claude по ключам — для страницы обновлений
    $st['load'] = isset($b['load']) && is_numeric($b['load']) ? round((float)$b['load'], 2) : null;
    if (is_array($b['git'] ?? null)) $st['git'] = ['ok' => !empty($b['git']['ok']), 'hash' => preg_replace('~[^a-f0-9]~', '', substr((string)($b['git']['hash'] ?? ''), 0, 12)),
      'at' => mb_substr((string)($b['git']['at'] ?? ''), 0, 20), 'err' => mb_substr((string)($b['git']['err'] ?? ''), 0, 160)];
    if (is_array($b['money'] ?? null)) $st['money'] = ks_money_clean($b['money']);
    // №542 · сейф Bitwarden: сервер сам кладёт туда паспорт и ключи
    if (is_array($b['bw'] ?? null)) $st['bw'] = ['set' => !empty($b['bw']['set']), 'ok' => !empty($b['bw']['ok']), 'at' => mb_substr((string)($b['bw']['at'] ?? ''), 0, 20),
      'err' => mb_substr((string)($b['bw']['err'] ?? ''), 0, 200), 'n' => max(0, (int)($b['bw']['n'] ?? 0)), 'project' => mb_substr((string)($b['bw']['project'] ?? ''), 0, 60)];
    ks_put(ks_dir() . '/status.json', $st);
    // №533 · в ответ — ключ шлюза (им магазин спрашивает Claude от имени «Космоса») и адрес, по которому сервер раз в минуту будит фоновые задачи
    $g = ks_gw();
    // №545 · сервер сам собирает обновления: ключ обновлений отдаём ему, пока он его не получил
    $out = ['ok' => true, 'gw' => $g['gw'], 'cron' => ks_cron_url()];
    if (!empty($b['want_upd_key']) && ($uk = ks_upd_key()) !== '') $out['upd_key'] = $uk;
    if (is_array($b['releases'] ?? null)) ks_put(ks_dir() . '/releases.json', ['at' => time(), 'last_err' => mb_substr((string)($b['releases']['last_err'] ?? ''), 0, 300)]);
    ks_out($out);
  }

  case 'backup': {                                   // ночная копия базы частями: ?day=ГГГГММДД&off=N&last=1
    ks_server();
    $day = preg_replace('~\D~', '', (string)($_GET['day'] ?? ''));
    $off = (int)($_GET['off'] ?? 0);
    if (strlen($day) !== 8) ks_out(['ok' => false, 'error' => 'день'], 400);
    $part = ks_dir('backup') . '/' . $day . '.db.gz.part';
    $have = is_file($part) ? filesize($part) : 0;
    if ($off === 0 && $have) { @unlink($part); $have = 0; }
    if ($off !== $have) ks_out(['ok' => false, 'error' => 'смещение', 'have' => $have], 409);
    $chunk = (string)file_get_contents('php://input');
    if (strlen($chunk) > 8 * 1024 * 1024) ks_out(['ok' => false, 'error' => 'кусок больше 8 МБ'], 413);
    file_put_contents($part, $chunk, FILE_APPEND | LOCK_EX);
    if (!empty($_GET['last'])) {
      $sha = (string)($_GET['sha'] ?? '');
      if ($sha !== '' && !hash_equals($sha, hash_file('sha256', $part))) { @unlink($part); ks_out(['ok' => false, 'error' => 'копия пришла с ошибкой'], 422); }
      rename($part, ks_dir('backup') . '/' . $day . '.db.gz');
      $all = glob(ks_dir('backup') . '/*.db.gz') ?: []; sort($all);
      while (count($all) > 7) @unlink(array_shift($all));
    }
    ks_out(['ok' => true, 'have' => is_file($part) ? filesize($part) : 0]);
  }

  case 'backups': {
    ks_server();
    $out = [];
    foreach (glob(ks_dir('backup') . '/*.db.gz') ?: [] as $f) $out[] = ['day' => basename($f, '.db.gz'), 'size' => filesize($f), 'sha' => hash_file('sha256', $f)];
    ks_out(['ok' => true, 'backups' => $out]);
  }

  case 'backup_get': {
    ks_server();
    $day = preg_replace('~\D~', '', (string)($_GET['day'] ?? ''));
    $f = ks_dir('backup') . '/' . $day . '.db.gz';
    if (strlen($day) !== 8 || !is_file($f)) ks_out(['ok' => false, 'error' => 'нет такой копии'], 404);
    header('Content-Type: application/gzip'); header('Content-Length: ' . filesize($f)); readfile($f); exit;
  }
}
ks_out(['ok' => false, 'error' => 'неизвестное действие'], 404);

/* Установщик: сохраняет служебный ключ, скачивает пакет, проверяет отпечаток и подпись, запускает установку из пакета. */
function ks_install_script($token, $m) {
  $q = function ($s) { return "'" . str_replace("'", "'\\''", (string)$s) . "'"; };
  return "#!/bin/bash\n"
    . "# ИТК · установка «Космоса» " . $m['ver'] . " · " . date('d.m.Y H:i') . " · одноразовая\n"
    . "set -euo pipefail\n"
    . "[ \"\$(id -u)\" = 0 ] || { echo 'Запустите через sudo'; exit 1; }\n"
    . "echo '== Космос: начинаю установку ' " . $q($m['ver']) . "\n"
    . "command -v curl >/dev/null || { apt-get update -qq; apt-get install -y -qq curl; }\n"
    . "mkdir -p /etc/kosmos; chmod 711 /etc/kosmos\n"
    . "(umask 077; printf '%s' " . $q($token) . " > /etc/kosmos/store_token)\n"
    . "printf '%s' " . $q($m['pub']) . " > /etc/kosmos/sign.pub\n"
    . "T=\$(mktemp -d); trap 'rm -rf \"\$T\"' EXIT\n"
    . "curl -fsS -H \"X-Kosmos-Token: \$(cat /etc/kosmos/store_token)\" -o \"\$T/p.tgz\" " . $q(KS_STORE . '/api/kosmos.php?a=pkg&f=' . rawurlencode($m['file'])) . "\n"
    . "[ \"\$(sha256sum \"\$T/p.tgz\" | cut -d' ' -f1)\" = " . $q($m['sha256']) . " ] || { echo 'Пакет повреждён (отпечаток не совпал)'; exit 1; }\n"
    . "mkdir -p \"\$T/x\"; tar -xzf \"\$T/p.tgz\" -C \"\$T/x\"\n"
    . "bash \"\$T/x/kosmos/install/install.sh\" " . $q($m['ver']) . " " . $q($m['sig']) . " " . $q($m['sha256']) . "\n";
}
