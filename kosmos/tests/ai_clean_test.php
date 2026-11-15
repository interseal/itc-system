<?php
// №621 · Проверка: служебные теги (<invoke…>, <function_calls>, <parameter…>) не попадают в ленту «Фила» и «Космоса»,
// вызов <invoke name="X"> превращается в обычный шаг {"tool":"X","args":{…}}. Запуск: php tests/ai_clean_test.php
$root = dirname(__DIR__, 2);
require $root . '/store/api/ai_lib.php';
require $root . '/store/api/kosmos_lib.php';
$ok = 0; $bad = 0;
function t($name, $cond, $extra = null) { global $ok, $bad; if ($cond) { $ok++; echo "  ✓ $name\n"; } else { $bad++; echo "  ✗ $name " . json_encode($extra, JSON_UNESCAPED_UNICODE) . "\n"; } }

$junk = "<invoke name=\"develop_status\">\n</invoke>\n<invoke_placeholder>\n\n\n\n</invoke>\n<invoke_placeholder>";
t('мусор из тегов → пусто', itcai_clean($junk) === '', itcai_clean($junk));
t('текст вокруг тегов остаётся, пустые строки схлопнуты', itcai_clean("Готово.\n\n\n\n<function_calls>\n<invoke name=\"x\">\n<parameter name=\"a\">1</parameter>\n</invoke>\n</function_calls>\n\n\nДальше.") === "Готово.\n\nДальше.");
t('оборванный тег в конце вырезан', itcai_clean('Ответ. <invoke name="develop_st') === 'Ответ.');
t('оборванный параметр в конце — со значением', itcai_clean('Ок <invoke name="a"><parameter name="q">abc') === 'Ок');
t('с приставкой пространства имён', itcai_clean('<ns:function_calls><ns:invoke name="a"><ns:parameter name="q">x</ns:parameter></ns:invoke></ns:function_calls>Ок') === 'Ок');
t('обычный текст с < не трогаем', itcai_clean('Цена < 5 $ и <b>жирный</b>') === 'Цена < 5 $ и <b>жирный</b>');

$inv = itcai_invoke("<function_calls>\n<invoke name=\"deals\">\n<parameter name=\"q\">SNADI</parameter>\n<parameter name=\"limit\">5</parameter>\n<parameter name=\"f\">{\"a\":1}</parameter>\n</invoke>\n</function_calls>");
t('<invoke> → tool и args', $inv === ['tool' => 'deals', 'args' => ['q' => 'SNADI', 'limit' => 5, 'f' => ['a' => 1]]], $inv);
t('<invoke> без параметров', itcai_invoke('<invoke name="develop_status">') === ['tool' => 'develop_status', 'args' => []]);
t('нет <invoke> → null', itcai_invoke('просто текст') === null);
t('<invoke_placeholder> — не вызов', itcai_invoke('<invoke_placeholder>') === null);

// «Фил»: разбор шага
t('Фил: JSON с мусором вокруг → ответ', (itcai_step("<invoke_placeholder>\n{\"do\":\"answer\",\"say\":\"Привет\"}\n</invoke>")['say'] ?? '') === 'Привет');
t('Фил: say чистится от тегов', (itcai_step('{"do":"answer","say":"Обновление 621 готово. <invoke name=\"develop_status\"></invoke>"}')['say'] ?? '') === 'Обновление 621 готово.');
t('Фил: say из одних тегов → null (будет повтор)', itcai_step('{"do":"answer","say":"<invoke name=\"x\"></invoke>"}') === null);
t('Фил: только мусор → null', itcai_step($junk . "\n</invoke>") === null || (itcai_step($junk)['tool'] ?? '') === 'develop_status');
t('Фил: вызов тегами → tool', (itcai_step('<invoke name="develop_status"></invoke>')['tool'] ?? '') === 'develop_status');
t('Фил: другие ответы (без say) не ломаются', itcai_step('{"do":"Позвонить клиенту","at":"2026-11-16"}') === ['do' => 'Позвонить клиенту', 'at' => '2026-11-16']);

// «Космос»
t('Космос: обычный текст без JSON — ответ', ks_ai_json('Добрый день, Босс.') === ['do' => 'answer', 'say' => 'Добрый день, Босс.']);
t('Космос: мусор → null (будет повтор, не в ленту)', ks_ai_json("<invoke_placeholder>\n</invoke>\n<invoke_placeholder>\n</invoke>") === null);
t('Космос: <invoke name> → tool', ks_ai_json("Сейчас посмотрю.\n<invoke name=\"develop_status\">\n</invoke>") === ['tool' => 'develop_status', 'args' => []]);
t('Космос: JSON важнее тегов', (ks_ai_json("<invoke_placeholder>\n{\"tool\":\"deals\",\"args\":{\"q\":\"x\"}}")['tool'] ?? '') === 'deals');
t('Космос: say чистится', (ks_ai_json('{"do":"answer","say":"Chrome через Сингапур работает.\n\n\n\n</invoke>"}')['say'] ?? '') === 'Chrome через Сингапур работает.');
t('Космос: JSON как раньше', ks_ai_json("```json\n{\"do\":\"ask\",\"say\":\"Какой?\"}\n```") === ['do' => 'ask', 'say' => 'Какой?']);
t('напоминание «строго один JSON» есть', strpos(ITCAI_ONE_JSON, 'строго один JSON') !== false);

echo "\nИтог: $ok прошло, $bad не прошло\n";
exit($bad ? 1 : 0);
