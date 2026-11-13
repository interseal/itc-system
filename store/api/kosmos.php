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
        'pkgs' => array_values(array_map(function ($m) { return $m['ver']; }, ks_pkgs()))];
    }
    ks_out($out);
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
    ks_put(ks_dir() . '/status.json', $st);
    // №533 · в ответ — ключ шлюза (им магазин спрашивает Claude от имени «Космоса») и адрес, по которому сервер раз в минуту будит фоновые задачи
    $g = ks_gw();
    ks_out(['ok' => true, 'gw' => $g['gw'], 'cron' => ks_cron_url()]);
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
