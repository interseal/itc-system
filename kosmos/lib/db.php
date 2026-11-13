<?php
declare(strict_types=1);
// ==== КОСМОС · lib/db.php · база памяти (SQLite, один файл) ====
// Один файл базы легко копировать и переносить на другой сервер — вся память «Космоса» в нём.

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $f = (string)cfg('db');
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0750, true);
  $pdo = new PDO('sqlite:' . $f, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA journal_mode = WAL');
  $pdo->exec('PRAGMA busy_timeout = 8000');
  $pdo->exec('PRAGMA foreign_keys = ON');
  db_schema($pdo);
  return $pdo;
}

function db_schema(PDO $d): void {
  $d->exec("CREATE TABLE IF NOT EXISTS users (
    phone TEXT PRIMARY KEY, name TEXT NOT NULL DEFAULT '', role TEXT NOT NULL DEFAULT '', boss INTEGER NOT NULL DEFAULT 0,
    allow TEXT NOT NULL DEFAULT '', first_at TEXT NOT NULL DEFAULT '', last_at TEXT NOT NULL DEFAULT '')");
  $d->exec("CREATE TABLE IF NOT EXISTS threads (
    id TEXT PRIMARY KEY, phone TEXT NOT NULL, title TEXT NOT NULL DEFAULT '', summary TEXT NOT NULL DEFAULT '',
    summary_upto INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0)");
  $d->exec("CREATE INDEX IF NOT EXISTS idx_threads_phone ON threads (phone, deleted, updated_at)");
  $d->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT, thread_id TEXT NOT NULL, role TEXT NOT NULL, text TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '[]', task_id TEXT NOT NULL DEFAULT '', model TEXT NOT NULL DEFAULT '', key_id INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL)");
  $d->exec("CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages (thread_id, id)");
  $d->exec("CREATE TABLE IF NOT EXISTS memory (
    id INTEGER PRIMARY KEY AUTOINCREMENT, scope TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '', text TEXT NOT NULL,
    source TEXT NOT NULL DEFAULT '', created_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0)");
  $d->exec("CREATE TABLE IF NOT EXISTS tasks (
    id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, phone TEXT NOT NULL, goal TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'queued',
    model TEXT NOT NULL DEFAULT '', steps TEXT NOT NULL DEFAULT '[]', result TEXT NOT NULL DEFAULT '', error TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL, started_at TEXT NOT NULL DEFAULT '', finished_at TEXT NOT NULL DEFAULT '')");
  $d->exec("CREATE INDEX IF NOT EXISTS idx_tasks_status ON tasks (status, created_at)");
  $d->exec("CREATE TABLE IF NOT EXISTS keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, enc TEXT NOT NULL, last4 TEXT NOT NULL DEFAULT '',
    active INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT '', checked_at TEXT NOT NULL DEFAULT '',
    created_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0)");
  // 0.2.1 · личные ключи Claude (созданы без рабочей области): ID рабочей области (workspace) и её название —
  // «Космос» указывает её в каждом запросе (заголовок anthropic-workspace-id)
  $cols = array_column($d->query('PRAGMA table_info(keys)')->fetchAll(PDO::FETCH_ASSOC), 'name');
  foreach (['ws', 'ws_name'] as $c) {
    if (in_array($c, $cols, true)) continue;
    try { $d->exec("ALTER TABLE keys ADD COLUMN $c TEXT NOT NULL DEFAULT ''"); }
    catch (PDOException $e) { if (stripos($e->getMessage(), 'duplicate column') === false) throw $e; }   // другой процесс успел раньше
  }
  $d->exec("CREATE TABLE IF NOT EXISTS usage (
    day TEXT NOT NULL, key_id INTEGER NOT NULL, model TEXT NOT NULL, calls INTEGER NOT NULL DEFAULT 0,
    tokens_in INTEGER NOT NULL DEFAULT 0, tokens_out INTEGER NOT NULL DEFAULT 0, cache_read INTEGER NOT NULL DEFAULT 0,
    cache_write INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, key_id, model))");
  $d->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT NOT NULL DEFAULT '')");
  $d->exec("CREATE TABLE IF NOT EXISTS log (id INTEGER PRIMARY KEY AUTOINCREMENT, at TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '',
    what TEXT NOT NULL, data TEXT NOT NULL DEFAULT '')");
  $d->exec("CREATE TABLE IF NOT EXISTS auth_cache (h TEXT PRIMARY KEY, user TEXT NOT NULL, at INTEGER NOT NULL)");
  $d->exec("CREATE TABLE IF NOT EXISTS rate (phone TEXT NOT NULL, at INTEGER NOT NULL)");
  // 0.2.0 · обращения через шлюз магазина («Космос» в Экосистеме и CRM): по людям и дням
  $d->exec("CREATE TABLE IF NOT EXISTS gw_use (day TEXT NOT NULL, phone TEXT NOT NULL, calls INTEGER NOT NULL DEFAULT 0,
    tokens_in INTEGER NOT NULL DEFAULT 0, tokens_out INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, phone))");
}

function setting(string $k, string $def = ''): string {
  $q = db()->prepare('SELECT v FROM settings WHERE k=?'); $q->execute([$k]);
  $v = $q->fetchColumn();
  return $v === false ? $def : (string)$v;
}

function setting_put(string $k, string $v): void {
  db()->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v')->execute([$k, $v]);
}

/* Модели: из настроек руководителя, иначе из config.php. */
function model(string $kind): string {
  $m = cfg('models');
  $def = (string)($m[$kind] ?? $m['deep'] ?? 'claude-sonnet-5-5');
  $v = setting('model_' . $kind);
  return $v !== '' ? $v : $def;
}
