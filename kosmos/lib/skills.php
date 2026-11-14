<?php
declare(strict_types=1);
// ==== КОСМОС · lib/skills.php · навыки (0.3.1) ====
// Навык — проверенный способ решить задачу: какими инструментами и в каком порядке, что проверить, что ответить.
// Навыки общие для «Космоса» и «Фила»: записывает их «Космос», когда решил задачу в несколько шагов, пользуются оба.
// Главное место — здесь, в базе сервера «Космоса» (kosmos.sqlite; ночная копия — вместе со всей памятью).
// 0.3.2 · копия есть и в магазине (решение руководителя): если этот сервер заменят, магазин вернёт навыки сюда (op import).
// Магазин обращается к ним через шлюз: POST /api.php?a=gw  {a: "skills", op: pick|list|save|del|used|import, ...}
// Кто вправе их менять (руководитель), проверяет магазин: сюда приходят только запросы с ключом шлюза.

const SKILL_MAX = 150;     // больше не храним: лишние — самые давние и неприменявшиеся
const SKILL_STOP = ['для', 'что', 'это', 'как', 'все', 'всё', 'мне', 'нам', 'его', 'она', 'они', 'при', 'про', 'или', 'так', 'там', 'тут', 'уже', 'ещё', 'еще', 'надо', 'нужно', 'можно',
  'пожалуйста', 'сделай', 'сделать', 'давай', 'найди', 'покажи', 'посмотри', 'скажи', 'напиши', 'ты', 'вы', 'мы', 'по', 'на', 'из', 'от', 'до', 'не', 'да', 'нет', 'за', 'со', 'же', 'ли', 'бы'];

/* слова без окончаний: «гостиницы» и «гостиница», «клиентов» и «клиенты» совпадут */
function skill_stems(string $t): array {
  $o = [];
  foreach (preg_split('~[^\p{L}\p{N}]+~u', str_replace('ё', 'е', mb_strtolower($t))) ?: [] as $w) {
    $n = mb_strlen($w);
    if ($n < 2 || in_array($w, SKILL_STOP, true)) continue;
    $o[$n >= 6 ? mb_substr($w, 0, 5) : ($n >= 4 ? mb_substr($w, 0, 4) : $w)] = 1;
  }
  return $o;
}

function skill_txt($v, int $n): string {
  if (is_array($v)) $v = implode('; ', array_map('strval', array_filter($v, 'is_scalar')));
  return trim(mb_substr((string)preg_replace('~[ \t]+~u', ' ', is_scalar($v) ? (string)$v : ''), 0, $n));
}

function skill_key(string $t): string { return mb_strtolower((string)preg_replace('~[^\p{L}\p{N}]+~u', '', str_replace('ё', 'е', $t))); }

function skill_row(array $r): array {
  return ['id' => (int)$r['id'], 'title' => (string)$r['title'], 'trig' => (string)$r['trig'], 'steps' => (string)$r['steps'], 'trace' => (string)$r['trace'],
          'author' => (string)$r['author'], 'who' => (string)$r['who'], 'manual' => (int)$r['manual'], 'uses' => (int)$r['uses'],
          'used_at' => (string)$r['used_at'], 'created_at' => (string)$r['created_at'], 'updated_at' => (string)$r['updated_at']];
}

function skill_all(): array {
  return array_map('skill_row', db()->query('SELECT * FROM skills WHERE deleted=0 ORDER BY uses DESC, id DESC LIMIT ' . SKILL_MAX)->fetchAll());
}

/* навыки, похожие на запрос: совпавшие слова (слова названия весят вдвое), сначала самые похожие и применявшиеся */
function skill_pick(string $text, int $n = 4): array {
  $q = skill_stems(mb_substr($text, 0, 600));
  if (!$q) return [];
  $sc = [];
  foreach (skill_all() as $r) {
    $s = 0;
    foreach (skill_stems($r['title']) as $k => $_) if (isset($q[$k])) $s += 2;
    foreach (skill_stems($r['trig']) as $k => $_) if (isset($q[$k])) $s += 1;
    if ($s >= 2) { $r['score'] = $s; $sc[] = $r; }
  }
  usort($sc, function (array $a, array $b): int { return [$b['score'], $b['uses'], $b['id']] <=> [$a['score'], $a['uses'], $a['id']]; });
  return array_slice($sc, 0, max(1, $n));
}

