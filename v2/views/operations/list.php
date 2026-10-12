<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-tools me-2"></i>Operations</h5>
    <?php if (hasRole('admin', 'sales_rep')): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addOpModal">
        <i class="bi bi-plus-lg me-1"></i>Schedule Operation
    </button>
    <?php endif; ?>
</div>

<!-- Search + filter row -->
<form method="GET" action="<?= BASE_URL ?>/operations" class="d-flex gap-2 mb-3">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="search" name="q" class="form-control form-control-sm" style="max-width:260px"
           placeholder="Search name or phone…" value="<?= htmlspecialchars($q ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
    <?php if (!empty($q)): ?>
    <a href="<?= BASE_URL ?>/operations?status=<?= htmlspecialchars($status) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- Status filter tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/operations?status=pending<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">
            Pending / Overdue
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'done' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/operations?status=done<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">
            Completed
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'all' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/operations?status=all<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">
            All
        </a>
    </li>
</ul>

<!-- Operations table -->
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Team</th>
                    <th>Scheduled</th>
                    <th>Status</th>
                    <th>Done On</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($operations as $op):
                $isOverdue = !$op['done_at'] && $op['scheduled_at'] < date('Y-m-d');
                $daysOverdue = $isOverdue ? (int) round((time() - strtotime($op['scheduled_at'])) / 86400) : null;
                $daysUntil  = (!$op['done_at'] && $op['scheduled_at'] >= date('Y-m-d'))
                    ? (int) round((strtotime($op['scheduled_at']) - time()) / 86400) : null;
            ?>
            <tr id="op-row-<?= $op['id'] ?>" class="<?= $isOverdue ? 'table-danger' : '' ?>">
                <td>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $op['lead_id'] ?>" class="fw-semibold text-decoration-none">
                        <?= htmlspecialchars($op['lead_name']) ?>
                    </a>
                    <?php if ($op['lead_phone']): ?>
                    <div class="text-muted"><?= htmlspecialchars($op['lead_phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($op['lead_location']): ?>
                    <div class="text-muted small"><?= htmlspecialchars($op['lead_location']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($op['operation_type']) ?></td>
                <td class="text-muted small"><?= $op['team_name'] ? htmlspecialchars($op['team_name']) : '—' ?></td>
                <td class="text-nowrap"><?= formatDate($op['scheduled_at']) ?></td>
                <td>
                    <?php if ($op['done_at']): ?>
                        <span class="badge bg-success">Done</span>
                    <?php elseif ($isOverdue): ?>
                        <span class="badge bg-danger">Overdue <?= $daysOverdue ?>d</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">In <?= $daysUntil ?>d</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= $op['done_at'] ? formatDate($op['done_at']) : '—' ?></td>
                <td class="text-muted"><?= $op['notes'] ? htmlspecialchars(mb_strimwidth($op['notes'], 0, 60, '…')) : '' ?></td>
                <td class="text-nowrap">
                    <?php if (!$op['done_at'] && hasRole('admin', 'installer', 'sales_rep')): ?>
                    <button class="btn btn-sm btn-outline-success mark-done-btn py-0"
                            data-id="<?= $op['id'] ?>"
                            data-interval="<?= (int)$op['interval_days'] ?>"
                            data-type="<?= htmlspecialchars($op['operation_type']) ?>">
                        <i class="bi bi-check-circle me-1"></i>Mark Done
                    </button>
                    <?php endif; ?>
                    <?php if (hasRole('admin')): ?>
                    <button class="btn btn-sm btn-outline-danger delete-op-btn py-0 ms-1"
                            data-id="<?= $op['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($operations)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No operations found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Add Operation Modal ──────────────────────────────────────────────── -->
<?php if (hasRole('admin', 'sales_rep')): ?>
<div class="modal fade" id="addOpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Operation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Customer (Won lead) <span class="text-danger">*</span></label>
                    <select id="opLeadId" class="form-select form-select-sm" required>
                        <option value="">— select —</option>
                        <?php foreach ($wonLeads as $wl): ?>
                        <option value="<?= $wl['id'] ?>"><?= htmlspecialchars($wl['name']) ?>
                            <?= $wl['phone'] ? ' — ' . htmlspecialchars($wl['phone']) : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Type</label>
                    <input type="text" id="opType" class="form-control form-control-sm" value="Cleaning">
                </div>
                <div class="mb-3">
                    <label class="form-label">Team</label>
                    <select id="opTeamId" class="form-select form-select-sm">
                        <option value="">— None —</option>
                        <?php foreach ($teams as $tm): ?>
                        <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-8">
                        <label class="form-label">Scheduled Date <span class="text-danger">*</span></label>
                        <input type="date" id="opDate" class="form-control form-control-sm"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Interval (days)</label>
                        <input type="number" id="opInterval" class="form-control form-control-sm" value="120" min="0">
                    </div>
                </div>
                <div class="mb-3 mt-2">
                    <label class="form-label">Notes</label>
                    <textarea id="opNotes" class="form-control form-control-sm" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveOpBtn" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Mark Done Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="markDoneModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Done</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="doneOpId">
                <div class="mb-3">
                    <label class="form-label">Date Completed</label>
                    <input type="date" id="doneDate" class="form-control form-control-sm"
                           value="<?= date('Y-m-d') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea id="doneNotes" class="form-control form-control-sm" rows="2"
                              placeholder="Optional notes…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="confirmDoneBtn" class="btn btn-success btn-sm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
'use strict';
document.addEventListener('DOMContentLoaded', () => {

    // ── Mark Done ────────────────────────────────────────────────────────────
    const markDoneModal = new bootstrap.Modal(document.getElementById('markDoneModal'));

    document.querySelectorAll('.mark-done-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('doneOpId').value = btn.dataset.id;
            document.getElementById('doneDate').value = '<?= date('Y-m-d') ?>';
            document.getElementById('doneNotes').value = '';
            markDoneModal.show();
        });
    });

    document.getElementById('confirmDoneBtn').addEventListener('click', async () => {
        const id    = document.getElementById('doneOpId').value;
        const doneAt = document.getElementById('doneDate').value;
        const notes = document.getElementById('doneNotes').value;

        const res = await apiFetch('/operations/done', {
            method: 'POST',
            body: JSON.stringify({ id: parseInt(id), done_at: doneAt, notes }),
        });

        markDoneModal.hide();
        if (res.ok) {
            // Update row UI
            const row = document.getElementById('op-row-' + id);
            if (row) {
                row.classList.remove('table-danger');
                row.querySelector('.mark-done-btn')?.remove();
                const statusCell = row.cells[3];
                if (statusCell) statusCell.innerHTML = '<span class="badge bg-success">Done</span>';
                const doneCell = row.cells[4];
                if (doneCell) {
                    const d = new Date(res.done_at + 'T00:00:00');
                    doneCell.textContent = d.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
                }
            }
            // Append next scheduled row if auto-created
            if (res.next_id && res.next_scheduled) {
                showToast('success', res.operation_type + ' marked done. Next scheduled: ' + res.next_scheduled);
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('success', 'Operation marked as done.');
            }
        } else {
            showToast('danger', res.error ?? 'Could not mark done.');
        }
    });

    // ── Add Operation ────────────────────────────────────────────────────────
    const saveOpBtn = document.getElementById('saveOpBtn');
    if (saveOpBtn) {
        saveOpBtn.addEventListener('click', async () => {
            const leadId  = parseInt(document.getElementById('opLeadId').value);
            const type    = document.getElementById('opType').value.trim() || 'Cleaning';
            const teamId  = parseInt(document.getElementById('opTeamId').value) || 0;
            const date    = document.getElementById('opDate').value;
            const interval = parseInt(document.getElementById('opInterval').value) || 120;
            const notes   = document.getElementById('opNotes').value.trim();

            if (!leadId || !date) {
                showToast('danger', 'Customer and date are required.');
                return;
            }

            const res = await apiFetch('/operations/save', {
                method: 'POST',
                body: JSON.stringify({
                    lead_id: leadId, team_id: teamId || null, operation_type: type,
                    scheduled_at: date, interval_days: interval, notes,
                }),
            });

            if (res.ok) {
                bootstrap.Modal.getInstance(document.getElementById('addOpModal')).hide();
                showToast('success', 'Operation scheduled.');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('danger', res.error ?? 'Could not save.');
            }
        });
    }

    // ── Delete ───────────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-op-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this operation?')) return;
            const res = await apiFetch('/operations/delete', {
                method: 'POST',
                body: JSON.stringify({ id: parseInt(btn.dataset.id) }),
            });
            if (res.ok) {
                document.getElementById('op-row-' + btn.dataset.id)?.remove();
            } else {
                showToast('danger', res.error ?? 'Could not delete.');
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
