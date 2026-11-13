<?php
declare(strict_types=1);
// ==== КОСМОС · lib/util.php · мелкие общие функции ====

function now(): string { return date('Y-m-d H:i:s'); }

function uid(int $n = 10): string { return bin2hex(random_bytes($n)); }

function str($v, int $max = 2000): string {
  $s = is_scalar($v) ? trim((string)$v) : '';
  return mb_substr($s, 0, $max);
}

function body(): array {
  static $b = null;
  if ($b === null) {
    $raw = (string)file_get_contents('php://input');
    $j = $raw !== '' ? json_decode($raw, true) : [];
    $b = is_array($j) ? $j : [];
  }
  return $b;
}

function jout(array $a, int $code = 200): void {
  if (!headers_sent()) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
  }
  echo json_encode($a, JSON_UNESCAPED_UNICODE);
  exit;
}

function fail(string $msg, int $code = 400): void { jout(['ok' => false, 'error' => $msg], $code); }

function log_add(string $phone, string $what, $data = null): void {
  try {
    db()->prepare('INSERT INTO log (at, phone, what, data) VALUES (?,?,?,?)')
      ->execute([now(), $phone, mb_substr($what, 0, 120), $data === null ? '' : mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE), 0, 4000)]);
  } catch (Throwable $e) { /* журнал не должен ломать работу */ }
}

/* Поток событий для экрана: одна строка «data: {...}» на событие. */
function sse_start(): void {
  @set_time_limit(0);
  ignore_user_abort(true);
  header('Content-Type: text/event-stream; charset=utf-8');
  header('Cache-Control: no-cache, no-store');
  header('X-Accel-Buffering: no');
  while (ob_get_level() > 0) @ob_end_flush();
  @ob_implicit_flush(true);
  echo ":" . str_repeat(' ', 2048) . "\n\n";   // пробиваем буферы по дороге
  @flush();
}

function sse(array $ev): void {
  echo 'data: ' . json_encode($ev, JSON_UNESCAPED_UNICODE) . "\n\n";
  @flush();
}
