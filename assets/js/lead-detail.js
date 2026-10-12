'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Activity log form ──────────────────────────────────────────────────────
    const actForm = document.getElementById('activityForm');
    if (actForm) {
        actForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd      = new FormData(actForm);
            const leadId  = fd.get('lead_id');
            const type    = fd.get('type');
            const notes   = fd.get('notes');

            const res = await apiFetch('/activities/save', {
                method: 'POST',
                body: JSON.stringify({ lead_id: parseInt(leadId), type, notes }),
            });

            if (res.ok) {
                const feed  = document.getElementById('activitiesFeed');
                const entry = document.createElement('div');
                entry.className = 'd-flex gap-2 mb-2 small';
                entry.innerHTML = `
                    <div class="text-muted text-nowrap">${res.occurred_at}</div>
                    <div>
                        <span class="badge bg-light text-dark text-capitalize me-1">${res.type}</span>
                        <strong>${escHtml(res.user_name)}</strong>
                        ${res.notes ? '— ' + escHtml(res.notes) : ''}
                    </div>`;
                feed.prepend(entry);
                actForm.querySelector('[name="notes"]').value = '';
            } else {
                showToast('danger', res.error ?? 'Could not log activity.');
            }
        });
    }

    // ── Reminders form ─────────────────────────────────────────────────────────
    const remForm = document.getElementById('reminderForm');
    if (remForm) {
        remForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd      = new FormData(remForm);
            const res     = await apiFetch('/reminders/save', {
                method: 'POST',
                body: JSON.stringify({
                    lead_id:   parseInt(fd.get('lead_id')),
                    remind_at: fd.get('remind_at'),
                    message:   fd.get('message'),
                }),
            });

            if (res.ok) {
                const list = document.getElementById('remindersList');
                document.getElementById('noReminders')?.remove();
                const item = document.createElement('div');
                item.id        = 'reminder-' + res.id;
                item.className = 'd-flex align-items-center gap-2 mb-2 small';
                item.innerHTML = `
                    <button class="btn btn-sm btn-outline-success py-0 mark-done-btn" data-id="${res.id}">
                        <i class="bi bi-circle"></i>
                    </button>
                    <span class="text-nowrap text-muted">${escHtml(res.remind_at)}</span>
                    <span>${escHtml(res.message)}</span>`;
                list.appendChild(item);
                remForm.reset();
                attachMarkDone(item.querySelector('.mark-done-btn'));
            } else {
                showToast('danger', res.error ?? 'Could not add reminder.');
            }
        });
    }

    // Mark-done buttons (existing + newly added)
    document.querySelectorAll('.mark-done-btn').forEach(attachMarkDone);

    function attachMarkDone(btn) {
        btn.addEventListener('click', async function () {
            const id  = parseInt(this.dataset.id);
            const res = await apiFetch('/reminders/done', {
                method: 'POST',
                body: JSON.stringify({ id }),
            });
            if (res.ok) {
                const row = document.getElementById('reminder-' + id);
                if (res.is_done) {
                    row.classList.add('text-decoration-line-through', 'text-muted');
                    this.className = this.className.replace('btn-outline-success', 'btn-outline-secondary');
                    this.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
                } else {
                    row.classList.remove('text-decoration-line-through', 'text-muted');
                    this.className = this.className.replace('btn-outline-secondary', 'btn-outline-success');
                    this.innerHTML = '<i class="bi bi-circle"></i>';
                }
            }
        });
    }
});

// XSS-safe string escape for JS-built HTML
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}
