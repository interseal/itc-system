#!/usr/bin/env python3
"""№597 · экран «Парикмахерские ИТК»: салон заводит себя, мастера, услугу с вариантами, публикуется;
клиент находит рядом, записывается; салон видит запись в расписании. Тестовый магазин /tmp/kft/store."""
import os, shutil, subprocess, sys, time
from playwright.sync_api import sync_playwright

R = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
O = C = None
ST = '/tmp/kft/store'; PORT = 8797; OK = 0; BAD = 0
shutil.copy(f'{R}/store/api/svc.php', f'{ST}/api/svc.php')
if os.path.exists(f'{ST}/api/svc_pay_provider.php'): os.remove(f'{ST}/api/svc_pay_provider.php')
shutil.rmtree(f'{ST}/apps/salon', ignore_errors=True); shutil.copytree(f'{R}/store/apps/salon', f'{ST}/apps/salon')
subprocess.run(['mysql', '-uitc', '-pitcpass', 'itc', '-e', 'DROP TABLE IF EXISTS svc_biz, svc_staff, svc_service, svc_booking, svc_pay'], capture_output=True)
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', ST], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(0.8)
U = f'http://127.0.0.1:{PORT}/apps/salon/'


def N(x): return str(x).replace('\xa0', ' ').replace('\u202f', ' ')


def check(name, cond, extra=''):
    global OK, BAD
    if cond: OK += 1; print('  ✓', name)
    else: BAD += 1; print('  ✗', name, str(extra)[:300])


def N_o(sel): return N(O.inner_text(sel))
def N_c(sel): return N(C.inner_text(sel))


def page(b, tok):
    c = b.new_context(viewport={'width': 400, 'height': 860}, geolocation={'latitude': 42.8746, 'longitude': 74.5698}, permissions=['geolocation'], timezone_id='Asia/Bishkek')
    p = c.new_page(); errs = []; p.on('pageerror', lambda e: errs.append(str(e)))
    p.route('https://unpkg.com/**', lambda r: r.abort()); p.route('https://*.tile.openstreetmap.org/**', lambda r: r.abort())
    p.on('dialog', lambda d: d.accept())
    p.goto(U + '?u=' + tok); p.wait_for_timeout(900); return p, errs


