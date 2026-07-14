/**
 * gCube PWA Service Worker - Production Ready v1.0.0
 *
 * Cache Strategies:
 * - HTML/Bundle: Stale-while-revalidate (instant load + background update)
 * - Static Assets: Cache-first (CSS, JS, fonts, images)
 * - API: Network-first with timeout (always fresh, fallback to cache)
 * - wp-admin: Network-only (never cache admin)
 *
 * Performance: <100ms for cached content, works offline
 */

const CACHE_VERSION = 'gcube-v1.0.0';
const CACHE_STATIC = `${CACHE_VERSION}-static`;
const CACHE_BUNDLE = `${CACHE_VERSION}-bundle`;
const CACHE_DYNAMIC = `${CACHE_VERSION}-dynamic`;
const NETWORK_TIMEOUT = 3000; // 3 second timeout for network requests

// Static assets to pre-cache on install
// Note: Theme assets are cached dynamically on first request
// Only root-level files are pre-cached to avoid hardcoded theme paths
const STATIC_ASSETS = [
    '/manifest.json',
];

/**
 * Install event: Pre-cache static assets
 */
self.addEventListener('install', event => {
    console.log('[gCube SW] Installing version:', CACHE_VERSION);

    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then(cache => {
                console.log('[gCube SW] Pre-caching static assets');
                return cache.addAll(
                    STATIC_ASSETS.map(url => new Request(url, { cache: 'reload' }))
                ).catch(err => {
                    console.warn('[gCube SW] Some assets failed to cache:', err);
                    return Promise.resolve();
                });
            })
            .then(() => {
                console.log('[gCube SW] Installation complete');
                return self.skipWaiting();
            })
    );
});

/**
 * Activate event: Clean old caches
 */
self.addEventListener('activate', event => {
    console.log('[gCube SW] Activating version:', CACHE_VERSION);

    event.waitUntil(
        caches.keys()
            .then(names => Promise.all(
                names
                    .filter(n => n.startsWith('gcube-') && !n.startsWith(CACHE_VERSION))
                    .map(n => {
                        console.log('[gCube SW] Deleting old cache:', n);
                        return caches.delete(n);
                    })
            ))
            .then(() => {
                console.log('[gCube SW] Claiming clients');
                return self.clients.claim();
            })
    );
});

/**
 * Fetch event: Route requests based on type
 */
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only handle GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Skip non-http(s) requests
    if (!url.protocol.startsWith('http')) {
        return;
    }

    // NEVER cache wp-admin
    if (url.pathname.startsWith('/wp-admin') || url.pathname.startsWith('/wp-login.php')) {
        return;
    }

    // Strategy 1: HTML/PHP pages - Stale-While-Revalidate
    if (url.pathname === '/' || url.pathname.endsWith('.html') || url.pathname.endsWith('.php')) {
        event.respondWith(staleWhileRevalidate(event.request, CACHE_BUNDLE));
        return;
    }

    // Strategy 2: Static assets - Cache-First
    if (url.pathname.match(/\.(css|js|woff|woff2|ttf|eot|otf|jpg|jpeg|png|gif|svg|ico|webp)$/i)) {
        event.respondWith(cacheFirst(event.request, CACHE_STATIC));
        return;
    }

    // Strategy 3a: Batch face render — Stale-While-Revalidate (instant on repeat visits)
    if (url.pathname.endsWith('/render-all')) {
        event.respondWith(staleWhileRevalidate(event.request, CACHE_BUNDLE));
        return;
    }

    // Strategy 3b: Other API endpoints - Network-First with timeout
    if (url.pathname.startsWith('/wp-json/')) {
        event.respondWith(networkFirstWithTimeout(event.request, CACHE_DYNAMIC, NETWORK_TIMEOUT));
        return;
    }

    // Default: Network-first
    event.respondWith(
        fetch(event.request).catch(() => caches.match(event.request))
    );
});

/**
 * Cache-First: Serve from cache, update cache if miss
 */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);
        if (response && response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        console.error('[gCube SW] Fetch failed:', request.url, err);
        throw err;
    }
}

