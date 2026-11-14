<?php
// ==== ИТК · КОСМОС · bin/agent.php · обслуживание сервера ====
// Служба kosmos-agent (раз в минуту, от root):
//   — сообщает магазину, что сервер жив (страница «Космоса» в Экосистеме показывает это руководителю);
//     в ответ магазин даёт ключ шлюза (0.2.0) и адрес, по которому агент будит фоновые задачи «Космоса»;
//   — раз в 10 минут проверяет новую версию «Космоса» в магазине, сверяет подпись и ставит её;
//   — пока нет https: когда DNS-запись kosmos.itcpartner.store указывает сюда, получает сертификат (certbot);
//   — ночью (03:00–04:59 по Бишкеку) делает копию базы и отправляет в магазин (там хранятся 7 последних);
//   — 0.3.0: раз в 10 минут обновляет рабочую копию хранилища кода ИТК из GitHub (/srv/itc-system, только чтение).
// Подкоманды: ip | update | backup | git | bw | restore [force] | dbinfo | bwinfo | dbbackup <файл>  (три последние — от www-data).
//   — 0.3.4: раз в 10 минут (и сразу после смены ключа сейфа) кладёт в сейф Bitwarden паспорт системы из GitHub и ключи сервера.

if (PHP_SAPI !== 'cli') exit;
const A_HOST = 'kosmos.itcpartner.store';
// 0.3.0 · опубликованный ключ github.com (сверяем при входе в GitHub; константы — до первого exit)
const A_GIT_HOSTKEY = 'github.com ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl';
// 0.3.4 · программа bws (сейф Bitwarden): версия и отпечатки архивов с GitHub (bitwarden/sdk-sm, сверены 14.11.2026)
const A_BWS_VER = '2.1.0';
const A_BWS_ZIP = ['x86_64' => 'f59ee150e42b82128d437087e9bac920053c6bfddcb960d20ce9386e5ac9bba6',
                   'aarch64' => 'eb0f1ae61d1c3b74244d2841233276e05c77e8be4da197ed90fc6248387005e1'];
const A_BW_PART = 24000;   // байт на запись: сейф берёт до 35 000 знаков в зашифрованном виде

$cfgFile = getenv('KOSMOS_CONFIG') ?: '/etc/kosmos/config.php';
$C = (is_file($cfgFile) ? (array)(require $cfgFile) : []) + [
  'store_url' => 'https://itcpartner.store', 'db' => '/opt/kosmos/data/kosmos.sqlite', 'tz' => 'Asia/Bishkek',
  'store_token_file' => '/etc/kosmos/store_token', 'sign_pub_file' => '/etc/kosmos/sign.pub', 'gw_key_file' => '/etc/kosmos/gw.key',
  'app_dir' => '/opt/kosmos', 'agent_state' => '/var/lib/kosmos-agent.json', 'backup_dir' => '/var/backups/kosmos',
  'db_user' => 'www-data', 'meta_url' => 'http://100.100.100.200/latest/meta-data/',
  // 0.3.0 · хранилище кода ИТК на GitHub и его рабочая копия на этом сервере
  'git_repo' => 'git@github.com:interseal/itc-system.git', 'git_dir' => '/srv/itc-system', 'git_key' => '/etc/kosmos/github_deploy',
  // 0.3.4 · сейф Bitwarden: программа bws (Secrets Manager CLI) и ключ шифрования базы (его копия — в сейф)
  'bws_bin' => '/usr/local/bin/bws', 'master_key_file' => '/etc/kosmos/master.key',
  // 0.3.7 · сервер сам собирает и подписывает обновления магазина из GitHub (releases/N.json)
  'upd_key_file' => '/etc/kosmos/upd_key.php', 'own_sign_key' => '/etc/kosmos/sign_own.pem', 'release_dir' => '/var/lib/kosmos-releases', 'python' => 'python3',
];
date_default_timezone_set((string)$C['tz']);
$cmd = (string)($argv[1] ?? '');

