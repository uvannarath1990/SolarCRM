<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3">
    <h5 class="fw-bold mb-0">
        <i class="bi bi-check2-circle me-2 text-primary"></i>Today
        <span class="text-muted fw-normal fs-6 ms-1"><?= date('l, d F Y') ?></span>
    </h5>
    <p class="text-muted small mb-0">Good <?= (int)date('H') < 12 ? 'morning' : ((int)date('H') < 17 ? 'afternoon' : 'evening') ?>,
        <strong><?= htmlspecialchars(currentUser()['name']) ?></strong></p>
</div>

<!-- KPI tiles -->
<div class="row g-2 mb-4">
<?php if (hasRole('admin','sales_rep')): ?>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-primary"><?= $kpis['total'] ?? 0 ?></div>
            <div class="small text-muted">Active Leads</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-success"><?= $kpis['wonMonth'] ?? 0 ?></div>
            <div class="small text-muted">Won This Month</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-warning"><?= formatCurrency($kpis['pipeline'] ?? 0) ?></div>
            <div class="small text-muted">Pipeline Value</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 <?= ($kpis['overdueCt'] ?? 0) > 0 ? 'border-danger' : '' ?>">
            <div class="fs-3 fw-bold <?= ($kpis['overdueCt'] ?? 0) > 0 ? 'text-danger' : 'text-muted' ?>"><?= $kpis['overdueCt'] ?? 0 ?></div>
            <div class="small text-muted">Overdue</div>
        </div>
    </div>
<?php elseif (hasRole('tele_caller')): ?>
    <div class="col-6 col-md-4">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-primary"><?= $kpis['total'] ?? 0 ?></div>
            <div class="small text-muted">My Leads</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-info"><?= $kpis['newCt'] ?? 0 ?></div>
            <div class="small text-muted">New to Call</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-warning"><?= $kpis['toSchedCt'] ?? 0 ?></div>
            <div class="small text-muted">Visits to Schedule</div>
        </div>
    </div>
<?php elseif (hasRole('installer')): ?>
    <div class="col-6 col-md-4">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-warning"><?= $kpis['pending'] ?? 0 ?></div>
            <div class="small text-muted">Pending Installs</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card text-center py-2">
            <div class="fs-3 fw-bold text-success"><?= $kpis['done'] ?? 0 ?></div>
            <div class="small text-muted">Completed</div>
        </div>
    </div>
<?php endif; ?>
</div>

<!-- ══ My Tasks ════════════════════════════════════════════════════════════ -->
<?php
$totalWf = count($wfOverdue) + count($wfToday) + count($wfFuture);
?>
<div class="mb-4">
    <div class="d-flex align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-diagram-3-fill me-1 text-primary"></i>My Pipeline Tasks</h6>
        <?php if ($totalWf > 0): ?>
        <span class="badge bg-primary"><?= $totalWf ?></span>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/tasks?type=workflow" class="btn btn-sm btn-outline-secondary ms-auto py-0">View All</a>
    </div>

<?php if ($totalWf === 0): ?>
    <div class="card"><div class="card-body text-center py-4 text-muted">
        <i class="bi bi-check2-all fs-2 text-success d-block mb-1"></i>No pending pipeline tasks.
    </div></div>
<?php endif; ?>

