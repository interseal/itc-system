// ITC Global — service worker. Network-first, чтобы всегда показывать свежую версию,
// с офлайн-фолбэком из кэша. API (/api/) не кэшируется никогда.
// ==== ИТК · ОБНОВЛЕНИЕ №668 · не подменять скрипты и стили страницей приложения ====
const ITC_UPDATE = 803;
const CACHE = 'itc-v1047';   // №533 · «Космос» в «Моих приложениях»
const SHELL = ['/', '/index.html', '/manifest.json', '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', function (e) {
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(function (c) { return c.addAll(SHELL).catch(function(){}); }));
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (ks) {
      return Promise.all(ks.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('push', function (e) {
  var data = { title: 'ИТК Мебель', body: 'Новое уведомление', url: '/' };
  try { if (e.data) { var j = e.data.json(); data.title = j.title || data.title; data.body = j.body || data.body; data.url = j.url || data.url; } } catch (err) {}
  e.waitUntil(self.registration.showNotification(data.title, {
    body: data.body,
    icon: '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
    data: { url: data.url },
    vibrate: [100, 50, 100]
  }));
});

self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var url = (e.notification.data && e.notification.data.url) || '/';
  e.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (cls) {
      for (var i = 0; i < cls.length; i++) { if ('focus' in cls[i]) { cls[i].navigate(url); return cls[i].focus(); } }
      if (self.clients.openWindow) return self.clients.openWindow(url);
    })
  );
});

self.addEventListener('fetch', function (e) {
  var req = e.request;
  if (req.method !== 'GET') return;
  var url = new URL(req.url);
  /* ЧУЖИЕ АДРЕСА НЕ ТРОГАЕМ ВООБЩЕ.
     Раньше сюда попадало всё подряд, включая картинки карты и её библиотеку с
     других сайтов. При любой заминке срабатывал запасной путь ниже и отдавал
     вместо картинки страницу приложения — карта получала кусок html и молча
     рисовала пустоту. Отсюда был белый экран без единой ошибки. */
  if (url.origin !== location.origin) return;
  if (url.pathname.indexOf('/api/') === 0) return; // API всегда из сети
  if (url.pathname.indexOf('/leaflet/') === 0) return; // библиотека карты — напрямую, без нашего вмешательства
  if (/\.php$/i.test(url.pathname)) return; // php-файлы (robokassa.php, оплата и т.п.) — всегда из сети, SW не трогает
  if (url.pathname.indexOf('/eco/') === 0) return;
  if (url.pathname.indexOf('/apps/') === 0) return; // №861 · приложения в своих окнах (Экосистема и др.) — SW магазина не вмешивается
  if (url.pathname.indexOf('/esim/') === 0) return; // приложение eSIM — своя папка, SW магазина не вмешивается // Экосистема — свой обработчик /eco/sw.js, магазин туда не лезет
  // Отдельные .html-страницы (офферы и т.п.) — всегда из сети, иначе SW подменит их приложением
  if (url.origin === location.origin && /\.html$/i.test(url.pathname) && url.pathname !== '/index.html') {
    e.respondWith(fetch(req).catch(function () { return caches.match(req); }));
    return;
  }
  // HTML-документы: NETWORK-FIRST — всегда свежая версия из сети; кэш только офлайн-фолбэк.
  var isHTML = (req.mode === 'navigate') || ((req.headers.get('accept') || '').indexOf('text/html') !== -1);
  if (isHTML) {
    e.respondWith(
      fetch(req, { cache: 'no-store' }).then(function (res) {
        if (res && res.status === 200 && url.origin === location.origin) {
          var cl = res.clone();
          caches.open(CACHE).then(function (c) { c.put(req, cl); });
        }
        return res;
      }).catch(function () {
        return caches.match(req).then(function (r) { return r || caches.match('/index.html'); });
      })
    );
    return;
  }
  e.respondWith(
    fetch(req).then(function (res) {
      if (res && res.status === 200 && url.origin === location.origin) {
        var cl = res.clone();
        caches.open(CACHE).then(function (c) { c.put(req, cl); });
      }
      return res;
    }).catch(function () {
      /* ВАЖНО: сюда попадают скрипты, стили и картинки. Раньше при неудаче им
         подсовывалась страница приложения — и, например, библиотека карты
         получала index.html вместо кода. Молча, без ошибки. Теперь отдаём
         только настоящую сохранённую копию, а если её нет — честную неудачу. */
      return caches.match(req).then(function (r) { return r || Response.error(); });
    })
  );
});
