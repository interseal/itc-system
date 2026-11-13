<?php
// ИТК · ds_check.php · проверка DeepSeek в Yandex AI Studio · вариант 2 (07.10.2026)
// Временный файл: после проверки удалить из папки api.
// Ключ не показывает — только ответы Яндекса.
@set_time_limit(180);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ob_yandex.php';

echo "=== ПРОВЕРКА DEEPSEEK · ВАРИАНТ 2 ===\n";
if (!yx_ready()) { echo "СТОП: нет ключей в config.php\n"; exit; }

function ds_raw($model, $extra, $maxTok) {
  $body = array_merge([
    'model'       => yx_ds_uri($model),
    'messages'    => [
      ['role' => 'system', 'content' => 'Переведи на русский. Верни только перевод.'],
      ['role' => 'user',   'content' => '内六角扳手9件套'],
    ],
    'temperature' => 0.2,
    'max_tokens'  => $maxTok,
  ], $extra);
  $ch = curl_init(YX_CHAT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 60,
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Api-Key ' . YC_API_KEY, 'x-folder-id: ' . YC_FOLDER_ID],
  ]);
  $t = microtime(true);
  $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  $s = round(microtime(true) - $t, 1);
  $j = json_decode((string)$r, true);
  $c = $j['choices'][0] ?? [];
  $content = (string)($c['message']['content'] ?? '');
  $reason  = (string)($c['message']['reasoning_content'] ?? $c['message']['reasoning'] ?? '');
  echo "\n[" . ($extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : 'без доп. настроек') . ", лимит $maxTok]  код $code, $s сек\n";
  if ($code !== 200) { echo "  ОТВЕТ: " . mb_substr((string)$r, 0, 300) . "\n"; return; }
  echo "  ПЕРЕВОД: " . ($content !== '' ? mb_substr($content, 0, 150) : '(пусто)') . "\n";
  echo "  РАЗМЫШЛЕНИЯ: " . ($reason !== '' ? mb_strlen($reason) . ' симв.' : 'нет') . "\n";
  echo "  ПРИЧИНА ОСТАНОВКИ: " . ($c['finish_reason'] ?? '?') . "\n";
  if (isset($j['usage'])) echo "  ТОКЕНЫ: " . json_encode($j['usage'], JSON_UNESCAPED_UNICODE) . "\n";
}

foreach (['deepseek-v4.1-flash', 'deepseek-v4-flash'] as $m) {
  echo "\n========== $m ==========\n";
  ds_raw($m, [], 20);
  ds_raw($m, [], 1500);
  ds_raw($m, ['reasoning_effort' => 'none'], 200);
  ds_raw($m, ['reasoning_effort' => 'low'], 200);
  ds_raw($m, ['thinking' => ['type' => 'disabled']], 200);
  ds_raw($m, ['chat_template_kwargs' => ['thinking' => false]], 200);
}
echo "\n=== КОНЕЦ ===\n";
