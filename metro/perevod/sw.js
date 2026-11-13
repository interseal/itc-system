/* ИТК Переводчик · sw.js v1 — само приложение хранится в телефоне и открывается мгновенно;
   перевод (api.php) и cfg.json всегда идут в сеть. Новая версия подтягивается при следующем запуске. */
var C = 'itc-perevod-v1';
var SHELL = ['./', 'index.html', 'manifest.webmanifest', 'icon-192.png', 'icon-512.png', 'fav-64.png',
  '../fonts/roboto-cyrillic-400-normal.woff2', '../fonts/roboto-latin-400-normal.woff2',
  '../fonts/roboto-cyrillic-500-normal.woff2', '../fonts/roboto-latin-500-normal.woff2',
  '../fonts/roboto-cyrillic-700-normal.woff2', '../fonts/roboto-latin-700-normal.woff2'];
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(C).then(function (c) { return Promise.all(SHELL.map(function (u) { return c.add(u).catch(function () {}); })); }));
  self.skipWaiting();
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k.indexOf('itc-perevod-') === 0 && k !== C; }).map(function (k) { return caches.delete(k); })); }));
  self.clients.claim();
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin) return;
  if (/\/(api\.php|cfg\.json)$/.test(u.pathname)) return;
  // страница: сначала из телефона, в фоне — свежая версия с сервера
  e.respondWith(caches.open(C).then(function (c) {
    return c.match(r, { ignoreSearch: true }).then(function (hit) {
      var net = fetch(r).then(function (res) { if (res && res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; });
      return hit || net;
    });
  }));
});
