#!/usr/bin/env python3
"""Полная проверка пути «магазин ↔ сервер Космоса» в этом контейнере:
1) api/kosmos.php в магазине (поддельный config.php вместо настоящего);
2) настоящая установка сервера одноразовой командой (apt, файлы, nginx, PHP-FPM; без systemd);
3) восстановление памяти из ночной копии при замене сервера;
4) работа через nginx: вход, поток ответа (по кусочкам), задачи, права на файлы базы;
5) агент: отчёт, ночная копия, обновление версии (и отказ при неверной подписи).
Запуск (от root, из папки kosmos): python3 tests/store_e2e.py
"""
import base64, gzip, hashlib, json, os, re, shutil, subprocess, sys, time, urllib.request, urllib.error, sqlite3, tarfile, io

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
T = '/tmp/kst'
STORE = 'http://127.0.0.1:8790'
shutil.rmtree(T, ignore_errors=True); os.makedirs(f'{T}/store/api'); os.makedirs(f'{T}/store/uploads/kosmos/pkg')
ok_n = fail_n = 0
def check(name, cond, extra=''):
    global ok_n, fail_n
    if cond: ok_n += 1; print('  ✓', name)
    else: fail_n += 1; print('  ✗', name, str(extra)[:600])

def http(url, data=None, headers=None, method=None, raw=False, timeout=60):
    if isinstance(data, (dict, list)): data = json.dumps(data).encode(); headers = {'Content-Type': 'application/json', **(headers or {})}
    req = urllib.request.Request(url, data=data, headers=headers or {}, method=method or ('POST' if data is not None else 'GET'))
    try:
        with urllib.request.urlopen(req, timeout=timeout) as r: b = r.read(); c = r.status; h = dict(r.headers)
    except urllib.error.HTTPError as e: b = e.read(); c = e.code; h = dict(e.headers)
    if raw: return c, b, h
    try: return c, json.loads(b)
    except Exception: return c, {'_raw': b[:300]}

def ks(a, tok=None, srv=None, data=None, q='', raw=False):
    h = {'X-Kosmos-Token': srv} if srv else {}
    url = f'{STORE}/api/kosmos.php?a={a}' + (f'&t={tok}' if tok else '') + q
    return http(url, data if data is not None else ({} if not raw else None), h, raw=raw)

# ---------- поддельный магазин ----------
open(f'{T}/store/api/config.php', 'w').write(r'''<?php
define('KOSMOS_STORE_URL', 'http://127.0.0.1:8790');
function db() { static $p; if (!$p) { $p = new PDO('sqlite:/tmp/kst/store.sqlite'); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC); } return $p; }
function authUser($role = null) {
  $t = $_GET['t'] ?? '';
  $map = ['boss-t' => ['+996555000001','itc','boss'], 'owner-t' => ['+996555000009','itc',''], 'mgr-t' => ['+996555000002','itc','manager'],
          'off-t' => ['+996555000003','itc','manager'], 'cust-t' => ['+79990000000','customer','']];
  if (!isset($map[$t])) { http_response_code(401); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Войдите заново']); exit; }
  [$ph, $r, $sr] = $map[$t];
  if ($role === 'itc' && $r !== 'itc') { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>'Только для сотрудников']); exit; }
  return ['phone'=>$ph, 'role'=>$r, 'staff_role'=>$sr];
}
''')
db = sqlite3.connect(f'{T}/store.sqlite')
db.execute('CREATE TABLE staff (phone TEXT, name TEXT, role TEXT, active INT)')
db.executemany('INSERT INTO staff VALUES (?,?,?,?)', [('+996555000001', 'Алексей', 'boss', 1), ('+996555000002', 'Айгуль', 'manager', 1), ('+996555000003', 'Бакыт', 'manager', 0)])
db.commit(); db.close()
shutil.copy(f'{ROOT}/store/api/kosmos.php', f'{T}/store/api/kosmos.php')
shutil.copy(f'{ROOT}/store/api/kosmos_lib.php', f'{T}/store/api/kosmos_lib.php')
subprocess.run(['openssl', 'genpkey', '-algorithm', 'ed25519', '-out', f'{T}/sign.pem'], check=True)
pk = json.loads(subprocess.run([sys.executable, f'{ROOT}/tools/build_pkg.py', '--key', f'{T}/sign.pem', '--out', f'{T}/store/uploads/kosmos/pkg', '--notes', 'первая версия'], check=True, capture_output=True, text=True).stdout)
procs = [subprocess.Popen(['php', '-S', '127.0.0.1:8790', '-t', f'{T}/store'], stdout=open(f'{T}/store.log', 'w'), stderr=subprocess.STDOUT),
         subprocess.Popen([sys.executable, f'{ROOT}/tests/mock_claude.py', '8767', f'{T}/mock.log'])]
