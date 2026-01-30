/**
 * Service Worker para notificações push do SISCONDI.
 * Quando o backend enviar um push (ex: nova solicitação de diária), esta função exibe a notificação.
 */
self.addEventListener('push', (event) => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'SISCONDI';
  const options = {
    body: data.body || 'Nova solicitação pendente de assinatura.',
    icon: data.icon || '/favicon/favicon-96x96.png',
    badge: data.badge || '/favicon/favicon-96x96.png',
    tag: data.tag || 'daily-request',
    data: { url: data.url || '/daily-requests' },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/daily-requests';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
      for (const client of windowClients) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          client.navigate(url);
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(url);
      }
    })
  );
});
