/**
 * TESDA-BCAT GMS — Service Worker v1.0.2
 *
 * Scope: served at /TesdaBCAT-1.02/sw.js → scope is /TesdaBCAT-1.02/
 * All asset URLs here are RELATIVE to the SW file location (= project root).
 */

const APP_VERSION   = 'v1.0.2';
const CACHE_STATIC  = `bcat-static-${APP_VERSION}`;
const CACHE_PAGES   = `bcat-pages-${APP_VERSION}`;
const SYNC_TAG      = 'bcat-background-sync';

// Derive the base path from the SW location dynamically
// self.location.pathname = '/TesdaBCAT-1.02/sw.js'
// BASE_PATH              = '/TesdaBCAT-1.02/'
const BASE_PATH = self.location.pathname.replace(/sw\.js$/, '');

// Helper: make absolute URL relative to SW scope
function abs(path) {
    return self.location.origin + BASE_PATH + path;
}

// ─── Assets to pre-cache on SW install ────────────────────────────────────
const PRECACHE_STATIC = [
    abs('assets/icons/icon-192.png'),
    abs('assets/icons/icon-512.png'),
    abs('assets/icons/apple-touch-icon.png'),
    abs('BCAT logo 2024.png'),
    abs('tesda_logo.png'),
];

const PRECACHE_PAGES = [
    abs('offline.php'),
    abs('index.php'),
];

// ─── Install ───────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
    self.skipWaiting();
    event.waitUntil((async () => {
        const staticCache = await caches.open(CACHE_STATIC);
        await Promise.allSettled(
            PRECACHE_STATIC.map(url => staticCache.add(url).catch(e => console.warn('[SW] Failed to cache:', url, e)))
        );
        const pageCache = await caches.open(CACHE_PAGES);
        await Promise.allSettled(
            PRECACHE_PAGES.map(url => pageCache.add(url).catch(e => console.warn('[SW] Failed to cache:', url, e)))
        );
        console.log('[SW] Installed', APP_VERSION);
    })());
});

// ─── Activate ──────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        // Delete old caches
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter(k => k !== CACHE_STATIC && k !== CACHE_PAGES)
                .map(k => { console.log('[SW] Deleting old cache:', k); return caches.delete(k); })
        );
        await self.clients.claim();
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach(c => c.postMessage({ type: 'SW_UPDATED', version: APP_VERSION }));
        console.log('[SW] Activated', APP_VERSION);
    })());
});

// ─── Fetch ─────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET, skip chrome-extension, skip browser-sync
    if (request.method !== 'GET') return;
    if (!url.protocol.startsWith('http')) return;

    const isSameOrigin = url.origin === self.location.origin;
    const isCacheableCDN = [
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'cdn.datatables.net',
        'code.jquery.com'
    ].some(domain => url.hostname.includes(domain));

    if (!isSameOrigin && !isCacheableCDN) return;

    // Static assets + CDN → CacheFirst
    const isStaticAsset = /\.(css|js|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|webp|ico)(\?.*)?$/i.test(url.pathname);
    if (isStaticAsset || isCacheableCDN) {
        event.respondWith(cacheFirst(request, CACHE_STATIC));
        return;
    }

    // Only intercept requests within our app scope
    if (!url.pathname.startsWith(BASE_PATH)) return;

    // PHP pages → NetworkFirst
    event.respondWith(networkFirst(request));
});

// ─── CacheFirst ────────────────────────────────────────────────────────────
async function cacheFirst(request, cacheName) {
    const cache  = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        // Background refresh
        fetch(request).then(r => { if (r && r.ok) cache.put(request, r.clone()); }).catch(() => {});
        return cached;
    }
    try {
        const response = await fetch(request);
        if (response && response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        return new Response('Asset not available offline.', { status: 503 });
    }
}

// ─── NetworkFirst ──────────────────────────────────────────────────────────
async function networkFirst(request) {
    try {
        const response = await fetch(request, { signal: AbortSignal.timeout(8000) });
        if (response && response.ok) {
            const cache = await caches.open(CACHE_PAGES);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        // Offline fallback
        const offline = await caches.match(abs('offline.php'));
        return offline || new Response(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Offline</title></head><body style="font-family:sans-serif;text-align:center;padding:3rem"><h1>📶 You\'re Offline</h1><p>Please check your connection and try again.</p><button onclick="location.reload()">Retry</button></body></html>',
            { headers: { 'Content-Type': 'text/html' }, status: 503 }
        );
    }
}

// ─── Background Sync ───────────────────────────────────────────────────────
self.addEventListener('sync', event => {
    if (event.tag === SYNC_TAG) event.waitUntil(replayQueue());
});

async function replayQueue() {
    const queue = await readQueue();
    const successful = [];
    for (const item of queue) {
        try {
            const res = await fetch(item.url, {
                method:  item.method,
                headers: item.headers,
                body:    item.body,
            });
            if (res.ok) {
                successful.push(item.id);
                const clients = await self.clients.matchAll({ type: 'window' });
                clients.forEach(c => c.postMessage({ type: 'SYNC_SUCCESS', label: item.label || 'Data synchronized' }));
            }
        } catch { /* retry later */ }
    }
    if (successful.length) await removeFromQueue(successful);
}

// ─── IndexedDB Queue ───────────────────────────────────────────────────────
const DB_NAME    = 'bcat-sync-queue';
const DB_VERSION = 1;

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = e => e.target.result.createObjectStore('requests', { keyPath: 'id', autoIncrement: true });
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

async function readQueue() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx  = db.transaction('requests', 'readonly');
        const req = tx.objectStore('requests').getAll();
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
    });
}

async function removeFromQueue(ids) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const tx    = db.transaction('requests', 'readwrite');
        const store = tx.objectStore('requests');
        ids.forEach(id => store.delete(id));
        tx.oncomplete = resolve;
        tx.onerror    = () => reject(tx.error);
    });
}

// ─── Message handler ───────────────────────────────────────────────────────
self.addEventListener('message', event => {
    if (!event.data) return;
    if (event.data.type === 'QUEUE_REQUEST') {
        (async () => {
            const db = await openDB();
            const tx = db.transaction('requests', 'readwrite');
            tx.objectStore('requests').add({
                url:       event.data.url,
                method:    event.data.method,
                headers:   event.data.headers,
                body:      event.data.body,
                label:     event.data.label,
                timestamp: Date.now()
            });
        })();
    }
    if (event.data.type === 'SKIP_WAITING') self.skipWaiting();
});
