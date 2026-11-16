<?php
// №632 · Проверка: уровень сложности (глубина размышлений Claude) у «Космоса»; «Фил» (DeepSeek) — без изменений.
// 1) выбор уровня свой для каждой модели, сохраняется, Haiku без «Максимума»; 2) fl_ai отдаёт уровень шлюзу (разговор и фон),
// служебные мелкие вызовы — «Быстро»; 3) магазин → шлюз: поле think и таймауты; 4) шлюз: бюджет < max_tokens, без temperature,
// запасные пути effort / без размышлений; 5) поток: ход размышлений не попадает в текст; 6) приложение; 7) «Фил» не тронут.
// Запуск: php tests/kosmos_think_test.php
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }
function fn_src($s, $name) { $a = strpos($s, "function $name("); if ($a === false) return ''; $b = strpos($s, "\n}\n", $a); return substr($s, $a, $b + 3 - $a); }
function line_fn($s, $name) { return preg_match('~^function ' . $name . '\(.*$~m', $s, $m) ? $m[0] : ''; }
function line_cst($s, $name) { return preg_match('~^const ' . $name . ' = .*$~m', $s, $m) ? $m[0] : ''; }

$fil = (string)file_get_contents("$root/store/api/flow.php");
$ks = (string)file_get_contents("$root/store/api/kosmos_flow.php");

// 1) выбор уровня
$SP = [];
function sp_get($k) { return (string)($GLOBALS['SP'][$k] ?? ''); }
function sp_put($k, $v, $by) { $GLOBALS['SP'][$k] = (string)$v; }
eval(line_cst($ks, 'KS_THINK') . line_cst($ks, 'KS_THINK0') . line_fn($ks, 'ks_think_ok') . line_fn($ks, 'ks_think_map') . line_fn($ks, 'ks_think_pick') . line_fn($ks, 'ks_think_set'));
t('уровни: Быстро, Обычно, Глубоко, Максимум; по умолчанию — Обычно', array_map(function ($x) { return $x[1]; }, KS_THINK) === ['Быстро', 'Обычно', 'Глубоко', 'Максимум'] && ks_think_pick('claude-opus-5-5') === 'norm');
t('Opus — «Глубоко», Sonnet — «Быстро»: у каждой модели свой уровень', ks_think_set('claude-opus-5-5', 'deep', 'Б') && ks_think_set('claude-sonnet-5-5', 'fast', 'Б') && ks_think_pick('claude-opus-5-5') === 'deep' && ks_think_pick('claude-sonnet-5-5') === 'fast', $GLOBALS['SP']);
t('выбор сохраняется (ks_think в общем хранилище настроек)', json_decode($GLOBALS['SP']['ks_think'], true) === ['claude-opus-5-5' => 'deep', 'claude-sonnet-5-5' => 'fast']);
t('Haiku: «Максимум» не берёт, остальные — да; чужое значение не сохраняется', !ks_think_set('claude-haiku-5-5', 'max', 'Б') && ks_think_set('claude-haiku-5-5', 'deep', 'Б') && !ks_think_set('claude-opus-5-5', 'ultra', 'Б') && !ks_think_set('gpt-4', 'deep', 'Б') && ks_think_pick('claude-opus-5-5') === 'deep');
$GLOBALS['SP']['ks_think'] = json_encode(['claude-haiku-5-5' => 'max']);
t('если у Haiku как-то оказался «Максимум» — идёт «Глубоко»', ks_think_pick('claude-haiku-5-5') === 'deep');
t('настройки (fil_cfg): уровень для указанной модели, список уровней и недоступные — в ответе', strpos($ks, "fl_in('think_model', '')") !== false && strpos($ks, "'think' => ks_think_pick(ks_model_pick()), 'thinks' => \$thl") !== false && strpos($ks, "'think_no' =>") !== false && strpos($ks, "if (!\$me['boss']) fl_fail('Настройки Космоса — только руководитель', 403);") !== false);