/* записать или обновить навык. $auto — записал помощник сам: навык, поправленный руководителем, он не перезаписывает */
function skill_save(array $a, string $author, string $who, int $id = 0, bool $auto = false): array {
  $t = skill_txt($a['title'] ?? '', 120); $w = skill_txt($a['trig'] ?? '', 400); $s = skill_txt($a['steps'] ?? '', 1500); $tr = skill_txt($a['trace'] ?? '', 500);
  if (mb_strlen($t) < 4 || mb_strlen($s) < 15) return ['ok' => false, 'error' => 'У навыка должны быть название и шаги (хотя бы одна строка)'];
  $d = db(); $man = 0;
  if ($id > 0) {
    $q = $d->prepare('SELECT manual FROM skills WHERE id=? AND deleted=0'); $q->execute([$id]); $m = $q->fetchColumn();
    if ($m === false) return ['ok' => false, 'error' => 'Навык не найден — возможно, его уже удалили'];
    $man = (int)$m;
  } else {
    foreach (skill_all() as $r) if (skill_key($r['title']) === skill_key($t)) { $id = $r['id']; $man = $r['manual']; break; }
  }
  if ($id > 0) {
    if ($auto && $man) return ['ok' => true, 'skip' => true, 'id' => $id, 'title' => $t, 'new' => false];
    $d->prepare('UPDATE skills SET title=?, trig=?, steps=?, trace=CASE WHEN ?<>\'\' THEN ? ELSE trace END, who=?, manual=?, updated_at=? WHERE id=?')
      ->execute([$t, $w, $s, $tr, $tr, $who, $auto ? $man : 1, now(), $id]);
    return ['ok' => true, 'id' => $id, 'new' => false, 'title' => $t];
  }
  $d->prepare('INSERT INTO skills (title, trig, steps, trace, author, who, manual, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([$t, $w, $s, $tr, mb_substr($author, 0, 60), mb_substr($who, 0, 190), $auto ? 0 : 1, now(), now()]);
  $id = (int)$d->lastInsertId();
  $n = (int)$d->query('SELECT COUNT(*) FROM skills WHERE deleted=0')->fetchColumn();
  if ($n > SKILL_MAX)
    $d->prepare("UPDATE skills SET deleted=1 WHERE id IN (SELECT id FROM skills WHERE deleted=0 AND id<>? AND src='' ORDER BY uses ASC, COALESCE(NULLIF(used_at,''), created_at) ASC LIMIT ?)")
      ->execute([$id, $n - SKILL_MAX]);
  return ['ok' => true, 'id' => $id, 'new' => true, 'title' => $t];
}

/* 0.3.5 · навыки Claude — помощника, который строит систему ИТК. Он пишет их в GitHub (docs/skills/*.md),
   агент сервера раз в 10 минут забирает GitHub и переносит сюда: «Космос» и «Фил» видят их как обычные навыки
   (автор — Claude). Источник правды — файл: изменился файл — навык обновляется; файл убрали — навык удаляется. */
function skill_parse_md(string $md): ?array {
  $md = str_replace("\r", '', $md);
  if (!preg_match('~^#\s+(.+)$~m', $md, $t)) return null;
  $trig = preg_match('~^Когда:\s*(.+)$~mu', $md, $w) ? trim($w[1]) : '';
  $p = strpos($md, "\n---\n");
  $steps = $p !== false ? trim(substr($md, $p + 5)) : '';
  if ($steps === '') return null;
  return ['title' => trim($t[1]), 'trig' => $trig, 'steps' => $steps];
}
function skill_sync_claude(string $dir): array {
  $d = db(); $seen = []; $n = ['new' => 0, 'upd' => 0, 'del' => 0, 'bad' => []];
  foreach (glob(rtrim($dir, '/') . '/*.md') ?: [] as $f) {
    $src = 'claude:' . basename($f, '.md');
    if (basename($f) === 'README.md') continue;
    $raw = (string)file_get_contents($f); $h = substr(hash('sha256', $raw), 0, 16);
    $a = skill_parse_md($raw);
    if (!$a || mb_strlen($a['steps']) > 1500) { $n['bad'][] = basename($f); continue; }
    $seen[] = $src;
    $q = $d->prepare('SELECT id, src_hash, deleted FROM skills WHERE src=? ORDER BY deleted, id DESC LIMIT 1'); $q->execute([$src]); $r = $q->fetch();
    if ($r && $r['src_hash'] === $h && !(int)$r['deleted']) continue;
    if ($r) {
      $d->prepare("UPDATE skills SET title=?, trig=?, steps=?, author='Claude', who='Claude', manual=1, deleted=0, src_hash=?, updated_at=? WHERE id=?")
        ->execute([skill_txt($a['title'], 120), skill_txt($a['trig'], 400), skill_txt($a['steps'], 1500), $h, now(), $r['id']]);
      $n['upd']++;
    } else {
      $d->prepare("INSERT INTO skills (title, trig, steps, trace, author, who, manual, created_at, updated_at, src, src_hash) VALUES (?,?,?,'','Claude','Claude',1,?,?,?,?)")
        ->execute([skill_txt($a['title'], 120), skill_txt($a['trig'], 400), skill_txt($a['steps'], 1500), now(), now(), $src, $h]);
      $n['new']++;
    }
  }
  $q = $d->query("SELECT id, src FROM skills WHERE deleted=0 AND src LIKE 'claude:%'");
  foreach ($q->fetchAll() as $r) if (!in_array($r['src'], $seen, true)) { $d->prepare('UPDATE skills SET deleted=1 WHERE id=?')->execute([$r['id']]); $n['del']++; }
  if ($n['new'] || $n['upd'] || $n['del']) log_add('', 'навыки Claude из GitHub', $n);
  return $n;
}

function skill_used(int $id): void {
  if ($id > 0) db()->prepare('UPDATE skills SET uses=uses+1, used_at=? WHERE id=? AND deleted=0')->execute([now(), $id]);
}

function skill_del(int $id): bool {
  $q = db()->prepare('UPDATE skills SET deleted=1, updated_at=? WHERE id=? AND deleted=0'); $q->execute([now(), $id]);
  return $q->rowCount() > 0;
}

/* перенос навыков из магазина: записанных раньше в его базе (№538) или из его копии, если этот сервер новый и навыков
   у него нет (0.3.2). Одинаковые по названию не задваиваем, а удалённые руководителем не возвращаем — сервер их помнит */
function skill_import(array $items): int {
  $have = []; foreach (db()->query('SELECT title FROM skills')->fetchAll() as $r) $have[skill_key((string)$r['title'])] = 1;
  $n = 0; $d = db();
  foreach (array_slice($items, 0, SKILL_MAX) as $x) {
    if (!is_array($x)) continue;
    $t = skill_txt($x['title'] ?? '', 120); $s = skill_txt($x['steps'] ?? '', 1500);
    if (mb_strlen($t) < 4 || mb_strlen($s) < 15 || isset($have[skill_key($t)])) continue;
    $ca = skill_txt($x['created_at'] ?? '', 19); if (!preg_match('~^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$~', $ca)) $ca = now();
    $ua = skill_txt($x['used_at'] ?? '', 19); if (!preg_match('~^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$~', $ua)) $ua = '';
    $d->prepare('INSERT INTO skills (title, trig, steps, trace, author, who, manual, uses, used_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([$t, skill_txt($x['trig'] ?? '', 400), $s, skill_txt($x['trace'] ?? '', 500), skill_txt($x['author'] ?? '', 60), skill_txt($x['who'] ?? '', 190),
                 !empty($x['manual']) ? 1 : 0, max(0, (int)($x['uses'] ?? 0)), $ua, $ca, now()]);
    $have[skill_key($t)] = 1; $n++;
  }
  return $n;
}

