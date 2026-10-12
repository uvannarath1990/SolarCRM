<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$stageColors = [
    'New'                    => 'secondary',
    'Contacted'              => 'info',
    'Visit Scheduled'        => 'primary',
    'Site Visit in Progress' => 'warning',
    'Site Visit Completed'   => 'primary',
    'Quoted'                 => 'info',
    'Won'                    => 'success',
    'Lost'                   => 'danger',
    'On Hold'                => 'warning',
];
$stageIcons = [
    'New'                    => 'bi-circle',
    'Contacted'              => 'bi-telephone-outbound',
    'Visit Scheduled'        => 'bi-calendar-check',
    'Site Visit in Progress' => 'bi-geo-alt',
    'Site Visit Completed'   => 'bi-check-circle',
    'Quoted'                 => 'bi-file-earmark-text',
    'Won'                    => 'bi-trophy',
    'Lost'                   => 'bi-x-circle',
    'On Hold'                => 'bi-pause-circle',
];
?>

<div class="d-flex align-items-center justify-content-between mb-3 mt-2 flex-wrap gap-2">
    <div>
        <h5 class="mb-0 fw-bold">Partner Dashboard</h5>
        <small class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['user']['name'] ?? '') ?></small>
    </div>
</div>

<!-- KPI cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
                <div class="small text-muted mt-1">Total Leads</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-warning"><?= $stats['active'] ?></div>
                <div class="small text-muted mt-1">In Progress</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= $stats['won'] ?></div>
                <div class="small text-muted mt-1">Won
                    <?php if ($stats['wonMonth'] > 0): ?>
                    <span class="badge bg-success-subtle text-success ms-1">+<?= $stats['wonMonth'] ?> this month</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="fs-2 fw-bold text-info"><?= $stats['conversion'] ?>%</div>
                <div class="small text-muted mt-1">Conversion Rate</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Stage breakdown -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pb-0">
                <i class="bi bi-bar-chart-steps me-1"></i> Leads by Stage
            </div>
            <div class="card-body pt-2">
                <?php if (empty($stats['stages'])): ?>
                    <p class="text-muted small">No leads yet.</p>
                <?php else: ?>
                <?php foreach ($stats['stages'] as $s): $color = $stageColors[$s['stage']] ?? 'secondary'; ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small">
                        <i class="bi <?= $stageIcons[$s['stage']] ?? 'bi-circle' ?> me-1 text-<?= $color ?>"></i>
                        <?= htmlspecialchars($s['stage']) ?>
                    </span>
                    <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> fw-semibold"><?= $s['cnt'] ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Lead list -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-transparent fw-semibold border-0 pb-0 d-flex justify-content-between">
                <span><i class="bi bi-people me-1"></i> My Leads</span>
                <small class="text-muted fw-normal"><?= count($leads) ?> total</small>
            </div>
            <div class="card-body p-0">
                <?php if (empty($leads)): ?>
                    <p class="text-muted small p-3">No leads have been assigned to you yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="d-none d-md-table-cell">Location</th>
                                <th>Stage</th>
                                <th class="d-none d-md-table-cell">Last Update</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($leads as $lead):
                            $color = $stageColors[$lead['stage']] ?? 'secondary';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($lead['name']) ?></div>
                                <?php if ($lead['rep_name'] || $lead['tc_name']): ?>
                                <div class="text-muted" style="font-size:0.75rem">
                                    <?php if ($lead['tc_name']): ?>TC: <?= htmlspecialchars($lead['tc_name']) ?><?php endif; ?>
                                    <?php if ($lead['rep_name']): ?> · Rep: <?= htmlspecialchars($lead['rep_name']) ?><?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-md-table-cell text-muted">
                                <?= htmlspecialchars($lead['location'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> fw-semibold">
                                    <?= htmlspecialchars($lead['stage']) ?>
                                </span>
                            </td>
                            <td class="d-none d-md-table-cell text-muted">
                                <?= date('d M Y', strtotime($lead['updated_at'])) ?>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>"
                                   class="btn btn-sm btn-outline-primary py-0 px-2">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
