'use strict';

(async function initPush() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    // Register service worker
    let reg;
    try {
        reg = await navigator.serviceWorker.register(window.BASE_URL + '/sw.js', {
            scope: window.BASE_URL + '/',
        });
    } catch (err) {
        return;
    }

    // Fetch VAPID public key
    let vapidKey;
    try {
        const res = await fetch(window.BASE_URL + '/push/vapid-public-key', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;
        const json = await res.json();
        vapidKey = json.publicKey;
    } catch (_) { return; }

    if (!vapidKey) return;

    // Wire up the subscribe button in the header
    const btn = document.getElementById('pushSubscribeBtn');
    if (!btn) return;

    // Check current subscription state
    const existingSub = await reg.pushManager.getSubscription();
    updateBtn(btn, !!existingSub);

    btn.addEventListener('click', async () => {
        const current = await reg.pushManager.getSubscription();

        if (current) {
            // Unsubscribe
            await current.unsubscribe();
            await apiFetch('/push/unsubscribe', {
                method: 'POST',
                body: JSON.stringify({ endpoint: current.endpoint }),
            });
            updateBtn(btn, false);
            showToast('success', 'Push notifications disabled.');
        } else {
            // Request permission and subscribe
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                showToast('warning', 'Notification permission denied.');
                return;
            }
            try {
                const sub = await reg.pushManager.subscribe({
                    userVisibleOnly:      true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey),
                });
                await apiFetch('/push/subscribe', {
                    method: 'POST',
                    body: JSON.stringify(sub.toJSON()),
                });
                updateBtn(btn, true);
                showToast('success', 'Push notifications enabled!');
            } catch (_) {
                showToast('danger', 'Could not enable push notifications.');
            }
        }
    });

    // Check reminders on page load (non-blocking)
    fetch(window.BASE_URL + '/push/check-reminders', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => {});
})();

function updateBtn(btn, isSubscribed) {
    btn.title = isSubscribed ? 'Disable push notifications' : 'Enable push notifications';
    btn.querySelector('i').className = isSubscribed
        ? 'bi bi-bell-fill fs-6 text-warning'
        : 'bi bi-bell-slash fs-6';
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}
