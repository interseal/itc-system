"""Экран: страница «Обновления» — кто подготовил обновление и решение руководителя по правкам разработчиков (№553).
Нужно: запущенный MariaDB и то, что оставил flow_e2e.py (/tmp/kft/store, /tmp/kft/kos)."""
import json, os, sqlite3, subprocess, time
from playwright.sync_api import sync_playwright
ST = '/tmp/kft/store'; OUT = '/tmp/upd_ui'; os.makedirs(OUT, exist_ok=True)
KOS = '/home/claude/itc-system/kosmos'; PORT = 8799; URL = f'http://127.0.0.1:{PORT}'
env = dict(os.environ, PHP_CLI_SERVER_WORKERS='4')
procs = [subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', ST], env=env, stdout=open(f'{OUT}/php_dev.log', 'w'), stderr=subprocess.STDOUT),
         subprocess.Popen(['php', '-S', '127.0.0.1:8793', '-t', f'{KOS}/www'], env=dict(env, KOSMOS_CONFIG='/tmp/kft/kos/config.php'), stdout=open(f'{OUT}/kos_dev.log', 'w'), stderr=subprocess.STDOUT)]
time.sleep(1.2)
db = sqlite3.connect('/tmp/kft/kos/kosmos.sqlite')
db.execute("DELETE FROM dev_tasks WHERE status IN ('needs_ok','approved','rejected')")
now = time.strftime('%Y-%m-%d %H:%M:%S')
for txt, eng in [('Поменяй минимальную версию Android в настройках сборки', 'deepseek'), ('Обнови плагин сборки', 'claude')]:
    db.execute("INSERT INTO dev_tasks (text, model, status, phone, created_at, result, error, release_n, engine, branch) VALUES (?,?,?,?,?,?,?,?,?,?)",
               (txt, 'opus', 'needs_ok', 'магазин', now, 'Изменил android/ITCApp/app/build.gradle', 'Затронуты настройки сборки (android/ITCApp/app/build.gradle). Нажмите «Одобрить»', 9555, eng, 'fil/1'))
db.commit(); ids = [r[0] for r in db.execute("SELECT id FROM dev_tasks WHERE status='needs_ok' ORDER BY id")]; db.close()
ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ✓', n)
    else: bad += 1; print('  ✗', n, str(x)[:600])
try:
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        ctx = b.new_context(viewport={'width': 412, 'height': 1000}, service_workers='block'); pg = ctx.new_page(); errs = []
        pg.on('pageerror', lambda e: errs.append(str(e)))
        pg.goto(f'{URL}/eco/upd.html?t=boss-t'); pg.wait_for_selector('#rdBox button', timeout=15000)
        t = pg.inner_text('#rdBox')
        check('правки Фила ждут решения прямо здесь, с пометкой «проверьте внимательнее»', f'Ждёт вашего решения: поручение №{ids[0]}' in t and 'Подготовил: Фил (DeepSeek) — проверьте внимательнее' in t, t)
        check('правки «Космоса» — со своей пометкой', 'Подготовил: «Космос» (Claude на сервере)' in t, t)
        check('видно поручение и что сделано', 'Поменяй минимальную версию Android' in t and 'Изменил android/ITCApp/app/build.gradle' in t, t)
        check('кнопки «Одобрить и выпустить» и «Отклонить»', pg.locator('text=Одобрить и выпустить').count() == 2 and pg.locator('#rdBox >> text=Отклонить').count() == 2)
        check('у готового обновления — кто подготовил', 'Готово к установке' not in t or 'Подготовил:' in t, t)
        pg.screenshot(path=f'{OUT}/dev_1.png', full_page=True)
        pg.once('dialog', lambda d: d.accept())
        pg.locator('text=Одобрить и выпустить').first.click(); time.sleep(1.5)
        db = sqlite3.connect('/tmp/kft/kos/kosmos.sqlite'); st = dict(db.execute('SELECT id, status FROM dev_tasks WHERE id IN (%s)' % ','.join(map(str, ids))).fetchall()); db.close()
        check('одобрил — на сервере «одобрено», карточка ушла', st[ids[0]] == 'approved' and f'поручение №{ids[0]}' not in pg.inner_text('#rdBox'), (st, pg.inner_text('#rdBox')[:300]))
        pg.on('dialog', lambda d: d.accept())
        pg.locator('#rdBox >> text=Отклонить').first.click(); time.sleep(1.5)
        db = sqlite3.connect('/tmp/kft/kos/kosmos.sqlite'); st = dict(db.execute('SELECT id, status FROM dev_tasks WHERE id IN (%s)' % ','.join(map(str, ids))).fetchall()); db.close()
        check('отклонил с подтверждением — «отклонено»', st[ids[1]] == 'rejected', st)
        pg.screenshot(path=f'{OUT}/dev_2.png', full_page=True)
        ctx2 = b.new_context(viewport={'width': 412, 'height': 900}, service_workers='block'); p2 = ctx2.new_page()
        r = p2.request.get(f'{URL}/api/kosmos.php?a=dev_pending&t=mgr-t'); check('сотрудник список не видит', r.status == 403, r.status)
        r = p2.request.post(f'{URL}/api/kosmos.php?a=dev_decide&t=mgr-t', data=json.dumps({'id': ids[0], 'ok': True})); check('сотрудник одобрить не может', r.status == 403, r.status)
        check('ошибок JavaScript нет', not errs, errs)
        b.close()
finally:
    for pr in procs: pr.terminate()
print(f'\nИтог: {ok} прошло, {bad} не прошло')
