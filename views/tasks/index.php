<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$typeFilter     = $_GET['type']             ?? 'all';
$filterUserId   = (int)($_GET['filter_user']      ?? 0);
$filterDateFrom = trim($_GET['filter_date_from']  ?? '');
$filterDateTo   = trim($_GET['filter_date_to']    ?? '');
$today          = date('Y-m-d');
$_isAdminFilter = in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'operation_manager'], true);
$_hasActiveFilter = $filterUserId || $filterDateFrom || $filterDateTo;

// Role-based pipeline sub-types (map GET key → assigned_role value)
$pipelineRoles = ['tc' => 'tele_caller', 'sv' => 'sales_rep', 'doc' => 'documentation', 'ops_task' => 'operation_manager'];
$isPipelineRole = array_key_exists($typeFilter, $pipelineRoles);

// Apply type filter
if ($typeFilter === 'all') {
    $filtered = $tasks;
} elseif ($isPipelineRole) {
    $role_val = $pipelineRoles[$typeFilter];
    $filtered = array_values(array_filter($tasks, fn($t) => $t['type'] === 'workflow' && ($t['assigned_role'] ?? '') === $role_val));
} else {
    $filtered = array_values(array_filter($tasks, fn($t) => $t['type'] === $typeFilter));
}

// Split into three buckets
$overdueTasks = array_values(array_filter($filtered, fn($t) => !empty($t['date']) && $t['date'] < $today));
$todayTasks   = array_values(array_filter($filtered, fn($t) => ($t['date'] ?? null) === $today));
$futureTasks  = array_values(array_filter($filtered, fn($t) => empty($t['date']) || $t['date'] > $today));

