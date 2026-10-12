/* ============================================================================
   Team location map.

   Uses circle markers rather than Leaflet's default pin images, so there are no
   marker icon files to load and nothing extra to allow through the CSP.

   Each person keeps a stable colour so they stay recognisable between visits,
   and their name is pinned beside the dot. Staleness is shown by fading the
   marker, not by changing its hue — otherwise everyone with equally old data
   looks identical, which is when you most need to tell them apart.

   The accuracy radius is drawn around each position: a 2km tower fix and a
   15m GPS fix should not look alike.
   ========================================================================== */
(function () {
    'use strict';

    var data = window.KRE_MAP;
    var el   = document.getElementById('map');
    if (!el || !data || typeof L === 'undefined') return;

    var map = L.map(el, { zoomControl: true, attributionControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    // Identity is carried by colour, freshness by opacity and the label text.
    // Encoding both in the dot colour made every rep look the same when their
    // data was equally stale — which is exactly when you need to tell them apart.
    var PALETTE = [
        '#2563EB', '#DC2626', '#059669', '#D97706', '#7C3AED',
        '#DB2777', '#0891B2', '#65A30D', '#EA580C', '#4F46E5'
    ];

    function colourFor(userId) {
        return PALETTE[Math.abs(parseInt(userId, 10) || 0) % PALETTE.length];
    }

    // Stale positions fade rather than change hue, so the person stays recognisable.
    function freshness(ageMin) {
        if (ageMin === null || ageMin === undefined) return { op: 0.35, stale: true };
        if (ageMin <= 60)  return { op: 1,    stale: false };
        if (ageMin <= 240) return { op: 0.72, stale: true };
        return { op: 0.45, stale: true };
    }

    function ageText(m) {
        if (m === null || m === undefined) return 'no data';
        if (m < 60)   return m + ' min ago';
        if (m < 1440) return Math.floor(m / 60) + ' hr ago';
        return 'earlier';
    }

    // Everything the slider can change lives in one layer, so a redraw is a
    // clear-and-draw rather than rebuilding the map.
    var repLayer = L.layerGroup().addTo(map);
    var bounds   = [];

    function drawReps(reps, fitAfter) {
        repLayer.clearLayers();
        bounds = [];

    (reps || []).forEach(function (r) {
        if (r.lat === null || r.lng === null) return;

        var lat = parseFloat(r.lat);
        var lng = parseFloat(r.lng);
        var age = r.age_min === null ? null : parseInt(r.age_min, 10);
        var col = colourFor(r.user_id);
        var fr  = freshness(age);

        if (r.accuracy_m) {
            L.circle([lat, lng], {
                radius: parseInt(r.accuracy_m, 10),
                color: col, weight: 1, opacity: 0.30 * fr.op,
                fillColor: col, fillOpacity: 0.08 * fr.op
            }).addTo(repLayer);
        }

        var marker = L.circleMarker([lat, lng], {
            radius: 9, color: '#fff', weight: 2.5,
            fillColor: col, fillOpacity: fr.op,
            opacity: fr.op
        });

        // The name rides next to the dot permanently — no hover, no tap needed,
        // which also makes it work on a phone.
        // Show the clock time of the fix as well as its age: at a past moment
        // "12 min ago" alone tells you nothing about when that actually was.
        var stamp = (r.captured_at || '').substring(11, 16);

        marker.bindTooltip(
            '<span class="ml-name">' + escapeHtml(firstName(r.name)) + '</span>' +
            '<span class="ml-age">' + escapeHtml(stamp ? stamp + ' · ' + ageText(age) : ageText(age)) + '</span>',
            {
                permanent: true,
                direction: 'right',
                offset: [10, 0],
                className: 'map-label' + (fr.stale ? ' is-stale' : ''),
                opacity: 1
            }
        );

        marker
        .bindPopup(
            '<strong>' + escapeHtml(r.name) + '</strong><br>' +
            escapeHtml(ageText(age)) +
            (r.accuracy_m ? ' · ±' + parseInt(r.accuracy_m, 10) + 'm' : '') +
            (r.source && r.source !== 'heartbeat'
                ? '<br>' + escapeHtml(r.source) + (r.lead_name ? ': ' + escapeHtml(r.lead_name) : '')
                : '')
        )
        .addTo(repLayer);

        marker.on('click', function () { marker.openPopup(); });

        bounds.push([lat, lng]);
    });

        if (fitAfter && bounds.length) {
            if (bounds.length === 1) map.setView(bounds[0], 14);
            else map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
        }
    }

    drawReps(data.reps, false);

    var trail = (data.trail || []).filter(function (p) { return p.lat !== null && p.lng !== null; });

    var trailColour = (data.selected ? colourFor(data.selected) : '#B45309');

    if (trail.length > 1) {
        var pts = trail.map(function (p) { return [parseFloat(p.lat), parseFloat(p.lng)]; });

        // Dashed on purpose: these are sampled positions, not a travelled route.
        L.polyline(pts, {
            color: trailColour, weight: 2.5, opacity: 0.8, dashArray: '6 5'
        }).addTo(map);

        trail.forEach(function (p, i) {
            var isEvent = p.source && p.source !== 'heartbeat';
            L.circleMarker([parseFloat(p.lat), parseFloat(p.lng)], {
                radius: isEvent ? 6 : 4,
                color: '#fff', weight: 1.5,
                fillColor: isEvent ? '#059669' : trailColour,
                fillOpacity: 1
            })
            .bindPopup(
                escapeHtml((p.captured_at || '').substring(11, 16)) + ' · ' +
                escapeHtml(isEvent ? p.source : 'ping') +
                (p.lead_name ? '<br>' + escapeHtml(p.lead_name) : '')
            )
            .addTo(map);
            bounds.push(pts[i]);
        });
    }

    function fitAll() {
        if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
        } else {
            map.setView([9.9816, 76.2999], 11); // Kochi, when there is nothing to show
        }
    }

    fitAll();

    // Leaflet measures the container when the map is created. If that happens
    // before the page has settled — web fonts, the sidebar, a late stylesheet —
    // it fits to the wrong size and the markers land off-screen. Re-measuring
    // once the layout is final and fitting again is the reliable cure.
    function settle() {
        map.invalidateSize();
        fitAll();
    }
    setTimeout(settle, 0);
    window.addEventListener('load', settle);

    // The view stays put while scrubbing — a map that jumps on every drag is
    // impossible to read.

    // ── Time scrubber ─────────────────────────────────────────────────────
    var slider = document.getElementById('timeSlider');
    var label  = document.getElementById('atLabel');
    var hint   = document.getElementById('atHint');
    var timer  = null;

    function hhmm(mins) {
        var h = Math.floor(mins / 60), m = mins % 60;
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    if (slider) {
        slider.addEventListener('input', function () {
            if (label) label.textContent = hhmm(parseInt(slider.value, 10));
            if (hint)  hint.textContent  = 'Positions as at this time';
        });

        // Fetch on release, not on every pixel of the drag.
        slider.addEventListener('change', function () {
            var at = hhmm(parseInt(slider.value, 10));
            var input = document.getElementById('atTime');
            if (input) input.value = at;

            clearTimeout(timer);
            timer = setTimeout(function () {
                var url = BASE_URL + '/api/team-locations?date=' + encodeURIComponent(data.date)
                        + '&time=' + encodeURIComponent(at)
                        + (data.selected ? '&user=' + data.selected : '');
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) { if (d) drawReps(d.reps, false); })
                    .catch(function () {});
            }, 150);
        });
    }

    function firstName(n) {
        var parts = String(n || '').trim().split(/\s+/);
        return parts.length > 1 && parts[0].length <= 12 ? parts[0] : String(n || '');
    }

    function escapeHtml(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
})();
