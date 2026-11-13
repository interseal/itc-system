#!/usr/bin/env python3
"""Экран «Космоса» = экран «Фила» один в один (apps/fil → apps/kosmos), только имя, фото и свой сервер.
Запуск: python3 tools/make_kosmos_app.py <папка apps/fil> <папка apps/kosmos> <папка с картинками Космоса>
Картинки (kosmos-face.jpg, kosmos-face-s.jpg, kosmos-splash.jpg, kosmos-cut.png, kosmos-eco.jpg, kosmos.ico,
icon-192.png, icon-512.png, maskable-512.png, fav-64.png, apple-touch-icon.png, itc-logo-w.svg) кладутся рядом.
После изменений «Фила» запустите ещё раз — «Космос» получит тот же вид и те же кнопки.
"""
import os, re, shutil, sys

SRC, DST, IMG = sys.argv[1], sys.argv[2], sys.argv[3]
os.makedirs(DST, exist_ok=True)
NAME = re.compile(r'(?<!\w)Фил(а|у|ом|е)?(?!\w)')


def name(s):
    return NAME.sub(lambda m: 'Космос' + (m.group(1) or ''), s)


def need(s, a, n=1):
    c = s.count(a)
    if c != n:
        raise SystemExit(f'не нашёл ровно {n} раз ({c}): {a[:90]!r}')


def rep(s, a, b, n=1):
    need(s, a, n)
    return s.replace(a, b)


# ---------------- index.html ----------------
s = open(os.path.join(SRC, 'index.html'), encoding='utf-8').read()
s = rep(s, "var API = '/api/flow.php'", "var API = '/api/kosmos_flow.php'")
s = s.replace('/api/flow.php', '/api/kosmos_flow.php')
for a, b in [("'fil_conv'", "'ks_conv'"), ("'fil_icon'", "'ks_icon'"), ("'fil_spl'", "'ks_spl'"), ("'fil_hello_'", "'ks_hello_'")]:
    if a not in s: raise SystemExit('нет ' + a)
    s = s.replace(a, b)
for a, b in [('fil-face-s.jpg', 'kosmos-face-s.jpg'), ('fil-face.jpg', 'kosmos-face.jpg'), ('fil-splash.jpg', 'kosmos-splash.jpg'),
             ('fil-cut.png', 'kosmos-cut.png'), ('fil.ico', 'kosmos.ico')]:
    s = s.replace(a, b)
s = s.replace('/apps/fil/', '/apps/kosmos/').replace('from=fil', 'from=kosmos')
s = rep(s, "/FilSplash/.test(navigator.userAgent || '')", "/KosmosSplash/.test(navigator.userAgent || '')")

# настройки: вместо DeepSeek — ключи Claude (живут на сервере «Космоса», окно настроек открывается поверх)
i = s.find('  function dsBlock(c){'); j = s.find('  /* №43 · планировщик')
if i < 0 or j < 0: raise SystemExit('нет dsBlock')
s = s[:i] + r'''  /* «Космос»: ключи Claude хранятся только на сервере «Космоса» в Сингапуре — здесь их состояние и кнопка настроек */
  function dsBlock(c){
    var k = c.ks || {}, h = '<h4>Claude — ключи и доступ</h4>';
    if (!k.ok) return h + '<p style="color:#ffb4a8">' + esc(k.error || 'Сервер «Космоса» не отвечает') + '</p>'
      + '<div class="r"><button class="cb" onclick="ksAdmin()">Открыть настройки Claude</button><button class="cb s" onclick="cfgOpen()">Проверить ещё раз</button></div>';
    var td = k.today || {};
    return h + (k.ready ? '<p style="color:#bbed21">Космос думает через Claude. Активный ключ: <b>' + esc(k.active) + '</b>' + (k.keys > 1 ? ' · запасных: ' + (k.keys - 1) : '') + '.</p>'
                        : '<p style="color:#ffb4a8">' + esc(k.problem || 'Ключ Claude ещё не добавлен — Космос не сможет отвечать.') + '</p>')
      + '<p>Ключи Claude (можно несколько, от разных аккаунтов) хранятся только на сервере «Космоса» в Сингапуре. '
      + (k.auto_switch ? 'Если ключ перестанет работать, Космос сам перейдёт на следующий.' : 'Автопереключение на запасной ключ выключено.')
      + (td.calls ? ' Сегодня обращений к Claude: ' + td.calls + '.' : '') + '</p>'
      + '<div class="r"><button class="cb" onclick="ksAdmin()">' + (k.keys ? 'Ключи Claude, доступ, расход' : '+ Добавить ключ Claude') + '</button></div>';
  }
  window.ksAdmin = function(){
    var u = (CFG && CFG.ks_admin) || 'https://kosmos.itcpartner.store/';
    var o = $('ksAdm'); if (!o) { o = document.createElement('div'); o.id = 'ksAdm'; o.style.cssText = 'position:fixed;inset:0;z-index:60;background:#fff;display:flex;flex-direction:column';
      o.innerHTML = '<div style="display:flex;align-items:center;gap:10px;padding:calc(10px + env(safe-area-inset-top)) 12px 10px;background:#0a1a66;color:#fff;font:500 1rem Roboto,sans-serif"><button onclick="ksAdminClose()" style="border:0;background:none;color:#fff;font-size:1.5rem;line-height:1;cursor:pointer;padding:0 6px">&#8592;</button>Настройки Claude</div><iframe id="ksAdmF" style="flex:1;border:0;width:100%" allow="clipboard-write"></iframe>';
      document.body.appendChild(o); }
    o.style.display = 'flex'; $('ksAdmF').src = u + '#t=' + encodeURIComponent(t) + '&set=keys';
  };
  window.ksAdminClose = function(){ var o = $('ksAdm'); if (o) { o.style.display = 'none'; $('ksAdmF').src = 'about:blank'; } if ($('cfg').style.display !== 'none') cfgOpen(); };
  window.addEventListener('message', function(e){ if (e.data && e.data.type === 'kosmos-close') ksAdminClose(); });
''' + s[j:]
s = rep(s, "<p>На какой модели думает Фил. Если выбранная не ответит, Фил сам перейдёт на запасную. Сравнить модели можно внизу.</p>",
        "<p>На какой модели Claude думает Космос. Sonnet — основной, Opus — сильнее и дороже, Haiku — быстрее и дешевле. Сравнить модели можно внизу.</p>")
