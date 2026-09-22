const CACHE = 'divine-static-v3';
const STATIC_PATHS = new Set([
  '/styles.css',
  '/styles/theme.css',
  '/styles/kioskito.css',
  '/styles/index.css',
  '/styles/menu.css',
  '/styles/login.css',
  '/styles/stock-contenedor.css',
  '/styles/stock-contenedor-crud.css',
  '/js/theme.js',
  '/js/menu.js',
  '/js/stock-contenedor.js',
  '/script.js',
  '/pwa.js',
]);

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll([...STATIC_PATHS])).then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin || event.request.method !== 'GET') return;
  if (!STATIC_PATHS.has(url.pathname)) return;

  event.respondWith(
    fetch(event.request).then(response => {
      const copy = response.clone();
      caches.open(CACHE).then(cache => cache.put(event.request, copy)).catch(() => {});
      return response;
    }).catch(() => caches.match(event.request).then(cached => cached || new Response('', { status: 503, statusText: 'Offline' })))
  );
});
