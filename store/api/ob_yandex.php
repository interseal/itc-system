<?php
// ════════════════════════════════════════════════════════════════════
//  ИТК · ob_yandex.php · перевод через Yandex Cloud
//  ────────────────────────────────────────────────────────────────
//  Два движка:
//    yx_translate()  — Yandex Translate. Дословно, батчами, дёшево.
//                      1 млн симв./мес бесплатно. Для имён вариантов
//                      и характеристик.
//    yx_gpt()        — YandexGPT Lite. Переосмысление. Для заголовков,
//                      где нужен живой русский, а не «Артефакты» из 神器.
//
//  Ключи в api/config.php:
//    define('YC_API_KEY',   'AQVN...');
//    define('YC_FOLDER_ID', 'b1gqhffkd5m6tlhib1tb');
//
//  07.10.2026 · DEEPSEEK В YANDEX AI STUDIO
//    Основной ИИ теперь DeepSeek (Flash) из AI Studio — тот же ключ,
//    та же папка, оплата с баланса облака в рублях.
//    yx_gpt() сначала спрашивает DeepSeek, при отказе — YandexGPT Lite.
//    Варианты и характеристики — yx_ds_list(): DeepSeek пачкой,
//    при сбое — дословный Translate.
//    Модель: deepseek-v4.1-flash с выключенными «раздумьями»
//    (reasoning_effort=none) — проверено ds_check 07.10: 0,6 сек, перевод верный.
//    deepseek-v4-flash без раздумий возвращает иероглифы как есть — не берём.
//    Выбранная модель запоминается в api/.yx_ds_model2 на сутки.
//    Задать вручную: define('YC_DS_MODEL', 'deepseek-v4.1-flash');   // в config.php
// ════════════════════════════════════════════════════════════════════

if (!defined('YX_TRANSLATE_URL')) {
  define('YX_TRANSLATE_URL', 'https://translate.api.cloud.yandex.net/translate/v2/translate');
  define('YX_GPT_URL',       'https://llm.api.cloud.yandex.net/foundationModels/v1/completion');
  define('YX_CHAT_URL',      'https://llm.api.cloud.yandex.net/v1/chat/completions');
  define('YX_MODELS_URLS',   'https://llm.api.cloud.yandex.net/v1/models|https://llm.api.cloud.yandex.net/foundationModels/v1/models');
  define('YX_DS_CACHE',      __DIR__ . '/.yx_ds_model2');
  define('YX_ALT_MODEL',     'qwen3.6-35b-a3b');   // 08.10.2026: запасной переводчик (Alibaba) после DeepSeek
}

/** Ключи на месте? */
function yx_ready() {
  return defined('YC_API_KEY') && YC_API_KEY
      && defined('YC_FOLDER_ID') && YC_FOLDER_ID;
}

/** Общий POST к Yandex Cloud. Возвращает массив или null. */
function yx_post($url, $payload, $timeout = 20) {
  if (!yx_ready()) return null;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Api-Key ' . YC_API_KEY,
    ],
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) { yx_log('curl', $err); return null; }
  $j = json_decode($resp, true);
  if ($code !== 200) {
    yx_log('http'.$code, is_array($j) ? ($j['message'] ?? $resp) : $resp);
    return null;
  }
  return is_array($j) ? $j : null;
}

/** Тихий лог ошибок — чтобы молча не падало в никуда. */
function yx_log($tag, $msg) {
  @error_log('[yandex:'.$tag.'] ' . mb_substr((string)$msg, 0, 300));
}

// ────────────────────────────────────────────────────────────────────
//  YANDEX TRANSLATE
// ────────────────────────────────────────────────────────────────────

/**
 * Батч-перевод. На вход массив строк — на выходе массив в том же порядке.
 * Пустые строки не тратят квоту: уходят пропуском и возвращаются пустыми.
 *
 * Лимит API: 10 000 символов на запрос. Режем сами.
 *
 * @return array|null  null = сервис недоступен (вызывающий откатывается)
 */