s = rep(s, "<h4>Работа в фоне</h4><p>Чтобы Фил доделывал большие задачи, даже когда приложение закрыто, сервер раз в минуту будит его по особому адресу. Адрес вставляется один раз в планировщик хостинга.</p>'\n      + '<div class=\"r\"><button class=\"cb\" onclick=\"cronShow()\">Показать адрес для планировщика</button></div><div id=\"cfCron\"></div>'",
        "<h4>Работа в фоне</h4><p>Большие задачи Космос доделывает, даже когда приложение закрыто: его будит собственный сервер в Сингапуре раз в минуту. Ничего настраивать не нужно.</p>'\n      + '<div class=\"r\"><button class=\"cb s\" onclick=\"cronShow()\">Проверить</button></div><div id=\"cfCron\"></div>'")
i = s.find('  window.cronShow = function(){'); j = s.find('  window.cfgOpen = function(){')
s = s[:i] + '''  window.cronShow = function(){
    api('ft_cron_url', {}).then(function(r){
      $('cfCron').innerHTML = '<p>' + (!r || !r.ok ? esc((r && r.error) || 'Ошибка') : r.seen ? 'Работает: сервер последний раз будил Космоса ' + esc(r.seen) : 'Сервер «Космоса» ещё ни разу не будил задачи — он начнёт сам в течение нескольких минут после обновления') + '</p>';
    });
  };
''' + s[j:]
s = name(s)
s = s.replace('<!-- ИТК · «Космос» (№14)', '<!-- ИТК · «Космос» — копия apps/fil один в один, но думает Claude (№533, собрано tools/make_kosmos_app.py). Ниже — исходное описание (№14)', 1)
if NAME.search(s): raise SystemExit('осталось имя Фил')
open(os.path.join(DST, 'index.html'), 'w', encoding='utf-8').write(s)

# ---------------- voice.js, files.js ----------------
v = open(os.path.join(SRC, 'voice.js'), encoding='utf-8').read()
v = rep(v, "'fil_mute'", "'ks_mute'", v.count("'fil_mute'") or 1)
open(os.path.join(DST, 'voice.js'), 'w', encoding='utf-8').write(name(v))
f = open(os.path.join(SRC, 'files.js'), encoding='utf-8').read()
f = f.replace('/apps/fil/lib/', '/apps/kosmos/lib/')
open(os.path.join(DST, 'files.js'), 'w', encoding='utf-8').write(name(f))
os.makedirs(os.path.join(DST, 'lib'), exist_ok=True)
for x in os.listdir(os.path.join(SRC, 'lib')):
    shutil.copy(os.path.join(SRC, 'lib', x), os.path.join(DST, 'lib', x))

# ---------------- sw.js, manifest ----------------
w = open(os.path.join(SRC, 'sw.js'), encoding='utf-8').read()
w = re.sub(r"var C = 'itc-fil-v\d+';[^\n]*", "var C = 'itc-kosmos-v1';   // №533", w)
w = w.replace("k.indexOf('itc-fil-') === 0", "k.indexOf('itc-kosmos-') === 0").replace('/apps/fil/', '/apps/kosmos/')
for a, b in [('fil-face-s.jpg', 'kosmos-face-s.jpg'), ('fil-face.jpg', 'kosmos-face.jpg'), ('fil-splash.jpg', 'kosmos-splash.jpg'), ('fil-cut.png', 'kosmos-cut.png')]:
    w = w.replace(a, b)
need(w, "'itc-kosmos-v1'")
open(os.path.join(DST, 'sw.js'), 'w', encoding='utf-8').write(name(w))
m = open(os.path.join(SRC, 'manifest.webmanifest'), encoding='utf-8').read()
m = m.replace('"Команды по сделкам голосом"', '"Помощник ИТК на Claude: сделки, клиенты, задачи — голосом и текстом"')
open(os.path.join(DST, 'manifest.webmanifest'), 'w', encoding='utf-8').write(name(m))

# ---------------- картинки ----------------
for x in ['kosmos-face.jpg', 'kosmos-face-s.jpg', 'kosmos-splash.jpg', 'kosmos-cut.png', 'kosmos-eco.jpg', 'kosmos.ico', 'icon-192.png',
          'icon-512.png', 'maskable-512.png', 'fav-64.png', 'apple-touch-icon.png', 'itc-logo-w.svg']:
    shutil.copy(os.path.join(IMG, x), os.path.join(DST, x))
print('ok', DST)
