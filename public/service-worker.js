/* OJMAS service worker — Web Push (Addendum Module 3).
 *
 * Standards only: receives push events and shows a notification with the
 * recorded title/message/destination. It never caches HTML, never executes
 * anything from the payload, and never opens anything but the recorded
 * http(s) destination. Consenting visitors can unsubscribe any time; the
 * worker only shows what was explicitly pushed to it. */

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let data = {};

  try {
    data = event.data ? event.data.json() : {};
  } catch (error) {
    data = {};
  }

  const title = typeof data.title === 'string' ? data.title : 'OJMAS update';
  const options = {
    body: typeof data.body === 'string' ? data.body : '',
    tag: typeof data.tag === 'string' ? data.tag : 'ojmas-push',
    icon: typeof data.icon === 'string' && data.icon.startsWith('https')
      ? data.icon
      : undefined,
    data: { url: typeof data.url === 'string' && /^(https?:\/\/)/.test(data.url) ? data.url : '/' },
    renotify: true,
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const target = (event.notification.data && event.notification.data.url) || '/';

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if ('focus' in client) {
          client.navigate(target).then(() => client.focus());
          return;
        }
      }
      return self.clients.openWindow(target);
    })
  );
});
