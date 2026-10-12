<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3 d-flex align-items-center justify-content-between">
    <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2"></i>Dashboard</h5>
    <a href="<?= BASE_URL ?>/tasks" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-list-task me-1"></i>My Tasks
    </a>
</div>

<!-- Lead KPI tiles -->
<h6 class="text-muted fw-semibold mb-2 small text-uppercase">Leads</h6>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= BASE_URL ?>/leads?active=1" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-primary"><?= number_format($kpis['total']) ?></div>
            <div class="small text-muted">Active Leads</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle text-primary"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="<?= BASE_URL ?>/leads?won_month=1" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-success"><?= number_format($kpis['wonMonth']) ?></div>
            <div class="small text-muted">Won This Month</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle text-success"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center py-3" title="Won ÷ (Won + Lost) × 100" data-bs-toggle="tooltip">
            <div class="fs-2 fw-bold text-info"><?= $kpis['conversion'] ?>%</div>
            <div class="small text-muted">Conversion Rate <i class="bi bi-info-circle text-muted" style="font-size:.7rem"></i></div>
            <div class="x-small text-muted mt-1" style="font-size:.65rem">Won ÷ (Won+Lost)</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>/leads?active=1" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-warning"><?= formatCurrency($kpis['pipeline']) ?></div>
            <div class="small text-muted">Pipeline Value</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle text-warning"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= BASE_URL ?>/leads?overdue=1" class="card text-center py-3 text-decoration-none kpi-tile <?= ($kpis['overdueCt'] ?? 0) > 0 ? 'border-danger' : '' ?>">
            <div class="fs-2 fw-bold <?= ($kpis['overdueCt'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"><?= number_format($kpis['overdueCt'] ?? 0) ?></div>
            <div class="small text-muted">Overdue Follow-ups</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle <?= ($kpis['overdueCt'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-secondary"><?= formatCurrency($kpis['avgQuote']) ?></div>
            <div class="small text-muted">Avg Quote</div>
        </div>
    </div>
</div>

<!-- Operations KPI tiles -->
<h6 class="text-muted fw-semibold mb-2 mt-1 small text-uppercase">Operations</h6>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/operations?status=pending" class="card text-center py-3 text-decoration-none kpi-tile <?= ($kpis['opsOverdue'] ?? 0) > 0 ? 'border-danger' : '' ?>">
            <div class="fs-2 fw-bold <?= ($kpis['opsOverdue'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"><?= number_format($kpis['opsOverdue'] ?? 0) ?></div>
            <div class="small text-muted">Overdue Ops</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle <?= ($kpis['opsOverdue'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/operations?status=pending" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-warning"><?= number_format($kpis['opsUpcoming'] ?? 0) ?></div>
            <div class="small text-muted">Upcoming (30d)</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle text-warning"></i></div>
        </a>
    </div>
    <div class="col-6 col-md-4">
        <a href="<?= BASE_URL ?>/operations?status=done" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-success"><?= number_format($kpis['opsDoneMonth'] ?? 0) ?></div>
            <div class="small text-muted">Done This Month</div>
            <div class="kpi-tile-arrow"><i class="bi bi-arrow-right-circle text-success"></i></div>
        </a>
    </div>
</div>

<!-- ── Won / Lost by month ─────────────────────────────────────────────────── -->
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-bar-chart-line me-1"></i>Leads Won &amp; Lost by Month</span>
        <span class="small text-muted" id="wonLostSummary"></span>
    </div>
    <div class="card-body">
        <canvas id="wonLostChart" style="max-height:300px"></canvas>
        <div class="text-muted small mt-2" style="font-size:.72rem">
            Counts stage changes into Won or Lost in each month, so a lead that closed
            in June stays counted in June. A lead reopened and re-closed counts in both months.
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-3">
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold bg-white small">Lead Stage Distribution</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="stageChart" style="max-height:280px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header fw-semibold bg-white small">Leads Created — Last 8 Weeks</div>
            <div class="card-body">
                <canvas id="weekChart" style="max-height:280px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Lead Pipeline Stats -->
<h6 class="text-muted fw-semibold mb-2 mt-3 small text-uppercase">Lead Pipeline</h6>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/leads?active=1" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-primary"><?= $leadStats['active'] ?></div>
            <div class="small text-muted">In Pipeline</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/leads?won_month=1" class="card text-center py-3 text-decoration-none kpi-tile">
            <div class="fs-2 fw-bold text-success"><?= $leadStats['won'] ?></div>
            <div class="small text-muted">Total Won</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-danger"><?= $leadStats['lost'] ?></div>
            <div class="small text-muted">Total Lost</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-info"><?= $leadStats['wonMonth'] ?></div>
            <div class="small text-muted">Won This Month</div>
        </div>
    </div>
</div>

<!-- Stage breakdown -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold bg-white small">Leads by Stage</div>
            <div class="card-body py-2">
                <div class="row g-2">
                <?php foreach ($leadStats['stages'] as $s): ?>
                <?php
                    $pct = $leadStats['total'] > 0 ? round($s['cnt'] / $leadStats['total'] * 100) : 0;
                    $color = match($s['stage']) {
                        'Won'  => 'success', 'Lost' => 'danger',
                        'New'  => 'secondary', 'Quoted' => 'warning',
                        default => 'primary',
                    };
                ?>
                <div class="col-6 col-md-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= htmlspecialchars($s['stage']) ?></span>
                        <strong><?= $s['cnt'] ?></strong>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Task Stats -->
<h6 class="text-muted fw-semibold mb-2 mt-1 small text-uppercase">Tasks</h6>
<div class="row g-3 mb-4">
    <div class="col-4">
        <a href="<?= BASE_URL ?>/tasks?type=workflow" class="card text-center py-3 text-decoration-none kpi-tile <?= $taskStats['totalPending'] > 0 ? 'border-warning' : '' ?>">
            <div class="fs-2 fw-bold text-warning"><?= $taskStats['totalPending'] ?></div>
            <div class="small text-muted">Pending Tasks</div>
        </a>
    </div>
    <div class="col-4">
        <div class="card text-center py-3 <?= $taskStats['unassigned'] > 0 ? 'border-danger' : '' ?>">
            <div class="fs-2 fw-bold <?= $taskStats['unassigned'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= $taskStats['unassigned'] ?></div>
            <div class="small text-muted">Unassigned</div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center py-3">
            <div class="fs-2 fw-bold text-success"><?= $taskStats['totalCompleted'] ?></div>
            <div class="small text-muted">Completed</div>
        </div>
    </div>
</div>

<!-- Per-user task breakdown -->
<?php if (!empty($taskStats['rows'])): ?>
<div class="card mb-4">
    <div class="card-header fw-semibold bg-white small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-check me-1"></i>Employee Task Performance</span>
        <a href="<?= BASE_URL ?>/tasks" class="btn btn-sm btn-outline-secondary py-0">View All Tasks</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Employee</th>
                        <th>Role</th>
                        <th class="text-center" title="Total tasks assigned">Total</th>
                        <th class="text-center text-warning" title="Still pending">Pending</th>
                        <th class="text-center text-success" title="Completed this week">This Week</th>
                        <th class="text-center text-primary" title="Completed this month">This Month</th>
                        <th class="text-center text-success" title="Completed on or before due date">On Time</th>
                        <th class="text-center text-danger" title="Completed after due date">Late</th>
                        <th class="text-center text-secondary" title="Skipped by manager">Skipped</th>
                        <th class="text-center text-warning" title="Due dates moved">Rescheduled</th>
                        <th style="min-width:110px" title="On-time completion rate">On-Time Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($taskStats['rows'] as $r):
                    $completedWithDate = (int)$r['on_time_count'] + (int)$r['late_count'];
                    $onTimePct = $completedWithDate > 0
                        ? round((int)$r['on_time_count'] / $completedWithDate * 100)
                        : null;
                    $rateColor = $onTimePct === null ? 'secondary'
                        : ($onTimePct >= 85 ? 'success' : ($onTimePct >= 60 ? 'warning' : 'danger'));
                ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['name']) ?></td>
                    <td class="text-muted text-nowrap"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['role']))) ?></td>
                    <td class="text-center"><?= (int)$r['total_assigned'] ?></td>
                    <td class="text-center <?= (int)$r['pending'] > 0 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['pending'] ?>
                    </td>
                    <td class="text-center fw-semibold text-success"><?= (int)$r['done_this_week'] ?></td>
                    <td class="text-center fw-semibold text-primary"><?= (int)$r['done_this_month'] ?></td>
                    <td class="text-center text-success"><?= (int)$r['on_time_count'] ?></td>
                    <td class="text-center <?= (int)$r['late_count'] > 0 ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['late_count'] ?>
                    </td>
                    <td class="text-center <?= (int)$r['skipped_count'] > 0 ? 'text-secondary fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['skipped_count'] ?>
                    </td>
                    <td class="text-center <?= (int)$r['total_reschedules'] > 3 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                        <?= (int)$r['total_reschedules'] ?>
                        <?php if ((int)$r['total_reschedules'] > 3): ?>
                        <i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="High reschedule count"></i>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($onTimePct !== null): ?>
                        <div class="d-flex align-items-center gap-1">
                            <div class="progress flex-grow-1" style="height:6px">
                                <div class="progress-bar bg-<?= $rateColor ?>" style="width:<?= $onTimePct ?>%"></div>
                            </div>
                            <span class="text-<?= $rateColor ?> fw-semibold" style="min-width:34px"><?= $onTimePct ?>%</span>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (hasRole('admin', 'manager')): ?>
