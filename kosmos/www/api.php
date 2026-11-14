<?php
declare(strict_types=1);
// ==== ИТК · КОСМОС · api.php · все запросы экрана «Космоса» ====
// /api.php?a=<действие>, тело — JSON. Пропуск Экосистемы — заголовок X-Eco-Token (или поле t).
// Сотрудники: разговоры, сообщения, задачи, своя память.
// Руководитель дополнительно: ключи Claude, модели, доступ сотрудников, расходы.

require_once dirname(__DIR__) . '/lib/boot.php';

$a = (string)($_GET['a'] ?? '');
$in = body();

try {
  if ($a === 'gw') gw_handle();      // 0.2.0 · магазин спрашивает Claude от имени «Космоса» (Экосистема, CRM, приложение)
  switch ($a) {
    case 'health': {
      $beat = (int)setting('worker_beat', '0');
      [$k] = key_active();
      jout(['ok' => true, 'version' => KOSMOS_VERSION, 'worker' => $beat > 0 && time() - $beat < 120, 'key' => (bool)$k]);
    }

    case 'me': {
      $m = me(false);
      [$k, $kv] = key_active();
      jout(['ok' => true, 'version' => KOSMOS_VERSION,
            'me' => ['name' => $m['name'], 'role' => $m['role'], 'boss' => $m['boss'], 'allowed' => $m['allowed']],
            'ready' => key_problem($k, $kv) === '', 'key_name' => $k ? (string)$k['name'] : '',
            'models' => ['fast' => model('fast'), 'deep' => model('deep')]]);
    }

    case 'threads': {
      jout(['ok' => true, 'threads' => thread_list(me())]);
    }

    case 'thread_new': {
      $t = thread_new(me(), str($in['title'] ?? '', 80));
      jout(['ok' => true, 'thread' => ['id' => $t['id'], 'title' => $t['title'], 'updated_at' => $t['updated_at']]]);
    }

    case 'thread_rename': {
      $m = me(); $t = thread_get($m, str($in['id'] ?? '', 40));
      $title = str($in['title'] ?? '', 80);
      if ($title === '') fail('Укажите название');
      db()->prepare('UPDATE threads SET title=? WHERE id=?')->execute([$title, $t['id']]);
      jout(['ok' => true]);
    }

    case 'thread_delete': {
      $m = me(); $t = thread_get($m, str($in['id'] ?? '', 40));
      db()->prepare('UPDATE threads SET deleted=1 WHERE id=?')->execute([$t['id']]);
      jout(['ok' => true]);
    }

    case 'messages': {
      $m = me(); $t = thread_get($m, str($in['thread_id'] ?? '', 40));
      jout(['ok' => true, 'thread' => ['id' => $t['id'], 'title' => $t['title']],
            'messages' => messages_get($t['id'], (int)($in['after'] ?? 0)), 'tasks' => tasks_of_thread($t['id'])]);
    }

    case 'send': {
      chat_send(me(), str($in['thread_id'] ?? '', 40), (string)($in['text'] ?? ''), !empty($in['deep']));
      exit;
    }

    case 'task': {
      $m = me(); $t = task_get(str($in['id'] ?? '', 40));
      if ($t['phone'] !== $m['phone'] && !$m['boss']) fail('Задача не найдена', 404);
      jout(['ok' => true, 'task' => task_public($t)]);
    }

    case 'task_cancel': {
      $m = me(); $t = task_get(str($in['id'] ?? '', 40));
      if ($t['phone'] !== $m['phone'] && !$m['boss']) fail('Задача не найдена', 404);
      if ($t['status'] !== 'queued') fail('Задача уже в работе — отменить можно только ожидающую');
      db()->prepare("UPDATE tasks SET status='cancelled', finished_at=? WHERE id=?")->execute([now(), $t['id']]);
      jout(['ok' => true]);
    }

    case 'memory': {
      $m = me();
      jout(['ok' => true, 'items' => !empty($in['all']) && $m['boss'] ? mem_all() : mem_for($m)]);
    }

    case 'memory_add': {
      $m = me();
      $r = mem_add($m, (string)($in['scope'] ?? 'user'), (string)($in['text'] ?? ''), 'вручную');
      if (empty($r['ok'])) fail((string)($r['error'] ?? 'Не записано'));
      jout($r);
    }

    case 'memory_del': {
      if (!mem_del(me(), (int)($in['id'] ?? 0))) fail('Запись не найдена или её может удалить только руководитель');
      jout(['ok' => true]);
    }

    /* ---------------- только руководитель ---------------- */

    case 'keys': {
      me_boss();
      jout(['ok' => true, 'keys' => keys_list(), 'auto_switch' => setting('auto_switch', '1') === '1']);
    }

    case 'key_add': {
      $m = me_boss();
      $k = key_add((string)($in['name'] ?? ''), (string)($in['key'] ?? ''), $m['phone']);
      $test = key_test($k['id']);
      jout(['ok' => true, 'key' => key_public(key_row($k['id'])), 'test' => $test]);
    }

    case 'key_activate': {
      $m = me_boss();
      key_activate((int)($in['id'] ?? 0), $m['phone']);
      jout(['ok' => true, 'keys' => keys_list()]);
    }

    case 'key_test': {
      me_boss();
      $r = key_test((int)($in['id'] ?? 0));
      jout(['ok' => true, 'test' => $r, 'keys' => keys_list()]);
    }

    case 'key_ws_list': {   // 0.2.1 · рабочие области личного ключа
      me_boss();
      jout(['ok' => true] + key_ws_options((int)($in['id'] ?? 0)));
    }

    case 'key_ws_set': {
      $m = me_boss();
      $id = (int)($in['id'] ?? 0);
      key_ws_set($id, (string)($in['ws'] ?? ''), (string)($in['name'] ?? ''), $m['phone']);
      $test = key_test($id);
      jout(['ok' => true, 'test' => $test, 'keys' => keys_list()]);
    }

    case 'bw': {    // 0.3.4 · сейф Bitwarden: вставлен ли токен и что сделал агент (сам токен не отдаётся)
      me_boss();
      $f = dirname((string)cfg('db')) . '/bw.json';
      $st = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
      jout(['ok' => true, 'set' => setting('bw_token', '') !== '', 'last4' => setting('bw_last4', ''), 'set_at' => setting('bw_set_at', ''), 'state' => is_array($st) ? $st : null]);
    }

    case 'bw_set': {
      $m = me_boss();
      $t = trim((string)($in['token'] ?? ''));
      if (!preg_match('~^0\.[0-9a-f\-]{36}\.[A-Za-z0-9]{10,}:[A-Za-z0-9+/=]{20,}$~', $t)) fail('Это не похоже на токен машинного аккаунта Bitwarden (начинается с «0.», внутри двоеточие)');
      setting_put('bw_token', key_enc($t)); setting_put('bw_last4', substr($t, -4)); setting_put('bw_set_at', now());
      log_add($m['phone'], 'bw_set', ['last4' => substr($t, -4)]);
      jout(['ok' => true]);
    }

    case 'bw_del': {
      $m = me_boss();
      setting_put('bw_token', ''); setting_put('bw_last4', '');
      log_add($m['phone'], 'bw_del', []);
      jout(['ok' => true]);
    }

    case 'git': {   // 0.3.0 · рабочая копия хранилища кода на GitHub (её ведёт агент)
      me_boss();
      $f = dirname((string)cfg('db')) . '/git.json';
      $g = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
      jout(['ok' => true, 'git' => is_array($g) ? $g : null]);
    }

    case 'key_rename': {
      $m = me_boss();
      key_rename((int)($in['id'] ?? 0), (string)($in['name'] ?? ''), $m['phone']);
      jout(['ok' => true, 'keys' => keys_list()]);
    }

    case 'key_delete': {
      $m = me_boss();
      key_delete((int)($in['id'] ?? 0), $m['phone']);
      jout(['ok' => true, 'keys' => keys_list()]);
    }

    case 'settings': {
      me_boss();
      jout(['ok' => true, 'settings' => settings_public(), 'model_list' => MODEL_LIST]);
    }

    case 'settings_set': {
      $m = me_boss();
      foreach (['model_fast', 'model_deep'] as $k) {
        if (!array_key_exists($k, $in)) continue;
        $v = str($in[$k], 60);
        if ($v !== '' && !preg_match('~^claude-[a-z0-9.\-]{3,50}$~', $v)) fail('Непонятное название модели: ' . $v);
        setting_put($k, $v);
      }
      if (array_key_exists('access_mode', $in)) setting_put('access_mode', $in['access_mode'] === 'list' ? 'list' : 'all');
      if (array_key_exists('deny_roles', $in)) setting_put('deny_roles', preg_replace('~[^a-z_,]~', '', (string)$in['deny_roles']));
      if (array_key_exists('daily_limit', $in)) setting_put('daily_limit', (string)max(0, min(5000, (int)$in['daily_limit'])));
      if (array_key_exists('auto_switch', $in)) setting_put('auto_switch', !empty($in['auto_switch']) ? '1' : '0');
      log_add($m['phone'], 'settings_set', array_intersect_key($in, array_flip(['model_fast', 'model_deep', 'access_mode', 'deny_roles', 'daily_limit', 'auto_switch'])));
      jout(['ok' => true, 'settings' => settings_public()]);
    }

    case 'users': {
      me_boss();
      $rows = db()->query("SELECT u.phone, u.name, u.role, u.boss, u.allow, u.first_at, u.last_at,
          (SELECT COUNT(*) FROM messages m JOIN threads t ON t.id=m.thread_id WHERE t.phone=u.phone AND m.role='user' AND m.created_at >= '" . date('Y-m-d', time() - 30 * 86400) . "') msgs30,
          (SELECT COALESCE(SUM(g.calls),0) FROM gw_use g WHERE g.phone=u.phone AND g.day >= '" . date('Y-m-d', time() - 30 * 86400) . "') gw30
        FROM users u ORDER BY u.last_at DESC LIMIT 300")->fetchAll();
      foreach ($rows as &$r) { $r['boss'] = (bool)$r['boss']; $r['msgs30'] = (int)$r['msgs30'] + (int)$r['gw30']; $r['allowed'] = $r['boss'] || access_rule((string)$r['role'], (string)$r['allow']); }
      jout(['ok' => true, 'users' => $rows]);
    }

    case 'user_allow': {
      $m = me_boss();
      $phone = str($in['phone'] ?? '', 40);
      $allow = in_array($in['allow'] ?? '', ['yes', 'no'], true) ? (string)$in['allow'] : '';
      db()->prepare('UPDATE users SET allow=? WHERE phone=?')->execute([$allow, $phone]);
      log_add($m['phone'], 'user_allow', ['phone' => $phone, 'allow' => $allow]);
      jout(['ok' => true]);
    }

    case 'usage': {
      me_boss();
      $from = date('Y-m-d', time() - 30 * 86400);
      $q = db()->prepare("SELECT u.day, u.key_id, COALESCE(k.name, '') key_name, u.model, u.calls, u.tokens_in, u.tokens_out, u.cache_read, u.cache_write
        FROM usage u LEFT JOIN keys k ON k.id=u.key_id WHERE u.day >= ? ORDER BY u.day DESC, u.key_id");
      $q->execute([$from]);
      jout(['ok' => true, 'rows' => $q->fetchAll()]);
    }
  }
  fail('Неизвестное действие', 404);
} catch (Throwable $e) {
  log_add('', 'error', ['a' => $a, 'e' => $e->getMessage(), 'at' => basename($e->getFile()) . ':' . $e->getLine()]);
  if (headers_sent()) { sse(['t' => 'error', 'error' => 'Сбой на сервере «Космоса»']); exit; }
  fail('Сбой на сервере «Космоса»', 500);
}

function settings_public(): array {
  return ['model_fast' => model('fast'), 'model_deep' => model('deep'), 'access_mode' => setting('access_mode', 'all'),
          'deny_roles' => setting('deny_roles', 'warehouse'), 'daily_limit' => (int)setting('daily_limit', '150'),
          'auto_switch' => setting('auto_switch', '1') === '1'];
}
