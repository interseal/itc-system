#!/usr/bin/env python3
"""Страница /apps/kosmos/ в магазине (в браузере): шаги подключения для руководителя, «Космос» и Фил рядом.
Адрес kosmos.itcpartner.store в браузере подменяется на локальный «Космос». Снимки — /tmp/kst_ui/.
"""
import json, os, shutil, subprocess, sys, time, urllib.request
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
T = '/tmp/kst_ui'; shutil.rmtree(T, ignore_errors=True)
os.makedirs(f'{T}/store/api'); os.makedirs(f'{T}/store/apps/kosmos'); os.makedirs(f'{T}/store/apps/fil'); os.makedirs(f'{T}/store/uploads/kosmos/pkg')
shutil.copy(f'{ROOT}/store/api/kosmos.php', f'{T}/store/api/')
shutil.copy(f'{ROOT}/store/apps/kosmos/index.html', f'{T}/store/apps/kosmos/')
open(f'{T}/store/apps/fil/index.html', 'w').write('<!doctype html><meta charset=utf-8><body style="font:16px sans-serif;padding:20px;background:#fff8ec"><h2>Фил</h2><p id=t></p><script>document.getElementById("t").textContent="пропуск: "+(location.search.match(/t=([^&]+)/)||[])[1]</script>')
open(f'{T}/store/api/config.php', 'w').write(r'''<?php
define('KOSMOS_STORE_URL', 'http://127.0.0.1:8791');
function db() { static $p; if (!$p) { $p = new PDO('sqlite::memory:'); $p->exec('CREATE TABLE staff (phone TEXT, name TEXT, role TEXT, active INT)'); } return $p; }
function authUser($role = null) {
  $t = $_GET['t'] ?? ''; $map = ['boss-token' => ['+996555000001','boss'], 'staff-token' => ['+996555000002','manager']];
  if (!isset($map[$t])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Войдите заново']); exit; }
  return ['phone' => $map[$t][0], 'role' => 'itc', 'staff_role' => $map[$t][1]];
}''')
subprocess.run(['openssl', 'genpkey', '-algorithm', 'ed25519', '-out', f'{T}/sign.pem'], check=True)
subprocess.run([sys.executable, f'{ROOT}/tools/build_pkg.py', '--key', f'{T}/sign.pem', '--out', f'{T}/store/uploads/kosmos/pkg'], check=True, capture_output=True)
open(f'{T}/master.key', 'wb').write(os.urandom(48))
open(f'{T}/kconf.php', 'w').write(f"<?php return ['db' => '{T}/k.sqlite', 'master_key_file' => '{T}/master.key', 'store_token_file' => '{T}/none', 'claude_url' => 'http://127.0.0.1:8768/v1/messages', 'dev_users' => ['boss-token' => ['+996555000001', 'Алексей', 'boss', true]]];")
env = dict(os.environ, KOSMOS_CONFIG=f'{T}/kconf.php', PHP_CLI_SERVER_WORKERS='4')
procs = [subprocess.Popen(['php', '-S', '127.0.0.1:8791', '-t', f'{T}/store'], stdout=open(f'{T}/s.log', 'w'), stderr=subprocess.STDOUT),
         subprocess.Popen(['php', '-S', '127.0.0.1:8783', '-t', f'{ROOT}/www'], env=env, stdout=open(f'{T}/k.log', 'w'), stderr=subprocess.STDOUT),
         subprocess.Popen([sys.executable, f'{ROOT}/tests/mock_claude.py', '8768', f'{T}/mock.log'])]
time.sleep(1)
ok_n = fail_n = 0
def check(name, cond, extra=''):
    global ok_n, fail_n
    if cond: ok_n += 1; print('  ✓', name)
    else: fail_n += 1; print('  ✗', name, extra)

def proxy(route):
    u = route.request.url.replace('https://kosmos.itcpartner.store', 'http://127.0.0.1:8783')
    req = urllib.request.Request(u, data=route.request.post_data_buffer, method=route.request.method,
                                 headers={k: v for k, v in route.request.headers.items() if k.lower() in ('content-type', 'x-eco-token')})
    with urllib.request.urlopen(req) as r:
        route.fulfill(status=r.status, headers={'content-type': r.headers.get('content-type', 'text/html')}, body=r.read())

