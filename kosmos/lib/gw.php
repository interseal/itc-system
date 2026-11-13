<?php
declare(strict_types=1);
// ==== КОСМОС · lib/gw.php · шлюз для магазина (0.2.0) ====
// «Космос» в Экосистеме и CRM выглядит и работает как «Фил»: те же сделки, клиенты, задачи, голос.
// Всю работу с данными CRM делает магазин (api/kosmos_flow.php), а думать он просит Claude через этот шлюз:
//   POST /api.php?a=gw   заголовок X-Kosmos-GW: <ключ шлюза>   тело {a: ask|status, ...}
// Ключ шлюза создаёт магазин и передаёт агенту в ответ на ежеминутный отчёт; агент кладёт его в /etc/kosmos/gw.key.
// Ключи Claude отсюда никуда не уходят: магазин получает только текст ответа.
// Доступ сотрудников, лимиты и расход — те же, что в настройках «Космоса» (кто спросил, магазин сообщает в поле who).

function gw_handle(): void {
  $want = '';
  $f = (string)cfg('gw_key_file');
  if ($f !== '' && is_file($f)) $want = trim((string)@file_get_contents($f));
  $got = (string)($_SERVER['HTTP_X_KOSMOS_GW'] ?? '');
  if ($want === '' || strlen($got) < 32 || !hash_equals($want, $got)) fail('Магазин и сервер «Космоса» ещё не связаны — подождите минуту', 403);
  $in = body();
  $a = (string)($in['a'] ?? '');
  if ($a === 'status') jout(gw_status());
  if ($a === 'ask') gw_ask($in);
  fail('Неизвестное действие шлюза', 404);
}

function gw_status(): array {
  [$k, $kv] = key_active();
  $n = (int)db()->query('SELECT COUNT(*) FROM keys WHERE deleted=0')->fetchColumn();
  $q = db()->prepare('SELECT COALESCE(SUM(calls),0) c, COALESCE(SUM(tokens_in),0) i, COALESCE(SUM(tokens_out),0) o, COALESCE(SUM(cache_read),0) r FROM usage WHERE day=?');
  $q->execute([date('Y-m-d')]); $u = $q->fetch() ?: [];
  return ['ok' => true, 'ver' => KOSMOS_VERSION, 'keys' => $n, 'active' => $k ? (string)$k['name'] : '',
          'ready' => key_problem($k, $kv) === '', 'problem' => key_problem($k, $kv), 'auto_switch' => setting('auto_switch', '1') === '1',
          'models' => MODEL_LIST, 'today' => ['calls' => (int)($u['c'] ?? 0), 'in' => (int)($u['i'] ?? 0), 'out' => (int)($u['o'] ?? 0), 'cache' => (int)($u['r'] ?? 0)]];
}

