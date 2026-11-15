<?php
// №628 · Проверка: ответы «Фила»/«Космоса» не обрываются.
// 1) история разговора (РАЗГОВОР ДО ЭТОГО): последние 3 реплики помощника — целиком, ранние — до 4000 знаков (было 700);
// 2) «Космос» (Claude): ответ упёрся в max_tokens — заново с запасом 16000, обрывок не показываем;
// 3) «Фил» (DeepSeek): finish_reason=length — так же заново с 16000;
// 4) озвучка: до 12000 знаков (было 1800 и 8 кусков), куски заказываются по 4 вперёд.
// Функции берём прямо из store/api/flow.php и kosmos_flow.php, всё вокруг — заглушки. Запуск: php tests/talk_cut_test.php
$root = dirname(__DIR__, 2);
require $root . '/store/api/ai_lib.php';
require $root . '/store/api/kosmos_lib.php';
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }
function fn_src($s, $name) { $a = strpos($s, "function $name("); if ($a === false) return ''; $b = strpos($s, "\n}\n", $a); return substr($s, $a, $b + 3 - $a); }

$src = (string)file_get_contents("$root/store/api/flow.php");
$ksrc = (string)file_get_contents("$root/store/api/kosmos_flow.php");
foreach (['flow.php' => $src, 'kosmos_flow.php' => $ksrc] as $fn => $s) {
  t("$fn: история через ag_talk, старой обрезки 700/400 нет", strpos($s, '$talk = ag_talk($hh);') !== false && strpos($s, "mb_substr(\$h['reply'], 0, 700)") === false && strpos($s, "mb_substr(\$h['said'], 0, 400)") === false);
  t("$fn: пределы истории 3 целиком / 4000 / 12000 / 1500", strpos($s, 'const AG_TALK_FULL = 3; const AG_TALK_CUT = 4000; const AG_TALK_MAX = 12000; const AG_TALK_SAID = 1500;') !== false);
}
preg_match_all('~^const (AG_TALK_FULL)\b[^\n]*~m', $src, $cm);
eval(implode("\n", $cm[0]) . "\n");
eval(fn_src($src, 'ag_talk'));

// 1) история
$long = function ($k, $n) { return str_repeat('ы', $n - 6) . "КОНЕЦ$k"; };
$hh = []; for ($k = 5; $k >= 1; $k--) $hh[] = ['said' => 'Вопрос ' . $k . str_repeat('в', 2000), 'reply' => $long($k, 6000)];   // как из базы: новые сначала
$talk = ag_talk($hh);
t('последние 3 ответа помощника — целиком (6000 знаков)', strpos($talk, 'КОНЕЦ3') !== false && strpos($talk, 'КОНЕЦ4') !== false && strpos($talk, 'КОНЕЦ5') !== false, mb_substr($talk, -300));
t('два ранних — до 4000 знаков с честной пометкой', strpos($talk, 'КОНЕЦ1') === false && strpos($talk, 'КОНЕЦ2') === false && substr_count($talk, 'сам ответ был полным') === 2);
t('порядок: от старой реплики к новой', strpos($talk, 'Вопрос 1') < strpos($talk, 'Вопрос 5'));
t('вопрос человека — до 1500 знаков (было 400)', preg_match('~Сотрудник: (Вопрос 5в+)~u', $talk, $m) && mb_strlen($m[1]) === 1500, mb_strlen($m[1] ?? ''));
$t2 = ag_talk([['said' => 'Да', 'reply' => $long(9, 20000)]]);
t('страховка: даже последняя реплика — не больше 12000', strpos($t2, 'КОНЕЦ9') === false && mb_strlen($t2) < 12200);
$t3 = ag_talk([['said' => 'Давай', 'reply' => 'Вывод для нас. Фил уже работает на DeepSeek, так что переход ничего не стоит. Делаю?']]);
t('короткий ответ — без пометок и без изменений', strpos($t3, 'так что переход ничего не стоит. Делаю?') !== false && strpos($t3, 'сокращено') === false);
t('kosmos_flow: реплики подписаны «Космос:»', strpos(fn_src($ksrc, 'ag_talk'), '"\nКосмос: "') !== false);

