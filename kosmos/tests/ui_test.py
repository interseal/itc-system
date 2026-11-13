#!/usr/bin/env python3
"""Проверка экрана «Космоса» в настоящем браузере (Chrome без окна): вход, ключ, разговор, задача, телефон.
Запуск: python3 tests/ui_test.py   (из папки kosmos). Снимки — в /tmp/kosmos_ui/.
"""
import json, os, shutil, subprocess, sys, time
from playwright.sync_api import sync_playwright

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TMP = '/tmp/kosmos_ui'
shutil.rmtree(TMP, ignore_errors=True); os.makedirs(TMP)
open(f'{TMP}/master.key', 'wb').write(os.urandom(48))
open(f'{TMP}/config.php', 'w').write(f'''<?php return [
  'db' => '{TMP}/kosmos.sqlite', 'master_key_file' => '{TMP}/master.key', 'store_token_file' => '{TMP}/none',
  'claude_url' => 'http://127.0.0.1:8766/v1/messages',
  'dev_users' => ['boss-token' => ['+996555000001', 'Алексей', 'boss', true], 'staff-token' => ['+996555000002', 'Айгуль', 'manager', false]],
];''')
env = dict(os.environ, KOSMOS_CONFIG=f'{TMP}/config.php', PHP_CLI_SERVER_WORKERS='4')
procs = [subprocess.Popen([sys.executable, f'{ROOT}/tests/mock_claude.py', '8766', f'{TMP}/mock.log']),
         subprocess.Popen(['php', '-S', '127.0.0.1:8782', '-t', f'{ROOT}/www'], env=env, stdout=open(f'{TMP}/php.log', 'w'), stderr=subprocess.STDOUT)]
time.sleep(1.2)
URL = 'http://127.0.0.1:8782/'
ok_n = fail_n = 0
def check(name, cond, extra=''):
    global ok_n, fail_n
    if cond: ok_n += 1; print('  ✓', name)
    else: fail_n += 1; print('  ✗', name, extra)

