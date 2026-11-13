<?php
declare(strict_types=1);
// ==== КОСМОС · lib/tasks.php · фоновые задачи для глубокой модели ====
// Быстрая модель отвечает сразу и ставит долгую работу в очередь. Служба kosmos-worker
// берёт задачи по одной, глубокая модель работает и кладёт результат в тот же разговор.

const TASK_MAX_ROUNDS = 12;

function task_create(string $threadId, array $me, string $goal): array {
  $id = uid(6);
  db()->prepare('INSERT INTO tasks (id, thread_id, phone, goal, status, model, created_at) VALUES (?,?,?,?,?,?,?)')
    ->execute([$id, $threadId, $me['phone'], str($goal, 4000), 'queued', model('deep'), now()]);
  log_add($me['phone'], 'task_create', ['id' => $id]);
  return task_get($id);
}

function task_get(string $id): array {
  $q = db()->prepare('SELECT * FROM tasks WHERE id=?'); $q->execute([$id]);
  $t = $q->fetch();
  if (!$t) fail('Задача не найдена', 404);
  return $t;
}

function task_public(array $t): array {
  return ['id' => $t['id'], 'thread_id' => $t['thread_id'], 'goal' => mb_substr((string)$t['goal'], 0, 400), 'status' => $t['status'],
          'steps' => json_decode((string)$t['steps'], true) ?: [], 'error' => (string)$t['error'],
          'created_at' => $t['created_at'], 'finished_at' => $t['finished_at']];
}

function task_step(string $id, string $note): void {
  if ($note === '') return;
  $t = task_get($id);
  $s = json_decode((string)$t['steps'], true) ?: [];
  $s[] = ['at' => date('H:i'), 'note' => $note];
  db()->prepare('UPDATE tasks SET steps=? WHERE id=?')->execute([json_encode(array_slice($s, -40), JSON_UNESCAPED_UNICODE), $id]);
}

function tasks_of_thread(string $threadId): array {
  $q = db()->prepare('SELECT * FROM tasks WHERE thread_id=? ORDER BY created_at DESC LIMIT 30'); $q->execute([$threadId]);
  return array_map('task_public', $q->fetchAll());
}

/* Взять следующую задачу из очереди (атомарно). */
function task_claim(): ?array {
  $d = db();
  $id = $d->query("SELECT id FROM tasks WHERE status='queued' ORDER BY created_at LIMIT 1")->fetchColumn();
  if (!$id) return null;
  $u = $d->prepare("UPDATE tasks SET status='running', started_at=? WHERE id=? AND status='queued'");
  $u->execute([now(), $id]);
  return $u->rowCount() ? task_get((string)$id) : null;
}

/* Выполнить задачу глубокой моделью. Результат — новое сообщение «Космоса» в разговоре. */
function task_run(array $task): void {
  $id = (string)$task['id'];
  $fin = function (string $status, string $result = '', string $err = '') use ($id) {
    db()->prepare('UPDATE tasks SET status=?, result=?, error=?, finished_at=? WHERE id=?')->execute([$status, $result, $err, now(), $id]);
  };
  try {
    $u = db()->prepare('SELECT * FROM users WHERE phone=?'); $u->execute([$task['phone']]);
    $urow = $u->fetch() ?: ['phone' => $task['phone'], 'name' => '', 'role' => '', 'boss' => 0];
    $me = ['phone' => (string)$urow['phone'], 'name' => (string)$urow['name'], 'role' => (string)$urow['role'], 'boss' => (bool)$urow['boss']];
    $q = db()->prepare('SELECT * FROM threads WHERE id=?'); $q->execute([$task['thread_id']]);
    $thread = $q->fetch();
    if (!$thread) { $fin('error', '', 'разговор удалён'); return; }
    [$krow, $key] = key_active();
    if (($why = key_problem($krow, $key)) !== '') { $fin('error', '', $why); msg_add($thread['id'], 'assistant', 'Не смог выполнить задачу. ' . $why . '.', [], $id); return; }
    $keyId = (int)$krow['id']; $notes = [];
    $onSwitch = function (string $from, string $to) use ($id, &$notes) { $n = 'Ключ «' . $from . '» перестал работать — переключился на «' . $to . '»'; $notes[] = $n; task_step($id, $n); };
    task_step($id, 'Начинаю работу');
    $msgs = history((string)$thread['id'], 60000, (int)$thread['summary_upto']);
    $msgs[] = ['role' => 'user', 'content' => "ЗАДАЧА ДЛЯ ГЛУБОКОЙ РАБОТЫ:\n" . $task['goal']];
    $msgs = history_merge($msgs);
    $payload = ['model' => (string)$task['model'] ?: model('deep'), 'max_tokens' => 8000,
                'system' => sys_blocks($me, $thread, 'deep'), 'messages' => $msgs, 'tools' => tool_defs('deep')];
    $text = ''; $model = ''; $t0 = time();
    for ($round = 0; $round < TASK_MAX_ROUNDS; $round++) {
      if (time() - $t0 > 1500) { $text .= "\n\n(Работа остановлена по времени — 25 минут. Если нужно продолжить, напишите «продолжай».)"; break; }
      $res = claude_call($payload, null, $onSwitch);
      if (!empty($res['key_id'])) $keyId = (int)$res['key_id'];
      if (!empty($res['error'])) {
        $fin('error', '', (string)$res['error']);
        msg_add($thread['id'], 'assistant', 'Не смог закончить задачу: ' . $res['error'], [], $id, '', $keyId);
        return;
      }
      $model = (string)($res['model'] ?? '');
      $t = blocks_text((array)$res['content']);
      if (($res['stop_reason'] ?? '') !== 'tool_use') { $text .= ($text !== '' && $t !== '' ? "\n\n" : '') . $t; break; }
      if ($t !== '') task_step($id, mb_substr(preg_replace('~\s+~u', ' ', $t), 0, 200));   // промежуточные мысли — в ход работы, не в итог
      $results = [];
      foreach ($res['content'] as $b) {
        if (($b['type'] ?? '') !== 'tool_use') continue;
        [$answer, $note] = tool_exec((string)$b['name'], $b['input'] ?? [], ['me' => $me, 'thread_id' => $thread['id'], 'task_id' => $id]);
        if ($note !== null) $notes[] = $note;
        $results[] = ['type' => 'tool_result', 'tool_use_id' => (string)$b['id'], 'content' => $answer];
      }
      $payload['messages'][] = ['role' => 'assistant', 'content' => $res['content']];
      $payload['messages'][] = ['role' => 'user', 'content' => $results];
    }
    $text = trim($text) !== '' ? trim($text) : 'Задача выполнена.';
    msg_add($thread['id'], 'assistant', $text, $notes, $id, $model, $keyId);
    $fin('done', mb_substr($text, 0, 2000));
  } catch (Throwable $e) {
    $fin('error', '', mb_substr($e->getMessage(), 0, 300));
  }
}

