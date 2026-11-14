#!/usr/bin/env python3
"""№597 · проверка «ИТК Сервис» (api/svc.php) на тестовом магазине /tmp/kft/store (MariaDB itc, входы boss-t / mgr-t / wh-t).
mgr-t — владелец салона, wh-t — клиент, boss-t — руководитель ИТК."""
import json, os, shutil, subprocess, sys, time, urllib.request, urllib.parse, datetime

R = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
ST = '/tmp/kft/store'; PORT = 8796; OK = 0; BAD = 0
shutil.copy(f'{R}/store/api/svc.php', f'{ST}/api/svc.php')
for f in ['svc_pay_provider.php']:
    if os.path.exists(f'{ST}/api/{f}'): os.remove(f'{ST}/api/{f}')
srv = subprocess.Popen(['php', '-S', f'127.0.0.1:{PORT}', '-t', ST], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL); time.sleep(0.8)
subprocess.run(['mysql', '-uitc', '-pitcpass', 'itc', '-e', 'DROP TABLE IF EXISTS svc_biz, svc_staff, svc_service, svc_booking, svc_pay'], capture_output=True)


def call(tok, action, body=None, **q):
    qs = urllib.parse.urlencode(dict(q, action=action, t=tok))
    req = urllib.request.Request(f'http://127.0.0.1:{PORT}/api/svc.php?{qs}', data=json.dumps(dict(body or {}, t=tok)).encode() if body is not None else None,
                                 headers={'Content-Type': 'application/json'})
    try: return json.loads(urllib.request.urlopen(req, timeout=20).read())
    except urllib.error.HTTPError as e: return json.loads(e.read() or b'{}')


def check(name, cond, extra=''):
    global OK, BAD
    if cond: OK += 1; print('  ✓', name)
    else: BAD += 1; print('  ✗', name, str(extra)[:400])


