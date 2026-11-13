// Экосистема ИТК — отдельный обработчик, область только /eco/.
// Главное приложение магазина живёт в /sw.js и сюда не лезет.
const CACHE_NAME = 'itc-eco-v4.12.0';
const ASSETS = ['/eco/', '/eco/index.html'];

self.addEventListener('install', function (e) {
  e.waitUntil(caches.open(CACHE_NAME).then(function (c) { return c.addAll(ASSETS).catch(function () {}); }));
  /* ЗДЕСЬ СТОЯЛО self.skipWaiting() — ИЗ-ЗА НЕГО И БЫЛА ДВОЙНАЯ ЗАГРУЗКА.
     Новый обработчик вставал немедленно, перехватывал управление, страница
     это видела как «пришло обновление» и сама перезагружалась: кружок,
     пропал, снова кружок. Полоска «Доступна новая версия» при этом не успевала
     появиться никогда.
     Теперь новый обработчик ждёт. Он встанет либо по нажатию на полоску
     (страница пришлёт SKIP_WAITING — см. ниже), либо сам при следующем
     запуске приложения. Одна загрузка вместо двух. */
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE_NAME; })
                             .map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
     .then(function () {
       return self.clients.matchAll({ type: 'window' }).then(function (cs) {
         cs.forEach(function (c) { c.postMessage({ type: 'SW_UPDATED', version: CACHE_NAME }); });
       });
     })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  if (url.pathname.indexOf('/api/') === 0) return;      // настройки всегда из сети
  if (url.pathname.indexOf('/eco/') !== 0) return;      // чужое не трогаем
  e.respondWith(
    fetch(req).then(function (res) {
      if (res && res.ok) { var cl = res.clone(); caches.open(CACHE_NAME).then(function (c) { c.put(req, cl); }); }
      return res;
    }).catch(function () { return caches.match(req); })
  );
});

self.addEventListener('message', function (e) {
  if (e.data && e.data.type === 'SKIP_WAITING') self.skipWaiting();
});
