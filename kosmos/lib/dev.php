<?php
declare(strict_types=1);
// ==== КОСМОС · lib/dev.php · «Космос»-разработчик (0.3.9) · Фил-разработчик на DeepSeek (0.4.0) ====
// Только для руководителя. Поручение уходит в Claude Code на этом сервере: своё рабочее место (/srv/itc-dev, копия кода
// из GitHub), модель на выбор (по умолчанию Opus), свои помощники. Claude Code работает от отдельного пользователя
// kosmosdev: не видит ключей сервера и не может писать в GitHub. Записывает в GitHub агент (root) — только если
// проверки прошли, в ветку kosmos/<номер>; в главную ветку — только если поручение выпускает обновление (releases/N.json).
// Ставит обновление по-прежнему руководитель кнопкой «Установить». Лимит расходов в день — в настройках (по умолчанию $30).

const DEV_MODELS = ['opus' => 'Opus 5.5', 'sonnet' => 'Sonnet 5.5', 'fable' => 'Fable 5.2'];

function dev_schema(PDO $d): void {
  $d->exec("CREATE TABLE IF NOT EXISTS dev_tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT, text TEXT NOT NULL, model TEXT NOT NULL DEFAULT 'opus', status TEXT NOT NULL DEFAULT 'queued',
    phone TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, started_at TEXT NOT NULL DEFAULT '', finished_at TEXT NOT NULL DEFAULT '',
    cost_u INTEGER NOT NULL DEFAULT 0, result TEXT NOT NULL DEFAULT '', checks TEXT NOT NULL DEFAULT '', branch TEXT NOT NULL DEFAULT '',
    commit_hash TEXT NOT NULL DEFAULT '', release_n INTEGER NOT NULL DEFAULT 0, merged INTEGER NOT NULL DEFAULT 0, error TEXT NOT NULL DEFAULT '')");
  // 0.4.0 · движок: claude — «Космос» (ключ Claude), deepseek — «Фил» (Claude Code через совместимый вход DeepSeek)
  $cols = array_column($d->query('PRAGMA table_info(dev_tasks)')->fetchAll(), 'name');
  if (!in_array('engine', $cols, true)) $d->exec("ALTER TABLE dev_tasks ADD COLUMN engine TEXT NOT NULL DEFAULT 'claude'");
}
const DEV_ENGINES = ['claude' => 'Космос (Claude)', 'deepseek' => 'Фил (DeepSeek)'];
/* примерная цена DeepSeek за миллион токенов, $ — Claude Code считает расход по ценам Claude, для DeepSeek пересчитываем сами */
const DEV_DS_PRICE = ['in' => 0.6, 'cache' => 0.06, 'out' => 2.4];
function dev_ds_cost(array $usage): float {
  $in = (int)($usage['input_tokens'] ?? 0) + (int)($usage['cache_creation_input_tokens'] ?? 0); $c = (int)($usage['cache_read_input_tokens'] ?? 0); $o = (int)($usage['output_tokens'] ?? 0);
  return round(($in * DEV_DS_PRICE['in'] + $c * DEV_DS_PRICE['cache'] + $o * DEV_DS_PRICE['out']) / 1000000, 4);
}