function yx_translate(array $texts, $from = 'zh', $to = 'ru') {
  if (!yx_ready()) return null;

  // Индексы непустых — их и переводим
  $idx = [];
  foreach ($texts as $i => $t) {
    if (trim((string)$t) !== '') $idx[$i] = (string)$t;
  }
  if (!$idx) return array_fill(0, count($texts), '');

  $out = array_fill(0, count($texts), '');

  // Бьём на пачки: не больше 10к символов и не больше 100 строк за раз
  $chunk = []; $chunkKeys = []; $len = 0;
  $flush = function() use (&$chunk, &$chunkKeys, &$len, &$out, $from, $to) {
    if (!$chunk) return true;
    $r = yx_post(YX_TRANSLATE_URL, [
      'folderId'           => YC_FOLDER_ID,
      'texts'              => array_values($chunk),
      'sourceLanguageCode' => $from,
      'targetLanguageCode' => $to,
      'format'             => 'PLAIN_TEXT',
    ]);
    if (!$r || !isset($r['translations'])) return false;
    foreach (array_values($chunkKeys) as $n => $key) {
      $out[$key] = $r['translations'][$n]['text'] ?? '';
    }
    $chunk = []; $chunkKeys = []; $len = 0;
    return true;
  };

  foreach ($idx as $key => $t) {
    $tl = mb_strlen($t);
    if ($chunk && ($len + $tl > 9000 || count($chunk) >= 100)) {
      if (!$flush()) return null;
    }
    $chunk[] = $t; $chunkKeys[] = $key; $len += $tl;
  }
  if (!$flush()) return null;

  return $out;
}

/** Одна строка. Удобная обёртка. */
function yx_translate_one($text, $from = 'zh', $to = 'ru') {
  $text = trim((string)$text);
  if ($text === '') return '';
  $r = yx_translate([$text], $from, $to);
  return $r === null ? null : ($r[0] ?? null);
}

// ────────────────────────────────────────────────────────────────────
//  YANDEXGPT LITE
// ────────────────────────────────────────────────────────────────────

/** Убираем обёртки, которые любят добавлять модели. */
function yx_clean_answer($txt) {
  $txt = (string)$txt;
  $txt = preg_replace('/<think>.*?<\/think>/su', '', $txt);   // рассуждения — не в карточку
  $txt = trim(preg_replace('/^```(?:\w+)?|```$/m', '', $txt));
  $txt = preg_replace('/^(вот|перевод|название)[:\s—-]+/iu', '', $txt);
  return trim($txt, " \t\n\r\0\x0B\"'«»");
}

// ────────────────────────────────────────────────────────────────────
//  DEEPSEEK В YANDEX AI STUDIO (07.10.2026)
// ────────────────────────────────────────────────────────────────────

/** Последняя ошибка DeepSeek — для окна «Проверить перевод». */
function yx_ds_err($set = null) {
  static $e = '';
  if ($set !== null) $e = (string)$set;
  return $e;
}

/** Имя модели → полный адрес gpt://папка/модель/latest */
function yx_ds_uri($name) {
  $name = trim((string)$name);
  if ($name === '') return '';
  if (strpos($name, 'gpt://') === 0) return $name;
  return 'gpt://' . YC_FOLDER_ID . '/' . $name . (strpos($name, '/') === false ? '/latest' : '');
}

/** Короткое имя модели для показа: deepseek-v4-flash */
function yx_ds_short($uri) {
  if (preg_match('~gpt://[^/]+/([^/]+)~', (string)$uri, $m)) return $m[1];
  return (string)$uri;
}

