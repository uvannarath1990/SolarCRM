'use strict';

self.addEventListener('push', event => {
    let data = { title: 'KRE Solar', body: '', url: '/employee/today' };
    try { data = { ...data, ...event.data.json() }; } catch (_) {}

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body:    data.body,
            icon:    '/employee/assets/images/icon-192.png',
            badge:   '/employee/assets/images/icon-72.png',
            tag:     data.tag ?? 'kre-notif',
            renotify: true,
            data:    { url: data.url },
        })
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/employee/today';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
            for (const client of list) {
                if (client.url.includes('/employee') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
