<?php
// ==== ИТК · КОСМОС · bin/agent.php · обслуживание сервера ====
// Служба kosmos-agent (раз в минуту, от root):
//   — сообщает магазину, что сервер жив (страница «Космоса» в Экосистеме показывает это руководителю);
//     в ответ магазин даёт ключ шлюза (0.2.0) и адрес, по которому агент будит фоновые задачи «Космоса»;
//   — раз в 10 минут проверяет новую версию «Космоса» в магазине, сверяет подпись и ставит её;
//   — пока нет https: когда DNS-запись kosmos.itcpartner.store указывает сюда, получает сертификат (certbot);
//   — ночью (03:00–04:59 по Бишкеку) делает копию базы и отправляет в магазин (там хранятся 7 последних);
//   — 0.3.0: раз в 10 минут обновляет рабочую копию хранилища кода ИТК из GitHub (/srv/itc-system, только чтение).
// Подкоманды: ip | update | backup | git | restore [force] | dbinfo | dbbackup <файл>  (две последние — от www-data).

if (PHP_SAPI !== 'cli') exit;
const A_HOST = 'kosmos.itcpartner.store';
// 0.3.0 · опубликованный ключ github.com (сверяем при входе в GitHub; константы — до первого exit)
const A_GIT_HOSTKEY = 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl';

$cfgFile = getenv('KOSMOS_CONFIG') ?: '/etc/kosmos/config.php';
$C = (is_file($cfgFile) ? (array)(require $cfgFile) : []) + [
  'store_url' => 'https://itcpartner.store', 'db' => '/opt/kosmos/data/kosmos.sqlite', 'tz' => 'Asia/Bishkek',
  'store_token_file' => '/etc/kosmos/store_token', 'sign_pub_file' => '/etc/kosmos/sign.pub', 'gw_key_file' => '/etc/kosmos/gw.key',
  'app_dir' => '/opt/kosmos', 'agent_state' => '/var/lib/kosmos-agent.json', 'backup_dir' => '/var/backups/kosmos',
  'db_user' => 'www-data', 'meta_url' => 'http://100.100.100.200/latest/meta-data/',
  // 0.3.0 · хранилище кода ИТК на GitHub и его рабочая копия на этом сервере
  'git_repo' => 'git@github.com:interseal/itc-system.git', 'git_dir' => '/srv/itc-system', 'git_key' => '/etc/kosmos/github_deploy',
];
date_default_timezone_set((string)$C['tz']);
$cmd = (string)($argv[1] ?? '');

switch ($cmd) {
  case 'dbinfo':   echo json_encode(a_dbinfo()); exit;
  case 'dbbackup': exit(a_dbbackup((string)($argv[2] ?? '')) ? 0 : 1);
  case 'ip':       echo a_ip(), "\n"; exit;
  case 'update':   a_lock(); a_update(true); exit;
  case 'backup':   a_lock(); exit(a_backup(true) ? 0 : 1);
  case 'git':      a_lock(); exit(a_git(true) ? 0 : 1);
  case 'restore':  a_lock(); exit(a_restore(($argv[2] ?? '') === 'force') ? 0 : 1);
  case '':
    a_lock();
    $st = a_state();
    if (time() - (int)($st['upd_at'] ?? 0) > 600) a_update(false);
    a_cert();
    a_backup(false);
    a_report();
    a_git(false);
    exit;
}
fwrite(STDERR, "неизвестная команда\n"); exit(2);

/* ---------------- общее ---------------- */
function a_log(string $s): void { echo date('H:i:s'), ' ', $s, "\n"; }
function a_lock(): void {
  static $h = null;
  $h = fopen(sys_get_temp_dir() . '/kosmos-agent.lock', 'c');
  if (!$h || !flock($h, LOCK_EX | LOCK_NB)) { a_log('уже работает другой проход'); exit(0); }
}
function a_state(): array { global $C; $j = is_file($C['agent_state']) ? json_decode((string)file_get_contents($C['agent_state']), true) : null; return is_array($j) ? $j : []; }
function a_state_put(array $add): void { global $C; $s = $add + a_state(); @file_put_contents($C['agent_state'], json_encode($s, JSON_PRETTY_PRINT), LOCK_EX); }
function a_token(): string { global $C; return is_file($C['store_token_file']) ? trim((string)file_get_contents($C['store_token_file'])) : ''; }

