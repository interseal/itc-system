<?php
// ==== ИТК · ai_lib.php · №2 (08.11.2026) · + запасной Qwen (Alibaba) после DeepSeek ====
// (было: ai_lib.php · №1 (02.11.2026) · общий вызов ИИ для Экосистемы и магазина ====
// Думает ТОЛЬКО DeepSeek (YandexGPT не используем — решение Алексея 02.11.2026):
//   1) DeepSeek через Yandex AI Studio (ключи YC_API_KEY / YC_FOLDER_ID из config.php),
//   2) запасной путь — DeepSeek напрямую (DEEPSEEK_API_KEY), когда счёт будет пополнен.
// Отказавший путь не трогаем 5 минут (нет модели / денег — час), чтобы люди не ждали зря.
// Подключать после config.php. Все имена начинаются с itcai_ — чтобы не спорить с config.php.

function itcai_dir() {
  $d = dirname(__DIR__) . '/eco/ai_state';
  if (!is_dir($d)) @mkdir($d, 0755, true);
  if (!is_file("$d/.htaccess")) @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n");
  if (!is_file("$d/index.html")) @file_put_contents("$d/index.html", '');
  return $d;
}

function itcai_post($url, $body, $headers, $timeout) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 5]);
  $r = curl_exec($ch); $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$http, $r === false ? '' : $r];
}

/* Вернуть текст ответа или null. $GLOBALS['ITCAI_ERR'] — какие пути отказали (для подсказки на экране). */
function itcai_ask($sys, $user, $maxTok = 2000, $timeout = 40, $json = false) {
  $YK = defined('YC_API_KEY') ? YC_API_KEY : ($GLOBALS['YC_API_KEY'] ?? '');
  $YF = defined('YC_FOLDER_ID') ? YC_FOLDER_ID : ($GLOBALS['YC_FOLDER_ID'] ?? '');
  $DK = defined('DEEPSEEK_API_KEY') ? DEEPSEEK_API_KEY : '';
  $chain = [];
  if ($YK && $YF) {
    $u = 'https://llm.api.cloud.yandex.net/v1/chat/completions';
    $hb = ['Authorization: Bearer ' . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF];
    $hk = ['Authorization: Api-Key ' . $YK, 'OpenAI-Project: ' . $YF, 'x-folder-id: ' . $YF];
    $chain['yds41'] = [$u, "gpt://$YF/deepseek-v4.1-flash", $hb];
    $chain['yds41k'] = [$u, "gpt://$YF/deepseek-v4.1-flash", $hk];
    $chain['yds4'] = [$u, "gpt://$YF/deepseek-v4-flash", $hb];
    $chain['yds4k'] = [$u, "gpt://$YF/deepseek-v4-flash", $hk];
  }
  if ($DK) $chain['ds'] = ['https://api.deepseek.com/chat/completions', 'deepseek-v4-flash', ['Authorization: Bearer ' . $DK]];
  // №330 · последний запасной путь — Qwen от Alibaba (тот же ключ, что у рисовалки; магазин кладёт его в api/config_art.php)
  $QA = @include __DIR__ . '/config_art.php';
  if (is_array($QA) && !empty($QA['key'])) { $qb = !empty($QA['base']) ? preg_replace('~/api/v1/?$~', '', rtrim($QA['base'], '/')) : 'https://dashscope.aliyuncs.com';
    $chain['qw'] = [$qb . '/compatible-mode/v1/chat/completions', 'qwen-plus', ['Authorization: Bearer ' . $QA['key']]]; }
  $GLOBALS['ITCAI_ERR'] = [];
  $dir = itcai_dir(); $start = microtime(true);
  foreach ($chain as $id => $p) {
    $brk = "$dir/brk_$id.txt";
    if (is_file($brk) && (int)@file_get_contents($brk) > time()) continue;
    $left = (int)($timeout - (microtime(true) - $start)); if ($left < 4) break;
    $req = ['model' => $p[1], 'temperature' => 0.2, 'max_tokens' => $maxTok, 'stream' => false,
      'messages' => [['role' => 'system', 'content' => $sys], ['role' => 'user', 'content' => $user]]];
    if ($json && $id === 'ds') $req['response_format'] = ['type' => 'json_object'];
    [$http, $r] = itcai_post($p[0], json_encode($req, JSON_UNESCAPED_UNICODE), array_merge(['Content-Type: application/json'], $p[2]), $left);
    $txt = $http === 200 ? (json_decode($r, true)['choices'][0]['message']['content'] ?? null) : null;
    if (is_string($txt) && trim($txt) !== '') return $txt;
    error_log("itcai $id http=$http " . substr((string)$r, 0, 200));
    $GLOBALS['ITCAI_ERR'][] = $id . ':' . ($http ?: 'нет связи');
    @file_put_contents($brk, (string)(time() + (in_array($http, [401, 402, 403, 404], true) ? 3600 : 300)));
  }
  if (!$GLOBALS['ITCAI_ERR']) $GLOBALS['ITCAI_ERR'][] = $chain ? 'все пути на паузе' : 'нет ключей';
  return null;
}

/* Ответ модели → массив (вырезаем JSON из текста, если модель добавила лишнее). */
function itcai_json($txt) {
  if (!is_string($txt)) return null;
  $t = trim(preg_replace('/^```(?:json)?|```$/m', '', $txt));
  $a = strpos($t, '{'); $b = strrpos($t, '}');
  if ($a === false || $b === false) return null;
  $j = json_decode(substr($t, $a, $b - $a + 1), true);
  return is_array($j) ? $j : null;
}

function itcai_err_text() { return 'DeepSeek: ' . implode(', ', $GLOBALS['ITCAI_ERR'] ?? []); }

/* Простой кэш в файлах: ключ → массив, со сроком жизни. */
function itcai_cache_get($ns, $key, $ttl) {
  $f = itcai_dir() . "/$ns/" . substr($key, 0, 2) . "/$key.json";
  if (!is_file($f) || filemtime($f) < time() - $ttl) return null;
  $j = json_decode((string)file_get_contents($f), true);
  return is_array($j) ? $j : null;
}
function itcai_cache_put($ns, $key, $val) {
  $d = itcai_dir() . "/$ns/" . substr($key, 0, 2);
  if (!is_dir($d)) @mkdir($d, 0755, true);
  @file_put_contents("$d/$key.json.tmp", json_encode($val, JSON_UNESCAPED_UNICODE), LOCK_EX);
  @rename("$d/$key.json.tmp", "$d/$key.json");
}
