"""«Фил на компьютере» — руки в WeChat (Windows). Три способа, выбирается сам:
  uia3 — WeChat 3.9.x: библиотека wxauto (окно читается напрямую, быстро и точно);
  uia4 — WeChat 4.0.5: библиотека wxauto4;
  ocr  — любая версия: снимки окна → распознавание текста в магазине (Яндекс), клики и вставка текста.
Файлы (каталоги, прайсы, фото) — для всех версий из папки, куда WeChat сам сохраняет полученные файлы.
Если быстрый способ сломался (WeChat обновился) — сам переходит на «по экрану»."""
import datetime as dt, glob, io, os, random, re, time

DAY_RE = re.compile(r'(?:(\d{4})年)?(\d{1,2})月(\d{1,2})日')
WEEK = {'一': 0, '二': 1, '三': 2, '四': 3, '五': 4, '六': 5, '日': 6, '天': 6}


def pause(a=1.2, b=3.0): time.sleep(random.uniform(a, b))   # «по-человечески» — не быстрее человека


def wx_time(s, now=None):
    """'14:25' / '昨天 18:20' / '星期二 09:10' / '11月3日 10:00' / '2025年11月3日 10:00' / 'Yesterday 18:20' → 'YYYY-MM-DD HH:MM' или ''"""
    now = now or dt.datetime.now(); s = (s or '').strip()
    m = re.search(r'(\d{1,2}):(\d{2})', s); hh, mm = (int(m.group(1)), int(m.group(2))) if m else (12, 0)
    d = now.date()
    if '昨天' in s or 'Yesterday' in s: d = d - dt.timedelta(days=1)
    elif '前天' in s: d = d - dt.timedelta(days=2)
    elif (w := re.search(r'(?:星期|周)([一二三四五六日天])', s)):
        back = (now.weekday() - WEEK[w.group(1)]) % 7 or 7; d = d - dt.timedelta(days=back)
    elif (x := DAY_RE.search(s)):
        y = int(x.group(1)) if x.group(1) else now.year
        try: d = dt.date(y, int(x.group(2)), int(x.group(3)))
        except ValueError: return ''
        if d > now.date(): d = d.replace(year=d.year - 1)
    elif (x := re.search(r'(\d{4})[/-](\d{1,2})[/-](\d{1,2})', s)):
        d = dt.date(int(x.group(1)), int(x.group(2)), int(x.group(3)))
    elif not m: return ''
    return f'{d.isoformat()} {hh:02d}:{mm:02d}'


def file_dirs(extra=()):
    """Папки, куда WeChat кладёт полученные файлы (3.x и 4.x) + свои, если указаны в настройках."""
    docs = [os.path.join(os.path.expanduser('~'), 'Documents')]
    try:   # «Документы» могут быть перенесены (D:\…)
        import winreg
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, r'Software\Microsoft\Windows\CurrentVersion\Explorer\User Shell Folders') as k:
            docs.append(os.path.expandvars(winreg.QueryValueEx(k, 'Personal')[0]))
    except Exception: pass
    out = []
    for d in docs:
        out += glob.glob(os.path.join(d, 'WeChat Files', '*', 'FileStorage', 'File', '*'))       # 3.x: …\File\2026-11
        out += glob.glob(os.path.join(d, 'WeChat Files', '*', 'FileStorage', 'Video', '*'))
        out += glob.glob(os.path.join(d, 'xwechat_files', '*', 'msg', 'file', '*'))              # 4.x: …\msg\file\2026-11
        out += glob.glob(os.path.join(d, 'xwechat_files', '*', 'msg', 'video', '*'))
    out += [e for e in extra if os.path.isdir(e)]
    return sorted(set(out))


