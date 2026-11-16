# «Космос»: уровень сложности — глубина размышлений Claude (№632)
Когда: уровень сложности, глубина размышлений, extended thinking, budget_tokens, effort, Быстро Обычно Глубоко Максимум, ks_think, думает дольше, thinking 400
---
Поручение №24 (16.11.2026), только «Космос»; «Фил» (DeepSeek, flow.php) не менялся. Сделано поверх №631 (ветка kosmos/23 влита).
1) Уровни: fast «Быстро» (без размышлений), norm «Обычно» (по умолчанию, 4000), deep «Глубоко» (16000), max «Максимум» (32000). Haiku — без «Максимума». Хранение: sp ks_think = {модель: уровень}; fil_cfg {think, think_model} (только руководитель), в ответе think, thinks, models[].think/think_no.
2) store/apps/kosmos/index.html: блок «Уровень сложности» в окне mdlBox и в настройках (thkHtml/thkPick), шапка «Opus · Глубоко». sw.js v56.
3) fl_ai (make_kosmos_flow.py): уровень берут шаги разговора и фоновых задач (запас AG_ANS_TOK); мелкие служебные — fast. ks_gw_ask(…, $think): поле think, ждёт 240 / 660 / 870 с.
4) Шлюз 0.5.8: gw_think_put — budget_tokens, max_tokens = бюджет + запас (≥8000), без temperature; 400 → adaptive+effort → без размышлений, журнал gw_think_effort / gw_think_off, в ответе think/think_off. «Максимум» — поток до 840 с (claude_once, GW_STREAM_TMO). Шаг = одно сообщение user, блоки thinking между шагами не возвращаются — 400 не бывает.
Проверки 227 ✓: kosmos_think_test.php, раздел 31 (пробный Claude понимает thinking и отказы).
