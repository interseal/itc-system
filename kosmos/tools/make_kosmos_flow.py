#!/usr/bin/env python3
"""«Космос» в магазине = копия «Фила» (api/flow.php), только думает Claude.
Делает api/kosmos_flow.php из api/flow.php. Запуск:
    python3 tools/make_kosmos_flow.py <путь к flow.php> <куда записать kosmos_flow.php>
Что меняется по сравнению с «Филом»:
  • ум — Claude через сервер «Космоса» в Сингапуре (шлюз, api/kosmos_lib.php), а не DeepSeek/Яндекс;
  • своя переписка, свои разговоры и фоновые задачи (таблицы itc_ks_log, itc_ks_conv, itc_ks_task);
  • свои настройки: модель и голос (ks_model, ks_voice);
  • №536: памятка и выученное — ОБЩИЕ с «Филом» (fil_memo, fil_learn): чему научился один, знает и другой;
    каждый видит журнал второго (AG_PEER, инструмент journal) — что тот делал и отвечал;
  • имя «Космос» во всех подсказках и ответах, ссылки на /apps/kosmos/;
  • фоновые задачи будит сервер «Космоса» раз в минуту (без планировщика хостинга); общие дела «Фила»
    (напоминания, спам, ночные копии) «Космос» не трогает — их делает «Фил»;
  • данные CRM (сделки, клиенты, поставщики, КП, файлы) — общие: «Космос» работает с той же базой.
После изменений «Фила» запустите этот скрипт ещё раз — «Космос» получит те же новые умения.
"""
import re, sys

src, dst = sys.argv[1], sys.argv[2]
s = open(src, encoding='utf-8').read()


def rep(a, b, n=1):
    global s
    c = s.count(a)
    if c != n:
        raise SystemExit(f'не нашёл ровно {n} раз ({c}): {a[:90]!r}')
    s = s.replace(a, b)


def cut(start, end, new):
    """Заменить кусок от start (включительно) до end (не включая)."""
    global s
    i = s.find(start); j = s.find(end, i + 1)
    if i < 0 or j < 0 or s.find(start, i + 1) >= 0 and s.find(start, i + 1) < j:
        raise SystemExit(f'не нашёл кусок {start[:60]!r} … {end[:40]!r}')
    s = s[:i] + new + s[j:]


# 0) заголовок
rep('<?php\n', '<?php\n'
    '// ==== ИТК · api/kosmos_flow.php · «КОСМОС» — копия «Фила» на Claude (№533, собрано tools/make_kosmos_flow.py из api/flow.php) ====\n'
    '// Не править вручную: изменения делаются в flow.php («Фил»), потом этот файл собирается заново.\n'
    '// Отличия от «Фила»: думает Claude (через сервер «Космоса» в Сингапуре), своя переписка/разговоры/задачи (itc_ks_*),\n'
    '// свои памятка, выученное, модель и голос (ks_*), фоновые задачи будит сервер «Космоса». Данные CRM — общие.\n', 1)

# 1) общие функции «Космоса» (шлюз к Claude, ключ фоновых задач)
rep("require_once __DIR__ . '/config.php';\n", "require_once __DIR__ . '/config.php';\nrequire_once __DIR__ . '/kosmos_lib.php';   // «Космос»: шлюз к Claude\n")

# 2) ум: Claude вместо DeepSeek/Яндекса
rep(s[s.find('function fil_model_name($id) {'):s.find('\n', s.find('function fil_model_name($id) {'))],
    "/* «Космос»: модели Claude на выбор руководителю (настройки). По умолчанию — Sonnet. */\n"
    "const KS_MODELS = [['claude-sonnet-5-5', 'Claude Sonnet 5.5 (рекомендую)'], ['claude-opus-5-5', 'Claude Opus 5.5 — самый сильный, дороже'], ['claude-haiku-5-5', 'Claude Haiku 5.5 — быстрый и недорогой'], ['claude-fable-5-2', 'Claude Fable 5.2 — старший уровень, самый дорогой']];\n"
    "const KS_MODEL0 = 'claude-sonnet-5-5';\n"
    "function ks_model_pick() { $m = (string)($GLOBALS['FIL_MODEL'] ?? ''); if ($m === '') $m = sp_get('ks_model'); return preg_match('~^claude-[a-z0-9.-]{3,50}$~', $m) ? $m : KS_MODEL0; }\n"
    "function fil_model_name($id) { foreach (KS_MODELS as $m) if ($m[0] === $id) return $m[1]; return ks_model_title($id); }")
