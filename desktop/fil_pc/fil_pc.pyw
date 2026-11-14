"""«Фил на компьютере» — запускается сам при входе в Windows (ярлык в «Автозагрузке»), живёт в маленьком окошке.
Первый запуск: показывает 6 цифр — скажите Филу «подключи компьютер 123456». Дальше Фил сам даёт поручения: прочитать WeChat,
забрать каталоги, написать поставщику. Окно можно свернуть; закрыть — значит выключить помощника до следующего входа в Windows."""
import base64, ctypes, json, os, sys, threading, time, urllib.request, zipfile, io
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import core
from wx_engine import Engine

def idle_seconds():
    try:
        class LII(ctypes.Structure): _fields_ = [('cbSize', ctypes.c_uint), ('dwTime', ctypes.c_uint)]
        l = LII(); l.cbSize = ctypes.sizeof(LII); ctypes.windll.user32.GetLastInputInfo(ctypes.byref(l))
        return (ctypes.windll.kernel32.GetTickCount() - l.dwTime) / 1000.0
    except Exception: return 999

def self_update():
    """Новая версия программы — с магазина (store/app/pc/version.json + fil_pc.zip)."""
    try:
        v = json.loads(urllib.request.urlopen(core.STORE + '/app/pc/version.json', timeout=20).read())
        if v.get('ver') and v['ver'] != core.APP_VER:
            z = urllib.request.urlopen(core.STORE + '/app/pc/fil_pc.zip', timeout=120).read()
            zipfile.ZipFile(io.BytesIO(z)).extractall(os.path.dirname(os.path.abspath(__file__)))
            core.log('обновился до', v['ver']); os.execv(sys.executable, [sys.executable] + sys.argv)
    except Exception as e: core.log('обновление:', e)

def main():
    import tkinter as tk
    root = tk.Tk(); root.title('Фил на компьютере'); root.geometry('380x170'); root.attributes('-topmost', False)
    try: root.iconbitmap(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'fil.ico'))
    except Exception: pass
    lab = tk.Label(root, text='Запускаюсь…', wraplength=360, justify='left', font=('Segoe UI', 11)); lab.pack(padx=10, pady=10, fill='both', expand=True)
    btn = tk.Button(root, text='Получить новый код', command=lambda: threading.Thread(target=pair, daemon=True).start()); btn.pack(pady=4)
    def ui(t): root.after(0, lambda: lab.config(text=t))
    st = core.Store()
    eng = Engine(lambda png: (st.call('pc_ocr', {'png': base64.b64encode(png).decode()}) or {}).get('text', ''),
                 extra_dirs=core.load().get('file_dirs', []))
    ag = core.Agent(eng, store=st, ui=ui)
    def pair():
        ag.pair()
    def loop():
        if not ag.paired(): pair()
        n = 0
        while True:
            if not ag.paired(): time.sleep(5); continue
            try:
                if n % 360 == 0: self_update()
                n += 1
                # не мешаем человеку: если он работает мышью/клавиатурой — ждём минуту тишины (до 5 минут)
                w = 0
                while idle_seconds() < 20 and w < 300: time.sleep(5); w += 5
                d = ag.tick()
                if d > 5: ui('Подключён · ' + core.load().get('who', '') + '\nЖду поручений Фила. WeChat должен быть открыт.')
                time.sleep(d)
            except Exception as e:
                core.log('цикл:', repr(e)); time.sleep(30)
    threading.Thread(target=loop, daemon=True).start()
    root.mainloop()

if __name__ == '__main__':
    main()
