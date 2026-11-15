# Фил и Космос: технический мусор <invoke> в ленте вместо ответа (№622)
Когда: лента Фила Космоса мусор invoke </invoke> invoke_placeholder function_calls parameter develop_status ответ без разметки не разобрался повтор строго один JSON Не получилось ответить
---
15.11.2026, поручение руководителя (видел мусор на вопросы про №621 и Chrome через Сингапур). Выпуск №622 (№621 занят веткой kosmos/8).
- Причина: у «Космоса» ks_ai_json (store/api/kosmos_lib.php) ответ без «{» целиком считал ответом человеку — теги <invoke name="develop_status">… шли в ленту и в журнал itc_ks_log. У «Фила» (itcai_json) JSON внутри say не чистился; при неудаче сырой кусок ответа уходил руководителю в тексте ошибки («начало ответа: «…»»).
- store/api/ai_lib.php: itcai_clean (вырезает парные блоки с содержимым, одиночные/оборванные теги, пустые строки), itcai_invoke (<invoke name=X><parameter name=k>v → {"tool":X,"args":{k:v}}), itcai_step_clean (чистит say/text/…; answer/ask с пустым say → null), itcai_step, ITCAI_ONE_JSON (напоминание для повтора).
- flow.php: ds_ask и Яндекс — itcai_step + одна повторная попытка с напоминанием; FL_AI_BAD=1 → «Не получилось ответить, повторите запрос.»; say чистится перед itc_fc_log и в фоновых задачах. Генератор Космоса — так же (повтор с напоминанием, сырой текст в ошибке убран).
- Проверка: kosmos/tests/ai_clean_test.php (23), подключена в run_tests.py (раздел 22).
