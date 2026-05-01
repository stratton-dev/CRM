// Stratton CRM — Web Push Service Worker
// Handles push events and notification clicks when the app is in background/closed.

self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload;
  try {
    payload = event.data.json();
  } catch {
    payload = { title: 'Stratton CRM', body: event.data.text() };
  }

  const title   = payload.title ?? 'Stratton CRM';
  const options = {
    body:    payload.body ?? '',
    icon:    '/icons/icon-192x192.png',
    badge:   '/icons/badge-72x72.png',
    tag:     payload.data?.type === 'chat'
               ? `chat-${payload.data.conversationId}`
               : 'crm-notification',
    renotify: true,
    data:    payload.data ?? {},
    actions: payload.data?.type === 'chat'
      ? [{ action: 'open', title: 'Otwórz czat' }]
      : [],
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data ?? {};
  let url = '/';

  if (data.type === 'chat') {
    url = '/app/dashboard?chat=1&conv=' + (data.conversationId ?? '');
  } else if (data.type === 'notification') {
    url = '/app/notifications';
  } else if (data.type === 'email') {
    url = '/app/mailbox';
  }

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      for (const client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.postMessage({ type: 'SW_NAVIGATE', url });
          return client.focus();
        }
      }
      return clients.openWindow(self.location.origin + url);
    })
  );
});