// 2) «Космос»: max_tokens
function ks_model_pick() { return 'claude-sonnet-5-5'; } function fil_model_name($m) { return $m; } function fl_fail($m) { throw new Exception($m); }
function test_ask($sys, $user, $mt, $model, $me) {   // вместо ks_gw_ask (сервер «Космоса»)
  $GLOBALS['MT'][] = $mt; $r = array_shift($GLOBALS['ANS']); $GLOBALS['KS_STOP'] = $r[1]; return [$r[0], '', 'Claude'];
}
preg_match('~^function ks_tok_first\(.*$~m', $ksrc, $tf); eval($tf[0]); preg_match('~^const KS_TOK_TOP = \d+;~m', $ksrc, $tc); eval($tc[0]);   // №630
eval(str_replace('ks_gw_ask(', 'test_ask(', fn_src($ksrc, 'fl_ai')));
$GLOBALS['MT'] = []; $GLOBALS['ANS'] = [['Для кода и агентов, то есть наших задач, почти все обзоры', 'max_tokens'], ['{"do":"answer","say":"Полный ответ с выводом."}', 'end_turn']];
$j = fl_ai('s', 'u', 4000, true);
t('Claude: обрывок по max_tokens не показываем — заново с запасом (№630: 64000, было 16000), приходит полный ответ', ($j['say'] ?? '') === 'Полный ответ с выводом.' && $GLOBALS['MT'] === [8000, 64000], [$j, $GLOBALS['MT']]);
$GLOBALS['MT'] = []; $GLOBALS['ANS'] = [['{"do":"answer","say":"Обычный ответ."}', 'end_turn']];
$j = fl_ai('s', 'u', 4000, true);
t('Claude: обычный ответ — одна попытка, как раньше', ($j['say'] ?? '') === 'Обычный ответ.' && $GLOBALS['MT'] === [8000], $GLOBALS['MT']);
$GLOBALS['MT'] = []; $GLOBALS['ANS'] = [['{"tool":"deals","args":{}}', 'end_turn']];
$j = fl_ai('s', 'u', 4000, true);
t('Claude: шаг с инструментом — без лишних попыток', ($j['tool'] ?? '') === 'deals' && count($GLOBALS['MT']) === 1);

// 3) «Фил»: DeepSeek finish_reason=length
function test_post($url, $req, $h, $to) {   // вместо itcai_post (она есть в ai_lib.php)
  $GLOBALS['DS'][] = json_decode($req, true)['max_tokens']; $r = array_shift($GLOBALS['DSA']);
  return [200, json_encode(['choices' => [['message' => ['content' => $r[0]], 'finish_reason' => $r[1]]]], JSON_UNESCAPED_UNICODE)];
}
function ds_key() { return 'x'; }
eval(str_replace('itcai_post(', 'test_post(', fn_src($src, 'ds_ask')));
$GLOBALS['DS'] = []; $GLOBALS['DSA'] = [['Вывод для нас. Фил уже работает на DeepSeek, та', 'length'], ['{"do":"answer","say":"Целиком."}', 'stop']];
[$j, $e] = ds_ask('deepseek-v4-pro', 's', 'u', 4000);
t('DeepSeek: ответ упёрся в длину — заново с 16000, обрывок не показываем', ($j['say'] ?? '') === 'Целиком.' && $GLOBALS['DS'] === [8000, 16000], [$j, $GLOBALS['DS']]);
$GLOBALS['DS'] = []; $GLOBALS['DSA'] = [['{"do":"answer","say":"Сразу."}', 'stop']];
[$j, $e] = ds_ask('deepseek-v4-pro', 's', 'u', 4000);
t('DeepSeek: обычный ответ — одна попытка', ($j['say'] ?? '') === 'Сразу.' && count($GLOBALS['DS']) === 1);

// 4) озвучка
foreach (['kosmos', 'fil'] as $app) {
  $v = (string)file_get_contents("$root/store/apps/$app/voice.js");
  t("$app/voice.js: озвучка до 12000 знаков и 80 кусков (было 1800 и 8)", strpos($v, ".substr(0, 12000)") !== false && strpos($v, 'out.slice(0, 80)') !== false && strpos($v, '.substr(0, 1800)') === false);
  t("$app/voice.js: куски заказываются по 4 вперёд, а не все сразу", strpos($v, 'function need(i)') !== false && strpos($v, 'parts.map(function (p) { return api(') === false);
}

echo "Итого: $ok ✓, $bad ✗\n";
exit($bad ? 1 : 0);
