/* ИТК Воронки сделок · sw.js v1 — само приложение хранится в телефоне и открывается мгновенно;
   данные сделок (/api/flow.php) всегда идут в сеть. Страница всегда берётся свежая с сервера, без связи — из телефона. */
var C = 'itc-crm-v61';   // №629 · voice.js: слова по ходу речи
var SHELL = ['./', 'index.html', 'manifest.webmanifest', 'icon-192.png', 'icon-512.png', 'fav-64.png',
  '/fonts/roboto-cyrillic-400-normal.woff2', '/fonts/roboto-latin-400-normal.woff2',
  '/fonts/roboto-cyrillic-500-normal.woff2', '/fonts/roboto-latin-500-normal.woff2',
  '/fonts/roboto-cyrillic-700-normal.woff2', '/fonts/roboto-latin-700-normal.woff2'];
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(C).then(function (c) { return Promise.all(SHELL.map(function (u) { return c.add(new Request(u, { cache: 'reload' })).catch(function () {}); })); }));
  self.skipWaiting();
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k.indexOf('itc-crm-') === 0 && k !== C; }).map(function (k) { return caches.delete(k); })); }));
  self.clients.claim();
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;
  if (/\/(api\.php)$/.test(u.pathname)) return;
  // №16 · сама страница — СРАЗУ из телефона (без ожидания сервера); свежую версию тихо берём в фоне — она откроется в следующий раз.
  // В телефоне ещё нет — берём с сервера.
  if (r.mode === 'navigate' || /\/(index\.html)?$/.test(u.pathname)) {
    e.respondWith(caches.open(C).then(function (c) {
      var net = fetch(r, { cache: 'no-store' }).then(function (res) { if (res && res.ok && res.type === 'basic') c.put('index.html', res.clone()); return res; });
      return c.match('index.html').then(function (hit) {
        if (hit) { e.waitUntil(net.catch(function () {})); return hit; }
        return net.catch(function () { return new Response('Нет связи', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } }); });
      });
    }));
    return;
  }
  // остальное (значки, шрифты): сначала из телефона, в фоне — свежая версия с сервера
  e.respondWith(caches.open(C).then(function (c) {
    return c.match(r, { ignoreSearch: true }).then(function (hit) {
      var net = fetch(r).then(function (res) { if (res && res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; });
      return hit || net;
    });
  }));
});
