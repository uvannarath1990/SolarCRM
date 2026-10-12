'use strict';

// ─── CSRF token (injected via <meta name="csrf-token"> in header.php) ─────────
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ─── apiFetch: wraps fetch with CSRF + JSON defaults ──────────────────────────
async function apiFetch(url, opts = {}) {
    const base = document.querySelector('meta[name="base-url"]')?.content ?? '';
    const options = {
        method: opts.body != null ? 'POST' : 'GET',
        ...opts,
        headers: {
            'Content-Type':  'application/json',
            'X-CSRF-Token':  CSRF_TOKEN,
            'X-Requested-With': 'XMLHttpRequest',
            ...(opts.headers ?? {}),
        },
    };
    const res = await fetch(base + url, options);
    if (res.status === 401) { window.location.href = base + '/login'; return {}; }
    if (res.status === 403) { showToast('danger', 'Access denied.'); return {}; }
    return res.json().catch(() => ({}));
}

// ─── Bootstrap toast helper ───────────────────────────────────────────────────
function showToast(type, message) {
    const container = document.getElementById('toastContainer') ?? (() => {
        const el = document.createElement('div');
        el.id    = 'toastContainer';
        el.style.cssText = 'position:fixed;top:70px;right:16px;z-index:9999;min-width:260px';
        document.body.appendChild(el);
        return el;
    })();

    const id   = 'toast-' + Date.now();
    const html = `
        <div id="${id}" class="toast align-items-center text-white bg-${type} border-0 mb-2" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`;
    container.insertAdjacentHTML('beforeend', html);
    const toastEl = new bootstrap.Toast(document.getElementById(id), { delay: 4000 });
    toastEl.show();
    document.getElementById(id).addEventListener('hidden.bs.toast', () => document.getElementById(id)?.remove());
}

// ─── Auto-dismiss flash alerts after 4 s ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#flashContainer .alert').forEach(el => {
        setTimeout(() => el && (el.style.display = 'none'), 4000);
    });
});