/**
 * Stale-While-Revalidate: Serve cache immediately, update in background
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    // Fetch in background
    const fetchPromise = fetch(request).then(response => {
        if (response && response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(err => {
        console.log('[gCube SW] Background fetch failed:', err);
        return cached;
    });

    // Return cached immediately if available, otherwise wait for network
    return cached || fetchPromise;
}

/**
 * Network-First with Timeout: Fresh data preferred, cache fallback
 */
async function networkFirstWithTimeout(request, cacheName, timeout) {
    const cache = await caches.open(cacheName);

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);

        const response = await fetch(request, { signal: controller.signal });
        clearTimeout(timeoutId);

        if (response && response.ok) {
            // Only cache if not explicitly marked no-cache
            const cacheControl = response.headers.get('Cache-Control');
            if (!cacheControl || !cacheControl.includes('no-cache')) {
                cache.put(request, response.clone());
            }
        }

        return response;
    } catch (err) {
        // Network failed or timeout - try cache
        const cached = await cache.match(request);
        if (cached) {
            console.log('[gCube SW] Network timeout, serving stale:', request.url);

            // Clone response and add stale header
            const cloned = cached.clone();
            const headers = new Headers(cloned.headers);
            headers.set('X-Cache-Status', 'stale');

            return new Response(cloned.body, {
                status: cloned.status,
                statusText: cloned.statusText,
                headers: headers
            });
        }

        throw err;
    }
}

/**
 * Message event: Handle commands from clients
 */
self.addEventListener('message', event => {
    if (!event.data || !event.data.type) {
        return;
    }

    switch (event.data.type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;

        case 'CLEAR_CACHE':
            caches.keys().then(names =>
                Promise.all(
                    names.filter(n => n.startsWith('gcube-')).map(n => caches.delete(n))
                )
            ).then(() => {
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({ success: true });
                }
            });
            break;

        case 'CACHE_SIZE':
            getCacheSize().then(size => {
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({
                        bytes: size,
                        formatted: formatBytes(size)
                    });
                }
            });
            break;

        case 'CACHE_BUNDLE':
            if (event.data.url && event.data.html) {
                caches.open(CACHE_BUNDLE).then(cache => {
                    cache.put(event.data.url, new Response(event.data.html, {
                        headers: { 'Content-Type': 'text/html; charset=utf-8' }
                    }));
                });
            }
            break;
    }
});

/**
 * Get total cache size
 */
async function getCacheSize() {
    if (!('storage' in navigator) || !('estimate' in navigator.storage)) {
        return 0;
    }

    try {
        const estimate = await navigator.storage.estimate();
        return estimate.usage || 0;
    } catch (err) {
        return 0;
    }
}

/**
 * Format bytes for display
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Background Sync: Retry failed requests
 */
self.addEventListener('sync', event => {
    if (event.tag === 'sync-bundle') {
        event.waitUntil(
            fetch('/').then(response => {
                if (response.ok) {
                    return caches.open(CACHE_BUNDLE).then(cache => cache.put('/', response));
                }
            }).catch(err => console.log('[gCube SW] Sync failed:', err))
        );
    }
});

/**
 * Push Notifications
 */
self.addEventListener('push', event => {
    const data = event.data ? event.data.json() : {};

    const options = {
        body: data.body || 'New content available',
        icon: '/wp-content/themes/gcube/assets/images/icon-192.png',
        badge: '/wp-content/themes/gcube/assets/images/badge-72.png',
        vibrate: [200, 100, 200],
        data: { url: data.url || '/', timestamp: Date.now() },
        actions: [
            { action: 'open', title: 'Open' },
            { action: 'close', title: 'Close' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'gCube', options)
    );
});

/**
 * Notification Click
 */
self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'close') {
        return;
    }

    const url = event.notification.data.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Focus existing window if available
                for (let client of clientList) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }

                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

console.log('[gCube SW] Service Worker loaded, version:', CACHE_VERSION);