try:
    with sync_playwright() as p:
        br = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        errs = []
        ctx = br.new_context(viewport={'width': 1400, 'height': 860}, locale='ru-RU')
        ctx.route('**/fonts.googleapis.com/**', lambda r: r.abort())
        ctx.route('https://kosmos.itcpartner.store/**', proxy)
        pg = ctx.new_page(); pg.on('pageerror', lambda e: errs.append(str(e)))
        print('1. Сервер ещё не подключён')
        pg.goto('http://127.0.0.1:8791/apps/kosmos/?t=boss-token')
        pg.wait_for_selector('text=Подключение «Космоса»')
        check('пропуск убран из адреса', 't=' not in pg.url, pg.url)
        check('шаг 1 «пакет в магазине» выполнен', pg.locator('.dot.ok').count() == 1)
        pg.click('text=Получить команду установки')
        pg.wait_for_selector('#cmd')
        check('команда установки показана', 'a=install&c=' in pg.inner_text('#cmd'))
        pg.screenshot(path=f'{T}/1_connect.png', full_page=True)
        st = ctx.new_page(); st.goto('http://127.0.0.1:8791/apps/kosmos/?t=staff-token'); st.wait_for_selector('text=«Космос» подключается')
        check('сотрудник видит «подключается», без команд', st.locator('#cmd').count() == 0); st.close()
        print('2. Сервер готов: «Космос» и Фил рядом')
        script = urllib.request.urlopen(pg.inner_text('#cmd').split("'")[1]).read().decode()
        tok = script.split("printf '%s' '")[1].split("'")[0]
        req = urllib.request.Request('http://127.0.0.1:8791/api/kosmos.php?a=report', data=json.dumps({'ver': '0.1.0', 'https': True, 'dns_ok': True, 'key': True, 'worker': True, 'ip': '8.8.8.8'}).encode(), headers={'X-Kosmos-Token': tok}, method='POST')
        urllib.request.urlopen(req).read()
        pg.goto('http://127.0.0.1:8791/apps/kosmos/?t=boss-token')
        pg.wait_for_selector('#pK iframe')
        kf = pg.frame_locator('#pK iframe'); kf.locator('text=Чем заняться?').wait_for()
        check('«Космос» открылся внутри страницы магазина и узнал руководителя', kf.locator('#who').inner_text().startswith('Алексей'))
        ff = pg.frame_locator('#pF iframe'); ff.locator('h2').wait_for()
        check('Фил открыт рядом, ему передан пропуск', 'boss-token' in ff.locator('#t').inner_text())
        check('на широком экране — режим «Рядом»', pg.is_visible('#pK') and pg.is_visible('#pF'))
        kr = urllib.request.Request('http://127.0.0.1:8783/api.php?a=key_add', data=json.dumps({'name': 'Тест', 'key': 'sk-ant-good-' + 'q' * 30}).encode(), headers={'Content-Type': 'application/json', 'X-Eco-Token': 'boss-token'}, method='POST')
        urllib.request.urlopen(kr).read()
        kf.locator('#ta').fill('Привет из магазина'); kf.locator('#sendBtn').click()
        kf.locator('.m.a .md').wait_for()
        check('в «Космосе» внутри магазина можно работать', 'Привет из магазина' in kf.locator('.m.a .md').inner_text())
        pg.screenshot(path=f'{T}/2_both.png')
        pg.click('#seg [data-m=f]'); check('переключение на Фила не закрывает «Космос»', pg.locator('#pK iframe').count() == 1 and not pg.is_visible('#pK'))
        pg.click('#infoBtn'); pg.wait_for_selector('text=Открыть «Космос»')
        check('руководитель видит состояние сервера (все шаги выполнены)', pg.locator('.dot.ok').count() == 5)
        pg.screenshot(path=f'{T}/3_status.png', full_page=True)
        ph = br.new_context(viewport={'width': 390, 'height': 800}, locale='ru-RU'); ph.route('**/fonts.googleapis.com/**', lambda r: r.abort()); ph.route('https://kosmos.itcpartner.store/**', proxy)
        pp = ph.new_page(); pp.goto('http://127.0.0.1:8791/apps/kosmos/?t=boss-token'); pp.wait_for_selector('#pK iframe')
        check('на телефоне — один помощник, без режима «Рядом»', pp.is_visible('#pK') and not pp.is_visible('#pF') and not pp.is_visible('#seg [data-m=b]'))
        pp.frame_locator('#pK iframe').locator('text=Чем заняться?').wait_for()
        pp.screenshot(path=f'{T}/4_phone.png')
        check('ошибок JavaScript нет', not errs, errs)
        br.close()
finally:
    for pr in procs: pr.terminate()
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
sys.exit(1 if fail_n else 0)