def recent_files(days, extra=()):
    lim = time.time() - days * 86400; res = []
    for d in file_dirs(extra):
        for p in glob.glob(os.path.join(d, '**', '*'), recursive=True):
            try:
                if os.path.isfile(p) and os.path.getmtime(p) >= lim and not p.lower().endswith(('.dat', '.tmp', '.db')):
                    res.append({'path': p, 'chat': '', 'at': time.strftime('%Y-%m-%d %H:%M', time.localtime(os.path.getmtime(p)))})
            except OSError: pass
    return res


def wx_version():
    """Версия WeChat из файла программы (запущенной): '3.9.12.17', '4.0.5.26', … или ''."""
    try:
        import psutil, win32api
        for p in psutil.process_iter(['name', 'exe']):
            if (p.info['name'] or '').lower() in ('wechat.exe', 'weixin.exe') and p.info['exe']:
                i = win32api.GetFileVersionInfo(p.info['exe'], '\\')
                return f"{i['FileVersionMS'] >> 16}.{i['FileVersionMS'] & 0xFFFF}.{i['FileVersionLS'] >> 16}.{i['FileVersionLS'] & 0xFFFF}"
    except Exception: pass
    return ''


class UIA:
    """WeChat 3.9 (wxauto) или 4.0.5 (wxauto4) — окно читается напрямую."""

    def __init__(self, v4):
        if v4: from wxauto4 import WeChat
        else: from wxauto import WeChat
        self.wx, self.name = WeChat(), 'uia4' if v4 else 'uia3'

    def sessions(self):
        f = getattr(self.wx, 'GetSessionList', None) or getattr(self.wx, 'GetSession', None)
        s = f() if f else {}
        if isinstance(s, dict): return [{'name': k, 'unread': int(v or 0)} for k, v in s.items()]
        out = []
        for x in s or []:
            n = getattr(x, 'name', None) or (x if isinstance(x, str) else str(x))
            out.append({'name': n, 'unread': int(getattr(x, 'new_count', 0) or getattr(x, 'unread', 0) or 0)})
        return out

    def read(self, chat, days):
        self.wx.ChatWith(chat); pause()
        if days > 1 and hasattr(self.wx, 'LoadMoreMessage'):
            for _ in range(min(10, days * 2)):
                try: self.wx.LoadMoreMessage(); pause(0.8, 1.6)
                except Exception: break
        msgs, cur, lim = [], '', (dt.datetime.now() - dt.timedelta(days=days)).strftime('%Y-%m-%d %H:%M')
        for m in self.wx.GetAllMessage() or []:
            kind = (getattr(m, 'type', '') or getattr(m, 'attr', '') or '').lower()
            content = str(getattr(m, 'content', '') or '')
            if kind == 'time': cur = wx_time(content) or cur; continue
            if kind in ('sys', 'system', 'recall'): continue
            at = cur or dt.datetime.now().strftime('%Y-%m-%d %H:%M')
            if at < lim: continue
            mt = 'text'
            if re.match(r'^\[(文件|File)\]', content) or getattr(m, 'file', None): mt = 'file'
            elif re.match(r'^\[(图片|Photo|Image)\]', content): mt = 'image'
            elif re.match(r'^\[(语音|Voice)\]', content): mt = 'voice'
            elif re.match(r'^\[(视频|Video)\]', content): mt = 'video'
            msgs.append({'dir': 'out' if kind in ('self', 'mine') else 'in', 'who': str(getattr(m, 'sender', '') or chat), 'text': content, 'at': at + ':00', 'type': mt})
        return msgs

    def send(self, chat, text):
        r = self.wx.SendMsg(text, who=chat); pause()
        return r is None or bool(r) or getattr(r, 'is_success', True)