/* Запрос к магазину (api/kosmos.php) со служебным ключом. Возвращает [код, тело]. */
function a_store(string $query, ?string $post = null, ?string $saveTo = null, int $timeout = 60): array {
  global $C;
  $ch = curl_init(rtrim((string)$C['store_url'], '/') . '/api/kosmos.php?' . $query);
  $o = [CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $timeout, CURLOPT_HTTPHEADER => ['X-Kosmos-Token: ' . a_token(), 'Content-Type: application/octet-stream']];
  if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $post; }
  if ($saveTo !== null) { $fh = fopen($saveTo, 'wb'); $o[CURLOPT_FILE] = $fh; } else $o[CURLOPT_RETURNTRANSFER] = true;
  curl_setopt_array($ch, $o);
  $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if (isset($fh)) fclose($fh);
  return [$code, is_string($body) ? $body : ''];
}

/* То, что лежит в базе, читаем от имени www-data (владельца базы), чтобы не испортить права на файлы. */
function a_as_db(string $sub, string $arg = ''): string {
  global $C, $cfgFile;
  $php = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $sub . ($arg !== '' ? ' ' . escapeshellarg($arg) : '');
  $env = 'KOSMOS_CONFIG=' . escapeshellarg($cfgFile) . ' ';
  $line = ($C['db_user'] !== '' && function_exists('posix_geteuid') && posix_geteuid() === 0) ? 'runuser -u ' . escapeshellarg((string)$C['db_user']) . ' -- env ' . $env . $php : 'env ' . $env . $php;
  return (string)shell_exec($line . ' 2>/dev/null');
}

function a_dbinfo(): array {
  global $C;
  if (!is_file($C['db'])) return ['worker' => false, 'key' => false];
  try {
    $d = new SQLite3($C['db'], SQLITE3_OPEN_READONLY); $d->busyTimeout(5000);
    $beat = (int)$d->querySingle("SELECT v FROM settings WHERE k='worker_beat'");
    $key = (int)$d->querySingle("SELECT COUNT(*) FROM keys WHERE active=1 AND deleted=0");
    return ['worker' => $beat > 0 && time() - $beat < 120, 'key' => $key > 0];
  } catch (Throwable $e) { return ['worker' => false, 'key' => false]; }
}

function a_dbbackup(string $to): bool {
  global $C;
  if ($to === '' || !is_file($C['db'])) return false;
  try {
    $src = new SQLite3($C['db'], SQLITE3_OPEN_READONLY); $src->busyTimeout(10000);
    @unlink($to);
    $dst = new SQLite3($to);
    $ok = $src->backup($dst);
    $dst->close(); $src->close();
    return $ok;
  } catch (Throwable $e) { return false; }
}

/* ---------------- адрес и сертификат ---------------- */
function a_ip(): string {
  global $C;
  $st = a_state();
  if (!empty($st['ip']) && time() - (int)($st['ip_at'] ?? 0) < 3600) return (string)$st['ip'];
  $ip = '';
  foreach ([$C['meta_url'] . 'eipv4', $C['meta_url'] . 'public-ipv4', 'https://api.ipify.org'] as $u) {
    $ch = curl_init($u);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 4]);
    $r = trim((string)curl_exec($ch)); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 200 && filter_var($r, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { $ip = $r; break; }
  }
  if ($ip !== '') a_state_put(['ip' => $ip, 'ip_at' => time()]);
  return $ip;
}
function a_dns_ok(string $ip): bool { $l = @gethostbynamel(A_HOST); return $ip !== '' && is_array($l) && in_array($ip, $l, true); }
function a_https(): bool { return is_file('/etc/letsencrypt/live/' . A_HOST . '/fullchain.pem'); }

function a_cert(): void {
  if (a_https() || !is_executable('/usr/bin/certbot')) return;
  $st = a_state();
  if (time() - (int)($st['cert_at'] ?? 0) < 600) return;
  a_state_put(['cert_at' => time()]);
  if (!a_dns_ok(a_ip())) { a_log('сертификат: DNS ещё не указывает на этот сервер'); return; }
  a_log('получаю сертификат https');
  exec('/usr/bin/certbot --nginx -d ' . A_HOST . ' --non-interactive --agree-tos --register-unsafely-without-email --redirect 2>&1', $out, $rc);
  a_log('certbot: ' . ($rc === 0 ? 'готово' : 'ошибка ' . $rc . ' ' . implode(' ', array_slice($out, -3))));
}

