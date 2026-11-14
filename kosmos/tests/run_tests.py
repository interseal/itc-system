#!/usr/bin/env python3
"""Проверка «Космоса» целиком у себя: пробный Claude + встроенный сервер PHP + служба задач.
Запуск: python3 tests/run_tests.py   (из папки kosmos)
"""
import json, os, shutil, subprocess, sys, time, urllib.request, urllib.error, sqlite3

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TMP = '/tmp/kosmos_test'
API = 'http://127.0.0.1:8781/api.php'
MOCK_PORT = 8765
shutil.rmtree(TMP, ignore_errors=True)
os.makedirs(TMP)
open(f'{TMP}/master.key', 'wb').write(os.urandom(48))
CONF = f'''<?php return [
  'db' => '{TMP}/kosmos.sqlite',
  'master_key_file' => '{TMP}/master.key',
  'store_token_file' => '{TMP}/no_store_token',
  'gw_key_file' => '{TMP}/gw.key',
  'claude_url' => 'http://127.0.0.1:{MOCK_PORT}/v1/messages',
  'dev_users' => [
    'boss-token'  => ['+996555000001', 'Алексей', 'boss', true],
    'staff-token' => ['+996555000002', 'Айгуль', 'manager', false],
    'wh-token'    => ['+8613800000000', 'Ли', 'warehouse', false],
  ],
];'''
open(f'{TMP}/config.php', 'w').write(CONF)
env = dict(os.environ, KOSMOS_CONFIG=f'{TMP}/config.php', PHP_CLI_SERVER_WORKERS='4')
procs = [
    subprocess.Popen([sys.executable, f'{ROOT}/tests/mock_claude.py', str(MOCK_PORT), f'{TMP}/mock.log']),
    subprocess.Popen(['php', '-S', '127.0.0.1:8781', '-t', f'{ROOT}/www'], env=env, stdout=open(f'{TMP}/php.log', 'w'), stderr=subprocess.STDOUT),
]
time.sleep(1.2)

ok_n = 0; fail_n = 0
def check(name, cond, extra=''):
    global ok_n, fail_n
    if cond: ok_n += 1; print('  ✓', name)
    else: fail_n += 1; print('  ✗', name, extra)

def call(a, tok=None, data=None, raw=False):
    req = urllib.request.Request(f'{API}?a={a}', data=json.dumps(data or {}).encode(), method='POST',
                                 headers={'Content-Type': 'application/json', **({'X-Eco-Token': tok} if tok else {})})
    try:
        with urllib.request.urlopen(req, timeout=30) as r:
            body = r.read().decode(); code = r.status
    except urllib.error.HTTPError as e:
        body = e.read().decode(); code = e.code
    if raw: return code, body
    try: return code, json.loads(body)
    except Exception: return code, {'_raw': body[:400]}

def send(tok, text, thread='', deep=False):
    code, body = call('send', tok, {'thread_id': thread, 'text': text, 'deep': deep}, raw=True)
    evs = [json.loads(l[6:]) for l in body.split('\n') if l.startswith('data: ')]
    return code, evs

def worker_once():
    r = subprocess.run(['php', f'{ROOT}/bin/worker.php', 'once'], env=env, capture_output=True, text=True, timeout=60)
    return r.returncode, r.stdout + r.stderr

