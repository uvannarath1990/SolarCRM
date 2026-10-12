<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-folder-check me-2"></i>Pending Documentation</h5>
    <span class="text-muted small"><?= $total ?> lead<?= $total !== 1 ? 's' : '' ?> pending</span>
</div>

<!-- Summary tiles -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 border-danger">
            <div class="fs-4 fw-bold text-danger"><?= $overdue ?></div>
            <div class="small text-muted">Overdue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 border-warning">
            <div class="fs-4 fw-bold text-warning"><?= $dueToday ?></div>
            <div class="small text-muted">Due Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2">
            <div class="fs-4 fw-bold text-secondary"><?= $upcoming ?></div>
            <div class="small text-muted">Upcoming</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center py-2 border-secondary">
            <div class="fs-4 fw-bold text-dark"><?= $noTask ?></div>
            <div class="small text-muted">No Task Yet</div>
        </div>
    </div>
</div>

<?php if (empty($pendingDocs)): ?>
<div class="alert alert-success">
    <i class="bi bi-check-circle me-2"></i>
    All documentation is up to date — nothing pending!
</div>
<?php else: ?>

<!-- Desktop table -->
<div class="card d-none d-md-block">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Lead</th>
                    <th>Stage</th>
                    <th>Visit Completed</th>
                    <th>Task</th>
                    <th>Assigned To</th>
                    <th>Due</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingDocs as $row):
                $hasTask   = !empty($row['task_id']);
                $isOverdue = $hasTask && $row['due_date'] && $row['due_date'] < date('Y-m-d');
                $isToday   = $hasTask && $row['due_date'] === date('Y-m-d');
            ?>
            <tr class="<?= $isOverdue ? 'table-danger' : ($isToday ? 'table-warning' : '') ?>">
                <td>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $row['lead_id'] ?>" class="fw-semibold text-decoration-none">
                        <?= htmlspecialchars($row['lead_name']) ?>
                    </a>
                    <?php if ($row['phone']): ?>
                    <br>
                    <?php if (canSeePhone($row)): ?>
                    <span class="text-muted"><?= htmlspecialchars($row['phone']) ?></span>
                    <?php else: ?>
                    <span class="text-muted"><i class="bi bi-lock me-1"></i><?= htmlspecialchars(maskPhone($row['phone'])) ?></span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($row['location']): ?>
                    <br><span class="text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($row['location']) ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge <?= stageBadgeClass($row['stage']) ?>"><?= htmlspecialchars($row['stage']) ?></span></td>
                <td class="text-muted">
                    <?= $row['site_visit_completed_date'] ? formatDate($row['site_visit_completed_date']) : '—' ?>
                </td>
                <td>
                    <?php if ($hasTask): ?>
                        <?= htmlspecialchars($row['task_title']) ?>
                    <?php else: ?>
                        <span class="text-warning fw-semibold">
                            <i class="bi bi-exclamation-triangle me-1"></i>Not assigned yet
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($hasTask): ?>
                        <?php if ($isAdmin && !empty($docUsers)): ?>
                        <select class="form-select form-select-sm doc-reassign" style="min-width:140px"
                                data-task-id="<?= $row['task_id'] ?>">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($docUsers as $du): ?>
                            <option value="<?= $du['id'] ?>" <?= (int)$row['assigned_to'] === (int)$du['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($du['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <span class="<?= $row['assigned_to'] ? '' : 'text-warning fw-semibold' ?>">
                            <?= $row['assignee_name'] ? htmlspecialchars($row['assignee_name']) : '<i class="bi bi-exclamation-triangle me-1"></i>Unassigned' ?>
                        </span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="<?= $isOverdue ? 'text-danger fw-semibold' : ($isToday ? 'text-warning fw-semibold' : 'text-muted') ?>">
                    <?= ($hasTask && $row['due_date']) ? formatDate($row['due_date']) : '—' ?>
                </td>
                <td class="text-end">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $row['lead_id'] ?>#tabPipeline"
                       class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile cards -->
<div class="d-md-none">
    <?php foreach ($pendingDocs as $row):
        $hasTask      = !empty($row['task_id']);
        $isOverdue    = $hasTask && $row['due_date'] && $row['due_date'] < date('Y-m-d');
        $isToday      = $hasTask && $row['due_date'] === date('Y-m-d');
        $borderColour = !$hasTask ? 'secondary' : ($isOverdue ? 'danger' : ($isToday ? 'warning' : 'primary'));
    ?>
    <div class="card mb-2 border-<?= $borderColour ?>" style="border-left-width:3px">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div style="min-width:0">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $row['lead_id'] ?>#tabPipeline"
                       class="fw-semibold text-decoration-none d-block text-truncate">
                        <?= htmlspecialchars($row['lead_name']) ?>
                    </a>
                    <?php if ($row['phone']): ?>
                    <span class="text-muted small">
                        <?php if (canSeePhone($row)): ?>
                        <?= htmlspecialchars($row['phone']) ?>
                        <?php else: ?>
                        <i class="bi bi-lock me-1"></i><?= htmlspecialchars(maskPhone($row['phone'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($row['location']): ?>
                    <div class="text-muted small text-truncate">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($row['location']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="badge <?= stageBadgeClass($row['stage']) ?> flex-shrink-0">
                    <?= htmlspecialchars($row['stage']) ?>
                </span>
            </div>

            <div class="mt-1 small">
                <?php if ($hasTask): ?>
                <div class="text-muted">
                    <i class="bi bi-person me-1"></i>
                    <?= htmlspecialchars($row['assignee_name'] ?? 'Unassigned') ?>
                </div>
                <?php else: ?>
                <div class="text-warning fw-semibold">
                    <i class="bi bi-exclamation-triangle me-1"></i>No task assigned yet — open lead to start documentation
                </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 mt-2 small flex-wrap align-items-center">
                <?php if ($row['site_visit_completed_date']): ?>
                <span class="text-muted">
                    <i class="bi bi-check2-circle me-1 text-success"></i>Visit: <?= formatDate($row['site_visit_completed_date']) ?>
                </span>
                <?php endif; ?>
                <?php if ($hasTask && $row['due_date']): ?>
                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : ($isToday ? 'text-warning fw-semibold' : 'text-muted') ?>">
                    <i class="bi bi-calendar3 me-1"></i><?= formatDate($row['due_date']) ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="mt-2 d-flex justify-content-end">
                <a href="<?= BASE_URL ?>/leads/detail?id=<?= $row['lead_id'] ?>#tabPipeline"
                   class="btn btn-sm btn-outline-primary py-0 px-3">
                    Open Lead <i class="bi bi-chevron-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isAdmin && !empty($docUsers)): ?>
<script>
document.querySelectorAll('.doc-reassign').forEach(sel => {
    sel.addEventListener('change', async function () {
        const taskId = parseInt(this.dataset.taskId);
        const userId = parseInt(this.value) || null;
        const res = await apiFetch('/workflow/assign', {
            body: JSON.stringify({ task_id: taskId, user_id: userId }),
        });
        if (res.ok) {
            showToast('success', 'Task assigned to ' + (res.assigned_name || 'user') + '.');
        } else {
            showToast('danger', res.error || 'Could not reassign task.');
        }
    });
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
