<?php
// №630 · Проверка: «Космос» (Claude) работает на максимуме, «Фил» (DeepSeek) — как был.
// 1) пределы в kosmos_flow.php больше, в flow.php — прежние; 2) запас токенов: Opus сразу 32000, упёрся — 64000;
// 3) страховка контекста AG_HIST_MAX сокращает ранние результаты, последние не трогает;
// 4) шлюз: предел 64000, большой ответ — потоком, модель не берёт 64000 → 32000; 5) переключатель модели в приложении.
// Запуск: php tests/kosmos_max_test.php
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }
function fn_src($s, $name) { $a = strpos($s, "function $name("); if ($a === false) return ''; $b = strpos($s, "\n}\n", $a); return substr($s, $a, $b + 3 - $a); }
function cst($s, $name) { return preg_match('~(?:^|[;\s])const ' . $name . ' = ([^;]+);~m', $s, $m) ? eval('return ' . $m[1] . ';') : null; }
function line_fn($s, $name) { return preg_match('~^function ' . $name . '\(.*$~m', $s, $m) ? $m[0] : ''; }

$fil = (string)file_get_contents("$root/store/api/flow.php");
$ks = (string)file_get_contents("$root/store/api/kosmos_flow.php");

// 1) пределы
t('«Фил»: 4 инструмента за ход, результаты 8000/10000, страховка выключена', cst($fil, 'AG_MULTI') === 4 && cst($fil, 'AG_TOOL_CUT') === 8000 && cst($fil, 'AG_TOOL_CUT_BG') === 10000 && cst($fil, 'AG_HIST_MAX') === 0);
$lf = cst($fil, 'AG_LIM'); $lk = cst($ks, 'AG_LIM');
t('«Фил»: прежние пределы инструментов (журнал 20, итог поручения 800, code_read 150, code_find 25, web 10)', $lf['journal'] === 20 && $lf['dev_res'] === 800 && $lf['dev_n'] === 8 && $lf['code_read'] === 150 && $lf['code_find'] === 25 && $lf['web'] === 10 && $lf['page'] === 5000, $lf);
t('«Фил»: ответ «коротко (до 6/10 предложений)» — как был', strpos($fil, "коротко (до ' . (\$bg ? 10 : 6) . ' предложений)") !== false);
t('«Космос»: 8 инструментов за ход, результаты до 32000, страховка 300000', cst($ks, 'AG_MULTI') === 8 && cst($ks, 'AG_TOOL_CUT') === 32000 && cst($ks, 'AG_TOOL_CUT_BG') === 32000 && cst($ks, 'AG_HIST_MAX') === 300000);
t('«Космос»: у всех пределов инструментов те же имена, и каждый не меньше, чем у «Фила»', is_array($lk) && array_keys($lk) === array_keys($lf) && !array_filter(array_keys($lf), function ($k) use ($lf, $lk) { return $lk[$k] < $lf[$k]; }), $lk);
t('«Космос»: итог поручения до 20000 знаков, 30 поручений, code_read 400, code_find 80', $lk['dev_res'] === 20000 && $lk['dev_n'] === 30 && $lk['code_read'] === 400 && $lk['code_find'] === 80);
t('«Космос»: нет жёсткого «до 6 предложений», длина — по задаче', strpos($ks, "коротко (до ' . (\$bg") === false && strpos($ks, 'Длина — по задаче') !== false && strpos($ks, 'ответ короткий и по делу?') === false);
t('защита не тронута: AG_REPEAT 3, AG_STALL 12, шаги 60/400 — одинаково', cst($ks, 'AG_REPEAT') === 3 && cst($ks, 'AG_STALL') === 12 && cst($ks, 'AG_MAX_STEPS') === 60 && cst($ks, 'AG_BG_STEPS') === 400 && cst($fil, 'AG_REPEAT') === 3 && cst($fil, 'AG_MAX_STEPS') === 60);
t('инструменты берут пределы из AG_LIM (журнал, сделки, поставщики, переписка, интернет, поручения)', substr_count($fil, "ag_lim('") >= 18 && strpos($fil, "dv_gw('list', ['n' => ag_lim('dev_n', 8)])") !== false);
foreach (['fil_edit.php' => ['code_find', 'code_read'], 'sup_hist.php' => ['sh_n', 'sh_feed', 'sh_len']] as $f => $ks_) {
  $s = (string)file_get_contents("$root/store/api/$f");
  t("$f: пределы через ag_lim (с запасным значением без flow.php)", !array_filter($ks_, function ($k) use ($s) { return strpos($s, "function_exists('ag_lim') ? (int)ag_lim('$k'") === false; }));
}