switch ($cmd) {
  case 'dbinfo':   echo json_encode(a_dbinfo()); exit;
  case 'claudeskills': require_once __DIR__ . '/../lib/boot.php'; echo json_encode(skill_sync_claude((string)($argv[2] ?? ''), (string)($argv[3] ?? 'claude') === 'hist' ? 'hist' : 'claude')); exit;   // 0.3.5 · от www-data
  case 'bwinfo':   echo json_encode(a_bwinfo()); exit;   // 0.3.4 · от www-data: токен сейфа и ключи Claude (расшифровка — ключом базы)
  case 'bw':       a_lock(); exit(a_bw(true) ? 0 : 1);
  case 'skills':   a_lock(); exit(a_claude_skills(true) ? 0 : 1);
  case 'release':  a_lock(); exit(a_release(true) ? 0 : 1);
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
    a_claude_skills(false);
    a_release(false);
    a_bw(false);
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
function a_as_db(string $sub, string ...$args): string {
  global $C, $cfgFile;
  $php = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $sub;
  foreach ($args as $arg) if ($arg !== '') $php .= ' ' . escapeshellarg($arg);
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
    return ['worker' => $beat > 0 && time() - $beat < 120, 'key' => $key > 0, 'money' => a_money($d)];
  } catch (Throwable $e) { return ['worker' => false, 'key' => false]; }
}

/* 0.3.3 · расход денег Claude по ключам: стоимость ответов пишет lib/price.php в usage.cost_u
   (миллионные доли доллара). Сегодня, 7 и 30 дней, всего. Удалённые ключи — только если по ним был расход
   (их траты нужны, чтобы правильно считать остаток счёта). Сами ключи отсюда не читаются — только название и 4 знака. */
