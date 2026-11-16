<?php
declare(strict_types=1);
// ==== КОСМОС · lib/gw.php · шлюз для магазина (0.2.0) ====
// «Космос» в Экосистеме и CRM выглядит и работает как «Фил»: те же сделки, клиенты, задачи, голос.
// Всю работу с данными CRM делает магазин (api/kosmos_flow.php), а думать он просит Claude через этот шлюз:
//   POST /api.php?a=gw   заголовок X-Kosmos-GW: <ключ шлюза>   тело {a: ask|status|skills, ...}
// Ключ шлюза создаёт магазин и передаёт агенту в ответ на ежеминутный отчёт; агент кладёт его в /etc/kosmos/gw.key.
// Ключи Claude отсюда никуда не уходят: магазин получает только текст ответа.
// Доступ сотрудников, лимиты и расход — те же, что в настройках «Космоса» (кто спросил, магазин сообщает в поле who).

const GW_DYN = "\n\n=== ПО ЭТОМУ ЗАПРОСУ ===";   // 0.5.2 (№626) · как AG_DYN в store/api/flow.php
/* где начинается меняющаяся часть правил (0 — метки нет или постоянная часть пустая) */
function gw_sys_cut(string $sys): int { $p = strpos($sys, GW_DYN); return $p === false || trim(substr($sys, 0, $p)) === '' ? 0 : (int)$p; }
const GW_IMG_MAX = 10; const GW_IMG_TOT = 26000000;   // 0.5.0 (№624)
/* 0.5.3 (№627) · КЕШ СНИМКОВ. Раньше магазин слал все снимки (до 10 × ~0,7 МБ) из России в Сингапур на КАЖДОМ шаге ответа.
   Теперь снимок, пришедший целиком, кладём сюда по отпечатку (sha256 его base64); дальше магазин шлёт только {"media_type","sha"}.
   Снимка нет (стёрт, другой сервер) — ответ 409 need_images, магазин пришлёт эти снимки целиком. Храним сутки. */
const GW_IMG_TTL = 86400;
function gw_img_dir(): string { $d = dirname((string)cfg('db')) . '/imgcache'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }
function gw_img_sha($sha): string { return is_string($sha) && preg_match('~^[a-f0-9]{64}$~', $sha) ? $sha : ''; }
/* base64 снимка: пришёл целиком — запомнить; пришёл отпечаток — взять из кеша ('' — нет) */
function gw_img_data(array $im): string {
  $d = (string)($im['data'] ?? ''); $dir = gw_img_dir();
  if ($d !== '') {
    if (strlen($d) <= 7000000 && preg_match('~^[A-Za-z0-9+/=]+$~', substr($d, 0, 200))) {
      $f = $dir . '/' . hash('sha256', $d); if (!is_file($f)) @file_put_contents($f, $d, LOCK_EX); else @touch($f);
      if (mt_rand(1, 50) === 1) foreach (glob($dir . '/*') ?: [] as $o) if (is_file($o) && filemtime($o) < time() - GW_IMG_TTL) @unlink($o);
    }
    return $d;
  }
  $sha = gw_img_sha($im['sha'] ?? null); if ($sha === '') return '';
  $f = $dir . '/' . $sha; if (!is_file($f)) return '';
  @touch($f); return (string)@file_get_contents($f);
}
/* каких снимков по отпечатку здесь нет */
function gw_img_need(array $ims): array {
  $o = [];
  foreach (array_slice($ims, 0, GW_IMG_MAX) as $im) {
    if (!is_array($im) || (string)($im['data'] ?? '') !== '') continue;
    $sha = gw_img_sha($im['sha'] ?? null); if ($sha !== '' && !is_file(gw_img_dir() . '/' . $sha)) $o[] = $sha;
  }
  return $o;
}

