/* ============================================================================
   Location heartbeat.
   Only loaded for users with tracking switched on.

   A browser will not give position to a page that is closed or hidden, so this
   is explicitly a foreground heartbeat: it pings on load, then on an interval,
   and it pauses whenever the tab is hidden. The gaps this leaves are real and
   the admin map shows them as gaps rather than guessing across them.
   ========================================================================== */
(function () {
    'use strict';

    if (!navigator.geolocation) return;

    var cfg       = window.KRE_TRACK || {};
    var everyMs   = Math.max(5, cfg.every || 60) * 60 * 1000;
    var lastSent  = 0;
    var stopped   = false;

    var KEY = 'kre_last_ping';

    function lastPing() {
        try { return parseInt(sessionStorage.getItem(KEY) || '0', 10) || 0; }
        catch (e) { return lastSent; }
    }

    function markPing(t) {
        lastSent = t;
        try { sessionStorage.setItem(KEY, String(t)); } catch (e) {}
    }

    function csrf() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function send(pos, source) {
        var body = new URLSearchParams();
        body.set('lat', pos.coords.latitude);
        body.set('lng', pos.coords.longitude);
        body.set('accuracy', Math.round(pos.coords.accuracy || 0));
        body.set('source', source || 'heartbeat');
        body.set('csrf_token', csrf());

        fetch(window.BASE_URL + '/location/ping', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            // The server tells us to stand down if tracking was switched off
            // mid-session, so we stop asking for position entirely.
            if (d && d.stop) { stopped = true; }
        })
        .catch(function () {});
    }

    function capture(source, force) {
        if (stopped) return;

        var now = Date.now();
        if (!force && (now - lastPing()) < everyMs) return;

        // Mark before the fix resolves: a slow GPS lock shouldn't let a second
        // timer tick queue another request behind this one.
        markPing(now);

        navigator.geolocation.getCurrentPosition(
            function (pos) { send(pos, source); },
            function () { /* denied or unavailable — try again next tick */ },
            { enableHighAccuracy: false, timeout: 20000, maximumAge: 300000 }
        );
    }

    capture('heartbeat', false);

    setInterval(function () {
        if (document.visibilityState === 'visible') capture('heartbeat', false);
    }, 60 * 1000);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') capture('heartbeat', false);
    });

    // Let other flows (site visit, task completion) force a capture.
    window.kreCaptureLocation = function (source) { capture(source || 'task', true); };
})();
