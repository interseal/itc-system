<?php
declare(strict_types=1);
// ==== КОСМОС · lib/keys.php · ключи Claude от разных аккаунтов ====
// Ключей может быть несколько (у каждого своё название), работает один — «активный».
// Ключ хранится только здесь, зашифрованным (AES-256-GCM, ключ шифрования /etc/kosmos/master.key).
// Наружу отдаём только название и последние 4 знака. Сменили ключ — память «Космоса» остаётся та же.

function master_key(): string {
  static $k = null;
  if ($k !== null) return $k;
  $f = (string)cfg('master_key_file');
  $raw = is_file($f) ? (string)file_get_contents($f) : '';
  if (strlen($raw) < 32) fail('На сервере нет ключа шифрования (/etc/kosmos/master.key)', 500);
  return $k = hash('sha256', $raw, true);
}

function key_enc(string $plain): string {
  $iv = random_bytes(12); $tag = '';
  $ct = openssl_encrypt($plain, 'aes-256-gcm', master_key(), OPENSSL_RAW_DATA, $iv, $tag);
  return base64_encode($iv . $tag . $ct);
}

function key_dec(string $enc): string {
  $r = base64_decode($enc, true);
  if ($r === false || strlen($r) < 29) return '';
  $p = openssl_decrypt(substr($r, 28), 'aes-256-gcm', master_key(), OPENSSL_RAW_DATA, substr($r, 0, 12), substr($r, 12, 16));
  return $p === false ? '' : $p;
}

function key_public(array $r): array {
  return ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'last4' => (string)$r['last4'], 'active' => (bool)$r['active'],
          'status' => (string)$r['status'], 'checked_at' => (string)$r['checked_at'], 'created_at' => (string)$r['created_at'],
          'ws' => (string)($r['ws'] ?? ''), 'ws_name' => (string)($r['ws_name'] ?? '')];
}