/* ---------------- отчёт магазину ---------------- */
function a_report(): void {
  global $C;
  $ip = a_ip();
  $db = json_decode(a_as_db('dbinfo'), true) ?: [];
  $free = @disk_free_space(dirname((string)$C['db']));
  $up = is_file('/proc/uptime') ? (int)explode(' ', (string)file_get_contents('/proc/uptime'))[0] : 0;
  $rep = ['ver' => a_ver(), 'ip' => $ip, 'dns_ok' => a_dns_ok($ip), 'https' => a_https(), 'worker' => !empty($db['worker']), 'key' => !empty($db['key']),
          'disk_free_gb' => $free ? round($free / 1073741824, 1) : null, 'backup' => (string)(a_state()['backup_day'] ?? ''),
          'up' => $up ? floor($up / 86400) . ' дн ' . gmdate('H:i', $up % 86400) : ''];
  [$code, $body] = a_store('a=report', json_encode($rep, JSON_UNESCAPED_UNICODE), null, 20);
  if ($code !== 200) { a_log('магазин не принял отчёт: ' . $code); return; }
  $j = json_decode((string)$body, true); if (!is_array($j)) return;
  // 0.2.0 · ключ шлюза: им магазин подписывает вопросы к Claude («Космос» в Экосистеме, CRM и приложении)
  $gw = (string)($j['gw'] ?? '');
  if (preg_match('~^[a-f0-9]{64}$~', $gw)) a_gw_save($gw);
  // 0.2.0 · раз в минуту будим фоновые задачи «Космоса» в магазине (большие поручения доделываются без открытого экрана)
  $cron = (string)($j['cron'] ?? '');
  if (strpos($cron, rtrim((string)$C['store_url'], '/') . '/api/kosmos_flow.php?') === 0) a_ping($cron);
}
function a_gw_save(string $gw): void {
  global $C;
  $f = (string)($C['gw_key_file'] ?? '/etc/kosmos/gw.key');
  if (is_file($f) && hash_equals(trim((string)file_get_contents($f)), $gw)) return;
  $tmp = $f . '.new';
  if (@file_put_contents($tmp, $gw . "\n") === false) { a_log('ключ шлюза не записался'); return; }
  @chown($tmp, 'root'); @chgrp($tmp, (string)$C['db_user']); @chmod($tmp, 0640);
  @rename($tmp, $f);
  a_log('ключ шлюза магазина обновлён');
}
function a_ping(string $url): void {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_USERAGENT => 'kosmos-agent']);
  curl_exec($ch); curl_close($ch);
}
function a_ver(): string { global $C; return trim((string)@file_get_contents($C['app_dir'] . '/VERSION')) ?: '0'; }

