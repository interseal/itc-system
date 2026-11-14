"""Экран: Экосистема → Навыки (№544): поиск, фильтры, приостановить, изменить, удалить, новый навык."""
import glob, json, os, shutil, sqlite3, subprocess, sys, time, urllib.request
from playwright.sync_api import sync_playwright
ST = '/tmp/kft/store'; OUT = '/tmp/skp_ui'; os.makedirs(OUT, exist_ok=True)
KOS = '/home/claude/itc-system/kosmos'; KT = '/tmp/skp_kos'; shutil.rmtree(KT, ignore_errors=True); os.makedirs(KT)
open(f'{KT}/master.key', 'wb').write(os.urandom(48))
open(f'{KT}/config.php', 'w').write(f"<?php return ['db' => '{KT}/kosmos.sqlite', 'master_key_file' => '{KT}/master.key', 'store_token_file' => '{KT}/none', 'gw_key_file' => '{KT}/gw.key', 'claude_url' => 'http://127.0.0.1:9/v1/messages', 'dev_users' => []];")
def sql(q): return subprocess.run(['mysql', '-uroot', 'itc', '-N', '-e', q], capture_output=True, text=True, check=True).stdout.strip()
sql("DROP TABLE IF EXISTS itc_skill, itc_skill_copy; DELETE FROM itc_secret WHERE k='nvk_copy_at'; REPLACE INTO itc_secret (k,v,who) VALUES ('nvk_moved','1','test')")
srv = subprocess.Popen(['php', '-S', '127.0.0.1:8797', '-t', ST], env=dict(os.environ, PHP_CLI_SERVER_WORKERS='4'), stdout=open(f'{OUT}/php.log', 'w'), stderr=subprocess.STDOUT)
ksrv = subprocess.Popen(['php', '-S', '127.0.0.1:8793', '-t', f'{KOS}/www'], env=dict(os.environ, KOSMOS_CONFIG=f'{KT}/config.php', PHP_CLI_SERVER_WORKERS='4'), stdout=open(f'{OUT}/kos.log', 'w'), stderr=subprocess.STDOUT)
time.sleep(1)
def api(a, d):
    r = urllib.request.Request(f'http://127.0.0.1:8797/api/flow.php?action={a}&t=boss-t', data=json.dumps(d).encode(), method='POST', headers={'Content-Type': 'application/json'})
    try:
        with urllib.request.urlopen(r, timeout=30) as f: return json.loads(f.read())
    except Exception as e: return {'ok': False, 'error': str(e)}
api('nvk_list', {})
open(f'{KT}/gw.key', 'w').write(json.load(open(glob.glob(f'{ST}/uploads/kosmos/*/gw.json')[0]))['gw'] + '\n')
api('nvk_save', {'title': 'КП для гостиницы', 'when': 'просят КП для гостиницы', 'steps': '1) deals — найти сделку; 2) suppliers — фабрики; 3) make_kp'})
con = sqlite3.connect(f'{KT}/kosmos.sqlite')
con.execute("INSERT INTO skills (title, trig, steps, trace, author, who, manual, created_at, updated_at, src, src_hash) VALUES ('Деньги Claude','баланс Claude','1) Billing на platform.claude.com; 2) страница Обновления','','Claude','Claude',1,'2026-11-14','2026-11-14','claude:claude-dengi','x')")
con.commit(); con.close()
ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ✓', n)
    else: bad += 1; print('  ✗', n, str(x)[:400])
try:
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        pg = b.new_context(viewport={'width': 412, 'height': 900}).new_page(); errs = []
        pg.on('pageerror', lambda e: errs.append(str(e)))
        pg.goto('http://127.0.0.1:8797/eco/upd.html?t=boss-t'); pg.wait_for_selector('text=Навыки «Фила», «Космоса» и Claude')
        check('на странице «Обновления» есть кнопка «Навыки»', pg.is_visible('a[href="/eco/skills.html"]'))
        pg.click('a[href="/eco/skills.html"]'); pg.wait_for_selector('.sk')
        t = pg.inner_text('#list')
        check('провалился — видны все навыки: свой и от Claude', 'КП для гостиницы' in t and 'Деньги Claude' in t and 'от Claude' in t, t)
        check('фильтры со счётом', 'Навыки Claude · 1' in pg.inner_text('#filt') and 'История Claude · 0' in pg.inner_text('#filt') and 'Все · 2' in pg.inner_text('#filt'), pg.inner_text('#filt'))
        pg.fill('#q', 'billing'); check('поиск по тексту шагов', pg.locator('.sk').count() == 1 and 'Деньги Claude' in pg.inner_text('#list'))
        pg.fill('#q', '')
        pg.click('text=Деньги Claude'); pg.wait_for_selector('text=Приостановить')
        check('открылся навык: когда, шаги, пояснение про навыки Claude', 'Billing' in pg.inner_text('#list') and 'не перезапишет' in pg.inner_text('#list'))
        pg.click('button:has-text("Приостановить")'); pg.wait_for_selector('.tag.p')
        check('приостановлен — отметка и кнопка «Включить снова»', 'приостановлен' in pg.inner_text('#list') and pg.is_visible('text=Включить снова'))
        pick = api('nvk_list', {'q': 'баланс Claude'})
        check('помощникам приостановленный навык не подбирается', pick.get('ok') and not any(x['title'] == 'Деньги Claude' for x in pick['items']), pick)
        cp = sql("SELECT paused FROM itc_skill_copy WHERE title='Деньги Claude'")
        check('и в копии магазина он приостановлен', cp == '1', cp)
        pg.click('text=Приостановлены'); check('фильтр «Приостановлены»', pg.locator('.sk').count() == 1)
        pg.click('text=Все ·')
        pg.click('text=Изменить / дополнить'); pg.fill('#fS', '1) Billing на platform.claude.com; 2) страница Обновления; 3) дополнил руководитель')
        pg.click('#form >> text=Сохранить'); pg.wait_for_selector('text=дополнил руководитель')
        check('дополнил шаги — сохранилось', 'дополнил руководитель' in pg.inner_text('#list'))
        pg.click('text=+ Новый навык'); pg.fill('#fT', 'Отгрузка в Бишкек'); pg.fill('#fW', 'когда отгрузка'); pg.fill('#fS', '1) проверить оплату; 2) заказать машину')
        pg.click('#form >> text=Сохранить'); pg.wait_for_selector('text=Отгрузка в Бишкек')
        check('новый навык добавлен', 'Все · 3' in pg.inner_text('#filt'))
        pg.click('.sk:has-text("КП для гостиницы") .sk-h'); pg.click('.sk:has-text("КП для гостиницы") button:has-text("Удалить")'); pg.wait_for_selector('button:has-text("Точно удалить?")')
        pg.click('button:has-text("Точно удалить?")'); pg.wait_for_function("document.getElementById('list').innerText.indexOf('КП для гостиницы') < 0")
        check('удаление — со вторым нажатием', 'Все · 2' in pg.inner_text('#filt'))
        pg.screenshot(path=f'{OUT}/skills.png', full_page=True)
        check('ошибок на странице нет', not errs, errs)
        b.close()
finally:
    srv.terminate(); ksrv.terminate()
perr = [l for f in ('php.log', 'kos.log') for l in open(f'{OUT}/{f}').read().split('\n') if any(w in l for w in ('Fatal', 'Warning', 'Deprecated'))]
if perr: print('Ошибки PHP:\n' + '\n'.join(perr[:10]))
print(f'\nИтог: {ok} прошло, {bad} не прошло'); sys.exit(1 if bad else 0)
