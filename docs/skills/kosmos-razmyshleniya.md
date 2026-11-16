# Размышления Claude у «Космоса» (уровень сложности)
Когда: thinking, budget_tokens, effort, уровень сложности, ks_think, Claude думает, ошибка 400 thinking, temperature с thinking
---
• Уровень хранится в магазине: sp ks_think = {модель: fast|norm|deep|max}; выбирает ks_think_pick (по умолчанию norm, Haiku max → deep).
• Магазин шлёт на шлюз только название уровня (think). Бюджеты и запасные пути — в kosmos/lib/gw.php (GW_THINK, gw_think_put): меняй там.
• Правила Claude: budget_tokens ≥ 1024 и < max_tokens; с thinking нельзя temperature/top_k; размышления — это output_tokens (учёт денег сам).
• Отказ модели (400) → adaptive + output_config.effort → без размышлений, запись gw_think_off. «Фил» и мелкие служебные вызовы (maxTok < AG_ANS_TOK) — без размышлений.
• Шлюз шлёт историю шагов текстом в одном user-сообщении, поэтому блоки thinking назад возвращать не нужно. Если когда-нибудь перейдёшь на настоящие tool_use — блоки thinking (с signature) надо отдавать модели без изменений.
• Время: «Максимум» 32000 — предел из-за 900 с nginx/PHP на сервере «Космоса»; поток 840 с, магазин ждёт 870 с.
• Проверка: kosmos/tests/kosmos_think_test.php + раздел 31 run_tests.py (mock_claude.py: «Отказ размышлений», «Отказ бюджета»).
