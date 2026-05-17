const CACHE_VERSION = 'life-pwa-v2';
const APP_SHELL_CACHE = `${CACHE_VERSION}-shell`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

const APP_SHELL_URLS = [
  '/offline.html',
  '/manifest.webmanifest',
  '/favicon.svg',
  '/branding/life-logo-light.svg',
  '/branding/life-logo-dark.svg',
  '/pwa/icon-192.png',
  '/pwa/icon-512.png',
  '/pwa/apple-touch-icon.png',
];

const WARM_NAVIGATION_URLS = [
  '/',
  '/about',
  '/directory',
  '/events',
  '/articles',
  '/classifieds',
  '/vouchers',
  '/advertise',
  '/transport',
  '/search',
  '/faults',
];

const PUBLIC_NAVIGATION_PATHS = [
  '/',
  '/about',
  '/directory',
  '/events',
  '/articles',
  '/classifieds',
  '/vouchers',
  '/advertise',
  '/add-listing',
  '/transport',
  '/search',
  '/faults',
  '/contact-us',
  '/terms-and-conditions',
  '/privacy-policy',
  '/staff-signup',
  '/basket',
  '/checkout',
];

const STATIC_ASSET_PREFIXES = [
  '/build/',
  '/branding/',
  '/pwa/',
  '/storage/',
];

const isPublicNavigation = (url) => {
  if (url.origin !== self.location.origin) return false;
  return PUBLIC_NAVIGATION_PATHS.some((path) => url.pathname === path || url.pathname.startsWith(`${path}/`));
};

const shouldCacheAsset = (url) => {
  if (url.origin === self.location.origin) {
    return STATIC_ASSET_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))
      || url.pathname === '/favicon.svg'
      || url.pathname === '/manifest.webmanifest';
  }

  return url.hostname === 'fonts.bunny.net';
};

const cacheResponse = async (cacheName, request, response) => {
  if (!response || !response.ok) return;
  const responseForCache = response.clone();
  const cache = await caches.open(cacheName);
  await cache.put(request, responseForCache);
};

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(APP_SHELL_CACHE)
      .then(async (cache) => {
        await cache.addAll(APP_SHELL_URLS);
        await Promise.allSettled(
          WARM_NAVIGATION_URLS.map(async (url) => {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (response.ok) await cache.put(url, response);
          })
        );
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.map((key) => (key.startsWith('life-pwa-') && ![APP_SHELL_CACHE, RUNTIME_CACHE].includes(key) ? caches.delete(key) : null))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (!['http:', 'https:'].includes(url.protocol)) return;

  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (isPublicNavigation(url)) {
            event.waitUntil(cacheResponse(RUNTIME_CACHE, request, response));
          }
          return response;
        })
        .catch(async () => {
          const cached = await caches.match(request);
          if (cached) return cached;

          const shellMatch = await caches.match(url.pathname);
          if (shellMatch) return shellMatch;

          return caches.match('/offline.html');
        })
    );
    return;
  }

  if (shouldCacheAsset(url)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fresh = fetch(request)
          .then((response) => {
            event.waitUntil(cacheResponse(RUNTIME_CACHE, request, response));
            return response;
          })
          .catch(() => cached);

        return cached || fresh;
      })
    );
  }
});

const defaultNotificationUrl = '/';

const vibrationPatterns = {
  none: [],
  short: [120],
  double: [90, 70, 90],
  urgent: [180, 90, 180, 90, 240],
};

const notifyOpenClients = async (payload) => {
  const clientList = await clients.matchAll({ type: 'window', includeUncontrolled: true });
  clientList.forEach((client) => {
    client.postMessage(payload);
  });
};

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload = {};
  try {
    payload = event.data.json();
  } catch (_) {
    payload = {
      title: 'Life@ update',
      body: event.data.text(),
    };
  }

  const title = payload.title || 'Life@ update';
  const actions = Array.isArray(payload.actions)
    ? payload.actions
        .filter((action) => action && action.action && action.title)
        .slice(0, 2)
        .map((action) => ({
          action: action.action,
          title: action.title,
          ...(action.icon ? { icon: action.icon } : {}),
        }))
    : [];

  const actionUrls = Array.isArray(payload.actions)
    ? payload.actions.reduce((urls, action) => {
        if (action?.action && action?.url) urls[action.action] = action.url;
        return urls;
      }, {})
    : {};

  const options = {
    body: payload.body || 'Open Life@ for the latest local update.',
    icon: payload.icon || '/pwa/icon-192.png',
    badge: payload.badge || '/pwa/favicon-32x32.png',
    ...(payload.image ? { image: payload.image } : {}),
    tag: payload.tag || 'life-update',
    renotify: Boolean(payload.renotify) && !Boolean(payload.silent),
    requireInteraction: Boolean(payload.requireInteraction),
    silent: Boolean(payload.silent),
    timestamp: Number(payload.timestamp) || Date.now(),
    actions,
    ...(!payload.silent && payload.vibration && vibrationPatterns[payload.vibration]?.length ? { vibrate: vibrationPatterns[payload.vibration] } : {}),
    data: {
      url: payload.url || defaultNotificationUrl,
      actionUrls,
      playTone: Boolean(payload.playTone) && !Boolean(payload.silent),
      tone: payload.tone || 'chime',
      ...(payload.data || {}),
    },
  };

  event.waitUntil(
    Promise.all([
      self.registration.showNotification(title, options),
      options.data.playTone ? notifyOpenClients({ type: 'life:push-tone', tone: options.data.tone }) : Promise.resolve(),
    ])
  );
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const actionUrl = event.action ? event.notification.data?.actionUrls?.[event.action] : null;
  const targetUrl = new URL(actionUrl || event.notification.data?.url || defaultNotificationUrl, self.location.origin).href;

  event.waitUntil(
    clients
      .matchAll({ type: 'window', includeUncontrolled: true })
      .then((clientList) => {
        const existingClient = clientList.find((client) => client.url === targetUrl && 'focus' in client);
        if (existingClient) return existingClient.focus();
        if (clients.openWindow) return clients.openWindow(targetUrl);
        return null;
      })
  );
});
