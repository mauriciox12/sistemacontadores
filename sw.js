// Kontify APP Service Worker
const CACHE_NAME = 'kontify-v1';
const ASSETS_TO_CACHE = [
    './',
    './index.php',
    './cotizador.php',
    './boveda.php'
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Modo de red primero con fallback básico
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});