function gw_handle(): void {
  $want = '';
  $f = (string)cfg('gw_key_file');
  if ($f !== '' && is_file($f)) $want = trim((string)@file_get_contents($f));
  $got = (string)($_SERVER['HTTP_X_KOSMOS_GW'] ?? '');
  if ($want === '' || strlen($got) < 32 || !hash_equals($want, $got)) fail('Магазин и сервер «Космоса» ещё не связаны — подождите минуту', 403);
  $in = body();
  $a = (string)($in['a'] ?? '');
  if ($a === 'status') jout(gw_status());
  if ($a === 'ask') gw_ask($in);
  if ($a === 'dev') gw_dev($in);
  if ($a === 'media') gw_media($in);     // 0.4.4 · PDF, фото, видео для Фила и «Космоса»         // 0.4.0 · поручения разработчику от руководителя из магазина (Фил — на DeepSeek)
  if ($a === 'skills') gw_skills($in);   // 0.3.1 · навыки (главное место — здесь, копия — в магазине)
  fail('Неизвестное действие шлюза', 404);
}

function gw_status(): array {
  [$k, $kv] = key_active();
  $n = (int)db()->query('SELECT COUNT(*) FROM keys WHERE deleted=0')->fetchColumn();
  $q = db()->prepare('SELECT COALESCE(SUM(calls),0) c, COALESCE(SUM(tokens_in),0) i, COALESCE(SUM(tokens_out),0) o, COALESCE(SUM(cache_read),0) r FROM usage WHERE day=?');
  $q->execute([date('Y-m-d')]); $u = $q->fetch() ?: [];
  return ['ok' => true, 'ver' => KOSMOS_VERSION, 'keys' => $n, 'active' => $k ? (string)$k['name'] : '',
          'ready' => key_problem($k, $kv) === '', 'problem' => key_problem($k, $kv), 'auto_switch' => setting('auto_switch', '1') === '1',
          'models' => MODEL_LIST, 'today' => ['calls' => (int)($u['c'] ?? 0), 'in' => (int)($u['i'] ?? 0), 'out' => (int)($u['o'] ?? 0), 'cache' => (int)($u['r'] ?? 0)]];
}