B, S, W = 'boss-token', 'staff-token', 'wh-token'
try:
    print('1. Вход и права')
    c, j = call('health'); check('health отвечает', c == 200 and j['ok'] and j['key'] is False, j)
    c, j = call('me', B); check('руководитель узнан', j.get('me', {}).get('boss') is True, j)
    c, j = call('me', S); check('сотрудник узнан, доступ есть', j['me']['boss'] is False and j['me']['allowed'] is True, j)
    c, j = call('me', W); check('склад по умолчанию без доступа', j['me']['allowed'] is False, j)
    c, j = call('threads', W); check('склад: разговоры закрыты (403)', c == 403, (c, j))
    c, j = call('me', 'wrong'); check('чужой пропуск не пускает', c in (401, 502), (c, j))
    c, j = call('keys', S); check('сотрудник не видит ключи (403)', c == 403, (c, j))

    print('2. Ключи Claude')
    c, evs = send(B, 'Привет')
    check('без ключа — понятная ошибка', any(e['t'] == 'error' and 'ключа' in e['error'] for e in evs), evs)
    c, j = call('key_add', S, {'name': 'X', 'key': 'sk-ant-good-' + 'a' * 30})
    check('сотрудник не добавляет ключ', c == 403, (c, j))
    KA = 'sk-ant-good-' + 'a' * 26 + 'AAAA'
    c, j = call('key_add', B, {'name': 'Аккаунт 1', 'key': KA})
    check('ключ 1 добавлен и проверен', j.get('ok') and j['test']['ok'] and j['key']['active'] and j['key']['last4'] == 'AAAA', j)
    c, j = call('key_add', B, {'name': 'Повтор', 'key': KA}); check('повтор ключа не добавляется', c == 400, j)
    c, j = call('key_add', B, {'name': 'Мусор', 'key': 'hello'}); check('не-ключ отклонён', c == 400, j)
    c, j = call('key_add', B, {'name': 'Плохой', 'key': 'sk-ant-bad-' + 'b' * 30})
    check('плохой ключ: проверка показывает ошибку', j.get('ok') and not j['test']['ok'] and 'не подходит' in j['test']['status'], j)
    bad_id = j['key']['id']
    c, j = call('key_add', B, {'name': 'Без денег', 'key': 'sk-ant-nocredit-' + 'c' * 30})
    check('ключ без денег: понятная ошибка', 'закончились деньги' in j['test']['status'], j)
    nocredit_id = j['key']['id']
    c, j = call('keys', B); check('в списке 3 ключа, активен один', len(j['keys']) == 3 and sum(k['active'] for k in j['keys']) == 1, j)
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite')
    raw = b''.join(r[0].encode() for r in db.execute('select enc from keys'))
    check('в базе ключи только зашифрованы', b'sk-ant' not in raw and b'AAAA' not in raw)
    c, j = call('keys', B); check('наружу не уходит сам ключ', 'sk-ant' not in json.dumps(j))

    print('3. Разговор, поток, память')
    c, evs = send(B, 'Привет, Космос')
    tid = next((e['thread_id'] for e in evs if e['t'] == 'start'), '')
    text = ''.join(e.get('text', '') for e in evs if e['t'] == 'delta')
    check('ответ пришёл потоком', len([e for e in evs if e['t'] == 'delta']) > 3 and 'Ключ …AAAA' in text, evs[-3:])
    check('разговор создан', tid != '' and any(e['t'] == 'done' for e in evs))
    c, evs = send(B, 'запомни, что отчёты мне нужны коротко', tid)
    check('remember: пометка «Запомнил»', any(e['t'] == 'note' and 'Запомнил' in e['text'] for e in evs), evs)
    c, j = call('memory', B); check('в памяти 1 запись', len(j['items']) == 1 and j['items'][0]['scope'] == 'user', j)
    c, j = call('messages', B, {'thread_id': tid}); check('в разговоре 4 сообщения', len(j['messages']) == 4, len(j['messages']))
    c, j = call('messages', S, {'thread_id': tid}); check('чужой разговор не открыть', c == 404, c)

    print('4. Смена ключа — память остаётся')
    KB = 'sk-ant-good-' + 'z' * 26 + 'BBBB'
    c, j = call('key_add', B, {'name': 'Аккаунт 2', 'key': KB}); kb_id = j['key']['id']
    check('ключ 2 добавлен неактивным', j['key']['active'] is False, j)
    c, j = call('key_activate', B, {'id': kb_id}); check('ключ 2 стал активным', any(k['id'] == kb_id and k['active'] for k in j['keys']), j)
    c, evs = send(B, 'Что ты помнишь обо мне?', tid)
    text = ''.join(e.get('text', '') for e in evs if e['t'] == 'delta')
    check('новый ключ работает', 'Ключ …BBBB' in text, text)
    check('новый ключ видит ту же память', 'Записей памяти: 1' in text, text)
    check('новый ключ видит ту же переписку', 'Сообщений в запросе: 5' in text, text)

    print('4б. Запасные ключи: сломанный активный ключ — переключение само')
    call('key_activate', B, {'id': bad_id})
    c, evs = send(B, 'Проверка запасного ключа', tid)
    text = ''.join(e.get('text', '') for e in evs if e['t'] == 'delta')
    check('сломанный ключ заменён рабочим, ответ пришёл', 'Ключ …AAAA' in text, evs[-4:])
    check('пометка о переключении в ответе', any(e['t'] == 'note' and 'переключился' in e['text'] for e in evs), evs)
    c, j = call('keys', B); act = [k for k in j['keys'] if k['active']]
    check('активным стал рабочий ключ', act and act[0]['last4'] == 'AAAA', act)
    call('settings_set', B, {'auto_switch': 0}); call('key_activate', B, {'id': bad_id})
    c, evs = send(B, 'Без автопереключения', tid)
    check('автопереключение выключено — честная ошибка', any(e['t'] == 'error' and 'не подходит' in e['error'] for e in evs), evs[-2:])
    call('settings_set', B, {'auto_switch': 1}); call('key_activate', B, {'id': kb_id})

    print('5. Быстрый ответ → долгая задача в фоне')
    c, evs = send(B, 'Сделай сложный анализ поставщиков', tid)
    task = next((e['task'] for e in evs if e['t'] == 'task'), None)
    check('быстрая модель поставила задачу', task is not None and task['status'] == 'queued', evs)
    rc, out = worker_once(); check('служба задач отработала', rc == 0, out)
    c, j = call('task', B, {'id': task['id']})
    check('задача выполнена, шаги видны', j['task']['status'] == 'done' and any('Собираю' in s['note'] for s in j['task']['steps']), j)
    c, j = call('messages', B, {'thread_id': tid})
    last = j['messages'][-1]
    check('итог задачи пришёл в разговор', last['role'] == 'assistant' and 'Итог задачи' in last['text'] and last['task_id'] == task['id'], last)
    c, evs = send(S, 'Подготовь письмо фабрике', '', True)
    t2 = next((e['task'] for e in evs if e['t'] == 'task'), None)
    check('кнопка «долгая задача» ставит сразу в очередь', t2 is not None)
    worker_once()
    c, j = call('task', S, {'id': t2['id']}); check('задача сотрудника выполнена', j['task']['status'] == 'done', j)

    print('6. Доступ сотрудников и лимиты')
    c, j = call('users', B); check('руководитель видит, кто пользуется', len(j['users']) == 3, j)
    c, j = call('user_allow', B, {'phone': '+8613800000000', 'allow': 'yes'})
    c, j = call('me', W); check('после разрешения склад пускают', j['me']['allowed'] is True, j)
    c, j = call('settings_set', B, {'access_mode': 'list'})
    c, j = call('me', S); check('режим «по списку»: без отметки — нет доступа', j['me']['allowed'] is False, j)
    c, j = call('settings_set', B, {'access_mode': 'all', 'daily_limit': 2})
    c, evs = send(S, 'раз'); c2, body2 = call('send', S, {'text': 'два'})
    check('дневной лимит сотрудника срабатывает', c == 200 and c2 == 429 and 'лимит' in body2.get('error', ''), (c, c2, body2))
    c3, body3 = call('send', B, {'text': 'руководителю лимит не мешает'}, raw=True)
    check('руководителя лимит не касается', c3 == 200, c3)
    c, j = call('settings_set', B, {'daily_limit': 150, 'model_deep': 'claude-opus-5-5'})
    check('модель глубокой работы меняется', j['settings']['model_deep'] == 'claude-opus-5-5', j)
    c, j = call('settings_set', B, {'model_fast': 'gpt-4'}); check('чужая модель не принимается', c == 400, j)

    print('7. Расходы и удаление ключа')
    c, j = call('usage', B); keys_used = {r['key_name'] for r in j['rows']}
    check('расход виден по каждому ключу', {'Аккаунт 1', 'Аккаунт 2'} <= keys_used, keys_used)
    c, j = call('key_delete', B, {'id': kb_id}); check('активный ключ удалён', all(k['id'] != kb_id for k in j['keys']), j)
    c, evs = send(B, 'Ещё вопрос', tid)
    check('без активного ключа — понятная ошибка', any(e['t'] == 'error' for e in evs), evs)
    c, j = call('memory', B); check('память цела после удаления ключа', len(j['items']) == 1, j)

    print('8. Журнал запросов к Claude')
    log = [json.loads(l) for l in open(f'{TMP}/mock.log')]
    check('версия API во всех запросах', all(r['ver'] == '2023-06-01' for r in log))
    check('быстрые ответы — haiku, задачи — sonnet/opus', {'claude-haiku-5-5', 'claude-sonnet-5-5'} <= {r['model'] for r in log}, {r['model'] for r in log})
    check('правила кешируются (cache_control)', all(r['cache'] for r in log if r['tools']))
    print('9. Шлюз магазина (0.2.0): «Космос» в Экосистеме и CRM')
    GW = 'a1' * 32
    def gw(data, key=GW):
        req = urllib.request.Request(f'{API}?a=gw', data=json.dumps(data).encode(), method='POST',
                                     headers={'Content-Type': 'application/json', **({'X-Kosmos-GW': key} if key else {})})
        try:
            with urllib.request.urlopen(req, timeout=30) as r: return r.status, json.loads(r.read().decode())
        except urllib.error.HTTPError as e: return e.code, json.loads(e.read().decode() or '{}')
    c, j = gw({'a': 'status'}); check('шлюз без ключа связи закрыт', c == 403, (c, j))
    open(f'{TMP}/gw.key', 'w').write(GW + '\n')
    c, j = gw({'a': 'status'}, 'b2' * 32); check('чужой ключ шлюза не подходит', c == 403, (c, j))
    c, j = gw({'a': 'status'}, None); check('без заголовка шлюз закрыт', c == 403, (c, j))
    c, j = call('keys', B); k1 = next(k['id'] for k in j['keys'] if k['last4'] == 'AAAA'); call('key_activate', B, {'id': k1})
    c, j = gw({'a': 'status'})
    check('состояние: ключи, активный, модели', c == 200 and j['ok'] and j['active'] == 'Аккаунт 1' and j['ready'] and len(j['models']) >= 3, j)
    STAFF = {'phone': '+996555000002', 'name': 'Айгуль', 'role': 'manager', 'boss': False}
    n0 = len(open(f'{TMP}/mock.log').read().splitlines())
    c, j = gw({'a': 'ask', 'system': 'Ты — Космос. ' + 'правило. ' * 500, 'user': 'Сколько сделок?', 'max_tokens': 3000, 'model': 'claude-haiku-5-5', 'who': STAFF})
    check('вопрос через шлюз: ответ Claude', c == 200 and j['ok'] and 'Ключ …AAAA' in j['text'] and j['key'] == 'Аккаунт 1', j)
    rec = [json.loads(l) for l in open(f'{TMP}/mock.log')][n0:]
    check('шлюз: правила в кеше, без потока, модель как просили', rec and rec[-1]['cache'] and not rec[-1]['stream'] and rec[-1]['model'] == 'claude-haiku-5-5', rec)
    c, j = gw({'a': 'ask', 'user': 'Ещё', 'model': 'gpt-4', 'who': STAFF}); rec = [json.loads(l) for l in open(f'{TMP}/mock.log')]
    check('чужая модель → модель «Космоса»', c == 200 and rec[-1]['model'] == 'claude-opus-5-5', rec[-1])
    c, j = gw({'a': 'ask', 'user': 'Вопрос', 'who': {'phone': '+8613811111111', 'name': 'Ван', 'role': 'warehouse'}})
    check('склад через шлюз не пускают', c == 403 and 'руководитель' in j.get('error', ''), (c, j))
    c, j = gw({'a': 'ask', 'user': 'Вопрос', 'who': {}}); check('без сотрудника — отказ', c == 400, (c, j))
    c, j = call('users', B); u = next((x for x in j['users'] if x['phone'] == '+996555000002'), {})
    check('обращения через шлюз видны в «Сотрудниках»', u.get('msgs30', 0) >= 3, u)
    check('новый человек из CRM появился в списке', any(x['phone'] == '+8613811111111' for x in j['users']), [x['phone'] for x in j['users']])
    call('settings_set', B, {'daily_limit': 1})
    codes = [gw({'a': 'ask', 'user': f'№{i}', 'who': STAFF})[0] for i in range(10)]
    check('лимит в день: одна команда = до 10 обращений', codes.count(429) >= 1 and codes[0] == 200, codes)
    c, j = gw({'a': 'ask', 'user': 'Босс спрашивает', 'who': {'phone': '+996555000001', 'name': 'Алексей', 'role': 'boss', 'boss': True}})
    check('руководителю лимит не мешает', c == 200 and j['ok'], (c, j))
    call('settings_set', B, {'daily_limit': 150})
    call('key_activate', B, {'id': bad_id})
    c, j = gw({'a': 'ask', 'user': 'Сломанный ключ', 'who': STAFF})
    check('шлюз тоже переключает сломанный ключ', c == 200 and j['ok'] and 'переключился' in j['switched'], j)
    src = open(f'{ROOT}/lib/auth.php', encoding='utf-8').read()
    check('вход спрашивает магазин по верному адресу (kosmos.php?a=who)', "/api/kosmos.php?a=who'" in src and 'kosmos_who.php\')' not in src)

    print('9б. Навыки «Космоса» и «Фила» — хранятся только здесь (0.3.1)')
    sk = lambda d, key=GW: gw({'a': 'skills', **d}, key)
    c, j = sk({'op': 'list'}); check('навыков пока нет', c == 200 and j['items'] == [], j)
    c, j = sk({'op': 'save', 'skill': {'title': 'КП для гостиницы', 'trig': 'просят КП для гостиницы или отеля', 'steps': '1) deals — сделка; 2) suppliers — фабрики; 3) make_kp — КП', 'trace': 'deals → suppliers → make_kp'}, 'author': 'Космос', 'who': 'Алексей', 'auto': True})
    check('навык записан', c == 200 and j['ok'] and j['new'] and j['id'] > 0, j); sid = j.get('id')
    c, j = sk({'op': 'pick', 'q': 'Сделай КП для отеля «Европа»'}); check('похожая просьба находит навык', c == 200 and [x['title'] for x in j['items']] == ['КП для гостиницы'], j)
    c, j = sk({'op': 'pick', 'q': 'Какая погода завтра'}); check('на постороннюю — ничего', c == 200 and j['items'] == [], j)
    sk({'op': 'used', 'id': sid}); c, j = sk({'op': 'list'}); check('применение посчитано', j['items'][0]['uses'] == 1, j)
    c, j = sk({'op': 'save', 'skill': {'title': 'КП  для гостиницы!', 'steps': '1) deals; 2) make_kp — короче'}, 'author': 'Космос', 'auto': True})
    check('то же название — обновление, без двойника', j.get('new') is False and len(sk({'op': 'list'})[1]['items']) == 1, j)
    c, j = sk({'op': 'save', 'id': sid, 'skill': {'title': 'КП для гостиницы', 'steps': '1) deals; 2) make_kp; проверить цены'}, 'author': 'Алексей', 'who': 'Алексей'})
    check('правка руководителя', c == 200 and j.get('ok') and sk({'op': 'list'})[1]['items'][0]['manual'] == 1, j)
    c, j = sk({'op': 'save', 'skill': {'title': 'КП для гостиницы', 'steps': '1) что-то совсем другое'}, 'author': 'Космос', 'auto': True})
    check('поправленное руководителем «Космос» сам не перезаписывает', j.get('skip') is True and sk({'op': 'list'})[1]['items'][0]['steps'].endswith('проверить цены'), j)
    c, j = sk({'op': 'save', 'skill': {'title': 'Пусто', 'steps': ''}}); check('без шагов — отказ', c == 400 and 'шаги' in j.get('error', ''), (c, j))
    c, j = sk({'op': 'import', 'items': [{'title': 'Сверка счёта', 'steps': '1) suppliers; 2) deals; сверить суммы', 'uses': 2, 'manual': 1, 'author': 'Космос'}, {'title': 'КП для гостиницы', 'steps': '1) двойник — не нужен'}]})
    check('перенос из магазина: новое добавлено, двойник пропущен', j.get('added') == 1 and len(sk({'op': 'list'})[1]['items']) == 2, j)
    c, j = sk({'op': 'del', 'id': sid}); check('удаление', j.get('deleted') is True and [x['title'] for x in sk({'op': 'list'})[1]['items']] == ['Сверка счёта'], j)
    c, j = sk({'op': 'import', 'items': [{'title': 'КП для гостиницы', 'steps': '1) deals; 2) make_kp — из копии магазина'}]})
    check('удалённый руководителем навык из копии магазина не возвращается (0.3.2)', j.get('added') == 0 and [x['title'] for x in sk({'op': 'list'})[1]['items']] == ['Сверка счёта'], j)
    c, j = sk({'op': 'list'}, 'b2' * 32); check('чужой ключ шлюза к навыкам не пускает', c == 403, (c, j))
    c, j = sk({'op': 'drop'}); check('неизвестное действие — 404', c == 404, (c, j))
    con = sqlite3.connect(f'{TMP}/kosmos.sqlite'); n = con.execute('SELECT COUNT(*) FROM skills').fetchone()[0]; con.close()
    check('навыки лежат в базе сервера (одна строка на навык)', n == 2, n)

    print('10. Личные ключи Claude без рабочей области (0.2.1)')
    BOSS = {'phone': '+996555000001', 'name': 'Алексей', 'role': 'boss', 'boss': True}
    c, j = call('key_add', B, {'name': 'Личный', 'key': 'sk-ant-usr1-' + 'd' * 26 + 'DDDD'}); k_u1 = j.get('key', {}).get('id')
    check('личный ключ: рабочую область выбрал сам, проверка прошла', c == 200 and j['test']['ok'] and j['test'].get('ws_auto') == 'Default', j)
    c, j = call('keys', B); ku = next(k for k in j['keys'] if k['id'] == k_u1)
    check('у ключа видна рабочая область', ku['ws'] == 'wrkspc_01Default00' and ku['ws_name'] == 'Default' and not ku['ws_issue'], ku)
    rec = [json.loads(l) for l in open(f'{TMP}/mock.log')][-1]
    check('в запросе к Claude есть anthropic-workspace-id', rec.get('ws') == 'wrkspc_01Default00', rec)
    g = [json.loads(l) for l in open(f'{TMP}/mock.log.get')]
    check('список рабочих областей просили вместе с Default', g and g[-1]['include_default'] == 'true' and g[-1]['ver'] == '2023-06-01', g[-1:])
    c, j = call('key_add', B, {'name': 'Личный 2', 'key': 'sk-ant-usr2-' + 'e' * 26 + 'EEEE'})
    check('из нескольких рабочих областей берёт Default', c == 200 and j['test']['ok'] and j['test'].get('ws_auto') == 'Default', j)
    c, j = call('key_add', B, {'name': 'Личный 3', 'key': 'sk-ant-usr3-' + 'f' * 26 + 'FFFF'}); k_u3 = j['key']['id']
    check('нет Default, областей несколько — просит выбрать', c == 200 and not j['test']['ok'] and 'рабочую область' in j['test']['status'], j)
    c, j = call('keys', B); ku = next(k for k in j['keys'] if k['id'] == k_u3)
    check('ключ помечен: нужна рабочая область', ku['ws_issue'] and ku['ws'] == '', ku)
    c, j = call('key_ws_list', B, {'id': k_u3})
    check('окно выбора: две рабочие области, архивной нет', c == 200 and [w['name'] for w in j['list']] == ['Продажи', 'Склад'], j)
    c, j = call('key_ws_set', B, {'id': k_u3, 'ws': 'не-то'}); check('неверный ID рабочей области не сохраняется', c == 400, (c, j))
    c, j = call('key_ws_set', B, {'id': k_u3, 'ws': 'wrkspc_99Nothing00'})
    check('чужая рабочая область — понятная ошибка', c == 200 and not j['test']['ok'] and 'не найдена' in j['test']['status'], j)
    c, j = call('key_ws_set', B, {'id': k_u3, 'ws': 'wrkspc_04Sklad000', 'name': 'Склад'}); ku = next(k for k in j['keys'] if k['id'] == k_u3)
    check('выбрал руководитель — ключ работает', j['test']['ok'] and ku['ws_name'] == 'Склад' and not ku['ws_issue'], j)
    c, j = call('key_add', B, {'name': 'Без прав', 'key': 'sk-ant-usrno-' + 'g' * 26 + 'GGGG'}); k_no = j['key']['id']
    c, j = call('key_ws_list', B, {'id': k_no})
    check('нет прав на список — подсказка ввести ID вручную', c == 200 and not j['list'] and 'вручную' in j['error'], j)
    c, j = call('key_ws_set', B, {'id': k_no, 'ws': 'wrkspc_05Manual00'}); check('ID вручную — ключ работает', c == 200 and j['test']['ok'], j)
    # как на живом сервере: ключ добавлен до 0.2.1, рабочей области нет — первое же обращение её находит
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite')
    db.execute("UPDATE keys SET ws='', ws_name='', status='Ошибка Claude: This API key is not scoped to a workspace' WHERE id=?", (k_u1,)); db.commit(); db.close()
    call('key_activate', B, {'id': k_u1})
    c, j = gw({'a': 'ask', 'user': 'Сколько сделок?', 'who': BOSS})
    check('старый личный ключ: шлюз сам нашёл рабочую область и ответил', c == 200 and j['ok'] and 'Ключ …DDDD' in j['text'], j)
    c, j = call('keys', B); ku = next(k for k in j['keys'] if k['id'] == k_u1)
    check('рабочая область запомнилась у ключа', ku['ws'] == 'wrkspc_01Default00', ku)
    c, evs = send(B, 'Привет через личный ключ')
    text = ''.join(e.get('text', '') for e in evs if e['t'] == 'delta')
    check('окно «Космоса» (поток) работает с личным ключом', 'Ключ …DDDD' in text and any(e['t'] == 'done' for e in evs), evs[-3:])
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); db.execute("UPDATE keys SET ws='', ws_name='' WHERE id=?", (k_u1,)); db.commit(); db.close()
    c, evs = send(B, 'Первый вопрос после обновления')
    text = ''.join(e.get('text', '') for e in evs if e['t'] == 'delta')
    check('поток: рабочую область нашёл сам посреди разговора, без ошибки', 'Ключ …DDDD' in text and not any(e['t'] == 'error' for e in evs), evs[-3:])
    c, j = call('keys', B); check('наружу по-прежнему не уходит сам ключ', 'sk-ant' not in json.dumps(j))
    html = open(f'{ROOT}/www/index.html', encoding='utf-8').read()
    check('поле ключа не password — браузер не подставит туда сохранённый пароль', 'id="kVal" type="text"' in html and '-webkit-text-security:disc' in html)
    # обновление базы: старая таблица keys без колонок ws/ws_name
    old = f'{TMP}/old_keys.sqlite'
    db = sqlite3.connect(old); db.execute("CREATE TABLE keys (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, enc TEXT NOT NULL, last4 TEXT NOT NULL DEFAULT '', active INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT '', checked_at TEXT NOT NULL DEFAULT '', created_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, deleted INTEGER NOT NULL DEFAULT 0)")
    db.execute("INSERT INTO keys (name, enc, created_at) VALUES ('Старый', 'x', '2026-11-13')"); db.commit(); db.close()
    oc = f'{TMP}/old_conf.php'; open(oc, 'w').write(CONF.replace(f'{TMP}/kosmos.sqlite', old))
    r = subprocess.run(['php', '-r', f"require '{ROOT}/lib/boot.php'; db(); db(); echo 'ok';"], env=dict(env, KOSMOS_CONFIG=oc), capture_output=True, text=True)
    db = sqlite3.connect(old); cols = [x[1] for x in db.execute('PRAGMA table_info(keys)')]; nm = db.execute('SELECT name FROM keys').fetchone()[0]; db.close()
    check('старая база получила колонки ws и ws_name, ключи на месте', r.stdout.endswith('ok') and 'ws' in cols and 'ws_name' in cols and nm == 'Старый', (r.stdout[-300:], r.stderr[-300:], cols))

    print('11. GitHub: рабочая копия на сервере (0.3.0)')
    c, j = call('git', B); check('пока агент не заводил копию — честно «ещё нет»', c == 200 and j['ok'] and j['git'] is None, j)
    json.dump({'repo': 'git@github.com:interseal/itc-system.git', 'dir': '/srv/itc-system', 'ok': True, 'pub': 'ssh-ed25519 AAAATEST kosmos-sg',
               'head': {'hash': 'abc1234', 'date': '2026-11-13T21:00:00+06:00', 'msg': 'первый'}, 'checked_at': '2026-11-13 21:10:00', 'pulled_at': '2026-11-13 21:10:00', 'files': 485},
              open(f'{TMP}/git.json', 'w'), ensure_ascii=False)
    c, j = call('git', B); check('настройки видят состояние копии и ключ сервера', j['git']['ok'] and j['git']['pub'].startswith('ssh-ed25519') and j['git']['files'] == 485, j)
    c, j = call('git', S); check('GitHub — только руководителю', c == 403, (c, j))

    print('12. Деньги Claude: цена ответов, счёт ключа, отчёт агента (0.3.3)')
    def php(code, cfg=None):
        return subprocess.run(['php', '-r', f"require '{ROOT}/lib/boot.php'; " + code], env=dict(env, KOSMOS_CONFIG=cfg or f'{TMP}/config.php'), capture_output=True, text=True)
    r = php("echo json_encode([cost_u('claude-haiku-5-5',100000,0,0,0), cost_u('claude-haiku-5-5',150000,0,0,0), cost_u('claude-haiku-5-5',50000,0,0,60000),"
            " cost_u('claude-opus-5-5',0,1000000,0,0), cost_u('claude-sonnet-5-5',0,0,1000000,0), cost_u('claude-fable-5-2',0,0,0,1000000),"
            " cost_u('claude-new-9',1000000,0,0,0), cost_u('claude-sonnet-5-5-20261007',1000,500,300,2000)]);")
    v = json.loads(r.stdout or 'null') if r.stdout.startswith('[') else None
    check('цена: Haiku — $0,10 за 1 млн входных токенов (100 000 токенов = $0,01)', v and v[0] == 10000, (r.stdout, r.stderr[-300:]))
    check('Haiku: запрос больше 100 000 токенов (с кэшем) — по старшей цене', v and v[1] == 75000 and v[2] == 28000, v)
    check('Opus выход $20, Sonnet запись кэша $2,50, Fable чтение кэша $0,20', v and v[3] == 20000000 and v[4] == 2500000 and v[5] == 200000, v)
    check('незнакомая модель — по цене Opus, модель с датой узнаётся', v and v[6] == 4000000 and v[7] == 7950, v)
    P = {'haiku': (0.10, 0.50, 0.125, 0.01), 'haiku+': (0.50, 2.50, 0.625, 0.05), 'sonnet': (2, 10, 2.5, 0.10), 'opus': (4, 20, 5, 0.20), 'fable': (8, 40, 10, 0.20)}
    def exp_cost(model, calls, ti, to, cr, cw):
        f = next((x for x in ('fable', 'opus', 'sonnet', 'haiku') if x in model), 'opus')
        if f == 'haiku' and (ti + cr + cw) // max(1, calls) > 100000: f = 'haiku+'
        pi, po, pw, pr = P[f]; return ti * pi + to * po + cw * pw + cr * pr
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite')
    rows = db.execute('SELECT model, calls, tokens_in, tokens_out, cache_read, cache_write, cost_u FROM usage').fetchall()
    bad = [x for x in rows if abs(x[6] - exp_cost(*x[:6])) > x[1]]
    check('у каждого ответа записана стоимость (usage.cost_u)', rows and not bad and all(x[6] > 0 for x in rows), bad or rows)
    orgs = dict(db.execute('SELECT id, org FROM keys').fetchall()); db.close()
    check('счёт ключа узнан из заголовка ответа Claude', orgs.get(k_u1) == '11111111-1111-4111-8111-111111111111', orgs)
    c, j = call('key_add', B, {'name': 'Второй счёт', 'key': 'sk-ant-org2-' + 'h' * 26 + 'HHHH'}); k_o2 = j['key']['id']
    c, j = call('key_add', B, {'name': 'Пустой счёт', 'key': 'sk-ant-nocredit-' + 'n' * 22 + 'NNNN'}); k_nc = j['key']['id']
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); orgs = dict(db.execute('SELECT id, org FROM keys').fetchall())
    check('проверка ключа тоже узнаёт счёт (и когда денег нет)', orgs.get(k_o2) == '22222222-2222-4222-8222-222222222222' and orgs.get(k_nc) == '11111111-1111-4111-8111-111111111111', orgs)
    db.execute("UPDATE keys SET status='На счёте аккаунта этого ключа закончились деньги — пополните или выберите другой ключ' WHERE id=?", (k_u1,)); db.commit(); db.close()
    c, evs = send(B, 'Вопрос после пополнения счёта')
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); st1 = db.execute('SELECT status FROM keys WHERE id=?', (k_u1,)).fetchone()[0]; db.close()
    check('ключ снова ответил — «деньги закончились» снято', any(e['t'] == 'done' for e in evs) and st1 == 'ok', (st1, evs[-1:]))
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'dbinfo'], env=env, capture_output=True, text=True, timeout=30)
    info = json.loads(r.stdout) if r.stdout.startswith('{') else {}; mk = {x['id']: x for x in info.get('money') or []}
    a1 = mk.get(k_u1, {})
    check('агент: расход ключа за сегодня, 7 и 30 дней и всего — для отчёта магазину', a1.get('a', 0) > 0 and a1['t'] == a1['w'] == a1['m'] == a1['a'] and a1['org'].startswith('1111') and a1['active'] and a1['d7'] == 1, (a1, r.stderr[-300:]))
    check('агент: «деньги закончились» видно у пустого счёта, у рабочего — нет', mk.get(k_nc, {}).get('out') is True and a1.get('out') is False, mk.get(k_nc))
    check('агент: сам ключ в отчёт не попадает', 'sk-ant' not in r.stdout and info.get('worker') is not None, r.stdout[:200])
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); tot = db.execute('SELECT SUM(cost_u) FROM usage WHERE key_id=?', (k_u1,)).fetchone()[0]; db.close()
    check('агент считает так же, как база', a1.get('a') == tot, (a1.get('a'), tot))
    # старая база (до 0.3.3): расход без стоимости — пересчитывается один раз по среднему размеру запроса
    old = f'{TMP}/old_usage.sqlite'
    db = sqlite3.connect(old)
    db.execute("CREATE TABLE usage (day TEXT NOT NULL, key_id INTEGER NOT NULL, model TEXT NOT NULL, calls INTEGER NOT NULL DEFAULT 0, tokens_in INTEGER NOT NULL DEFAULT 0,"
               " tokens_out INTEGER NOT NULL DEFAULT 0, cache_read INTEGER NOT NULL DEFAULT 0, cache_write INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, key_id, model))")
    db.executemany('INSERT INTO usage VALUES (?,?,?,?,?,?,?,?)', [('2026-11-10', 1, 'claude-sonnet-5-5', 10, 20000, 3000, 50000, 4000),
                   ('2026-11-11', 1, 'claude-haiku-5-5', 2, 240000, 1000, 0, 0), ('2026-11-12', 2, 'claude-haiku-5-5', 4, 40000, 2000, 0, 0)])
    db.commit(); db.close()
    oc2 = f'{TMP}/old_conf2.php'; open(oc2, 'w').write(CONF.replace(f'{TMP}/kosmos.sqlite', old))
    r1 = php("db(); echo 'ok';", oc2); r2 = php("db(); echo 'ok';", oc2)
    db = sqlite3.connect(old); got = {d: c for d, c in db.execute('SELECT day, cost_u FROM usage')}; kc = [x[1] for x in db.execute('PRAGMA table_info(keys)')]; db.close()
    check('старая база: стоимость пересчитана один раз (Sonnet, Haiku большой и малый)', r1.stdout == r2.stdout == 'ok' and got == {'2026-11-10': 85000, '2026-11-11': 122500, '2026-11-12': 5000}, (got, r1.stderr[-300:]))
    check('у ключей появилась колонка счёта (org)', 'org' in kc, kc)

    print('13. Сейф Bitwarden: токен в настройках, агент сам кладёт паспорт и ключи (0.3.4)')
    TOK = '0.' + '1b2c3d4e-0000-4000-8000-00000000abcd' + '.' + 'S3cretPart0123456789' + ':' + 'QUJDREVGR0hJSktMTU5PUFFSU1RVVg=='
    c, j = call('bw', S); check('сейф — только руководителю', c == 403, (c, j))
    c, j = call('bw', B); check('токена ещё нет', j.get('ok') and j['set'] is False, j)
    c, j = call('bw_set', B, {'token': 'просто текст'}); check('не токен — понятный отказ', c == 400 and 'токен' in j.get('error', ''), (c, j))
    c, j = call('bw_set', B, {'token': TOK}); c2, j2 = call('bw', B)
    check('токен сохранён, наружу — только 4 последних знака', j.get('ok') and j2['set'] and j2['last4'] == TOK[-4:] and TOK not in json.dumps(j2), j2)
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); raw = db.execute("SELECT v FROM settings WHERE k='bw_token'").fetchone()[0]; db.close()
    check('в базе токен зашифрован', raw and TOK not in raw and 'S3cret' not in raw, raw[:30])
    G = f'{TMP}/git'; os.makedirs(f'{G}/docs', exist_ok=True)
    big = '# Паспорт\n' + ''.join(f'\n## {i}. Раздел\n' + ('строка паспорта ' * 900) for i in range(4))
    open(f'{G}/docs/ITC_pasport_sistemy.md', 'w').write(big); open(f'{G}/docs/SEICHAS_V_RABOTE.md', 'w').write('# Сейчас в работе\n\nвсё хорошо\n')
    open(f'{TMP}/gitkey', 'w').write('-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----\n'); open(f'{TMP}/gw.key', 'w').write('ab' * 32 + '\n')
    FB = f'{TMP}/fake_bws.json'
    ac = f'{TMP}/agent_conf.php'
    open(ac, 'w').write(CONF.replace("];", f"  'bws_bin' => '{ROOT}/tests/fake_bws.py', 'git_dir' => '{G}', 'git_key' => '{TMP}/gitkey', 'db_user' => '', 'agent_state' => '{TMP}/agent_state.json',\n];"))
    aenv = dict(env, KOSMOS_CONFIG=ac, FAKE_BWS_DB=FB)
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'bw'], env=aenv, capture_output=True, text=True, timeout=60)
    st = json.load(open(f'{TMP}/bw.json')) if os.path.exists(f'{TMP}/bw.json') else {}
    fb = json.load(open(FB)) if os.path.exists(FB) else {'secrets': [], 'calls': []}
    keys = {x['key']: x for x in fb['secrets']}
    check('агент положил в сейф паспорт частями до 24 000 байт', r.returncode == 0 and 'SYSTEM_HANDOVER' in keys and 'SYSTEM_HANDOVER_2' in keys
          and all(len(keys[k]['value'].encode()) <= 24000 for k in keys if k.startswith('SYSTEM_HANDOVER'))
          and ''.join(keys[k]['value'] for k in sorted((k for k in keys if k.startswith('SYSTEM_HANDOVER')), key=lambda k: int(k.split('_')[-1]) if k[-1].isdigit() else 1)) == big, (r.stdout[-400:], r.stderr[-400:], list(keys)))
    check('и «Сейчас в работе», ключи сервера', 'SEICHAS_V_RABOTE' in keys and keys['KOSMOS_GW_KEY']['value'] == 'ab' * 32 and 'KOSMOS_GITHUB_DEPLOY_KEY' in keys and len(keys.get('KOSMOS_MASTER_KEY', {}).get('value', '')) > 40, list(keys))
    ck = [k for k in keys if k.startswith('CLAUDE_KEY_')]
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); nk = db.execute('SELECT COUNT(*) FROM keys WHERE deleted=0').fetchone()[0]; db.close()
    check('ключи Claude — каждый своей записью, сам ключ (sk-ant-…)', len(ck) == nk and all(keys[k]['value'].startswith('sk-ant-') for k in ck) and 'CLAUDE_KEY_VTOROY_SCHET' in ck, ck)
    check('состояние для настроек: в порядке, проект «Авто», токена нет', st.get('ok') and st.get('project') == 'Авто' and TOK not in json.dumps(st) and 'S3cret' not in r.stdout + r.stderr, st)
    n1 = len(fb['calls'])
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'bw'], env=aenv, capture_output=True, text=True, timeout=60); fb = json.load(open(FB))
    check('повторно — ничего не переписывает (только проверка)', [c for c in fb['calls'][n1:] if c[1] in ('create', 'edit')] == [], fb['calls'][n1:])
    open(f'{G}/docs/SEICHAS_V_RABOTE.md', 'w').write('# Сейчас в работе\n\nновое\n'); n1 = len(fb['calls'])
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'bw'], env=aenv, capture_output=True, text=True, timeout=60); fb = json.load(open(FB))
    keys = {x['key']: x for x in fb['secrets']}
    check('изменился файл — правится только он', [c[1] for c in fb['calls'][n1:] if c[1] in ('create', 'edit')] == ['edit'] and 'новое' in keys['SEICHAS_V_RABOTE']['value'], fb['calls'][n1:])
    c, j = call('bw', B); check('настройки видят: в порядке, сколько записей', j['state']['ok'] and len(j['state']['items']) == len(keys), j.get('state'))
    call('bw_set', B, {'token': TOK.replace('S3cret', 'S3bad0')})
    r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'bw'], env=aenv, capture_output=True, text=True, timeout=60)
    c, j = call('bw', B); check('сейф не принял токен — понятная причина в настройках', not j['state']['ok'] and 'новый токен' in j['state'].get('err', ''), j['state'])
    call('bw_del', B); c, j = call('bw', B); check('токен можно убрать', j['set'] is False, j)
    html = open(f'{ROOT}/www/index.html', encoding='utf-8').read()
    check('вкладка «Сейф» в настройках, поле не password', 'data-t="bw"' in html and 'id="bwVal" type="text"' in html)

    print('14. Навыки Claude из GitHub → навыки «Космоса» и «Фила» (0.3.5)')
    import shutil as _sh
    os.makedirs(f'{G}/docs/skills', exist_ok=True)
    for f in ('claude-dengi.md', 'kosmos-server.md', 'README.md'): _sh.copy(f'{os.path.dirname(ROOT)}/docs/skills/{f}', f'{G}/docs/skills/{f}')
    open(f'{G}/docs/skills/plokhoy.md', 'w').write('без заголовка и шагов')
    def run_sk():
        r = subprocess.run(['php', f'{ROOT}/bin/agent.php', 'skills'], env=aenv, capture_output=True, text=True, timeout=60)
        db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); rows = db.execute("SELECT title, author, manual, deleted, src, steps FROM skills WHERE src LIKE 'claude:%' ORDER BY src").fetchall(); db.close()
        return r, rows
    r, rows = run_sk()
    live = [x for x in rows if not x[3]]
    check('два навыка Claude перенесены: автор Claude, правленые (их не перепишет «Космос»)', len(live) == 2 and all(x[1] == 'Claude' and x[2] == 1 for x in live)
          and {x[4] for x in live} == {'claude:claude-dengi', 'claude:kosmos-server'}, (rows, r.stdout[-300:], r.stderr[-300:]))
    check('README и файл без шагов не стали навыками, в журнале причина', 'plokhoy.md' in r.stdout and not any('README' in x[4] for x in rows), r.stdout[-300:])
    c, j = gw({'a': 'skills', 'op': 'pick', 'q': 'сколько денег осталось на счёте Claude, баланс'}, key='ab' * 32)
    check('«Космос» и «Фил» находят навык Claude по вопросу (шлюз)', c == 200 and j['items'] and j['items'][0]['author'] == 'Claude' and 'Деньги Claude' in j['items'][0]['title'], j)
    r, rows2 = run_sk(); check('повторно ничего не меняется', rows2 == rows and 'новых' not in r.stdout, r.stdout[-200:])
    open(f'{G}/docs/skills/claude-dengi.md', 'a').write('7) Новая строка навыка.\n'); os.remove(f'{G}/docs/skills/kosmos-server.md')
    r, rows = run_sk(); d = {x[4]: x for x in rows}
    check('файл изменился — навык обновлён; файл убрали — навык удалён', 'Новая строка' in d['claude:claude-dengi'][5] and d['claude:claude-dengi'][3] == 0 and d['claude:kosmos-server'][3] == 1, rows)
    print('15. Руководитель: приостановить, поправить, удалить навык (0.3.6)')
    db = sqlite3.connect(f'{TMP}/kosmos.sqlite'); sid = db.execute("SELECT id FROM skills WHERE src='claude:claude-dengi'").fetchone()[0]; db.close()
    K = 'ab' * 32
    c, j = gw({'a': 'skills', 'op': 'pause', 'id': sid, 'paused': True, 'who': 'Алексей'}, key=K)
    c2, j2 = gw({'a': 'skills', 'op': 'pick', 'q': 'сколько денег осталось на счёте Claude, баланс'}, key=K)
    c3, j3 = gw({'a': 'skills', 'op': 'list'}, key=K); it = next(x for x in j3['items'] if x['id'] == sid)
    check('приостановленный навык не предлагается, но в списке есть с отметкой', j.get('ok') and not any(x['id'] == sid for x in j2['items']) and it['paused'] == 1 and it['claude'] == 1, (j, j2, it))
    open(f'{G}/docs/skills/claude-dengi.md', 'a').write('8) Ещё строка.\n'); r, rows = run_sk(); d = {x[4]: x for x in rows}
    check('файл Claude изменился, но руководитель навык трогал — его решение остаётся', '8) Ещё' not in d['claude:claude-dengi'][5] and d['claude:claude-dengi'][3] == 0, d['claude:claude-dengi'])
    gw({'a': 'skills', 'op': 'pause', 'id': sid, 'paused': False, 'who': 'Алексей'}, key=K)
    c, j = gw({'a': 'skills', 'op': 'save', 'id': sid, 'skill': {'title': 'Деньги Claude (мой вариант)', 'trig': 'баланс', 'steps': 'Смотреть Billing и страницу Обновления.'}, 'author': 'Алексей', 'who': 'Алексей'}, key=K)
    gw({'a': 'skills', 'op': 'del', 'id': sid, 'who': 'Алексей'}, key=K)
    os.remove(f'{G}/docs/skills/claude-dengi.md'); open(f'{G}/docs/skills/claude-dengi.md', 'w').write('# Деньги Claude\nКогда: баланс\n---\nновый текст от Claude, длинный достаточно\n')
    r, rows = run_sk(); d = {x[4]: x for x in rows}
    check('удалённый руководителем навык Claude не воскресает', d['claude:claude-dengi'][3] == 1 and 'мой вариант' in d['claude:claude-dengi'][0], d['claude:claude-dengi'])
finally:
    for p in procs: p.terminate()
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
php_log = open(f'{TMP}/php.log').read()
errs = [l for l in php_log.split('\n') if 'Fatal' in l or 'Warning' in l or 'Deprecated' in l or 'Notice' in l]
if errs: print('Ошибки PHP:\n' + '\n'.join(errs[:20]))
sys.exit(1 if fail_n else 0)