try:
    global_ = None
    with sync_playwright() as pw:
        b = pw.chromium.launch()
        print('1. Салон заводит себя')
        o, oerr = page(b, 'mgr-t'); O = o
        o.click('nav >> text=Мой салон'); o.wait_for_timeout(500)
        check('пустой кабинет предлагает добавить салон и честно пишет про 5%', 'Добавить салон' in N_o('#M') and '5%' in N_o('#M'), N_o('#M')[:200])
        o.click('text=Добавить салон'); o.wait_for_timeout(300)
        o.fill('#pn', 'Барбершоп «Борода»'); o.fill('#pph', '+996 555 123456'); o.click('#plt >> text=ИП'); o.fill('#pln', 'ИП Иванов'); o.fill('#pinn', '123456789012')
        o.fill('#pad', 'ул. Ленина, 1'); o.fill('#pci', 'Бишкек'); o.fill('#pcu', 'KGS'); o.click('#phere'); o.wait_for_timeout(500)
        check('«Я сейчас здесь» ставит точку', 'Точка стоит' in N_o('#pll'), N_o('#pll'))
        for i in range(1, 8): o.fill(f'#ho{i}', '00:00'); o.fill(f'#hc{i}', '23:59'); o.check(f'#hw{i}')
        o.select_option('#ppm', 'percent'); o.fill('#ppv', '20'); o.fill('#ppc', '2')
        o.click('#pgo'); o.wait_for_timeout(900)
        check('сохранён и сразу ведёт к мастерам', '+ Мастер' in N_o('#M'), N_o('#M')[:200])
        o.click('text=+ Мастер'); o.fill('#tn', 'Азамат'); o.fill('#tt', 'барбер'); o.click('#tgo'); o.wait_for_timeout(700)
        o.click('.chip >> text=Услуги'); o.wait_for_timeout(400); o.click('text=+ Услуга')
        o.fill('#sn', 'Мужская стрижка'); o.fill('#sd', '60'); o.fill('#sp', '1000')
        o.fill('.vr .vn', 'Короткие'); o.fill('.vr .vp', '1000'); o.click('#vadd'); o.locator('.vr .vn').nth(1).fill('Длинные'); o.locator('.vr .vp').nth(1).fill('1500')
        o.click('#sgo'); o.wait_for_timeout(700)
        check('услуга с двумя вариантами цены', 'Короткие 1 000 KGS' in N_o('#M') and 'Длинные 1 500 KGS' in N_o('#M'), N_o('#M')[:300])
        o.click('text=Показывать клиентам'); o.wait_for_timeout(800)
        check('опубликован — «Скрыть» вместо «Показывать»', o.locator('text=Скрыть').count() == 1, N_o('#M')[:300])

        print('2. Клиент рядом')
        c, cerr = page(b, 'wh-t'); C = c; c.wait_for_timeout(900)
        t = N_c('#nl')
        check('видит салон рядом со «светофором», расстоянием и ценой «от»', 'Барбершоп' in t and 'свободно' in t and 'от 1 000 KGS' in t and ' м' in t, t)
        check('без карты (библиотека не загрузилась) — честная подпись, список работает', 'Карта не загрузилась' in N_c('#map'), N_c('#map'))
        c.click('.biz'); c.wait_for_timeout(700); c.click('.pick'); c.wait_for_timeout(300); c.click('.chip >> text=Длинные'); c.wait_for_timeout(300)
        c.click('#days >> text=Завтра'); c.wait_for_timeout(900)
        check('свободные окна на завтра', c.locator('.slot').count() > 10, c.locator('.slot').count())
        c.locator('.slot').nth(40).click(); c.wait_for_timeout(300)
        conf = N_c('#conf'); check('подтверждение: цена 1 500 и предоплата 300', '1 500 KGS' in conf and 'Предоплата 300 KGS' in conf, conf)
        c.fill('#bnm', 'Иван'); c.click('#bgo'); c.wait_for_timeout(1000)
        mt = N_c('#M'); check('записан, открылись «Мои записи»', 'Барбершоп' in mt and 'Мужская стрижка · Длинные' in mt and 'Отменить' in mt, mt[:300])

        print('3. Салон видит запись')
        o.click('.chip >> text=Сегодня'); o.wait_for_timeout(500); o.click('#BB .chip >> text=Завтра'); o.wait_for_timeout(700)
        dt = N_o('#BB'); check('в расписании завтра: клиент Иван, предоплата', 'Иван' in dt and 'Мужская стрижка · Длинные' in dt and 'предоплата 300 KGS' in dt, dt[:400])
        o.click('#BB .chip >> text=Сегодня'); o.wait_for_timeout(600); o.click('text=+ Пришёл без записи'); o.wait_for_timeout(300); o.click('#wgo'); o.wait_for_timeout(800)
        check('«пришёл без записи» — мастер занят сейчас', 'без записи' in N_o('#BB'), N_o('#BB')[:300])
        o.click('.chip >> text=Деньги'); o.wait_for_timeout(600)
        check('«Деньги»: объяснение 5% / 95% и пусто, пока нет онлайн-оплат', '95%' in N_o('#BB') and 'онлайн-оплат не было' in N_o('#BB'), N_o('#BB')[:300])
        check('без ошибок на страницах', not oerr and not cerr, (oerr, cerr))
        o.screenshot(path='/tmp/salon_owner.png'); c.goto(U + '?u=wh-t'); c.wait_for_timeout(1200); c.screenshot(path='/tmp/salon_client.png')
finally:
    srv.terminate()
print(f'\nИтог: {OK} прошло, {BAD} не прошло'); sys.exit(1 if BAD else 0)
