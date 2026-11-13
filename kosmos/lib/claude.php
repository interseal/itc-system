<?php
declare(strict_types=1);
// ==== КОСМОС · lib/claude.php · запрос к Claude (Messages API) ====
// claude_request(ключ, id ключа, запрос, [что делать с кусочками текста]) → (рабочую область личного ключа подставляет сам)
//   ['content' => [...блоки...], 'stop_reason' => '...', 'usage' => [...], 'model' => '...']
//   или ['error' => 'понятная ошибка', 'http' => код].
// С функцией $onText ответ идёт потоком: текст показывается по мере написания.

function claude_request(string $key, int $keyId, array $payload, ?callable $onText = null): array {
  if ($key === '') return ['error' => 'Нет активного ключа Claude — руководитель добавляет его в настройках', 'http' => 0];
  $stream = $onText !== null;
  if ($stream) $payload['stream'] = true;
  $tries = $stream ? 1 : 3;
  $ws = $keyId > 0 ? key_ws($keyId) : '';
  $res = ['error' => 'Нет связи с Claude', 'http' => 0];
  for ($i = 0; $i < $tries; $i++) {
    if ($i > 0) usleep(1500000 * $i);
    $res = claude_once($key, $payload, $onText, $ws);
    if (empty($res['error'])) break;
    if (!in_array((int)$res['http'], [0, 500, 502, 503, 504, 529], true)) break;   // повторяем только перегрузку и сбои связи
  }
  // 0.2.1 · личный ключ без рабочей области: «Космос» сам находит рабочую область, запоминает её у ключа и повторяет запрос
  // (ответ с ошибкой приходит до первого слова, поэтому повтор безопасен и для потока)
  if (!empty($res['ws_needed']) && $keyId > 0) {
    $pick = ws_auto($key, $keyId);
    if ($pick !== '') { $ws = $pick; $res = claude_once($key, $payload, $onText, $ws); }
  }
  if (empty($res['error'])) usage_add($keyId, (string)($res['model'] ?? $payload['model']), (array)($res['usage'] ?? []));
  if ($keyId > 0 && claude_last_org() !== '') key_org_seen($keyId, claude_last_org());   // 0.3.3 · счёт ключа (lib/price.php)
  return $res;
}

/* 0.3.3 · счёт (организация Claude) из заголовка последнего ответа — anthropic-organization-id есть в каждом ответе */
function claude_last_org(?string $set = null): string { static $o = ''; if ($set !== null) $o = $set; return $o; }

function claude_once(string $key, array $payload, ?callable $onText, string $ws = ''): array {
  $ch = curl_init((string)cfg('claude_url'));
  $headers = ['content-type: application/json', 'anthropic-version: 2023-06-01', 'x-api-key: ' . $key];
  if ($ws !== '') $headers[] = 'anthropic-workspace-id: ' . $ws;   // 0.2.1 · рабочая область личного ключа
  $st = ['buf' => '', 'raw' => '', 'blocks' => [], 'json' => [], 'stop' => '', 'usage' => [], 'model' => '', 'err' => ''];
  $opts = [
    CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => $onText ? 600 : 300,
  ];
  if ($onText) {
    $opts[CURLOPT_WRITEFUNCTION] = function ($c, $chunk) use (&$st, $onText) {
      $st['raw'] .= strlen($st['raw']) < 20000 ? $chunk : '';
      $st['buf'] .= $chunk;
      while (($p = strpos($st['buf'], "\n\n")) !== false) {
        $ev = substr($st['buf'], 0, $p); $st['buf'] = substr($st['buf'], $p + 2);
        claude_event($ev, $st, $onText);
      }
      return strlen($chunk);
    };
  } else {
    $opts[CURLOPT_RETURNTRANSFER] = true;
  }
  claude_last_org('');
  $opts[CURLOPT_HEADERFUNCTION] = function ($c, $h) {
    if (stripos($h, 'anthropic-organization-id:') === 0) claude_last_org(trim(substr($h, 26)));
    return strlen($h);
  };
  curl_setopt_array($ch, $opts);
  $out = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $cerr = curl_error($ch);
  curl_close($ch);

  if ($out === false && $http === 0) return ['error' => 'Нет связи с Claude' . ($cerr ? ' (' . $cerr . ')' : ''), 'http' => 0];
  if ($onText) {
    if ($st['buf'] !== '') claude_event($st['buf'], $st, $onText);
    if ($http !== 200) return claude_fail($http, $st['raw']);
    if ($st['err'] !== '') return ['error' => $st['err'], 'http' => 529];
    if ($st['stop'] === '') return ['error' => 'Связь с Claude оборвалась посреди ответа — попробуйте ещё раз', 'http' => 0];
    ksort($st['blocks']);
    $blocks = [];
    foreach ($st['blocks'] as $i => $b) {
      if (($b['type'] ?? '') === 'tool_use') {
        $j = json_decode($st['json'][$i] ?? '', true);
        $b['input'] = is_array($j) ? $j : [];
      }
      $blocks[] = $b;
    }
    return ['content' => blocks_clean($blocks), 'stop_reason' => $st['stop'], 'usage' => $st['usage'], 'model' => $st['model']];
  }
  if ($http !== 200) return claude_fail($http, (string)$out);
  $j = json_decode((string)$out, true);
  if (!is_array($j)) return ['error' => 'Claude ответил непонятно', 'http' => $http];
  return ['content' => blocks_clean((array)($j['content'] ?? [])), 'stop_reason' => (string)($j['stop_reason'] ?? ''),
          'usage' => $j['usage'] ?? [], 'model' => (string)($j['model'] ?? '')];
}

