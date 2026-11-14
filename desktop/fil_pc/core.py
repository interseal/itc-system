"""«Фил на компьютере» — ядро (без Windows): связь с магазином, подключение, поручения.
Руки в WeChat — wx_engine.py (на Windows). Здесь только логика, её проверяют tests/test_core.py на любом компьютере."""
import base64, hashlib, json, os, random, socket, time, urllib.request, urllib.error

APP_VER = '1.1.0'
STORE = os.environ.get('FIL_PC_STORE', 'https://itcpartner.store')
HOME = os.environ.get('FIL_PC_HOME') or os.path.join(os.environ.get('LOCALAPPDATA', os.path.expanduser('~')), 'ITC', 'FilPC')
os.makedirs(HOME, exist_ok=True)
STATE = os.path.join(HOME, 'state.json')
MAX_FILE = 20 * 1024 * 1024


def log(*a):
    line = time.strftime('%Y-%m-%d %H:%M:%S ') + ' '.join(str(x) for x in a)
    try:
        with open(os.path.join(HOME, 'fil_pc.log'), 'a', encoding='utf-8') as f: f.write(line + '\n')
    except OSError: pass


def load():
    try: return json.load(open(STATE, encoding='utf-8'))
    except Exception: return {}


def save(s):
    tmp = STATE + '.tmp'; json.dump(s, open(tmp, 'w', encoding='utf-8'), ensure_ascii=False); os.replace(tmp, STATE)


class Store:
    def __init__(self, base=STORE, token=''):
        self.base, self.token = base.rstrip('/'), token

    def call(self, action, data=None, timeout=60):
        req = urllib.request.Request(f'{self.base}/api/flow.php?action={action}', data=json.dumps(data or {}, ensure_ascii=False).encode('utf-8'),
                                     headers={'Content-Type': 'application/json', 'X-PC-Token': self.token, 'User-Agent': f'FilPC/{APP_VER}'}, method='POST')
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r: return json.loads(r.read().decode('utf-8'))
        except urllib.error.HTTPError as e:
            try: return json.loads(e.read().decode('utf-8')) | {'_code': e.code}
            except Exception: return {'ok': False, 'error': f'код {e.code}', '_code': e.code}
        except Exception as e:
            return {'ok': False, 'error': f'нет связи: {e}', '_net': True}