function keys_list(): array {
  $rows = db()->query('SELECT * FROM keys WHERE deleted=0 ORDER BY active DESC, id')->fetchAll();
  $out = [];
  $u = db()->prepare("SELECT COALESCE(SUM(tokens_in),0) ti, COALESCE(SUM(tokens_out),0) tout, COALESCE(SUM(calls),0) c
                      FROM usage WHERE key_id=? AND day >= ?");
  foreach ($rows as $r) {
    $p = key_public($r);
    if (key_dec((string)$r['enc']) === '') { $p['status'] = 'не читается — сервер заменён, добавьте ключ заново'; $p['broken'] = true; }
    $u->execute([(int)$r['id'], date('Y-m-d', time() - 30 * 86400)]);
    $p['usage30'] = array_map('intval', $u->fetch() ?: []);
    $p['ws_issue'] = $p['status'] !== '' && $p['status'] !== 'ok' && mb_stripos($p['status'], 'рабоч') !== false;
    $out[] = $p;
  }
  return $out;
}

function key_add(string $name, string $secret, string $by): array {
  $secret = trim($secret); $name = str($name, 60);
  if (!preg_match('~^sk-ant-[A-Za-z0-9_\-]{20,300}$~', $secret)) fail('Это не похоже на ключ Claude (должен начинаться с sk-ant-)');
  if ($name === '') $name = 'Ключ ' . substr($secret, -4);
  $d = db();
  foreach ($d->query('SELECT enc FROM keys WHERE deleted=0')->fetchAll() as $r) {
    if (hash_equals(key_dec((string)$r['enc']), $secret)) fail('Такой ключ уже добавлен');
  }
  $hasActive = (int)$d->query('SELECT COUNT(*) FROM keys WHERE deleted=0 AND active=1')->fetchColumn() > 0;
  $d->prepare('INSERT INTO keys (name, enc, last4, active, created_by, created_at) VALUES (?,?,?,?,?,?)')
    ->execute([$name, key_enc($secret), substr($secret, -4), $hasActive ? 0 : 1, $by, now()]);
  $id = (int)$d->lastInsertId();
  log_add($by, 'key_add', ['id' => $id, 'name' => $name]);
  return key_public(key_row($id));
}

function key_row(int $id): array {
  $q = db()->prepare('SELECT * FROM keys WHERE id=? AND deleted=0'); $q->execute([$id]);
  $r = $q->fetch();
  if (!$r) fail('Ключ не найден', 404);
  return $r;
}

function key_activate(int $id, string $by): void {
  key_row($id);
  $d = db();
  $d->beginTransaction();
  $d->exec('UPDATE keys SET active=0');
  $d->prepare('UPDATE keys SET active=1 WHERE id=?')->execute([$id]);
  $d->commit();
  log_add($by, 'key_activate', ['id' => $id]);
}

function key_delete(int $id, string $by): void {
  key_row($id);
  db()->prepare('UPDATE keys SET deleted=1, active=0, enc=\'\' WHERE id=?')->execute([$id]);
  log_add($by, 'key_delete', ['id' => $id]);
}

function key_rename(int $id, string $name, string $by): void {
  key_row($id);
  $name = str($name, 60); if ($name === '') fail('Укажите название');
  db()->prepare('UPDATE keys SET name=? WHERE id=?')->execute([$name, $id]);
}

/* Активный ключ: [строка из базы, сам ключ]. */
function key_active(): array {
  $r = db()->query('SELECT * FROM keys WHERE deleted=0 AND active=1 LIMIT 1')->fetch();
  if (!$r) return [null, ''];
  return [$r, key_dec((string)$r['enc'])];
}

/* Почему нельзя работать: '' — всё в порядке. */
function key_problem(?array $row, string $key): string {
  if (!$row) return 'Нет активного ключа Claude — руководитель добавляет его в настройках «Космоса»';
  if ($key === '') return 'Активный ключ Claude не читается (сервер заменён) — руководитель добавляет ключ заново в настройках';
  return '';
}

/* Проверка ключа: маленький запрос к быстрой модели. */
function key_test(int $id): array {
  $r = key_row($id);
  $secret = key_dec((string)$r['enc']);
  $res = claude_request($secret, (int)$r['id'], [
    'model' => model('fast'), 'max_tokens' => 16,
    'messages' => [['role' => 'user', 'content' => 'Ответь одним словом: готово']],
  ]);
  $ok = empty($res['error']);
  $status = $ok ? 'ok' : (string)$res['error'];
  db()->prepare('UPDATE keys SET status=?, checked_at=? WHERE id=?')->execute([mb_substr($status, 0, 200), now(), $id]);
  $out = ['ok' => $ok, 'status' => $status];
  $after = key_row($id);   // 0.2.1 · рабочую область «Космос» мог выбрать сам во время проверки
  if ((string)$after['ws'] !== '' && (string)$after['ws'] !== (string)($r['ws'] ?? '')) $out['ws_auto'] = (string)$after['ws_name'] !== '' ? (string)$after['ws_name'] : (string)$after['ws'];
  return $out;
}

/* ---------- 0.2.1 · рабочая область (workspace) личного ключа ----------
   Личный ключ Claude, созданный без рабочей области, работает только с заголовком anthropic-workspace-id.
   «Космос» сам берёт список рабочих областей ключа (Admin API принимает такой ключ) и выбирает единственную
   или «по умолчанию» (Default). Если выбрать нельзя — руководитель выбирает у ключа, кнопка «Рабочая область». */
function ws_ok(string $id): bool { return (bool)preg_match('~^wrkspc_[A-Za-z0-9]{6,80}$~', $id); }

function key_ws(int $id): string {
  $q = db()->prepare('SELECT ws FROM keys WHERE id=?'); $q->execute([$id]);
  return (string)($q->fetchColumn() ?: '');
}

/* Рабочие области, которые видит ключ → ['ok' => bool, 'list' => [['id' => 'wrkspc_…', 'name' => '…'], …], 'error' => '…']. */
function ws_list(string $key): array {
  $url = preg_replace('~/v1/messages$~', '/v1/organizations/workspaces', (string)cfg('claude_url')) . '?include_default=true&limit=100';
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['anthropic-version: 2023-06-01', 'x-api-key: ' . $key],
                          CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25]);
  $out = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($out === false || $http === 0) return ['ok' => false, 'list' => [], 'error' => 'Нет связи с Claude — список рабочих областей не получен'];
  $j = json_decode((string)$out, true);
  if ($http !== 200 || !is_array($j)) {
    $msg = (string)($j['error']['message'] ?? '');
    return ['ok' => false, 'list' => [], 'error' => ($http === 401 || $http === 403)
      ? 'Claude не показывает этому ключу список рабочих областей — вставьте ID рабочей области вручную'
      : 'Список рабочих областей не получен (' . $http . ($msg !== '' ? ': ' . mb_substr($msg, 0, 120) : '') . ')'];
  }
  $list = [];
  foreach ((array)($j['data'] ?? []) as $w) {
    if (!is_array($w) || !empty($w['archived_at'])) continue;
    $id = (string)($w['id'] ?? '');
    if (ws_ok($id)) $list[] = ['id' => $id, 'name' => mb_substr((string)($w['name'] ?? ''), 0, 80)];
  }
  return ['ok' => true, 'list' => $list, 'error' => $list ? '' : 'Claude не показал ни одной рабочей области — вставьте ID вручную'];
}

/* Какую взять самому: единственную, а из нескольких — «по умолчанию» (Default). Иначе [] — выбирает руководитель. */
function ws_pick(array $list): array {
  if (count($list) === 1) return $list[0];
  $def = array_values(array_filter($list, function ($w) { return (bool)preg_match('~default|по умолчанию~iu', (string)$w['name']); }));
  return count($def) === 1 ? $def[0] : [];
}

