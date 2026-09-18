/* Storefront service worker (shared by every template).
   Navigations always go to the network (fresh prices, cart, session) and fall back to the
   offline page; built assets and media are served cache-first and refreshed in the background.
   The admin, Livewire and JSON endpoints are never cached. */
const VERSION = 'v1';
const CACHE = 'storefront-' + VERSION;
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })).catch(() => {}))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (/^\/(admin|livewire|api)(\/|$)/.test(url.pathname)) return;

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    const cacheable = url.pathname.startsWith('/build/') || url.pathname.startsWith('/storage/') || url.pathname.startsWith('/templates/')
        || /\.(png|jpe?g|webp|avif|gif|svg|ico|woff2?)$/i.test(url.pathname);
    if (!cacheable) return;

    event.respondWith(caches.open(CACHE).then(async (cache) => {
        const hit = await cache.match(req);
        const refresh = fetch(req).then((res) => { if (res && res.ok) cache.put(req, res.clone()); return res; }).catch(() => hit);
        return hit || refresh;
    }));
});