/* Кто спрашивает (магазин уже проверил пропуск) — заносим в список сотрудников «Космоса» и проверяем доступ и лимиты. */
function gw_who(array $w): array {
  $phone = str($w['phone'] ?? '', 40);
  if ($phone === '') fail('Не указан сотрудник', 400);
  $u = ['phone' => $phone, 'name' => str($w['name'] ?? '', 120), 'role' => str($w['role'] ?? '', 40), 'boss' => !empty($w['boss'])];
  $d = db();
  $d->prepare("INSERT INTO users (phone, name, role, boss, first_at, last_at) VALUES (?,?,?,?,?,?)
    ON CONFLICT(phone) DO UPDATE SET name=excluded.name, role=excluded.role, boss=excluded.boss, last_at=excluded.last_at")
    ->execute([$u['phone'], $u['name'], $u['role'], $u['boss'] ? 1 : 0, now(), now()]);
  $q = $d->prepare('SELECT allow FROM users WHERE phone=?'); $q->execute([$phone]);
  if (!$u['boss'] && !access_rule($u['role'], (string)$q->fetchColumn())) fail('Доступ к «Космосу» выдаёт руководитель — он уже видит вашу просьбу в настройках «Космоса»', 403);
  // защита от случайных больших расходов: одна команда — это несколько обращений (Космос смотрит данные шагами)
  $t = time();
  $d->prepare('DELETE FROM rate WHERE at < ?')->execute([$t - 60]);
  $q = $d->prepare('SELECT COUNT(*) FROM rate WHERE phone=?'); $q->execute(['gw:' . $phone]);
  if ((int)$q->fetchColumn() >= max(40, (int)cfg('msg_per_min') * 3)) fail(   /* 0.5.0 (№624): было 20 — 60 шагов разговора идут быстро */ 'Слишком много запросов за минуту — подождите немного', 429);
  $lim = (int)setting('daily_limit', '150');
  if (!$u['boss'] && $lim > 0) {
    $q = $d->prepare('SELECT COALESCE(SUM(calls),0) FROM gw_use WHERE day=? AND phone=?'); $q->execute([date('Y-m-d'), $phone]);
    if ((int)$q->fetchColumn() >= $lim * 10) fail('На сегодня лимит «Космоса» исчерпан. Лимит меняет руководитель в настройках «Космоса»', 429);
  }
  $d->prepare('INSERT INTO rate (phone, at) VALUES (?,?)')->execute(['gw:' . $phone, $t]);
  return $u;
}

/* 0.5.6 (№630) · запрос к Claude для шлюза: большой max_tokens — потоком (curl до 600 с, без простоя), сбой связи/перегрузка — ещё раз */
const GW_TOK_MAX = 64000; const GW_TOK_STREAM = 16000;
function gw_claude(array $payload, callable $onSw): array {
  if ((int)($payload['max_tokens'] ?? 0) <= GW_TOK_STREAM) return claude_call($payload, null, $onSw);
  $res = [];
  for ($i = 0; $i < 2; $i++) {
    $res = claude_call($payload, function ($t) {}, $onSw);
    if (empty($res['error']) || !in_array((int)($res['http'] ?? 0), [0, 500, 502, 503, 504, 529], true)) break;
    usleep(1500000);
  }
  return $res;
}
/* 0.5.8 (№632) · УРОВЕНЬ СЛОЖНОСТИ — глубина размышлений Claude (extended thinking). Бюджет — сколько токенов модель может
   потратить на размышления до ответа (берёт столько, сколько нужно, не больше). Размышления — это выходные токены: входят в расход
   (usage.output_tokens) и в учёт денег как обычно. Ход размышлений в ответ магазину не попадает — только текст (blocks_text).
   «Максимум» 32000: больше не уложится в 15 минут сервера (nginx/PHP 900 с); у Haiku «Максимум» — как «Глубоко». */
const GW_THINK = ['fast' => 0, 'norm' => 4000, 'deep' => 16000, 'max' => 32000];
const GW_EFFORT = ['norm' => 'medium', 'deep' => 'high', 'max' => 'max'];   // запасной путь: модель без budget_tokens (adaptive + effort)
const GW_THINK_ANS = 8000;   // запас на сам ответ сверх размышлений
function gw_think_level(string $model, $lv): string {
  $lv = is_string($lv) && isset(GW_THINK[$lv]) ? $lv : 'fast';
  return ($lv === 'max' && stripos($model, 'haiku') !== false) ? 'deep' : $lv;
}
/* Размышления в запрос: бюджет меньше max_tokens (поднимаем запас), temperature/top_k с размышлениями нельзя — убираем.
   $how: 'budget' — {type: enabled, budget_tokens}, 'effort' — {type: adaptive} + output_config.effort, '' — без размышлений. */
function gw_think_put(array $p, string $lv, string $how): array {
  unset($p['thinking'], $p['output_config']);
  $b = GW_THINK[$lv] ?? 0;
  if ($b <= 0 || $how === '') return $p;
  unset($p['temperature'], $p['top_k'], $p['top_p']);
  if ($how === 'budget') $p['max_tokens'] = min(GW_TOK_MAX, $b + max(GW_THINK_ANS, (int)$p['max_tokens']));   // запасные пути запас не поднимают
  if ($how === 'budget') { if ($b >= $p['max_tokens']) $b = (int)$p['max_tokens'] - GW_THINK_ANS; $p['thinking'] = ['type' => 'enabled', 'budget_tokens' => max(1024, $b)]; }
  else { $p['thinking'] = ['type' => 'adaptive']; $p['output_config'] = ['effort' => GW_EFFORT[$lv] ?? 'medium']; }
  return $p;
}
/* 0.5.9 (№633) · способ размышлений — по модели (проверено по нашему ключу 16.11.2026): budget_tokens ({type: enabled}) принимают
   только Claude 4.5/4.6 (opus-4-5, opus-4-6, sonnet-4-6, haiku-4-5; opus-4-5 и haiku-4-5 adaptive не берут вовсе). Все новые — 4.7, 4.8, 5, 5.5,
   Fable — только adaptive + effort: раньше их первый запрос с размышлениями всегда получал 400 и шёл второй раз. Ошибётся — запасной путь ниже. */
function gw_think_how(string $model): string {
  return preg_match('~^claude-(opus|sonnet|haiku)-4-[56](-|$)~', $model) ? 'budget' : 'effort';
}
function gw_ask(array $in): void {
  // 0.5.3 (№627) · снимок по отпечатку, которого здесь нет — просим целиком (до учёта обращения, чтобы повтор не считался дважды)
  $need = gw_img_need((array)($in['images'] ?? []));
  if ($need) jout(['ok' => false, 'error' => 'нужны снимки целиком', 'need_images' => $need, 'img_cache' => 1], 409);
  $u = gw_who((array)($in['who'] ?? []));
  $sys = (string)($in['system'] ?? '');
  $user = (string)($in['user'] ?? '');
  if (trim($user) === '') fail('Пустой вопрос');
  if (strlen($sys) + strlen($user) > 1500000) fail('Слишком большой запрос');
  $model = str($in['model'] ?? '', 60);
  if (!preg_match('~^claude-[a-z0-9.\-]{3,50}$~', $model)) $model = model('deep');
  // 0.5.6 (№630) · предел ответа поднят до 64000 (было 32000) — столько пишут нынешние модели Claude; модель с меньшим пределом
  // ответит 400 — тогда тот же запрос ещё раз с 32000 (ниже). Платится только написанное, запас денег не тратит.
  $max = max(256, min(GW_TOK_MAX, (int)($in['max_tokens'] ?? 4000)));
  // 0.4.6 · фото к вопросу: Claude видит сами снимки, а не только распознанный текст · 0.5.0 (№624): до 10 снимков (было 4), каждый до ~5 МБ,
  // все вместе — до 26 МБ в base64 (предел запроса Claude — 32 МБ; предел тела у nginx/PHP «Космоса» — 64 МБ)
  $content = $user; $imgs = []; $tot = 0;
  foreach (array_slice((array)($in['images'] ?? []), 0, GW_IMG_MAX) as $im) {
    $mt = (string)($im['media_type'] ?? ''); $d = gw_img_data((array)$im);
    if (!in_array($mt, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $d === '' || strlen($d) > 7000000 || !preg_match('~^[A-Za-z0-9+/=]+$~', substr($d, 0, 200))) continue;
    if ($tot + strlen($d) > GW_IMG_TOT) break; $tot += strlen($d);
    $imgs[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mt, 'data' => $d]];
  }
  if ($imgs) {
    // 0.5.0 (№624): снимки теперь идут на каждом шаге разговора — ставим их в кеш Claude вместе с правилами: следующие шаги платят ~10% за снимки
    $imgs[count($imgs) - 1]['cache_control'] = ['type' => 'ephemeral'];
    $content = array_merge($imgs, [['type' => 'text', 'text' => $user]]);
  }
  $payload = ['model' => $model, 'max_tokens' => $max, 'messages' => [['role' => 'user', 'content' => $content]]];
  // большая постоянная часть (правила, инструменты, памятка) — в кеш Claude: следующие шаги той же команды дешевле и быстрее
  if (trim($sys) !== '') $payload['system'] = [['type' => 'text', 'text' => $sys, 'cache_control' => ['type' => 'ephemeral']]];
  // 0.5.2 (№626) · магазин ставит перед меняющейся частью правил (время, дела напарника, навыки) метку GW_DYN — постоянная часть
  // идёт отдельным блоком со своей меткой кеша: её Claude берёт из кеша и в следующих командах, а не только в шагах одной команды
  if (($cut = gw_sys_cut($sys)) > 0) $payload['system'] = [['type' => 'text', 'text' => substr($sys, 0, $cut), 'cache_control' => ['type' => 'ephemeral']],
                                                        ['type' => 'text', 'text' => substr($sys, $cut), 'cache_control' => ['type' => 'ephemeral']]];
  // 0.5.8 (№632) · уровень сложности: размышления; «Максимум» — поток до 14 мин (claude_once), остальное — как было
  $think = gw_think_level($model, $in['think'] ?? ''); $thinkOff = '';
  $how = gw_think_how($model);
  $payload = gw_think_put($payload, $think, $how); $max = (int)$payload['max_tokens'];
  $GLOBALS['GW_STREAM_TMO'] = $think === 'max' ? 840 : 600;
  $switched = '';
  $onSw = function ($old, $new) use (&$switched) { $switched = 'Ключ «' . $old . '» перестал работать — переключился на «' . $new . '»'; };
  // 0.5.6 (№630) · длинный ответ (больше 16000 токенов запаса, Opus пишет его минутами) — потоком: соединение не простаивает
  // и не рвётся по пути; ответ собирается целиком, магазин получает его как раньше. Короткий — обычным запросом (с повторами).
  $res = gw_claude($payload, $onSw);
  if (!empty($res['error']) && (int)($res['http'] ?? 0) === 400 && $max > 32000) {   // модель не берёт 64000
    $payload['max_tokens'] = $max = 32000;
    if (isset($payload['thinking']['budget_tokens']) && $payload['thinking']['budget_tokens'] > $max - GW_THINK_ANS) $payload['thinking']['budget_tokens'] = $max - GW_THINK_ANS;
    $res = gw_claude($payload, $onSw);
  }
  // 0.5.8 (№632) · модель не приняла размышления (400) — тот же вопрос другим способом (0.5.9: budget ↔ effort), потом без размышлений:
  // без ответа Босс не остаётся
  if (!empty($res['error']) && (int)($res['http'] ?? 0) === 400 && isset($payload['thinking'])) {
    $why = mb_substr((string)$res['error'], 0, 300); $how2 = $how === 'budget' ? 'effort' : 'budget';
    $payload['max_tokens'] = $max; $payload = gw_think_put($payload, $think, $how2); $res = gw_claude($payload, $onSw);
    if (!empty($res['error']) && (int)($res['http'] ?? 0) === 400) { $thinkOff = $why . ' · ' . $how2 . ': ' . mb_substr((string)$res['error'], 0, 200); $payload = gw_think_put($payload, $think, ''); $res = gw_claude($payload, $onSw); }
    // через effort получилось — это не отказ (think_off пустой), но причину всё равно пишем в журнал
    log_add($u['phone'], $thinkOff !== '' ? 'gw_think_off' : 'gw_think_effort', ['model' => $model, 'think' => $think, 'error' => $thinkOff !== '' ? $thinkOff : $why]);
  }
  $kid = (int)($res['key_id'] ?? 0);
  if (!empty($res['error'])) {
    log_add($u['phone'], 'gw_error', ['model' => $model, 'error' => mb_substr((string)$res['error'], 0, 300)]);
    jout(['ok' => false, 'error' => (string)$res['error'], 'switched' => $switched], 502);
  }
  $us = (array)($res['usage'] ?? []);
  db()->prepare('INSERT INTO gw_use (day, phone, calls, tokens_in, tokens_out) VALUES (?,?,1,?,?)
    ON CONFLICT(day, phone) DO UPDATE SET calls=calls+1, tokens_in=tokens_in+excluded.tokens_in, tokens_out=tokens_out+excluded.tokens_out')
    ->execute([date('Y-m-d'), $u['phone'], (int)($us['input_tokens'] ?? 0) + (int)($us['cache_read_input_tokens'] ?? 0) + (int)($us['cache_creation_input_tokens'] ?? 0), (int)($us['output_tokens'] ?? 0)]);
  $kn = '';
  if ($kid) { $q = db()->prepare('SELECT name FROM keys WHERE id=?'); $q->execute([$kid]); $kn = (string)$q->fetchColumn(); }
  jout(['ok' => true, 'text' => blocks_text((array)($res['content'] ?? [])), 'model' => (string)($res['model'] ?? $model),
        'stop' => (string)($res['stop_reason'] ?? ''), 'key' => $kn, 'switched' => $switched, 'img_cache' => 1,
        'think' => isset($payload['thinking']) ? $think : 'fast', 'think_off' => $thinkOff]);
}
