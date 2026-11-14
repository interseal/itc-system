"""Экран: лента поставщика /eco/sup.html (№555). Нужно: MariaDB и данные, которые оставил flow_e2e.py (поставщик s1)."""
import os, subprocess, time
from playwright.sync_api import sync_playwright
ST = '/tmp/kft/store'; OUT = '/tmp/upd_ui'; os.makedirs(OUT, exist_ok=True); PORT = 8797; URL = f'http://127.0.0.1:{PORT}'
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', ST], env=dict(os.environ, PHP_CLI_SERVER_WORKERS='4'), stdout=open(f'{OUT}/php_sup.log', 'w'), stderr=subprocess.STDOUT)
time.sleep(1); ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ✓', n)
    else: bad += 1; print('  ✗', n, str(x)[:500])
try:
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        pg = b.new_context(viewport={'width': 412, 'height': 900}, service_workers='block').new_page(); errs = []
        pg.on('pageerror', lambda e: errs.append(str(e)))
        pg.goto(f'{URL}/eco/sup.html?id=s1&t=boss-t'); pg.wait_for_selector('.it', timeout=10000)
        t = pg.inner_text('body')
        check('заголовок — поставщик, сводка, переписка и запись', 'SolarOne' in pg.inner_text('#ttl') and 'Записей:' in t and 'Новая цена 0,58' in t and '0,62 $/Вт' in t, t[:600])
        pg.click('.chip >> text=цены'); pg.wait_for_timeout(800)
        check('фильтр «цены» — только записи о ценах', 'Новая цена 0,58' not in pg.inner_text('#list') and '0,62' in pg.inner_text('#list'), pg.inner_text('#list')[:300])
        pg.screenshot(path=f'{OUT}/sup_1.png', full_page=True)
        check('ошибок JavaScript нет', not errs, errs)
        b.close()
finally:
    srv.terminate()
print(f'\nИтог: {ok} прошло, {bad} не прошло')
