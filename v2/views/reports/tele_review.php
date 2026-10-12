<?php require __DIR__ . '/../layout/header.php';

$qs  = fn(array $extra = []) => http_build_query(array_merge(
    ['from' => $from, 'to' => $to, 'tc' => $tcId ?: '', 'source' => $sourceId ?: ''], $extra
));
// Link into the drill-down list for one caller, optionally narrowed to one source
$drill = fn(int $tc, ?int $src = null) => BASE_URL . '/reports/tele-review?' . http_build_query(
    ['from' => $from, 'to' => $to, 'tc' => $tc, 'source' => $src ?? '']
) . '#leadlist';

$sourceLabel = '';
if ($sourceId === -1) $sourceLabel = 'No source';
else foreach ($allSources as $sx) { if ((int)$sx['id'] === $sourceId) $sourceLabel = $sx['name']; }
$csv = fn(string $section) => BASE_URL . '/reports/tele-review/export?' . $qs(['section' => $section]);

$selectedName = '';
foreach ($allTele as $t) { if ((int)$t['id'] === $tcId) $selectedName = $t['name']; }

$totLeads = array_sum(array_column($scorecard, 'leads'));
$totWon   = array_sum(array_column($scorecard, 'won'));
$totOver  = array_sum(array_column($scorecard, 'overdue'));
?>