time.sleep(1)

try:
    print('1. Магазин: страница статуса и подключение')
    c, j = ks('status', 'mgr-t'); check('сотрудник видит статус, сервер не готов', j.get('ok') and j['ready'] is False and j['boss'] is False and 'detail' not in j, j)
    c, j = ks('status', 'boss-t'); check('руководитель видит пакет 0.1.0', j['detail']['pkgs'] == [pk['ver']] and j['linked'] is False, j)
    c, j = ks('status', 'cust-t'); check('покупателю магазина — нет доступа', c == 403, (c, j))
    c, j = ks('connect', 'mgr-t'); check('сотрудник не получает команду установки', c == 403, (c, j))
    c, j = ks('connect', 'owner-t'); check('владелец (роль пустая) — как руководитель', c == 200 and j.get('ok'), j)
    c, j = ks('connect', 'boss-t'); cmd = j['cmd']
    check('команда установки выдана', re.search(r"a=install&c=[a-f0-9]{32}' \| sudo bash$", cmd) is not None, cmd)
    url = re.search(r"'([^']+)'", cmd).group(1)
    c, script, _ = http(url, raw=True); script = script.decode()
    tok = re.search(r"printf '%s' '([a-f0-9]{64})' > /etc/kosmos/store_token", script)
    check('установщик содержит служебный ключ и пакет', tok is not None and pk['file'] in script and pk['sha256'] in script, script[:400])
    tok = tok.group(1)
    c, again, _ = http(url, raw=True); check('команда одноразовая', 'устарела' in again.decode(), again[:200])
    c, j = ks('who', srv='x' * 40, data={'t': 'mgr-t'}); check('who: чужой служебный ключ — отказ', c == 403, (c, j))
    c, j = ks('who', srv=tok, data={'t': 'mgr-t'}); check('who: сотрудник узнан по пропуску в теле запроса', j.get('ok') and j['name'] == 'Айгуль' and j['boss'] is False and j['staff_role'] == 'manager', j)
    c, j = ks('who', srv=tok, data={'t': 'off-t'}); check('who: отключённый сотрудник — отказ', c == 403 and 'отключён' in j.get('error', ''), (c, j))
    c, j = ks('who', srv=tok, data={'t': 'owner-t'}); check('who: владелец — руководитель', j.get('boss') is True and j['name'] == 'Руководитель', j)
    c, j = ks('who', srv=tok, data={'t': 'cust-t'}); check('who: покупатель — отказ', c == 403, (c, j))
    c, j = ks('pkgs', srv=tok); check('список пакетов для сервера', [p['ver'] for p in j['pkgs']] == [pk['ver']], j)
    c, b, _ = ks('pkg', srv=tok, q='&f=' + pk['file'], raw=True); check('пакет скачивается целым', hashlib.sha256(b).hexdigest() == pk['sha256'])
    c, b, _ = ks('pkg', srv=tok, q='&f=../../api/config.php', raw=True); check('чужие файлы через pkg не отдаются', c == 404, c)

    print('2. Ночная копия «старого» сервера (для проверки замены сервера)')
    old = f'{T}/old.sqlite'
    env_old = dict(os.environ, KOSMOS_CONFIG=f'{T}/old_config.php')
    open(f'{T}/old.key', 'wb').write(os.urandom(48))
    open(f'{T}/old_config.php', 'w').write(f"<?php return ['db' => '{old}', 'master_key_file' => '{T}/old.key', 'store_token_file' => '{T}/none', 'claude_url' => 'http://127.0.0.1:8767/v1/messages', 'dev_users' => ['boss-token' => ['+996555000001', 'Алексей', 'boss', true]]];")
    php_old = r'''require '%s/lib/boot.php'; $me = ['phone'=>'+996555000001','name'=>'Алексей','role'=>'boss','boss'=>true];
      mem_add($me, 'company', 'Оплата фабрикам — только после согласования руководителя', 'тест');
      mem_add($me, 'user', 'Отчёты руководителю — коротко, сначала цифры', 'тест');
      key_add('Старый ключ', 'sk-ant-good-' . str_repeat('o', 26) . 'OLD1', $me['phone']);
      $t = thread_new($me, 'Старый разговор'); msg_add($t['id'], 'user', 'Помнишь про оплату?'); echo 'ok';''' % ROOT
    r = subprocess.run(['php', '-r', php_old], env=env_old, capture_output=True, text=True); check('старая база подготовлена', r.stdout.endswith('ok'), r.stdout + r.stderr)
    s = sqlite3.connect(old); s.execute('PRAGMA wal_checkpoint(TRUNCATE)'); s.close()
    gz = gzip.compress(open(old, 'rb').read()); sha = hashlib.sha256(gz).hexdigest(); half = len(gz) // 2
    c, j = ks('backup', srv=tok, data=gz[:half], q='&day=20261112&off=0'); check('копия: первая часть принята', j.get('ok'), j)
    c, j = ks('backup', srv=tok, data=gz[half:], q='&day=20261112&off=999'); check('копия: неверное смещение — отказ', c == 409, (c, j))
    c, j = ks('backup', srv=tok, data=gz[half:], q=f'&day=20261112&off={half}&last=1&sha={sha}'); check('копия: собрана целиком', j.get('ok'), j)
    c, j = ks('backups', srv=tok); check('копия в списке, отпечаток верный', j['backups'][0]['sha'] == sha, j)
    c, j = ks('report', srv=tok, data={'ver': '0.0.9', 'ip': '1.2.3.4', 'worker': True}); c, j = ks('status', 'boss-t')
    check('отчёт сервера — «на связи»', j['online'] is True and j['detail']['ip'] == '1.2.3.4' and j['ready'] is False, j)

    print('3. Установка сервера одноразовой командой (настоящая, в этом контейнере)')
    for p in ['/opt/kosmos', '/etc/kosmos', '/var/backups/kosmos', '/var/lib/kosmos-agent.json']:
        subprocess.run(['rm', '-rf', p])
    os.makedirs('/etc/kosmos', exist_ok=True)
    open('/etc/kosmos/config.php', 'w').write(f"""<?php return [
  'store_url' => '{STORE}', 'db' => '/opt/kosmos/data/kosmos.sqlite', 'tz' => 'Asia/Bishkek',
  'claude_url' => 'http://127.0.0.1:8767/v1/messages', 'meta_url' => 'http://127.0.0.1:9/', 'agent_state' => '{T}/agent.json',
  'dev_users' => ['boss-token' => ['+996555000001', 'Алексей', 'boss', true], 'staff-token' => ['+996555000002', 'Айгуль', 'manager', false]],
];""")
    c, j = ks('connect', 'boss-t'); url = re.search(r"'([^']+)'", j['cmd']).group(1)
    t0 = time.time()
    r = subprocess.run(['bash', '-c', f"curl -fsSL '{url}' | bash"], capture_output=True, text=True, timeout=900)
    out = r.stdout + r.stderr
    print('    (установка заняла', int(time.time() - t0), 'с)')
    check('установка прошла', r.returncode == 0 and 'готово, версия ' + pk['ver'] in out, out[-1500:])
    check('память поднята из ночной копии', 'восстановлена память' in out, out[-800:])
    st = os.stat('/opt/kosmos/data/kosmos.sqlite'); import pwd
    check('база принадлежит www-data', pwd.getpwuid(st.st_uid).pw_name == 'www-data', st)
    check('ключ шифрования создан, закрыт', oct(os.stat('/etc/kosmos/master.key').st_mode)[-3:] == '640')
    check('файлы приложения не может менять www-data', pwd.getpwuid(os.stat('/opt/kosmos/lib/boot.php').st_uid).pw_name == 'root')
    tok2 = open('/etc/kosmos/store_token').read().strip()
    c, j = ks('who', srv=tok, data={'t': 'mgr-t'}); check('старый служебный ключ больше не действует', c == 403, (c, j))
    c, j = ks('who', srv=tok2, data={'t': 'mgr-t'}); check('новый служебный ключ действует', j.get('ok'), j)

    print('4. Работа через nginx и PHP-FPM')
    os.makedirs('/run/php', exist_ok=True)
    subprocess.run(['pkill', '-f', 'php-fpm'], capture_output=True); subprocess.run(['nginx', '-s', 'stop'], capture_output=True); time.sleep(0.5)
    r1 = subprocess.run(['php-fpm8.3', '-t'], capture_output=True, text=True); check('PHP-FPM: настройки верны', r1.returncode == 0, r1.stderr)
    r2 = subprocess.run(['nginx', '-t'], capture_output=True, text=True); check('nginx: настройки верны', r2.returncode == 0, r2.stderr)
    subprocess.run(['php-fpm8.3', '-D']); subprocess.run(['nginx']); time.sleep(1)
    c, b, h = http('http://127.0.0.1/', raw=True)
    check('страница открывается, встраивание только в магазин', c == 200 and 'frame-ancestors' in h.get('Content-Security-Policy', '') and b'<title>' in b, (c, h))
    c, b, h = http('http://127.0.0.1/../lib/boot.php', raw=True); check('код сервера снаружи не виден', c in (400, 404), c)
    API = 'http://127.0.0.1/api.php?a='
    H = {'X-Eco-Token': 'boss-token'}
    # 0.2.0 · вход настоящим пропуском Экосистемы: сервер спрашивает магазин (kosmos.php?a=who)
    c, j = http(API + 'me', {}, {'X-Eco-Token': 'mgr-t'}); check('вход пропуском Экосистемы (магазин подтверждает)', c == 200 and j.get('me', {}).get('name') == 'Айгуль' and j['me']['boss'] is False, (c, j))
    c, j = http(API + 'me', {}, {'X-Eco-Token': 'wrong-t'}); check('чужой пропуск — «войдите заново»', c == 401, (c, j))
    c, j = http(API + 'memory', {}, H); check('память старого сервера на месте', len(j.get('items', [])) == 2, j)
    c, j = http(API + 'threads', {}, H); check('старый разговор на месте', any(t['title'] == 'Старый разговор' for t in j.get('threads', [])), j)
    c, j = http(API + 'keys', {}, H); check('старый ключ помечен «добавьте заново»', j['keys'][0].get('broken') is True, j)
    c, j = http(API + 'me', {}, H); check('«Космос» сообщает, что ключ нужно добавить', j['ready'] is False, j)
    c, j = http(API + 'key_add', {'name': 'Новый', 'key': 'sk-ant-good-' + 'n' * 26 + 'NEW1'}, H)
    check('новый ключ добавлен через nginx', j.get('test', {}).get('ok') is True, j)
    old_id = [k for k in http(API + 'keys', {}, H)[1]['keys'] if k.get('broken')][0]['id']
    http(API + 'key_delete', {'id': old_id}, H)
    c, j = http(API + 'key_activate', {'id': j['key']['id']}, H)
    # поток: первые слова должны прийти задолго до конца ответа
    req = urllib.request.Request(API + 'send', data=json.dumps({'text': 'Ответь медленно, пожалуйста, подробно и длинно'}).encode(), headers={'Content-Type': 'application/json', **H}, method='POST')
    t0 = time.time(); first = None; n = 0
    with urllib.request.urlopen(req, timeout=120) as resp:
        while True:
            line = resp.readline()
            if not line: break
            if line.startswith(b'data: ') and b'"delta"' in line:
                n += 1
                if first is None: first = time.time() - t0
    total = time.time() - t0
    check('ответ идёт по кусочкам через nginx (не копится)', first is not None and n > 5 and total - first > 1.0, (first, total, n))
    c, b, h = http(API + 'send', {'text': 'Сделай сложный план', 'deep': True}, H, raw=True)
    tid = [json.loads(l[6:]) for l in b.decode().split('\n') if l.startswith('data: ')][0]['thread_id']
    r = subprocess.run(['runuser', '-u', 'www-data', '--', 'env', 'KOSMOS_CONFIG=/etc/kosmos/config.php', 'php', '/opt/kosmos/bin/worker.php', 'once'], capture_output=True, text=True, timeout=60)
    c, j = http(API + 'messages', {'thread_id': tid}, H)
    check('служба задач работает от www-data', j['tasks'][0]['status'] == 'done', (j.get('tasks'), r.stderr[-300:]))

    print('5. Агент: отчёт, копия, обновление')
    r = subprocess.run(['php', '/opt/kosmos/bin/agent.php'], capture_output=True, text=True, timeout=120)
    c, j = ks('status', 'boss-t'); check('агент отчитался магазину (версия, ключ)', j['online'] and j['ver'] == pk['ver'] and j['detail']['key'] is True, (j, r.stdout[-300:]))
    gwf = '/etc/kosmos/gw.key'; st = os.stat(gwf) if os.path.exists(gwf) else None
    import grp
    check('агент получил ключ шлюза от магазина (root:www-data, 640)', st is not None and oct(st.st_mode)[-3:] == '640' and grp.getgrgid(st.st_gid).gr_name == 'www-data', (st, r.stdout[-300:]))
    gw = open(gwf).read().strip() if st else ''
    c, j = http('http://127.0.0.1/api.php?a=gw', {'a': 'status'}, {'X-Kosmos-GW': gw}); check('шлюз через nginx отвечает магазину', c == 200 and j.get('ok') and j.get('active') == 'Новый', (c, j))
    c, j = http('http://127.0.0.1/api.php?a=gw', {'a': 'ask', 'user': 'Сколько сделок?', 'who': {'phone': '+996555000002', 'name': 'Айгуль', 'role': 'manager'}}, {'X-Kosmos-GW': gw})
    check('вопрос к Claude через шлюз (nginx, PHP-FPM)', c == 200 and j.get('ok') and 'NEW1' in j.get('text', ''), (c, j))
    c, j = http('http://127.0.0.1/api.php?a=gw', {'a': 'status'}, {'X-Kosmos-GW': 'f' * 64}); check('чужой ключ шлюза — отказ', c == 403, c)
    r = subprocess.run(['php', '/opt/kosmos/bin/agent.php', 'backup'], capture_output=True, text=True, timeout=120)
    c, j = ks('backups', srv=tok2); check('ночная копия ушла в магазин', len(j['backups']) == 2, (j, r.stdout))
    check('после копии база по-прежнему у www-data', pwd.getpwuid(os.stat('/opt/kosmos/data/kosmos.sqlite').st_uid).pw_name == 'www-data')
    # новая версия 0.1.1 — копия исходников с поднятым номером
    src2 = f'{T}/src2'; shutil.copytree(ROOT, src2, ignore=shutil.ignore_patterns('tests', 'store'))
    bp = f'{src2}/lib/boot.php'; txt = open(bp, encoding='utf-8').read(); open(bp, 'w', encoding='utf-8').write(txt.replace(f"KOSMOS_VERSION = '{pk['ver']}'", "KOSMOS_VERSION = '0.2.99'"))
    bad = f'{T}/bad.pem'; subprocess.run(['openssl', 'genpkey', '-algorithm', 'ed25519', '-out', bad], check=True)
    pk_bad = json.loads(subprocess.run([sys.executable, f'{src2}/tools/build_pkg.py', '--key', bad, '--out', f'{T}/store/uploads/kosmos/pkg'], check=True, capture_output=True, text=True).stdout)
    r = subprocess.run(['php', '/opt/kosmos/bin/agent.php', 'update'], capture_output=True, text=True, timeout=300)
    check('пакет с чужой подписью не ставится', 'подпись не сходится' in r.stdout and open('/opt/kosmos/VERSION').read().strip() == pk['ver'], r.stdout)
    pk2 = json.loads(subprocess.run([sys.executable, f'{src2}/tools/build_pkg.py', '--key', f'{T}/sign.pem', '--out', f'{T}/store/uploads/kosmos/pkg'], check=True, capture_output=True, text=True).stdout)
    r = subprocess.run(['php', '/opt/kosmos/bin/agent.php', 'update'], capture_output=True, text=True, timeout=300)
    check('новая версия с верной подписью поставилась', open('/opt/kosmos/VERSION').read().strip() == '0.2.99', r.stdout[-800:])
    c, j = http(API + 'me', {}, H); check('после обновления всё работает, память цела', j.get('version') == '0.2.99' and len(http(API + 'memory', {}, H)[1]['items']) == 2, j)
finally:
    for p in procs: p.terminate()
    subprocess.run(['nginx', '-s', 'stop'], capture_output=True); subprocess.run(['pkill', '-f', 'php-fpm'], capture_output=True)
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
sys.exit(1 if fail_n else 0)
