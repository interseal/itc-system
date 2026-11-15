<?php
// №624 · Проверка: лимиты шагов «Фила»/«Космоса» (разговор 60, фон 400 и 3 часа) и защита от зацикливания в ag_run:
// один и тот же вызов 3 раза подряд — стоп с причиной; фоновая задача без нового результата 12 шагов — стоп с причиной;
// снимки: в разговоре — на всех шагах, в фоне — на первых 12. Функцию ag_run берём прямо из store/api/flow.php, всё вокруг — заглушки.
// Запуск: php tests/ag_loop_test.php
$root = dirname(__DIR__, 2);
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }

foreach (['store/api/flow.php', 'store/api/kosmos_flow.php'] as $fn) {
  $s = (string)file_get_contents("$root/$fn");
  t("$fn: разговор 60, фон 400 шагов и 180 минут", strpos($s, 'const AG_MAX_STEPS = 60;') !== false && strpos($s, 'const AG_BG_STEPS = 400;') !== false && strpos($s, 'const AG_BG_MIN = 180;') !== false);
  t("$fn: в промпте нет старых «160 шагов и час» и «до 160 обращений»", strpos($s, '160 шагов') === false && strpos($s, 'до 160 обращений') === false && strpos($s, 'с запасом до 400 шагов и до 3 часов') !== false && strpos($s, 'до 400 обращений к инструментам и до 3 часов') !== false);
  t("$fn: «Максимум N обращений» берётся из лимита", strpos($s, "Максимум ' . \$max . ' обращений к инструментам.") !== false);
}

$src = (string)file_get_contents("$root/store/api/flow.php");
$a = strpos($src, 'function ag_run('); $b = strpos($src, "\n}\n", $a);
preg_match_all('~^const (AG_REPEAT|AG_STALL|AG_MAX_STEPS|AG_BG_STEPS)\b[^\n]*~m', $src, $cm);
foreach (['FIL_SELF', 'FIL_CORE', 'FIL_KP', 'FIL_CATALOG', 'FE_RULES', 'PL_RULES', 'FIL_SEARCH', 'CRM_RULES', 'RB_RULES', 'FIL_EX', 'FIL_LAST', 'AG_BG_RULES', 'AG_TASK_RULES', 'AG_DEEP_RULES'] as $c) define($c, '');
eval(implode("\n", $cm[0]) . "\n");
function ag_tools($me, $ctx) { return ['web' => ['{"q":""}', function ($a) { return $GLOBALS['TOOL_OUT'] === 'same' ? 'ничего не нашёл' : 'нашёл: ' . json_encode($a, JSON_UNESCAPED_UNICODE) . ' ' . (++$GLOBALS['N']); }]]; }
function fl_staff() { return []; } function fil_io($p, $n) { return ''; } function fil_boss_io() { return 'Алексей Николаевич'; }
function pl_loc($a, $b, $c) { return '1'; } function pl_tz() { return 'Europe/Moscow'; } function fil_memo() { return ''; } function fil_learn() { return ''; }
function ag_peer_block($me) { return ''; } function nvk_block($t) { return ''; } function sp_get($k) { return ''; }
/* «модель»: по очереди отдаёт заготовленные ответы; запоминает, что ей сказали и видела ли она снимки */
function fl_ai($sys, $user, $max, $soft) {
  $GLOBALS['ASKED'][] = $user; $GLOBALS['IMG_SEEN'][] = count($GLOBALS['FC_IMGS'] ?? []);
  $f = $GLOBALS['SCRIPT']; return $f(count($GLOBALS['ASKED']), $user);
}
eval(substr($src, $a, $b + 3 - $a));
$me = ['name' => 'Алексей', 'boss' => true, 'role' => 'boss', 'phone' => '+7'];
$ctx = ['pipes' => [], 'st' => []];
function run($script, $opt = [], $out = 'diff', $imgs = []) {
  global $me, $ctx; $GLOBALS['ASKED'] = []; $GLOBALS['IMG_SEEN'] = []; $GLOBALS['SCRIPT'] = $script; $GLOBALS['TOOL_OUT'] = $out; $GLOBALS['N'] = 0; $GLOBALS['FC_IMGS'] = $imgs;
  return ag_run($me, $ctx, 'найди фабрику', '', $opt);
}