/** Один запрос к чату AI Studio (совместим с OpenAI). Ответ или null. */
function yx_ds_call($uri, $system, $user, $temp, $maxTokens, $timeout = 40) {
  $ch = curl_init(YX_CHAT_URL);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_POSTFIELDS     => json_encode([
      'model'       => $uri,
      'messages'    => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user',   'content' => $user],
      ],
      'temperature' => (float)$temp,
      'max_tokens'  => (int)$maxTokens,
      'stream'      => false,
      // Без «раздумий»: быстрее, дешевле, и весь лимит уходит на ответ
      'reasoning_effort' => 'none',
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
      'Content-Type: application/json',
      'Authorization: Api-Key ' . YC_API_KEY,
      'x-folder-id: ' . YC_FOLDER_ID,
      'x-data-logging-enabled: false',
    ],
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp === false) { yx_ds_err('нет связи: ' . $err); yx_log('ds-curl', $err); return null; }
  $j = json_decode($resp, true);
  if ($code !== 200) {
    $msg = is_array($j) ? ($j['error']['message'] ?? $j['message'] ?? $resp) : $resp;
    yx_ds_err('ошибка ' . $code . ': ' . mb_substr((string)$msg, 0, 200));
    yx_log('ds-http' . $code, $msg);
    return null;
  }
  $txt = $j['choices'][0]['message']['content'] ?? null;
  if (!is_string($txt) || trim($txt) === '') { yx_ds_err('пустой ответ'); return null; }
  yx_ds_err('');
  return $txt;
}

/** Все строки со словом deepseek из ответа списка моделей (любой вложенности). */
function yx_ds_scan($node, &$found) {
  if (is_string($node)) {
    if (stripos($node, 'deepseek') !== false && mb_strlen($node) < 200) $found[] = $node;
  } elseif (is_array($node)) {
    foreach ($node as $v) yx_ds_scan($v, $found);
  }
}

/**
 * Какая модель DeepSeek доступна в папке.
 * Порядок: YC_DS_MODEL из config.php → запомненная → список моделей
 * Яндекса → перебор известных имён живой пробой.
 * @return string  полный адрес gpt://… или '' (DeepSeek не найден)
 */
function yx_ds_model($refresh = false) {
  static $mem = null;
  if (!$refresh && $mem !== null) return $mem;
  if (!yx_ready()) return $mem = '';

  if (defined('YC_DS_MODEL') && YC_DS_MODEL) return $mem = yx_ds_uri(YC_DS_MODEL);

  // Запомненная: удачная — сутки, неудачная — 10 минут (чтобы не тормозить каждый запрос)
  if (!$refresh && is_file(YX_DS_CACHE)) {
    $c = json_decode((string)@file_get_contents(YX_DS_CACHE), true);
    if (is_array($c) && isset($c['uri'], $c['t'])) {
      $ttl = $c['uri'] !== '' ? 86400 : 120;   // неудача — пробуем снова через 2 минуты
      if ($c['uri'] === '' && !empty($c['err'])) yx_ds_err($c['err']);
      if (time() - (int)$c['t'] < $ttl) return $mem = (string)$c['uri'];
    }
  }

  // 1) Спрашиваем у Яндекса список моделей
  $found = [];
  foreach (explode('|', YX_MODELS_URLS) as $url) {
    $ch = curl_init($url . (strpos($url, '?') === false ? '?' : '&') . 'folderId=' . urlencode(YC_FOLDER_ID));
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_HTTPHEADER => ['Authorization: Api-Key ' . YC_API_KEY, 'x-folder-id: ' . YC_FOLDER_ID],
    ]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r !== false && $code === 200) yx_ds_scan(json_decode($r, true), $found);
    if ($found) break;
  }
  // Из найденного — только чат-модели (без vl/картинок), Flash вперёд, новее вперёд
  $cand = [];
  foreach (array_unique($found) as $f) {
    if (preg_match('/vl|vision|embed|ocr|speech|realtime/i', $f)) continue;
    if (strpos($f, 'gpt://') !== 0 && !preg_match('/^deepseek[\w.\-]*$/i', $f)) continue;
    if (!preg_match('/\d/', $f)) continue;   // «DeepSeek» — подпись в списке, а не модель
    $cand[] = $f;
  }
  usort($cand, function ($a, $b) {
    $fa = stripos($a, 'flash') !== false; $fb = stripos($b, 'flash') !== false;
    if ($fa !== $fb) return $fa ? -1 : 1;
    return strnatcasecmp($b, $a);
  });

  // Проверенная модель — первой; остальные из списка — запасом
  array_unshift($cand, 'deepseek-v4.1-flash');

  // Пробуем по очереди живым коротким запросом
  $uri = '';
  // Проба настоящим переводом: модель, вернувшая иероглифы, не годится
  $seen = []; $errs = [];
  foreach ($cand as $c) {
    $u = yx_ds_uri($c);
    if (isset($seen[$u])) continue; $seen[$u] = 1;
    if (count($seen) > 4) break;
    $a = yx_ds_call($u, 'Переведи на русский. Верни только перевод.', '螺丝刀', 0.1, 30, 20);
    if ($a !== null && !preg_match('/[\x{4e00}-\x{9fff}]/u', $a)) { $uri = $u; break; }
    $errs[] = yx_ds_short($u) . ': ' . ($a === null ? yx_ds_err() : 'вернула иероглифы');
  }
  // Причину пишем по ПЕРВОЙ (главной) модели — именно она нам нужна
  $err = $uri === '' ? implode(' | ', array_slice($errs, 0, 2)) : '';
  if ($err !== '') yx_ds_err($err);

  @file_put_contents(YX_DS_CACHE, json_encode(['uri' => $uri, 't' => time(), 'err' => $err], JSON_UNESCAPED_UNICODE));
  return $mem = $uri;
}

