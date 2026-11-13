<?php
declare(strict_types=1);
// ==== ИТК · КОСМОС · bin/worker.php · фоновая работа ====
// Служба kosmos-worker: берёт задачи из очереди и выполняет их глубокой моделью,
// раз в 10 минут сжимает длинные разговоры в сводку. Раз в час сама перезапускается (systemd поднимает заново).
// Запуск вручную для проверки: php bin/worker.php once

if (PHP_SAPI !== 'cli') exit;
require_once dirname(__DIR__) . '/lib/boot.php';

$once = ($argv[1] ?? '') === 'once';
$start = time(); $lastSum = 0;
tasks_recover();
while (true) {
  setting_put('worker_beat', (string)time());
  $did = false;
  try {
    $task = task_claim();
    if ($task) { task_run($task); $did = true; }
    if (time() - $lastSum > 600) { $lastSum = time(); threads_summarize(); }
  } catch (Throwable $e) {
    log_add('', 'worker_error', ['e' => $e->getMessage(), 'at' => basename($e->getFile()) . ':' . $e->getLine()]);
    sleep(5);
  }
  if ($once && !$did) break;
  if (time() - $start > 3600) break;
  if (!$did) sleep(2);
}