cut('function fl_ai($sys, $user, $maxTok = 1500, $soft = false) {', 'const FL_ITC = ',
    "function fl_ai($sys, $user, $maxTok = 1500, $soft = false) {\n"
    "  // «Космос»: тот же вопрос, что задал бы «Фил», уходит к Claude через сервер «Космоса» (ключи Claude — только там)\n"
    "  // №535: JSON разбирает ks_ai_json (пояснения вокруг, несколько шагов сразу, переводы строк внутри строк);\n"
    "  // ответ не уместился или не разобрался — ещё одна попытка (не уместился — с запасом 16000)\n"
    "  $model = ks_model_pick(); $mt = min(16000, max(2000, (int)$maxTok * 2)); $txt = null; $err = '';\n"
    "  for ($try = 0; $try < 2; $try++) {\n"
    "    [$txt, $err, $real] = ks_gw_ask($sys, $user, $mt, $model, $GLOBALS['me'] ?? []);\n"
    "    if ($txt === null) break;\n"
    "    $j = ks_ai_json($txt);\n"
    "    if ($j) { $j['_model'] = $real !== '' ? $real : fil_model_name($model); return $j; }\n"
    "    $stop = (string)($GLOBALS['KS_STOP'] ?? '');\n"
    "    $err = 'Claude: ' . ($stop === 'max_tokens' ? 'ответ не уместился' : (trim((string)$txt) === '' ? 'пустой ответ' : 'ответ без разметки'));\n"
    "    error_log('kosmos ai: ' . $err . ' [' . $stop . '] ' . mb_substr((string)$txt, 0, 600));\n"
    "    if ($stop === 'max_tokens') $mt = 16000;\n"
    "  }\n"
    "  if (!empty($GLOBALS['me']['boss']) && is_string($txt) && trim($txt) !== '') $err .= ' — начало ответа: «' . mb_substr(preg_replace('~\\s+~u', ' ', trim($txt)), 0, 90) . '…»';\n"
    "  error_log('kosmos ai: ' . $err); $GLOBALS['FL_AI_ERR'] = $err;\n"
    "  if ($soft) return null;\n"
    "  fl_fail('Космос сейчас не ответил (' . $err . '). Попробуйте через минуту.');\n"
    "}\n")
# №535: в истории разговора реплики «Космоса» подписаны его именем (было «Фил:»)
rep('"\\nФил: "', '"\\nКосмос: "')
fs = s.find('const FIL_SELF = ')
rep(s[fs:s.find('\n', fs)],
    'const FIL_SELF = "\\nО СЕБЕ: тебя зовут Космос, ты работаешь на модели Claude компании Anthropic (через сервер ИТК в Сингапуре). '
    'На вопрос, на чём ты работаешь, отвечай именно так. На каждый ход отвечай строго одним JSON, как описано выше, без текста вокруг.";')

# 3) своя переписка, разговоры, фоновые задачи
for a, b in [('itc_fc_log', 'itc_ks_log'), ('itc_fil_conv', 'itc_ks_conv'), ('itc_fil_task', 'itc_ks_task')]:
    n = len(re.findall(r'\b' + a + r'\b', s))
    if n < 3: raise SystemExit('мало упоминаний ' + a)
    s = re.sub(r'\b' + a + r'\b', b, s)

# 4) свои настройки: модель и голос. Памятка и выученное — общие с «Филом» (№536)
for a, b in [("'fil_model2'", "'ks_model'"), ("'sp_voice'", "'ks_voice'")]:
    if s.count(a) < 1: raise SystemExit('нет ' + a)
    s = s.replace(a, b)

# 5) фоновые задачи будит сервер «Космоса»; общие дела «Фила» не трогаем
rep("    $k = sp_get('ft_cron_key'); if ($k === '' || !hash_equals($k, (string)($_GET['k'] ?? ''))) { http_response_code(403); exit('нет доступа'); }\n"
    "    sp_put('ft_cron_seen', date('Y-m-d H:i:s'), 'сервер');\n",
    "    if (!ks_cron_ok((string)($_GET['k'] ?? ''))) { http_response_code(403); exit('нет доступа'); }   // будит сервер «Космоса» (агент, раз в минуту)\n"
    "    sp_put('ks_cron_seen', date('Y-m-d H:i:s'), 'сервер');\n")
rep("    pl_tick();   // №43 · напоминания планировщика\n", "")
cut("    if (function_exists('msp_tick')) {", "    $r = db()->query(\"SELECT id,phone FROM itc_ks_task", "")
cut("    if (!$r && bk_due()) {", "    if (!$r) exit('ok: задач нет');", "")
cut("  case 'ft_cron_url': {", "  case 'yk_set': {",
    "  case 'ft_cron_url': {\n"
    "    if (!$me['boss']) fl_fail('Только руководитель', 403);\n"
    "    fl_out(['ok' => true, 'url' => ks_cron_url(), 'seen' => sp_get('ks_cron_seen'), 'auto' => true]);   // «Космос»: будит его сервер, вписывать никуда не нужно\n"
    "  }\n")