/**
 * Запрос к DeepSeek. null = не ответил (вызывающий откатывается).
 * После двух сбоев подряд в одном запросе страницы больше не пробуем —
 * чтобы массовый перевод не висел на таймаутах.
 */
/**
 * 08.10.2026: запасной переводчик Qwen3.6 (Alibaba) в той же папке Яндекса.
 * Нужен, пока DeepSeek недоступен (авария Яндекса ru-central-b, обращение
 * NK684926). После двух сбоев подряд в одном запросе — больше не пробуем.
 */
function yx_qwen($system, $user, $temp = 0.3, $maxTokens = 200) {
  static $fails = 0;
  if (!yx_ready() || $fails >= 2) return null;
  $keepErr = yx_ds_err();                       // ошибку DeepSeek не затираем — она главная
  $txt = yx_ds_call(yx_ds_uri(YX_ALT_MODEL), $system, $user, $temp, (int)$maxTokens + 50, 40);
  yx_ds_err($keepErr);
  if ($txt === null) { $fails++; return null; }
  $fails = 0;
  $txt = yx_clean_answer($txt);
  if ($txt === '') return null;
  $GLOBALS['yx_qwen_used'] = 1;
  return $txt;
}

function yx_ds($system, $user, $temp = 0.3, $maxTokens = 200) {
  static $fails = 0;
  // №848: любой отказ DeepSeek помечаем — товар потом встанет в очередь
  // и будет переведён DeepSeek заново, когда тот ответит.
  // 08.10.2026: вместо отказа сначала пробуем запасной Qwen; если и он молчит —
  // null, и дальше по цепочке пойдёт YandexGPT. Отметка «запасной» остаётся.
  if (!yx_ready()) return null;
  if ($fails >= 2) { $GLOBALS['yx_ds_fallback'] = 1; return yx_qwen($system, $user, $temp, $maxTokens); }
  $uri = yx_ds_model();
  if ($uri === '') {
    if (yx_ds_err() === '') yx_ds_err('модель DeepSeek в папке не найдена');
    $GLOBALS['yx_ds_fallback'] = 1;
    return yx_qwen($system, $user, $temp, $maxTokens);
  }

  $txt = yx_ds_call($uri, $system, $user, $temp, (int)$maxTokens + 50);
  if ($txt === null) {
    $fails++; if ($fails >= 2) $GLOBALS['yx_ds_dead'] = 1;
    $GLOBALS['yx_ds_fallback'] = 1;
    return yx_qwen($system, $user, $temp, $maxTokens);
  }
  $fails = 0;
  $txt = yx_clean_answer($txt);
  return $txt === '' ? null : $txt;
}

/**
 * Пачка коротких строк (варианты, характеристики) через DeepSeek.
 * Ключи массива сохраняются. Строки, которые модель не вернула, в ответе
 * отсутствуют — их доделывает вызывающий (дословным Translate).
 * @return array  [ключ => перевод]
 */
