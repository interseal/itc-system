"""Экран: страница «Обновления» — строка «Сингапур» рядом с Гонконгом и деньги Claude (№541).
Магазин — /tmp/kft/store (php -S), состояние сервера — отчёт агента по базе /tmp/kft/kos (её оставил flow_e2e.py)."""
import glob, json, os, subprocess, sys, time, urllib.request
from playwright.sync_api import sync_playwright
ST = '/tmp/kft/store'; OUT = '/tmp/upd_ui'; os.makedirs(OUT, exist_ok=True)
KOS = '/home/claude/itc-system/kosmos'; PORT = 8798; URL = f'http://127.0.0.1:{PORT}'
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', ST], env=dict(os.environ, PHP_CLI_SERVER_WORKERS='4'), stdout=open(f'{OUT}/php.log', 'w'), stderr=subprocess.STDOUT)
time.sleep(1)
def post(path, body, headers=None):
    r = urllib.request.Request(URL + path, data=json.dumps(body).encode(), method='POST', headers={'Content-Type': 'application/json', **(headers or {})})
    with urllib.request.urlopen(r, timeout=30) as f: return json.loads(f.read())
def report():   # как агент сервера: свежий отчёт с расходом по ключам
    r = subprocess.run(['php', f'{KOS}/bin/agent.php', 'dbinfo'], env=dict(os.environ, KOSMOS_CONFIG='/tmp/kft/kos/config.php'), capture_output=True, text=True)
    body = {'ver': '0.3.3', 'https': True, 'dns_ok': True, 'worker': True, 'key': True, 'disk_free_gb': 31.4, 'backup': '20261114', 'up': '3 дн 04:10', 'load': 0.21,
            'git': {'ok': True, 'hash': 'e14a275', 'at': '2026-11-14 00:40:00', 'err': ''}, 'money': json.loads(r.stdout)['money']}
    return post('/api/kosmos.php?a=report', body, {'X-Kosmos-Token': 'srv' * 22})
ok = bad = 0
def check(n, c, x=''):
    global ok, bad
    if c: ok += 1; print('  ✓', n)
    else: bad += 1; print('  ✗', n, str(x)[:500])
