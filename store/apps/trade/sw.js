// ИТК Трейдинг — служебный файл для установки приложения на рабочий стол (Windows, Android).
// Ничего не запоминает: все запросы идут напрямую в сеть, поэтому обновления приходят сразу.
self.addEventListener('install',()=>self.skipWaiting());
self.addEventListener('activate',e=>e.waitUntil(self.clients.claim()));
self.addEventListener('fetch',()=>{});