function yx_ds_list(array $lines, $ctx = '', $what = 'варианты товара') {
  $out = [];
  if (!$lines || !yx_ready()) return $out;

  $sys = "Ты переводчик российского маркетплейса ИТК (товары из Китая, площадка 1688).\n".
         "Тебе дают нумерованный список: {$what}. Переведи КАЖДУЮ строку на русский.\n".
         "ПРАВИЛА:\n".
         "- Ответ: только нумерованный список, та же нумерация, ровно одна строка на номер.\n".
         "- Числа, размеры и единицы (см, мм, кг, шт) сохраняй как есть.\n".
         "- Используй принятые в русской торговле термины (内六角 — шестигранник, 螺丝刀 — отвёртка, 仿真 — искусственный).\n".
         "- Не транслитерируй иероглифы, иероглифов в ответе быть не должно.\n".
         "- Убирай рекламный мусор продавца. Никаких пояснений.";
  if ($ctx !== '') $sys .= "\nТовар (для точности терминов): " . mb_substr($ctx, 0, 200);

  foreach (array_chunk($lines, 60, true) as $part) {   // 30 характеристик × 2 строки — одним запросом
    $keys = array_keys($part);
    $num = [];
    foreach (array_values($part) as $i => $t) $num[] = ($i + 1) . '. ' . $t;

    $txt = yx_ds($sys, implode("\n", $num), 0.2, min(3000, 60 * count($part) + 200));
    if ($txt === null) continue;

    $got = [];
    foreach (preg_split('/\r?\n/', $txt) as $l) {
      if (preg_match('/^\s*(\d+)\s*[\.\)]\s*(.+)$/u', $l, $m)) {
        $v = trim($m[2], " \t\"'«»");
        if ($v !== '' && !preg_match('/[\x{4e00}-\x{9fff}]/u', $v)) $got[(int)$m[1]] = $v;
      }
    }
    // Нумерация сбилась (строк меньше или больше) — пачку не берём целиком:
    // иначе переводы уедут не к своим строкам.
    if (count($got) !== count($part) || max(array_keys($got)) !== count($part)) { $GLOBALS['yx_ds_fallback'] = 1; continue; }
    foreach ($keys as $i => $key) $out[$key] = $got[$i + 1];
  }
  return $out;
}

/**
 * Главный ИИ-вызов магазина. Сначала DeepSeek, при отказе — YandexGPT Lite.
 * Все прежние места (названия, описания, отзывы, категории, поиск)
 * ходят сюда — менять их не пришлось.
 */
function yx_gpt($system, $user, $temp = 0.3, $maxTokens = 200) {
  if (!yx_ready()) return null;
  $r = yx_ds($system, $user, $temp, $maxTokens);
  // Отказ («не могу…») в карточку не пускаем. Короткие ответы (ОК, Да) — не отказ.
  if ($r !== null && !(mb_strlen($r) >= 3 && function_exists('itc_bad_ai_text') && itc_bad_ai_text($r))) return $r;
  return yx_gpt_lite($system, $user, $temp, $maxTokens);
}

/**
 * Запрос к YandexGPT Lite (теперь запасной).
 * @return string|null  null = недоступен / нет роли ai.languageModels.user
 */
function yx_gpt_lite($system, $user, $temp = 0.3, $maxTokens = 200) {
  if (!yx_ready()) return null;
  $r = yx_post(YX_GPT_URL, [
    'modelUri' => 'gpt://' . YC_FOLDER_ID . '/yandexgpt-lite/latest',
    'completionOptions' => [
      'stream'      => false,
      'temperature' => $temp,
      'maxTokens'   => (string)$maxTokens,
    ],
    'messages' => [
      ['role' => 'system', 'text' => $system],
      ['role' => 'user',   'text' => $user],
    ],
  ], 30);

  $txt = $r['result']['alternatives'][0]['message']['text'] ?? null;
  if ($txt === null) return null;
  // GPT любит обрамлять кавычками и добавлять «Вот перевод:» — чистим
  $txt = trim($txt);
  $txt = preg_replace('/^(вот|перевод|название)[:\s—-]+/iu', '', $txt);
  $txt = trim($txt, " \t\n\r\0\x0B\"'«»");
  return $txt;
}

