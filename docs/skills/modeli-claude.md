# Модели Claude у «Космоса»: проверить, добавить, цена
Когда: новая модель Claude, добавить модель, какие модели доступны, цена модели, thinking budget_tokens 400, effort, предыдущая версия
---
• Какие модели есть по ключу: GET https://api.anthropic.com/v1/models?limit=100 (x-api-key, anthropic-version) — там max_input_tokens, max_tokens и capabilities.thinking.types (enabled = budget_tokens, adaptive = effort), image_input, effort-уровни.
• Ключ в коде не писать; в окружении разработчика есть ANTHROPIC_API_KEY — пробные запросы делай короткими (max_tokens 50–200).
• Добавить модель: строка в KS_MODELS (kosmos/tools/make_kosmos_flow.py: id, имя, короткое, подпись, метка цены, main/old) → пересобрать kosmos_flow.php; MODEL_LIST в kosmos/lib/boot.php; цена — kosmos/lib/price.php (PRICE_IDS по началу id, новые — PRICE_NEW); способ размышлений — gw_think_how в kosmos/lib/gw.php.
• Цены: platform.claude.com/docs/en/about-claude/pricing. Метка «дороже Opus ×N» — относительно Opus 5.5 ($4/$20).
• У моделей 4.5 (Opus 4.5, Haiku 4.5) окно 200 тыс. токенов, у остальных 1 млн. Haiku — без «Максимума».