/* Кто спрашивает (магазин уже проверил пропуск) — заносим в список сотрудников «Космоса» и проверяем доступ и лимиты. */
function gw_who(array $w): array {
  $phone = str($w['phone'] ?? '', 40);
  if ($phone === '') fail('Не указан сотрудник', 400);
  $u = ['phone' => $phone, 'name' => str($w['name'] ?? '', 120), 'role' => str($w['role'] ?? '', 40), 'boss' => !empty($w['boss'])];
  $d = db();
  $d->prepare("INSERT INTO users (phone, name, role, boss, first_at, last_at) VALUES (?,?,?,?,?,?)
    ON CONFLICT(phone) DO UPDATE SET name=excluded.name, role=excluded.role, boss=excluded.boss, last_at=excluded.last_at")
    ->execute([$u['phone'], $u['name'], $u['role'], $u['boss'] ? 1 : 0, now(), now()]);
  $q = $d->prepare('SELECT allow FROM users WHERE phone=?'); $q->execute([$phone]);
  if (!$u['boss'] && !access_rule($u['role'], (string)$q->fetchColumn())) fail('Доступ к «Космосу» выдаёт руководитель — он уже видит вашу просьбу в настройках «Космоса»', 403);
  // защита от случайных больших расходов: одна команда — это несколько обращений (Космос смотрит данные шагами)
  $t = time();
  $d->prepare('DELETE FROM rate WHERE at < ?')->execute([$t - 60]);
  $q = $d->prepare('SELECT COUNT(*) FROM rate WHERE phone=?'); $q->execute(['gw:' . $phone]);
  if ((int)$q->fetchColumn() >= max(20, (int)cfg('msg_per_min') * 3)) fail('Слишком много запросов за минуту — подождите немного', 429);
  $lim = (int)setting('daily_limit', '150');
  if (!$u['boss'] && $lim > 0) {
    $q = $d->prepare('SELECT COALESCE(SUM(calls),0) FROM gw_use WHERE day=? AND phone=?'); $q->execute([date('Y-m-d'), $phone]);
    if ((int)$q->fetchColumn() >= $lim * 10) fail('На сегодня лимит «Космоса» исчерпан. Лимит меняет руководитель в настройках «Космоса»', 429);
  }
  $d->prepare('INSERT INTO rate (phone, at) VALUES (?,?)')->execute(['gw:' . $phone, $t]);
  return $u;
}

function gw_ask(array $in): void {
  $u = gw_who((array)($in['who'] ?? []));
  $sys = (string)($in['system'] ?? '');
  $user = (string)($in['user'] ?? '');
  if (trim($user) === '') fail('Пустой вопрос');
  if (strlen($sys) + strlen($user) > 1500000) fail('Слишком большой запрос');
  $model = str($in['model'] ?? '', 60);
  if (!preg_match('~^claude-[a-z0-9.\-]{3,50}$~', $model)) $model = model('deep');
  $max = max(256, min(32000, (int)($in['max_tokens'] ?? 4000)));
  $payload = ['model' => $model, 'max_tokens' => $max, 'messages' => [['role' => 'user', 'content' => $user]]];
  // большая постоянная часть (правила, инструменты, памятка) — в кеш Claude: следующие шаги той же команды дешевле и быстрее
  if (trim($sys) !== '') $payload['system'] = [['type' => 'text', 'text' => $sys, 'cache_control' => ['type' => 'ephemeral']]];
  $switched = '';
  $res = claude_call($payload, null, function ($old, $new) use (&$switched) { $switched = 'Ключ «' . $old . '» перестал работать — переключился на «' . $new . '»'; });
  $kid = (int)($res['key_id'] ?? 0);
  if (!empty($res['error'])) {
    log_add($u['phone'], 'gw_error', ['model' => $model, 'error' => mb_substr((string)$res['error'], 0, 300)]);
    jout(['ok' => false, 'error' => (string)$res['error'], 'switched' => $switched], 502);
  }
  $us = (array)($res['usage'] ?? []);
  db()->prepare('INSERT INTO gw_use (day, phone, calls, tokens_in, tokens_out) VALUES (?,?,1,?,?)
    ON CONFLICT(day, phone) DO UPDATE SET calls=calls+1, tokens_in=tokens_in+excluded.tokens_in, tokens_out=tokens_out+excluded.tokens_out')
    ->execute([date('Y-m-d'), $u['phone'], (int)($us['input_tokens'] ?? 0) + (int)($us['cache_read_input_tokens'] ?? 0) + (int)($us['cache_creation_input_tokens'] ?? 0), (int)($us['output_tokens'] ?? 0)]);
  $kn = '';
  if ($kid) { $q = db()->prepare('SELECT name FROM keys WHERE id=?'); $q->execute([$kid]); $kn = (string)$q->fetchColumn(); }
  jout(['ok' => true, 'text' => blocks_text((array)($res['content'] ?? [])), 'model' => (string)($res['model'] ?? $model),
        'stop' => (string)($res['stop_reason'] ?? ''), 'key' => $kn, 'switched' => $switched]);
}