// 2) fl_ai → шлюз
const ITCAI_ONE_JSON = ''; const AG_ANS_TOK = 4000; const KS_TOK_TOP = 64000;
eval(line_fn($ks, 'ks_tok_first'));
function ks_model_pick() { return $GLOBALS['MODEL']; } function fil_model_name($m) { return $m; } function fl_fail($m) { throw new Exception($m); }
function ks_ai_json($t) { $j = json_decode($t, true); return is_array($j) ? $j : ['do' => 'answer', 'say' => $t]; }
function test_ask($sys, $user, $mt, $model, $me, $think = '') { $GLOBALS['ASK'][] = [$model, $think]; $GLOBALS['KS_STOP'] = 'end_turn'; return [array_shift($GLOBALS['ANS']), '', 'Claude']; }
eval(str_replace('ks_gw_ask(', 'test_ask(', fn_src($ks, 'fl_ai')));
$GLOBALS['SP']['ks_think'] = json_encode(['claude-opus-5-5' => 'max', 'claude-sonnet-5-5' => 'fast']);
$GLOBALS['MODEL'] = 'claude-opus-5-5'; $GLOBALS['ASK'] = [];
$GLOBALS['ANS'] = ['{"tool":"deals","args":{}}', '{"tool":"suppliers","args":{}}', '{"do":"answer","say":"Готово"}'];
$steps = [fl_ai('s', 'шаг 1', AG_ANS_TOK, true), fl_ai('s', 'шаг 2', AG_ANS_TOK, true), fl_ai('s', 'шаг 3', AG_ANS_TOK, true)];
t('шаги разговора / фона (запас AG_ANS_TOK) идут с уровнем выбранной модели — Opus «Максимум» на всех трёх', $GLOBALS['ASK'] === array_fill(0, 3, ['claude-opus-5-5', 'max']) && ($steps[2]['say'] ?? '') === 'Готово', $GLOBALS['ASK']);
$GLOBALS['MODEL'] = 'claude-sonnet-5-5'; $GLOBALS['ASK'] = []; $GLOBALS['ANS'] = ['{"do":"answer","say":"ok"}'];
fl_ai('s', 'u', AG_ANS_TOK, true);
t('сменили модель — сразу её уровень (Sonnet «Быстро»), без перезагрузки', $GLOBALS['ASK'] === [['claude-sonnet-5-5', 'fast']], $GLOBALS['ASK']);
$GLOBALS['MODEL'] = 'claude-opus-5-5'; $GLOBALS['ASK'] = []; $GLOBALS['ANS'] = ['{"zh":"你好"}'];
fl_ai('s', 'переведи', 900, true);
t('мелкий служебный вызов (перевод, 900 токенов) — «Быстро», как раньше', $GLOBALS['ASK'] === [['claude-opus-5-5', 'fast']], $GLOBALS['ASK']);
t('фоновые задачи (ft_run → ag_run) и разговор зовут fl_ai с AG_ANS_TOK', strpos($ks, ', AG_ANS_TOK, true);') !== false && strpos(fn_src($ks, 'ft_run'), 'ag_run(') !== false);

// 3) магазин → шлюз
$lib = (string)file_get_contents("$root/store/api/kosmos_lib.php");
function ks_gw_call($req, $tmo) { $GLOBALS['GWC'][] = [$req['think'] ?? null, $tmo]; return [200, ['ok' => true, 'text' => 'ответ', 'think' => $req['think'] ?? 'fast', 'think_off' => $GLOBALS['OFF']]]; }
function ks_model_title($m) { return $m; }
eval(fn_src($lib, 'ks_gw_ask'));
$GLOBALS['OFF'] = ''; $GLOBALS['GWC'] = [];
foreach (['fast', 'norm', 'deep', 'max', ''] as $lv) ks_gw_ask('s', 'u', 8000, 'claude-sonnet-5-5', [], $lv);
t('уровень уходит на шлюз полем think; «Глубоко» ждём 660 с, «Максимум» 870 с, «Обычно» 240 с', $GLOBALS['GWC'] === [['fast', 150], ['norm', 240], ['deep', 660], ['max', 870], [null, 150]], $GLOBALS['GWC']);
$GLOBALS['OFF'] = 'thinking is not supported'; $r = ks_gw_ask('s', 'u', 8000, 'claude-sonnet-5-5', [], 'deep');
t('шлюз ответил без размышлений — ответ всё равно есть (причина — в журнал ошибок)', $r[0] === 'ответ' && strpos($lib, "error_log('kosmos think: ответ без размышлений") !== false);