// Sort each bucket
usort($overdueTasks, fn($a, $b) => strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'));
usort($todayTasks,   fn($a, $b) => strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'));
usort($futureTasks,  fn($a, $b) => strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'));

// Build counts
$counts = ['all' => count($tasks), 'reminder' => 0, 'followup' => count($overdueFollowups), 'operation' => 0, 'workflow' => 0,
           'tc' => 0, 'sv' => 0, 'doc' => 0, 'ops_task' => 0];
foreach ($tasks as $t) {
    $counts[$t['type']] = ($counts[$t['type']] ?? 0) + 1;
    if ($t['type'] === 'workflow') {
        $rk = array_search($t['assigned_role'] ?? '', $pipelineRoles);
        if ($rk !== false) $counts[$rk] = ($counts[$rk] ?? 0) + 1;
    }
}
?>

<div class="mt-3 mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <h5 class="fw-bold mb-0"><i class="bi bi-list-task me-2 text-primary"></i>My Tasks</h5>
    <span class="text-muted small"><?= count($filtered) ?> of <?= count($tasks) ?> task<?= count($tasks) !== 1 ? 's' : '' ?></span>
</div>

<?php if ($_isAdminFilter): ?>
<!-- Admin / Manager filter bar -->
<form method="get" class="card card-body py-2 px-3 mb-3 d-flex flex-wrap gap-2 align-items-end" id="taskFilterForm" style="background:#f8f9fa;border:1px solid #dee2e6">
    <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
    <div class="d-flex flex-wrap gap-2 align-items-end w-100">
        <div style="min-width:180px;flex:1 1 180px">
            <label class="form-label small fw-semibold mb-1">Assigned To</label>
            <select name="filter_user" class="form-select form-select-sm">
                <option value="">— All Users —</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= $u['id'] ?>" <?= $filterUserId === (int)$u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="min-width:130px;flex:0 1 140px">
            <label class="form-label small fw-semibold mb-1">Date From</label>
            <input type="date" name="filter_date_from" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($filterDateFrom) ?>">
        </div>
        <div style="min-width:130px;flex:0 1 140px">
            <label class="form-label small fw-semibold mb-1">Date To</label>
            <input type="date" name="filter_date_to" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($filterDateTo) ?>">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-funnel-fill me-1"></i>Filter
            </button>
            <?php if ($_hasActiveFilter): ?>
            <a href="?type=<?= urlencode($typeFilter) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-x-lg me-1"></i>Clear
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($_hasActiveFilter): ?>
    <div class="w-100 small text-info mt-1">
        <i class="bi bi-info-circle me-1"></i>
        Filters active:
        <?php if ($filterUserId): ?>
        <?php $fu = array_filter($allUsers, fn($u) => (int)$u['id'] === $filterUserId); $fu = reset($fu); ?>
        <strong><?= $fu ? htmlspecialchars($fu['name']) : 'User #'.$filterUserId ?></strong>
        <?php endif; ?>
        <?php if ($filterDateFrom): ?> from <strong><?= date('d M Y', strtotime($filterDateFrom)) ?></strong><?php endif; ?>
        <?php if ($filterDateTo): ?> to <strong><?= date('d M Y', strtotime($filterDateTo)) ?></strong><?php endif; ?>
    </div>
    <?php endif; ?>
</form>
<?php endif; ?>

<!-- Main type tabs -->
<div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
    <div class="btn-group btn-group-sm" role="group">
        <?php
        $mainTypes = [
            'all'       => ['All',                'secondary', 'bi-grid-fill'],
            'workflow'  => ['All Pipeline',       'primary',   'bi-diagram-3-fill'],
            'reminder'  => ['Reminders',          'warning',   'bi-bell-fill'],
            'operation' => ['Operations',         'dark',      'bi-wrench-adjustable-circle-fill'],
            'followup'  => ['Overdue Follow-ups', 'danger',    'bi-calendar-x-fill'],
        ];
        foreach ($mainTypes as $key => [$label, $color, $icon]):
            $isActive = ($typeFilter === $key) || ($isPipelineRole && $key === 'workflow');
            $active   = $isActive ? 'active' : '';
            $url      = http_build_query(array_merge($_GET, ['type' => $key]));
        ?>
        <a href="?<?= $url ?>" class="btn btn-outline-<?= $color ?> <?= $active ?>">
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
            <span class="badge bg-<?= $color ?> <?= $color === 'warning' ? 'text-dark' : '' ?> ms-1 small"><?= $counts[$key] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pipeline sub-tabs (show when All Pipeline or a sub-type is active) -->
<?php if ($typeFilter === 'workflow' || $isPipelineRole): ?>
<div class="d-flex flex-wrap gap-2 mb-3 ps-1 align-items-center">
    <span class="text-muted small me-1"><i class="bi bi-filter me-1"></i>Filter by:</span>
    <div class="btn-group btn-group-sm" role="group">
        <?php
        $subTypes = [
            'workflow'   => ['All',           'primary',   'bi-list-task'],
            'tc'         => ['Tele Caller',   'info',      'bi-headset'],
            'sv'         => ['Site Visit',    'success',   'bi-geo-alt-fill'],
            'doc'        => ['Documentation', 'warning',   'bi-folder-check'],
            'ops_task'   => ['Op. Tasks',     'secondary', 'bi-gear-fill'],
        ];
        foreach ($subTypes as $key => [$label, $color, $icon]):
            $active = ($typeFilter === $key || ($key === 'workflow' && $typeFilter === 'workflow')) ? 'active' : '';
            $url    = http_build_query(array_merge($_GET, ['type' => $key]));
        ?>
        <a href="?<?= $url ?>" class="btn btn-outline-<?= $color ?> <?= $active ?>">
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
            <span class="badge bg-<?= $color ?> <?= $color === 'warning' ? 'text-dark' : '' ?> ms-1 small"><?= $counts[$key] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="mb-3"></div>
<?php endif; ?>

<?php if ($typeFilter === 'followup'): ?>

<?php if (empty($overdueFollowups)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-check2-all fs-1 text-success d-block mb-2"></i>
        <div class="fw-semibold">No overdue follow-ups!</div>
        <div class="small">All leads have been followed up or closed.</div>
    </div>
</div>
<?php else: ?>
<div class="row g-2">
<?php foreach ($overdueFollowups as $fl):
    $phone      = dialablePhone($fl);
    $daysOverdue = (int) floor((strtotime(date('Y-m-d')) - strtotime($fl['next_followup_date'])) / 86400);
    $stageColor = match($fl['stage']) {
        'New'                   => 'secondary',
        'Contacted'             => 'info',
        'Visit Scheduled'       => 'primary',
        'Site Visit Completed'  => 'success',
        'Quoted'                => 'warning',
        default                 => 'secondary',
    };
?>
<div class="col-12 col-md-6 col-xl-4">
    <div class="card border-danger" style="border-left:3px solid var(--bs-danger)!important">
        <div class="card-body p-3">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                <span class="badge bg-<?= $stageColor ?> <?= $stageColor === 'warning' ? 'text-dark' : '' ?>">
                    <?= htmlspecialchars($fl['stage']) ?>
                </span>
                <span class="text-danger small fw-semibold text-nowrap">
                    <i class="bi bi-calendar-x me-1"></i>
                    <?= date('d M Y', strtotime($fl['next_followup_date'])) ?>
                    <span class="badge bg-danger ms-1"><?= $daysOverdue ?>d overdue</span>
                </span>
            </div>
            <div class="fw-semibold text-truncate mb-1">
                <a href="<?= BASE_URL ?>/leads/detail?id=<?= $fl['id'] ?>" class="text-decoration-none text-dark">
                    <?= htmlspecialchars($fl['name']) ?>
                </a>
            </div>
            <?php if ($fl['rep_name'] || $fl['tc_name']): ?>
            <div class="small text-muted mb-2">
                <?php if ($fl['rep_name']): ?>
                <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($fl['rep_name']) ?>
                <?php endif; ?>
                <?php if ($fl['tc_name']): ?>
                <span class="ms-2"><i class="bi bi-headset me-1"></i><?= htmlspecialchars($fl['tc_name']) ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2 flex-wrap mt-2">
                <?php if ($phone): ?>
                <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success py-1 px-2">
                    <i class="bi bi-telephone-fill me-1"></i><span class="d-none d-sm-inline">Call</span>
                </a>
                <a href="https://wa.me/91<?= htmlspecialchars(ltrim($phone, '+91'), ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                   class="btn btn-sm py-1 px-2" style="color:#25d366;border:1px solid #25d366">
                    <i class="bi bi-whatsapp me-1"></i><span class="d-none d-sm-inline">WhatsApp</span>
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/leads/detail?id=<?= $fl['id'] ?>"
                   class="btn btn-sm btn-outline-primary py-1 px-2 ms-auto">
                    <i class="bi bi-arrow-right-short"></i>Open
                </a>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif (empty($filtered)): ?>
<div class="card">
    <div class="card-body text-center py-5 text-muted">
        <i class="bi bi-check2-all fs-1 text-success d-block mb-2"></i>
        <div class="fw-semibold">All clear!</div>
        <div class="small">No pending tasks<?= $typeFilter !== 'all' ? ' in this category' : '' ?>.</div>
    </div>
</div>
<?php else: ?>

<?php
$_isAdminOrMgr = in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'operation_manager'], true);

// Helper to render task cards for an accordion panel
function renderTaskCards(array $taskList, string $today, bool $isAdminOrMgr = false, array $allUsers = []): void {
    foreach ($taskList as $task):
        $isOverdue   = !empty($task['date']) && $task['date'] < $today;
        $borderClass = match($task['color']) {
            'danger'  => 'border-danger',
            'warning' => 'border-warning',
            'info'    => 'border-info',
            'primary' => 'border-primary',
            default   => '',
        };
        $phone = dialablePhone($task);
?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card task-card <?= $borderClass ?>" style="border-left-width:3px">
            <div class="card-body p-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?= $task['color'] ?> <?= $task['color'] === 'warning' ? 'text-dark' : '' ?>">
                            <i class="bi <?= $task['icon'] ?> me-1"></i><?= htmlspecialchars($task['label']) ?>
                        </span>
                    </div>
                    <div class="text-<?= $isOverdue ? 'danger' : 'muted' ?> small text-nowrap fw-semibold">
                        <?php if (!empty($task['date'])): ?>
                        <i class="bi bi-calendar3 me-1"></i><?= date('d M', strtotime($task['date'])) ?>
                        <?php else: ?>
                        <span class="text-muted">No date</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fw-semibold text-truncate mb-1">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>" class="text-decoration-none text-dark">
                        <?= htmlspecialchars($task['lead_name']) ?>
                    </a>
                </div>
                <div class="text-muted small mb-1" style="line-height:1.4">
                    <?= htmlspecialchars($task['message']) ?>
                </div>
                <?php if ($task['type'] === 'workflow'): ?>
                <div class="small mb-2">
                    <?php if (!empty($task['assigned_name'])): ?>
                    <i class="bi bi-person-fill me-1 text-muted"></i>
                    <span class="text-dark fw-semibold"><?= htmlspecialchars($task['assigned_name']) ?></span>
                    <span class="text-muted ms-1">(<?= htmlspecialchars(str_replace('_', ' ', $task['assigned_role'] ?? '')) ?>)</span>
                    <?php else: ?>
                    <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                    <?php endif; ?>
                </div>
                <?php if ($isAdminOrMgr && !empty($allUsers)): ?>
                <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
                    <select class="form-select form-select-sm wf-assign-select flex-grow-1"
                            data-task-id="<?= $task['raw_id'] ?>" style="min-width:140px">
                        <option value="">— <?= empty($task['assigned_to']) ? 'Assign to...' : 'Reassign...' ?> —</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= (int)($task['assigned_to'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['name']) ?>
                            (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group input-group-sm flex-shrink-0" style="width:auto" title="Change due date">
                        <span class="input-group-text px-1 <?= $isOverdue ? 'bg-danger text-white border-danger' : '' ?>">
                            <i class="bi bi-calendar3"></i>
                        </span>
                        <input type="date" class="form-control form-control-sm wf-due-date-input <?= $isOverdue ? 'border-danger' : '' ?>"
                               data-task-id="<?= $task['raw_id'] ?>"
                               value="<?= htmlspecialchars($task['date'] ?? '') ?>"
                               style="width:130px">
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap">
                    <?php if ($phone): ?>
                    <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-success py-1 px-2">
                        <i class="bi bi-telephone-fill me-1"></i><span class="d-none d-sm-inline">Call</span>
                    </a>
                    <a href="https://wa.me/91<?= htmlspecialchars(ltrim($phone, '+91'), ENT_QUOTES, 'UTF-8') ?>" target="_blank"
                       class="btn btn-sm btn-outline-success py-1 px-2" style="color:#25d366;border-color:#25d366">
                        <i class="bi bi-whatsapp me-1"></i><span class="d-none d-sm-inline">WhatsApp</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($task['type'] === 'workflow'): ?>
                    <a href="<?= BASE_URL ?>/workflow/detail?id=<?= $task['raw_id'] ?>"
                       class="btn btn-sm btn-success py-1 px-2 ms-auto">
                        <i class="bi bi-check2-square me-1"></i>Act
                    </a>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>"
                       class="btn btn-sm btn-outline-primary py-1 px-2">
                        <i class="bi bi-arrow-right-short"></i>Lead
                    </a>
                    <?php else: ?>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>"
                       class="btn btn-sm btn-outline-primary py-1 px-2 ms-auto">
                        <i class="bi bi-arrow-right-short"></i>Open
                    </a>
                    <?php if ($task['type'] === 'reminder'): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary py-1 px-2 mark-done-btn"
                            data-id="<?= $task['raw_id'] ?>"
                            title="Mark reminder done">
                        <i class="bi bi-check-lg"></i>
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php
    endforeach;
}
?>

<div class="accordion" id="tasksAccordion">

    <!-- Overdue -->
    <div class="accordion-item border-danger mb-2" style="border-left:3px solid var(--bs-danger)!important;border-radius:.375rem">
        <h2 class="accordion-header">
            <button class="accordion-button <?= empty($overdueTasks) ? 'collapsed' : '' ?> fw-semibold text-danger py-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#accOverdue"
                    aria-expanded="<?= empty($overdueTasks) ? 'false' : 'true' ?>">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                Overdue
                <span class="badge bg-danger ms-2"><?= count($overdueTasks) ?></span>
            </button>
        </h2>
        <div id="accOverdue" class="accordion-collapse collapse <?= !empty($overdueTasks) ? 'show' : '' ?>"
             data-bs-parent="">
            <div class="accordion-body pt-2 pb-3 px-2">
                <?php if (empty($overdueTasks)): ?>
                <p class="text-muted small mb-0 ps-1"><i class="bi bi-check2 me-1"></i>No overdue tasks.</p>
                <?php else: ?>
                <div class="row g-2"><?php renderTaskCards($overdueTasks, $today, $_isAdminOrMgr, $allUsers); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today -->
    <div class="accordion-item border-warning mb-2" style="border-left:3px solid var(--bs-warning)!important;border-radius:.375rem">
        <h2 class="accordion-header">
            <button class="accordion-button <?= empty($todayTasks) ? 'collapsed' : '' ?> fw-semibold text-warning py-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#accToday"
                    aria-expanded="<?= empty($todayTasks) ? 'false' : 'true' ?>">
                <i class="bi bi-calendar-check-fill me-2"></i>
                Today
                <span class="badge bg-warning text-dark ms-2"><?= count($todayTasks) ?></span>
            </button>
        </h2>
        <div id="accToday" class="accordion-collapse collapse <?= !empty($todayTasks) ? 'show' : '' ?>"
             data-bs-parent="">
            <div class="accordion-body pt-2 pb-3 px-2">
                <?php if (empty($todayTasks)): ?>
                <p class="text-muted small mb-0 ps-1"><i class="bi bi-check2 me-1"></i>No tasks due today.</p>
                <?php else: ?>
                <div class="row g-2"><?php renderTaskCards($todayTasks, $today, $_isAdminOrMgr, $allUsers); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Future -->
    <div class="accordion-item mb-2" style="border-left:3px solid var(--bs-secondary)!important;border-radius:.375rem">
        <h2 class="accordion-header">
            <button class="accordion-button <?= empty($futureTasks) ? 'collapsed' : '' ?> fw-semibold text-secondary py-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#accFuture"
                    aria-expanded="<?= empty($futureTasks) ? 'false' : 'true' ?>">
                <i class="bi bi-calendar3 me-2"></i>
                Future
                <span class="badge bg-secondary ms-2"><?= count($futureTasks) ?></span>
            </button>
        </h2>
        <div id="accFuture" class="accordion-collapse collapse <?= !empty($futureTasks) ? 'show' : '' ?>"
             data-bs-parent="">
            <div class="accordion-body pt-2 pb-3 px-2">
                <?php if (empty($futureTasks)): ?>
                <p class="text-muted small mb-0 ps-1"><i class="bi bi-check2 me-1"></i>No upcoming tasks.</p>
                <?php else: ?>
                <div class="row g-2"><?php renderTaskCards($futureTasks, $today, $_isAdminOrMgr); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /accordion -->

<?php endif; ?>

<?php if (!empty($completedTasks)): ?>
<div class="mt-4">
    <button class="btn btn-sm btn-outline-secondary w-100 text-start d-flex align-items-center gap-2"
            type="button" data-bs-toggle="collapse" data-bs-target="#completedSection">
        <i class="bi bi-check2-all text-success"></i>
        <span class="fw-semibold">Completed Tasks</span>
        <span class="badge bg-success ms-1"><?= count($completedTasks) ?></span>
        <i class="bi bi-chevron-down ms-auto"></i>
    </button>
    <div class="collapse mt-2" id="completedSection">
        <div class="row g-2">
        <?php foreach ($completedTasks as $ct): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card border-success" style="border-left-width:3px;opacity:.85">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                        <span class="badge bg-<?= $ct['status'] === 'skipped' ? 'secondary' : 'success' ?>">
                            <i class="bi bi-<?= $ct['status'] === 'skipped' ? 'skip-forward' : 'check-lg' ?> me-1"></i>
                            <?= $ct['status'] === 'skipped' ? 'Skipped' : 'Done' ?>
                        </span>
                        <span class="text-muted small text-nowrap">
                            <?= $ct['completed_at'] ? date('d M, g:i a', strtotime($ct['completed_at'])) : '' ?>
                        </span>
                    </div>
                    <div class="fw-semibold text-truncate mb-1">
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= $ct['lead_id'] ?>" class="text-decoration-none text-dark">
                            <?= htmlspecialchars($ct['lead_name']) ?>
                        </a>
                    </div>
                    <div class="text-muted small mb-1">[<?= htmlspecialchars($ct['stage']) ?>] <?= htmlspecialchars($ct['title']) ?></div>
                    <?php if (!empty($ct['outcome'])): ?>
                    <div class="small text-success fw-semibold">Outcome: <?= htmlspecialchars($ct['outcome']) ?></div>
                    <?php endif; ?>
                    <div class="mt-2">
                        <a href="<?= BASE_URL ?>/workflow/detail?id=<?= $ct['id'] ?>"
                           class="btn btn-sm btn-outline-secondary py-1 px-2">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ── Task reassign (admin/manager/operation_manager) ───────────────────────────
document.querySelectorAll('.wf-assign-select').forEach(function(sel) {
    sel.addEventListener('change', async function() {
        const taskId = parseInt(this.dataset.taskId);
        const userId = parseInt(this.value) || null;
        const res = await apiFetch('/workflow/assign', {
            body: JSON.stringify({ task_id: taskId, user_id: userId }),
        });
        if (res.ok) {
            showToast('success', 'Task assigned to ' + (res.assigned_name || 'user') + '.');
        } else {
            showToast('danger', res.message || 'Could not assign task.');
        }
    });
});

// ── Task due-date change (admin/manager only) ─────────────────────────────────
document.querySelectorAll('.wf-due-date-input').forEach(function(input) {
    const original = input.value;
    input.addEventListener('change', async function() {
        const taskId  = this.dataset.taskId;
        const dueDate = this.value || null;
        const res = await apiFetch('/workflow/set-due-date', {
            body: JSON.stringify({ task_id: parseInt(taskId), due_date: dueDate }),
        });
        if (res.ok) {
            showToast('success', 'Due date updated.');
        } else {
            showToast('danger', res.message || 'Could not update due date.');
            this.value = original;
        }
    });
});

document.querySelectorAll('.mark-done-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const id = this.dataset.id;
        const card = this.closest('.col-12');
        fetch('<?= BASE_URL ?>/reminders/done', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'id=' + encodeURIComponent(id)
                + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]').content)
        })
        .then(r => r.json())
        .then(function(data) {
            if (data.ok) {
                card.style.transition = 'opacity .3s';
                card.style.opacity = '0';
                setTimeout(() => card.remove(), 300);
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