// 1) один и тот же вызов подряд → после 3-го повтора стоп, модель отвечает с причиной
[$j, $tr] = run(function ($n, $u) { return strpos($u, 'СТОП, нет продвижения') !== false ? ['do' => 'answer', 'say' => 'Не нашёл, вот что есть.'] : ['tool' => 'web', 'args' => ['q' => 'одно и то же']]; });
t('повтор одного и того же вызова 3 раза подряд — стоп (а не 60 шагов)', count($tr) === 3 && !empty($j['stall']) && strpos($j['stall'], 'повторился 3 раза') !== false, [$tr, $j]);
t('модели сказано, почему стоп, и инструменты больше нельзя', strpos(end($GLOBALS['ASKED']), 'СТОП, нет продвижения: один и тот же запрос («web») повторился 3 раза подряд') !== false && strpos(end($GLOBALS['ASKED']), 'Инструменты больше нельзя') !== false);
// 1б) модель всё равно просит инструмент — ответ с причиной без неё
[$j, $tr] = run(function ($n, $u) { return ['tool' => 'web', 'args' => ['q' => 'одно и то же']]; });
t('модель упёрлась — человеку ответ с причиной', strpos((string)$j['say'], 'Остановился: один и тот же запрос') !== false, $j);
// 2) разные вызовы — работает дальше, до лимита разговора (60)
[$j, $tr] = run(function ($n, $u) { return strpos($u, 'Инструменты больше нельзя') !== false ? ['do' => 'answer', 'say' => 'итог'] : ['tool' => 'web', 'args' => ['q' => 'запрос ' . $n]]; });
t('разные вызовы — до 60 обращений к инструментам в разговоре', count($tr) === 60 && ($j['say'] ?? '') === 'итог' && empty($j['stall']), count($tr));
// 3) фон: разные запросы, но 12 шагов подряд без нового результата → стоп с причиной
[$j, $tr] = run(function ($n, $u) { return strpos($u, 'Инструменты больше нельзя') !== false ? ['do' => 'answer', 'say' => 'Сделал что смог.'] : ['tool' => 'web', 'args' => ['q' => 'город ' . $n]]; }, ['bg' => true], 'same');
t('фон: 12 шагов без нового результата — стоп, причина в ответе', count($tr) === 13 && strpos((string)$j['say'], 'Остановил задачу: нет продвижения — последние 12 шагов не дали ничего нового') === 0, [count($tr), $j]);
// 4) фон: продолжение после перерыва — уже сделанные повторы учитываются
$ent = []; for ($i = 0; $i < 2; $i++) $ent[] = ['j' => '{"tool":"web","args":{"q":"x"}}', 'n' => 'web', 'o' => 'r' . $i];
[$j, $tr] = run(function ($n, $u) { return strpos($u, 'Инструменты больше нельзя') !== false ? ['do' => 'answer', 'say' => 'ок'] : ['tool' => 'web', 'args' => ['q' => 'x']]; }, ['bg' => true, 'ent' => $ent]);
t('фон после перерыва: повторы из сохранённых шагов учитываются', count($tr) === 3 && !empty($j['stall']), [count($tr), $j]);
// 5) снимки: разговор — на всех шагах, фон — на первых 12
run(function ($n, $u) { return $n > 30 ? ['do' => 'answer', 'say' => 'ok'] : ['tool' => 'web', 'args' => ['q' => 'q' . $n]]; }, [], 'diff', [['m' => 'image/jpeg', 'd' => 'x', 'n' => 'a.jpg']]);
t('разговор: снимки видны на всех шагах', count($GLOBALS['IMG_SEEN']) === 31 && min($GLOBALS['IMG_SEEN']) === 1, $GLOBALS['IMG_SEEN']);
run(function ($n, $u) { return $n > 30 ? ['do' => 'answer', 'say' => 'ok'] : ['tool' => 'web', 'args' => ['q' => 'q' . $n]]; }, ['bg' => true], 'diff', [['m' => 'image/jpeg', 'd' => 'x', 'n' => 'a.jpg']]);
t('фон: снимки видны на первых 12 шагах', array_sum($GLOBALS['IMG_SEEN']) === 12 && $GLOBALS['IMG_SEEN'][11] === 1 && $GLOBALS['IMG_SEEN'][12] === 0, $GLOBALS['IMG_SEEN']);

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
