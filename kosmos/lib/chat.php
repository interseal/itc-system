<?php
declare(strict_types=1);
// ==== КОСМОС · lib/chat.php · разговоры и быстрый ответ ====

function thread_get(array $me, string $id): array {
  $q = db()->prepare('SELECT * FROM threads WHERE id=? AND deleted=0'); $q->execute([$id]);
  $t = $q->fetch();
  if (!$t || $t['phone'] !== $me['phone']) fail('Разговор не найден', 404);
  return $t;
}

function thread_new(array $me, string $title): array {
  $id = uid(8);
  db()->prepare('INSERT INTO threads (id, phone, title, created_at, updated_at) VALUES (?,?,?,?,?)')
    ->execute([$id, $me['phone'], str($title, 80) ?: 'Новый разговор', now(), now()]);
  return thread_get($me, $id);
}

function thread_list(array $me): array {
  $q = db()->prepare('SELECT id, title, updated_at FROM threads WHERE phone=? AND deleted=0 ORDER BY updated_at DESC LIMIT 200');
  $q->execute([$me['phone']]);
  return $q->fetchAll();
}

function messages_get(string $threadId, int $after = 0): array {
  $q = db()->prepare('SELECT id, role, text, notes, task_id, model, created_at FROM messages WHERE thread_id=? AND id>? ORDER BY id LIMIT 500');
  $q->execute([$threadId, $after]);
  $out = [];
  foreach ($q->fetchAll() as $r) { $r['notes'] = json_decode((string)$r['notes'], true) ?: []; $r['id'] = (int)$r['id']; $out[] = $r; }
  return $out;
}

function msg_add(string $threadId, string $role, string $text, array $notes = [], string $taskId = '', string $model = '', int $keyId = 0): int {
  $d = db();
  $d->prepare('INSERT INTO messages (thread_id, role, text, notes, task_id, model, key_id, created_at) VALUES (?,?,?,?,?,?,?,?)')
    ->execute([$threadId, $role, $text, json_encode(array_values($notes), JSON_UNESCAPED_UNICODE), $taskId, $model, $keyId, now()]);
  $d->prepare('UPDATE threads SET updated_at=? WHERE id=?')->execute([now(), $threadId]);
  return (int)$d->lastInsertId();
}

/* Сообщение человека → быстрый ответ потоком. $deep — сразу отдать задачу глубокой модели. */
function chat_send(array $me, string $threadId, string $text, bool $deep): void {
  $text = str($text, 20000);
  if ($text === '') fail('Пустое сообщение');
  if (($why = rate_ok($me)) !== '') fail($why, 429);
  $thread = $threadId !== '' ? thread_get($me, $threadId) : thread_new($me, mb_substr(preg_replace('~\s+~u', ' ', $text), 0, 60));
  $tid = $thread['id'];
  $userMsgId = msg_add($tid, 'user', $text);

  sse_start();
  sse(['t' => 'start', 'thread_id' => $tid, 'title' => $thread['title'], 'user_msg_id' => $userMsgId]);

  if ($deep) {
    $task = task_create($tid, $me, $text);
    $say = 'Беру в работу. Результат пришлю сюда же, когда будет готов.';
    sse(['t' => 'delta', 'text' => $say]);
    sse(['t' => 'task', 'task' => task_public($task)]);
    $mid = msg_add($tid, 'assistant', $say, [], $task['id'], 'очередь');
    sse(['t' => 'done', 'message_id' => $mid, 'thread_id' => $tid]);
    return;
  }

  $payload = [
    'model' => model('fast'), 'max_tokens' => 1500,
    'system' => sys_blocks($me, $thread, 'fast'),
    'messages' => history($tid, 40000, (int)$thread['summary_upto']),
    'tools' => tool_defs('fast'),
  ];
  $all = ''; $notes = []; $taskId = ''; $model = ''; $keyId = 0;
  for ($round = 0; $round < 5; $round++) {
    $res = claude_call($payload, function (string $piece) use (&$all) { $all .= $piece; sse(['t' => 'delta', 'text' => $piece]); },
      function (string $from, string $to) use (&$notes) { $n = 'Ключ «' . $from . '» перестал работать — переключился на «' . $to . '»'; $notes[] = $n; sse(['t' => 'note', 'text' => $n]); });
    $keyId = (int)($res['key_id'] ?? 0);
    if (!empty($res['error'])) { sse(['t' => 'error', 'error' => $res['error']]); break; }
    $model = (string)($res['model'] ?? $payload['model']);
    if (($res['stop_reason'] ?? '') !== 'tool_use') break;
    $results = [];
    foreach ($res['content'] as $b) {
      if (($b['type'] ?? '') !== 'tool_use') continue;
      [$answer, $note] = tool_exec((string)$b['name'], $b['input'] ?? [], ['me' => $me, 'thread_id' => $tid]);
      if ($note !== null && strncmp($note, 'task:', 5) === 0) {
        $taskId = substr($note, 5);
        sse(['t' => 'task', 'task' => task_public(task_get($taskId))]);
      } elseif ($note !== null) {
        $notes[] = $note; sse(['t' => 'note', 'text' => $note]);
      }
      $results[] = ['type' => 'tool_result', 'tool_use_id' => (string)$b['id'], 'content' => $answer];
    }
    $payload['messages'][] = ['role' => 'assistant', 'content' => $res['content']];
    $payload['messages'][] = ['role' => 'user', 'content' => $results];
    if ($all !== '' && substr($all, -1) !== "\n") { $all .= "\n\n"; sse(['t' => 'delta', 'text' => "\n\n"]); }
  }
  $all = trim($all);
  if ($all === '' && !$notes && $taskId === '') { sse(['t' => 'done', 'thread_id' => $tid]); return; }
  $mid = msg_add($tid, 'assistant', $all, $notes, $taskId, $model, $keyId);
  sse(['t' => 'done', 'message_id' => $mid, 'thread_id' => $tid]);
}