// ────────────────────────────────────────────────────────────────────
//  ГЛАВНАЯ ФУНКЦИЯ — замена ai_translate_field() на Yandex
// ────────────────────────────────────────────────────────────────────

/**
 * Тот же контракт, что у старой ai_translate_field():
 *   $kind: name | description | review | specs | plain
 * Возвращает строку или null (тогда вызывающий откатывается на MyMemory).
 *
 * Логика: name/description/review — через GPT (нужно переосмысление).
 *         specs/plain — через Translate (дословно, дёшево, быстро).
 *         Если GPT недоступен (нет роли) — молча падаем на Translate.
 */
function yx_translate_field($text, $kind = 'plain', $ctx = '') {
  $text = trim((string)$text);
  if ($text === '') return '';
  if (!yx_ready()) return null;

  // Дословные поля (07.10.2026): DeepSeek точнее в терминах, чем Translate.
  // Не ответил — дословный Translate, как раньше.
  if ($kind === 'specs' || $kind === 'plain') {
    $sys = "Ты переводчик российского маркетплейса ИТК (товары из Китая, площадка 1688). ".
           "Переведи текст на русский точно, без пересказа. Сохрани структуру строк и нумерацию, ".
           "числа и единицы измерения — как есть. Используй принятые в русской торговле термины. ".
           "Иероглифов в ответе быть не должно. Верни ТОЛЬКО перевод, без пояснений.";
    if ($ctx !== '') $sys .= "\nКонтекст: " . mb_substr($ctx, 0, 300);
    $r = yx_ds($sys, $text, 0.2, min(3000, mb_strlen($text) * 3 + 100));
    if ($r !== null && $r !== '') return $r;
    return yx_translate_one($text);
  }

  $base = "Ты контент-менеджер российского маркетплейса ИТК (мебель и товары для дома, ".
          "прямые поставки из Китая). Тебе дают текст с китайской площадки (1688 / Pinduoduo / Taobao). ".
          "Переведи на русский.\n".
          "ГЛАВНОЕ: не переводи дословно — переосмысли как живой русский копирайтер. ".
          "Убирай китайский рекламный мусор (爆款, 包邮, 工厂直销, 秒杀, 限时, 现货速发). ".
          "Не выдумывай фактов, которых нет в исходнике. ".
          "Верни ТОЛЬКО результат, без пояснений и кавычек.";

  switch ($kind) {
    case 'name':
      $sys = $base."\nЗАДАЧА: короткое продающее НАЗВАНИЕ товара в стиле Wildberries/Ozon: ".
             "тип товара + материал + стиль + ключевая особенность. Без иероглифов, до 90 символов.";
      $temp = 0.3; $max = 120; break;
    case 'description':
      $sys = $base."\nЗАДАЧА: ОПИСАНИЕ товара, 2–5 предложений, продающее, по делу. ".
             "Пиши о материале, размерах, применении, преимуществах — только то, что следует из исходника.";
      $temp = 0.4; $max = 700; break;
    case 'review':
      $sys = $base."\nЗАДАЧА: перевести ОТЗЫВ покупателя. Сохрани живую разговорную интонацию ".
             "и эмоцию автора, не приукрашивай и не смягчай критику.";
      $temp = 0.5; $max = 500; break;
    default:
      $sys = $base."\nЗАДАЧА: аккуратно перевести текст на русский, сохранив смысл.";
      $temp = 0.3; $max = 700;
  }
  if ($ctx !== '') $sys .= "\nКонтекст товара (для точности терминов): " . mb_substr($ctx, 0, 200);

  $r = yx_gpt($sys, $text, $temp, $max);

  // GPT не сработал (нет роли ai.languageModels.user / кончился грант)
  // — не теряем перевод совсем, отдаём дословный
  if ($r === null || $r === '') {
    yx_log('gpt-fallback', 'kind='.$kind.' → Translate');
    return yx_translate_one($text);
  }
  return $r;
}

// ────────────────────────────────────────────────────────────────────
//  ДИАГНОСТИКА — кейс ob_yx_env
// ────────────────────────────────────────────────────────────────────