/* Склеить подряд идущие сообщения одной роли (Claude ждёт чередования). */
function history_merge(array $msgs): array {
  $out = [];
  foreach ($msgs as $m) {
    $n = count($out);
    if ($n && $out[$n - 1]['role'] === $m['role'] && is_string($out[$n - 1]['content']) && is_string($m['content'])) $out[$n - 1]['content'] .= "\n\n" . $m['content'];
    else $out[] = $m;
  }
  return $out;
}

/* Сводка длинного разговора: старые сообщения сжимаются быстрой моделью, чтобы запросы не росли бесконечно. */
function threads_summarize(int $limit = 3): int {
  $d = db(); $done = 0;
  $rows = $d->query("SELECT t.*, (SELECT COUNT(*) FROM messages m WHERE m.thread_id=t.id AND m.id>t.summary_upto) n
                     FROM threads t WHERE t.deleted=0 ORDER BY t.updated_at DESC LIMIT 50")->fetchAll();
  [$krow, $key] = key_active();
  if (key_problem($krow, $key) !== '') return 0;
  foreach ($rows as $t) {
    $n = (int)$t['n'];
    if ($n < 40 || $done >= $limit) continue;
    $q = $d->prepare('SELECT id, role, text FROM messages WHERE thread_id=? AND id>? ORDER BY id LIMIT ' . min(60, $n - 16));
    $q->execute([$t['id'], (int)$t['summary_upto']]);
    $part = $q->fetchAll();
    if (!$part) continue;
    $lastId = (int)end($part)['id'];
    $txt = '';
    foreach ($part as $m) $txt .= ($m['role'] === 'user' ? 'Человек: ' : 'Космос: ') . mb_substr((string)$m['text'], 0, 3000) . "\n";
    $res = claude_request($key, (int)$krow['id'], ['model' => model('fast'), 'max_tokens' => 1200,
      'system' => 'Сожми разговор в сводку по-русски: решения, договорённости, цифры, открытые вопросы. Только факты, без воды.',
      'messages' => [['role' => 'user', 'content' => "Прошлая сводка:\n" . ($t['summary'] ?: '—') . "\n\nНовые сообщения:\n" . $txt]]]);
    if (!empty($res['error'])) break;
    $sum = blocks_text((array)$res['content']);
    if ($sum === '') continue;
    $d->prepare('UPDATE threads SET summary=?, summary_upto=? WHERE id=?')->execute([mb_substr($sum, 0, 6000), $lastId, $t['id']]);
    $done++;
  }
  return $done;
}

/* Задачи, которые «зависли» (служба перезапускалась посреди работы), — снова в очередь, но не больше двух раз. */
function tasks_recover(): int {
  $d = db(); $n = 0;
  $rows = $d->query("SELECT id, steps FROM tasks WHERE status='running' AND started_at < '" . date('Y-m-d H:i:s', time() - 1800) . "'")->fetchAll();
  foreach ($rows as $r) {
    $tries = substr_count((string)$r['steps'], 'Перезапуск');
    if ($tries >= 2) {
      $d->prepare("UPDATE tasks SET status='error', error='прервалась несколько раз', finished_at=? WHERE id=?")->execute([now(), $r['id']]);
    } else {
      task_step((string)$r['id'], 'Перезапуск после сбоя');
      $d->prepare("UPDATE tasks SET status='queued' WHERE id=?")->execute([$r['id']]);
    }
    $n++;
  }
  return $n;
}