/* ---------------- обновления ---------------- */
function a_update(bool $force): void {
  global $C;
  a_state_put(['upd_at' => time()]);
  [$code, $body] = a_store('a=pkgs', null, null, 20);
  $j = json_decode($body, true);
  if ($code !== 200 || empty($j['ok'])) { a_log('список версий не получен: ' . $code); return; }
  $cur = a_ver(); $new = null;
  foreach ((array)$j['pkgs'] as $p) if (version_compare((string)$p['ver'], $cur, '>') && (!$new || version_compare((string)$p['ver'], (string)$new['ver'], '>'))) $new = $p;
  if (!$new) { if ($force) a_log('новых версий нет (стоит ' . $cur . ')'); return; }
  $tmp = sys_get_temp_dir() . '/kosmos-upd-' . bin2hex(random_bytes(4));
  @mkdir($tmp, 0700, true);
  $tgz = $tmp . '/p.tgz';
  [$code] = a_store('a=pkg&f=' . rawurlencode((string)$new['file']), null, $tgz, 300);
  if ($code !== 200 || !is_file($tgz) || !hash_equals((string)$new['sha256'], hash_file('sha256', $tgz))) { a_log('версия ' . $new['ver'] . ': пакет не скачался или повреждён'); a_rm($tmp); return; }
  $pub = base64_decode(trim((string)@file_get_contents($C['sign_pub_file'])), true);
  $sig = base64_decode((string)$new['sig'], true);
  if (!$pub || strlen($pub) !== 32 || !$sig || !sodium_crypto_sign_verify_detached($sig, "kosmos\n" . $new['ver'] . "\n" . $new['sha256'], $pub)) {
    a_log('версия ' . $new['ver'] . ': подпись не сходится — не ставлю'); a_rm($tmp); return;
  }
  exec('tar -xzf ' . escapeshellarg($tgz) . ' -C ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
  if ($rc !== 0 || !is_file($tmp . '/kosmos/install/install.sh')) { a_log('версия ' . $new['ver'] . ': пакет не распаковался'); a_rm($tmp); return; }
  a_log('ставлю версию ' . $new['ver']);
  exec('bash ' . escapeshellarg($tmp . '/kosmos/install/install.sh') . ' ' . escapeshellarg((string)$new['ver']) . ' ' . escapeshellarg((string)$new['sig']) . ' ' . escapeshellarg((string)$new['sha256']) . ' 2>&1', $out, $rc);
  a_log('установка ' . $new['ver'] . ': ' . ($rc === 0 ? 'готово' : 'ошибка ' . $rc . ' ' . implode(' | ', array_slice($out, -4))));
  a_rm($tmp);
}
function a_rm(string $d): void { if ($d !== '' && strpos($d, sys_get_temp_dir() . '/kosmos-') === 0) exec('rm -rf ' . escapeshellarg($d)); }

/* ---------------- ночные копии ---------------- */
function a_backup(bool $force): bool {
  global $C;
  $day = date('Ymd'); $h = (int)date('G');
  if (!$force && ((a_state()['backup_day'] ?? '') === $day || $h < 3 || $h > 4)) return true;
  if (!is_file($C['db'])) { a_log('копия: базы ещё нет'); return false; }
  @mkdir($C['backup_dir'], 0700, true);
  $raw = dirname((string)$C['db']) . '/.backup.sqlite';
  a_as_db('dbbackup', $raw);
  if (!is_file($raw) || filesize($raw) < 1024) { a_log('копия: не получилась'); return false; }
  $gz = $C['backup_dir'] . '/' . $day . '.db.gz';
  $in = fopen($raw, 'rb'); $out = gzopen($gz, 'wb6');
  while (!feof($in)) gzwrite($out, (string)fread($in, 1 << 20));
  fclose($in); gzclose($out); @unlink($raw);
  $all = glob($C['backup_dir'] . '/*.db.gz') ?: []; sort($all);
  while (count($all) > 7) @unlink(array_shift($all));
  // в магазин частями по 4 МБ
  $sha = hash_file('sha256', $gz); $size = filesize($gz); $fh = fopen($gz, 'rb'); $off = 0; $ok = true;
  do {
    $chunk = (string)fread($fh, 4 << 20); $last = $off + strlen($chunk) >= $size;
    [$code, $body] = a_store('a=backup&day=' . $day . '&off=' . $off . ($last ? '&last=1&sha=' . $sha : ''), $chunk, null, 120);
    if ($code !== 200) { $ok = false; a_log('копия: магазин не принял (' . $code . ' ' . mb_substr($body, 0, 100) . ')'); break; }
    $off += strlen($chunk);
  } while (!$last);
  fclose($fh);
  if ($ok) { a_state_put(['backup_day' => $day]); a_log('копия базы ' . $day . ' отправлена в магазин (' . round($size / 1024) . ' КБ)'); }
  return $ok;
}

function a_restore(bool $force): bool {
  global $C;
  if (is_file($C['db']) && !$force) { a_log('восстановление: база уже есть — не трогаю (restore force — заменить)'); return false; }
  [$code, $body] = a_store('a=backups', null, null, 20);
  $j = json_decode($body, true);
  $list = (array)($j['backups'] ?? []);
  if ($code !== 200 || !$list) { a_log('восстановление: в магазине нет копий — начинаю с чистой базы'); return false; }
  usort($list, function ($a, $b) { return strcmp((string)$b['day'], (string)$a['day']); });
  $b = $list[0];
  $gz = sys_get_temp_dir() . '/kosmos-restore.db.gz';
  [$code] = a_store('a=backup_get&day=' . rawurlencode((string)$b['day']), null, $gz, 600);
  if ($code !== 200 || !hash_equals((string)$b['sha'], hash_file('sha256', $gz))) { a_log('восстановление: копия не скачалась'); @unlink($gz); return false; }
  if (is_file($C['db'])) rename($C['db'], $C['db'] . '.before-restore-' . date('YmdHis'));
  @unlink($C['db'] . '-wal'); @unlink($C['db'] . '-shm');
  $in = gzopen($gz, 'rb'); $out = fopen($C['db'], 'wb');
  while (!gzeof($in)) fwrite($out, (string)gzread($in, 1 << 20));
  gzclose($in); fclose($out); @unlink($gz);
  if ($C['db_user'] !== '' && function_exists('posix_geteuid') && posix_geteuid() === 0) { @chown($C['db'], (string)$C['db_user']); @chgrp($C['db'], (string)$C['db_user']); }
  @chmod($C['db'], 0640);
  a_log('восстановлена память «Космоса» из копии ' . $b['day'] . '. Ключи Claude, если сервер новый, нужно добавить заново (они зашифрованы ключом старого сервера)');
  return true;
}

/* ---------------- 0.3.0 · рабочая копия GitHub ----------------
   Главная копия кода ИТК — закрытое хранилище на GitHub (interseal/itc-system). Здесь — его рабочая копия, только чтение:
   раз в 10 минут агент забирает изменения. Вход в GitHub — ключом этого сервера (deploy key): агент создаёт его сам,
   руководитель один раз добавляет открытую часть в GitHub (itc-system → Settings → Deploy keys), её показывают
   настройки «Космоса» → GitHub. Состояние — в <папка базы>/git.json. Ключей и паролей в хранилище нет. */

function a_git_file(): string { global $C; return dirname((string)$C['db']) . '/git.json'; }
function a_git_save(array $g): void {
  $f = a_git_file(); @mkdir(dirname($f), 0755, true);
  @file_put_contents($f, json_encode($g, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX); @chmod($f, 0644);
}
function a_git_err(string $out): string {
  if (stripos($out, 'Permission denied') !== false || stripos($out, 'Repository not found') !== false || stripos($out, 'Could not read from remote') !== false)
    return 'GitHub пока не пускает сервер: добавьте ключ сервера в хранилище (itc-system → Settings → Deploy keys)';
  if (stripos($out, 'Host key verification failed') !== false) return 'не сходится ключ github.com — проверьте сеть сервера';
  if (stripos($out, "couldn't find remote ref") !== false || stripos($out, 'empty repository') !== false || stripos($out, 'unknown revision') !== false) return 'хранилище на GitHub пока пустое';
  if (stripos($out, 'уже занята') !== false) return trim($out);
  if (stripos($out, 'timed out') !== false || stripos($out, 'Could not resolve') !== false) return 'нет связи с GitHub';
  return 'git: ' . mb_substr(trim(preg_replace('~\s+~u', ' ', $out)), 0, 240);
}
function a_git(bool $force): bool {
  global $C;
  if (!$force && time() - (int)(a_state()['git_at'] ?? 0) < 600) return true;
  a_state_put(['git_at' => time()]);
  $g = ['repo' => (string)$C['git_repo'], 'dir' => (string)$C['git_dir'], 'checked_at' => date('Y-m-d H:i:s'), 'ok' => false];
  $old = is_file(a_git_file()) ? (array)json_decode((string)file_get_contents(a_git_file()), true) : [];
  foreach (['pulled_at', 'head'] as $k) if (isset($old[$k])) $g[$k] = $old[$k];
  $have = function (string $b): bool { return trim((string)shell_exec('command -v ' . escapeshellarg($b) . ' 2>/dev/null')) !== ''; };
  if (!$have('git') || !$have('ssh-keygen')) {
    a_log('ставлю git');
    exec('DEBIAN_FRONTEND=noninteractive apt-get install -y -q git openssh-client 2>&1', $o, $rc);
    if (!$have('git') || !$have('ssh-keygen')) { $g['err'] = 'не удалось поставить git'; a_git_save($g); a_log($g['err']); return false; }
  }
  $key = (string)$C['git_key'];
  if (!is_file($key)) {
    @mkdir(dirname($key), 0750, true);
    exec('ssh-keygen -q -t ed25519 -N "" -C ' . escapeshellarg('kosmos-sg ' . date('Y-m-d')) . ' -f ' . escapeshellarg($key) . ' 2>&1', $o, $rc);
    if (!is_file($key)) { $g['err'] = 'не удалось создать ключ сервера'; a_git_save($g); a_log($g['err']); return false; }
    a_log('создан ключ сервера для GitHub');
  }
  @chmod($key, 0600);
  $g['pub'] = trim((string)@file_get_contents($key . '.pub'));
  $kh = dirname($key) . '/github_known_hosts';
  if (!is_file($kh)) @file_put_contents($kh, A_GIT_HOSTKEY . "\n");
  $ssh = 'ssh -i ' . escapeshellarg($key) . ' -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=' . escapeshellarg($kh) . ' -o ConnectTimeout=20';
  $env = 'GIT_SSH_COMMAND=' . escapeshellarg($ssh) . ' GIT_TERMINAL_PROMPT=0 ';
  $dir = (string)$C['git_dir']; $q = escapeshellarg($dir); $o = [];
  if (!is_dir($dir . '/.git')) {   // первый раз: клон во временную папку, потом — на место
    @mkdir(dirname($dir), 0755, true);
    $tmp = $dir . '.new-' . bin2hex(random_bytes(3)); $qt = escapeshellarg($tmp);
    exec($env . 'timeout 900 git clone -q ' . escapeshellarg((string)$C['git_repo']) . ' ' . $qt . ' 2>&1', $o, $rc);
    if ($rc === 0 && trim((string)shell_exec('git -C ' . $qt . ' rev-parse --verify -q HEAD 2>/dev/null')) === ''
        && trim((string)shell_exec('git -C ' . $qt . ' rev-parse --verify -q origin/main 2>/dev/null')) !== '') exec('git -C ' . $qt . ' checkout -q -B main origin/main 2>&1', $o);   // у хранилища нет ветки по умолчанию — берём main
    if ($rc === 0 && trim((string)shell_exec('git -C ' . $qt . ' rev-parse --verify -q HEAD 2>/dev/null')) === '') { $rc = 1; $o[] = 'empty repository'; }
    if ($rc === 0 && (is_dir($dir) || !@rename($tmp, $dir))) { $rc = 1; $o[] = 'папка ' . $dir . ' уже занята чем-то другим'; }
    if (is_dir($tmp)) exec('rm -rf ' . $qt);
  } else {
    exec($env . 'timeout 900 git -C ' . $q . ' fetch -q origin 2>&1', $o, $rc);
    if ($rc === 0) {
      $br = trim((string)shell_exec('git -C ' . $q . ' symbolic-ref -q --short refs/remotes/origin/HEAD 2>/dev/null')) ?: 'origin/main';
      exec('git -C ' . $q . ' reset -q --hard ' . escapeshellarg($br) . ' 2>&1 && git -C ' . $q . ' clean -qfdx 2>&1', $o, $rc);   // копия только для чтения: всегда как на GitHub
    }
  }
  if ($rc !== 0) { $g['err'] = a_git_err(implode("\n", $o)); a_git_save($g); a_log('GitHub: ' . $g['err']); return false; }
  $h = explode("\t", trim((string)shell_exec('git -C ' . $q . ' log -1 --format=%h%x09%cI%x09%s 2>/dev/null')), 3);
  $new = ['hash' => $h[0] ?? '', 'date' => $h[1] ?? '', 'msg' => $h[2] ?? ''];
  if (($g['head']['hash'] ?? '') !== $new['hash']) a_log('рабочая копия GitHub: ' . $new['hash'] . ' ' . $new['msg']);
  $g['head'] = $new; $g['ok'] = true; $g['pulled_at'] = date('Y-m-d H:i:s'); unset($g['err']);
  $g['files'] = (int)trim((string)shell_exec('git -C ' . $q . ' ls-files | wc -l'));
  a_git_save($g);
  return true;
}
