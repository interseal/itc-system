#!/usr/bin/env python3
"""«Космос» в магазине (api/kosmos_flow.php — копия «Фила» на Claude) — проверка целиком у себя:
магазин (PHP + MariaDB, поддельный config.php) → шлюз → сервер «Космоса» (php -S) → пробный Claude.
Нужно: запущенный MariaDB с базой itc (пользователь itc/itcpass) и папка магазина /tmp/kft/store
(api/ из выгрузки + flow.php, kosmos_flow.php, kosmos_lib.php, kosmos.php, поддельный config.php).
Запуск: python3 tests/flow_e2e.py
"""
import json, os, shutil, subprocess, sys, time, urllib.request, urllib.error, urllib.parse, sqlite3, hashlib, glob

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ST = '/tmp/kft/store'
STORE = 'http://127.0.0.1:8792'
KOS = 'http://127.0.0.1:8793'
TMP = '/tmp/kft/kos'
shutil.rmtree(TMP, ignore_errors=True); os.makedirs(TMP)
open(f'{TMP}/master.key', 'wb').write(os.urandom(48))
open(f'{TMP}/config.php', 'w').write(f'''<?php return [
  'db' => '{TMP}/kosmos.sqlite', 'master_key_file' => '{TMP}/master.key', 'store_token_file' => '{TMP}/no_token',
  'gw_key_file' => '{TMP}/gw.key', 'claude_url' => 'http://127.0.0.1:8768/v1/messages',
  'dev_users' => ['boss-token' => ['+996555000001', 'Алексей', 'boss', true]],
];''')
subprocess.run(['mysql', '-uroot', 'itc', '-e', 'DROP TABLE IF EXISTS itc_ks_log, itc_ks_conv, itc_ks_task, itc_fc_log, itc_fil_conv, itc_fil_task, itc_skill, itc_skill_copy; DELETE FROM itc_secret WHERE k LIKE "ks_%" OR k LIKE "fil_%" OR k = "sp_voice" OR k LIKE "nvk_%"'], capture_output=True)
for f in glob.glob(f'{ST}/uploads/kosmos/*/gw.json'): os.remove(f)
for f in glob.glob(f'{ST}/uploads/kosmos/*/ready/*'): os.remove(f)   # готовые обновления прошлых проверок
env = dict(os.environ, KOSMOS_CONFIG=f'{TMP}/config.php', PHP_CLI_SERVER_WORKERS='4')
procs = [subprocess.Popen([sys.executable, f'{ROOT}/tests/mock_claude.py', '8768', f'{TMP}/mock.log']),
         subprocess.Popen(['php', '-S', '127.0.0.1:8793', '-t', f'{ROOT}/www'], env=env, stdout=open(f'{TMP}/php.log', 'w'), stderr=subprocess.STDOUT),
         subprocess.Popen(['php', '-S', '127.0.0.1:8792', '-t', ST], env=dict(os.environ, PHP_CLI_SERVER_WORKERS='4'), stdout=open(f'{TMP}/store.log', 'w'), stderr=subprocess.STDOUT)]
time.sleep(1.3)
ok_n = fail_n = 0
def check(name, cond, extra=''):
    global ok_n, fail_n
    if cond: ok_n += 1; print('  ✓', name)
    else: fail_n += 1; print('  ✗', name, str(extra)[:700])
def http(url, data=None, headers=None, timeout=90):
    b = json.dumps(data).encode() if data is not None else None
    req = urllib.request.Request(url, data=b, headers={'Content-Type': 'application/json', **(headers or {})}, method='POST' if b is not None else 'GET')
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r: raw = r.read(); c = r.status
    except urllib.error.HTTPError as e: raw = e.read(); c = e.code
    try: return c, json.loads(raw)
    except Exception: return c, {'_raw': raw[:400].decode('utf-8', 'replace')}
def kf(action, tok='boss-t', data=None, flow='kosmos_flow'):
    return http(f'{STORE}/api/{flow}.php?action={action}&t={tok}', data if data is not None else {})