function dev_limit_u(): int { return (int)round((float)setting('dev_limit', '30') * 1000000); }
function dev_spent_today_u(): int {
  $q = db()->prepare("SELECT COALESCE(SUM(cost_u),0) FROM dev_tasks WHERE substr(created_at,1,10)=?"); $q->execute([date('Y-m-d')]);
  return (int)$q->fetchColumn();
}
function dev_row(array $r): array {
  return ['id' => (int)$r['id'], 'text' => (string)$r['text'], 'model' => (string)$r['model'], 'status' => (string)$r['status'],
          'created_at' => (string)$r['created_at'], 'started_at' => (string)$r['started_at'], 'finished_at' => (string)$r['finished_at'],
          'cost' => round((int)$r['cost_u'] / 1000000, 2), 'result' => (string)$r['result'], 'checks' => (string)$r['checks'],
          'branch' => (string)$r['branch'], 'commit' => (string)$r['commit_hash'], 'release_n' => (int)$r['release_n'], 'merged' => (int)$r['merged'], 'error' => (string)$r['error'], 'engine' => (string)($r['engine'] ?? 'claude')];
}
function dev_list(int $n = 30): array {
  dev_schema(db());
  return array_map('dev_row', db()->query('SELECT * FROM dev_tasks ORDER BY id DESC LIMIT ' . max(1, min(100, $n)))->fetchAll());
}
function dev_add(string $text, string $model, string $phone, string $engine = 'claude'): array {
  dev_schema(db());
  if (!isset(DEV_ENGINES[$engine])) $engine = 'claude';
  if ($engine === 'deepseek' && key_dec((string)setting('ds_key', '')) === '') fail('Нет ключа DeepSeek для Фила-разработчика');
  $text = trim(mb_substr($text, 0, 8000));
  if (mb_strlen($text) < 10) fail('Опишите поручение подробнее — хотя бы одно предложение');
  if (!isset(DEV_MODELS[$model])) $model = 'opus';
  if (dev_spent_today_u() >= dev_limit_u()) fail('Лимит расходов разработчика на сегодня исчерпан ($' . setting('dev_limit', '30') . '). Поднимите лимит или продолжим завтра');
  db()->prepare('INSERT INTO dev_tasks (text, model, phone, created_at, engine) VALUES (?,?,?,?,?)')->execute([$text, $engine === 'deepseek' ? 'opus' : $model, $phone, now(), $engine]);
  $id = (int)db()->lastInsertId();
  log_add($phone, 'поручение разработчику', ['id' => $id, 'model' => $model, 'engine' => $engine]);
  return ['id' => $id];
}
function dev_cancel(int $id, string $phone): void {
  dev_schema(db());
  $q = db()->prepare("UPDATE dev_tasks SET status='cancelled', finished_at=? WHERE id=? AND status='queued'"); $q->execute([now(), $id]);
  if (!$q->rowCount()) fail('Отменить можно только поручение, которое ещё не начато');
  log_add($phone, 'поручение разработчику отменено', ['id' => $id]);
}
/* 0.4.2 · правки сборки (gradle, ключ подписи) ждут решения руководителя: «Одобрить» — агент отправит их в главную ветку и выпустит обновление */
function dev_decide(int $id, bool $ok, string $phone): void {
  dev_schema(db());
  $q = db()->prepare("UPDATE dev_tasks SET status=? WHERE id=? AND status='needs_ok'"); $q->execute([$ok ? 'approved' : 'rejected', $id]);
  if (!$q->rowCount()) fail('Это поручение не ждёт вашего решения');
  log_add($phone, $ok ? 'правки разработчика одобрены' : 'правки разработчика отклонены', ['id' => $id]);
}
function dev_approved(): array {
  dev_schema(db());
  return array_map('dev_row', db()->query("SELECT * FROM dev_tasks WHERE status='approved' ORDER BY id")->fetchAll());
}
function dev_state(): array {
  $f = dirname((string)cfg('db')) . '/dev.json';
  $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
  return is_array($j) ? $j : [];
}
/* для агента (от www-data): взять следующее поручение и ключ Claude */
function dev_claim(): ?array {
  dev_schema(db());
  if (dev_spent_today_u() >= dev_limit_u()) return null;
  $r = db()->query("SELECT * FROM dev_tasks WHERE status='queued' ORDER BY id LIMIT 1")->fetch();
  if (!$r) return null;
  $ws = '';
  if (($r['engine'] ?? 'claude') === 'deepseek') { $key = key_dec((string)setting('ds_key', '')); if ($key === '') return null; }
  else { [$kr, $key] = key_active(); if ($key === '') return null; $ws = key_ws((int)$kr['id']); }
  db()->prepare("UPDATE dev_tasks SET status='running', started_at=? WHERE id=? AND status='queued'")->execute([now(), $r['id']]);
  $left = max(1, dev_limit_u() - dev_spent_today_u());
  return ['task' => dev_row($r), 'key' => $key, 'budget' => round(min($left, 20000000) / 1000000, 2), 'ws' => $ws, 'engine' => (string)($r['engine'] ?? 'claude')];
}
function dev_finish(int $id, array $a): void {
  dev_schema(db());
  db()->prepare('UPDATE dev_tasks SET status=?, finished_at=?, cost_u=?, result=?, checks=?, branch=?, commit_hash=?, release_n=?, merged=?, error=? WHERE id=?')
    ->execute([(string)($a['status'] ?? 'done'), now(), (int)round((float)($a['cost'] ?? 0) * 1000000), mb_substr((string)($a['result'] ?? ''), 0, 6000),
               mb_substr((string)($a['checks'] ?? ''), 0, 3000), (string)($a['branch'] ?? ''), (string)($a['commit'] ?? ''), (int)($a['release_n'] ?? 0),
               !empty($a['merged']) ? 1 : 0, mb_substr((string)($a['error'] ?? ''), 0, 1000), $id]);
}

/* 0.4.0 · шлюз магазина: Фил (и Космос в магазине) поручает разработку от имени руководителя.
   Магазин пускает к инструменту только руководителя; ключ DeepSeek приходит от магазина и хранится зашифрованным. */
function gw_dev(array $in): void {
  dev_schema(db());
  $op = (string)($in['op'] ?? '');
  $info = function () { return ['spent' => round(dev_spent_today_u() / 1000000, 2), 'limit' => (float)setting('dev_limit', '30')]; };
  if ($op === 'list') jout(['ok' => true, 'tasks' => dev_list(10)] + $info());
  if ($op === 'decide') { dev_decide((int)($in['id'] ?? 0), !empty($in['ok']), 'магазин: ' . mb_substr((string)($in['who'] ?? ''), 0, 60)); jout(['ok' => true]); }
  if ($op === 'add') {
    $eng = (string)($in['engine'] ?? 'claude');
    if ($eng === 'deepseek') {
      $k = trim((string)($in['key'] ?? ''));
      if (!preg_match('~^sk-[A-Za-z0-9]{16,}$~', $k)) fail('Ключ DeepSeek не похож на ключ');
      if (key_dec((string)setting('ds_key', '')) !== $k) setting_put('ds_key', key_enc($k));
    }
    $r = dev_add((string)($in['text'] ?? ''), (string)($in['model'] ?? 'opus'), 'магазин: ' . mb_substr((string)($in['who'] ?? ''), 0, 60), $eng);
    jout(['ok' => true, 'id' => $r['id']] + $info());
  }
  fail('Неизвестное действие разработчика', 404);
}
