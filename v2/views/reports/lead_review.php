<?php require __DIR__ . '/../layout/header.php';

$qs = fn(array $extra = []) => http_build_query(array_merge(
    ['from' => $from, 'to' => $to, 'rep' => $repId ?: ''], $extra
));
$csv = fn(string $section) => BASE_URL . '/reports/lead-review/export?' . $qs(['section' => $section]);
?>

<div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-graph-up-arrow me-2"></i>Lead Review</h5>
    <span class="text-muted small">
        <?= htmlspecialchars(formatDate($from)) ?> — <?= htmlspecialchars(formatDate($to)) ?>
        · <?= (int) $funnel['total'] ?> leads created
    </span>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/reports/lead-review" class="card mb-3">
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
                <label class="form-label small fw-semibold mb-1">Sales Rep</label>
                <select name="rep" class="form-select form-select-sm">
                    <option value="">— All reps —</option>
                    <?php foreach ($allReps as $r): ?>
                    <option value="<?= (int)$r['id'] ?>" <?= $repId === (int)$r['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($r['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel me-1"></i>Apply
                </button>
            </div>
        </div>
        <div class="form-text small mt-1">
            Date range filters on <strong>lead created date</strong>. Rep filter applies to the funnel,
            velocity and stuck sections; scorecards always show every active user.
        </div>
    </div>
</form>

<!-- ── Funnel ──────────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-filter-circle me-1"></i>Stage Funnel</span>
        <a href="<?= $csv('funnel') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small">
            Counts every lead that <em>ever reached</em> a stage — a lead now at Won still counts
            at Contacted. Conversion is against the previous stage.
        </p>
        <?php
        $maxCount = max(1, $funnel['rows'][0]['count'] ?? 1);
        foreach ($funnel['rows'] as $row):
            $pct = round($row['count'] / $maxCount * 100);
        ?>
        <div class="mb-2">
            <div class="d-flex justify-content-between small mb-1">
                <span class="fw-semibold"><?= htmlspecialchars($row['stage']) ?></span>
                <span>
                    <strong><?= (int)$row['count'] ?></strong>
                    <?php if ($row['conv'] !== ''): ?>
                    <span class="text-muted ms-2"><?= $row['conv'] ?>% from prev</span>
                    <?php if ((int)$row['dropoff'] > 0): ?>
                    <span class="text-danger ms-2">−<?= (int)$row['dropoff'] ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="progress" style="height:14px">
                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex gap-3 mt-3 small">
            <span class="text-danger"><i class="bi bi-x-circle me-1"></i>Lost: <strong><?= (int)$funnel['lost'] ?></strong></span>
            <span class="text-warning"><i class="bi bi-pause-circle me-1"></i>On Hold: <strong><?= (int)$funnel['onhold'] ?></strong></span>
        </div>
    </div>
</div>

<!-- ── Velocity ────────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-speedometer2 me-1"></i>Time in Stage</span>
        <a href="<?= $csv('velocity') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV
        </a>
    </div>
    <?php if (empty($velocity)): ?>
    <div class="card-body text-muted small">No stage-change history in this range.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th>Stage</th>
                    <th class="text-end">Transitions</th>
                    <th class="text-end">Avg Days</th>
                    <th class="text-end">Median Days</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($velocity as $v): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($v['stage']) ?></td>
                    <td class="text-end text-muted"><?= (int)$v['n'] ?></td>
                    <td class="text-end <?= $v['avg'] > 14 ? 'text-danger fw-semibold' : '' ?>"><?= $v['avg'] ?></td>
                    <td class="text-end"><?= $v['median'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Median is the more reliable figure — a few very old leads can drag the average up.
    </div>
    <?php endif; ?>
</div>

<!-- ── Stuck leads ─────────────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-hourglass-split me-1"></i>Stalled Leads (top 25)</span>
        <a href="<?= $csv('stuck') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV (all)
        </a>
    </div>
    <?php if (empty($stuck)): ?>
    <div class="card-body text-muted small">Nothing stalled — every open lead has moved recently.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th>Lead</th>
                    <th>Stage</th>
                    <th>Sales Rep</th>
                    <th class="text-end">Days Stalled</th>
                    <th>Last Change</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stuck as $s): ?>
                <tr class="<?= $s['days'] >= 30 ? 'table-danger' : ($s['days'] >= 14 ? 'table-warning' : '') ?>">
                    <td>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= $s['id'] ?>" target="_blank"
                           class="text-decoration-none fw-semibold">
                            <?= htmlspecialchars($s['name']) ?>
                        </a>
                    </td>
                    <td><span class="badge <?= stageBadgeClass($s['stage']) ?>"><?= htmlspecialchars($s['stage']) ?></span></td>
                    <td class="text-muted"><?= htmlspecialchars($s['rep_name'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= (int)$s['days'] ?></td>
                    <td class="text-muted"><?= htmlspecialchars(formatDate($s['last_change'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Sales rep scorecard ─────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-person-badge me-1"></i>Sales Rep Scorecard</span>
        <a href="<?= $csv('reps') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th>Sales Rep</th>
                    <th class="text-end">Assigned</th>
                    <th class="text-end">Visits Done</th>
                    <th class="text-end">Won</th>
                    <th class="text-end">Lost</th>
                    <th class="text-end">Open</th>
                    <th class="text-end">Win Rate</th>
                    <th class="text-end" title="Average days from lead creation to Won">Avg Days to Win</th>
                    <th class="text-end">kW Won</th>
                    <th class="text-end">Overdue</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($repRows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-end"><?= (int)$r['assigned'] ?></td>
                    <td class="text-end"><?= (int)$r['visits'] ?></td>
                    <td class="text-end text-success fw-semibold"><?= (int)$r['won'] ?></td>
                    <td class="text-end text-danger"><?= (int)$r['lost'] ?></td>
                    <td class="text-end"><?= (int)$r['open'] ?></td>
                    <td class="text-end fw-semibold"><?= $r['win_rate'] ?>%</td>
                    <td class="text-end text-muted"><?= $r['avg_days_to_win'] !== '' ? $r['avg_days_to_win'] : '—' ?></td>
                    <td class="text-end"><?= $r['kw_won'] ?></td>
                    <td class="text-end <?= (int)$r['overdue'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['overdue'] ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Win rate = Won ÷ (Won + Lost); open leads are excluded so it isn't diluted by work in progress.
    </div>
</div>

<!-- ── Tele caller scorecard ───────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-headset me-1"></i>Tele Caller Scorecard</span>
        <a href="<?= $csv('tele') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-download me-1"></i>CSV
        </a>
    </div>
    <?php if (empty($teleRows)): ?>
    <div class="card-body text-muted small">No tele caller leads in this range.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tele Caller</th>
                    <th class="text-end">Leads</th>
                    <th class="text-end">Reached Contacted</th>
                    <th class="text-end">Reached Visit Scheduled</th>
                    <th class="text-end">Reached Won</th>
                    <th class="text-end">Contact Rate</th>
                    <th class="text-end">Visit Rate</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($teleRows as $t): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
                    <td class="text-end"><?= (int)$t['leads'] ?></td>
                    <td class="text-end"><?= (int)$t['contacted'] ?></td>
                    <td class="text-end"><?= (int)$t['scheduled'] ?></td>
                    <td class="text-end text-success fw-semibold"><?= (int)$t['won'] ?></td>
                    <td class="text-end"><?= $t['contact_rate'] ?>%</td>
                    <td class="text-end"><?= $t['visit_rate'] ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