// 4) шлюз Сингапура
$gw = (string)file_get_contents("$root/kosmos/lib/gw.php");
eval("const GW_TOK_MAX = 64000;\n" . line_cst($gw, 'GW_THINK') . "\n" . line_cst($gw, 'GW_EFFORT') . "\n" . line_cst($gw, 'GW_THINK_ANS') . "\n" . fn_src($gw, 'gw_think_level') . fn_src($gw, 'gw_think_put'));
t('бюджеты: Обычно 4000, Глубоко 16000, Максимум 32000; Haiku «Максимум» → «Глубоко»', GW_THINK === ['fast' => 0, 'norm' => 4000, 'deep' => 16000, 'max' => 32000] && gw_think_level('claude-haiku-5-5', 'max') === 'deep' && gw_think_level('claude-opus-5-5', 'max') === 'max' && gw_think_level('claude-opus-5-5', 'x') === 'fast');
$p0 = ['model' => 'm', 'max_tokens' => 8000, 'temperature' => 0.3, 'messages' => []];
$a = gw_think_put($p0, 'fast', 'budget');
t('«Быстро» — запрос как раньше (без thinking, тот же max_tokens)', $a === $p0);
$a = gw_think_put($p0, 'norm', 'budget'); $b = gw_think_put(array_merge($p0, ['max_tokens' => 32000]), 'max', 'budget'); $c = gw_think_put($p0, 'deep', 'budget');
t('бюджет меньше max_tokens, запас на ответ: Обычно 4000/12000, Глубоко 16000/24000, Opus Максимум 32000/64000', $a['thinking'] === ['type' => 'enabled', 'budget_tokens' => 4000] && $a['max_tokens'] === 12000 && $c['max_tokens'] === 24000 && $c['thinking']['budget_tokens'] === 16000 && $b['max_tokens'] === 64000 && $b['thinking']['budget_tokens'] === 32000, [$a, $b, $c]);
t('с размышлениями temperature убрана (Claude её с thinking не принимает)', !isset($a['temperature']) && !isset($b['temperature']));
$e = gw_think_put($c, 'deep', 'effort'); $z = gw_think_put($e, 'deep', '');
t('запасной путь: adaptive + effort high, запас не растёт; потом — совсем без размышлений', $e['thinking'] === ['type' => 'adaptive'] && $e['output_config'] === ['effort' => 'high'] && $e['max_tokens'] === 24000 && !isset($z['thinking']) && !isset($z['output_config']) && $z['max_tokens'] === 24000, [$e, $z]);
t('шлюз: 400 из-за размышлений → effort → без них, причина в журнал (gw_think_off), в ответе think/think_off', strpos($gw, "log_add(\$u['phone'], \$thinkOff !== '' ? 'gw_think_off' : 'gw_think_effort'") !== false && strpos($gw, "'think' => isset(\$payload['thinking']) ? \$think : 'fast', 'think_off' => \$thinkOff") !== false);
t('шлюз: при 64000 → 32000 бюджет ужимается, чтобы остался меньше max_tokens', strpos($gw, "\$payload['thinking']['budget_tokens'] = \$max - GW_THINK_ANS") !== false);
t('шлюз: запрос — одно сообщение user (история шагов текстом), блоки thinking между шагами не переносятся — ошибки 400 нет', strpos($gw, "'messages' => [['role' => 'user', 'content' => \$content]]") !== false);
t('шлюз: «Максимум» — поток до 840 с (предел сервера 900 с)', strpos($gw, "\$GLOBALS['GW_STREAM_TMO'] = \$think === 'max' ? 840 : 600;") !== false);

// 5) поток: ход размышлений не в тексте; расход — output_tokens (в нём и размышления)
$cl = (string)file_get_contents("$root/kosmos/lib/claude.php");
eval(fn_src($cl, 'claude_event') . fn_src($cl, 'blocks_clean') . fn_src($cl, 'blocks_text') . fn_src($cl, 'claude_err_kind'));
$st = ['buf' => '', 'raw' => '', 'blocks' => [], 'json' => [], 'stop' => '', 'usage' => [], 'model' => '', 'err' => '']; $said = '';
$on = function ($t) use (&$said) { $said .= $t; };
foreach ([['type' => 'message_start', 'message' => ['model' => 'm', 'usage' => ['input_tokens' => 10, 'output_tokens' => 1]]],
          ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'thinking', 'thinking' => '']],
          ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'thinking_delta', 'thinking' => 'СКРЫТО']],
          ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'signature_delta', 'signature' => 'x']],
          ['type' => 'content_block_start', 'index' => 1, 'content_block' => ['type' => 'text', 'text' => '']],
          ['type' => 'content_block_delta', 'index' => 1, 'delta' => ['type' => 'text_delta', 'text' => 'Ответ']],
          ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn'], 'usage' => ['output_tokens' => 900]]] as $ev) claude_event('data: ' . json_encode($ev, JSON_UNESCAPED_UNICODE), $st, $on);
ksort($st['blocks']);
t('поток: размышления не уходят ни в текст ответа, ни в озвучку; расход 900 токенов (с размышлениями)', $said === 'Ответ' && blocks_text(blocks_clean($st['blocks'])) === 'Ответ' && $st['usage']['output_tokens'] === 900, [$said, $st['blocks']]);
t('обычный ответ: блок thinking пропускается', blocks_text([['type' => 'thinking', 'thinking' => 'СКРЫТО', 'signature' => 's'], ['type' => 'text', 'text' => 'Да']]) === 'Да');

