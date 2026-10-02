/* Haziri service worker: cache the app shell (built assets) so /me and the kiosk open without a network.
   API calls always go to the network; the kiosk keeps its own IndexedDB queue for offline punches. */
const CACHE = 'hazri-shell-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    if (event.request.method !== 'GET' || url.origin !== location.origin) return;

    // Hashed build assets: cache-first (immutable).
    if (url.pathname.startsWith('/build/') || url.pathname === '/hazri-icon.svg') {
        event.respondWith(
            caches.match(event.request).then((hit) => hit || fetch(event.request).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(event.request, copy));
                return res;
            })),
        );
        return;
    }

    // Pages (/me, /kiosk/…): network-first, fall back to the last good copy.
    if (event.request.mode === 'navigate' && (url.pathname.startsWith('/me') || url.pathname.startsWith('/kiosk/'))) {
        event.respondWith(
            fetch(event.request).then((res) => {
                const copy = res.clone();
                caches.open(CACHE).then((c) => c.put(event.request, copy));
                return res;
            }).catch(() => caches.match(event.request)),
        );
    }
});