class Agent:
    """Подключение и выполнение поручений. engine — объект с методами sessions(), read(chat, days), send(chat, text), files(days), info()."""

    def __init__(self, engine, store=None, ui=None, sleep=time.sleep):
        self.engine, self.ui, self.sleep = engine, ui, sleep
        self.st = load(); self.store = store or Store(token=self.st.get('token', ''))
        if store is not None and self.st.get('token'): self.store.token = self.st['token']

    def say(self, text):
        log(text)
        if self.ui: self.ui(text)

    # ---------- подключение ----------
    def paired(self): return bool(self.st.get('token'))

    def pair(self, wait=900, step=4):
        inf = self.engine.info()
        k = self.st.get('install_key')   # №560 · поставлен по личной ссылке сотрудника — подключаемся сами, без кода
        if k:
            r = self.store.call('pc_pair_auto', {'key': k, 'name': f'{socket.gethostname()} · {os.environ.get("USERNAME", "")}', 'wx_ver': inf.get('wx_ver', ''), 'app_ver': APP_VER})
            if r.get('token'):
                self.st.pop('install_key', None); self.st['token'] = r['token']; self.st['who'] = r.get('who', ''); save(self.st); self.store.token = r['token']
                self.say('Компьютер подключён: ' + r.get('who', '')); return True
            if not r.get('_net'): self.st.pop('install_key', None); save(self.st)   # ключ устарел — дальше обычным кодом
        r = self.store.call('pc_pair_start', {'name': f'{socket.gethostname()} · {os.environ.get("USERNAME", "")}', 'wx_ver': inf.get('wx_ver', ''), 'app_ver': APP_VER})
        if not r.get('ok'): self.say('Не удалось связаться с магазином: ' + str(r.get('error'))); return False
        self.say(f"Код подключения: {r['code']}\n" + r.get('say', ''))
        t0 = time.time()
        while time.time() - t0 < wait:
            self.sleep(step)
            c = self.store.call('pc_pair_check', {'pair': r['pair'], 'secret': r['secret']})
            if c.get('token'):
                self.st['token'] = c['token']; self.st['who'] = c.get('who', ''); save(self.st); self.store.token = c['token']
                self.say('Компьютер подключён: ' + c.get('who', '')); return True
            if c.get('expired'): break
        self.say('Код устарел — получите новый'); return False

    # ---------- поручения ----------
    def tick(self):
        """Один шаг: спросить поручение и выполнить. Возвращает секунды до следующего опроса."""
        inf = self.engine.info()
        r = self.store.call('pc_poll', {'wx_ver': inf.get('wx_ver', ''), 'backend': inf.get('backend', ''), 'app_ver': APP_VER})
        if r.get('repair'): self.st.pop('token', None); save(self.st); self.say('Компьютер отключён в Филе — подключите заново'); return 5
        if not r.get('ok'): return 60 if r.get('_net') else 30
        j = r.get('job')
        if not j: return int(r.get('wait', 20))
        self.run(j); return 2

    def run(self, j):
        op, a, out = j['op'], j.get('args') or {}, {'job': j['id'], 'ok': True}
        self.say({'read': 'Читаю WeChat…', 'files': 'Забираю файлы из WeChat…', 'send': 'Пишу в WeChat…', 'sessions': 'Смотрю список чатов…'}.get(op, op) + ' Не трогайте мышь минуту.')
        try:
            if op == 'read':
                names = a.get('chats') or [s['name'] for s in self.engine.sessions() if s.get('unread') or s.get('recent')][:20]
                chats = []
                for n in names[:30]:
                    msgs = self.engine.read(n, int(a.get('days', 1)))
                    chats.append({'name': n, 'msgs': msgs}); self.sleep(random.uniform(1.5, 4))
                out['chats'] = chats
                out['files'] = self.upload_files(int(a.get('days', 1)))
            elif op == 'files':
                out['files'] = self.upload_files(int(a.get('days', 7)))
            elif op == 'send':
                out['sent'] = bool(self.engine.send(a['chat'], a['text']))
                if not out['sent']: out['ok'] = False; out['error'] = 'не нашёл чат «' + a['chat'] + '» или не удалось отправить'
            elif op == 'sessions':
                out['sessions'] = [s['name'] for s in self.engine.sessions()]
            else:
                out['ok'] = False; out['error'] = 'неизвестное поручение ' + op
        except Exception as e:
            out['ok'] = False; out['error'] = f'{type(e).__name__}: {e}'[:400]; log('ошибка', repr(e))
        self.store.call('pc_done', out, timeout=180)
        self.say('Готово' if out['ok'] else 'Не получилось: ' + out.get('error', ''))
        return out

    def upload_files(self, days):
        sent = set(self.st.get('sent', [])); n = 0
        for f in self.engine.files(days):
            p = f['path']
            try:
                if os.path.getsize(p) > MAX_FILE or os.path.getsize(p) == 0: continue
                h = hashlib.sha256(open(p, 'rb').read()).hexdigest()
            except OSError: continue
            if h in sent: continue
            r = self.store.call('pc_file', {'name': os.path.basename(p), 'chat': f.get('chat', ''), 'at': f.get('at', ''),
                                            'data': base64.b64encode(open(p, 'rb').read()).decode()}, timeout=180)
            if r.get('ok'): sent.add(h); n += 1
            self.sleep(random.uniform(0.3, 1))
        self.st['sent'] = list(sent)[-5000:]; save(self.st)
        return n
