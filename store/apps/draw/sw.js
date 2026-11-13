/* «Рисовалка ИТК» · sw.js v1 — приложение открывается сразу, свежая версия тихо догружается в фоне.
   Данные (/api/) — всегда через сеть. */
var C = 'itc-draw-v4';   // №532
var SHELL = ['./', 'index.html', 'manifest.webmanifest', 'icon-192.png'];
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(C).then(function (c) { return Promise.all(SHELL.map(function (u) { return c.add(new Request(u, { cache: 'reload' })).catch(function () {}); })); }));
  self.skipWaiting();
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k.indexOf('itc-draw-') === 0 && k !== C; }).map(function (k) { return caches.delete(k); })); }));
  self.clients.claim();
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || u.pathname.indexOf('/apps/draw/') !== 0 || u.pathname.indexOf('/apps/draw/img/') === 0) return;
  var page = r.mode === 'navigate' || /\/(index\.html)?$/.test(u.pathname);
  var key = page ? 'index.html' : r;
  e.respondWith(caches.open(C).then(function (c) {
    var net = fetch(r, { cache: 'no-store' }).then(function (res) { if (res && res.ok && res.type === 'basic') c.put(key, res.clone()); return res; });
    return c.match(key, { ignoreSearch: !page }).then(function (hit) {
      if (hit) { e.waitUntil(net.catch(function () {})); return hit; }
      return net;
    });
  }));
});