<?php
// ── helper to render a task card ──────────────────────────────────────────
function renderWfCard(array $t, bool $overdue = false): void {
    $phone = dialablePhone($t);
    $border = $overdue ? 'border-danger' : '';
?>
<div class="card <?= $border ?> mb-2" style="<?= $overdue ? 'border-left-width:3px' : '' ?>">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-start justify-content-between gap-2">
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold text-truncate">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $t['lead_id'] ?>" class="text-decoration-none text-dark">
                        <?= htmlspecialchars($t['lead_name']) ?>
                    </a>
                </div>
                <div class="small text-muted"><?= htmlspecialchars($t['title']) ?></div>
                <div class="d-flex flex-wrap gap-2 mt-1 small">
                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($t['stage']) ?></span>
                    <?php if ($t['assignee_name']): ?>
                    <span class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($t['assignee_name']) ?></span>
                    <?php else: ?>
                    <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                    <?php endif; ?>
                    <?php if ($t['due_date']): ?>
                    <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                        <i class="bi bi-calendar3 me-1"></i><?= date('d M', strtotime($t['due_date'])) ?>
                        <?= $overdue ? '<span class="badge bg-danger ms-1">Overdue</span>' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-1 flex-shrink-0">
                <?php if ($phone): ?>
                <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success py-0 px-2"><i class="bi bi-telephone"></i></a>
                <a href="https://wa.me/91<?= htmlspecialchars(ltrim($phone, '+91'), ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="btn btn-sm py-0 px-2" style="color:#25d366;border:1px solid #25d366"><i class="bi bi-whatsapp"></i></a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/workflow/detail?id=<?= $t['id'] ?>"
                   class="btn btn-sm btn-<?= $overdue ? 'danger' : 'success' ?> py-0 px-2">
                    <i class="bi bi-check2-square me-1"></i>Act
                </a>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php if (!empty($wfOverdue)): ?>
<div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fw-semibold text-danger small"><i class="bi bi-exclamation-circle-fill me-1"></i>Overdue</span>
        <span class="badge bg-danger"><?= count($wfOverdue) ?></span>
        <hr class="flex-grow-1 my-0 border-danger opacity-25">
    </div>
    <?php foreach ($wfOverdue as $t) renderWfCard($t, true); ?>
</div>
<?php endif; ?>

<?php if (!empty($wfToday)): ?>
<div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fw-semibold text-primary small"><i class="bi bi-calendar-check-fill me-1"></i>Today</span>
        <span class="badge bg-primary"><?= count($wfToday) ?></span>
        <hr class="flex-grow-1 my-0">
    </div>
    <?php foreach ($wfToday as $t) renderWfCard($t); ?>
</div>
<?php endif; ?>

<?php if (!empty($wfFuture)): ?>
<div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fw-semibold text-secondary small"><i class="bi bi-calendar3 me-1"></i>Upcoming</span>
        <span class="badge bg-secondary"><?= count($wfFuture) ?></span>
        <hr class="flex-grow-1 my-0">
    </div>
    <?php foreach ($wfFuture as $t) renderWfCard($t); ?>
</div>
<?php endif; ?>

</div>
<hr class="my-3">

<!-- ── Overdue Operations alert ──────────────────────────────────────────── -->
<?php if ($overdueOps > 0 && hasRole('admin', 'installer', 'sales_rep')): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-3" role="alert">
    <i class="bi bi-tools fs-5"></i>
    <div>
        <strong><?= $overdueOps ?> operation<?= $overdueOps !== 1 ? 's' : '' ?> overdue.</strong>
        <a href="<?= BASE_URL ?>/operations?status=pending" class="alert-link ms-2">View Operations →</a>
    </div>
</div>
<?php endif; ?>

<!-- ── Overdue ─────────────────────────────────────────────────────────────── -->
<?php if (!empty($overdue)): ?>
<h6 class="text-danger fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Overdue (<?= count($overdue) ?>)</h6>
<div class="mb-4">
<?php foreach ($overdue as $l): ?>
<div class="card border-danger mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <span class="badge <?= stageBadgeClass($l['stage']) ?> ms-1 small"><?= htmlspecialchars($l['stage']) ?></span>
            <br>
            <span class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($l['location'] ?? '—') ?></span>
            <span class="small text-danger ms-2">Due <?= formatDate($l['next_followup_date']) ?></span>
        </div>
        <div class="d-flex gap-1">
            <?php if (dialablePhone($l)): ?>
            <a href="tel:<?= htmlspecialchars(dialablePhone($l)) ?>" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-telephone"></i></a>
            <a href="https://wa.me/<?= htmlspecialchars(dialablePhone($l)) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-whatsapp"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Site visits today (Sales Rep / Admin) ───────────────────────────────── -->
<?php if (!empty($todayVisits)): ?>
<h6 class="fw-bold mb-2"><i class="bi bi-geo-alt me-1 text-primary"></i>Site Visits Today (<?= count($todayVisits) ?>)</h6>
<div class="mb-4">
<?php foreach ($todayVisits as $l): ?>
<div class="card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <?php if ($l['potential_kw']): ?><span class="badge bg-light text-dark ms-1"><?= $l['potential_kw'] ?> kW</span><?php endif; ?>
            <br>
            <span class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($l['location'] ?? '—') ?></span>
        </div>
        <a href="<?= BASE_URL ?>/site-visits/view?lead_id=<?= $l['id'] ?>" class="btn btn-sm btn-primary">
            <?= $l['stage'] === 'Site Visit in Progress' ? 'Resume Visit' : 'Start Visit' ?>
        </a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Follow-ups today ───────────────────────────────────────────────────── -->
<?php if (!empty($todayFollowups)): ?>
<h6 class="fw-bold mb-2"><i class="bi bi-telephone me-1 text-info"></i>Follow-ups Today (<?= count($todayFollowups) ?>)</h6>
<div class="mb-4">
<?php foreach ($todayFollowups as $l): ?>
<div class="card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <span class="badge <?= stageBadgeClass($l['stage']) ?> ms-1 small"><?= htmlspecialchars($l['stage']) ?></span>
            <br>
            <span class="small text-muted"><?= htmlspecialchars(displayPhone($l)) ?></span>
        </div>
        <div class="d-flex gap-1">
            <?php if (dialablePhone($l)): ?>
            <a href="tel:<?= htmlspecialchars(dialablePhone($l)) ?>" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-telephone"></i></a>
            <a href="https://wa.me/<?= htmlspecialchars(dialablePhone($l)) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-whatsapp"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Tele Caller: New leads to call ────────────────────────────────────── -->
<?php if (!empty($newLeads)): ?>
<h6 class="fw-bold mb-2"><i class="bi bi-person-plus me-1 text-success"></i>New Leads to Call (<?= count($newLeads) ?>)</h6>
<div class="mb-4">
<?php foreach ($newLeads as $l): ?>
<div class="card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <br>
            <span class="small text-muted"><?= htmlspecialchars(displayPhone($l)) ?></span>
        </div>
        <?php if (dialablePhone($l)): ?>
        <a href="tel:<?= htmlspecialchars(dialablePhone($l)) ?>" class="btn btn-sm btn-outline-success py-0"><i class="bi bi-telephone"></i></a>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Tele Caller: Visits to schedule ───────────────────────────────────── -->
<?php if (!empty($toSchedule)): ?>
<h6 class="fw-bold mb-2"><i class="bi bi-calendar-plus me-1 text-warning"></i>Visits to Schedule (<?= count($toSchedule) ?>)</h6>
<div class="mb-4">
<?php foreach ($toSchedule as $l): ?>
<div class="card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <br>
            <span class="small text-muted"><?= htmlspecialchars($l['location'] ?? '') ?></span>
        </div>
        <a href="<?= BASE_URL ?>/leads/detail?id=<?= $l['id'] ?>" class="btn btn-sm btn-outline-primary py-0">Schedule</a>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Installer: Won lead queue ──────────────────────────────────────────── -->
<?php if (!empty($wonLeads)): ?>
<h6 class="fw-bold mb-2"><i class="bi bi-tools me-1 text-primary"></i>Installation Queue (<?= count($wonLeads) ?>)</h6>
<div class="mb-4">
<?php foreach ($wonLeads as $l): ?>
<div class="card mb-2">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <div>
            <a href="<?= BASE_URL ?>/installations/detail?lead_id=<?= $l['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($l['name']) ?></a>
            <?php if ($l['potential_kw']): ?><span class="badge bg-light text-dark ms-1"><?= $l['potential_kw'] ?> kW</span><?php endif; ?>
            <br>
            <span class="small text-muted"><?= htmlspecialchars($l['location'] ?? '—') ?></span>
            <?php if ($l['scheduled_date']): ?>
            <span class="badge bg-info ms-1 small"><?= formatDate($l['scheduled_date']) ?></span>
            <?php endif; ?>
        </div>
        <span class="badge bg-secondary text-capitalize"><?= htmlspecialchars(str_replace('_',' ',$l['install_status'])) ?></span>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (empty($overdue) && empty($todayVisits) && empty($todayFollowups) && empty($newLeads) && empty($toSchedule) && empty($wonLeads)): ?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-sun display-1 text-warning mb-3 d-block"></i>
    <h5>All clear for today!</h5>
    <p>No overdue items or scheduled actions. <a href="<?= BASE_URL ?>/leads">Browse leads</a></p>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