try:
    report()
    gid = post('/api/kosmos.php?a=status&t=boss-t', {})['detail']['money'][0]['g']
    post('/api/kosmos.php?a=claude_bal&t=boss-t', {'g': gid, 'amount': '36.27'})   # известная отметка остатка
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        ctx = b.new_context(viewport={'width': 412, 'height': 1000}, service_workers='block'); pg = ctx.new_page(); errs = []
        pg.on('pageerror', lambda e: errs.append(str(e)))
        pg.goto(f'{URL}/eco/upd.html?t=boss-t'); pg.wait_for_selector('#sgBox .row', timeout=10000)
        pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('проверяю') < 0", timeout=10000)
        t = pg.inner_text('#cState')
        check('верх страницы как раньше: что установлено, хостинг', 'Сейчас установлено' in t and 'Свободно на хостинге' in t, t[:300])
        s = pg.inner_text('#sgBox')
        check('Сингапур: на связи, версия сервера', 'Сингапур («Космос»): на связи · сервер 0.3.3' in s, s)
        check('Сингапур: диск, нагрузка, копия данных, копия кода с GitHub, без перезагрузки',
              all(w in s for w in ['свободно 31,4 ГБ', 'нагрузка 0,21', 'копия данных 14.11', 'копия кода с GitHub e14a275 (проверена 14.11 00:40)', 'без перезагрузки 3 дн 04:10']), s)
        check('деньги Claude: заголовок, ключи одного счёта, остаток', 'Claude — деньги на счёте' in s and '«Аккаунт 1»' in s and '«Запасной»' in s and '≈ $36,27' in s and 'осталось' in s, s)
        check('видно, когда указан остаток и сколько потрачено с тех пор', 'На счёте было $36,27' in s and 'с тех пор потрачено $0,00' in s, s)
        pg.locator('#sgBox').screenshot(path=f'{OUT}/sg_1.png')
        pg.click('text=Пополнили счёт? Указать новый остаток'); pg.wait_for_selector('#sgBox input[type=text]')
        check('подсказка, где взять сумму', 'Settings → Billing' in pg.inner_text('#sgBox') and 'Credit balance' in pg.inner_text('#sgBox'))
        pg.fill('#sgBox input[type=text]', 'abc'); pg.click('text=Сохранить остаток'); pg.wait_for_selector('#sgBox .err')
        check('не сумма — ошибка прямо под полем', 'долларах' in pg.inner_text('#sgBox'), pg.inner_text('#sgBox'))
        pg.fill('#sgBox input[type=text]', '50'); pg.click('text=Сохранить остаток')
        pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('≈ $50,00') >= 0", timeout=8000)
        check('сохранили 50 — сразу видно «≈ $50,00»', '≈ $50,00' in pg.inner_text('#sgBox'))
        pg.reload(); pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('≈ $50,00') >= 0", timeout=10000)
        check('после перезагрузки страницы остаток тот же', '≈ $50,00' in pg.inner_text('#sgBox'))
        pg.screenshot(path=f'{OUT}/sg_2.png', full_page=True)
        pg.route('**/api/kosmos.php?a=upd_ready*', lambda r: r.fulfill(status=200, content_type='application/json', body=json.dumps({'ok': True, 'err': '', 'items': [{'n': 99999, 'needs': 0, 'title': 'Проба с сервера', 'what': ['пункт один'], 'size': 1000, 'at': 1}]})))
        pg.reload(); pg.wait_for_selector('text=Установить №99999')
        check('№545: обновление, собранное сервером, — «Готово к установке» и одна кнопка', 'Готово к установке: обновление №99999' in pg.inner_text('#cUp') and 'пункт один' in pg.inner_text('#cUp') and pg.evaluate("document.getElementById('cUp').getBoundingClientRect().top < document.getElementById('cState').getBoundingClientRect().top"))
        pg.unroute('**/api/kosmos.php?a=upd_ready*')
        check('ошибок на странице нет', not errs, errs)
        # сервер старый (расход не присылает) и сбой ответа — остальная страница работает
        def fake(route, body): route.fulfill(status=200, content_type='application/json', body=json.dumps(body))
        pg.route('**/api/kosmos.php?a=status*', lambda r: fake(r, {'ok': True, 'online': True, 'ver': '0.3.2', 'detail': {'at_ts': int(time.time()), 'now': int(time.time()), 'https': True, 'dns_ok': True,
                 'worker': True, 'key': True, 'disk_free_gb': 30, 'backup': '', 'host': 'kosmos.itcpartner.store', 'load': None, 'up': '', 'git': None, 'money': None}}))
        pg.reload(); pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('0.3.2') >= 0", timeout=10000)
        s = pg.inner_text('#sgBox')
        check('старый сервер: «расход начнёт присылать с 0.3.3»', 'с версии 0.3.3' in s and 'копий данных пока нет' in s, s)
        pg.unroute('**/api/kosmos.php?a=status*')
        pg.route('**/api/kosmos.php?a=status*', lambda r: r.fulfill(status=500, content_type='text/html', body='<b>Fatal error</b>'))
        pg.reload(); pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('не удалось') >= 0", timeout=10000)
        check('сбой ответа — понятная строка, Гонконг и остальная страница на месте', 'Сингапур: не удалось узнать состояние' in pg.inner_text('#sgBox')
              and 'Сейчас установлено' in pg.inner_text('#cState') and pg.is_visible('#cUp'), pg.inner_text('#cState')[:300])
        pg.unroute('**/api/kosmos.php?a=status*')
        pg.route('**/api/kosmos.php?a=status*', lambda r: fake(r, {'ok': True, 'online': False, 'ver': '0.3.3', 'detail': {'at_ts': int(time.time()) - 900, 'now': int(time.time()), 'https': True,
                 'dns_ok': True, 'worker': True, 'key': True, 'disk_free_gb': 30, 'backup': '20261113', 'host': 'kosmos.itcpartner.store', 'load': 0.1, 'up': '', 'git': {'ok': False, 'hash': '', 'at': '', 'err': 'нет доступа к GitHub'},
                 'bw': {'set': True, 'ok': False, 'at': '', 'err': 'сейф не принял ключ — вставьте новый токен', 'n': 0, 'project': ''}, 'money': [{'g': 'k1', 'keys': [{'id': 1, 'name': 'Киргизия', 'last4': 'AB12', 'active': True, 'out': True, 'st': ''}], 'active': True, 'out': True, 'today': 0, 'week': 7.7, 'month': 20, 'per_day': 1.1, 'span': 7,
                            'mark': {'amount': 10, 'at': '13.11.2026 10:00', 'spent': 8.9, 'left': 1.1, 'days': 1}}]}}))
        pg.reload(); pg.wait_for_function("document.getElementById('sgBox').innerText.indexOf('нет связи') >= 0", timeout=10000)
        s = pg.inner_text('#sgBox'); html = pg.inner_html('#sgBox')
        check('нет связи 15 мин — красная строка; GitHub — причина', 'Сингапур («Космос»): нет связи 15 мин' in s and 'GitHub: нет доступа к GitHub' in s and 'row bad' in html, s)
        check('№542: ошибка сейфа Bitwarden — красной строкой', 'Сейф Bitwarden: сейф не принял ключ' in s, s)
        check('денег мало и «закончились» — красным и с подсказкой', 'пора пополнить' in s and 'закончились деньги' in s and 'class="bad"' in html and 'хватит примерно на 1 дн.' in s, s)
        pg.locator('#sgBox').screenshot(path=f'{OUT}/sg_3.png')
        check('и здесь ошибок на странице нет', not errs, errs)
        b.close()
finally:
    srv.terminate()
log = open(f'{OUT}/php.log').read()
perr = [l for l in log.split('\n') if any(w in l for w in ('Fatal', 'Warning', 'Deprecated', 'Parse error'))]
if perr: print('Ошибки PHP:\n' + '\n'.join(perr[:10]))
print(f'\nИтог: {ok} прошло, {bad} не прошло')
sys.exit(1 if bad else 0)