/** Проверка обоих движков живым запросом. */
function yx_env() {
  $env = [
    'key_set'    => defined('YC_API_KEY') && YC_API_KEY ? 'да' : 'НЕТ',
    'key_prefix' => defined('YC_API_KEY') && YC_API_KEY ? mb_substr(YC_API_KEY, 0, 6).'…' : '—',
    'folder_id'  => defined('YC_FOLDER_ID') ? YC_FOLDER_ID : '—',
  ];
  if (!yx_ready()) {
    $env['itog'] = 'Ключи не прописаны в config.php';
    return $env;
  }

  // Translate
  $t0 = microtime(true);
  $tr = yx_translate_one('简易多层家用鞋架');
  $env['translate'] = $tr === null
    ? '✗ не отвечает (нет роли ai.translate.user?)'
    : '✓ «' . $tr . '»';
  $env['translate_ms'] = round((microtime(true) - $t0) * 1000);

  // DeepSeek (07.10.2026). В строке «gpt» окна проверки — теперь он.
  $t0 = microtime(true);
  $uri = yx_ds_model(true);              // при проверке ищем модель заново
  $d = $uri !== '' ? yx_ds_call($uri, 'Ответь одним словом: ОК', 'Проверка связи', 0.1, 20, 25) : null;
  $dsOk = $d !== null;
  $env['ds_model'] = $uri !== '' ? yx_ds_short($uri) : '—';
  if ($dsOk) {
    $env['gpt'] = '✓ DeepSeek (' . $env['ds_model'] . ') «' . yx_clean_answer($d) . '»';
  } else {
    // DeepSeek молчит — смотрим запасных: сначала Qwen, потом YandexGPT Lite
    $q = yx_qwen('Переведи на русский. Верни только перевод.', '螺丝刀', 0.1, 30);
    $g = $q === null ? yx_gpt_lite('Ответь одним словом: ОК', 'Проверка связи', 0.1, 10) : null;
    $env['gpt'] = '✗ DeepSeek не отвечает (' . (yx_ds_err() ?: 'модель не найдена') . ')' .
                  ($q !== null ? '. Переводит запасной Qwen: 螺丝刀 → «' . $q . '»'
                               : ($g !== null ? '. Qwen молчит, работает YandexGPT Lite.' : '. Qwen и YandexGPT Lite тоже молчат.'));
  }
  $env['gpt_ms'] = round((microtime(true) - $t0) * 1000);

  // Сравнение движков на живом примере
  $probe = '神器多层家用简易鞋架经济型防尘收纳鞋柜';
  $env['probe_cn']        = $probe;
  $env['probe_translate'] = yx_translate_one($probe);
  $env['probe_gpt']       = yx_translate_field($probe, 'name');

  // Проба терминов: варианты пачкой
  if (true) {   // 08.10: проба вариантов — DeepSeek или, если он молчит, Qwen
    $l = yx_ds_list([1 => '内六角扳手9件套', 2 => '十字螺丝刀 6mm', 3 => '仿真绿植藤条 2米'],
                    'Набор инструментов', 'названия вариантов товара');
    $env['probe_list'] = $l ? implode(' | ', $l) : '—';
  }

  $ok = strpos($env['translate'], '✓') === 0;
  if ($dsOk) {
    $env['itog'] = 'DeepSeek работает: названия, описания, варианты и характеристики идут через него. ' .
                   ($ok ? 'Переводчик в запасе.' : 'Запасной переводчик не отвечает — проверь роль ai.translate.user.') .
                   (!empty($env['probe_list']) && $env['probe_list'] !== '—' ? "\nВарианты: " . $env['probe_list'] : '');
  } else {
    $qOk = strpos($env['gpt'], 'запасной Qwen') !== false;
    $env['itog'] = ($qOk ? 'DeepSeek не отвечает — новые карточки переводит запасной Qwen (Alibaba) и ставит в очередь на DeepSeek. '
                         : 'DeepSeek и Qwen не отвечают — перевод идёт по-старому (YandexGPT Lite и переводчик). ') .
                   (!empty($env['probe_list']) && $env['probe_list'] !== '—' ? "\nВарианты: " . $env['probe_list'] : '');
  }
  return $env;
}
