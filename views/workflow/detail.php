<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$isPending    = $task['status'] === 'pending';
$isDone       = $task['status'] === 'done';
$isSkipped    = $task['status'] === 'skipped';
$isClosed     = !$isPending;
$canComplete  = $isPending && ($isAdmin || (int) $task['assigned_to'] === $userId);
// Mirrors WorkflowController::completeTask(): tele-caller tasks are gated at any stage
$needsOutcome = $isPending && (
                 $task['assigned_role'] === 'tele_caller'
              || ($task['assigned_role'] === 'sales_rep'
                  && in_array($task['stage'], ['New', 'Visit Scheduled', 'Quoted'], true))
             );
$isOverdue    = $task['due_date'] && $task['due_date'] < date('Y-m-d');

$statusColor = $isDone ? 'success' : ($isSkipped ? 'secondary' : ($isOverdue ? 'danger' : 'primary'));
?>

<!-- Breadcrumb -->
<div class="mt-3 mb-3 d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/tasks" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Tasks
    </a>
    <span class="text-muted small">
        / <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>" class="text-decoration-none">
            <?= htmlspecialchars($task['lead_name']) ?>
        </a> / Task
    </span>
</div>

<div class="row g-3" style="max-width:900px">

    <!-- ── Task summary card ──────────────────────────────────────────────── -->
    <div class="col-12">
        <div class="card border-<?= $statusColor ?>" style="border-left-width:4px">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="badge bg-<?= $statusColor ?>"><?= ucfirst($task['status']) ?></span>
                            <span class="badge bg-light text-dark border small">Stage: <?= htmlspecialchars($task['stage']) ?></span>
                            <?php if ($task['assigned_role']): ?>
                            <span class="badge bg-light text-dark border small">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $task['assigned_role']))) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($task['title']) ?></h5>
                    </div>
                    <?php if ($task['due_date']): ?>
                    <div class="text-<?= $isOverdue && $isPending ? 'danger fw-semibold' : 'muted' ?> small text-nowrap">
                        <i class="bi bi-calendar3 me-1"></i>Due <?= date('d M Y', strtotime($task['due_date'])) ?>
                        <?php if ($isOverdue && $isPending): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Assignee / completion info -->
                <div class="d-flex flex-wrap gap-3 text-muted small mb-2">
                    <?php if ($task['assignee_name']): ?>
                    <span><i class="bi bi-person me-1"></i>
                        Assigned to <strong class="text-dark"><?= htmlspecialchars($task['assignee_name']) ?></strong>
                        (<?= htmlspecialchars(str_replace('_', ' ', $task['assignee_role'] ?? '')) ?>)
                    </span>
                    <?php else: ?>
                    <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                    <?php endif; ?>

                    <?php if ($isDone && $task['completer_name']): ?>
                    <span><i class="bi bi-check-circle me-1 text-success"></i>
                        Completed by <strong class="text-dark"><?= htmlspecialchars($task['completer_name']) ?></strong>
                        <?php if ($task['completed_at']): ?>on <?= date('d M Y, g:i a', strtotime($task['completed_at'])) ?><?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Outcome summary (when done) -->
                <?php if ($isDone && !empty($task['outcome'])): ?>
                <div class="alert alert-success py-2 mb-0">
                    <div class="fw-semibold"><i class="bi bi-check-circle me-1"></i>Outcome: <?= htmlspecialchars($task['outcome']) ?></div>
                    <?php if (!empty($task['outcome_site_visit_date'])): ?>
                    <div class="small mt-1">Site Visit Date: <strong><?= date('d M Y', strtotime($task['outcome_site_visit_date'])) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($task['outcome_next_visit_date'])): ?>
                    <div class="small mt-1">Next Visit Date: <strong><?= date('d M Y', strtotime($task['outcome_next_visit_date'])) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($task['outcome_lost_reason'])): ?>
                    <div class="small mt-1">Lost Reason: <strong><?= htmlspecialchars($task['outcome_lost_reason']) ?></strong></div>
                    <?php endif; ?>
                </div>
                <?php elseif ($isSkipped): ?>
                <div class="alert alert-secondary py-2 mb-0"><i class="bi bi-skip-forward me-1"></i>This task was skipped.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Lead info ──────────────────────────────────────────────────────── -->
    <div class="col-md-5">
        <div class="card h-100">
            <div class="card-header fw-semibold small py-2">
                <i class="bi bi-person-lines-fill me-1 text-primary"></i>Lead
            </div>
            <div class="card-body py-3">
                <div class="fw-semibold mb-1">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>" class="text-decoration-none">
                        <?= htmlspecialchars($task['lead_name']) ?>
                    </a>
                </div>
                <?php if ($task['lead_phone']): ?>
                <div class="mb-1 small"><a href="tel:<?= htmlspecialchars($task['lead_phone']) ?>" class="text-dark text-decoration-none">
                    <i class="bi bi-telephone me-1 text-success"></i><?= htmlspecialchars($task['lead_phone']) ?>
                </a></div>
                <?php endif; ?>
                <?php if (!empty($task['lead_email'])): ?>
                <div class="mb-1 small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($task['lead_email']) ?></div>
                <?php endif; ?>
                <?php if (!empty($task['lead_location'])): ?>
                <div class="mb-2 small text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($task['lead_location']) ?></div>
                <?php endif; ?>
                <span class="badge bg-light text-dark border small">Stage: <?= htmlspecialchars($task['lead_stage']) ?></span>

                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <?php $phone = preg_replace('/[^0-9+]/', '', $task['lead_phone'] ?? ''); ?>
                    <?php if ($phone): ?>
                    <a href="tel:<?= $phone ?>" class="btn btn-sm btn-outline-success py-1">
                        <i class="bi bi-telephone-fill me-1"></i>Call
                    </a>
                    <a href="https://wa.me/91<?= ltrim($phone, '+91') ?>" target="_blank"
                       class="btn btn-sm py-1" style="color:#25d366;border:1px solid #25d366">
                        <i class="bi bi-whatsapp me-1"></i>WhatsApp
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $task['lead_id'] ?>"
                       class="btn btn-sm btn-outline-primary py-1">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Lead
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Actions ────────────────────────────────────────────────────────── -->
    <?php if ($isPending): ?>
    <div class="col-md-7">
        <div class="card h-100">
            <div class="card-header fw-semibold small py-2">
                <i class="bi bi-lightning-charge-fill me-1 text-warning"></i>Actions
            </div>
            <div class="card-body py-3">

                <?php
                $taskRole  = $task['assigned_role'] ?? '';
                $taskStage = $task['stage'] ?? '';
                $isManagerTask = ($taskRole === 'manager');
                $isSRVisit     = ($taskRole === 'sales_rep' && $taskStage === 'Visit Scheduled');
                $isSRNew       = ($taskRole === 'sales_rep' && $taskStage === 'New');
                $isTCNew       = ($taskRole === 'tele_caller');   // any stage — see $needsOutcome
                $isSRQuoted    = ($taskRole === 'sales_rep' && $taskStage === 'Quoted');
                ?>

                <?php if ($isManagerTask && $canComplete): ?>
                <!-- ── Manager task: assign buttons + mark done ──────────────── -->
                <p class="text-muted small mb-3">Use the buttons below to assign this lead, then mark the task done.</p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTC">
                        <i class="bi bi-person-check me-1"></i>Assign Tele Caller
                    </button>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalSR">
                        <i class="bi bi-person-badge me-1"></i>Assign Sales Rep
                    </button>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalDoc">
                        <i class="bi bi-file-earmark-text me-1"></i>Start Documentation
                    </button>
                </div>
                <hr class="my-3">
                <button class="btn btn-success" id="simple-complete-btn" data-task-id="<?= $task['id'] ?>">
                    <i class="bi bi-check-lg me-1"></i>Mark Task Done
                </button>

                <?php elseif (($isSRVisit || $isSRNew || $isTCNew || $isSRQuoted) && $canComplete): ?>
                <!-- ── Outcome form ──────────────────────────────────────────── -->
                <form id="outcome-form" novalidate>
                    <input type="hidden" id="task-id-val" value="<?= $task['id'] ?>">

                    <?php if ($isTCNew): ?>
                    <p class="fw-semibold mb-2">Select an outcome:</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Arranged Site Visit" id="oc-sv">
                            <label class="form-check-label" for="oc-sv">
                                <span class="fw-semibold">Arranged Site Visit</span>
                                <small class="d-block text-muted">Customer agreed — enter the visit date</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Reschedule Call" id="oc-resched"
                                   <?= ($task['due_date'] && $task['due_date'] < date('Y-m-d')) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="oc-resched">
                                <span class="fw-semibold">Reschedule Call</span>
                                <small class="d-block text-muted">Couldn't reach them, or they asked to be called later —
                                    closes this task and sets a new one for the date you pick</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Lost" id="oc-lost">
                            <label class="form-check-label" for="oc-lost">
                                <span class="fw-semibold">Lead Lost</span>
                                <small class="d-block text-muted">Not interested — select a reason</small>
                            </label>
                        </div>
                    </div>
                    <div id="field-resched-date"
                         class="mb-3 <?= ($task['due_date'] && $task['due_date'] < date('Y-m-d')) ? '' : 'd-none' ?>">
                        <label class="form-label fw-semibold">Call again on <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inp-resched-date" name="outcome_next_visit_date"
                               min="<?= date('Y-m-d') ?>">
                        <?php if ($task['due_date'] && $task['due_date'] < date('Y-m-d')): ?>
                        <div class="form-text text-danger">
                            This task was due <?= htmlspecialchars(formatDate($task['due_date'])) ?>.
                        </div>
                        <?php endif; ?>
                    </div>
                    <div id="field-sv-date" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Site Visit Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inp-sv-date" name="outcome_site_visit_date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div id="field-lost-reason" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Lost Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="inp-lost-reason" name="outcome_lost_reason">
                            <option value="">— Select reason —</option>
                            <?php foreach ($lostReasons as $lr): ?>
                            <option><?= htmlspecialchars($lr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php elseif ($isSRNew): ?>
                    <p class="fw-semibold mb-2">Select an outcome:</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Site Visit Pending" id="oc-svp">
                            <label class="form-check-label" for="oc-svp">
                                <span class="fw-semibold">Site Visit Pending</span>
                                <small class="d-block text-muted">Visit planned but not yet done</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Site Visit Postponed" id="oc-svpost">
                            <label class="form-check-label" for="oc-svpost">
                                <span class="fw-semibold">Site Visit Postponed</span>
                                <small class="d-block text-muted">Rescheduled — enter new date</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Lost" id="oc-srlost">
                            <label class="form-check-label" for="oc-srlost">
                                <span class="fw-semibold">Lead Lost</span>
                                <small class="d-block text-muted">Not interested — select a reason</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Won" id="oc-srwon">
                            <label class="form-check-label" for="oc-srwon">
                                <span class="fw-semibold">Lead Won</span>
                                <small class="d-block text-muted">Site visit completed successfully</small>
                            </label>
                        </div>
                    </div>
                    <div id="field-next-date" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Next Site Visit Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inp-next-date" name="outcome_next_visit_date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div id="field-sr-lost" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Lost Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="inp-sr-lost" name="outcome_lost_reason">
                            <option value="">— Select reason —</option>
                            <?php foreach ($lostReasons as $lr): ?>
                            <option><?= htmlspecialchars($lr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php elseif ($isSRVisit): ?>
                    <p class="fw-semibold mb-2">How did the site visit go?</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Site Visit Completed" id="oc-vs-done">
                            <label class="form-check-label" for="oc-vs-done">
                                <span class="fw-semibold">Site Visit Completed</span>
                                <small class="d-block text-muted">Visit done — move lead forward</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Won" id="oc-vs-won">
                            <label class="form-check-label" for="oc-vs-won">
                                <span class="fw-semibold">Lead Won</span>
                                <small class="d-block text-muted">Customer confirmed — mark as Won</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Site Visit Rescheduled" id="oc-vs-reschedule">
                            <label class="form-check-label" for="oc-vs-reschedule">
                                <span class="fw-semibold">Site Visit Rescheduled</span>
                                <small class="d-block text-muted">Customer rescheduled — enter new date</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Lost" id="oc-vs-lost">
                            <label class="form-check-label" for="oc-vs-lost">
                                <span class="fw-semibold">Lead Lost</span>
                                <small class="d-block text-muted">Not interested — select a reason</small>
                            </label>
                        </div>
                    </div>
                    <div id="field-next-date" class="mb-3 d-none">
                        <label class="form-label fw-semibold">New Visit Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inp-next-date" name="outcome_next_visit_date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <div id="field-sr-lost" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Lost Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="inp-sr-lost" name="outcome_lost_reason">
                            <option value="">— Select reason —</option>
                            <?php foreach ($lostReasons as $lr): ?>
                            <option><?= htmlspecialchars($lr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="field-quote" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Quote Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="inp-quote" name="quote_amount"
                               min="1" step="100" placeholder="e.g. 250000">
                        <div class="form-text">Saved on the lead and moves it to <strong>Quoted</strong>,
                            creating the follow-up task automatically.</div>
                    </div>

                    <?php elseif ($isSRQuoted): ?>
                    <p class="fw-semibold mb-2">How did the deal close?</p>
                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Won" id="oc-q-won">
                            <label class="form-check-label" for="oc-q-won">
                                <span class="fw-semibold">Lead Won</span>
                                <small class="d-block text-muted">Customer confirmed — starts delivery tasks</small>
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="outcome" value="Lead Lost" id="oc-q-lost">
                            <label class="form-check-label" for="oc-q-lost">
                                <span class="fw-semibold">Lead Lost</span>
                                <small class="d-block text-muted">Not proceeding — select a reason</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="outcome" value="Still Following Up" id="oc-q-follow">
                            <label class="form-check-label" for="oc-q-follow">
                                <span class="fw-semibold">Still Following Up</span>
                                <small class="d-block text-muted">Not decided yet — set the next follow-up date</small>
                            </label>
                        </div>
                    </div>
                    <div id="field-q-lost" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Lost Reason <span class="text-danger">*</span></label>
                        <select class="form-select" id="inp-q-lost" name="outcome_lost_reason">
                            <option value="">— Select reason —</option>
                            <?php foreach ($lostReasons as $lr): ?>
                            <option><?= htmlspecialchars($lr) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="field-q-next" class="mb-3 d-none">
                        <label class="form-label fw-semibold">Next follow-up date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="inp-q-next" name="outcome_next_visit_date" min="<?= date('Y-m-d') ?>">
                    </div>
                    <?php endif; ?>

                    <div id="form-error" class="alert alert-danger py-2 d-none small mb-2"></div>
                    <button type="submit" class="btn btn-success" id="submit-btn">
                        <i class="bi bi-check-lg me-1"></i>Complete Task
                    </button>
                </form>

                <?php elseif ($canComplete): ?>
                <p class="text-muted mb-3">Mark this task as done to advance the pipeline.</p>
                <button class="btn btn-success" id="simple-complete-btn" data-task-id="<?= $task['id'] ?>">
                    <i class="bi bi-check-lg me-1"></i>Mark Done
                </button>

                <?php elseif (!$canComplete && $task['assigned_to'] !== null): ?>
                <p class="text-muted">This task is assigned to someone else.</p>
                <?php else: ?>
                <p class="text-muted">This task is unassigned.</p>
                <?php endif; ?>

                <!-- Reassign (admin/manager, pending only) -->
                <?php if ($isAdmin && !empty($assignableUsers)): ?>
                <hr class="my-3">
                <label class="form-label fw-semibold small mb-1">
                    <i class="bi bi-person-check me-1"></i><?= $task['assigned_to'] ? 'Reassign' : 'Assign' ?> Task
                </label>
                <select class="form-select form-select-sm" id="reassign-select" style="max-width:320px">
                    <option value="">— <?= $task['assigned_to'] ? 'Reassign to...' : 'Assign to...' ?> —</option>
                    <?php foreach ($assignableUsers as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int)$task['assigned_to'] === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div id="assign-msg" class="mt-1 small d-none"></div>
                <?php endif; ?>

                <!-- Reschedule site visit (tele_caller / admin / manager) -->
                <?php
                $canReschedule = $isPending
                    && in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'tele_caller'], true)
                    && in_array($task['stage'] ?? '', ['New', 'Visit Scheduled', 'Contacted'], true);
                ?>
                <?php if ($canReschedule): ?>
                <hr class="my-3">
                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalReschedule">
                    <i class="bi bi-calendar-event me-1"></i>Reschedule Site Visit
                </button>
                <div class="text-muted small mt-1">Closes the current sales rep task and creates a new one with the updated date.</div>
                <?php endif; ?>

                <!-- Skip (admin/manager only) -->
                <?php if ($isAdmin): ?>
                <hr class="my-3">
                <button class="btn btn-sm btn-outline-secondary" id="skip-btn" data-task-id="<?= $task['id'] ?>">
                    <i class="bi bi-skip-forward me-1"></i>Skip Task
                </button>
                <div class="text-muted small mt-1">Skipping still advances the pipeline.</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Reopen + Edit (admin/manager, closed tasks only) ─────────────────── -->
    <?php if ($isAdmin && $isClosed): ?>
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning-subtle fw-semibold small py-2 border-warning">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Admin Actions
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    This task is <strong><?= htmlspecialchars($task['status']) ?></strong>.
                    Reopening resets it to pending so it can be actioned again.
                </p>

                <!-- Editable fields -->
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <label class="form-label small fw-semibold mb-1">Task Title</label>
                        <input type="text" class="form-control form-control-sm" id="edit-title"
                               value="<?= htmlspecialchars($task['title']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Due Date</label>
                        <input type="date" class="form-control form-control-sm" id="edit-due-date"
                               value="<?= htmlspecialchars($task['due_date'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold mb-1">Notes / Description</label>
                        <textarea class="form-control form-control-sm" id="edit-notes" rows="2"><?= htmlspecialchars($task['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <button class="btn btn-warning btn-sm fw-semibold" id="reopen-btn" data-task-id="<?= $task['id'] ?>">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Task
                    </button>
                    <span id="reopen-msg" class="small d-none"></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
(function () {
    const taskId = <?= (int) $task['id'] ?>;

    // ── Conditional fields (TC) ───────────────────────────────────────────────
    document.querySelectorAll('[name="outcome"]').forEach(r => {
        r.addEventListener('change', function () {
            const v = this.value;
            document.getElementById('field-sv-date')    ?.classList.toggle('d-none', v !== 'Arranged Site Visit');
            document.getElementById('field-lost-reason')?.classList.toggle('d-none', v !== 'Lead Lost');
            document.getElementById('field-resched-date')?.classList.toggle('d-none', v !== 'Reschedule Call');
            document.getElementById('field-next-date')  ?.classList.toggle('d-none',
                v !== 'Site Visit Postponed' && v !== 'Site Visit Rescheduled');
            document.getElementById('field-sr-lost')    ?.classList.toggle('d-none', v !== 'Lead Lost');
            document.getElementById('field-quote')      ?.classList.toggle('d-none', v !== 'Site Visit Completed');
            document.getElementById('field-q-lost')     ?.classList.toggle('d-none', v !== 'Lead Lost');
            document.getElementById('field-q-next')     ?.classList.toggle('d-none', v !== 'Still Following Up');
        });
    });

    // ── Outcome form submit ───────────────────────────────────────────────────
    document.getElementById('outcome-form')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const errEl = document.getElementById('form-error');
        const btn   = document.getElementById('submit-btn');
        const data  = new FormData(this);
        const body  = { task_id: taskId };

        const outcome = data.get('outcome');
        if (!outcome) { return showErr('Please select an outcome.'); }
        body.outcome = outcome;

        if (outcome === 'Arranged Site Visit') {
            const d = data.get('outcome_site_visit_date');
            if (!d) return showErr('Site Visit Date is required.');
            body.outcome_site_visit_date = d;
        }
        if (outcome === 'Reschedule Call') {
            const d = data.get('outcome_next_visit_date');
            if (!d) return showErr('Choose the date to call the customer again.');
            body.outcome_next_visit_date = d;
        }
        if (outcome === 'Site Visit Postponed' || outcome === 'Site Visit Rescheduled') {
            const d = data.get('outcome_next_visit_date');
            if (!d) return showErr('A new visit date is required.');
            body.outcome_next_visit_date = d;
        }
        if (outcome === 'Lead Lost') {
            const r = data.get('outcome_lost_reason');
            if (!r) return showErr('Lost Reason is required.');
            body.outcome_lost_reason = r;
        }
        if (outcome === 'Site Visit Completed') {
            const q = data.get('quote_amount');
            if (!q || parseFloat(q) <= 0) return showErr('Quote amount is required to complete the site visit.');
            body.quote_amount = q;
        }
        if (outcome === 'Still Following Up') {
            const d = data.get('outcome_next_visit_date');
            if (!d) return showErr('Next follow-up date is required.');
            body.outcome_next_visit_date = d;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        const res = await apiFetch('/workflow/complete', { body: JSON.stringify(body) });
        if (res.ok) {
            window.location.href = window.location.origin + '<?= BASE_URL ?>/tasks';
        } else {
            showErr(res.error || res.message || 'Could not complete task.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Complete Task';
        }

        function showErr(msg) {
            errEl.textContent = msg;
            errEl.classList.remove('d-none');
        }
    });

    // ── Simple complete ───────────────────────────────────────────────────────
    document.getElementById('simple-complete-btn')?.addEventListener('click', async function () {
        if (!confirm('Mark this task as done?')) return;
        this.disabled = true;
        const res = await apiFetch('/workflow/complete', { body: JSON.stringify({ task_id: taskId }) });
        if (res.ok) {
            window.location.href = window.location.origin + '<?= BASE_URL ?>/tasks';
        } else {
            alert(res.error || res.message || 'Could not complete task.');
            this.disabled = false;
        }
    });

    // ── Skip ──────────────────────────────────────────────────────────────────
    document.getElementById('skip-btn')?.addEventListener('click', async function () {
        if (!confirm('Skip this task? The pipeline will still advance.')) return;
        this.disabled = true;
        const res = await apiFetch('/workflow/skip', { body: JSON.stringify({ task_id: taskId }) });
        if (res.ok) {
            window.location.href = window.location.origin + '<?= BASE_URL ?>/tasks';
        } else {
            alert(res.message || 'Could not skip task.');
            this.disabled = false;
        }
    });

    // ── Reopen task ───────────────────────────────────────────────────────────
    document.getElementById('reopen-btn')?.addEventListener('click', async function () {
        if (!confirm('Reopen this task? It will be set back to pending so it can be actioned again.')) return;
        const btn  = this;
        const msg  = document.getElementById('reopen-msg');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Reopening…';

        // Gather edited fields
        const title   = document.getElementById('edit-title')?.value.trim() || null;
        const dueDate = document.getElementById('edit-due-date')?.value || null;
        const notes   = document.getElementById('edit-notes')?.value.trim() || null;

        const res = await apiFetch('/workflow/reopen', {
            body: JSON.stringify({ task_id: taskId, title, due_date: dueDate, notes }),
        });
        if (res.ok) {
            msg.textContent = 'Task reopened — reloading…';
            msg.className = 'small text-success';
            msg.classList.remove('d-none');
            setTimeout(() => location.reload(), 800);
        } else {
            msg.textContent = res.error || 'Could not reopen task.';
            msg.className = 'small text-danger';
            msg.classList.remove('d-none');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>Reopen Task';
        }
    });

    // ── Reassign ──────────────────────────────────────────────────────────────
    document.getElementById('reassign-select')?.addEventListener('change', async function () {
        const msgEl = document.getElementById('assign-msg');
        const res   = await apiFetch('/workflow/assign', {
            body: JSON.stringify({ task_id: taskId, user_id: parseInt(this.value) || null }),
        });
        msgEl.classList.remove('d-none', 'text-success', 'text-danger');
        if (res.ok) {
            msgEl.textContent = 'Assigned to ' + (res.assigned_name || 'user') + '.';
            msgEl.classList.add('text-success');
            setTimeout(() => location.reload(), 800);
        } else {
            msgEl.textContent = res.message || 'Could not assign task.';
            msgEl.classList.add('text-danger');
        }
    });
})();
</script>

<?php if ($isAdmin): ?>
<!-- ── Assign Tele Caller Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="modalTC" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-check me-1"></i>Assign Tele Caller</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Select User</label>
                <select class="form-select form-select-sm mb-3" id="am-tc-user">
                    <option value="">— Select —</option>
                    <?php foreach ($teleCallerUsers ?? [] as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($teleCallerUsers ?? [])): ?>
                <div class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No active tele callers found.</div>
                <?php endif; ?>
                <label class="form-label small fw-semibold">Target Date</label>
                <input type="date" class="form-control form-control-sm" id="am-tc-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm" onclick="amConfirm(this,'tele_caller','am-tc-user','am-tc-date')">Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Assign Sales Rep Modal ──────────────────────────────────────────── -->
<div class="modal fade" id="modalSR" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-person-badge me-1"></i>Assign Sales Rep</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Select User</label>
                <select class="form-select form-select-sm mb-3" id="am-sr-user">
                    <option value="">— Select —</option>
                    <?php foreach ($salesRepUsers ?? [] as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($salesRepUsers ?? [])): ?>
                <div class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No active sales reps found.</div>
                <?php endif; ?>
                <label class="form-label small fw-semibold">Target Date</label>
                <input type="date" class="form-control form-control-sm" id="am-sr-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm" onclick="amConfirm(this,'sales_rep','am-sr-user','am-sr-date')">Assign</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Start Documentation Modal ──────────────────────────────────────── -->
<div class="modal fade" id="modalDoc" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-1"></i>Start Documentation</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Select User</label>
                <select class="form-select form-select-sm mb-3" id="am-doc-user">
                    <option value="">— Select —</option>
                    <?php foreach ($documentationUsers ?? [] as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($documentationUsers ?? [])): ?>
                <div class="text-warning small"><i class="bi bi-exclamation-triangle me-1"></i>No active documentation users found.</div>
                <?php endif; ?>
                <label class="form-label small fw-semibold">Target Date</label>
                <input type="date" class="form-control form-control-sm" id="am-doc-date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary btn-sm" onclick="amConfirm(this,'documentation','am-doc-user','am-doc-date')">Assign</button>
            </div>
        </div>
    </div>
</div>

<script>
async function amConfirm(btn, taskType, srcId, dateId) {
    const userId  = parseInt(document.getElementById(srcId)?.value) || null;
    const dueDate = document.getElementById(dateId)?.value || null;
    if (!userId) { alert('Please select a user.'); return; }

    btn.disabled = true;
    const res = await apiFetch('/workflow/create-task', {
        body: JSON.stringify({
            lead_id:          <?= (int) $task['lead_id'] ?>,
            task_type:        taskType,
            assigned_user_id: userId,
            due_date:         dueDate,
        }),
    });
    btn.disabled = false;

    if (res.ok) {
        bootstrap.Modal.getInstance(btn.closest('.modal'))?.hide();
        location.reload();
    } else {
        alert(res.message || 'Could not create task.');
    }
}
</script>
<?php endif; ?>

<!-- ── Reschedule Site Visit Modal ─────────────────────────────────────────── -->
<div class="modal fade" id="modalReschedule" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-calendar-event me-1"></i>Reschedule Site Visit</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">The current sales rep task will be closed and a new one created for the new date.</p>
                <label class="form-label fw-semibold small">New Visit Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" id="reschedule-date">
                <div id="reschedule-msg" class="mt-2 small text-danger d-none"></div>
            </div>
            <div class="modal-footer py-2">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-warning btn-sm" id="reschedule-confirm-btn">Reschedule</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const confirmBtn = document.getElementById('reschedule-confirm-btn');
    if (!confirmBtn) return;
    confirmBtn.addEventListener('click', async function () {
        const newDate = document.getElementById('reschedule-date').value;
        const msgEl   = document.getElementById('reschedule-msg');
        if (!newDate) { msgEl.textContent = 'Please select a date.'; msgEl.classList.remove('d-none'); return; }
        confirmBtn.disabled = true;
        const res = await apiFetch('/workflow/reschedule-visit', {
            body: JSON.stringify({ lead_id: <?= (int) $task['lead_id'] ?>, new_date: newDate }),
        });
        confirmBtn.disabled = false;
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('modalReschedule'))?.hide();
            location.reload();
        } else {
            msgEl.textContent = res.message || 'Could not reschedule.';
            msgEl.classList.remove('d-none');
        }
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