// 2) запас токенов «Космоса»
eval(line_fn($ks, 'ks_tok_first'));
define('KS_TOK_TOP', cst($ks, 'KS_TOK_TOP'));
t('Opus — сразу 32000, Sonnet — как раньше 8000, предел 64000', ks_tok_first('claude-opus-5-5', 4000) === 32000 && ks_tok_first('claude-sonnet-5-5', 4000) === 8000 && KS_TOK_TOP === 64000);
$GLOBALS['MODEL'] = 'claude-opus-5-5';
function ks_think_pick($m) { return 'fast'; }   // №632 · уровень сложности проверяет kosmos_think_test.php
function ks_model_pick() { return $GLOBALS['MODEL']; } function fil_model_name($m) { return $m; } function fl_fail($m) { throw new Exception($m); }
function ks_ai_json($t) { $j = json_decode($t, true); return is_array($j) ? $j : ['do' => 'answer', 'say' => $t]; }
const ITCAI_ONE_JSON = '';
function test_ask($sys, $user, $mt, $model, $me) { $GLOBALS['MT'][] = $mt; $r = array_shift($GLOBALS['ANS']); $GLOBALS['KS_STOP'] = $r[1]; return [$r[0], '', 'Claude']; }
eval(str_replace('ks_gw_ask(', 'test_ask(', fn_src($ks, 'fl_ai')));
$GLOBALS['MT'] = []; $GLOBALS['ANS'] = [['Длинный разбор, оборвался на сере', 'max_tokens'], ['{"do":"answer","say":"Весь разбор."}', 'end_turn']];
$j = fl_ai('s', 'u', 4000, true);
t('Opus: обрыв по max_tokens — заново с 64000, обрывок не показываем', ($j['say'] ?? '') === 'Весь разбор.' && $GLOBALS['MT'] === [32000, 64000], [$j, $GLOBALS['MT']]);

// 3) страховка контекста
$a = strpos($ks, '    if (AG_HIST_MAX > 0) {'); $b = strpos($ks, "\n    }\n", $a);
$guard = substr($ks, $a, $b + 6 - $a);
define('AG_HIST_MAX', 300000);
$hist = 'НОВЫЙ ЗАПРОС: x'; $hp = []; for ($i = 0; $i < 20; $i++) $hp[$i] = ["\n\nТЫ: {}\nРЕЗУЛЬТАТ t: ", str_repeat('я', 31990) . "КОНЕЦ$i"]; $nE = 20;
eval($guard);
$tot = mb_strlen($hist); foreach ($hp as $x) $tot += mb_strlen($x[0]) + mb_strlen($x[1]);
t('история 20×32000 знаков ужата до 300000, начиная со старых', $tot <= 300000 && strpos($hp[0][1], 'не хватает места') !== false && strpos($hp[19][1], 'КОНЕЦ19') !== false, $tot);
$hp2 = [["\n\nТЫ: {}\nРЕЗУЛЬТАТ t: ", str_repeat('я', 5000)]]; $hp = $hp2; $nE = 1; eval($guard);
t('короткая история — без изменений', $hp === $hp2);

// 4) шлюз Сингапура
$gw = (string)file_get_contents("$root/kosmos/lib/gw.php");
t('шлюз: предел 64000 (было 32000), длинное — потоком, 400 → повтор с 32000', strpos($gw, 'const GW_TOK_MAX = 64000;') !== false && strpos($gw, 'min(32000,') === false && strpos($gw, "\$payload['max_tokens'] = \$max = 32000") !== false);
eval('const GW_TOK_STREAM = 16000;' . fn_src($gw, 'gw_claude'));
function claude_call($p, $onText, $onSw) { $GLOBALS['CC'][] = [$p['max_tokens'], $onText !== null]; return array_shift($GLOBALS['CR']); }
$GLOBALS['CC'] = []; $GLOBALS['CR'] = [['content' => []]]; gw_claude(['max_tokens' => 8000], function () {});
$GLOBALS['CR'] = [['error' => 'перегрузка', 'http' => 529], ['content' => []]]; gw_claude(['max_tokens' => 64000], function () {});
t('шлюз: 8000 — обычным запросом; 64000 — потоком, сбой — ещё раз', $GLOBALS['CC'] === [[8000, false], [64000, true], [64000, true]], $GLOBALS['CC']);
$lib = (string)file_get_contents("$root/store/api/kosmos_lib.php");
t('магазин ждёт длинный ответ / Opus до 660 с (было 150/240)', strpos($lib, "\$tmo = \$long ? 660 :") !== false && strpos($lib, "preg_match('~opus|fable~i', (string)\$model)") !== false);
$dev = (string)file_get_contents("$root/kosmos/lib/dev.php");
t('сервер: список поручений по запросу до 50 (по умолчанию 10), итог хранится до 20000', strpos($dev, "dev_list(max(1, min(50, (int)(\$in['n'] ?? 10))))") !== false && strpos($dev, "(\$a['result'] ?? ''), 0, 20000)") !== false);

// 5) переключатель модели
$h = (string)file_get_contents("$root/store/apps/kosmos/index.html");
t('приложение: кнопка модели в шапке и пункт «Модель» в меню', strpos($h, 'id="mdlBtn" onclick="mdlOpen()"') !== false && strpos($h, 'id="miMdl"') !== false && strpos($h, "\$('miMdl').style.display = 'flex'") !== false);
t('приложение: Sonnet, Opus («сильнее, дороже»), Haiku; сохраняет тот же ks_model через fil_cfg', strpos($h, "['opus', 'Opus', 'Самая сильная', 'сильнее, дороже']") !== false && strpos($h, "['sonnet', 'Sonnet', 'Рекомендуемая, по умолчанию'") !== false && strpos($h, "['haiku', 'Haiku'") !== false && strpos($h, "api('fil_cfg', {model:id})") !== false);
t('приложение: настройки и шапка показывают одно (mdlShow после сохранения в настройках)', strpos($h, 'CFG = r; cfgRender(); mdlShow();') !== false && strpos($h, "CFG = r; \$('gear').style.display = 'flex'; mdlShow();") !== false);
t('приложение: кеш sw.js поднят (v55 и новее)', preg_match("~'itc-kosmos-v(\\d+)'~", (string)file_get_contents("$root/store/apps/kosmos/sw.js"), $m) && (int)$m[1] >= 55);

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