try:
    H = {str(i): {'o': '09:00', 'c': '21:00'} for i in range(1, 8)}
    print('1. Салон заводит себя')
    r = call('mgr-t', 'biz_save', {'kind': 'salon', 'name': 'Барбершоп «Борода»', 'legal_type': 'ip', 'legal_name': 'ИП Иванов И.И.', 'inn': '123456789012',
                                   'address': 'ул. Ленина, 1', 'city': 'Бишкек', 'lat': 42.8746, 'lng': 74.5698, 'tz': 'Asia/Bishkek', 'cur': 'KGS', 'hours': H,
                                   'prepay': {'mode': 'percent', 'value': 20, 'cancel_h': 2}})
    check('создан черновик', r.get('ok') and r.get('id'), r); B = r['id']
    r = call('mgr-t', 'publish', {'biz': B, 'on': 1}); check('без мастеров и услуг не публикуется, и говорит чего не хватает', not r.get('ok') and 'мастер' in r.get('error', ''), r)
    s1 = call('mgr-t', 'staff_save', {'biz': B, 'name': 'Азамат', 'title': 'барбер'})['id']; s2 = call('mgr-t', 'staff_save', {'biz': B, 'name': 'Тимур', 'title': 'барбер'})['id']
    sv = call('mgr-t', 'svc_save', {'biz': B, 'name': 'Мужская стрижка', 'dur': 60, 'price': 1000, 'variants': [{'name': 'Короткие', 'price': 1000}, {'name': 'Длинные', 'price': 1500}]})
    check('мастера и услуга с вариантами цены', sv.get('ok') and s1 and s2, sv); SV = sv['id']
    r = call('mgr-t', 'publish', {'biz': B, 'on': 1}); check('опубликован', r.get('ok') and r.get('status') == 'active', r)

    print('2. Клиент видит рядом и записывается')
    r = call('wh-t', 'near', kind='salon', lat=42.87, lng=74.57, r=5)
    check('салон на карте с расстоянием и «светофором»', r.get('ok') and r['list'] and r['list'][0]['id'] == B and r['list'][0]['km'] is not None and r['list'][0]['load']['c'] in ('green', 'yellow', 'red', 'grey'), r)
    check('цена «от» по вариантам', r['list'][0]['from'] == 1000, r['list'][0])
    r = call('wh-t', 'near', kind='salon', lat=55.75, lng=37.61, r=5); check('в другом городе не видно', r.get('ok') and not r['list'], r)
    tom = (datetime.datetime.now(datetime.UTC).replace(tzinfo=None) + datetime.timedelta(hours=6) + datetime.timedelta(days=1)).strftime('%Y-%m-%d')
    r = call('wh-t', 'slots', biz=B, service=SV, date=tom)
    check('свободные окна на завтра с 09:00 по 15 минут, оба мастера', r.get('ok') and r['slots'] and r['slots'][0]['hm'] == '09:00' and len(r['slots'][0]['staff']) == 2 and r['slots'][-1]['hm'] == '20:00', r.get('slots', [])[:2])
    T = r['slots'][0]['at']
    b1 = call('wh-t', 'book', {'biz': B, 'service': SV, 'variant': 1, 'at': T, 'name': 'Клиент'})
    check('запись: вариант «Длинные» 1500, предоплата 20% = 300, терминала нет — оплата на месте', b1.get('ok') and b1['prepay'] == 300 and b1.get('pay_onsite'), b1)
    b2 = call('wh-t', 'book', {'biz': B, 'service': SV, 'at': T}); check('то же время — достаётся второму мастеру', b2.get('ok') and b2['staff'] != b1['staff'], (b1, b2))
    b3 = call('wh-t', 'book', {'biz': B, 'service': SV, 'at': T}); check('третьему в то же время — «уже заняли»', not b3.get('ok') and 'заняли' in b3.get('error', ''), b3)
    r = call('wh-t', 'slots', biz=B, service=SV, date=tom); check('09:00 пропало из свободных', r['slots'][0]['hm'] != '09:00', r['slots'][:1])
    r = call('wh-t', 'my'); check('«Мои записи» клиента', r.get('ok') and len(r['list']) == 2 and r['list'][0]['biz']['name'].startswith('Барбершоп'), r)

    print('3. Деньги: прошло через нас — 5% наши, 95% салону')
    open(f'{ST}/api/svc_pay_provider.php', 'w').write("<?php function svc_provider_start($p,$b){return ['provider'=>'test','ref'=>'T'.$p['id'],'url'=>'https://pay.test/'.$p['id']];}\n"
                                                       "function svc_provider_hook(){return ['pay_id'=>(int)($_GET['pid']??0),'paid'=>1,'ref'=>'OK'];}\n")
    b4 = call('wh-t', 'book', {'biz': B, 'service': SV, 'at': T + 3600}); check('с терминалом — ссылка на оплату, запись ждёт оплаты', b4.get('ok') and b4.get('pay_url', '').startswith('https://pay.test/'), b4)
    pid = int(b4['pay_url'].rsplit('/', 1)[1])
    raw = urllib.request.urlopen(f'http://127.0.0.1:{PORT}/api/svc.php?action=pay_hook&pid={pid}').read(); check('терминал подтвердил оплату', json.loads(raw).get('ok'), raw)
    m = call('mgr-t', 'money', biz=B)
    check('у салона: прошло 200, наши 5% = 10, ему 190 к выплате', m.get('ok') and m['sum']['paid'] == 200 and m['sum']['fee'] == 10 and m['sum']['payout'] == 190 and m['sum']['due'] == 190, m.get('sum'))
    a = call('boss-t', 'admin'); check('руководитель видит: через нас 200, заработали 10, к выплате 190', a.get('ok') and a['total'] == {'through': 200.0, 'fee': 10.0, 'due': 190.0}, a.get('total'))
    call('boss-t', 'payout_mark', {'biz': B}); m = call('mgr-t', 'money', biz=B); check('выплата отмечена отправленной', m['sum']['sent'] == 190 and m['sum']['due'] == 0, m.get('sum'))
    b5 = call('wh-t', 'book', {'biz': B, 'service': SV, 'at': T + 7200}); pid5 = int(b5['pay_url'].rsplit('/', 1)[1]); urllib.request.urlopen(f'http://127.0.0.1:{PORT}/api/svc.php?action=pay_hook&pid={pid5}').read()
    c = call('wh-t', 'cancel', {'id': b5['id']}); check('отмена заранее — деньги клиенту, наши 5% не берём', c.get('ok') and c.get('refund') and not c.get('late'), c)
    m = call('mgr-t', 'money', biz=B); check('после возврата у салона по-прежнему 200 / 10', m['sum']['paid'] == 200 and m['sum']['fee'] == 10, m.get('sum'))

    print('4. Кабинет салона')
    d = call('mgr-t', 'day', biz=B, date=tom); check('расписание дня по мастерам', d.get('ok') and len(d['staff']) == 2 and d['staff'][0]['open'] == ['09:00', '21:00'] and len(d['list']) >= 3, d)
    w = call('mgr-t', 'walkin', {'biz': B, 'staff': s1, 'dur': 30}); check('«пришёл без записи» (если салон сейчас открыт — встаёт, иначе тоже пишется)', w.get('ok'), w)
    w2 = call('mgr-t', 'walkin', {'biz': B, 'staff': s1, 'dur': 30}); check('второй «без записи» на того же мастера в то же время — нельзя', not w2.get('ok'), w2)
    r = call('mgr-t', 'bstatus', {'id': w['id'], 'status': 'done', 'end_now': 1}); check('«закончили раньше» освобождает мастера', r.get('ok'), r)
    r = call('wh-t', 'biz_save', {'id': B, 'name': 'Чужой'}); check('чужой бизнес менять нельзя', not r.get('ok') and r.get('error', '').startswith('Нет доступа'), r)
    r = call('wh-t', 'admin'); check('итоги ИТК — только руководителю', not r.get('ok'), r)
    r = call('mgr-t', 'bstatus', {'id': b1['id'], 'status': 'cancel'}); check('салон отменил запись', r.get('ok'), r)
    r = call('mgr-t', 'mybiz', kind='salon'); check('«Мой салон»: профиль, мастера, услуги, загрузка', r.get('ok') and r['list'][0]['name'].startswith('Барбершоп') and len(r['list'][0]['staff']) == 2 and r['list'][0]['services'][0]['variants'][1]['price'] == 1500, r)
finally:
    srv.terminate()
    if os.path.exists(f'{ST}/api/svc_pay_provider.php'): os.remove(f'{ST}/api/svc_pay_provider.php')
print(f'\nИтог: {OK} прошло, {BAD} не прошло'); sys.exit(1 if BAD else 0)