<div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-headset me-2"></i>Tele Caller Review</h5>
    <span class="text-muted small">
        <?= htmlspecialchars(formatDate($from)) ?> — <?= htmlspecialchars(formatDate($to)) ?>
        · <?= $totLeads ?> leads · <?= $totWon ?> won
        <?php if ($totOver): ?>· <span class="text-danger fw-semibold"><?= $totOver ?> overdue</span><?php endif; ?>
    </span>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/reports/tele-review" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
            </div>
            <div class="col-8 col-md-4">
                <label class="form-label small fw-semibold mb-1">Tele Caller</label>
                <select name="tc" class="form-select form-select-sm">
                    <option value="">— All tele callers —</option>
                    <?php foreach ($allTele as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $tcId === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-md-3">
                <label class="form-label small fw-semibold mb-1">Lead Source</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">— All sources —</option>
                    <option value="-1" <?= $sourceId === -1 ? 'selected' : '' ?>>No source recorded</option>
                    <?php foreach ($allSources as $sx): ?>
                    <option value="<?= (int)$sx['id'] ?>" <?= $sourceId === (int)$sx['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sx['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
        </div>
        <div class="form-text small mt-1">
            Date range filters on <strong>lead created date</strong>.
            Pick a tele caller to see their individual leads at the bottom.
        </div>
    </div>
</form>

<!-- ── Scorecard ───────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-person-badge me-1"></i>Conversion Scorecard</span>
        <a href="<?= $csv('scorecard') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Tele Caller</th>
                    <th class="text-end">Leads</th>
                    <th class="text-end">Contacted</th>
                    <th class="text-end">Visit Scheduled</th>
                    <th class="text-end">Won</th>
                    <th class="text-end">Lost</th>
                    <th class="text-end">Open</th>
                    <th class="text-end" title="Reached Contacted ÷ leads">Contact Rate</th>
                    <th class="text-end" title="Reached Visit Scheduled ÷ leads">Visit Rate</th>
                    <th class="text-end" title="Won ÷ (Won + Lost)">Win Rate</th>
                    <th class="text-end">Overdue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($scorecard as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-end"><?= (int)$r['leads'] ?></td>
                    <td class="text-end"><?= (int)$r['contacted'] ?></td>
                    <td class="text-end"><?= (int)$r['scheduled'] ?></td>
                    <td class="text-end text-success fw-semibold"><?= (int)$r['won'] ?></td>
                    <td class="text-end text-danger"><?= (int)$r['lost'] ?></td>
                    <td class="text-end text-muted"><?= (int)$r['open'] ?></td>
                    <td class="text-end"><?= $r['contact_rate'] ?>%</td>
                    <td class="text-end fw-semibold"><?= $r['visit_rate'] ?>%</td>
                    <td class="text-end">
                        <?= $r['win_rate'] === null ? '<span class="text-muted">—</span>' : $r['win_rate'] . '%' ?>
                    </td>
                    <td class="text-end <?= (int)$r['overdue'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['overdue'] ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Contacted and Visit Scheduled count leads that <em>ever reached</em> that stage, so a Won lead still
        counts at both. Win rate excludes open leads. Overdue counts open leads whose follow-up date has passed.
    </div>
</div>

<!-- ── Tele caller × lead source ───────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-grid-3x3 me-1"></i>Leads by Source</span>
        <a href="<?= $csv('sources') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
    <?php if (empty($matrix['rows'])): ?>
    <div class="card-body text-muted small">No leads in this date range.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="min-width:130px">Tele Caller</th>
                    <?php foreach ($matrix['sources'] as $sc): ?>
                    <th class="text-end" style="min-width:90px"><?= htmlspecialchars($sc['name']) ?></th>
                    <?php endforeach; ?>
                    <th class="text-end table-light" style="min-width:80px">Total</th>
                    <th class="text-end">Won</th>
                    <th class="text-end">Lost</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matrix['rows'] as $mr): ?>
                <tr>
                    <td class="fw-semibold">
                        <a href="<?= $drill((int)$mr['id']) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($mr['name']) ?>
                            <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </td>
                    <?php foreach ($matrix['sources'] as $sc):
                        $cell = $mr['cells'][$sc['id']] ?? null; ?>
                    <td class="text-end">
                        <?php if ($cell && $cell['leads'] > 0): ?>
                        <a href="<?= $drill((int)$mr['id'], (int)$sc['id']) ?>"
                           class="text-decoration-none fw-semibold"
                           title="<?= $cell['won'] ?> won · <?= $cell['lost'] ?> lost — click to list these leads">
                            <?= $cell['leads'] ?>
                        </a>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                    <td class="text-end fw-bold table-light"><?= (int)$mr['total'] ?></td>
                    <td class="text-end text-success fw-semibold"><?= (int)$mr['won'] ?></td>
                    <td class="text-end text-danger"><?= (int)$mr['lost'] ?></td>
                </tr>
            <?php endforeach; ?>
                <tr class="table-light">
                    <td class="fw-bold">All</td>
                    <?php foreach ($matrix['sources'] as $sc): ?>
                    <td class="text-end fw-bold"><?= (int)$sc['total'] ?></td>
                    <?php endforeach; ?>
                    <td class="text-end fw-bold"><?= array_sum(array_column($matrix['rows'], 'total')) ?></td>
                    <td class="text-end fw-bold text-success"><?= array_sum(array_column($matrix['rows'], 'won')) ?></td>
                    <td class="text-end fw-bold text-danger"><?= array_sum(array_column($matrix['rows'], 'lost')) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Click a name to list all that caller's leads, or a number to list just that caller and source.
        Hover a number to see its won/lost split. Columns are ordered by volume.
    </div>
    <?php endif; ?>
</div>

<!-- ── Task performance ────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-list-check me-1"></i>Task Performance</span>
        <a href="<?= $csv('tasks') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Tele Caller</th>
                    <th class="text-end">Assigned</th>
                    <th class="text-end">Done</th>
                    <th class="text-end">Pending</th>
                    <th class="text-end">Overdue</th>
                    <th class="text-end">On Time</th>
                    <th class="text-end">On-Time %</th>
                    <th class="text-end">Avg Days to Close</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($taskStats as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-end"><?= (int)$r['assigned'] ?></td>
                    <td class="text-end"><?= (int)$r['done'] ?></td>
                    <td class="text-end"><?= (int)$r['pending'] ?></td>
                    <td class="text-end <?= (int)$r['overdue'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['overdue'] ?>
                    </td>
                    <td class="text-end text-muted"><?= (int)$r['on_time'] ?></td>
                    <td class="text-end fw-semibold <?= $r['on_time_pct'] !== null && $r['on_time_pct'] < 50 ? 'text-danger' : '' ?>">
                        <?= $r['on_time_pct'] === null ? '<span class="text-muted">—</span>' : $r['on_time_pct'] . '%' ?>
                    </td>
                    <td class="text-end"><?= $r['avg_days'] !== '' ? $r['avg_days'] : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Outcomes ────────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-signpost-split me-1"></i>Recorded Call Outcomes</span>
        <a href="<?= $csv('outcomes') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Tele Caller</th>
                    <th class="text-end">Arranged Site Visit</th>
                    <th class="text-end">Lead Lost</th>
                    <th class="text-end">Closed without an outcome</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($outcomes as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-end text-success fw-semibold"><?= (int)$r['arranged'] ?></td>
                    <td class="text-end text-danger"><?= (int)$r['lost'] ?></td>
                    <td class="text-end <?= (int)$r['none'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['none'] ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Only qualification tasks at stage <strong>New</strong> force an outcome. The last column counts
        tasks closed without one — those give no insight into why the lead moved.
    </div>
</div>

<!-- ── Individual lead tracking ────────────────────────────────────────────── -->
<div class="card mb-4" id="leadlist">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold">
            <i class="bi bi-people me-1"></i>Lead Tracking<?= $selectedName ? ' — ' . htmlspecialchars($selectedName) : '' ?>
            <?php if ($sourceLabel !== ''): ?>
            <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars($sourceLabel) ?></span>
            <?php endif; ?>
        </span>
        <?php if ($tcId > 0): ?>
        <a href="<?= $csv('leads') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>CSV</a>
        <?php endif; ?>
    </div>
    <?php if ($tcId <= 0): ?>
    <div class="card-body text-muted small">
        <i class="bi bi-arrow-up me-1"></i>Select a tele caller in the filter above to list their leads,
        ordered by how long each has been sitting without a stage change.
    </div>
    <?php elseif (empty($leads)): ?>
    <div class="card-body text-muted small">
        No leads for this tele caller<?= $sourceLabel !== '' ? ' from ' . htmlspecialchars($sourceLabel) : '' ?>
        in the selected range.
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="min-width:150px">Lead</th>
                    <th>Stage</th>
                    <th>Source</th>
                    <th>Sales Rep</th>
                    <th class="text-end">Created</th>
                    <th class="text-end">Next Follow-up</th>
                    <th class="text-end">Days Idle</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $today = date('Y-m-d');
            foreach ($leads as $l):
                $idle = (int) $l['days_idle'];
                $fuOverdue = $l['next_followup_date'] && $l['next_followup_date'] < $today
                          && !in_array($l['stage'], ['Won','Lost'], true);
            ?>
                <tr class="<?= $idle >= 30 ? 'table-danger' : ($idle >= 14 ? 'table-warning' : '') ?>">
                    <td>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int)$l['id'] ?>" target="_blank"
                           class="text-decoration-none fw-semibold"><?= htmlspecialchars($l['name']) ?></a>
                    </td>
                    <td><span class="badge <?= stageBadgeClass($l['stage']) ?>"><?= htmlspecialchars($l['stage']) ?></span></td>
                    <td class="text-muted"><?= htmlspecialchars($l['source_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($l['rep_name'] ?? '—') ?></td>
                    <td class="text-end text-muted"><?= htmlspecialchars(formatDate($l['created_at'])) ?></td>
                    <td class="text-end <?= $fuOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= $l['next_followup_date'] ? htmlspecialchars(formatDate($l['next_followup_date'])) : '—' ?>
                    </td>
                    <td class="text-end fw-semibold"><?= $idle ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Days Idle counts from the last stage change, or from creation if the lead has never moved.
        Amber past 14 days, red past 30.
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