/* Блоки ответа в виде, пригодном для отправки обратно: без пустых текстов, входные данные инструмента — всегда объект. */
function blocks_clean(array $blocks): array {
  $out = [];
  foreach ($blocks as $b) {
    $type = (string)($b['type'] ?? '');
    if ($type === 'text') {
      if (trim((string)($b['text'] ?? '')) === '') continue;
      $out[] = ['type' => 'text', 'text' => (string)$b['text']];
    } elseif ($type === 'tool_use') {
      $in = $b['input'] ?? [];
      $out[] = ['type' => 'tool_use', 'id' => (string)$b['id'], 'name' => (string)$b['name'],
                'input' => (is_array($in) && $in) ? $in : new stdClass()];
    } else {
      $out[] = $b;
    }
  }
  return $out;
}

/* Одно событие потока Claude. */
function claude_event(string $ev, array &$st, callable $onText): void {
  $data = '';
  foreach (explode("\n", $ev) as $line) if (strncmp($line, 'data:', 5) === 0) $data .= ltrim(substr($line, 5));
  if ($data === '') return;
  $j = json_decode($data, true);
  if (!is_array($j)) return;
  switch ($j['type'] ?? '') {
    case 'message_start':
      $st['model'] = (string)($j['message']['model'] ?? '');
      $st['usage'] = (array)($j['message']['usage'] ?? []);
      break;
    case 'content_block_start':
      $st['blocks'][(int)$j['index']] = (array)$j['content_block'];
      if (($j['content_block']['type'] ?? '') === 'text') $st['blocks'][(int)$j['index']]['text'] = (string)($j['content_block']['text'] ?? '');
      break;
    case 'content_block_delta':
      $i = (int)$j['index']; $d = (array)($j['delta'] ?? []);
      if (($d['type'] ?? '') === 'text_delta') {
        $st['blocks'][$i]['text'] = ($st['blocks'][$i]['text'] ?? '') . (string)$d['text'];
        $onText((string)$d['text']);
      } elseif (($d['type'] ?? '') === 'input_json_delta') {
        $st['json'][$i] = ($st['json'][$i] ?? '') . (string)($d['partial_json'] ?? '');
      }
      break;
    case 'message_delta':
      $st['stop'] = (string)($j['delta']['stop_reason'] ?? $st['stop']);
      foreach ((array)($j['usage'] ?? []) as $k => $v) $st['usage'][$k] = $v;
      break;
    case 'error':
      $st['err'] = claude_err_kind((string)($j['error']['type'] ?? ''), (string)($j['error']['message'] ?? ''));
      break;
  }
}

/* Ошибка запроса: понятный текст, код и (0.2.1) что не так с рабочей областью ключа. */
function claude_fail(int $http, string $raw): array {
  $ws = claude_ws_issue($http, $raw);
  return ['error' => claude_err_text($http, $raw), 'http' => $http, 'ws_needed' => $ws === 'need', 'ws_bad' => $ws === 'bad'];
}

