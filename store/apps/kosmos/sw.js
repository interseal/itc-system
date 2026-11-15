/* «Космос» · sw.js v2 (№16) — страница открывается сразу из телефона, свежая версия тихо догружается в фоне
   и откроется в следующий раз. Команды и голос (/api/) — всегда через сеть. */
var C = 'itc-kosmos-v49';   // номер — как у apps/fil (№537)
var SHELL = ['./', 'index.html', 'kosmos-splash.jpg?v=1', 'itc-logo-w.svg', 'voice.js', 'manifest.webmanifest', 'icon-192.png', 'fav-64.png', 'kosmos-face.jpg', 'kosmos-face-s.jpg', 'kosmos-cut.png'];
self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(C).then(function (c) { return Promise.all(SHELL.map(function (u) { return c.add(new Request(u, { cache: 'reload' })).catch(function () {}); })); }));
  self.skipWaiting();
});
self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (ks) { return Promise.all(ks.filter(function (k) { return k.indexOf('itc-kosmos-') === 0 && k !== C; }).map(function (k) { return caches.delete(k); })); }));
  self.clients.claim();
});
self.addEventListener('fetch', function (e) {
  var r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || u.pathname.indexOf('/apps/kosmos/') !== 0 || u.pathname.indexOf('/apps/kosmos/foto/') === 0) return;
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
