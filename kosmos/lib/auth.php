<?php
declare(strict_types=1);
// ==== КОСМОС · lib/auth.php · вход тем же пропуском, что в Экосистеме ====
// Пропуск сотрудника проверяет сам магазин (api/kosmos.php?a=who, служебный ключ X-Kosmos-Token).
// 0.2.0 · исправлен адрес: раньше спрашивали несуществующий kosmos_who.php — и любой пропуск считался неверным.
// Ответ помним 10 минут. Если магазин не отвечает (сеть), пускаем по последнему ответу до 12 часов.
// Сам пропуск нигде не храним — только его отпечаток (sha256).
// Руководитель решает, кто из сотрудников пользуется «Космосом» (по умолчанию — все, кроме склада).

const AUTH_FRESH = 600;
const AUTH_GRACE = 43200;

function req_token(): string {
  $t = (string)($_SERVER['HTTP_X_ECO_TOKEN'] ?? '');
  if ($t === '') $t = (string)(body()['t'] ?? '');
  if ($t === '') $t = (string)($_GET['t'] ?? '');
  return mb_substr(trim($t), 0, 300);
}

/* Кто спрашивает. $strict=false — не проверять доступ (нужно, чтобы показать «попросите доступ»). */
function me(bool $strict = true): array {
  static $me = null;
  if ($me === null) {
    $t = req_token();
    if ($t === '') fail('Войдите через Экосистему', 401);
    $u = auth_check($t);
    if (!$u) fail('Пропуск не подошёл — откройте «Космос» из Экосистемы заново', 401);
    $d = db();
    $d->prepare("INSERT INTO users (phone, name, role, boss, first_at, last_at) VALUES (?,?,?,?,?,?)
      ON CONFLICT(phone) DO UPDATE SET name=excluded.name, role=excluded.role, boss=excluded.boss, last_at=excluded.last_at")
      ->execute([$u['phone'], $u['name'], $u['role'], $u['boss'] ? 1 : 0, now(), now()]);
    $q = $d->prepare('SELECT allow FROM users WHERE phone=?'); $q->execute([$u['phone']]);
    $u['allowed'] = $u['boss'] || access_rule((string)$u['role'], (string)$q->fetchColumn());
    $me = $u;
  }
  if ($strict && !$me['allowed']) fail('Доступ к «Космосу» выдаёт руководитель — он уже видит вашу просьбу', 403);
  return $me;
}

function me_boss(): array {
  $m = me();
  if (!$m['boss']) fail('Это может делать только руководитель', 403);
  return $m;
}

/* Правило доступа: личная отметка руководителя важнее общего правила. */
function access_rule(string $role, string $allow): bool {
  if ($allow === 'yes') return true;
  if ($allow === 'no') return false;
  if (setting('access_mode', 'all') !== 'all') return false;
  $deny = array_filter(array_map('trim', explode(',', setting('deny_roles', 'warehouse'))));
  return !in_array($role, $deny, true);
}

function auth_check(string $t): ?array {
  $dev = cfg('dev_users');
  if (is_array($dev) && isset($dev[$t])) {
    $x = $dev[$t] + ['', '', '', false];
    return ['phone' => (string)$x[0], 'name' => (string)$x[1], 'role' => (string)$x[2], 'boss' => (bool)$x[3]];
  }
  $h = hash('sha256', $t);
  $d = db();
  $q = $d->prepare('SELECT user, at FROM auth_cache WHERE h=?'); $q->execute([$h]);
  $row = $q->fetch();
  if ($row && time() - (int)$row['at'] < AUTH_FRESH) return json_decode((string)$row['user'], true) ?: null;

  [$code, $u] = auth_ask_store($t);
  if ($code === 200 && is_array($u) && !empty($u['ok']) && ($u['phone'] ?? '') !== '' && ($u['role'] ?? '') === 'itc'
      && array_key_exists('staff_role', $u)) {
    $role = (string)$u['staff_role'];
    $user = ['phone' => (string)$u['phone'], 'name' => (string)($u['name'] ?? ''), 'role' => $role,
             'boss' => array_key_exists('boss', $u) ? (bool)$u['boss'] : in_array($role, ['boss', ''], true)];
    $d->prepare('INSERT INTO auth_cache (h, user, at) VALUES (?,?,?) ON CONFLICT(h) DO UPDATE SET user=excluded.user, at=excluded.at')
      ->execute([$h, json_encode($user, JSON_UNESCAPED_UNICODE), time()]);
    return $user;
  }
  // магазин не ответил вовсе (сеть) — пускаем по прошлому ответу, но не дольше 12 часов
  if ($code === 0 && $row && time() - (int)$row['at'] < AUTH_GRACE) return json_decode((string)$row['user'], true) ?: null;
  if ($code === 0) fail('Не получается проверить вход: магазин не отвечает. Попробуйте через минуту', 502);
  $d->prepare('DELETE FROM auth_cache WHERE h=?')->execute([$h]);
  return null;
}

/* Вопрос магазину: чей это пропуск? Возвращает [код ответа, данные]. 0 — нет связи. */
function auth_ask_store(string $t): array {
  $kf = (string)cfg('store_token_file');
  $k = is_file($kf) ? trim((string)file_get_contents($kf)) : '';
  if ($k === '') return [0, null];
  $ch = curl_init(rtrim((string)cfg('store_url'), '/') . '/api/kosmos.php?a=who');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Kosmos-Token: ' . $k],
    CURLOPT_POSTFIELDS => json_encode(['t' => $t]),
  ]);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  if ($raw === false || $code === 0 || $code >= 500) return [0, null];
  return [$code, json_decode((string)$raw, true)];
}

/* Защита от случайных больших расходов: не больше N сообщений в минуту и M в день на человека. */
function rate_ok(array $me): string {
  $d = db(); $t = time(); $phone = $me['phone'];
  $d->prepare('DELETE FROM rate WHERE at < ?')->execute([$t - 60]);
  $q = $d->prepare('SELECT COUNT(*) FROM rate WHERE phone=?'); $q->execute([$phone]);
  if ((int)$q->fetchColumn() >= (int)cfg('msg_per_min')) return 'Слишком много сообщений за минуту — подождите немного';
  $lim = (int)setting('daily_limit', '150');
  if (!$me['boss'] && $lim > 0) {
    $q = $d->prepare("SELECT COUNT(*) FROM messages m JOIN threads t ON t.id=m.thread_id WHERE t.phone=? AND m.role='user' AND m.created_at >= ?");
    $q->execute([$phone, date('Y-m-d') . ' 00:00:00']);
    if ((int)$q->fetchColumn() >= $lim) return 'На сегодня лимит сообщений исчерпан (' . $lim . '). Лимит меняет руководитель';
  }
  $d->prepare('INSERT INTO rate (phone, at) VALUES (?,?)')->execute([$phone, $t]);
  return '';
}
