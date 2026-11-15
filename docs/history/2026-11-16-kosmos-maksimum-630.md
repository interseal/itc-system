# «Космос»: переключатель модели и работа на максимуме (№630)
Когда: переключатель модели, Sonnet Opus Haiku в шапке, модель Космоса, лимиты Космоса, max_tokens 64000, обрезано, develop_status итог, до 6 предложений, таймаут шлюза, AG_LIM
---
Поручение №22 (16.11.2026), только «Космос»; «Фил» (DeepSeek) не менялся.
1) store/apps/kosmos/index.html: кнопка модели в шапке (mdlBtn) и пункт «Модель» в меню (miMdl) — окно mdlBox: Sonnet (рекомендуемая), Opus («сильнее, дороже»), Haiku. Сохраняет fil_cfg {model} → тот же ks_model, что в настройках; mdlShow() после любого сохранения. Видно руководителю (fil_cfg — только он). WebView грузит ту же страницу — одно меню на браузер и Android. sw.js v55.
2) Пределы: AG_LIM / AG_HIST_MAX в flow.php (значения «Фила»), make_kosmos_flow.py шаг 13 подставляет «Космосу» большие: AG_MULTI 8, AG_TOOL_CUT 32000, AG_TOOL_OLD 4000, AG_HIST_MAX 300000 (ранние результаты ужимаются со старых), ответ «длина — по задаче». fil_edit.php и sup_hist.php берут ag_lim через function_exists.
3) Токены: ks_tok_first (Opus/Fable 32000), KS_TOK_TOP 64000. Шлюз 0.5.6: GW_TOK_MAX 64000, больше 16000 — потоком (gw_claude), 400 → 32000; магазин ждёт 660 с (Opus или >16000). dev.php: list n до 50, итог до 20000.
Проверки 215 ✓: kosmos/tests/kosmos_max_test.php, раздел 29.