# 6) чужие входы (переписка WeCom, сервер в Китае, копии) — только у «Фила»
rep("try {\n  if ($action === 'wc_in')",
    "try {\n  if (in_array($action, ['wc_in', 'srv_poll', 'srv_done', 'bk_list', 'bk_get'], true)) { http_response_code(404); exit('нет'); }   // «Космос»: это делает «Фил»\n"
    "  if ($action === 'wc_in')")

# 7) настройки: модели Claude и ключи Claude вместо DeepSeek
cfg_s = s.find("  case 'fil_cfg': {"); cfg_e = s.find("  case 'fil_compare': {")
blk = s[cfg_s:cfg_e]
names_line = next(l for l in blk.split('\n') if "fl_in('names', null)" in l)
s = s[:cfg_s] + (
    "  case 'fil_cfg': {\n"
    "    if (!$me['boss']) fl_fail('Настройки Космоса — только руководитель', 403);\n"
    "    if (fl_in('memo', null) !== null && fl_in('memo') !== '') sp_put('fil_memo', mb_substr((string)fl_in('memo'), 0, 12000), $me['name']);   // №536: памятка общая с «Филом»\n"
    "    if (fl_in('memo_reset')) sp_put('fil_memo', FIL_MEMO0, $me['name']);\n"
    + names_line + "\n"
    "    if (fl_in('learn', null) !== null) sp_put('fil_learn', mb_substr(trim((string)fl_in('learn')), 0, 12000), $me['name']);   // №536: выученное общее\n"
    "    if (fl_in('model', null) !== null && fl_in('model') !== '') { $m = preg_replace('~[^a-z0-9.-]~', '', (string)fl_in('model')); if (preg_match('~^claude-[a-z0-9.-]{3,50}$~', $m)) sp_put('ks_model', $m, $me['name']); }\n"
    "    $mods = array_map(function ($m) { return ['id' => $m[0], 'name' => $m[1]]; }, KS_MODELS);\n"
    "    fl_out(['ok' => true, 'memo' => fil_memo(), 'learn' => fil_learn(), 'model' => ks_model_pick(), 'models' => $mods, 'ds' => ['has' => false], 'ks' => ks_gw_status(), 'ks_admin' => KS_SERVER . '/',\n"
    "      'staff' => array_map(function ($s) { return ['phone' => $s['phone'], 'name' => $s['name'], 'role' => $s['role'], 'io' => fil_io($s['phone'], '')]; }, fl_staff())]);\n"
    "  }\n") + s[cfg_e:]

# 8) имя и адреса
s = re.sub(r'(?<!\w)Фил(а|у|ом|е)?(?!\w)', lambda m: 'Космос' + (m.group(1) or ''), s)
s = s.replace('/apps/fil/', '/apps/kosmos/').replace('/api/flow.php', '/api/kosmos_flow.php')
s = s.replace('uploads/fil_prog', 'uploads/ks_prog')
if re.search(r'(?<!\w)Фил(а|у|ом|е)?(?!\w)', s): raise SystemExit('осталось имя Фил')

# 8б) новые таблицы «Космоса» сразу со столбцом разговора (у «Фила» он добавлялся позже)
rep("said TEXT NOT NULL, reply TEXT NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT '', deal_id VARCHAR(40) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB",
    "said TEXT NOT NULL, reply TEXT NOT NULL, kind VARCHAR(20) NOT NULL DEFAULT '', deal_id VARCHAR(40) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, conv_id VARCHAR(40) NOT NULL DEFAULT '', KEY idx_fclog_conv (phone, conv_id, id)) ENGINE=InnoDB")
rep("created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, done_at DATETIME NULL, KEY idx_ft_phone (phone, created_at)) ENGINE=InnoDB",
    "created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, done_at DATETIME NULL, conv_id VARCHAR(40) NOT NULL DEFAULT '', KEY idx_ft_phone (phone, created_at)) ENGINE=InnoDB")
# комментарии, которые замена имени сделала бессмысленными
rep("// «Космос»: тот же вопрос, что задал бы «Космос», уходит", "// «Космос»: тот же вопрос, что задал бы «Фил», уходит")
rep("// «Космос»: это делает «Космос»", "// «Космос»: это делает только «Фил»")

