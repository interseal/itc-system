<?php
declare(strict_types=1);
// ==== КОСМОС · lib/tools.php · инструменты, которыми пользуется Claude ====
// Этап 1: память, поиск по прошлому, фоновые задачи. Данные Экосистемы — этап 3.

function tool_defs(string $mode): array {
  $t = [
    ['name' => 'remember', 'description' => 'Запомнить важный устойчивый факт, решение, договорённость или предпочтение. scope: company — общее о компании (только руководитель), user — о собеседнике.',
     'input_schema' => ['type' => 'object', 'properties' => [
        'text' => ['type' => 'string', 'description' => 'Что запомнить, одной фразой, понятно без контекста'],
        'scope' => ['type' => 'string', 'enum' => ['company', 'user']]], 'required' => ['text']]],
    ['name' => 'forget', 'description' => 'Забыть запись памяти по её номеру (№ из блока памяти).',
     'input_schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']]],
    ['name' => 'recall', 'description' => 'Найти в памяти и в прошлых разговорах собеседника то, что относится к запросу.',
     'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'required' => ['query']]],
  ];
  if ($mode === 'fast') $t[] = ['name' => 'start_task', 'description' => 'Передать долгую работу глубокой модели в фон. Результат придёт отдельным сообщением.',
     'input_schema' => ['type' => 'object', 'properties' => ['goal' => ['type' => 'string', 'description' => 'Ясная цель: что сделать, для кого, в каком виде результат']], 'required' => ['goal']]];
  if ($mode === 'deep') $t[] = ['name' => 'report_progress', 'description' => 'Коротко сообщить человеку, что сейчас делается (одна строка).',
     'input_schema' => ['type' => 'object', 'properties' => ['note' => ['type' => 'string']], 'required' => ['note']]];
  return $t;
}

/* Выполнить инструмент. Возвращает [ответ для Claude, пометка для человека или null]. */
function tool_exec(string $name, $input, array $ctx): array {
  $in = is_array($input) ? $input : (is_object($input) ? (array)$input : []);
  $me = $ctx['me'];
  switch ($name) {
    case 'remember':
      $r = mem_add($me, (string)($in['scope'] ?? 'user'), (string)($in['text'] ?? ''), 'kosmos');
      if (empty($r['ok'])) return ['Не записано: ' . ($r['error'] ?? 'ошибка'), null];
      return ['Записано в память под №' . $r['id'] . ($r['scope'] === 'company' ? ' (о компании)' : ' (о собеседнике)'),
              'Запомнил: ' . str($in['text'] ?? '', 200)];
    case 'forget':
      $ok = mem_del($me, (int)($in['id'] ?? 0));
      return [$ok ? 'Запись удалена' : 'Такой записи нет или её может удалить только руководитель', $ok ? 'Забыл запись №' . (int)$in['id'] : null];
    case 'recall':
      $found = mem_search($me, (string)($in['query'] ?? ''));
      if (!$found) return ['Ничего не нашлось', null];
      $lines = array_map(fn($f) => '— ' . $f['kind'] . ', ' . $f['at'] . ': ' . $f['text'], $found);
      return [implode("\n", $lines), null];
    case 'start_task':
      $goal = str($in['goal'] ?? '', 2000);
      if ($goal === '') return ['Цель пустая — задача не создана', null];
      $task = task_create($ctx['thread_id'], $me, $goal);
      return ['Задача поставлена в работу (№' . $task['id'] . '). Скажи человеку одной фразой, что результат придёт сюда же.',
              'task:' . $task['id']];
    case 'report_progress':
      if (!empty($ctx['task_id'])) task_step((string)$ctx['task_id'], str($in['note'] ?? '', 300));
      return ['Ок', null];
  }
  return ['Неизвестный инструмент', null];
}
