<?php
declare(strict_types=1);
// ==== КОСМОС · lib/memory.php · долгая память ====
// scope 'company' — общее знание о компании (пишет руководитель или «Космос» по его просьбе),
// scope 'user'    — личное: что важно знать о конкретном человеке и как с ним работать.

function mem_for(array $me): array {
  $q = db()->prepare("SELECT id, scope, phone, text, source, created_at FROM memory
    WHERE deleted=0 AND (scope='company' OR (scope='user' AND phone=?)) ORDER BY scope, id");
  $q->execute([$me['phone']]);
  return $q->fetchAll();
}

/* Для руководителя в настройках: вся память, включая личную память сотрудников. */
function mem_all(): array {
  return db()->query("SELECT m.id, m.scope, m.phone, COALESCE(u.name, '') name, m.text, m.source, m.created_at
    FROM memory m LEFT JOIN users u ON u.phone = m.phone WHERE m.deleted=0 ORDER BY m.scope, m.phone, m.id")->fetchAll();
}

function mem_add(array $me, string $scope, string $text, string $source): array {
  $text = str($text, 600);
  if ($text === '') return ['ok' => false, 'error' => 'пустая запись'];
  if ($scope !== 'company') $scope = 'user';
  if ($scope === 'company' && !$me['boss']) $scope = 'user';   // общее знание о компании пишет только руководитель
  $d = db();
  $q = $d->prepare("SELECT id FROM memory WHERE deleted=0 AND scope=? AND phone=? AND text=?");
  $q->execute([$scope, $scope === 'user' ? $me['phone'] : '', $text]);
  if ($id = $q->fetchColumn()) return ['ok' => true, 'id' => (int)$id, 'scope' => $scope, 'dup' => true];
  $d->prepare('INSERT INTO memory (scope, phone, text, source, created_by, created_at) VALUES (?,?,?,?,?,?)')
    ->execute([$scope, $scope === 'user' ? $me['phone'] : '', $text, $source, $me['phone'], now()]);
  return ['ok' => true, 'id' => (int)$d->lastInsertId(), 'scope' => $scope];
}

function mem_del(array $me, int $id): bool {
  $q = db()->prepare('SELECT scope, phone FROM memory WHERE id=? AND deleted=0'); $q->execute([$id]);
  $r = $q->fetch();
  if (!$r) return false;
  $own = $r['scope'] === 'user' && $r['phone'] === $me['phone'];
  if (!$own && !$me['boss']) return false;
  db()->prepare('UPDATE memory SET deleted=1 WHERE id=?')->execute([$id]);
  return true;
}

/* Поиск по памяти и прошлым разговорам человека. */
function mem_search(array $me, string $query, int $limit = 12): array {
  $words = array_values(array_filter(preg_split('~[\s,.;:!?«»"()]+~u', mb_strtolower($query)), fn($w) => mb_strlen($w) >= 3));
  if (!$words) return [];
  $out = [];
  foreach (mem_for($me) as $m) {
    $t = mb_strtolower($m['text']); $hit = 0;
    foreach ($words as $w) if (mb_strpos($t, $w) !== false) $hit++;
    if ($hit) $out[] = ['hit' => $hit, 'kind' => 'память', 'id' => (int)$m['id'], 'text' => $m['text'], 'at' => $m['created_at']];
  }
  $like = '%' . str_replace(['%', '_'], '', $words[0]) . '%';
  $q = db()->prepare("SELECT m.id, m.role, m.text, m.created_at, t.title FROM messages m JOIN threads t ON t.id=m.thread_id
    WHERE t.phone=? AND t.deleted=0 AND lower(m.text) LIKE ? ORDER BY m.id DESC LIMIT 40");
  $q->execute([$me['phone'], $like]);
  foreach ($q->fetchAll() as $r) {
    $t = mb_strtolower($r['text']); $hit = 0;
    foreach ($words as $w) if (mb_strpos($t, $w) !== false) $hit++;
    $out[] = ['hit' => $hit, 'kind' => 'разговор «' . $r['title'] . '»', 'id' => (int)$r['id'],
              'text' => ($r['role'] === 'user' ? 'Человек: ' : 'Космос: ') . mb_substr($r['text'], 0, 500), 'at' => $r['created_at']];
  }
  usort($out, fn($a, $b) => $b['hit'] <=> $a['hit']);
  return array_slice($out, 0, $limit);
}