/* шлюз магазина: {a: "skills", op: …} */
function gw_skills(array $in): void {
  $op = (string)($in['op'] ?? '');
  if ($op === 'list') jout(['ok' => true, 'items' => skill_all()]);
  if ($op === 'pick') jout(['ok' => true, 'items' => skill_pick(str($in['q'] ?? '', 2000), max(1, min(10, (int)($in['n'] ?? 4))))]);
  if ($op === 'save') {
    $r = skill_save((array)($in['skill'] ?? []), str($in['author'] ?? '', 60), str($in['who'] ?? '', 190), (int)($in['id'] ?? 0), !empty($in['auto']));
    if (!empty($r['ok'])) log_add(str($in['who'] ?? '', 190), !empty($r['skip']) ? 'навык не перезаписан (поправлен руководителем)' : ($r['new'] ? 'навык записан' : 'навык обновлён'), ['id' => $r['id'], 'title' => $r['title']]);
    jout($r, empty($r['ok']) ? 400 : 200);
  }
  if ($op === 'del') { $ok = skill_del((int)($in['id'] ?? 0)); if ($ok) log_add(str($in['who'] ?? '', 190), 'навык удалён', ['id' => (int)($in['id'] ?? 0)]); jout(['ok' => true, 'deleted' => $ok]); }
  if ($op === 'used') { skill_used((int)($in['id'] ?? 0)); jout(['ok' => true]); }
  if ($op === 'import') { $n = skill_import((array)($in['items'] ?? [])); log_add('', 'навыки перенесены из магазина', ['added' => $n]); jout(['ok' => true, 'added' => $n]); }
  fail('Неизвестное действие с навыками', 404);
}
