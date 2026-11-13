#!/usr/bin/env python3
"""«Космос» в магазине (api/kosmos_flow.php — копия «Фила» на Claude) — проверка целиком у себя:
магазин (PHP + MariaDB, поддельный config.php) → шлюз → сервер «Космоса» (php -S) → пробный Claude.
Нужно: запущенный MariaDB с базой itc (пользователь itc/itcpass) и папка магазина /tmp/kft/store
(api/ из выгрузки + flow.php, kosmos_flow.php, kosmos_lib.php, kosmos.php, поддельный config.php).
Запуск: python3 tests/flow_e2e.py
"""
import json, os, shutil, subprocess, sys, time, urllib.request, urllib.error, sqlite3, hashlib, glob

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
subprocess.run(['mysql', '-uroot', 'itc', '-e', 'DROP TABLE IF EXISTS itc_ks_log, itc_ks_conv, itc_ks_task, itc_fc_log, itc_fil_conv, itc_fil_task, itc_skill; DELETE FROM itc_secret WHERE k LIKE "ks_%" OR k LIKE "fil_%" OR k = "sp_voice"'], capture_output=True)
for f in glob.glob(f'{ST}/uploads/kosmos/*/gw.json'): os.remove(f)
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
finally:
    for p in procs: p.terminate()
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
for lf in ['store.log', 'php.log']:
    errs = [l for l in open(f'{TMP}/{lf}').read().split('\n') if any(w in l for w in ('Fatal', 'Warning', 'Deprecated', 'Parse error'))]
    if errs: print(f'Ошибки PHP ({lf}):\n' + '\n'.join(errs[:15]))
sys.exit(1 if fail_n else 0)
