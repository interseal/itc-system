/* ИТК · установка программы на компьютер (№349): браузер присылает «можно установить» — показываем стеклянную кнопку.
   Открыли с ?install=1 (кнопка «На рабочий стол» в Экосистеме) — кнопка видна сразу, а если браузер молчит — подсказка, где пункт меню. */
(function () {
  if (window.__itcInst) return; window.__itcInst = 1;
  var mob = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '') || /ITCApp/.test(navigator.userAgent || '');
  var standalone = false; try { standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone; } catch (e) {}
  if (mob || standalone) return;
  var ask = /[?&]install=1/.test(location.search || ''), ev = null, box = null;
  function el() {
    if (box) return box;
    box = document.createElement('div');
    box.style.cssText = 'position:fixed;right:18px;bottom:18px;z-index:99999;max-width:340px;padding:14px 16px;border-radius:16px;background:rgba(17,44,147,.82);-webkit-backdrop-filter:blur(14px);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.35);box-shadow:0 10px 30px rgba(0,0,0,.25);color:#fff;font:400 14px/1.4 Roboto,Arial,sans-serif';
    document.body.appendChild(box); return box;
  }
  function show(withBtn) {
    var b = el();
    b.innerHTML = '<div style="font-weight:700;font-size:15px;margin-bottom:6px">Значок на рабочий стол</div>'
      + (withBtn ? '<div style="margin-bottom:10px">Программа будет открываться отдельным окном, как обычная, и появится значок на рабочем столе.</div><button type="button" id="itcInstGo" style="width:100%;border:0;border-radius:11px;padding:11px;background:#fff;color:#112C93;font:700 15px Roboto,Arial,sans-serif;cursor:pointer">Установить на компьютер</button>'
                 : '<div>Нажмите в браузере на три точки (меню) справа сверху и выберите <b>«Установить приложение»</b> или <b>«Добавить на рабочий стол»</b>. В Яндекс Браузере значок установки есть и прямо в адресной строке.</div>')
      + '<button type="button" id="itcInstX" style="position:absolute;right:8px;top:6px;border:0;background:none;color:#fff;font:700 18px Roboto;cursor:pointer">×</button>';
    var g = document.getElementById('itcInstGo'); if (g) g.onclick = function () { if (!ev) return; ev.prompt(); ev.userChoice.then(function () { ev = null; hide(); }, hide); };
    document.getElementById('itcInstX').onclick = hide;
  }
  function hide() { if (box) { box.remove(); box = null; } }
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; if (ask) show(true); });
  window.addEventListener('appinstalled', hide);
  if (ask) setTimeout(function () { if (!ev && document.body) show(false); }, 2500);
})();