try:
    print('1. Связь магазина и сервера «Космоса»')
    # служебный ключ сервера, как после установки: в магазине — только отпечаток
    tok = 'srv' * 22
    priv = None
    c, j = http(f'{STORE}/api/kosmos.php?a=status&t=boss-t', {})   # создаст закрытую папку
    priv = [d for d in glob.glob(f'{ST}/uploads/kosmos/*') if os.path.isdir(d) and len(os.path.basename(d)) == 32][0]
    json.dump({'sha': hashlib.sha256(tok.encode()).hexdigest()}, open(f'{priv}/auth.json', 'w'))
    c, j = http(f'{STORE}/api/kosmos.php?a=report', {'ver': '0.2.0', 'https': True}, {'X-Kosmos-Token': tok})
    check('отчёт сервера: магазин отдаёт ключ шлюза и адрес фоновых задач', c == 200 and len(j.get('gw', '')) == 64 and 'kosmos_flow.php?action=ft_cron&k=' in j.get('cron', ''), j)
    gw, cron = j['gw'], j['cron']
    c, j2 = http(f'{STORE}/api/kosmos.php?a=report', {'ver': '0.2.0'}, {'X-Kosmos-Token': tok})
    check('ключ шлюза постоянный (не меняется каждую минуту)', j2.get('gw') == gw, j2)
    c, j = http(f'{STORE}/api/kosmos.php?a=report', {}, {'X-Kosmos-Token': 'alien' * 10})
    check('чужой сервер ключ шлюза не получит', c == 403 and 'gw' not in j, j)
    c, j = kf('fil_cfg')
    check('пока сервер не получил ключ шлюза — настройки честно говорят «не связан»', c == 200 and j['ks']['online'] and not j['ks']['ok'], j.get('ks'))
    open(f'{TMP}/gw.key', 'w').write(gw + '\n')          # так делает агент сервера (a_gw_save)

    print('2. Ключ Claude и настройки «Космоса» (руководитель)')
    c, j = http(f'{KOS}/api.php?a=key_add', {'name': 'Аккаунт 1', 'key': 'sk-ant-good-' + 'a' * 26 + 'AAAA'}, {'X-Eco-Token': 'boss-token'})
    check('ключ Claude добавлен на сервере «Космоса»', j.get('ok') and j['test']['ok'], j)
    c, j = kf('fil_cfg')
    check('настройки: сервер на связи, ключ «Аккаунт 1»', c == 200 and j['ks']['ok'] and j['ks']['active'] == 'Аккаунт 1', j.get('ks'))
    check('настройки: модели Claude, по умолчанию Sonnet', j['model'] == 'claude-sonnet-5-5' and all(m['id'].startswith('claude-') for m in j['models']), (j['model'], j['models']))
    check('настройки: DeepSeek в «Космосе» нет', j['ds'] == {'has': False}, j['ds'])
    check('адрес настроек ключей Claude', j.get('ks_admin', '').startswith('http'), j.get('ks_admin'))
    c, j = kf('fil_cfg', 'mgr-t'); check('настройки «Космоса» — только руководитель', c == 403, (c, j))

    print('3. Общая память «Фила» и «Космоса» (№536)')
    # как было после №533: у «Космоса» своя копия правил (с его именем), кое-что он выучил отдельно
    c, j = kf('fil_cfg', data={'memo': 'Фил, отчёты всегда пиши коротко.\n- Филу нельзя обещать сроки.', 'learn': '- «с Нади» — это фабрика SNADI (12.11.2026, Алексей)'}, flow='flow')
    check('памятка и выученное «Фила» записаны', c == 200 and j['ok'], j)
    sql = ("DELETE FROM itc_secret WHERE k='ks_mem_merged'; "   # как первый запуск после обновления №536
           "REPLACE INTO itc_secret (k,v,who) VALUES ('ks_memo','Космос, отчёты всегда пиши коротко.\\n- Космосу нельзя обещать сроки.\\n- Счета клиентам — только в рублях.','test'),"
           "('ks_learn','- «с Нади» — это фабрика SNADI (12.11.2026, Алексей)\\n- «Пекрон» — это фабрика PECRON (13.11.2026, Алексей)','test')")
    subprocess.run(['mysql', '-uroot', 'itc', '-e', sql], check=True)
    c, j = kf('fil_cfg')
    check('выученное «Космосом» отдельно перешло в общую память, без повторов', j['learn'].count('SNADI') == 1 and 'PECRON' in j['learn'], j['learn'])
    check('памятка: новое правило перешло, строки «с другим именем» не задвоились', 'только в рублях' in j['memo'] and j['memo'].count('обещать сроки') == 1, j['memo'][:300])
    c, j = kf('fil_cfg', flow='flow')
    check('«Фил» видит то же самое', 'PECRON' in j['learn'] and 'только в рублях' in j['memo'], (j['learn'], j['memo'][:200]))
    c, j = kf('fil_cfg', data={'memo': j['memo'] + '\n- Новое правило от Космоса.'})
    c, j = kf('fil_cfg', flow='flow'); check('памятку поправили в «Космосе» — видно в «Филе»', 'Новое правило от Космоса' in j['memo'], j['memo'][-120:])
    c, j = kf('fil_cfg', data={'learn': j['learn'] + '\n- «Метро» — это гостиница Метрополь (13.11.2026, Алексей)'}, flow='flow')
    c, j = kf('fil_cfg'); check('выучил «Фил» — знает и «Космос»', 'гостиница Метрополь' in j['learn'], j['learn'][-160:])
    r = subprocess.run(['mysql', '-uroot', 'itc', '-N', '-e', "SELECT v FROM itc_secret WHERE k='ks_mem_merged'"], capture_output=True, text=True)
    check('перенос памяти — один раз', r.stdout.strip() == '1', r.stdout)
    c, j = kf('fil_cfg', data={'model': 'claude-haiku-5-5'}); check('модель меняется на Claude Haiku', j['model'] == 'claude-haiku-5-5', j['model'])
    c, j = kf('fil_cfg', data={'model': 'deepseek-v4'}); check('чужая модель не принимается', j['model'] == 'claude-haiku-5-5', j['model'])
    kf('fil_cfg', data={'model': 'claude-sonnet-5-5'})

    print('4. Команда «Космосу» — отвечает Claude, переписка своя')
    n0 = len(open(f'{TMP}/mock.log').read().splitlines()) if os.path.exists(f'{TMP}/mock.log') else 0
    c, j = kf('fc_cmd', data={'text': 'Привет, кто ты?', 'conv': 'new', 'rid': 'test12345'})
    check('ответ пришёл', c == 200 and j.get('ok'), j)
    say = j.get('say', '')
    check('отвечает «Космос», знает что он на Claude, ключ Аккаунт 1', 'Я Космос' in say and 'На Claude: да' in say and 'AAAA' in say, say)
    rec = [json.loads(l) for l in open(f'{TMP}/mock.log')][n0:]
    check('к Claude ушёл запрос с кешем правил, модель Sonnet', rec and rec[-1]['cache'] and rec[-1]['model'] == 'claude-sonnet-5-5', rec[-1:] )
    conv = j.get('conv', '')
    c, j = kf('fc_hist', data={'conv': conv})
    check('в истории «Космоса» — этот разговор', c == 200 and any('кто ты' in (x.get('said') or '') for x in j.get('items', [])), j)
    c, j = kf('fc_hist', flow='flow')
    check('в истории «Фила» его нет (переписка раздельная)', not any('кто ты' in (x.get('said') or '') for x in j.get('items', [])), j)
    c, j = kf('fm_convs')
    check('разговор виден в меню «Космоса»', c == 200 and j.get('ok'), j)

    print('5. Доступ и фоновые задачи')
    c, j = kf('fc_cmd', 'wh-t', {'text': 'Привет', 'conv': 'new'})
    check('склад: «Космос» не отвечает, понятная причина', not j.get('ok') and 'руководитель' in j.get('error', ''), j)
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Что у меня сегодня?', 'conv': 'new'})
    check('менеджер получает ответ', c == 200 and j.get('ok') and 'Я Космос' in j.get('say', ''), j)
    c, j = http(cron.replace('http://127.0.0.1:8792', STORE))
    check('сервер будит фоновые задачи «Космоса» по своему адресу', c == 200, (c, j))
    c, j = http(f'{STORE}/api/kosmos_flow.php?action=ft_cron&k=' + '0' * 32)
    check('чужой адрес фоновых задач — отказ', c == 403, c)
    c, j = kf('ft_cron_url'); check('в настройках: задачи будит сервер, вызов был', j.get('auto') and j.get('seen'), j)
    c, j = http(f'{STORE}/api/kosmos_flow.php?action=wc_in')
    check('чужие входы «Фила» у «Космоса» закрыты', c == 404, c)
    c, j = kf('sp_status'); check('голос «Космоса» — свой (Антон)', c == 200 and j.get('voice') == 'anton', j)

    print('6. Ключ сломался — Claude сам переключается на запасной')
    http(f'{KOS}/api.php?a=key_add', {'name': 'Запасной', 'key': 'sk-ant-good-' + 'b' * 26 + 'BBBB'}, {'X-Eco-Token': 'boss-token'})
    c, k = http(f'{KOS}/api.php?a=key_add', {'name': 'Сломанный', 'key': 'sk-ant-bad-' + 'c' * 30}, {'X-Eco-Token': 'boss-token'})
    http(f'{KOS}/api.php?a=key_activate', {'id': k['key']['id']}, {'X-Eco-Token': 'boss-token'})
    c, j = kf('fc_cmd', data={'text': 'Проверка запасного ключа', 'conv': 'new'})
    check('ответ пришёл с рабочего ключа', c == 200 and j.get('ok') and ('AAAA' in j.get('say', '') or 'BBBB' in j.get('say', '')), j)
    c, j = kf('fil_cfg'); check('активным стал рабочий ключ', j['ks']['active'] in ('Аккаунт 1', 'Запасной'), j['ks'])

    print('8. Каждый знает, что делал другой (№536)')
    subprocess.run(['mysql', '-uroot', 'itc', '-e', "INSERT INTO itc_fc_log (phone,who,said,reply,kind,deal_id) VALUES ('+996555000001','Алексей','Сделай КП для Метрополя на полотенца','КП для Метрополя готово: 3 позиции, 412 000 руб.','answer','')"], check=True)
    c, j = kf('fc_cmd', data={'text': 'Что делал Фил?', 'conv': 'new'})
    check('«Космос» в правилах видит недавние дела «Фила»', c == 200 and j.get('ok') and 'КП для Метрополя' in j.get('say', '') and 'Фил' in j.get('say', ''), j)
    c, j = kf('fc_cmd', data={'text': 'Посмотри журнал по Метрополю', 'conv': 'new'})
    check('инструмент journal: общий журнал двух помощников', c == 200 and j.get('ok') and 'Метрополя' in j.get('say', '') and j.get('looked') == ['journal'], j)
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Что делал Фил?', 'conv': 'new'})
    check('сотрудник видит в журнале только свои дела', c == 200 and j.get('ok') and 'Метрополя' not in j.get('say', ''), j)
    rows = subprocess.run(['mysql', '-uroot', 'itc', '-N', '-e', "SELECT COUNT(*) FROM itc_ks_log WHERE said LIKE '%журнал по Метрополю%'"], capture_output=True, text=True).stdout.strip()
    check('запросы к «Космосу» лежат в его журнале — их увидит «Фил»', rows == '1', rows)
    src = open(f'{ST}/api/flow.php', encoding='utf-8').read()
    check('«Фил» читает журнал «Космоса» (itc_ks_log) и показывает его дела в правилах', "const AG_PEER = ['log' => 'itc_ks_log', 'name' => 'Космос'" in src and '. ag_peer_block($me)' in src)

    print('9. Голоса: у каждого свой, одним голосом нельзя (№537)')
    def sql(q): subprocess.run(['mysql', '-uroot', 'itc', '-e', q], check=True)
    sql("DELETE FROM itc_secret WHERE k IN ('sp_voice','ks_voice')")
    c, k = kf('sp_status'); c2, f = kf('sp_status', flow='flow')
    check('«Космос»: Антон, видит голос «Фила» (Кирилл)', k.get('voice') == 'anton' and k.get('peer') == 'Фил' and k.get('peer_gen') == 'Фила' and k.get('peer_voice') == 'kirill', k)
    check('«Фил»: Кирилл, видит голос «Космоса» (Антон)', f.get('voice') == 'kirill' and f.get('peer') == 'Космос' and f.get('peer_voice') == 'anton', f)
    c, j = kf('sp_voice_set', data={'voice': 'kirill'})
    check('«Космосу» нельзя взять голос «Фила» — понятная причина', c == 400 and 'говорит Фил' in j.get('error', ''), (c, j))
    c, j = kf('sp_voice_set', 'mgr-t', {'voice': 'zahar'}); check('голос выбирает только руководитель', c == 403, (c, j))
    c, j = kf('sp_voice_set', data={'voice': 'zahar'}); check('«Космос» говорит голосом Захар', c == 200 and j.get('voice') == 'zahar', j)
    c, k = kf('sp_status'); c, f = kf('sp_status', flow='flow')
    check('«Фил» видит новый голос «Космоса», свой не изменился', k.get('voice') == 'zahar' and f.get('peer_voice') == 'zahar' and f.get('voice') == 'kirill', (k, f))
    c, j = kf('sp_voice_set', data={'voice': 'zahar'}, flow='flow'); check('«Филу» нельзя взять голос «Космоса»', c == 400 and 'говорит Космос' in j.get('error', ''), (c, j))
    c, j = kf('sp_voice_set', data={'voice': 'alena'}, flow='flow'); check('«Фил» говорит голосом Алёна', c == 200 and j.get('voice') == 'alena', j)
    c, k = kf('sp_status'); check('у «Космоса» голос прежний', k.get('voice') == 'zahar' and k.get('peer_voice') == 'alena', k)
    # как могло быть до №537: «Филу» когда-то выбрали Антона, а «Космосу» голос не выбирали — совпали бы
    sql("DELETE FROM itc_secret WHERE k='ks_voice'; REPLACE INTO itc_secret (k,v,who) VALUES ('sp_voice','anton','test')")
    c, k = kf('sp_status'); check('совпал бы с «Филом» — «Космос» сам берёт запасной (Филипп)', k.get('voice') == 'filipp' and k.get('peer_voice') == 'anton', k)
    c, j = kf('sp_voice_set', data={'voice': 'kirill'}, flow='flow')
    c, k = kf('sp_status'); check('«Филу» сменили голос — голос «Космоса» сам не поменялся', k.get('voice') == 'filipp' and k.get('peer_voice') == 'kirill', k)
    c, j = kf('sp_say', data={'text': 'Проверка', 'voice': 'jane'})
    check('послушать голос — без ключа Яндекса понятная ошибка', c == 400 and 'не подключ' in j.get('error', ''), (c, j))
    sql("DELETE FROM itc_secret WHERE k IN ('sp_voice','ks_voice')")

    print('10. Навыки: «Космос» записывает способ, «Фил» делает так же; на сервере «Космоса» и копия в магазине (№538–№540)')
    def q1(qs): return subprocess.run(['mysql', '-uroot', 'itc', '-N', '-e', qs], capture_output=True, text=True).stdout.strip()
    def kq(qs):   # база сервера «Космоса» (SQLite) — там и только там лежат навыки
        con = sqlite3.connect(f'{TMP}/kosmos.sqlite'); r = con.execute(qs).fetchall(); con.close(); return r
    # как осталось после №538: навык в базе магазина — при первом обращении переезжает на сервер, таблица в магазине удаляется
    q1("DELETE FROM itc_secret WHERE k IN ('nvk_moved','nvk_copy_at'); DROP TABLE IF EXISTS itc_skill, itc_skill_copy; CREATE TABLE itc_skill (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL DEFAULT '', trig VARCHAR(600) NOT NULL DEFAULT '', steps TEXT NULL, trace VARCHAR(600) NOT NULL DEFAULT '', author VARCHAR(60) NOT NULL DEFAULT '', who VARCHAR(190) NOT NULL DEFAULT '', manual TINYINT NOT NULL DEFAULT 0, uses INT NOT NULL DEFAULT 0, used_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, deleted TINYINT NOT NULL DEFAULT 0); INSERT INTO itc_skill (title,trig,steps,trace,author,who,manual,uses) VALUES ('Сверка счёта поставщика','прислали счёт фабрики','1) suppliers — найти фабрику; 2) deals — сделку; сверить суммы','suppliers → deals','Космос','Алексей',1,2)")
    c, j = kf('fc_cmd', data={'text': 'Проверка навыка: подготовь КП для гостиницы Метрополь', 'conv': 'new'})
    check('«Космос» решил задачу в 2 шага и записал навык', c == 200 and j.get('ok') and 'Записал навык «КП для гостиницы» — теперь так сможет и Фил' in j.get('say', '') and j.get('looked') == ['deals', 'suppliers'], j)
    check('навык — на сервере «Космоса»: записал «Космос», инструменты по порядку', kq("SELECT title||'|'||author||'|'||trace||'|'||manual FROM skills WHERE deleted=0 AND title='КП для гостиницы'") == [('КП для гостиницы|Космос|deals → suppliers|0',)], kq('SELECT * FROM skills'))
    check('навык из базы магазина (№538) переехал на сервер со счётчиком и правкой руководителя', kq("SELECT uses, manual, author FROM skills WHERE title='Сверка счёта поставщика'") == [(2, 1, 'Космос')], kq('SELECT title, uses, manual FROM skills'))
    check('старая таблица №538 в магазине удалена после переноса', q1("SHOW TABLES LIKE 'itc_skill'") == '' and q1("SELECT v FROM itc_secret WHERE k='nvk_moved'") == '1', q1("SHOW TABLES LIKE 'itc_skill'"))
    def same():   # копия в магазине = навыки на сервере (номера, названия, шаги)
        srv = sorted((r[0], r[1], r[2]) for r in kq('SELECT id, title, steps FROM skills WHERE deleted=0'))
        cp = sorted(tuple(l.split('\t')) for l in q1('SELECT sid, title, steps FROM itc_skill_copy').split('\n') if l)
        return [(str(a), b, c) for a, b, c in srv] == cp, (srv, cp)
    ok, why = same(); check('копия в магазине совпадает с сервером (№540)', ok and len(why[0]) == 2, why)
    c, j = kf('nvk_list', data={'q': 'Сделай коммерческое для отеля Европа, КП нужно сегодня'}, flow='flow')
    check('«Фил» находит навык «Космоса» по похожей просьбе (спрашивает сервер)', c == 200 and [x['title'] for x in j.get('items', [])] == ['КП для гостиницы'], j)
    check('у «Фила» навык попадёт в правила (кто записал, шаги, инструменты), а писать новые ему не велят', 'КП для гостиницы (записал Космос)' in j.get('block', '') and 'Инструменты по порядку: deals → suppliers' in j.get('block', '') and 'ЗАПИШИ НАВЫК' not in j.get('block', ''), j.get('block'))
    check('в настройках сказано, где хранятся навыки', 'в двух местах: на сервере «Космоса» в Сингапуре и копия в магазине' in j.get('note', ''), j.get('note'))
    c, j = kf('nvk_list', data={'q': 'Сделай КП для отеля Европа'})
    check('у «Космоса» в правилах — навык и как записывать новые для «Фила»', 'КП для гостиницы' in j.get('block', '') and 'ЗАПИШИ НАВЫК' in j.get('block', '') and 'будет работать и Фил' in j.get('block', ''), j.get('block'))
    c, j = kf('nvk_list', data={'q': 'Какая завтра погода в Бишкеке'}, flow='flow')
    check('на постороннюю просьбу навыков не подсовывает', c == 200 and j.get('items') == [] and j.get('block') == '', j)
    c, j = kf('fc_cmd', data={'text': 'Проверка навыка повторно: КП для гостиницы Астория', 'conv': 'new'})
    sid = kq("SELECT id FROM skills WHERE title='КП для гостиницы' AND deleted=0")[0][0]
    check('навык в правилах — сделал по нему и отметил', c == 200 and j.get('ok') and f'по навыку №{sid}' in j.get('say', ''), j)
    check('счётчик применений вырос (на сервере)', kq(f'SELECT uses FROM skills WHERE id={sid}') == [(1,)])
    c, j = kf('fc_cmd', data={'text': 'Проверка навыка: подготовь КП для гостиницы Метрополь', 'conv': 'new'})
    check('тот же навык второй раз не задваивается — обновляется', 'Обновил навык «КП для гостиницы»' in j.get('say', '') and kq("SELECT COUNT(*) FROM skills WHERE deleted=0 AND title='КП для гостиницы'") == [(1,)], j)
    c, j = kf('nvk_save', data={'id': sid, 'title': 'КП для гостиницы', 'when': 'КП для гостиницы или отеля', 'steps': '1) deals — сделка; 2) suppliers — фабрики; 3) make_kp — КП; проверить цены за единицу'}, flow='flow')
    check('руководитель поправил навык в «Филе»', c == 200 and j.get('ok') and any(x['steps'].endswith('проверить цены за единицу') for x in j.get('items', [])), j)
    c, j = kf('nvk_list'); check('«Космос» видит правку руководителя', any(x['steps'].endswith('проверить цены за единицу') and x['manual'] == 1 for x in j.get('items', [])), j)
    c, j = kf('fc_cmd', data={'text': 'Проверка навыка: подготовь КП для гостиницы Метрополь', 'conv': 'new'})
    check('поправленный руководителем навык «Космос» сам не перезаписывает', c == 200 and 'навык' not in j.get('say', '').lower() and kq(f'SELECT steps FROM skills WHERE id={sid}')[0][0].endswith('проверить цены за единицу'), j)
    c, j = kf('nvk_save', data={'title': 'Счёт по сделке', 'when': 'просят выставить счёт по сделке', 'steps': '1) deals — найти сделку; 2) make_invoice — счёт из товаров сделки'}, flow='flow')
    check('руководитель добавил навык вручную', c == 200 and j.get('ok') and len(j.get('items', [])) == 3, j)
    c, j = kf('nvk_save', data={'title': 'Пустой', 'steps': ''}); check('навык без шагов не принимается (ответ сервера)', c == 400 and 'название и шаги' in j.get('error', ''), (c, j))
    c, j = kf('nvk_save', 'mgr-t', {'title': 'Чужой навык', 'steps': '1) deals — что-то сделать'}); check('сотрудник навыки не правит', c == 403, (c, j))
    c, j = kf('nvk_list', 'mgr-t'); check('сотруднику список навыков не показывается', c == 403, (c, j))
    c, j = kf('nvk_del', data={'id': sid}, flow='flow')
    c, j = kf('nvk_list'); check('удалённый навык пропал у обоих', c == 200 and sorted(x['title'] for x in j.get('items', [])) == ['Сверка счёта поставщика', 'Счёт по сделке'], j)
    ok, why = same(); check('правки и удаление сразу попали и в копию магазина', ok and len(why[0]) == 2 and 'КП для гостиницы' not in q1('SELECT title FROM itc_skill_copy'), why)
    os.rename(f'{TMP}/gw.key', f'{TMP}/gw.key.off')   # сервер в Сингапуре недоступен
    c, j = kf('nvk_list', flow='flow')
    check('сервер недоступен — в настройках копия из магазина и понятное предупреждение', c == 200 and sorted(x['title'] for x in j.get('items', [])) == ['Сверка счёта поставщика', 'Счёт по сделке'] and 'Показана копия навыков из магазина' in j.get('warn', ''), (c, j))
    c, j = kf('nvk_list', data={'q': 'Выставь счёт по сделке Метрополь'}, flow='flow')
    check('сервер недоступен — «Фил» берёт навык из копии в магазине', 'Счёт по сделке (записал Алексей)' in j.get('block', ''), j.get('block'))
    c, j = kf('nvk_save', data={'title': 'Без сервера', 'steps': '1) deals — попробовать записать без сервера'}, flow='flow')
    check('без сервера навыки не правятся — понятная причина', c == 400 and j.get('error'), (c, j))
    os.rename(f'{TMP}/gw.key.off', f'{TMP}/gw.key')
    # сервер заменили (база пустая) — магазин сам возвращает ему навыки из копии
    con = sqlite3.connect(f'{TMP}/kosmos.sqlite'); con.execute('DELETE FROM skills'); con.commit(); con.close()
    q1("DELETE FROM itc_secret WHERE k='nvk_copy_at'")
    c, j = kf('nvk_list')
    check('новый пустой сервер получил навыки из копии магазина', c == 200 and sorted(x['title'] for x in j.get('items', [])) == ['Сверка счёта поставщика', 'Счёт по сделке'] and sorted(t for (t,) in kq('SELECT title FROM skills WHERE deleted=0')) == ['Сверка счёта поставщика', 'Счёт по сделке'], (j, kq('SELECT title FROM skills')))
    ok, why = same(); check('после возврата копия снова совпадает с сервером (новые номера)', ok and len(why[0]) == 2, why)
    check('счётчик применений и правка руководителя вернулись вместе с навыком', kq("SELECT uses, manual FROM skills WHERE title='Сверка счёта поставщика'") == [(2, 1)], kq('SELECT title, uses, manual FROM skills'))
    check('пишет навыки только «Космос»', "const NVK_WRITE = false;" in open(f'{ST}/api/flow.php', encoding='utf-8').read() and "const NVK_WRITE = true; " in open(f'{ST}/api/kosmos_flow.php', encoding='utf-8').read())

    print('7. Claude ответил не по форме — «Космос» всё равно понимает (№535)')
    def ask(q):
        c, j = kf('fc_cmd', data={'text': q, 'conv': 'new'}); return c, j
    c, j = ask('Проверка: два шага сразу')
    check('сразу два шага — берёт первый, потом отвечает', c == 200 and j.get('ok') and 'Посмотрел сделки' in j.get('say', '') and j.get('looked') == ['deals'], j)
    c, j = ask('Проверка: перенос строки внутри ответа')
    check('перевод строки внутри JSON — ответ цел', c == 200 and j.get('ok') and 'Строка один.' in j.get('say', '') and 'Строка два.' in j.get('say', ''), j)
    c, j = ask('Проверка: ответь простым текстом')
    check('ответ без JSON — это просто ответ', c == 200 and j.get('ok') and 'простым текстом' in j.get('say', ''), j)
    c, j = ask('Проверка: ответ с пояснением')
    check('JSON в ``` и пояснение со скобками вокруг', c == 200 and j.get('ok') and 'Я Космос' in j.get('say', ''), j)
    n0 = len(open(f'{TMP}/mock.log').read().splitlines())
    c, j = ask('Проверка: длинный ответ')
    rec = [json.loads(l) for l in open(f'{TMP}/mock.log')][n0:]
    check('не уместился — второй раз с запасом 16000', c == 200 and j.get('ok') and [r['max_tokens'] for r in rec][-2:] == [5000, 16000], (j, [r['max_tokens'] for r in rec]))
    c, j = ask('Проверка: сломанный ответ')
    check('совсем не разобрать — понятная ошибка с началом ответа (руководителю)', not j.get('ok') and 'начало ответа' in j.get('error', '') and 'Обрыв' in j.get('error', ''), j)
    c, j = kf('fc_hist')
    check('в истории реплики подписаны «Космос», не «Фил»', 'Фил:' not in open(f'{ST}/api/kosmos_flow.php', encoding='utf-8').read())

    print('11. Сингапур и деньги Claude на странице обновлений (№541)')
    def agent_money():
        r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'dbinfo'], env=env, capture_output=True, text=True, timeout=30)
        return json.loads(r.stdout).get('money') if r.stdout.startswith('{') else ('ERR', r.stdout[:200], r.stderr[-300:])
    def report(money):
        body = {'ver': '0.3.3', 'https': True, 'dns_ok': True, 'worker': True, 'key': True, 'disk_free_gb': 31.4, 'backup': '20261114', 'up': '3 дн 04:10',
                'load': 0.21, 'git': {'ok': True, 'hash': 'e14a275', 'at': '2026-11-14 00:40:00', 'err': ''}}
        if money is not None: body['money'] = money
        return http(f'{STORE}/api/kosmos.php?a=report', body, {'X-Kosmos-Token': tok})
    def status(t='boss-t'):
        c, s = http(f'{STORE}/api/kosmos.php?a=status&t={t}', {}); return c, s, s.get('detail', {})
    def bal(data, t='boss-t'):
        return http(f'{STORE}/api/kosmos.php?a=claude_bal&t={t}', data)
    def kdb(sql, args=()):
        db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); r = db.execute(sql, args).fetchall(); db.commit(); db.close(); return r
    for f in glob.glob(f'{ST}/uploads/kosmos/*/claude_bal.json'): os.remove(f)   # отметки остатка прошлого прогона
    report(None); c, j = bal({'g': 'x', 'amount': '10'})
    check('сервер ещё старый (без расхода) — понятный отказ', not j.get('ok') and '0.3.3' in j.get('error', ''), j)
    c, s, x = status(); check('старый сервер: денег в состоянии нет (страница так и скажет)', x.get('money') is None and x.get('load') == 0.21, x)
    m = agent_money(); report(m); c, s, x = status()
    check('Сингапур: версия, нагрузка, свободное место, копия GitHub, без перезагрузки', s.get('online') and s.get('ver') == '0.3.3' and x.get('disk_free_gb') == 31.4
          and x.get('git', {}).get('hash') == 'e14a275' and x.get('up') == '3 дн 04:10' and x.get('at_ts', 0) > 0, x)
    g = x.get('money') or []
    names = sorted(k['name'] for k in (g[0]['keys'] if g else []))
    check('ключи одного счёта — одной строкой (счёт узнан из ответа Claude)', len(g) == 1 and names == ['Аккаунт 1', 'Запасной', 'Сломанный'] and g[0]['g'].startswith('1111'), g)
    t_sum = sum(k['t'] for k in m) / 1e6
    check('потрачено сегодня = сумма по ключам с сервера', g and g[0]['today'] == round(t_sum, 2) and t_sum > 0 and g[0]['mark'] is None, (g, t_sum))
    c, j, _ = status('mgr-t'); check('сотруднику деньги и детали сервера не показываются', 'detail' not in j, j)
    c, j = bal({'g': g[0]['g'], 'amount': '50'}, 'mgr-t'); check('остаток указывает только руководитель', c == 403, (c, j))
    c, j = bal({'g': g[0]['g'], 'amount': 'много'}); check('не сумма — понятная ошибка', not j.get('ok') and 'долларах' in j.get('error', ''), j)
    c, j = bal({'g': 'чужой', 'amount': '5'}); check('чужой счёт — отказ', not j.get('ok'), j)
    # счёт ключа ещё не известен (ключ не отвечал после обновления сервера) — свой отдельный счёт «k<id>»
    kz = kdb("SELECT id FROM keys WHERE name='Запасной'")[0][0]; org1 = kdb('SELECT org FROM keys WHERE id=?', (kz,))[0][0]
    kdb("UPDATE keys SET org='' WHERE id=?", (kz,)); report(agent_money()); c, s, x = status()
    gz = next((q for q in x['money'] if q['g'] == f'k{kz}'), None)
    check('ключ без известного счёта — отдельной строкой', len(x['money']) == 2 and gz and [k['name'] for k in gz['keys']] == ['Запасной'], x['money'])
    c, j = bal({'g': f'k{kz}', 'amount': '10'}); check('остаток для него сохраняется', j.get('ok') and next(q for q in j['money'] if q['g'] == f'k{kz}')['mark']['amount'] == 10, j)
    kdb('UPDATE keys SET org=? WHERE id=?', (org1, kz)); report(agent_money()); c, s, x = status()
    check('счёт узнали позже — отметка остатка не потерялась', len(x['money']) == 1 and x['money'][0]['mark']['amount'] == 10, x['money'])
    c, j = bal({'g': x['money'][0]['g'], 'amount': '37,50'}); g = j.get('money', [{}])[0]
    check('указан остаток «37,50» — осталось $37,50, потрачено $0', j.get('ok') and g['mark']['amount'] == 37.5 and g['mark']['left'] == 37.5 and g['mark']['spent'] == 0, j)
    ka = kdb("SELECT id FROM keys WHERE name='Аккаунт 1'")[0][0]
    kdb("UPDATE usage SET cost_u = cost_u + 1234567 WHERE key_id=? AND rowid = (SELECT MAX(rowid) FROM usage WHERE key_id=?)", (ka, ka))
    m2 = agent_money(); report(m2); c, s, x = status(); g = x['money'][0]
    span = max(k['d7'] for k in m2); w7 = sum(k['w'] for k in m2) / 1e6 / span
    check('Космос потратил $1,23 — остаток $36,27', g['mark']['spent'] == 1.23 and g['mark']['left'] == 36.27, g)
    check('в среднем в день (счёт работает 1 день — делим на 1, не на 7) и «хватит примерно на N дней»',
          span == 1 and g['span'] == 1 and g['per_day'] == round(w7, 2) and g['mark']['days'] == int(36.265433 // w7), (g, w7, span))
    import datetime, zoneinfo   # дни расхода — по времени Бишкека, как на сервере
    d3 = (datetime.datetime.now(zoneinfo.ZoneInfo('Asia/Bishkek')).date() - datetime.timedelta(days=3)).isoformat()
    kdb("INSERT INTO usage (day, key_id, model, calls, tokens_in, tokens_out, cache_read, cache_write, cost_u) VALUES (?, ?, 'claude-sonnet-5-5', 1, 1, 1, 0, 0, 1)", (d3, ka))
    m3 = agent_money(); report(m3); c, s, x = status(); g = x['money'][0]
    check('ключ работает 4-й день — среднее за 4 дня', max(k['d7'] for k in m3) == 4 and g['span'] == 4, (g, [k['d7'] for k in m3]))
    c, j = bal({'g': g['g'], 'amount': '', 'clear': 1}); check('остаток можно убрать', j.get('ok') and j['money'][0]['mark'] is None, j)
    c, j = bal({'g': g['g'], 'amount': '$36.27'}); check('сумма со знаком доллара и точкой тоже понимается', j.get('ok') and j['money'][0]['mark']['amount'] == 36.27, j)
    kdb("UPDATE keys SET status='На счёте аккаунта этого ключа закончились деньги — пополните или выберите другой ключ' WHERE id=?", (kz,))
    report(agent_money()); c, s, x = status()
    check('«деньги закончились» у ключа — видно на странице', x['money'][0]['out'] is True and any(k['out'] for k in x['money'][0]['keys']), x['money'])
    kdb("UPDATE keys SET deleted=1 WHERE id=?", (kz,)); report(agent_money()); c, s, x = status()
    check('удалённый ключ не показывается, но его траты в остатке учтены', [k['name'] for k in x['money'][0]['keys']] == ['Аккаунт 1', 'Сломанный'] and x['money'][0]['mark']['amount'] == 36.27, x['money'])
    kdb("UPDATE keys SET deleted=0, status='ok' WHERE id=?", (kz,)); report(agent_money())
    c, j = http(f'{STORE}/api/kosmos.php?a=report', {'ver': '0.3.4', 'bw': {'set': True, 'ok': True, 'at': '2026-11-14 11:00:00', 'err': '', 'n': 9, 'project': 'Авто', 'token': 'не должен сохраниться'}}, {'X-Kosmos-Token': tok})
    c, s, x = status(); check('№542: состояние сейфа Bitwarden видно руководителю (без лишних полей)', x.get('bw') == {'set': True, 'ok': True, 'at': '2026-11-14 11:00:00', 'err': '', 'n': 9, 'project': 'Авто'}, x.get('bw'))
    report(agent_money())

    print('12. Сервер сам собирает и подписывает обновления (№545, сервер 0.3.7)')
    import secrets as _sc
    uk = _sc.token_hex(32); open(f'{ST}/api/upd_key.php', 'w').write(f"<?php define('UPD_KEY', '{uk}');\n")
    c, j = http(f'{STORE}/api/kosmos.php?a=report', {'ver': '0.3.7', 'want_upd_key': True}, {'X-Kosmos-Token': tok})
    check('сервер попросил — магазин отдал ключ обновлений (только своему серверу)', j.get('upd_key') == uk, list(j))
    c, j = http(f'{STORE}/api/kosmos.php?a=report', {'ver': '0.3.7'}, {'X-Kosmos-Token': tok}); check('не просил — не отдаёт', 'upd_key' not in j, list(j))
    R = os.path.dirname(ROOT); GD = f'{TMP}/git'; shutil.rmtree(GD, ignore_errors=True)
    for d in ('kosmos', 'store/api', 'store/apps/kosmos', 'store/eco'): shutil.copytree(f'{R}/{d}', f'{GD}/{d}', ignore=shutil.ignore_patterns('tests', 'pdfjs', '*.png', '*.jpg'))
    os.makedirs(f'{GD}/releases'); json.dump({'n': 9001, 'title': 'Проба сборки на сервере', 'what': ['страница навыков'], 'files': ['store/eco/skills.html'], 'kosmos': True}, open(f'{GD}/releases/9001.json', 'w'), ensure_ascii=False)
    open(f'{TMP}/upd_key.php', 'w').write(f"<?php define('UPD_KEY', '{uk}');\n"); open(f'{TMP}/store_token', 'w').write(tok)
    open(f'{TMP}/sign.pub', 'w').write('bpxDwrFCyrjCqBZF7hGEqLdfDYmxzHphsJtF1CNsc/k=\n')
    ac = f'{TMP}/agent_rel.php'
    open(ac, 'w').write(open(f'{TMP}/config.php').read().replace('];', f"  'store_url' => '{STORE}', 'store_token_file' => '{TMP}/store_token', 'git_dir' => '{GD}', 'upd_key_file' => '{TMP}/upd_key.php', 'own_sign_key' => '{TMP}/sign_own.pem',"
        f" 'sign_pub_file' => '{TMP}/sign.pub', 'release_dir' => '{TMP}/rel', 'db_user' => '', 'agent_state' => '{TMP}/agent_state.json', 'git_key' => '{TMP}/nokey',\n];"))
    aenv = dict(env, KOSMOS_CONFIG=ac, NO_PROXY='127.0.0.1,localhost')
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv, capture_output=True, text=True, timeout=180)
    c, j = http(f'{STORE}/api/kosmos.php?a=upd_ready&t=boss-t', {})
    it = (j.get('items') or [{}])[-1]
    check('сервер собрал №9001 и отдал в магазин: «готово к установке»', r.returncode == 0 and it.get('n') == 9001 and it.get('title') == 'Проба сборки на сервере' and it.get('needs') == 9000, (r.stdout[-500:], r.stderr[-500:], j))
    c, j = http(f'{STORE}/api/kosmos.php?a=upd_ready&t=mgr-t', {}); check('список — только руководителю', c == 403, c)
    req = urllib.request.Request(f'{STORE}/api/kosmos.php?a=upd_get&n=9001&t=boss-t')
    zb = urllib.request.urlopen(req, timeout=30).read()
    import zipfile, io, hmac as _hm, tarfile, base64
    z = zipfile.ZipFile(io.BytesIO(zb)); man = z.read('manifest.json'); names = z.namelist()
    check('архив подписан ключом обновлений, внутри страница и пакет сервера', _hm.compare_digest(_hm.new(uk.encode(), man, hashlib.sha256).hexdigest(), z.read('sig.txt').decode().strip())
          and 'store/eco/skills.html' in names and any(n.endswith('.tar.gz') for n in names), names)
    pubs = open(f'{TMP}/sign.pub').read().split()
    meta = json.loads(z.read(next(n for n in names if n.endswith('.json') and '/pkg/' in n)))
    vr = subprocess.run(['php', '-r', 'foreach (array_slice($argv,1) as $p) if (sodium_crypto_sign_verify_detached(base64_decode(getenv("S")), getenv("M"), base64_decode($p))) { echo "ok"; exit; } echo "no";'] + pubs,
                        env=dict(os.environ, S=meta['sig'], M='kosmos\n' + meta['ver'] + '\n' + meta['sha256']), capture_output=True, text=True)
    check('пакет «Космоса» подписан своим ключом сервера, он в доверенных рядом с ключом ноутбука', len(pubs) == 2 and pubs[0].startswith('bpxD') and vr.stdout == 'ok' and os.stat(f'{TMP}/sign_own.pem').st_mode & 0o077 == 0, (pubs, vr.stdout))
    n1 = len(os.listdir(f'{TMP}/rel'))
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv, capture_output=True, text=True, timeout=60)
    check('повторно то же обновление не собирается', 'собрано' not in r.stdout and len(os.listdir(f'{TMP}/rel')) == n1, r.stdout[-200:])
    c, j = http(f'{STORE}/api/kosmos.php?a=upd_put&n=9002', None, {'X-Kosmos-Token': tok})
    req = urllib.request.Request(f'{STORE}/api/kosmos.php?a=upd_put&n=9001', data=zb.replace(b'Proba', b'Proba'), headers={'X-Kosmos-Token': 'alien' * 10}, method='POST')
    try: urllib.request.urlopen(req, timeout=20); cc = 200
    except urllib.error.HTTPError as e: cc = e.code
    check('чужой сервер обновление подложить не может', cc == 403, cc)
    bad = io.BytesIO(); zz = zipfile.ZipFile(bad, 'w'); zz.writestr('manifest.json', man.replace(b'9001', b'9003')); zz.writestr('sig.txt', z.read('sig.txt')); zz.close()
    req = urllib.request.Request(f'{STORE}/api/kosmos.php?a=upd_put&n=9003', data=bad.getvalue(), headers={'X-Kosmos-Token': tok}, method='POST')
    try: urllib.request.urlopen(req, timeout=20); cc = 200
    except urllib.error.HTTPError as e: cc = e.code
    check('архив с неверной подписью магазин не принимает', cc == 400, cc)
    os.remove(f'{ST}/api/upd_key.php')
    print('13. Комплексное КП из нескольких поставщиков и разработчик для руководителя (№550)')
    q1("DROP TABLE IF EXISTS itc_sup; CREATE TABLE itc_sup (id VARCHAR(40) PRIMARY KEY, name VARCHAR(200), full_name VARCHAR(300) NOT NULL DEFAULT '', deleted TINYINT NOT NULL DEFAULT 0, seg VARCHAR(40) NOT NULL DEFAULT '', city VARCHAR(100) NOT NULL DEFAULT '', products TEXT, facts TEXT, what TEXT); "
       "INSERT INTO itc_sup (id,name,city,products,facts,what) VALUES ('s1','SolarOne','Нинбо','[\"Панель 550 Вт\",\"Панель 600 Вт\"]','[\"20 лет на рынке\"]','солнечные панели'),"
       "('s2','BattCo','Шэньчжэнь','[\"LiFePO4 5 кВт·ч\"]','[]','аккумуляторы'),('s3','InvMax','Ханчжоу','[\"Инвертор 5 кВт\",\"Инвертор 10 кВт\"]','[]','инверторы')")
    c, j = kf('fc_cmd', data={'text': 'Проверка комплексного КП: панели, батареи и инверторы для отеля', 'conv': 'new'})
    say = j.get('say', '')
    check('одно КП из трёх поставщиков разделами (панели + аккумуляторы + инверторы)', c == 200 and 'Солнечные панели' in say and 'Инверторы' in say and '"поставщиков":3' in say.replace(' ', '') and '"моделей":5' in say.replace(' ', ''), j)
    kid = q1("SELECT id FROM itc_fil_kp ORDER BY created_at DESC LIMIT 1")
    xl = glob.glob(f'{ST}/uploads/**/{kid}.xlsx', recursive=True)
    import zipfile
    sheet = zipfile.ZipFile(xl[0]).read('xl/worksheets/sheet1.xml').decode() if xl else ''
    check('в Excel все разделы и модели всех поставщиков', 'Солнечные панели — SolarOne' in sheet and 'Аккумуляторы — BattCo' in sheet and 'Инвертор 10 кВт' in sheet, (kid, xl, sheet[:300]))
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Проверка разработки: добавь кнопку', 'conv': 'new'})
    check('сотруднику разработка недоступна (всё остальное — как было)', c == 200 and 'Разработки в моих инструментах нет' in j.get('say', ''), j)
    c, j = kf('fc_cmd', data={'text': 'Проверка разработки: добавь кнопку', 'conv': 'new'})
    sayd = j.get('say', '')
    con = sqlite3.connect(f'{TMP}/kosmos.sqlite'); row = con.execute("SELECT engine, text, phone FROM dev_tasks ORDER BY id DESC LIMIT 1").fetchone(); con.close()
    check('руководителю: поручение ушло на сервер «Космоса» (у «Космоса» — Claude)', c == 200 and 'поручение №' in sayd and 'Космос-разработчик' in sayd and row and row[0] == 'claude' and 'кнопку проверки связи' in row[1] and row[2].startswith('магазин'), (sayd, row))
    print('14. Android-приложения собирает сервер (№551, сервер 0.4.1)')
    open(f'{ST}/api/upd_key.php', 'w').write(f"<?php define('UPD_KEY', '{uk}');\n")
    shutil.copytree(f'{R}/android/ITCApp', f'{GD}/android/ITCApp', ignore=shutil.ignore_patterns('build'))
    json.dump({'n': 9010, 'title': 'Фил и Космос 2.2.4', 'what': ['новые значки'], 'files': [], 'kosmos': False,
               'android': {'apps': ['fil', 'kosmos'], 'code': 27, 'name': '2.2.4', 'notes': 'Быстрее открывается'}}, open(f'{GD}/releases/9010.json', 'w'), ensure_ascii=False)
    KS = base64.b64encode(b'KEYSTORE-BYTES-\x01\x02').decode()
    FB = f'{TMP}/fake_bws.json'; pid = 'p-avto'
    json.dump({'projects': [{'id': pid, 'name': 'Авто'}], 'calls': [], 'secrets': [{'id': f's{i}', 'projectId': pid, 'key': k, 'value': v, 'note': ''} for i, (k, v) in enumerate(
        [('ANDROID_KEYSTORE_BASE64', KS), ('ANDROID_KEYSTORE_PASSWORD', 'storepw-123456'), ('ANDROID_KEY_ALIAS', 'key0'), ('ANDROID_KEY_PASSWORD', 'keypw-654321')])]}, open(FB, 'w'))
    c, j = http(f'{KOS}/api.php?a=bw_set', {'token': '0.' + '1' * 8 + '-' + '2' * 4 + '-' + '3' * 4 + '-' + '4' * 4 + '-' + '5' * 12 + '.' + 'A' * 30 + ':' + 'B' * 24}, {'X-Eco-Token': 'boss-token'})
    check('сейф подключён (пробный токен)', j.get('ok'), j)
    open(ac, 'a')
    acf = open(ac).read().replace('];', f"  'bws_bin' => '{ROOT}/tests/fake_bws.py', 'android_dir' => '{TMP}/android_src',\n];"); open(ac, 'w').write(acf)
    for f in glob.glob('/tmp/itc-sign-*'): shutil.rmtree(f, ignore_errors=True)
    aenv2 = dict(aenv, FAKE_BWS_DB=FB, ITC_GRADLE_BIN=f'{ROOT}/tests/fake_gradle.py', ITC_APKSIGNER=f'{ROOT}/tests/fake_apksigner.py', ITC_ANDROID_SDK=f'{TMP}/sdk')
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv2, capture_output=True, text=True, timeout=120)
    check('обновление с Android: сначала сборка в фоне, обновление ждёт', 'начинаю сборку' in r.stdout and json.load(open(f'{TMP}/rel/state.json')).get('wait', '').startswith('№9010'), (r.stdout[-400:], r.stderr[-300:]))
    for _ in range(60):
        if os.path.exists(f'{TMP}/rel/android/9010/ok.json') or os.path.exists(f'{TMP}/rel/android/9010/fail.txt'): break
        time.sleep(0.5)
    fg = json.load(open('/tmp/fake_gradle.json'))
    check('сборка: ключ подписи из сейфа — файлом 600, пароли переданы, base64 в окружение не попал', os.path.exists(f'{TMP}/rel/android/9010/ok.json') and fg['ks_mode'] == '0o600'
          and fg['ks_body'] == 'KEYSTORE-BYTES-\x01\x02' and fg['pw'] and fg['alias'] == 'key0' and not fg['b64_in_env'], (fg, open(f'{TMP}/rel/android/9010/fail.txt').read() if os.path.exists(f'{TMP}/rel/android/9010/fail.txt') else ''))
    check('версия 27 / 2.2.4, только нужные приложения, в копии проекта', '-PitcVersionCode=27' in fg['args'] and '-PitcVersionName=2.2.4' in fg['args'] and 'assembleFilRelease' in fg['args']
          and 'assembleKosmosRelease' in fg['args'] and len([x for x in fg['args'] if x.startswith('assemble')]) == 2 and fg['cwd'] == f'{TMP}/android_src', fg)
    check('файл ключа удалён сразу после сборки', not glob.glob('/tmp/itc-sign-*'), glob.glob('/tmp/itc-sign-*'))
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv2, capture_output=True, text=True, timeout=120)
    c, j = http(f'{STORE}/api/kosmos.php?a=upd_ready&t=boss-t', {}); it = next((x for x in j.get('items', []) if x.get('n') == 9010), {})
    check('обновление №9010 готово к установке, в «что нового» — приложения и версия', it and any('fil (' in w and '2.2.4' in w for w in it.get('what', [])), (r.stdout[-300:], j))
    zb = urllib.request.urlopen(urllib.request.Request(f'{STORE}/api/kosmos.php?a=upd_get&n=9010&t=boss-t'), timeout=30).read(); z = zipfile.ZipFile(io.BytesIO(zb))
    vj = json.loads(z.read('store/app/fil/version.json'))
    check('в обновлении APK и version.json по адресам приложений', {'store/app/fil/itc-fil.apk', 'store/app/kosmos/itc-kosmos.apk', 'store/app/kosmos/version.json'} <= set(z.namelist())
          and vj['code'] == 27 and vj['name'] == '2.2.4' and vj['url'] == 'https://itcpartner.store/app/fil/itc-fil.apk' and vj['notes'] == 'Быстрее открывается', (z.namelist(), vj))
    log_all = open(f'{TMP}/rel/android/9010/ok.json').read() + r.stdout + r.stderr
    check('пароли и ключ нигде не записаны', 'storepw-123456' not in log_all and 'keypw-654321' not in log_all and KS not in log_all)
    json.dump({'n': 9011, 'title': 'Сломанная сборка', 'what': [], 'files': [], 'kosmos': False, 'android': {'apps': ['fil'], 'code': 28, 'name': '2.2.5'}}, open(f'{GD}/releases/9011.json', 'w'), ensure_ascii=False)
    open(f'{TMP}/gradle_fail.sh', 'w').write(f'#!/bin/sh\nFAKE_GRADLE_FAIL=1 exec {ROOT}/tests/fake_gradle.py "$@"\n'); os.chmod(f'{TMP}/gradle_fail.sh', 0o755)
    aenv3 = dict(aenv2, ITC_GRADLE_BIN=f'{TMP}/gradle_fail.sh')
    subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv3, capture_output=True, text=True, timeout=120)
    for _ in range(60):
        if os.path.exists(f'{TMP}/rel/android/9011/fail.txt'): break
        time.sleep(0.5)
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv3, capture_output=True, text=True, timeout=120)
    stj = json.load(open(f'{TMP}/rel/state.json')); ft = open(f'{TMP}/rel/android/9011/fail.txt').read() if os.path.exists(f'{TMP}/rel/android/9011/fail.txt') else ''
    check('сборка не удалась — обновление не выпускается, причина видна, пароль скрыт', stj['done'].get('9011', {}).get('ok') is False and 'Gradle не собрал' in ft and 'keypw-654321' not in ft + json.dumps(stj, ensure_ascii=False), (ft, stj.get('done', {}).get('9011')))
    json.dump({'n': 9012, 'title': 'Слишком много', 'what': [], 'files': [], 'kosmos': False, 'android': {'apps': 'all', 'code': 29, 'name': '2.2.6'}}, open(f'{GD}/releases/9012.json', 'w'), ensure_ascii=False)
    subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv2, capture_output=True, text=True, timeout=120)
    for _ in range(60):
        if os.path.exists(f'{TMP}/rel/android/9012/fail.txt'): break
        time.sleep(0.5)
    check('больше 6 приложений за раз — просит разбить (магазин принимает до 60 МБ)', 'не больше 6' in (open(f'{TMP}/rel/android/9012/fail.txt').read() if os.path.exists(f'{TMP}/rel/android/9012/fail.txt') else ''))
    print('15. Кто подготовил обновление: Claude, «Космос» или Фил (№553)')
    check('обновление №9010 (от Claude) помечено «Claude»', it.get('by') == 'Claude', it)
    json.dump({'n': 9013, 'title': 'От Фила', 'what': ['проба'], 'files': ['store/eco/skills.html'], 'kosmos': False}, open(f'{GD}/releases/9013.json', 'w'), ensure_ascii=False)
    subprocess.run(f'cd {GD} && git init -q && git add releases/9013.json && git -c user.name="Фил-разработчик" -c user.email=f@f commit -qm fil', shell=True, check=True)
    st0 = json.load(open(f'{TMP}/rel/state.json')); st0['done'].update({'9011': {'ok': True}, '9012': {'ok': True}}); json.dump(st0, open(f'{TMP}/rel/state.json', 'w'))
    subprocess.run(['php', f'{ROOT}/bin/agent.php', 'release'], env=aenv2, capture_output=True, text=True, timeout=120)
    c, j = http(f'{STORE}/api/kosmos.php?a=upd_ready&t=boss-t', {}); it13 = next((x for x in j.get('items', []) if x.get('n') == 9013), {})
    check('обновление из правок Фила помечено «Фил» (по автору в GitHub)', it13.get('by') == 'Фил', (it13, json.load(open(f'{TMP}/rel/state.json')).get('done', {}).get('9013')))
    print('16. Файлы: Фил/«Космос» достают фото из PDF на сервере (№554)')
    from PIL import Image as _Im
    _Im.new('RGB', (500, 500), (20, 120, 40)).save(f'{TMP}/cat2.pdf')
    c, j = kf('fc_file', data={'name': 'каталог GP Solar.pdf', 'mime': 'application/pdf', 'text': 'Каталог солнечных панелей GP Solar, модели 450-600 Вт, цены по запросу', 'data': base64.b64encode(open(f'{TMP}/cat2.pdf', 'rb').read()).decode()})
    fid = j.get('id'); check('PDF приложен, оригинал сохранён', c == 200 and fid, j)
    c, j = kf('fc_cmd', data={'text': 'Проверка файлов: достань фото товаров', 'conv': 'new', 'files': [fid]})
    say = j.get('say', '').replace('\\/', '/'); import re as _re
    links = _re.findall(r'https://[^\s"\\]+/uploads/fil_out/([0-9a-f]{16})/([^\s"\\]+?\.jpg)', say)
    check('фото из PDF — ссылкой, файл лежит в магазине', c == 200 and links and os.path.exists(f'{ST}/uploads/fil_out/{links[0][0]}/{urllib.parse.unquote(links[0][1])}'), say[:500])
    c, j = kf('fc_file', data={'name': 'обзор.mp4', 'mime': 'video/mp4', 'data': base64.b64encode(open(f'{TMP}/cat2.pdf', 'rb').read()).decode()})
    check('видео принимается (до 20 МБ), Фил знает, что его можно обработать', c == 200 and j.get('kind') == 'видео', j)
    print('17. История поставщика: записи, переписка WeChat, сделки — одной лентой (№555)')
    q1("DROP TABLE IF EXISTS itc_sup_log; DELETE FROM itc_wc_msg WHERE chat_id='wcS1'; DELETE FROM itc_wc_chat WHERE id='wcS1'")
    q1("INSERT INTO itc_wc_chat (id, ext_id, emp_id, title, sup_id, last_at) VALUES ('wcS1','x1','e1','SolarOne Ningbo','s1','2026-03-01 10:00:00');"
       "INSERT INTO itc_wc_msg (id, chat_id, dir, who, text, ru, at) VALUES ('m1','wcS1','in','Lily','新报价 0.58','Новая цена 0,58 $/Вт на панели 550 Вт, срок 20 дней','2026-03-01 10:00:00'),"
       "('m2','wcS1','out','Алексей','OK','Хорошо, пришлите каталог','2026-03-01 11:00:00')")
    _Im.new('RGB', (400, 300), (250, 250, 250)).save(f'{TMP}/shot.jpg')
    c, j = kf('fc_file', data={'name': 'скриншот wechat.jpg', 'mime': 'image/jpeg', 'text': 'SolarOne Lily: 550W panel 0.62 USD/W FOB MOQ 1x40HQ lead time 25 days', 'data': base64.b64encode(open(f'{TMP}/shot.jpg', 'rb').read()).decode()})
    fid2 = j.get('id')
    c, j = kf('fc_cmd', data={'text': 'Проверка истории записи: занеси в базу', 'conv': 'new', 'files': [fid2]})
    check('скриншот → запись в истории поставщика (нашёл SolarOne сам, дата, вид «цены»)', c == 200 and 'SolarOne' in j.get('say', '') and '2025-06-10' in j.get('say', '')
          and q1("SELECT kind FROM itc_sup_log WHERE sup_id='s1'") == 'цены', (j.get('say', '')[:400], q1('SELECT * FROM itc_sup_log')))
    check('в записи — текст со скриншота (для поиска через год)', '0.62 USD/W' in q1("SELECT text FROM itc_sup_log WHERE sup_id='s1'"))
    c, j = kf('fc_cmd', data={'text': 'Проверка истории вопрос: какие цены давал SolarOne?', 'conv': 'new'})
    say = j.get('say', '').replace('\\/', '/')
    check('вопрос через год: Фил видит и запись менеджера, и переписку WeChat из CRM, по датам', c == 200 and '0,62 $/Вт' in say and 'Новая цена 0,58' in say and 'WeChat' in say
          and say.split('новые сверху')[1].index('2026-03-01') < say.split('новые сверху')[1].index('2025-06-10'), say[:900])
    c, j = kf('sh_view', data={'id': 's1'}, flow='flow')
    kinds = [x['type'] for x in j.get('items', [])]
    check('страница ленты: записи и переписка, новые сверху', c == 200 and j['sup']['name'] == 'SolarOne' and kinds[:2] == ['WeChat', 'WeChat'] and 'запись' in kinds, (c, kinds, str(j)[:300]))
    c, j = kf('sh_view', data={'id': 's1', 'q': '0,58'}, flow='flow'); check('поиск по ленте', len(j.get('items', [])) == 1 and '0,58' in j['items'][0]['text'], j.get('items'))
    c, j = kf('sh_add', 'mgr-t', {'id': 's1', 'kind': 'сроки', 'text': 'Сроки выросли до 35 дней из-за праздников', 'at': '2026-10-01'}, flow='flow'); check('менеджер добавил запись вручную', j.get('ok'), j)
    rid = q1("SELECT id FROM itc_sup_log WHERE kind='сроки'")
    c, j = kf('sh_hide', 'mgr-t', {'rid': rid}, flow='flow'); check('скрыть запись менеджер не может', c == 403, c)
    c, j = kf('sh_hide', data={'rid': rid}, flow='flow'); c, j = kf('sh_view', data={'id': 's1'}, flow='flow')
    check('руководитель скрыл ошибочную запись — в ленте её нет', not any('35 дней' in x['text'] for x in j['items']))
    print('18. Фирменный стиль: одна база для всех документов (№556)')
    os.makedirs(f'{ST}/api/kp_fonts', exist_ok=True)
    for a_, b_ in [('DejaVuSans.ttf', 'Roboto-Regular.ttf'), ('DejaVuSans-Bold.ttf', 'Roboto-Bold.ttf')]:
        if not os.path.exists(f'{ST}/api/kp_fonts/{b_}'): shutil.copy(f'/usr/share/fonts/truetype/dejavu/{a_}', f'{ST}/api/kp_fonts/{b_}')
    q1("DELETE FROM itc_secret WHERE k='brand'"); shutil.rmtree(f'{ST}/uploads/brand', ignore_errors=True)
    _Im.new('RGB', (600, 220), (13, 42, 107)).save(f'{ST}/uploads/brand_site.png'); os.makedirs(f'{ST}/uploads/brand', exist_ok=True); shutil.copy(f'{ST}/uploads/brand_site.png', f'{ST}/uploads/brand/site-logo.png')
    def last_pdf(skip=''):
        kid = q1(f"SELECT id FROM itc_fil_kp WHERE kind='rq' AND id<>'{skip}' ORDER BY created_at DESC LIMIT 1"); f = glob.glob(f'{ST}/uploads/**/{kid}.pdf', recursive=True)
        import zlib as _z, re as _r2
        raw = open(f[0], 'rb').read() if f else b''; out = raw
        for m in _r2.finditer(rb'stream\n(.*?)\nendstream', raw, _r2.S):
            try: out += _z.decompress(m.group(1))
            except Exception: pass
        return out, kid
    q1("DELETE FROM itc_fil_kp WHERE kind='rq'")
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Проверка запроса: запроси цены у SolarOne', 'conv': 'new'})
    pdf, kid = last_pdf()
    check('менеджер: запрос цены — PDF в фирменных цветах сайта, с логотипом', c == 200 and b'0.051 0.165 0.420 rg' in pdf and b'/Subtype /Image' in pdf and q1(f"SELECT kind FROM itc_fil_kp WHERE id='{kid}'") == 'rq', (j.get('say', '')[:300], len(pdf)))
    check('запрос записан в историю поставщика', 'Отправлен коммерческий запрос' in q1("SELECT GROUP_CONCAT(text) FROM itc_sup_log WHERE sup_id='s1'"))
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Проверка стиля: поменяй цвет', 'conv': 'new'})
    check('менеджер стиль поменять не может', 'только руководитель' in j.get('say', ''), j.get('say'))
    _Im.new('RGB', (500, 200), (170, 0, 0)).save(f'{TMP}/logo.png')
    c, j = kf('fc_file', data={'name': 'наш логотип.png', 'mime': 'image/png', 'text': 'логотип ИТК', 'data': base64.b64encode(open(f'{TMP}/logo.png', 'rb').read()).decode()})
    c, j = kf('fc_cmd', data={'text': 'Проверка стиля: вот новый логотип и цвет', 'conv': 'new', 'files': [j.get('id')]})
    check('руководитель поменял логотип, цвет, слоган — одной командой', 'фирменный стиль обновлён' in j.get('say', '') and 'логотип' in j.get('say', ''), j.get('say'))
    q1("DELETE FROM itc_fil_kp WHERE kind='rq' AND id<>'" + kid + "'")
    c, j = kf('fc_cmd', 'mgr-t', {'text': 'Проверка запроса: ещё раз', 'conv': 'new'}); pdf2, _ = last_pdf(kid)
    check('у всех менеджеров сразу новый стиль (цвет руководителя)', b'0.667 0.000 0.000 rg' in pdf2 and b'0.051 0.165 0.420 rg' not in pdf2, len(pdf2))
    q1("DELETE FROM itc_secret WHERE k='brand'")
finally:
    for p in procs: p.terminate()
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
for lf in ['store.log', 'php.log']:
    errs = [l for l in open(f'{TMP}/{lf}').read().split('\n') if any(w in l for w in ('Fatal', 'Warning', 'Deprecated', 'Parse error'))]
    if errs: print(f'Ошибки PHP ({lf}):\n' + '\n'.join(errs[:15]))
sys.exit(1 if fail_n else 0)
