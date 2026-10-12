<?php require __DIR__ . '/../layout/header.php';

$today = date('Y-m-d');

// Totals describe every task matching the filter, not just this page
$cOverdue    = (int) ($totals['overdue']    ?? 0);
$cToday      = (int) ($totals['due_today']  ?? 0);
$cUnassigned = (int) ($totals['unassigned'] ?? 0);
$cNoDate     = (int) ($totals['no_date']    ?? 0);

$rowFrom = $total ? $paging['offset'] + 1 : 0;
$rowTo   = min($paging['offset'] + $paging['per_page'], $total);
$qs      = fn(array $extra = []) => htmlspecialchars(http_build_query(array_merge($_GET, $extra)));
?>

<style>
/* ── Task Review ──────────────────────────────────────────────────────────── */
.tr-wrap { --tr-line:#e5e7eb; }

.tr-head { position:sticky; top:0; z-index:3; background:#f8fafc; }
.tr-head th {
    font-size:.68rem; font-weight:600; letter-spacing:.03em; text-transform:uppercase;
    color:#64748b; border-bottom:1px solid var(--tr-line) !important; white-space:nowrap;
    padding:.5rem .5rem;
}

.tr-table td { padding:.3rem .5rem; vertical-align:middle; border-color:#f1f5f9; }
.tr-row:hover  { background:#f8fafc; }
.tr-row.is-dirty { background:#fffbeb; }
.tr-row.is-dirty:hover { background:#fef9e7; }

/* Severity shows as a left edge, not a full-row wash — so it still reads
   as a signal when most of the list is late. */
.tr-row > td:first-child { border-left:3px solid transparent; }
.tr-row.sev-1 > td:first-child { border-left-color:#fcd34d; }
.tr-row.sev-2 > td:first-child { border-left-color:#fb923c; }
.tr-row.sev-3 > td:first-child { border-left-color:#ef4444; }

/* Inputs stay quiet until you interact with them */
.tr-input {
    border:1px solid transparent; background:transparent; border-radius:6px;
    box-shadow:none !important; font-size:.82rem; padding:.25rem .4rem; width:100%;
    transition:border-color .12s, background-color .12s;
}
.tr-input:hover  { border-color:var(--tr-line); background:#fff; }
.tr-input:focus  { border-color:#60a5fa; background:#fff; outline:none; }
select.tr-input  { appearance:none; padding-right:1.2rem;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2394a3b8'%3E%3Cpath d='M4.5 6.5 8 10l3.5-3.5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right .25rem center; background-size:14px; }
.tr-input::placeholder { color:#cbd5e1; }

.tr-task  { font-weight:500; color:#0f172a; }
.tr-notes { font-size:.78rem; }

.tr-lead      { font-weight:600; font-size:.82rem; text-decoration:none; }
.tr-lead:hover{ text-decoration:underline; }
.tr-substage  { font-size:.62rem; color:#64748b; }

.tr-chip { font-size:.62rem; padding:.12rem .4rem; border-radius:4px; font-weight:600;
           background:#f1f5f9; color:#475569; white-space:nowrap; }
.tr-late { font-size:.66rem; font-weight:600; color:#dc2626; white-space:nowrap; }
.tr-due-today { font-size:.66rem; font-weight:600; color:#d97706; }

.tr-unassigned { color:#b45309 !important; }
.tr-dot { width:7px; height:7px; border-radius:50%; background:#f59e0b; display:inline-block; }

.tr-summary .chip {
    display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .6rem;
    border:1px solid var(--tr-line); border-radius:999px; font-size:.75rem; background:#fff;
}
.tr-savebar { position:sticky; bottom:0; z-index:4; }
.tr-date { font-size:.78rem; white-space:nowrap; font-variant-numeric:tabular-nums; }
.tr-sub  { font-size:.66rem; color:#64748b; }
</style>

<div class="tr-wrap">

<div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap gap-2">
    <div>
        <h5 class="mb-1 fw-bold"><i class="bi bi-list-check me-2"></i>Task Review</h5>
        <div class="tr-summary d-flex flex-wrap gap-2">
            <span class="chip"><strong><?= $rowFrom ?>–<?= $rowTo ?></strong> of <strong><?= $total ?></strong></span>
            <?php if ($cOverdue): ?>
            <span class="chip"><span class="text-danger">●</span> <strong><?= $cOverdue ?></strong> overdue</span>
            <?php endif; ?>
            <?php if ($cToday): ?>
            <span class="chip"><span class="text-warning">●</span> <strong><?= $cToday ?></strong> due today</span>
            <?php endif; ?>
            <?php if ($cUnassigned): ?>
            <span class="chip"><i class="bi bi-person-dash text-warning"></i> <strong><?= $cUnassigned ?></strong> unassigned</span>
            <?php endif; ?>
            <?php if ($cNoDate): ?>
            <span class="chip"><i class="bi bi-calendar-x text-muted"></i> <strong><?= $cNoDate ?></strong> no date</span>
            <?php endif; ?>
        </div>
    </div>
    <button id="saveAllBtn" class="btn btn-primary btn-sm" disabled>
        <i class="bi bi-save me-1"></i>Save Changes
        <span id="dirtyCount" class="badge bg-white text-primary ms-1">0</span>
    </button>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/tasks/review" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">Status</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['pending' => 'Pending', 'done' => 'Done', 'skipped' => 'Skipped', 'cancelled' => 'Cancelled', 'all' => 'All'] as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= $filterStatus === $sv ? 'selected' : '' ?>>
                        <?= $sl ?><?= isset($counts[$sv]) ? " ({$counts[$sv]})" : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">Owner</label>
                <select name="user" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">— All —</option>
                    <option value="-1" <?= $filterUser === -1 ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($allUsers as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted"
                       title="Filters on the stage that generated the task">Task Stage</label>
                <select name="stage" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All —</option>
                    <?php
                    $stageList = $stageOptions;
                    if ($filterStage !== '' && !in_array($filterStage, $stageList, true)) {
                        $stageList[] = $filterStage;
                    }
                    foreach ($stageList as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $filterStage === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-semibold mb-1 text-muted">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control"
                           placeholder="Lead name, phone, #ID or task…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="overdue" value="1"
                           id="overdueCheck" <?= $filterOverdue ? 'checked' : '' ?> onchange="this.form.submit()">
                    <label class="form-check-label small fw-semibold" for="overdueCheck">Overdue</label>
                </div>
                <button type="submit" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-funnel"></i></button>
            </div>
        </div>

        <div class="row g-2 align-items-end mt-1">
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">Date applies to</label>
                <select name="date_field" class="form-select form-select-sm">
                    <?php foreach (['created' => 'Created date', 'due' => 'Due date', 'completed' => 'Completed date'] as $dv => $dl): ?>
                    <option value="<?= $dv ?>" <?= $dateField === $dv ? 'selected' : '' ?>><?= $dl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1 text-muted">Sort by</label>
                <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach (['due' => 'Due date (soonest)', 'completed' => 'Recently completed',
                                    'created' => 'Recently created'] as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= $sort === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                <?php if ($dateFrom !== '' || $dateTo !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" title="Clear dates"
                   href="<?= BASE_URL ?>/tasks/review?<?= htmlspecialchars(http_build_query(array_filter([
                       'status' => $filterStatus, 'user' => $filterUser ?: null, 'stage' => $filterStage ?: null,
                       'overdue' => $filterOverdue ?: null, 'q' => $search ?: null,
                   ]))) ?>"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
            <?php if ($dateField === 'completed' && $filterStatus === 'pending'): ?>
            <div class="col-12 col-md-4">
                <div class="form-text text-warning small">
                    <i class="bi bi-info-circle me-1"></i>Pending tasks have no completed date —
                    set Status to <em>Done</em> or <em>All</em> to use this.
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if (empty($tasks)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
        <div class="fw-semibold">No tasks match these filters</div>
        <div class="small">Try widening the status or clearing the search.</div>
    </div>
</div>
<?php else: ?>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm tr-table align-middle mb-0" id="taskReviewTable">
            <thead class="tr-head">
                <tr>
                    <th style="min-width:260px">Task</th>
                    <th style="min-width:150px">Lead</th>
                    <th style="min-width:110px">Stage</th>
                    <th style="min-width:170px">Owner</th>
                    <th style="min-width:150px">Due</th>
                    <th style="min-width:110px">Status</th>
                    <th style="min-width:105px">Created</th>
                    <th style="min-width:130px">Completed</th>
                    <th style="min-width:170px">Notes</th>
                    <th style="width:24px"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $t):
                $isPending = $t['status'] === 'pending';
                $daysLate  = ($isPending && $t['due_date'] && $t['due_date'] < $today)
                    ? (int) floor((strtotime($today) - strtotime($t['due_date'])) / 86400) : 0;
                $sev = $daysLate >= 30 ? 3 : ($daysLate >= 7 ? 2 : ($daysLate > 0 ? 1 : 0));
                $isDueToday = $isPending && $t['due_date'] === $today;
            ?>
                <tr class="tr-row <?= $sev ? 'sev-' . $sev : '' ?>" data-task-id="<?= (int)$t['id'] ?>"
                    data-orig-title="<?= htmlspecialchars($t['title']) ?>"
                    data-orig-user="<?= (int)($t['assigned_to'] ?? 0) ?>"
                    data-orig-due="<?= htmlspecialchars($t['due_date'] ?? '') ?>"
                    data-orig-status="<?= htmlspecialchars($t['status']) ?>"
                    data-orig-notes="<?= htmlspecialchars($t['notes'] ?? '') ?>">

                    <td>
                        <input type="text" class="tr-input tr-task f-title"
                               value="<?= htmlspecialchars($t['title']) ?>"
                               title="<?= htmlspecialchars($t['title']) ?>">
                    </td>

                    <td>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int)$t['lead_id'] ?>"
                           class="tr-lead" target="_blank"><?= htmlspecialchars($t['lead_name']) ?></a>
                        <div class="tr-substage"><?= htmlspecialchars($t['lead_stage']) ?></div>
                    </td>

                    <td><span class="tr-chip"><?= htmlspecialchars($t['stage']) ?></span></td>

                    <td>
                        <select class="tr-input f-user <?= empty($t['assigned_to']) ? 'tr-unassigned' : '' ?>">
                            <option value="0">— Unassigned —</option>
                            <?php foreach ($allUsers as $u): ?>
                            <option value="<?= (int)$u['id'] ?>" <?= (int)($t['assigned_to'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?> · <?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td>
                        <input type="date" class="tr-input f-due" value="<?= htmlspecialchars($t['due_date'] ?? '') ?>">
                        <?php if ($daysLate): ?>
                        <div class="tr-late"><?= $daysLate ?>d late</div>
                        <?php elseif ($isDueToday): ?>
                        <div class="tr-due-today">due today</div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <select class="tr-input f-status">
                            <?php foreach (['pending' => 'Pending', 'done' => 'Done', 'skipped' => 'Skipped', 'cancelled' => 'Cancelled'] as $sv => $sl): ?>
                            <option value="<?= $sv ?>" <?= $t['status'] === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td class="tr-date">
                        <?php if (!empty($t['created_at'])): ?>
                        <?= htmlspecialchars(date('d M Y', strtotime($t['created_at']))) ?>
                        <div class="tr-sub"><?= htmlspecialchars(date('h:i A', strtotime($t['created_at']))) ?></div>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>

                    <td class="tr-date">
                        <?php if (!empty($t['completed_at'])): ?>
                        <?= htmlspecialchars(date('d M Y', strtotime($t['completed_at']))) ?>
                        <?php if ($t['was_on_time'] !== null): ?>
                        <span class="<?= (int)$t['was_on_time'] ? 'text-success' : 'text-danger' ?>"
                              title="<?= (int)$t['was_on_time'] ? 'Closed on time' : 'Closed after the due date' ?>">
                            <i class="bi <?= (int)$t['was_on_time'] ? 'bi-check-circle-fill' : 'bi-clock-history' ?>"></i>
                        </span>
                        <?php endif; ?>
                        <div class="tr-sub">
                            <?= htmlspecialchars(date('h:i A', strtotime($t['completed_at']))) ?>
                            <?= !empty($t['completer_name']) ? ' · ' . htmlspecialchars($t['completer_name']) : '' ?>
                        </div>
                        <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                    </td>

                    <td>
                        <input type="text" class="tr-input tr-notes f-notes"
                               value="<?= htmlspecialchars($t['notes'] ?? '') ?>" placeholder="Add a note…">
                    </td>

                    <td class="text-center">
                        <span class="dirty-dot d-none" title="Unsaved changes"><span class="tr-dot"></span></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center text-muted small mt-2 flex-wrap gap-2">
    <span>
        Showing <strong><?= $rowFrom ?>–<?= $rowTo ?></strong> of <strong><?= $total ?></strong>.
        Click any field to edit, then Save Changes.
        <span class="ms-2">Per page:
            <?php foreach ([50, 100, 200] as $pp): ?>
            <a href="?<?= $qs(['per_page' => $pp, 'page' => 1]) ?>"
               class="<?= (int)$paging['per_page'] === $pp ? 'fw-bold text-dark' : 'text-muted' ?> text-decoration-none ms-1"><?= $pp ?></a>
            <?php endforeach; ?>
        </span>
    </span>
    <span class="d-flex align-items-center gap-2" style="font-size:.72rem">
        Overdue:
        <span class="d-inline-flex align-items-center gap-1"><span style="display:inline-block;width:10px;height:3px;background:#fcd34d"></span> 1–6d</span>
        <span class="d-inline-flex align-items-center gap-1"><span style="display:inline-block;width:10px;height:3px;background:#fb923c"></span> 7–29d</span>
        <span class="d-inline-flex align-items-center gap-1"><span style="display:inline-block;width:10px;height:3px;background:#ef4444"></span> 30d+</span>
    </span>
</div>

<?php if ($paging['total_pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
        <li class="page-item <?= $paging['has_prev'] ? '' : 'disabled' ?>">
            <a class="page-link" href="?<?= $qs(['page' => $page - 1]) ?>">‹ Prev</a>
        </li>
        <?php for ($p = max(1, $page - 2); $p <= min($paging['total_pages'], $page + 2); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= $qs(['page' => $p]) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $paging['has_next'] ? '' : 'disabled' ?>">
            <a class="page-link" href="?<?= $qs(['page' => $page + 1]) ?>">Next ›</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

</div>

<script>
'use strict';
(function () {
    const saveBtn    = document.getElementById('saveAllBtn');
    const dirtyBadge = document.getElementById('dirtyCount');
    if (!saveBtn) return;

    function rowChanges(row) {
        const ch = {};
        const title  = row.querySelector('.f-title').value.trim();
        const user   = row.querySelector('.f-user').value;
        const due    = row.querySelector('.f-due').value;
        const status = row.querySelector('.f-status').value;
        const notes  = row.querySelector('.f-notes').value.trim();

        if (title  !== row.dataset.origTitle)  ch.title       = title;
        if (user   !== row.dataset.origUser)   ch.assigned_to = parseInt(user);
        if (due    !== row.dataset.origDue)    ch.due_date    = due || null;
        if (status !== row.dataset.origStatus) ch.status      = status;
        if (notes  !== row.dataset.origNotes)  ch.notes       = notes;
        return ch;
    }

    function refreshDirty() {
        let count = 0;
        document.querySelectorAll('.tr-row').forEach(row => {
            const dirty = Object.keys(rowChanges(row)).length > 0;
            row.querySelector('.dirty-dot').classList.toggle('d-none', !dirty);
            row.classList.toggle('is-dirty', dirty);
            if (dirty) count++;
        });
        dirtyBadge.textContent = count;
        saveBtn.disabled = count === 0;
    }

    document.querySelectorAll('.tr-row input, .tr-row select').forEach(el => {
        el.addEventListener('input',  refreshDirty);
        el.addEventListener('change', refreshDirty);
    });

    saveBtn.addEventListener('click', async () => {
        const changes = [];
        document.querySelectorAll('.tr-row').forEach(row => {
            const ch = rowChanges(row);
            if (Object.keys(ch).length > 0) {
                ch.task_id = parseInt(row.dataset.taskId);
                changes.push(ch);
            }
        });
        if (changes.length === 0) return;

        saveBtn.disabled  = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        const res = await apiFetch('/workflow/bulk-update', { body: JSON.stringify({ changes }) });

        if (res.ok) {
            if (res.errors && res.errors.length > 0) {
                alert('Saved ' + res.updated + ' task(s), but some failed:\n' + res.errors.join('\n'));
            }
            location.reload();
        } else {
            showToast('danger', res.error || 'Could not save changes.');
            saveBtn.disabled  = false;
            saveBtn.innerHTML = '<i class="bi bi-save me-1"></i>Save Changes ' +
                '<span id="dirtyCount" class="badge bg-white text-primary ms-1">' + changes.length + '</span>';
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (!saveBtn.disabled && !saveBtn.innerHTML.includes('Saving')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