class OCR:
    """Любая версия WeChat: окно → снимок → текст (распознаёт магазин). Ищет чат через поиск WeChat (Ctrl+F)."""
    TITLES = ('微信', 'WeChat', 'Weixin')

    def __init__(self, ocr_fn):
        import pyautogui, pyperclip, pygetwindow   # noqa
        self.pg, self.clip, self.gw, self.ocr_fn, self.name = pyautogui, pyperclip, pygetwindow, ocr_fn, 'ocr'
        self.pg.FAILSAFE = True; self.pg.PAUSE = 0.15

    def win(self):
        for t in self.TITLES:
            for w in self.gw.getWindowsWithTitle(t):
                if w.title.strip() in self.TITLES and w.width > 400:
                    try:
                        if w.isMinimized: w.restore()
                        w.activate()
                    except Exception: pass
                    pause(0.6, 1.2); return w
        raise RuntimeError('окно WeChat не найдено — откройте WeChat и войдите')

    def shot(self, box):
        im = self.pg.screenshot(region=box); b = io.BytesIO(); im.save(b, 'PNG'); return b.getvalue()

    def open_chat(self, chat):
        w = self.win()
        self.pg.hotkey('ctrl', 'f'); pause(0.5, 1)
        self.clip.copy(chat); self.pg.hotkey('ctrl', 'v'); pause(1.2, 2.2); self.pg.press('enter'); pause(1, 2)
        return w

    def read(self, chat, days):
        w = self.open_chat(chat)
        box = (w.left + int(w.width * 0.32), w.top + 60, int(w.width * 0.66), int(w.height * 0.62))   # правая часть: переписка
        texts = []
        for i in range(min(4, 1 + days)):
            texts.insert(0, self.ocr_fn(self.shot(box)) or '')
            self.pg.moveTo(box[0] + box[2] // 2, box[1] + box[3] // 2); self.pg.scroll(800); pause(0.8, 1.5)
        t = '\n'.join(x for x in texts if x.strip())
        return [{'dir': 'in', 'who': chat, 'text': '[снимок экрана WeChat]\n' + t, 'at': dt.datetime.now().strftime('%Y-%m-%d %H:%M:%S'), 'type': 'other'}] if t.strip() else []

    def sessions(self):
        w = self.win(); box = (w.left + 60, w.top + 60, int(w.width * 0.26), w.height - 70)
        lines = [x.strip() for x in (self.ocr_fn(self.shot(box)) or '').splitlines()]
        names = [x for x in lines if x and not re.match(r'^[\d:/ 昨天星期周\-.]+$', x) and len(x) <= 40]
        return [{'name': n, 'recent': True} for n in names[:15]]

    def send(self, chat, text):
        w = self.open_chat(chat)
        self.pg.click(w.left + int(w.width * 0.65), w.top + int(w.height * 0.88)); pause(0.4, 0.8)   # поле ввода
        self.clip.copy(text); self.pg.hotkey('ctrl', 'v'); pause(0.8, 1.5); self.pg.press('enter'); pause(1, 2)
        return True


class Engine:
    """Выбирает способ по версии WeChat; сломался быстрый — переходит на «по экрану»."""

    def __init__(self, ocr_fn, extra_dirs=()):
        self.ocr_fn, self.extra, self.impl, self.ver = ocr_fn, extra_dirs, None, ''

    def _get(self):
        v = wx_version()
        if self.impl is None or v != self.ver:
            self.ver, self.impl = v, None
            for v4 in ([False] if v.startswith('3.9') else [True] if v.startswith('4.0.5') else []):
                try: self.impl = UIA(v4); break
                except Exception: self.impl = None
            if self.impl is None: self.impl = OCR(self.ocr_fn)
        return self.impl

    def _do(self, name, *a):
        impl = self._get()
        try: return getattr(impl, name)(*a)
        except Exception:
            if impl.name == 'ocr': raise
            self.impl = OCR(self.ocr_fn); return getattr(self.impl, name)(*a)

    def info(self):
        try: b = self._get().name
        except Exception: b = 'нет'
        return {'wx_ver': self.ver or wx_version(), 'backend': b}

    def sessions(self): return self._do('sessions')
    def read(self, chat, days): return self._do('read', chat, days)
    def send(self, chat, text): return self._do('send', chat, text)
    def files(self, days): return recent_files(days, self.extra)