<div class="row g-3 mt-0">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold bg-white small">Sales Rep Performance</div>
            <div class="card-body">
                <canvas id="repChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold bg-white small">Tele Caller Performance</div>
            <div class="card-body">
                <canvas id="tcChart" style="max-height:260px"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mt-0">
    <div class="col-12">
        <div class="card">
            <div class="card-header fw-semibold bg-white small">Operations Completed — Last 6 Months</div>
            <div class="card-body">
                <canvas id="opsChart" style="max-height:200px"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Task Changes by User -->
<?php if (!empty($taskAuditByUser)): ?>
<h6 class="text-muted fw-semibold mb-2 mt-3 small text-uppercase">Task Activity by User</h6>
<div class="card mb-3">
    <div class="card-header fw-semibold bg-white small">
        <i class="bi bi-person-check me-1 text-primary"></i>Who is changing tasks?
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th class="text-center"><i class="bi bi-person-fill-gear me-1 text-warning"></i>Reassignments</th>
                        <th class="text-center"><i class="bi bi-calendar-check me-1 text-info"></i>Date Updates</th>
                        <th class="text-center">Total Changes</th>
                        <th>Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($taskAuditByUser as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($r['user_name']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['role']))) ?></td>
                    <td class="text-center">
                        <?php if ($r['reassignments'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?= (int)$r['reassignments'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($r['date_updates'] > 0): ?>
                        <span class="badge bg-info text-white"><?= (int)$r['date_updates'] ?></span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center fw-semibold"><?= (int)$r['total_changes'] ?></td>
                    <td class="text-muted text-nowrap small"><?= date('d M y, H:i', strtotime($r['last_change_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Task Audit Log -->
<?php if (!empty($taskAuditLog)): ?>
<div class="card mt-3 mb-4" id="taskAuditCard">
    <div class="card-header fw-semibold bg-white small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-1 text-info"></i>Task Change History <span class="badge bg-secondary ms-1"><?= count($taskAuditLog) ?></span></span>
        <button class="btn btn-sm btn-outline-secondary py-0" type="button" data-bs-toggle="collapse" data-bs-target="#taskAuditBody" aria-expanded="false">
            <i class="bi bi-chevron-down"></i>
        </button>
    </div>
    <div class="collapse" id="taskAuditBody">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Lead</th>
                            <th>Task</th>
                            <th>Change</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Changed By</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($taskAuditLog as $row):
                        // Extract task ID from entity string e.g. "workflow_task:42"
                        $taskIdStr = str_replace('workflow_task:', '#', $row['entity'] ?? '');
                        $isAssign  = $row['field_name'] === 'assigned_to';
                        $oldVal    = $isAssign ? ($row['old_user_name'] ?? ($row['old_value'] ?? '—')) : ($row['old_value'] ?? '—');
                        $newVal    = $isAssign ? ($row['new_user_name'] ?? ($row['new_value'] ?? '—')) : ($row['new_value'] ?? '—');
                        $fieldLabel = match($row['field_name']) {
                            'assigned_to' => 'Assignee',
                            'due_date'    => 'Due Date',
                            default       => htmlspecialchars($row['field_name'] ?? ''),
                        };
                        $leadUrl = BASE_URL . '/leads/detail?id=' . (int)$row['lead_id'] . '#tabPipeline';
                    ?>
                    <tr>
                        <td>
                            <?php if ($row['lead_id'] && $row['lead_name']): ?>
                            <a href="<?= htmlspecialchars($leadUrl) ?>" class="text-decoration-none fw-semibold">
                                <?= htmlspecialchars($row['lead_name']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($taskIdStr) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= $fieldLabel ?></span></td>
                        <td class="text-muted"><?= htmlspecialchars((string)($oldVal ?: '—')) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars((string)($newVal ?: '—')) ?></td>
                        <td><?= htmlspecialchars($row['changed_by_name'] ?? '—') ?></td>
                        <td class="text-muted text-nowrap"><?= date('d M y, H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (hasRole('admin') && !empty($sourceOutcomes)):
    $soWon  = array_sum(array_column($sourceOutcomes, 'won'));
    $soLost = array_sum(array_column($sourceOutcomes, 'lost'));
    $soMax  = max(1, max(array_map(fn($r) => (int)$r['won'] + (int)$r['lost'], $sourceOutcomes)));
?>
<!-- ── Won / Lost by lead source (admin only) ──────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span class="fw-semibold"><i class="bi bi-diagram-2 me-1"></i>Won &amp; Lost by Lead Source</span>
        <span class="small text-muted">
            <span class="text-success fw-semibold"><?= $soWon ?></span> won ·
            <span class="text-danger fw-semibold"><?= $soLost ?></span> lost
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="min-width:150px">Source</th>
                    <th style="min-width:140px">Won vs Lost</th>
                    <th class="text-end">Won</th>
                    <th class="text-end">Lost</th>
                    <th class="text-end">Open</th>
                    <th class="text-end">Total</th>
                    <th class="text-end" title="Won ÷ (Won + Lost) — open leads excluded">Win Rate</th>
                    <th class="text-end">kW Won</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sourceOutcomes as $so):
                $won    = (int) $so['won'];
                $lost   = (int) $so['lost'];
                $closed = $won + $lost;
                $scale  = round($closed / $soMax * 100);
            ?>
                <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($so['source_name']) ?></td>
                    <td>
                        <?php if ($closed > 0): ?>
                        <div class="progress" style="height:12px; width:<?= max(12, $scale) ?>%"
                             title="<?= $won ?> won / <?= $lost ?> lost">
                            <div class="progress-bar bg-success" style="width:<?= round($won / $closed * 100) ?>%"></div>
                            <div class="progress-bar bg-danger"  style="width:<?= round($lost / $closed * 100) ?>%"></div>
                        </div>
                        <?php else: ?>
                        <span class="text-muted" style="font-size:.72rem">nothing closed yet</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-success fw-semibold"><?= $won ?></td>
                    <td class="text-end text-danger"><?= $lost ?></td>
                    <td class="text-end text-muted"><?= (int) $so['open'] ?></td>
                    <td class="text-end"><?= (int) $so['total'] ?></td>
                    <td class="text-end fw-semibold">
                        <?= $so['win_rate'] === null ? '<span class="text-muted">—</span>' : $so['win_rate'] . '%' ?>
                    </td>
                    <td class="text-end"><?= $so['kw_won'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white text-muted small">
        Bar length reflects how many leads that source has closed; the split shows won against lost.
        Win rate excludes open leads, so a source still working its pipeline is not penalised.
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
