# Фил на компьютере: WeChat сотрудника
Когда: прочитай переписку WeChat, скачай каталоги из WeChat, напиши поставщику, подключить компьютер, Фил не видит WeChat
---
1) Установка: itcpartner.store/app/pc/ на этом компьютере, войдя в Экосистему под сотрудником → «Установить Фила.cmd» (Windows, без администратора) — подключится сам (личная ссылка, pc_inst → pc_pair_auto). Без входа — код из 6 цифр → pc_pair.
2) Чтение: wechat_read {chats, days} → поручение компьютеру; переписка — в itc_wc_* (emp_id pc:…), файлы — pc_file → itc_fc_file + история поставщика. Анализ — chats / sup_history.
3) Письмо: показать текст (китайский) и перевод, спросить «отправить?»; после «да» — wechat_send {chat, text, ru}.
4) Проблемы: wechat_status (на связи ли, версия WeChat, способ: uia3 / uia4 / ocr), журнал на компьютере — %LOCALAPPDATA%\ITC\FilPC\fil_pc.log. Отключить — pc_unpair.
5) Правка программы: desktop/fil_pc → python3 kosmos/tools/build_pc_zip.py (APP_VER в core.py) → выпуск с store/app/pc/fil_pc.zip и version.json; компьютеры обновятся сами.