/* Найти и запомнить рабочую область ключа. → её ID или '' (выбрать не получилось). */
function ws_auto(string $key, int $keyId): string {
  $r = ws_list($key);
  $w = $r['ok'] ? ws_pick($r['list']) : [];
  if (!$w) { log_add('', 'key_ws_auto_fail', ['id' => $keyId, 'found' => count($r['list']), 'error' => $r['error']]); return ''; }
  db()->prepare('UPDATE keys SET ws=?, ws_name=? WHERE id=?')->execute([$w['id'], $w['name'], $keyId]);
  log_add('', 'key_ws_auto', ['id' => $keyId, 'ws' => $w['id'], 'name' => $w['name']]);
  return $w['id'];
}

/* Для окна выбора: что видит ключ и что выбрано сейчас. */
function key_ws_options(int $id): array {
  $r = key_row($id);
  $secret = key_dec((string)$r['enc']);
  if ($secret === '') fail('Ключ не читается — добавьте его заново');
  $l = ws_list($secret);
  return ['list' => $l['list'], 'error' => $l['error'], 'ws' => (string)$r['ws'], 'ws_name' => (string)$r['ws_name']];
}

/* Руководитель выбрал рабочую область ('' — без неё). */
function key_ws_set(int $id, string $ws, string $name, string $by): void {
  key_row($id);
  $ws = trim($ws);
  if ($ws !== '' && !ws_ok($ws)) fail('ID рабочей области выглядит так: wrkspc_… — его видно в консоли Claude: Settings → Workspaces, столбец ID');
  db()->prepare('UPDATE keys SET ws=?, ws_name=? WHERE id=?')->execute([$ws, $ws === '' ? '' : str($name, 80), $id]);
  log_add($by, 'key_ws_set', ['id' => $id, 'ws' => $ws]);
}

/* ---------- №0.1.1 · запасные ключи ----------
   Если активный ключ перестал работать (отозван, на счёте нет денег, нет прав), «Космос» сам берёт
   следующий рабочий ключ, делает его активным и сообщает об этом. Выключается в настройках (auto_switch). */
function key_is_dead(array $res): bool {
  $h = (int)($res['http'] ?? 0); $e = (string)($res['error'] ?? '');
  if (!empty($res['ws_needed']) || !empty($res['ws_bad'])) return true;   // 0.2.1 · без рабочей области ключ работать не может
  return $h === 401 || $h === 403 || ($h === 400 && mb_stripos($e, 'деньги') !== false);
}

function key_next(array $exclude): ?array {
  foreach (db()->query('SELECT * FROM keys WHERE deleted=0 ORDER BY id')->fetchAll() as $r) {
    if (in_array((int)$r['id'], $exclude, true)) continue;
    $k = key_dec((string)$r['enc']);
    if ($k !== '') return [$r, $k];
  }
  return null;
}

/* Запрос к Claude активным ключом, при поломке ключа — следующим. $onSwitch(старое название, новое название). */
function claude_call(array $payload, ?callable $onText = null, ?callable $onSwitch = null): array {
  [$krow, $key] = key_active();
  if (($why = key_problem($krow, $key)) !== '') return ['error' => $why, 'http' => 0, 'key_id' => 0];
  $tried = [];
  while (true) {
    $res = claude_request($key, (int)$krow['id'], $payload, $onText);
    $res['key_id'] = (int)$krow['id'];
    // 0.3.3 · ключ ответил — старая ошибка у него больше не правда (например, счёт пополнили): снимаем её,
    // иначе страница обновлений магазина продолжит писать «деньги закончились»
    if (empty($res['error']) && !in_array((string)($krow['status'] ?? ''), ['', 'ok'], true))
      db()->prepare("UPDATE keys SET status='ok', checked_at=? WHERE id=?")->execute([now(), $krow['id']]);
    if (empty($res['error']) || !key_is_dead($res) || setting('auto_switch', '1') !== '1') return $res;
    $tried[] = (int)$krow['id'];
    db()->prepare('UPDATE keys SET status=?, checked_at=? WHERE id=?')->execute([mb_substr((string)$res['error'], 0, 200), now(), $krow['id']]);
    $next = key_next($tried);
    if (!$next) { $res['error'] .= '. Запасных рабочих ключей нет — добавьте ключ в настройках'; return $res; }
    $old = (string)$krow['name'];
    [$krow, $key] = $next;
    key_activate((int)$krow['id'], 'auto');
    log_add('', 'key_auto_switch', ['from' => $old, 'to' => (string)$krow['name']]);
    if ($onSwitch) $onSwitch($old, (string)$krow['name']);
  }
}