/* 0.2.1 · 'need' — ключ личный (создан без рабочей области), Claude просит указать её в заголовке anthropic-workspace-id;
   'bad' — указанной рабочей области нет или у ключа нет к ней доступа; '' — дело не в рабочей области. */
function claude_ws_issue(int $http, string $raw): string {
  $j = json_decode($raw, true);
  $msg = (string)($j['error']['message'] ?? '');
  if ($http === 400 && (stripos($msg, 'anthropic-workspace-id is required') !== false || stripos($msg, 'not scoped to a workspace') !== false)) return 'need';
  if (($http === 400 && stripos($msg, 'valid workspace ID') !== false) || ($http === 404 && stripos($msg, 'workspace') !== false)) return 'bad';
  return '';
}

function claude_err_text(int $http, string $raw): string {
  $j = json_decode($raw, true);
  $type = (string)($j['error']['type'] ?? '');
  $msg = (string)($j['error']['message'] ?? '');
  $ws = claude_ws_issue($http, $raw);
  if ($ws === 'need') return 'Ключ личный: Claude просит указать рабочую область (workspace), а сам «Космос» её не выбрал — выберите её у ключа: кнопка «Рабочая область»';
  if ($ws === 'bad') return 'Рабочая область ключа не найдена или у ключа нет к ней доступа — выберите другую у ключа: кнопка «Рабочая область»';
  if ($http === 401) return 'Ключ Claude не подходит — проверьте его или выберите другой ключ';
  if ($http === 403) return 'Claude не пускает этот запрос (страна сервера или права ключа)';
  if ($http === 429) return 'Слишком много запросов к Claude — подождите минуту';
  if ($http === 400 && stripos($msg, 'credit') !== false) return 'На счёте аккаунта этого ключа закончились деньги — пополните или выберите другой ключ';
  if ($http === 529 || $type === 'overloaded_error') return 'Claude сейчас перегружен — попробуйте чуть позже';
  if ($http >= 500) return 'Сбой на стороне Claude (' . $http . ') — попробуйте ещё раз';
  return claude_err_kind($type, $msg) ?: ('Ошибка Claude (' . $http . ')');
}

function claude_err_kind(string $type, string $msg): string {
  if ($type === 'overloaded_error') return 'Claude сейчас перегружен — попробуйте чуть позже';
  if ($type === 'rate_limit_error') return 'Слишком много запросов к Claude — подождите минуту';
  if ($type === 'authentication_error') return 'Ключ Claude не подходит — проверьте его или выберите другой ключ';
  if ($type === 'permission_error') return 'Claude не пускает этот запрос (страна сервера или права ключа)';
  return $msg !== '' ? 'Ошибка Claude: ' . mb_substr($msg, 0, 200) : '';
}

function usage_add(int $keyId, string $model, array $u): void {
  $in = (int)($u['input_tokens'] ?? 0); $out = (int)($u['output_tokens'] ?? 0);
  $cr = (int)($u['cache_read_input_tokens'] ?? 0); $cw = (int)($u['cache_creation_input_tokens'] ?? 0);
  // 0.3.3 · cost_u — сколько стоил этот ответ (lib/price.php), для остатка денег на счёте
  db()->prepare("INSERT INTO usage (day, key_id, model, calls, tokens_in, tokens_out, cache_read, cache_write, cost_u) VALUES (?,?,?,1,?,?,?,?,?)
    ON CONFLICT(day, key_id, model) DO UPDATE SET calls=calls+1, tokens_in=tokens_in+excluded.tokens_in, tokens_out=tokens_out+excluded.tokens_out,
      cache_read=cache_read+excluded.cache_read, cache_write=cache_write+excluded.cache_write, cost_u=cost_u+excluded.cost_u")
    ->execute([date('Y-m-d'), $keyId, $model, $in, $out, $cr, $cw, cost_u($model, $in, $out, $cw, $cr)]);
}

/* Текст из блоков ответа. */
function blocks_text(array $blocks): string {
  $t = '';
  foreach ($blocks as $b) if (($b['type'] ?? '') === 'text') $t .= (string)$b['text'];
  return trim($t);
}