# 9) №536: памятка и выученное общие с «Филом». Один раз переносим в общую память то, что «Космос» выучил отдельно
#    (ks_learn, ks_memo с №533), без повторов: строки, отличающиеся только именем помощника, считаем одинаковыми
rep("function fil_learn() { return sp_get('fil_learn'); }",
    "function ks_mem_norm($l) {\n"
    "  $l = preg_replace('~\\s*\\([^()]*\\)\\s*$~u', '', (string)$l);\n"
    "  $l = preg_replace('~(?<![\\p{L}\\p{N}_])(Фил|Космос)(а|у|ом|е)?(?![\\p{L}\\p{N}_])~u', '', $l);\n"
    "  return mb_strtolower(preg_replace('~[^\\p{L}\\p{N}]+~u', '', $l));\n"
    "}\n"
    "function ks_mem_merge() {   // №536 · один раз: отдельная память «Космоса» → общая\n"
    "  static $done = false; if ($done) return; $done = true;\n"
    "  if (sp_get('ks_mem_merged') === '1') return;\n"
    "  foreach (['ks_learn' => 'fil_learn', 'ks_memo' => 'fil_memo'] as $from => $to) {\n"
    "    $own = sp_get($from); if (trim($own) === '') continue;\n"
    "    $base = sp_get($to); if ($base === '' && $to === 'fil_memo') $base = FIL_MEMO0;\n"
    "    $have = []; foreach (explode(\"\\n\", $base) as $l) $have[ks_mem_norm($l)] = 1;\n"
    "    $add = []; foreach (explode(\"\\n\", $own) as $l) { $l = rtrim($l); $k = ks_mem_norm($l); if ($k === '' || isset($have[$k])) continue; $add[] = $l; $have[$k] = 1; }\n"
    "    if ($add) sp_put($to, mb_substr(rtrim($base) . \"\\n\" . implode(\"\\n\", $add), 0, 12000), 'Космос (общая память)');\n"
    "  }\n"
    "  sp_put('ks_mem_merged', '1', 'Космос');\n"
    "}\n"
    "function fil_learn() { ks_mem_merge(); return sp_get('fil_learn'); }")
rep("function fil_memo() { $m = sp_get('fil_memo'); return $m !== '' ? $m : FIL_MEMO0; }",
    "function fil_memo() { ks_mem_merge(); $m = sp_get('fil_memo'); return $m !== '' ? $m : FIL_MEMO0; }")

# 10) №536 · второй помощник для «Космоса» — «Фил»: его журнал itc_fc_log (после всех замен)
rep("const AG_PEER = ['log' => 'itc_ks_log', 'name' => 'Космос', 'gen' => 'Космоса', 'ins' => 'Космосом', 'me' => 'Космос'];",
    "const AG_PEER = ['log' => 'itc_fc_log', 'name' => 'Фил', 'gen' => 'Фила', 'ins' => 'Филом', 'me' => 'Космос'];")

# 11) №537 · голоса: свой и напарника меняются местами. У «Космоса» по умолчанию Антон (запасной — Филипп),
#     у «Фила» — Кирилл (запасной — Ермил). Одним голосом им говорить нельзя
rep("const SP_ME = ['key' => 'ks_voice', 'def' => 'kirill', 'alt' => 'ermil'];",
    "const SP_ME = ['key' => 'ks_voice', 'def' => 'anton', 'alt' => 'filipp'];   // у «Космоса» свой голос")
rep("const SP_PEER = ['key' => 'ks_voice', 'def' => 'anton', 'alt' => 'filipp', 'name' => 'Космос', 'gen' => 'Космоса'];",
    "const SP_PEER = ['key' => 'sp_voice', 'def' => 'kirill', 'alt' => 'ermil', 'name' => 'Фил', 'gen' => 'Фила'];   // напарник — «Фил»")

# 12) №538 · навыки: записывает их «Космос» (Claude решает сложное лучше), пользуются оба — таблица itc_skill общая
rep("const NVK_WRITE = false;", "const NVK_WRITE = true; ")

# проверки
for bad in ['itc_fil_conv', 'itc_fil_task', "sp_get('ft_cron_key')", 'pl_tick();   // №43 · напоминания планировщика', "'ks_memo'"]:
    if bad in s and not (bad == "'ks_memo'" and s.count(bad) == 1):
        raise SystemExit('осталось: ' + bad)
if s.count("'sp_voice'") != 1 or "'key' => 'sp_voice', 'def' => 'kirill'" not in s:
    raise SystemExit('голос «Фила» должен остаться только в SP_PEER')
if "const NVK_WRITE = true; " not in s:
    raise SystemExit('«Космос» должен записывать навыки')
if s.count('itc_fc_log') != 1 or "'log' => 'itc_fc_log'" not in s:
    raise SystemExit('журнал «Фила» должен остаться только в AG_PEER')
open(dst, 'w', encoding='utf-8').write(s)
print('ok', dst, len(s))
