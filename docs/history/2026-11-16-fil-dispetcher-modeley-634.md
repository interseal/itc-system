# «Фил» — диспетчер китайских моделей ИИ по ролям (№634)
Когда: модели Фила, Qwen, Qwen-VL, GLM, Kimi, Moonshot, Doubao, MiniMax, Hunyuan, ERNIE, StepFun, Yi, роли, фото через Qwen, китайские модели, дневной лимит, cn_text
---
Поручение №26 (16.11.2026), только «Фил»; «Космос» не тронут (fr_on() = false при KS_MODEL0).
1) store/api/fil_roles.php: FR_PROV (адреса OpenAI-совместимые, международные), FR_MODELS (цена $/1М вход/выход, v — видит картинки), FR_ROLES: photo (qwen-vl-max → glm-4.5v), long (kimi-k2-0905-preview → qwen-plus, порог 30 000 знаков), cn (qwen-plus → glm-4.6, инструмент cn_text), easy (seed-1-6-flash-250715 → qwen-flash, только «спасибо/привет/ок» по FR_EASY_RE), main — DeepSeek.
2) flow.php (fc_cmd): fr_photo_att + fr_long_att к $att; fr_easy до ag_run; ag_run — fr_tool_res (files/pdf_text, chats, wechat_read, sup_history > 30 000); ag_tools + fr_tools; ds_ask → fr_ds_usage; после записи журнала fr_log_done (столбец itc_fc_log.ai). Журнал напарника — SELECT * + [модели: …].
3) Отказ/60 с/нет ключа/проверка не пройдена/лимит дня (fr_cfg limit, 5 $) → роль пропускается, делает DeepSeek.
4) Ключи: itc_secret frk_<поставщик>; Qwen без своего ключа берёт ключ рисовалки (config_art.php). Учёт: itc_fr_use.
5) Окно: upd.html карточка cFr, kb.php fr_status/fr_set/fr_key/fr_test. Проверка: текст, китайский, инструменты, картинка (FR_TEST_PNG «4827»).
6) Отсюда ключей нет — модели проверены только по адресам (401 = живой). Yi (api.lingyiwanwu.com) — 410, API закрыт, в список не включён. Проверки 235 ✓, раздел 33 (47 своих).
