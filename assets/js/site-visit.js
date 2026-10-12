'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('visitApp');
    if (!app) return;

    const leadId    = parseInt(app.dataset.leadId);
    let   visitId   = app.dataset.visitId ? parseInt(app.dataset.visitId) : null;
    const checkinAt = app.dataset.checkinTime; // ISO datetime string or ''

    // ── Timer ──────────────────────────────────────────────────────────────────
    let timerInterval = null;
    const timerEl     = document.getElementById('visitTimer');

    function startTimer(startEpoch) {
        if (timerInterval) clearInterval(timerInterval);
        timerInterval = setInterval(() => {
            const elapsed = Math.floor(Date.now() / 1000) - startEpoch;
            const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
            const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
            const s = String(elapsed % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = `${h}:${m}:${s}`;
        }, 1000);
    }

    function showInProgress() {
        document.getElementById('stateNotStarted')?.classList.add('d-none');
        document.getElementById('stateInProgressPanel')?.classList.remove('d-none');
    }

    // Resume if visit already active (page reload case)
    if (visitId && checkinAt) {
        const startEpoch = Math.floor(new Date(checkinAt).getTime() / 1000);
        startTimer(startEpoch);
        showInProgress();
        sessionStorage.setItem('visitStart', String(startEpoch));
        sessionStorage.setItem('visitId',    String(visitId));
    }

    // ── Check-in ───────────────────────────────────────────────────────────────
    const btnCheckin = document.getElementById('btnCheckin');
    if (btnCheckin) {
        btnCheckin.addEventListener('click', async () => {
            btnCheckin.disabled   = true;
            btnCheckin.innerHTML  = '<span class="spinner-border spinner-border-sm me-2"></span>Getting location…';

            const getGps = () => new Promise((resolve, reject) => {
                if (!navigator.geolocation) { resolve({ lat: null, lng: null, accuracy: null }); return; }
                navigator.geolocation.getCurrentPosition(
                    pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
                    ()  => resolve({ lat: null, lng: null, accuracy: null }),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });

            const gps = await getGps();
            if (!gps.lat) showToast('warning', 'Location not available — check-in recorded without GPS.');

            const res = await apiFetch('/site-visits/checkin', {
                method: 'POST',
                body: JSON.stringify({ lead_id: leadId, ...gps }),
            });

            if (res.ok) {
                visitId = res.visit_id;
                const epoch = Math.floor(Date.now() / 1000);
                sessionStorage.setItem('visitStart', String(epoch));
                sessionStorage.setItem('visitId',    String(visitId));
                startTimer(epoch);
                showInProgress();
            } else {
                showToast('danger', res.error ?? 'Check-in failed.');
                btnCheckin.disabled  = false;
                btnCheckin.innerHTML = '<i class="bi bi-play-circle me-2"></i>Start Site Visit';
            }
        });
    }

    // ── Check-out ──────────────────────────────────────────────────────────────
    const btnCheckout = document.getElementById('btnCheckout');
    if (btnCheckout) {
        btnCheckout.addEventListener('click', async () => {
            if (!visitId) { showToast('danger', 'No active visit found.'); return; }

            btnCheckout.disabled  = true;
            btnCheckout.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Recording…';

            const getGps = () => new Promise(resolve => {
                if (!navigator.geolocation) { resolve({ lat: null, lng: null, accuracy: null }); return; }
                navigator.geolocation.getCurrentPosition(
                    pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
                    ()  => resolve({ lat: null, lng: null, accuracy: null }),
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });

            const gps        = await getGps();
            const voiceNotes = document.getElementById('voiceNotesTA')?.value ?? '';
            const roofType   = document.getElementById('roofType')?.value ?? '';
            const roofArea   = document.getElementById('roofArea')?.value ?? '';
            const shading    = document.getElementById('shadingNotes')?.value ?? '';

            const res = await apiFetch('/site-visits/checkout', {
                method: 'POST',
                body: JSON.stringify({
                    visit_id:     visitId,
                    lat:          gps.lat,
                    lng:          gps.lng,
                    accuracy:     gps.accuracy,
                    voice_notes:  voiceNotes,
                    roof_type:    roofType,
                    roof_area_sqft: roofArea,
                    shading_notes: shading,
                }),
            });

            if (res.ok) {
                clearInterval(timerInterval);
                sessionStorage.removeItem('visitStart');
                sessionStorage.removeItem('visitId');

                // Show summary
                const summary = document.getElementById('stateSummary');
                const prog    = document.getElementById('stateInProgressPanel');
                if (prog)    prog.classList.add('d-none');
                if (summary) {
                    summary.classList.remove('d-none');
                    const secs = res.duration_secs ?? 0;
                    const h    = Math.floor(secs / 3600);
                    const m    = Math.floor((secs % 3600) / 60);
                    document.getElementById('summDuration').textContent =
                        h > 0 ? `${h}h ${m}m` : `${m}m`;
                    if (gps.lat) {
                        const mapUrl = `https://www.google.com/maps?q=${gps.lat},${gps.lng}`;
                        document.getElementById('summCheckoutMap')?.setAttribute('href', mapUrl);
                    }
                }
            } else {
                showToast('danger', res.error ?? 'Checkout failed.');
                btnCheckout.disabled  = false;
                btnCheckout.innerHTML = '<i class="bi bi-stop-circle me-2"></i>Complete Site Visit';
            }
        });
    }
});
