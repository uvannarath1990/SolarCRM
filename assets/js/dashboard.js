'use strict';

const STAGE_COLORS = {
    'New':                    '#6c757d',
    'Contacted':              '#17a2b8',
    'Visit Scheduled':        '#fd7e14',
    'Site Visit in Progress': '#007bff',
    'Site Visit Completed':   '#20c997',
    'Quoted':                 '#6f42c1',
    'Won':                    '#28a745',
    'Lost':                   '#dc3545',
    'On Hold':                '#ffc107',
};

document.addEventListener('DOMContentLoaded', async () => {
    const data = await apiFetch('/api/dashboard-stats');
    if (!data || !data.stages) return;

    // ── Won / Lost by month ────────────────────────────────────────────────────
    const wlCanvas = document.getElementById('wonLostChart');
    if (wlCanvas && Array.isArray(data.wonLostMonthly)) {
        const rows = data.wonLostMonthly;

        if (rows.length === 0) {
            wlCanvas.closest('.card-body').innerHTML =
                '<div class="text-muted small py-3 text-center">No leads have been won or lost yet.</div>';
        } else {
            const fmtMonth = (mo) => {
                const [y, m] = mo.split('-');
                return new Date(y, m - 1, 1)
                    .toLocaleString('en-GB', { month: 'short', year: '2-digit' });
            };

            // Build a gap-free month axis spanning both series, so a month with no
            // activity shows as a dip rather than being skipped over by the line.
            const created = {};
            (data.createdMonthly || []).forEach(r => { created[r.mo] = Number(r.cnt); });

            const allMonths = [...new Set([...rows.map(r => r.mo), ...Object.keys(created)])].sort();
            const months    = [];
            if (allMonths.length) {
                const [sy, sm] = allMonths[0].split('-').map(Number);
                const [ey, em] = allMonths[allMonths.length - 1].split('-').map(Number);
                for (let y = sy, m = sm; y < ey || (y === ey && m <= em); m === 12 ? (m = 1, y++) : m++) {
                    months.push(y + '-' + String(m).padStart(2, '0'));
                }
            }

            const byMonth = {};
            rows.forEach(r => { byMonth[r.mo] = r; });

            const won       = months.map(m => Number(byMonth[m]?.won  ?? 0));
            const lost      = months.map(m => Number(byMonth[m]?.lost ?? 0));
            const createdBy = months.map(m => created[m] ?? 0);

            const summary = document.getElementById('wonLostSummary');
            if (summary) {
                const tw = won.reduce((a, b) => a + b, 0);
                const tl = lost.reduce((a, b) => a + b, 0);
                const closed = tw + tl;
                summary.innerHTML =
                    '<span class="text-success fw-semibold">' + tw + '</span> won · ' +
                    '<span class="text-danger fw-semibold">' + tl + '</span> lost' +
                    (closed ? ' · ' + Math.round(tw / closed * 100) + '% win rate' : '');
            }

            const lineStyle = (color) => ({
                borderColor:          color,
                backgroundColor:      color,
                pointBackgroundColor: color,
                borderWidth:      2,
                tension:          0.35,
                pointRadius:      4,
                pointHoverRadius: 6,
                fill:             false,
            });

            new Chart(wlCanvas, {
                type: 'line',
                data: {
                    labels: months.map(fmtMonth),
                    datasets: [
                        { label: 'Won',  data: won,  ...lineStyle(STAGE_COLORS['Won'])  },
                        { label: 'Lost', data: lost, ...lineStyle(STAGE_COLORS['Lost']) },
                    ],
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                footer: (items) => {
                                    const i      = items[0].dataIndex;
                                    const closed = won[i] + lost[i];
                                    const lines  = ['Leads created: ' + createdBy[i],
                                                    'Closed this month: ' + closed];
                                    if (closed) {
                                        lines.push('Win rate: ' + Math.round(won[i] / closed * 100) + '%');
                                    }
                                    return lines;
                                },
                            },
                        },
                    },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                },
            });
        }
    }

    // ── Stage doughnut ─────────────────────────────────────────────────────────
    const stageCanvas = document.getElementById('stageChart');
    if (stageCanvas && data.stages.length > 0) {
        new Chart(stageCanvas, {
            type: 'doughnut',
            data: {
                labels:   data.stages.map(r => r.stage),
                datasets: [{
                    data:            data.stages.map(r => r.cnt),
                    backgroundColor: data.stages.map(r => STAGE_COLORS[r.stage] ?? '#999'),
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'right' } },
            },
        });
    }

    // ── Weekly bar ─────────────────────────────────────────────────────────────
    const weekCanvas = document.getElementById('weekChart');
    if (weekCanvas && data.weeks.length > 0) {
        new Chart(weekCanvas, {
            type: 'bar',
            data: {
                labels:   data.weeks.map(r => 'Wk ' + String(r.yw).slice(-2)),
                datasets: [{
                    label:           'Leads Created',
                    data:            data.weeks.map(r => r.cnt),
                    backgroundColor: '#0d6efd',
                    borderRadius:    4,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales:  { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    // ── Operations completed per month ────────────────────────────────────────
    const opsCanvas = document.getElementById('opsChart');
    if (opsCanvas) {
        const opsData = data.opsMonthly ?? [];
        new Chart(opsCanvas, {
            type: 'bar',
            data: {
                labels:   opsData.length > 0 ? opsData.map(r => r.mo) : ['No data'],
                datasets: [{
                    label:           'Operations Done',
                    data:            opsData.length > 0 ? opsData.map(r => r.ct) : [0],
                    backgroundColor: '#20c997',
                    borderRadius:    4,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales:  { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }

    // ── Rep performance (admin only) ───────────────────────────────────────────
    const repCanvas = document.getElementById('repChart');
    if (repCanvas && data.reps && data.reps.length > 0) {
        new Chart(repCanvas, {
            type: 'bar',
            data: {
                labels:   data.reps.map(r => r.name),
                datasets: [
                    {
                        label:           'Leads',
                        data:            data.reps.map(r => r.total),
                        backgroundColor: '#6c757d',
                        borderRadius:    4,
                        yAxisID:         'yLeads',
                    },
                    {
                        label:           'Pipeline (₹)',
                        data:            data.reps.map(r => r.pipeline ?? 0),
                        backgroundColor: '#0d6efd',
                        borderRadius:    4,
                        yAxisID:         'yPipeline',
                    },
                ],
            },
            options: {
                responsive: true,
                scales: {
                    yLeads:    { type: 'linear', position: 'left',  beginAtZero: true, ticks: { precision: 0 } },
                    yPipeline: { type: 'linear', position: 'right', beginAtZero: true },
                },
            },
        });
    }

    // ── Tele Caller performance (admin/manager only) ───────────────────────────
    const tcCanvas = document.getElementById('tcChart');
    if (tcCanvas && data.tcs && data.tcs.length > 0) {
        new Chart(tcCanvas, {
            type: 'bar',
            data: {
                labels:   data.tcs.map(r => r.name),
                datasets: [
                    {
                        label:           'Total Leads',
                        data:            data.tcs.map(r => r.total),
                        backgroundColor: '#6c757d',
                        borderRadius:    4,
                    },
                    {
                        label:           'Won',
                        data:            data.tcs.map(r => r.won ?? 0),
                        backgroundColor: '#28a745',
                        borderRadius:    4,
                    },
                    {
                        label:           'Lost',
                        data:            data.tcs.map(r => r.lost ?? 0),
                        backgroundColor: '#dc3545',
                        borderRadius:    4,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            },
        });
    }
});