function a_money(SQLite3 $d): ?array {
  try {
    $cols = function (string $t) use ($d) { $o = []; $r = $d->query('PRAGMA table_info(' . $t . ')'); while ($r && ($c = $r->fetchArray(SQLITE3_ASSOC))) $o[] = $c['name']; return $o; };
    if (!in_array('cost_u', $cols('usage'), true)) return null;
    $org = in_array('org', $cols('keys'), true) ? 'k.org' : "''";
    $q = $d->prepare("SELECT k.id, k.name, k.last4, k.active, k.status, k.deleted, $org AS org,
        (SELECT COALESCE(SUM(cost_u),0) FROM usage u WHERE u.key_id=k.id AND u.day=:d1) t,
        (SELECT COALESCE(SUM(cost_u),0) FROM usage u WHERE u.key_id=k.id AND u.day>=:d7) w,
        (SELECT COALESCE(SUM(cost_u),0) FROM usage u WHERE u.key_id=k.id AND u.day>=:d30) m,
        (SELECT COALESCE(SUM(cost_u),0) FROM usage u WHERE u.key_id=k.id) a,
        (SELECT MIN(day) FROM usage u WHERE u.key_id=k.id AND u.cost_u>0) f
      FROM keys k WHERE k.deleted=0 OR EXISTS (SELECT 1 FROM usage u WHERE u.key_id=k.id AND u.cost_u>0) ORDER BY k.id LIMIT 30");
    $q->bindValue(':d1', date('Y-m-d')); $q->bindValue(':d7', date('Y-m-d', time() - 6 * 86400)); $q->bindValue(':d30', date('Y-m-d', time() - 29 * 86400));
    $r = $q->execute(); $out = []; $today = strtotime(date('Y-m-d'));
    while ($r && ($k = $r->fetchArray(SQLITE3_ASSOC))) {
      $st = (string)$k['status'];
      // d7 — сколько из последних 7 дней ключ уже работал: среднее в день не занижается в первую неделю
      $f = (string)($k['f'] ?? ''); $d7 = $f !== '' && strtotime($f) ? max(1, min(7, (int)floor(($today - strtotime($f)) / 86400) + 1)) : 0;
      $out[] = ['id' => (int)$k['id'], 'name' => mb_substr((string)$k['name'], 0, 60), 'last4' => substr((string)$k['last4'], -4),
                'active' => (bool)$k['active'], 'del' => (bool)$k['deleted'], 'org' => (string)$k['org'],
                'out' => mb_stripos($st, 'деньги') !== false, 'st' => ($st !== '' && $st !== 'ok') ? mb_substr($st, 0, 200) : '',
                't' => (int)$k['t'], 'w' => (int)$k['w'], 'm' => (int)$k['m'], 'a' => (int)$k['a'], 'd7' => $d7];
    }
    return $out;
  } catch (Throwable $e) { return null; }
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
  // 0.3.3 · для страницы обновлений магазина: нагрузка, рабочая копия GitHub, расход денег Claude по ключам
  $la = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
  $rep['load'] = is_array($la) ? round((float)$la[0], 2) : null;
  $g = is_file(a_git_file()) ? (array)json_decode((string)file_get_contents(a_git_file()), true) : [];
  if ($g) $rep['git'] = ['ok' => !empty($g['ok']), 'hash' => substr((string)($g['head']['hash'] ?? ''), 0, 7),
                         'at' => (string)($g['pulled_at'] ?? ''), 'err' => mb_substr((string)($g['err'] ?? ''), 0, 160)];
  if (isset($db['money']) && is_array($db['money'])) $rep['money'] = $db['money'];
  // 0.3.4 · сейф Bitwarden: вставлен ли ключ, когда сохранено, ошибка
  $bw = is_file(a_bw_file()) ? (array)json_decode((string)file_get_contents(a_bw_file()), true) : [];
  if ($bw) $rep['bw'] = ['set' => !empty($bw['set']), 'ok' => !empty($bw['ok']), 'at' => (string)($bw['saved_at'] ?? $bw['checked_at'] ?? ''),
                         'err' => mb_substr((string)($bw['err'] ?? ''), 0, 200), 'n' => count((array)($bw['items'] ?? [])), 'project' => mb_substr((string)($bw['project'] ?? ''), 0, 60)];
  $rep['want_upd_key'] = !is_file((string)$C['upd_key_file']);   // 0.3.7 · ключ обновлений магазин отдаёт серверу один раз
  $rep['releases'] = a_release_state();
  [$code, $body] = a_store('a=report', json_encode($rep, JSON_UNESCAPED_UNICODE), null, 20);
  if ($code !== 200) { a_log('магазин не принял отчёт: ' . $code); return; }
  $j = json_decode((string)$body, true); if (!is_array($j)) return;
  // 0.2.0 · ключ шлюза: им магазин подписывает вопросы к Claude («Космос» в Экосистеме, CRM и приложении)
  $uk = (string)($j['upd_key'] ?? '');
  if (preg_match('~^[a-f0-9]{64}$~', $uk) && !is_file((string)$C['upd_key_file'])) {
    @mkdir(dirname((string)$C['upd_key_file']), 0750, true);
    file_put_contents((string)$C['upd_key_file'], "<?php define('UPD_KEY', '" . $uk . "');\n"); @chmod((string)$C['upd_key_file'], 0600);
    a_log('получен ключ обновлений магазина');
  }
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
  $sig = base64_decode((string)$new['sig'], true); $ok = false;   // 0.3.7 · ключей подписи может быть несколько (ноутбук, сам сервер)
  foreach (preg_split('~\s+~', trim((string)@file_get_contents($C['sign_pub_file']))) as $l) { $pub = base64_decode($l, true); if ($pub && strlen($pub) === 32 && $sig && sodium_crypto_sign_verify_detached($sig, "kosmos\n" . $new['ver'] . "\n" . $new['sha256'], $pub)) $ok = true; }
  if (!$ok) {
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

/* ---------------- 0.3.4 · сейф Bitwarden ----------------
   Руководитель вставляет в настройках «Космоса» (вкладка «Сейф») токен машинного аккаунта Bitwarden.
   Раз в 10 минут агент сам кладёт в сейф: паспорт системы и «Сейчас в работе» (из рабочей копии GitHub),
   ключи Claude (из базы «Космоса»), ключи сервера. Пишет только в проекты, открытые этому аккаунту;
   записи не удаляет; меняет только то, что изменилось. Программа bws ставится сама, файл сверяется по SHA-256. */

function a_bwinfo(): array {   // от www-data: расшифровать токен сейфа и ключи Claude может только код «Космоса»
  require_once __DIR__ . '/../lib/boot.php';
  $tok = ''; $enc = setting('bw_token', '');
  if ($enc !== '') $tok = key_dec($enc);
  $keys = [];
  foreach (db()->query('SELECT id, name, enc, last4 FROM keys WHERE deleted=0 ORDER BY id')->fetchAll() as $r) {
    $k = key_dec((string)$r['enc']); if ($k !== '') $keys[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'last4' => (string)$r['last4'], 'key' => $k];
  }
  return ['token' => $tok, 'claude' => $keys];
}
function a_bw_file(): string { global $C; return dirname((string)$C['db']) . '/bw.json'; }
function a_bw_save(array $s): void { $f = a_bw_file(); @file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); @chmod($f, 0644); }
function a_bw_name(string $s): string {
  $tr = ['а'=>'A','б'=>'B','в'=>'V','г'=>'G','д'=>'D','е'=>'E','ё'=>'E','ж'=>'ZH','з'=>'Z','и'=>'I','й'=>'Y','к'=>'K','л'=>'L','м'=>'M','н'=>'N','о'=>'O','п'=>'P','р'=>'R','с'=>'S','т'=>'T','у'=>'U','ф'=>'F','х'=>'H','ц'=>'TS','ч'=>'CH','ш'=>'SH','щ'=>'SCH','ъ'=>'','ы'=>'Y','ь'=>'','э'=>'E','ю'=>'YU','я'=>'YA'];
  $s = strtr(mb_strtolower($s), $tr);
  return trim((string)preg_replace('~[^A-Z0-9]+~', '_', strtoupper($s)), '_');
}
/* Текст по частям не больше A_BW_PART байт, режем по разделам «## ». */
function a_bw_split(string $t): array {
  $parts = []; $cur = '';
  foreach (preg_split('~(?=^## )~m', $t) as $sec) {
    while (strlen($sec) > A_BW_PART) { if ($cur !== '') { $parts[] = $cur; $cur = ''; } $cut = mb_strcut($sec, 0, A_BW_PART); $parts[] = $cut; $sec = substr($sec, strlen($cut)); }
    if (strlen($cur) + strlen($sec) > A_BW_PART && $cur !== '') { $parts[] = $cur; $cur = ''; }
    $cur .= $sec;
  }
  if ($cur !== '') $parts[] = $cur;
  return $parts;
}
function a_bws_install(): string {
  global $C;
  $bin = (string)$C['bws_bin'];
  if (is_file($bin) && is_executable($bin)) return '';
  $arch = php_uname('m') === 'aarch64' ? 'aarch64' : 'x86_64';
  $zip = sys_get_temp_dir() . '/kosmos-bws.zip';
  $url = 'https://github.com/bitwarden/sdk-sm/releases/download/bws-v' . A_BWS_VER . '/bws-' . $arch . '-unknown-linux-musl-' . A_BWS_VER . '.zip';
  a_log('ставлю bws ' . A_BWS_VER);
  $ch = curl_init($url); $fh = fopen($zip, 'wb');
  curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 180, CURLOPT_CONNECTTIMEOUT => 20]);
  curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fh);
  if ($code !== 200 || !hash_equals(A_BWS_ZIP[$arch], hash_file('sha256', $zip))) { @unlink($zip); return 'не удалось скачать программу bws (код ' . $code . ' или не сошёлся отпечаток файла)'; }
  $tmp = sys_get_temp_dir() . '/kosmos-bws'; @mkdir($tmp, 0700);
  $ok = false;
  if (class_exists('ZipArchive')) { $z = new ZipArchive(); if ($z->open($zip) === true) { $ok = $z->extractTo($tmp, 'bws'); $z->close(); } }
  if (!$ok) { exec('command -v unzip >/dev/null || DEBIAN_FRONTEND=noninteractive apt-get install -y -q unzip >/dev/null 2>&1; unzip -o -q ' . escapeshellarg($zip) . ' bws -d ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc); $ok = $rc === 0; }
  @unlink($zip);
  if (!$ok || !is_file("$tmp/bws")) return 'не удалось распаковать bws';
  @mkdir(dirname($bin), 0755, true);
  rename("$tmp/bws", $bin); @chmod($bin, 0755); @rmdir($tmp);
  return '';
}
/* Запуск bws без оболочки (токен — в окружении, не в строке команды). Возвращает [код, разобранный JSON, текст ошибки]. */
function a_bws(string $tok, array $args): array {
  global $C;
  $env = ['BWS_ACCESS_TOKEN' => $tok, 'HOME' => '/root', 'PATH' => '/usr/local/bin:/usr/bin:/bin', 'NO_COLOR' => '1'];
  foreach (['FAKE_BWS_DB'] as $k) if (getenv($k) !== false) $env[$k] = getenv($k);   // для проверок
  // параметры — сразу после команды, значения — через «--» и «--value=»: ключ вида «-----BEGIN…» иначе принимается за параметр
  $cmd = array_merge([(string)$C['bws_bin']], array_slice($args, 0, 2), ['-o', 'json', '-c', 'no'], array_slice($args, 2));
  $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
  if (!is_resource($p)) return [1, null, 'bws не запускается'];
  $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
  $rc = proc_close($p);
  $err = str_replace($tok, '…', trim($err));
  return [$rc, json_decode((string)$out, true), mb_substr($err !== '' ? $err : trim((string)$out), 0, 300)];
}
function a_bw(bool $force): bool {
  global $C;
  $info = json_decode(a_as_db('bwinfo'), true);
  $tok = is_array($info) ? (string)($info['token'] ?? '') : '';
  $old = is_file(a_bw_file()) ? (array)json_decode((string)file_get_contents(a_bw_file()), true) : [];
  if ($tok === '') { if (!empty($old['set']) || !$old) a_bw_save(['set' => false, 'checked_at' => date('Y-m-d H:i:s')]); return true; }
  $th = substr(hash('sha256', $tok), 0, 16);
  if (!$force && ($old['th'] ?? '') === $th && time() - (int)(a_state()['bw_at'] ?? 0) < 600) return true;
  a_state_put(['bw_at' => time()]);
  $st = ['set' => true, 'th' => $th, 'ok' => false, 'checked_at' => date('Y-m-d H:i:s')];
  foreach (['saved_at', 'project', 'items'] as $k) if (isset($old[$k])) $st[$k] = $old[$k];
  $fail = function (string $e) use (&$st) { $st['err'] = $e; a_bw_save($st); a_log('сейф: ' . $e); return false; };
  if (($e = a_bws_install()) !== '') return $fail($e);
  [$rc, $pr, $e] = a_bws($tok, ['project', 'list']);
  if ($rc !== 0 || !is_array($pr)) return $fail(stripos($e, 'access token') !== false || stripos($e, 'unauthorized') !== false || stripos($e, 'invalid') !== false ? 'сейф не принял ключ — вставьте новый токен' : 'нет связи с сейфом: ' . $e);
  if (!$pr) return $fail('у машинного аккаунта нет ни одного проекта — откройте ему проект «Авто» в Bitwarden');
  $proj = $pr[0];
  foreach ($pr as $x) if (mb_strtolower((string)($x['name'] ?? '')) === 'авто') $proj = $x;
  $pid = (string)$proj['id']; $st['project'] = (string)($proj['name'] ?? '');
  [$rc, $list, $e] = a_bws($tok, ['secret', 'list', $pid]);
  if ($rc !== 0 || !is_array($list)) return $fail('не удалось прочитать записи сейфа: ' . $e);
  $have = []; foreach ($list as $x) $have[(string)$x['key']] = $x;
  // что должно лежать в сейфе
  $want = [];
  $gd = rtrim((string)$C['git_dir'], '/');
  foreach (['ITC_pasport_sistemy.md' => ['SYSTEM_HANDOVER', 'Паспорт системы ИТК (docs/ITC_pasport_sistemy.md в GitHub), часть %d из %d. Кладёт сервер «Космоса» сам.'],
            'SEICHAS_V_RABOTE.md' => ['SEICHAS_V_RABOTE', '«Сейчас в работе» (docs/SEICHAS_V_RABOTE.md в GitHub), часть %d из %d. Кладёт сервер «Космоса» сам.']] as $f => [$name, $note]) {
    if (!is_file("$gd/docs/$f")) continue;
    $parts = a_bw_split((string)file_get_contents("$gd/docs/$f"));
    foreach ($parts as $i => $t) $want[$i ? $name . '_' . ($i + 1) : $name] = [$t, sprintf($note, $i + 1, count($parts))];
  }
  $used = [];
  foreach ((array)($info['claude'] ?? []) as $k) {
    $n = 'CLAUDE_KEY_' . (a_bw_name((string)$k['name']) ?: 'K' . $k['id']);
    if (isset($used[$n])) $n .= '_' . $k['id'];
    $used[$n] = 1;
    $want[$n] = [(string)$k['key'], 'Ключ Claude «' . $k['name'] . '» …' . $k['last4'] . ' из настроек «Космоса». Кладёт сервер «Космоса» сам.'];
  }
  $files = ['KOSMOS_MASTER_KEY' => [(string)$C['master_key_file'], true, 'Ключ шифрования базы «Космоса» (/etc/kosmos/master.key, base64). Без него ключи Claude из копии базы не читаются.'],
            'KOSMOS_GITHUB_DEPLOY_KEY' => [(string)$C['git_key'], false, 'Ключ сервера «Космоса» для GitHub (/etc/kosmos/github_deploy), только чтение хранилища itc-system.'],
            'KOSMOS_GW_KEY' => [(string)$C['gw_key_file'], false, 'Ключ шлюза магазин → «Космос» (/etc/kosmos/gw.key). Магазин выдаёт его сам.'],
            'KOSMOS_STORE_TOKEN' => [(string)$C['store_token_file'], false, 'Служебный ключ сервера «Космоса» для магазина (/etc/kosmos/store_token).'],
            // 0.3.7 · ключи, которыми сервер сам подписывает обновления
            'UPD_KEY' => [(string)$C['upd_key_file'], false, 'Ключ подписи обновлений магазина (upd_key.php). Сервер «Космоса» получил его от магазина и подписывает им обновления.'],
            'KOSMOS_SIGN_KEY_SERVER' => [(string)$C['own_sign_key'], false, 'Свой ключ сервера «Космоса» для подписи его пакетов (Ed25519, /etc/kosmos/sign_own.pem). Открытая часть — в /etc/kosmos/sign.pub рядом с ключом ноутбука.']];
  foreach ($files as $n => [$f, $bin, $note]) {
    if (!is_file($f)) continue;
    $v = (string)file_get_contents($f); $v = $bin ? base64_encode($v) : trim($v);
    if ($v !== '') $want[$n] = [$v, $note];
  }
  $done = [];
  foreach ($want as $n => [$v, $note]) {
    if (!isset($have[$n])) [$rc, , $e] = a_bws($tok, ['secret', 'create', '--note=' . $note, '--', $n, $v, $pid]);
    elseif ((string)$have[$n]['value'] !== $v) [$rc, , $e] = a_bws($tok, ['secret', 'edit', '--value=' . $v, '--note=' . $note, '--', (string)$have[$n]['id']]);
    else continue;
    if ($rc !== 0) return $fail('не удалось записать ' . $n . ': ' . $e);
    $done[] = $n;
  }
  $st['ok'] = true; unset($st['err']);
  $st['items'] = array_keys($want);
  if ($done) { $st['saved_at'] = date('Y-m-d H:i:s'); $st['last'] = $done; a_log('сейф: записано ' . implode(', ', $done)); }
  a_bw_save($st);
  return true;
}

/* 0.3.5 · навыки Claude из рабочей копии GitHub (docs/skills/*.md) → навыки «Космоса» и «Фила». Только когда в GitHub что-то поменялось. */
function a_claude_skills(bool $force): bool {
  global $C;
  $g = is_file(a_git_file()) ? (array)json_decode((string)file_get_contents(a_git_file()), true) : [];
  $head = (string)($g['head']['hash'] ?? '');
  if (!$force && ($head === '' || (a_state()['skills_head'] ?? '') === $head)) return true;
  // 0.3.8 · и история работы Claude (docs/history) — «Космос» и «Фил» видят, что и когда делалось
  foreach (['skills' => ['claude', 'навыки Claude'], 'history' => ['hist', 'история Claude']] as $sub => [$pre, $what]) {
    $dir = rtrim((string)$C['git_dir'], '/') . '/docs/' . $sub;
    if (!is_dir($dir)) continue;
    $out = a_as_db('claudeskills', $dir, $pre);
    $r = json_decode($out, true);
    if (!is_array($r)) { a_log($what . ': не удалось перенести'); return false; }
    if ($r['new'] || $r['upd'] || $r['del']) a_log($what . ': новых ' . $r['new'] . ', обновлено ' . $r['upd'] . ', убрано ' . $r['del']);
    if ($r['bad']) a_log($what . ': не разобраны (нет «# название» / «---» или текст длиннее 1500 знаков): ' . implode(', ', $r['bad']));
  }
  a_state_put(['skills_head' => $head]);
  return true;
}

/* ---------------- 0.3.7 · обновления магазина собирает и подписывает сам сервер ----------------
   Claude кладёт в GitHub код и releases/<N>.json: {"n": N, "title": "…", "what": ["…"], "files": ["store/…"], "kosmos": true|false}.
   Агент (раз в 10 минут, после рабочей копии GitHub) собирает obnovlenie_N.zip тем же kosmos/tools/build_store_update.py,
   подписывает ключом обновлений (магазин отдал его серверу) и своим ключом «Космоса» (сервер создал его сам,
   открытая часть — в /etc/kosmos/sign.pub рядом с ключом ноутбука) и отдаёт в магазин. Руководитель ставит одной кнопкой
   на странице «Обновления». Ноутбук для сборки больше не нужен. */
function a_release_state(): array {
  global $C; $f = rtrim((string)$C['release_dir'], '/') . '/state.json';
  $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : [];
}
function a_release_save(array $s): void { global $C; @mkdir((string)$C['release_dir'], 0700, true); file_put_contents(rtrim((string)$C['release_dir'], '/') . '/state.json', json_encode($s, JSON_UNESCAPED_UNICODE)); }
function a_own_key(): string {   // свой ключ подписи пакетов «Космоса»: создаётся один раз, открытая часть — в список доверенных
  global $C; $k = (string)$C['own_sign_key'];
  if (!is_file($k)) {
    exec('openssl genpkey -algorithm ed25519 -out ' . escapeshellarg($k) . ' 2>&1', $o, $rc);
    if ($rc !== 0 || !is_file($k)) return '';
    @chmod($k, 0600); a_log('создан свой ключ подписи пакетов «Космоса»');
  }
  $der = (string)shell_exec('openssl pkey -in ' . escapeshellarg($k) . ' -pubout -outform DER 2>/dev/null');
  $pub = base64_encode(substr($der, -32));
  $pf = (string)$C['sign_pub_file']; $have = trim((string)@file_get_contents($pf));
  if (strlen($der) >= 32 && strpos($have, $pub) === false) { file_put_contents($pf, ($have !== '' ? $have . "\n" : '') . $pub . "\n"); @chmod($pf, 0644); a_log('свой ключ добавлен в доверенные'); }
  return $k;
}
function a_release(bool $force): bool {
  global $C;
  $dir = rtrim((string)$C['git_dir'], '/');
  $g = is_file(a_git_file()) ? (array)json_decode((string)file_get_contents(a_git_file()), true) : [];
  $head = (string)($g['head']['hash'] ?? '');
  $st = a_release_state();
  if (!is_dir("$dir/releases") || (!$force && ($head === '' || ($st['head'] ?? '') === $head))) return true;
  $st['head'] = $head; $st['done'] = (array)($st['done'] ?? []);
  $todo = [];
  foreach (glob("$dir/releases/*.json") ?: [] as $f) {
    $r = json_decode((string)file_get_contents($f), true);
    if (!is_array($r) || (int)($r['n'] ?? 0) < 1 || isset($st['done'][(string)$r['n']])) continue;
    $todo[(int)$r['n']] = $r;
  }
  ksort($todo);
  foreach ($todo as $n => $r) {
    $err = a_release_one($n, $r, $dir);
    $st['done'][(string)$n] = ['at' => date('Y-m-d H:i:s'), 'ok' => $err === '', 'err' => $err, 'title' => mb_substr((string)($r['title'] ?? ''), 0, 120)];
    if ($err !== '') { a_log("обновление №$n: $err"); unset($st['done'][(string)$n]['ok']); $st['done'][(string)$n]['ok'] = false; $st['last_err'] = "№$n: $err"; break; }
    a_log("обновление №$n собрано, подписано и отдано в магазин");
    unset($st['last_err']);
  }
  if (count($st['done']) > 40) $st['done'] = array_slice($st['done'], -40, null, true);
  a_release_save($st);
  return empty($st['last_err']);
}
function a_release_one(int $n, array $r, string $dir): string {
  global $C;
  if (!is_file((string)$C['upd_key_file'])) return 'ещё нет ключа обновлений (магазин передаст его в ближайшую минуту)';
  if (trim((string)shell_exec('command -v ' . escapeshellarg((string)$C['python']) . ' 2>/dev/null')) === '') {
    exec('DEBIAN_FRONTEND=noninteractive apt-get install -y -q python3 2>&1', $o, $rc);
    if (trim((string)shell_exec('command -v python3 2>/dev/null')) === '') return 'не удалось поставить python3';
  }
  $key = a_own_key(); if ($key === '') return 'не удалось создать ключ подписи';
  $tmp = sys_get_temp_dir() . '/kosmos-rel-' . bin2hex(random_bytes(4)); @mkdir("$tmp/extra", 0700, true);
  foreach ((array)($r['files'] ?? []) as $p) {
    $p = ltrim(str_replace('\\', '/', (string)$p), '/');
    if ($p === '' || strpos($p, '..') !== false || !preg_match('~^(store|site|metro)/~', $p) || !is_file("$dir/$p")) { a_rm($tmp); return 'нет файла ' . $p; }
    @mkdir(dirname("$tmp/extra/$p"), 0700, true); copy("$dir/$p", "$tmp/extra/$p");
  }
  $cmd = [(string)$C['python'], "$dir/kosmos/tools/build_store_update.py", '--n', (string)$n, '--upd-key', (string)$C['upd_key_file'], '--sign-key', $key,
          '--out', "$tmp/obnovlenie_$n.zip", '--title', mb_substr((string)($r['title'] ?? ('Обновление №' . $n)), 0, 200)];
  if ((array)($r['files'] ?? [])) array_push($cmd, '--extra', "$tmp/extra");
  foreach ((array)($r['what'] ?? []) as $w) array_push($cmd, '--what', mb_substr((string)$w, 0, 600));
  if (empty($r['kosmos'])) $cmd[] = '--no-pkg';
  $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
  $out = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $rc = proc_close($p);
  if ($rc !== 0 || !is_file("$tmp/obnovlenie_$n.zip")) { a_rm($tmp); return 'сборка не удалась: ' . mb_substr(trim($e . ' ' . $out), -300); }
  $zip = (string)file_get_contents("$tmp/obnovlenie_$n.zip");
  @copy("$tmp/obnovlenie_$n.zip", rtrim((string)$C['release_dir'], '/') . "/obnovlenie_$n.zip");
  a_rm($tmp);
  [$code, $body] = a_store('a=upd_put&n=' . $n, $zip, null, 120);
  $j = json_decode($body, true);
  if ($code !== 200 || empty($j['ok'])) return 'магазин не принял файл: ' . (is_array($j) ? (string)($j['error'] ?? '') : '') . ' (' . $code . ')';
  return '';
}