try:
    with sync_playwright() as p:
        br = p.chromium.launch(executable_path='/usr/bin/google-chrome', args=['--no-sandbox'])
        errors = []
        def page_for(w, h, tok):
            ctx = br.new_context(viewport={'width': w, 'height': h}, locale='ru-RU')
            ctx.route('**/fonts.googleapis.com/**', lambda r: r.abort())
            pg = ctx.new_page()
            pg.on('pageerror', lambda e: errors.append(str(e)))
            pg.on('console', lambda m: errors.append(m.text) if m.type == 'error' and 'fonts' not in m.text and 'Failed to load resource' not in m.text else None)
            pg.goto(URL + ('#t=' + tok if tok else ''))
            return pg

        print('1. Без пропуска')
        pg = page_for(1280, 800, '')
        pg.wait_for_selector('text=Откройте «Космос» из Экосистемы')
        check('просит войти через Экосистему', True)
        pg.screenshot(path=f'{TMP}/0_no_token.png')

        print('2. Руководитель: ключ и разговор')
        pg = page_for(1280, 820, 'boss-token')
        pg.wait_for_selector('text=Чем заняться?')
        check('пропуск убран из адреса', '#t=' not in pg.url, pg.url)
        check('видно предупреждение «нет ключа»', pg.is_visible('text=Ключ Claude ещё не добавлен'))
        pg.screenshot(path=f'{TMP}/1_empty.png')
        pg.click('.banner button')
        pg.wait_for_selector('#kVal')
        pg.fill('#kName', 'Аккаунт 1')
        pg.fill('#kVal', 'sk-ant-good-' + 'a' * 26 + 'AAAA')
        pg.click('#kAdd')
        pg.wait_for_selector('.card.act >> text=работает')
        check('ключ добавлен, «работает», активный', pg.is_visible('.tag.act'))
        pg.screenshot(path=f'{TMP}/2_keys.png')
        pg.click('#setModal .win-h .ibtn')
        check('предупреждение исчезло', not pg.is_visible('text=Ключ Claude ещё не добавлен'))
        pg.fill('#ta', 'Привет! Расскажи, что ты умеешь')
        pg.keyboard.press('Enter')
        pg.wait_for_selector('.m.a .md >> text=Ключ …AAAA')
        pg.wait_for_timeout(500)
        check('ответ показан, разговор в списке', pg.locator('.th').count() == 1)
        pg.fill('#ta', 'запомни: отчёты мне нужны коротко')
        pg.click('#sendBtn')
        pg.wait_for_selector('.chip >> text=Запомнил')
        check('пометка «Запомнил» в ответе', True)
        pg.fill('#ta', 'Сделай сложный анализ трёх фабрик с таблицей')
        pg.click('#sendBtn')
        pg.wait_for_selector('.task')
        check('карточка задачи появилась', pg.is_visible('.task >> text=Задача в'))
        pg.screenshot(path=f'{TMP}/3_task_queued.png')
        subprocess.run(['php', f'{ROOT}/bin/worker.php', 'once'], env=env, timeout=60)
        pg.wait_for_selector('.md h1 >> text=Итог задачи', timeout=15000)
        check('результат задачи пришёл сам (без обновления страницы)', pg.is_visible('.md table'))
        check('карточка: «Задача выполнена»', pg.is_visible('.task.done'))
        pg.screenshot(path=f'{TMP}/4_task_done.png', full_page=True)
        pg.click('text=Память')
        pg.wait_for_selector('#memBody .card')
        check('в памяти видна запись', pg.locator('#memBody .card').count() >= 1)
        pg.screenshot(path=f'{TMP}/5_memory.png')
        pg.click('#memModal .win-h .ibtn')
        pg.click('#setBtn'); pg.click('[data-t=users]')
        pg.wait_for_selector('table.t')
        pg.screenshot(path=f'{TMP}/6_users.png')
        pg.click('[data-t=usage]'); pg.wait_for_selector('table.t >> text=Аккаунт 1')
        check('расход по ключу виден', True)
        pg.click('[data-t=models]'); pg.wait_for_selector('#mFast')
        check('модели: быстрые — Haiku', pg.eval_on_selector('#mFast', 'e => e.value') == 'claude-haiku-5-5')
        pg.screenshot(path=f'{TMP}/7_models.png')

        print('2а. Личный ключ без рабочей области (0.2.1)')
        pg.click('[data-t=keys]'); pg.wait_for_selector('#kVal')
        check('поле ключа текстовое, символы скрыты', pg.eval_on_selector('#kVal', 'e => e.type') == 'text'
              and pg.eval_on_selector('#kVal', 'e => getComputedStyle(e).webkitTextSecurity') == 'disc')
        pg.fill('#kName', 'Личный')
        pg.fill('#kVal', 'sk-ant-usr3-' + 'f' * 26 + 'FFFF')
        pg.click('#kAdd')
        pg.wait_for_selector('select[id^=wsSel]', timeout=15000)
        check('выбор рабочей области открылся сам', pg.locator('select[id^=wsSel] option').count() == 2)
        pg.screenshot(path=f'{TMP}/7a_ws_pick.png')
        pg.select_option('select[id^=wsSel]', label='Склад — wrkspc_04Sklad000')
        pg.click('text=Выбрать и проверить')
        pg.wait_for_selector('text=Рабочая область: Склад', timeout=15000)
        check('рабочая область сохранена у ключа', pg.is_visible('text=Рабочая область: Склад'))
        pg.wait_for_timeout(300)
        pg.screenshot(path=f'{TMP}/7b_ws_done.png')
        pg.fill('#kName', 'Личный 1')
        pg.fill('#kVal', 'sk-ant-usr1-' + 'd' * 26 + 'DDDD')
        pg.click('#kAdd')
        pg.wait_for_selector('text=Рабочая область: Default', timeout=15000)
        check('единственную рабочую область «Космос» выбрал сам', not pg.is_visible('select[id^=wsSel]'))
        pg.screenshot(path=f'{TMP}/7c_ws_auto.png')

        print('2б. GitHub: рабочая копия (0.3.0)')
        json.dump({'repo': 'git@github.com:interseal/itc-system.git', 'dir': '/srv/itc-system', 'ok': False, 'pub': 'ssh-ed25519 AAAAC3NzaTESTKEY kosmos-sg 2026-11-13',
                   'err': 'GitHub пока не пускает сервер: добавьте ключ сервера в хранилище (itc-system → Settings → Deploy keys)', 'checked_at': '2026-11-13 21:10:00'},
                  open(f'{TMP}/git.json', 'w'), ensure_ascii=False)
        pg.click('[data-t=git]'); pg.wait_for_selector('#gitPub')
        check('вкладка GitHub: ключ сервера и что сделать', 'AAAAC3NzaTESTKEY' in pg.eval_on_selector('#gitPub', 'e => e.value') and pg.is_visible('text=Deploy keys'))
        pg.screenshot(path=f'{TMP}/7d_github.png')

        print('3. Сотрудник на телефоне')
        ph = page_for(390, 780, 'staff-token')
        ph.wait_for_selector('text=Чем заняться?')
        check('у сотрудника нет кнопки «Настройки»', not ph.is_visible('#setBtn'))
        ph.click('.idea >> nth=0')
        ph.click('#sendBtn')
        ph.wait_for_selector('.m.a .md')
        ph.screenshot(path=f'{TMP}/8_phone.png')
        ph.click('.top .ibtn >> nth=0')
        ph.wait_for_timeout(300)
        ph.screenshot(path=f'{TMP}/9_phone_drawer.png')
        check('на телефоне открывается список разговоров', ph.locator('.th').count() == 1)
        check('сотрудник не видит разговоры руководителя', ph.locator('.th').count() == 1)
        check('ошибок JavaScript нет', not errors, errors[:5])
        br.close()
finally:
    for pr in procs: pr.terminate()
print(f'\nИтог: {ok_n} прошло, {fail_n} не прошло')
sys.exit(1 if fail_n else 0)