// 6) приложение
$h = (string)file_get_contents("$root/store/apps/kosmos/index.html");
t('окно модели: блок «Уровень сложности» с пояснением «Глубже — точнее, но дольше и дороже»', strpos($h, '<h4>Уровень сложности — ') !== false && strpos($h, 'Глубже — точнее, но дольше и дороже.') !== false && strpos($h, '+ thkHtml(busy)') !== false);
t('кнопка в шапке: «Модель · Уровень»', strpos($h, "mdlShort(CFG.model) + (t ? ' · ' + t : '')") !== false);
t('уровень сохраняется через fil_cfg для текущей модели; настройки и окно связаны', strpos($h, "api('fil_cfg', {think:id, think_model:CFG.model})") !== false && strpos($h, "'<div id=\"cfThk\">' + thkHtml(false)") !== false && strpos($h, "if (\$('cfg').style.display === 'flex') cfgRender(); st('Уровень: '") !== false);
t('недоступный уровень — неактивная кнопка с пояснением', strpos($h, "'У этой модели нет такого уровня'") !== false && strpos($h, "(busy || off ? ' disabled' : '')") !== false);
// №633 · все рабочие модели: список с сервера, подписи, группы, предыдущие версии свёрнуты
$fl = (string)file_get_contents("$root/store/api/kosmos_flow.php");
preg_match('~const KS_MODELS = (\[.*?\n\]);~s', $fl, $km); $KM = $km ? eval('return ' . $km[1] . ';') : [];
$ids = array_column($KM, 0);
t('№633: 14 моделей, первая — Sonnet 5.5 (по умолчанию), основные: Sonnet, Opus, Fable, Haiku', count($KM) === 14 && $ids[0] === 'claude-sonnet-5-5' && array_slice($ids, 0, 4) === ['claude-sonnet-5-5', 'claude-opus-5-5', 'claude-fable-5-2', 'claude-haiku-5-5'] && strpos($fl, "const KS_MODEL0 = 'claude-sonnet-5-5';") !== false);
t('№633: у каждой модели короткое имя, подпись, группа main/old; id разные и проходят проверку', count(array_unique($ids)) === 14 && !array_filter($KM, function ($m) { return count($m) !== 6 || $m[2] === '' || $m[3] === '' || !in_array($m[5], ['main', 'old'], true) || !preg_match('~^claude-[a-z0-9.-]{3,50}$~', $m[0]); }));
t('№633: метка цены есть у всех, кто дороже Opus 5.5 (Fable, старые Opus)', !array_filter($KM, function ($m) { return preg_match('~fable|opus-(5$|4)~', $m[0]) && strpos($m[4], 'дороже Opus') === false; }));
t('№633: подписи и уровни — в ответе fil_cfg (short, desc, tag, grp, think_no)', strpos($fl, "'short' => \$m[2] ?? '', 'desc' => \$m[3] ?? '', 'tag' => \$m[4] ?? '', 'grp' => \$m[5] ?? 'main'") !== false);
t('№633: Haiku 4.5 тоже без «Максимума»', function_exists('ks_think_ok') ? !ks_think_ok('claude-haiku-4-5-20251001', 'max') && ks_think_ok('claude-fable-5-2', 'max') : true);
t('№633: окно — список с сервера, «Предыдущие версии» свёрнуты, открыты, если выбрана старая', strpos($h, "grp:m.grp || 'main'") !== false && strpos($h, 'Предыдущие версии (') !== false && strpos($h, "MDL_OLD = !!(cur && cur.grp === 'old')") !== false);
eval(fn_src($gw, 'gw_think_how'));
t('№633: шлюз — budget только у 4.5/4.6, новые (4.7+, 5, 5.5, Fable) — сразу effort', gw_think_how('claude-sonnet-4-6') === 'budget' && gw_think_how('claude-opus-4-5-20251101') === 'budget' && gw_think_how('claude-haiku-4-5-20251001') === 'budget' && gw_think_how('claude-opus-4-6') === 'budget' && gw_think_how('claude-opus-4-7') === 'effort' && gw_think_how('claude-opus-5') === 'effort' && gw_think_how('claude-sonnet-5-5') === 'effort' && gw_think_how('claude-fable-5-2') === 'effort');
t('кеш sw.js поднят (v56 и выше)', preg_match("~'itc-kosmos-v(\\d+)'~", (string)file_get_contents("$root/store/apps/kosmos/sw.js"), $vm) && (int)$vm[1] >= 56);

// 7) «Фил» не тронут
t('«Фил» (flow.php): ни уровней, ни thinking — как был', strpos($fil, 'ks_think') === false && strpos($fil, "'thinking'") === false && strpos($fil, 'KS_THINK') === false && strpos(fn_src($fil, 'fl_ai'), '$think') === false && strpos($fil, "'think' =>") === false);
$fh = (string)file_get_contents("$root/store/apps/fil/index.html");
t('приложение «Фила» без переключателя уровня', strpos($fh, 'thkHtml') === false && strpos($fh, 'think_model') === false);

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
