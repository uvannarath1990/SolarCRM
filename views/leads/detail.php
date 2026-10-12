<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-2">
    <div class="d-flex align-items-center gap-2 mb-1">
        <a href="<?= BASE_URL ?>/leads" class="btn btn-sm btn-outline-secondary flex-shrink-0"><i class="bi bi-arrow-left"></i></a>
        <h5 class="mb-0 fw-bold text-truncate"><?= htmlspecialchars($lead['name']) ?></h5>
        <span class="badge <?= stageBadgeClass($lead['stage']) ?> flex-shrink-0" id="stageBadge"><?= htmlspecialchars($lead['stage']) ?></span>
        <?php if (hasRole('admin')): ?>
        <button id="deleteLeadBtn" class="btn btn-sm btn-outline-danger ms-auto flex-shrink-0"
                data-id="<?= $lead['id'] ?>">
            <i class="bi bi-trash"></i>
        </button>
        <?php endif; ?>
    </div>
    <?php if (hasRole('admin', 'manager')): ?>
    <?php $nextStages = allowedNextStages($lead['stage'], $_SESSION['role']); ?>
    <?php if (!empty($nextStages)): ?>
    <div class="d-flex gap-1 align-items-center stage-move-row mt-1" style="overflow-x:auto;padding-bottom:2px">
        <small class="text-muted flex-shrink-0">Move to:</small>
        <?php foreach ($nextStages as $ns): ?>
        <button class="btn btn-sm btn-outline-secondary stage-move-btn py-0 px-2 flex-shrink-0"
                data-stage="<?= htmlspecialchars($ns) ?>" style="font-size:.78rem">
            <?= htmlspecialchars($ns) ?>
        </button>
        <?php endforeach; ?>
        <span class="text-muted ms-2 flex-shrink-0" style="font-size:.78rem">or</span>
        <select class="form-select form-select-sm py-0 flex-shrink-0" id="forceStageSelect" style="font-size:.78rem;width:auto;height:26px">
            <option value="">Force set stage…</option>
            <?php foreach (allStages() as $s): ?>
            <?php if ($s !== $lead['stage']): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <?php else: ?>
    <div class="d-flex gap-1 align-items-center mt-1">
        <small class="text-muted flex-shrink-0">Set stage:</small>
        <select class="form-select form-select-sm py-0 flex-shrink-0" id="forceStageSelect" style="font-size:.78rem;width:auto;height:26px">
            <option value="">Force set stage…</option>
            <?php foreach (allStages() as $s): ?>
            <?php if ($s !== $lead['stage']): ?>
            <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php endif; // end admin/manager stage controls ?>

    <?php
    // Sales rep: dedicated Won / Lost action buttons (no generic stage switching)
    $srAllowedNext = allowedNextStages($lead['stage'], 'sales_rep');
    $srCanWon  = in_array('Won',  $srAllowedNext, true);
    $srCanLost = in_array('Lost', $srAllowedNext, true);
    if (hasRole('sales_rep') && ($srCanWon || $srCanLost)):
    ?>
    <div class="d-flex gap-2 align-items-center mt-1">
        <?php if ($srCanWon): ?>
        <button class="btn btn-sm btn-success" id="markWonBtn">
            <i class="bi bi-trophy me-1"></i>Mark Won
        </button>
        <?php endif; ?>
        <?php if ($srCanLost): ?>
        <button class="btn btn-sm btn-outline-danger" id="markLostBtn">
            <i class="bi bi-x-circle me-1"></i>Mark Lost
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Pipeline stage bar -->
<?php
$allStagesList   = ['New','Contacted','Visit Scheduled','Site Visit in Progress',
                    'Site Visit Completed','Quoted','Won'];
$currentStageTop = $lead['stage'];
$currentIdxTop   = array_search($currentStageTop, $allStagesList, true);
?>
<div class="mb-3">
    <div class="d-flex align-items-center gap-0 pipeline-bar">
    <?php foreach ($allStagesList as $i => $s):
        $isDone    = $currentIdxTop !== false && $i < $currentIdxTop;
        $isCurrent = $s === $currentStageTop;
        $cls = $isDone ? 'done' : ($isCurrent ? 'current' : 'future');
    ?>
        <div class="pipeline-step <?= $cls ?>" title="<?= htmlspecialchars($s) ?>">
            <div class="pipeline-dot">
                <?php if ($isDone): ?><i class="bi bi-check-lg"></i>
                <?php elseif ($isCurrent): ?><i class="bi bi-record-circle"></i>
                <?php else: ?><i class="bi bi-circle"></i><?php endif; ?>
            </div>
            <div class="pipeline-label"><?= htmlspecialchars($s) ?></div>
        </div>
        <?php if ($i < count($allStagesList)-1): ?>
        <div class="pipeline-connector <?= $isDone ? 'done' : '' ?>"></div>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>
</div>

<?php if (hasRole('admin', 'manager')): ?>
<!-- Manager Action Panel -->
<div class="card mb-3 border-primary">
    <div class="card-header fw-semibold bg-white d-flex align-items-center gap-2">
        <i class="bi bi-lightning-charge-fill text-primary"></i> Actions
    </div>
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
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
    </div>
</div>
<?php endif; ?>

<!-- Quick action buttons -->
<div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
    <?php if ($lead['phone']): ?>
    <?php if (canSeePhone($lead)): ?>
    <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-telephone me-1"></i>Call
    </a>
    <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="bi bi-whatsapp me-1"></i>WhatsApp
    </a>
    <?php else: ?>
    <span class="btn btn-sm btn-outline-secondary disabled">
        <i class="bi bi-lock me-1"></i><?= htmlspecialchars(maskPhone($lead['phone'])) ?>
    </span>
    <small class="text-muted">Contact manager to reveal phone</small>
    <?php endif; ?>
    <?php if (hasRole('admin', 'manager')): ?>
    <button class="btn btn-sm <?= $lead['phone_revealed'] ? 'btn-warning' : 'btn-outline-warning' ?>"
            id="phoneRevealBtn" title="<?= $lead['phone_revealed'] ? 'Hide phone from sales rep' : 'Reveal phone to sales rep' ?>">
        <i class="bi bi-<?= $lead['phone_revealed'] ? 'eye-slash' : 'eye' ?> me-1"></i>
        <?= $lead['phone_revealed'] ? 'Hide from Rep' : 'Reveal to Rep' ?>
    </button>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (hasRole('admin','sales_rep') && in_array($lead['stage'], ['Visit Scheduled','Site Visit in Progress'], true)): ?>
    <a href="<?= BASE_URL ?>/site-visits/view?lead_id=<?= $lead['id'] ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-geo-alt me-1"></i>Site Visit
    </a>
    <?php endif; ?>
    <?php // Anyone who can open this lead can raise a follow-up on it ?>
    <button class="btn btn-sm btn-outline-primary" id="createFollowUpBtn">
        <i class="bi bi-calendar-plus me-1"></i>Create Follow-up
    </button>
</div>

<!-- ── Create follow-up ────────────────────────────────────────────────────── -->
<div class="modal fade" id="followUpModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i>Create Follow-up</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label fw-semibold small" for="fu-title">What needs doing?</label>
            <input type="text" class="form-control form-control-sm" id="fu-title"
                   value="Follow up with customer" maxlength="200">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small" for="fu-user">Assign to <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm" id="fu-user">
                <option value="">— Select a user —</option>
                <?php foreach ($allUsers as $u):
                    if ($u['role'] === 'partner') continue; ?>
                <option value="<?= (int)$u['id'] ?>"
                        <?= (int)$u['id'] === (int)$_SESSION['user_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small" for="fu-due">Due date</label>
            <input type="date" class="form-control form-control-sm" id="fu-due"
                   value="<?= date('Y-m-d', strtotime('+3 days')) ?>">
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold small" for="fu-notes">Notes</label>
            <textarea class="form-control form-control-sm" id="fu-notes" rows="2"
                      placeholder="Optional context for whoever picks this up"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="fuConfirmBtn">
            <i class="bi bi-check-lg me-1"></i>Create Follow-up
        </button>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
<!-- LEFT: Edit form -->
<div class="col-lg-7">
<div class="card">
    <div class="card-header fw-semibold bg-white">Lead Details</div>
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/leads/detail/save" novalidate class="lead-detail-form">
            <?= csrfField() ?>
            <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Name</label>
                    <input type="text" class="form-control form-control-sm" name="name"
                           value="<?= htmlspecialchars($lead['name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Phone</label>
                    <?php if (canSeePhone($lead)): ?>
                    <input type="tel" class="form-control form-control-sm" name="phone"
                           id="phoneInput"
                           value="<?= htmlspecialchars($lead['phone']) ?>"
                           pattern="[0-9]{10}" maxlength="10"
                           title="Enter exactly 10 digits" required>
                    <div id="phoneError" class="invalid-feedback"></div>
                    <?php else: ?>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm bg-light text-muted"
                               value="<?= htmlspecialchars(maskPhone($lead['phone'])) ?>" readonly disabled>
                    </div>
                    <?php // no hidden phone field — the real number must never reach the page source.
                          // LeadDetailController::save() preserves the stored phone when canSeePhone() is false. ?>
                    <div class="form-text text-muted" style="font-size:.72rem">Phone hidden — ask manager to reveal</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Email</label>
                    <input type="email" class="form-control form-control-sm" name="email"
                           value="<?= htmlspecialchars($lead['email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Location</label>
                    <input type="text" class="form-control form-control-sm" name="location"
                           value="<?= htmlspecialchars($lead['location'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Source</label>
                    <select class="form-select form-select-sm" name="source_id">
                        <option value="">— None —</option>
                        <?php foreach ($sources as $src): ?>
                        <option value="<?= $src['id'] ?>" <?= (int)($lead['source_id'] ?? 0) === (int)$src['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($src['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Assigned Rep</label>
                    <?php if (hasRole('admin', 'manager')): ?>
                    <select class="form-select form-select-sm" name="assigned_rep_id">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($reps as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= (int)($lead['assigned_rep_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <?php
                        $repName = '— Unassigned —';
                        foreach ($reps as $r) {
                            if ((int)$r['id'] === (int)($lead['assigned_rep_id'] ?? 0)) {
                                $repName = $r['name']; break;
                            }
                        }
                    ?>
                    <input type="hidden" name="assigned_rep_id" value="<?= (int)($lead['assigned_rep_id'] ?? 0) ?: '' ?>">
                    <input type="text" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($repName) ?>" readonly>
                    <?php endif; ?>
                </div>
                <?php if (hasRole('admin', 'manager')): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">
                        Tele Caller
                        <span class="text-muted fw-normal">(sales rep / tele caller)</span>
                    </label>
                    <select class="form-select form-select-sm" name="tele_caller_id">
                        <option value="">— None —</option>
                        <?php foreach ($teleCallers as $tc): ?>
                        <option value="<?= $tc['id'] ?>" <?= (int)($lead['tele_caller_id'] ?? 0) === (int)$tc['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tc['name']) ?>
                            (<?= htmlspecialchars(str_replace('_',' ',$tc['role'])) ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($teleCallers)): ?>
                        <option value="" disabled>No tele callers or sales reps yet</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Documentation User</label>
                    <select class="form-select form-select-sm" name="documentation_user_id">
                        <option value="">— None —</option>
                        <?php foreach ($documentationUsers as $du): ?>
                        <option value="<?= (int)$du['id'] ?>" <?= (int)($lead['documentation_user_id'] ?? 0) === (int)$du['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($du['name']) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($documentationUsers)): ?>
                        <option value="" disabled>No documentation users yet</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Monthly Bill (₹)</label>
                    <input type="number" class="form-control form-control-sm" name="bill_amount"
                           value="<?= htmlspecialchars($lead['bill_amount'] ?? '') ?>" min="0" step="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Potential kW</label>
                    <input type="number" class="form-control form-control-sm" name="potential_kw"
                           value="<?= htmlspecialchars($lead['potential_kw'] ?? '') ?>" min="0" step="0.1">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Quote (₹)</label>
                    <input type="number" class="form-control form-control-sm" name="quote_amount"
                           value="<?= htmlspecialchars($lead['quote_amount'] ?? '') ?>" min="0" step="1000">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Won Value (₹)</label>
                    <input type="number" class="form-control form-control-sm" name="won_value"
                           value="<?= htmlspecialchars($lead['won_value'] ?? '') ?>" min="0" step="1000">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Bank Process</label>
                    <select class="form-select form-select-sm" name="bank_process">
                        <option value=""         <?= ($lead['bank_process'] ?? '') === ''         ? 'selected' : '' ?>>— Not Set —</option>
                        <option value="Pending"  <?= ($lead['bank_process'] ?? '') === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="Complete" <?= ($lead['bank_process'] ?? '') === 'Complete' ? 'selected' : '' ?>>Complete</option>
                        <option value="Blocked"  <?= ($lead['bank_process'] ?? '') === 'Blocked'  ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">KSEB Registration Process</label>
                    <select class="form-select form-select-sm" name="kseb_process" id="ksebProcessSelect">
                        <option value=""         <?= ($lead['kseb_process'] ?? '') === ''         ? 'selected' : '' ?>>— Not Set —</option>
                        <option value="Pending"  <?= ($lead['kseb_process'] ?? '') === 'Pending'  ? 'selected' : '' ?>>Pending</option>
                        <option value="Complete" <?= ($lead['kseb_process'] ?? '') === 'Complete' ? 'selected' : '' ?>>Complete</option>
                        <option value="Blocked"  <?= ($lead['kseb_process'] ?? '') === 'Blocked'  ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </div>
                <div class="col-md-4" id="ksebCompletedWrap" <?= ($lead['kseb_process'] ?? '') !== 'Complete' ? 'style="display:none"' : '' ?>>
                    <label class="form-label fw-semibold small">KSEB Completed Date</label>
                    <input type="date" class="form-control form-control-sm" name="kseb_completed_date"
                           id="ksebCompletedDate"
                           value="<?= htmlspecialchars($lead['kseb_completed_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">
                        Installation Date
                        <span class="text-muted fw-normal">(creates MNRE task)</span>
                    </label>
                    <input type="date" class="form-control form-control-sm" name="installation_date"
                           value="<?= htmlspecialchars($lead['installation_date'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <?php $nfu = $lead['next_followup_date'] ?? null;
                          $nfuLate = $nfu && $nfu < date('Y-m-d') && !in_array($lead['stage'], ['Won','Lost'], true); ?>
                    <label class="form-label fw-semibold small">
                        Next Follow-up <i class="bi bi-lock text-muted ms-1" title="Not editable"></i>
                    </label>
                    <?php // Deliberately not an input — the follow-up date can't be edited by users ?>
                    <div class="form-control form-control-sm bg-light <?= $nfuLate ? 'text-danger fw-semibold' : 'text-muted' ?>"
                         aria-readonly="true">
                        <?= $nfu ? htmlspecialchars(formatDate($nfu)) . ($nfuLate ? ' · overdue' : '') : '—' ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold small">Visit Scheduled</label>
                    <input type="date" class="form-control form-control-sm" name="visit_scheduled_date"
                           value="<?= htmlspecialchars($lead['visit_scheduled_date'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold small">Lost/Blocked Reason</label>
                    <select class="form-select form-select-sm" name="status_reason_code">
                        <option value="">— None —</option>
                        <?php foreach ($statusReasons as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>"
                                <?= ($lead['status_reason_code'] ?? '') === $r ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold small">Status Notes</label>
                    <div class="position-relative">
                        <textarea class="form-control form-control-sm" name="status_reason_notes"
                                  rows="2"><?= htmlspecialchars($lead['status_reason_notes'] ?? '') ?></textarea>
                        <textarea id="statusNotesTA" class="d-none"></textarea>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- RIGHT: Meta info -->
<div class="col-lg-5">
    <div class="card mb-3">
        <div class="card-body small">
            <div class="row g-2">
                <div class="col-6"><span class="text-muted">Created by</span><br><strong><?= htmlspecialchars($lead['creator_name'] ?? '—') ?></strong></div>
                <div class="col-6"><span class="text-muted">Created on</span><br><strong><?= formatDate($lead['created_at']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Lead ID</span><br><strong>#<?= $lead['id'] ?></strong></div>
                <div class="col-6"><span class="text-muted">Last updated</span><br><strong><?= formatDateTime($lead['updated_at']) ?></strong></div>
            </div>
        </div>
    </div>

    <?php
    // ── Documents ────────────────────────────────────────────────────────────
    $docsEditable = hasRole('admin', 'manager', 'operation_manager', 'documentation')
                 || (hasRole('sales_rep') && (int)$lead['assigned_rep_id'] === (int)$_SESSION['user_id']);
    $docsComplete = $lead['doc_aadhar'] && $lead['doc_registered_mobile']
                 && $lead['doc_kseb_bill'] && $lead['doc_bank_passbook'];
    ?>
    <div class="card mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-folder2-open me-1"></i>Documents</span>
            <?php if ($docsComplete): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-check-circle-fill me-1"></i>Complete
            </span>
            <?php else: ?>
            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Incomplete</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dv-aadhar-chk"
                           <?= $lead['doc_aadhar'] ? 'checked' : '' ?> <?= $docsEditable ? '' : 'disabled' ?>>
                    <label class="form-check-label fw-semibold small" for="dv-aadhar-chk">Aadhar received</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1" id="dv-aadhar-num"
                       placeholder="Aadhar number (12 digits)" inputmode="numeric" maxlength="14"
                       value="<?= htmlspecialchars($lead['doc_aadhar_number'] ?? '') ?>"
                       <?= $docsEditable ? '' : 'disabled' ?>>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dv-mobile-chk"
                           <?= $lead['doc_registered_mobile'] ? 'checked' : '' ?> <?= $docsEditable ? '' : 'disabled' ?>>
                    <label class="form-check-label fw-semibold small" for="dv-mobile-chk">Registered mobile received</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1" id="dv-mobile-num"
                       placeholder="Registered mobile number (10 digits)" inputmode="numeric" maxlength="12"
                       value="<?= htmlspecialchars($lead['doc_mobile_number'] ?? '') ?>"
                       <?= $docsEditable ? '' : 'disabled' ?>>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dv-kseb-chk"
                           <?= $lead['doc_kseb_bill'] ? 'checked' : '' ?> <?= $docsEditable ? '' : 'disabled' ?>>
                    <label class="form-check-label fw-semibold small" for="dv-kseb-chk">KSEB bill received</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1" id="dv-kseb-num"
                       placeholder="KSEB consumer number" maxlength="40"
                       value="<?= htmlspecialchars($lead['doc_kseb_consumer_no'] ?? '') ?>"
                       <?= $docsEditable ? '' : 'disabled' ?>>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="dv-passbook-chk"
                           <?= $lead['doc_bank_passbook'] ? 'checked' : '' ?> <?= $docsEditable ? '' : 'disabled' ?>>
                    <label class="form-check-label fw-semibold small" for="dv-passbook-chk">Bank pass book received</label>
                </div>
                <input type="text" class="form-control form-control-sm mt-1" id="dv-passbook-num"
                       placeholder="Bank account number" maxlength="40"
                       value="<?= htmlspecialchars($lead['doc_bank_account_no'] ?? '') ?>"
                       <?= $docsEditable ? '' : 'disabled' ?>>
            </div>

            <?php if ($docsEditable): ?>
            <button class="btn btn-sm btn-primary w-100" id="saveDocsBtn">
                <i class="bi bi-save me-1"></i>Save Documents
            </button>
            <?php else: ?>
            <div class="text-muted small"><i class="bi bi-lock me-1"></i>You cannot edit documents on this lead.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<!-- Tele Caller Modal -->
<div class="modal fade" id="modalTC" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">Assign Tele Caller</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Tele Caller</label>
        <select class="form-select form-select-sm mb-3" id="am-tc-user">
          <option value="">— Select —</option>
          <?php foreach ($teleCallerUsers as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
          <?php if (empty($teleCallerUsers)): ?><option disabled>No tele callers found</option><?php endif; ?>
        </select>
        <label class="form-label small fw-semibold">Target Date</label>
        <input type="date" class="form-control form-control-sm" id="am-tc-date"
               value="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="am-tc-btn"
                onclick="amConfirm(this,'tele_caller','am-tc-user','am-tc-date')">Assign</button>
      </div>
    </div>
  </div>
</div>

<!-- Sales Rep Modal -->
<div class="modal fade" id="modalSR" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">Assign Sales Rep</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Sales Rep</label>
        <select class="form-select form-select-sm mb-3" id="am-sr-user">
          <option value="">— Select —</option>
          <?php foreach ($salesRepUsers as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
          <?php if (empty($salesRepUsers)): ?><option disabled>No sales reps found</option><?php endif; ?>
        </select>
        <label class="form-label small fw-semibold">Target Date</label>
        <input type="date" class="form-control form-control-sm" id="am-sr-date"
               value="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="am-sr-btn"
                onclick="amConfirm(this,'sales_rep','am-sr-user','am-sr-date')">Assign</button>
      </div>
    </div>
  </div>
</div>

<!-- Documentation Modal -->
<div class="modal fade" id="modalDoc" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">Start Documentation</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Documentation User</label>
        <select class="form-select form-select-sm mb-3" id="am-doc-user">
          <option value="">— Select —</option>
          <?php foreach ($documentationUsers as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
          <?php if (empty($documentationUsers)): ?><option disabled>No documentation users found</option><?php endif; ?>
        </select>
        <label class="form-label small fw-semibold">Target Date</label>
        <input type="date" class="form-control form-control-sm" id="am-doc-date"
               value="<?= date('Y-m-d') ?>">
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="am-doc-btn"
                onclick="amConfirm(this,'documentation','am-doc-user','am-doc-date')">Assign</button>
      </div>
    </div>
  </div>
</div>

<!-- Mark Won Modal -->
<?php
$srWonAllowed = in_array('Won', allowedNextStages($lead['stage'], 'sales_rep'), true);
if (hasRole('sales_rep') && $srWonAllowed):
?>
<div class="modal fade" id="markWonModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-trophy-fill text-warning me-2"></i>Mark as Won</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3">Confirm the documents collected from this customer:</p>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="won-aadhar">
          <label class="form-check-label" for="won-aadhar">Aadhar</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="won-mobile">
          <label class="form-check-label" for="won-mobile">Registered Mobile Number</label>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="won-kseb">
          <label class="form-check-label" for="won-kseb">KSEB Bill</label>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="won-passbook">
          <label class="form-check-label" for="won-passbook">Bank Pass Book</label>
        </div>
        <div class="alert alert-success small mb-0 py-2">
          <i class="bi bi-info-circle me-1"></i>This will move the lead to <strong>Won</strong> stage.
        </div>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success btn-sm" id="markWonConfirmBtn">
          <i class="bi bi-trophy me-1"></i>Confirm Won
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Mark Lost Modal -->
<?php
$srLostAllowed = in_array('Lost', allowedNextStages($lead['stage'], 'sales_rep'), true);
if (hasRole('sales_rep') && $srLostAllowed):
?>
<div class="modal fade" id="markLostModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-x-circle-fill text-danger me-2"></i>Mark as Lost</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Lost/Blocked Reason</label>
          <select class="form-select form-select-sm" id="lost-reason">
            <option value="">— Select reason —</option>
            <?php foreach ($statusReasons as $lr): ?>
            <option value="<?= htmlspecialchars($lr) ?>"><?= htmlspecialchars($lr) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Status Notes</label>
          <textarea class="form-control form-control-sm" id="lost-notes" rows="3"
                    placeholder="Additional notes…"></textarea>
        </div>
        <div class="alert alert-danger small mb-0 py-2">
          <i class="bi bi-info-circle me-1"></i>This will move the lead to <strong>Lost</strong> stage.
        </div>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger btn-sm" id="markLostConfirmBtn">
          <i class="bi bi-x-circle me-1"></i>Confirm Lost
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<?php
$wfPending = count(array_filter($workflowTasks ?? [], fn($t) => $t['status'] === 'pending'));
$wfStages  = array_unique(array_column($workflowTasks ?? [], 'stage'));
?>
<ul class="nav nav-tabs nav-tabs-scroll" id="leadTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPipeline">
        <i class="bi bi-diagram-3 me-1"></i>Pipeline
        <?php if ($wfPending > 0): ?>
        <span class="badge bg-primary"><?= $wfPending ?></span>
        <?php endif; ?>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabActivities">
        <i class="bi bi-chat-left-text me-1"></i>Activities <span class="badge bg-secondary"><?= count($activities) ?></span>
    </button></li>

    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabReminders">
        <i class="bi bi-bell me-1"></i>Reminders <span class="badge bg-secondary"><?= count(array_filter($reminders, fn($r) => !$r['is_done'])) ?></span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVisits">
        <i class="bi bi-geo-alt me-1"></i>Site Visits <span class="badge bg-secondary"><?= count($siteVisits) ?></span>
    </button></li>
    <?php if ($lead['stage'] === 'Won'): ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabOperations">
        <i class="bi bi-tools me-1"></i>Operations <span class="badge bg-secondary"><?= count($operations) ?></span>
    </button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFinancials" id="tabFinancialsBtn">
        <i class="bi bi-cash-stack me-1"></i>Financials
    </button></li>
    <?php endif; ?>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAudit">
        <i class="bi bi-clock-history me-1"></i>Audit Trail
    </button></li>
</ul>

<div class="tab-content mt-2" id="leadTabContent">

    <!-- Pipeline tab -->
    <div class="tab-pane fade show active" id="tabPipeline">
    <?php
    $allStages      = ['New','Contacted','Visit Scheduled','Site Visit in Progress',
                       'Site Visit Completed','Quoted','Won'];
    $currentStage   = $lead['stage'];
    $currentIdx     = array_search($currentStage, $allStages, true);
    $tasksByStage   = [];
    foreach ($workflowTasks ?? [] as $wt) {
        $tasksByStage[$wt['stage']][] = $wt;
    }
    $isAdminOrMgr   = hasRole('admin', 'manager');
    $canEditDate    = hasRole('admin', 'manager', 'operation_manager');
    $sessionUserId  = (int) ($_SESSION['user_id'] ?? 0);
    ?>

    <?php if (empty($workflowTasks)): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        No pipeline tasks yet.
        <?php if ($isAdminOrMgr): ?>
        <a href="#" class="alert-link" onclick="createWorkflowTasksForCurrentStage(<?= $lead['id'] ?>);return false">
            Create tasks for current stage
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    // Show tasks grouped by stage (current stage first, then ad-hoc follow-ups,
    // then Won, Installation, then history)
    $displayOrder = array_merge(
        [$currentStage],
        array_diff(['Follow-up','Won','Installation'], [$currentStage]),
        array_diff(array_reverse($allStages), [$currentStage, 'Won', 'Installation'])
    );
    // Anything carrying a stage we don't know about still gets rendered — otherwise
    // a task can exist on the lead and be invisible here.
    $displayOrder = array_merge($displayOrder, array_diff(array_keys($tasksByStage), $displayOrder));
    foreach ($displayOrder as $stageName):
        if (!isset($tasksByStage[$stageName])) continue;
        $stageTasks = $tasksByStage[$stageName];
        $isCurrent  = $stageName === $currentStage;
        $allDone    = count(array_filter($stageTasks, fn($t) => $t['status'] === 'pending')) === 0;
    ?>
    <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge <?= $stageName === 'Follow-up' ? 'bg-info text-dark'
                                   : ($isCurrent ? 'bg-primary' : ($allDone ? 'bg-success' : 'bg-secondary')) ?>">
                <?php if ($stageName === 'Follow-up'): ?><i class="bi bi-calendar-check me-1"></i><?php endif; ?>
                <?= htmlspecialchars($stageName) ?>
            </span>
            <?php if ($allDone): ?>
            <small class="text-success"><i class="bi bi-check-all"></i> Complete</small>
            <?php elseif ($isCurrent): ?>
            <small class="text-primary fw-semibold">← Current stage</small>
            <?php endif; ?>
        </div>
        <div class="row g-2">
        <?php foreach ($stageTasks as $wt):
            $isPending    = $wt['status'] === 'pending';
            $canComplete  = $isPending && $isAdminOrMgr;   // only admin/manager can act from lead page
            $isUnassigned = $wt['assigned_to'] === null;
            $isOverdue    = $isPending && $wt['due_date'] && $wt['due_date'] < date('Y-m-d');
        ?>
        <div class="col-12 col-md-6" id="wfTask<?= $wt['id'] ?>">
            <div class="card border-<?= $wt['status']==='done' ? 'success' : ($wt['status']==='skipped' ? 'secondary' : ($isUnassigned ? 'warning' : ($isOverdue?'danger':'primary'))) ?>"
                 style="border-left-width:3px">
                <div class="card-body p-3">
                    <div class="d-flex align-items-start gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold small <?= $wt['status']!=='pending' ? 'text-muted text-decoration-line-through' : '' ?>">
                                <?= htmlspecialchars($wt['title']) ?>
                            </div>
                            <div class="small text-muted mt-1">
                                <?php if ($isUnassigned && $isPending): ?>
                                <span class="text-warning fw-semibold"><i class="bi bi-exclamation-triangle me-1"></i>Unassigned</span>
                                <?php else: ?>
                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($wt['assignee_name'] ?? ucwords(str_replace('_',' ',$wt['assigned_role'] ?? ''))) ?>
                                <?php endif; ?>
                                <?php if ($wt['due_date']): ?>
                                · <i class="bi bi-calendar3 me-1"></i>
                                <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                                    <?= date('d M', strtotime($wt['due_date'])) ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($wt['status'] === 'done'): ?>
                                · <span class="text-success"><i class="bi bi-check-circle me-1"></i>Done <?= $wt['completed_at'] ? date('d M', strtotime($wt['completed_at'])) : '' ?></span>
                                <?php elseif ($wt['status'] === 'skipped'): ?>
                                · <span class="text-muted"><i class="bi bi-skip-forward me-1"></i>Skipped</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <?php if ($isPending): ?>
                            <?php if ($canComplete): ?>
                            <button class="btn btn-sm btn-success py-0 px-2 wf-complete-btn"
                                    data-task-id="<?= $wt['id'] ?>"
                                    data-assigned-role="<?= htmlspecialchars($wt['assigned_role'] ?? '') ?>"
                                    data-stage="<?= htmlspecialchars($wt['stage'] ?? '') ?>"
                                    title="Mark done">
                                <i class="bi bi-check-lg"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($isAdminOrMgr): ?>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-2 wf-skip-btn"
                                    data-id="<?= $wt['id'] ?>"
                                    title="Skip task (advances pipeline)">
                                <i class="bi bi-skip-forward"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger py-0 px-2 wf-cancel-btn"
                                    data-id="<?= $wt['id'] ?>"
                                    title="Cancel task">
                                <i class="bi bi-x-lg"></i>
                            </button>
                            <?php endif; ?>
                            <?php elseif ($isAdminOrMgr): ?>
                            <button class="btn btn-sm btn-outline-warning py-0 px-2 wf-reopen-btn"
                                    data-id="<?= $wt['id'] ?>"
                                    title="Reopen task">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($isPending && $canEditDate): ?>
                    <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                        <!-- Assign dropdown (admin/manager/operation_manager) -->
                        <select class="form-select form-select-sm wf-assign-select flex-grow-1" data-task-id="<?= $wt['id'] ?>" style="min-width:140px">
                            <option value="">— <?= $isUnassigned ? 'Assign to...' : 'Reassign...' ?> —</option>
                            <?php foreach ($allUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= (int)$wt['assigned_to'] === (int)$u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['name']) ?>
                                (<?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Due date picker (admin/manager/operation_manager) -->
                        <div class="input-group input-group-sm flex-shrink-0" style="width:auto" title="Change due date">
                            <span class="input-group-text px-1"><i class="bi bi-calendar3"></i></span>
                            <input type="date" class="form-control form-control-sm wf-due-date-input"
                                   data-task-id="<?= $wt['id'] ?>"
                                   value="<?= htmlspecialchars($wt['due_date'] ?? '') ?>"
                                   style="width:130px">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    </div><!-- /#tabPipeline -->

    <!-- Activities tab -->
    <div class="tab-pane fade" id="tabActivities">
        <div class="card mb-3">
            <div class="card-body">
                <form id="activityForm" class="row g-2 align-items-end">
                    <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="type" required>
                            <option value="call">Call</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                            <option value="note" selected>Note</option>
                            <option value="quote">Quote Sent</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <div class="position-relative">
                            <textarea class="form-control form-control-sm" name="notes" rows="1"
                                      id="activityNotes" placeholder="Activity notes…"
                                      ></textarea>
                            <textarea id="activityNotesSrc" class="d-none"></textarea>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Log</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="activitiesFeed">
        <?php foreach ($activities as $act): ?>
        <div class="d-flex gap-2 mb-2 small">
            <div class="text-muted text-nowrap"><?= formatDateTime($act['occurred_at']) ?></div>
            <div>
                <span class="badge bg-light text-dark text-capitalize me-1"><?= htmlspecialchars($act['type']) ?></span>
                <strong><?= htmlspecialchars($act['user_name']) ?></strong>
                <?php if ($act['notes']): ?>— <?= htmlspecialchars($act['notes']) ?><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($activities)): ?><p class="text-muted small">No activities yet.</p><?php endif; ?>
        </div>
    </div>

    <!-- Reminders tab -->
    <div class="tab-pane fade" id="tabReminders">
        <div class="card mb-3">
            <div class="card-body">
                <form id="reminderForm" class="row g-2 align-items-end">
                    <input type="hidden" name="lead_id" value="<?= $lead['id'] ?>">
                    <div class="col-md-4">
                        <input type="datetime-local" class="form-control form-control-sm" name="remind_at" required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control form-control-sm" name="message"
                               placeholder="Reminder message…" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="remindersList">
        <?php foreach ($reminders as $rem): ?>
        <div class="d-flex align-items-center gap-2 mb-2 small <?= $rem['is_done'] ? 'text-decoration-line-through text-muted' : '' ?>"
             id="reminder-<?= $rem['id'] ?>">
            <button class="btn btn-sm <?= $rem['is_done'] ? 'btn-outline-secondary' : 'btn-outline-success' ?> py-0 mark-done-btn"
                    data-id="<?= $rem['id'] ?>">
                <i class="bi <?= $rem['is_done'] ? 'bi-check-circle-fill' : 'bi-circle' ?>"></i>
            </button>
            <span class="text-nowrap text-muted"><?= formatDateTime($rem['remind_at']) ?></span>
            <span><?= htmlspecialchars($rem['message']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($reminders)): ?><p class="text-muted small" id="noReminders">No reminders.</p><?php endif; ?>
        </div>
    </div>

    <!-- Site Visits tab -->
    <div class="tab-pane fade" id="tabVisits">
        <?php if (empty($siteVisitHistory ?? [])): ?>
        <p class="text-muted small">No site visit history recorded.</p>
        <?php else: ?>
        <div class="position-relative ps-4" style="border-left:2px solid #dee2e6">
        <?php foreach ($siteVisitHistory as $ev): ?>
        <?php $raw = $ev['raw']; ?>
        <div class="mb-3 position-relative">
            <!-- Timeline dot -->
            <span class="position-absolute d-flex align-items-center justify-content-center rounded-circle bg-<?= $ev['color'] ?> text-white"
                  style="width:28px;height:28px;left:-43px;top:2px;font-size:12px">
                <i class="bi <?= $ev['icon'] ?>"></i>
            </span>

            <div class="card border-<?= $ev['color'] ?>" style="border-left-width:3px">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                        <div>
                            <span class="badge bg-<?= $ev['color'] ?> mb-1"><?= htmlspecialchars($ev['label']) ?></span>
                            <?php if ($ev['detail']): ?>
                            <div class="small fw-semibold"><?= htmlspecialchars($ev['detail']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted small text-nowrap text-end">
                            <?php if ($ev['event_at']): ?>
                            <div><?= date('d M Y', strtotime($ev['event_at'])) ?></div>
                            <div><?= date('g:i a', strtotime($ev['event_at'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($ev['actor']): ?>
                    <div class="text-muted small mt-1">
                        <i class="bi bi-person me-1"></i><?= htmlspecialchars($ev['actor']) ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($ev['type'] === 'checkin'): ?>
                    <div class="mt-2 row g-1 small">
                        <div class="col-auto">
                            <span class="text-muted">Duration:</span>
                            <?= isset($raw['duration_secs']) ? formatDuration($raw['duration_secs']) : '—' ?>
                        </div>
                        <?php if ($raw['location_provided'] && $raw['checkin_lat']): ?>
                        <div class="col-auto">
                            <a href="<?= googleMapsUrl($raw['checkin_lat'], $raw['checkin_lng']) ?>" target="_blank" class="text-decoration-none">
                                <i class="bi bi-geo-alt me-1"></i>Check-in GPS <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                        <?php if ($raw['checkout_lat']): ?>
                        <div class="col-auto">
                            <a href="<?= googleMapsUrl($raw['checkout_lat'], $raw['checkout_lng']) ?>" target="_blank" class="text-decoration-none">
                                <i class="bi bi-geo-alt me-1"></i>Check-out GPS <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($raw['voice_notes']): ?>
                        <div class="col-12 text-muted mt-1">
                            <i class="bi bi-mic me-1"></i><?= htmlspecialchars($raw['voice_notes']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($raw['roof_type']): ?>
                        <div class="col-12 text-muted mt-1">
                            <i class="bi bi-house me-1"></i><?= htmlspecialchars($raw['roof_type']) ?>
                            <?= $raw['roof_area_sqft'] ? '· ' . $raw['roof_area_sqft'] . ' sq ft' : '' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Operations tab (Won leads only) -->
    <?php if ($lead['stage'] === 'Won'): ?>
    <div class="tab-pane fade" id="tabOperations">
        <?php if (hasRole('admin', 'sales_rep')): ?>
        <div class="card mb-3">
            <div class="card-body">
                <form id="opForm" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="opFormType"
                               placeholder="Type (e.g. Cleaning)" value="Cleaning">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" id="opFormTeamId">
                            <option value="">— No team —</option>
                            <?php foreach ($teams as $tm): ?>
                            <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="date" class="form-control form-control-sm" id="opFormDate"
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" id="opFormInterval"
                               value="120" min="0" placeholder="Interval days">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="opFormNotes"
                               placeholder="Notes (optional)">
                    </div>
                    <div class="col-auto">
                        <button type="button" id="opFormSaveBtn" class="btn btn-sm btn-outline-primary">Schedule</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div id="operationsList">
        <?php if (empty($operations)): ?>
        <p class="text-muted small" id="noOperations">No operations scheduled yet.</p>
        <?php endif; ?>
        <?php foreach ($operations as $op):
            $isOverdue   = !$op['done_at'] && $op['scheduled_at'] < date('Y-m-d');
            $canReschedule = !$op['done_at'] && hasRole('admin', 'operation_manager');
        ?>
        <div class="d-flex align-items-start gap-2 mb-2 small <?= $isOverdue ? 'text-danger' : '' ?>"
             id="op-detail-<?= $op['id'] ?>">
            <?php if (!$op['done_at'] && hasRole('admin','installer','sales_rep')): ?>
            <button class="btn btn-sm btn-outline-success py-0 flex-shrink-0 op-done-btn" data-id="<?= $op['id'] ?>">
                <i class="bi bi-circle"></i>
            </button>
            <?php else: ?>
            <span class="text-success flex-shrink-0 mt-1"><i class="bi bi-check-circle-fill"></i></span>
            <?php endif; ?>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span>
                        <span class="fw-semibold"><?= htmlspecialchars($op['operation_type']) ?></span>
                        <?= $op['done_at'] ? '— Done ' . formatDate($op['done_at']) : '' ?>
                        <?= $op['notes'] ? '<span class="text-muted"> · ' . htmlspecialchars($op['notes']) . '</span>' : '' ?>
                        <?= $op['interval_days'] ? '<span class="text-muted"> · Every ' . (int)$op['interval_days'] . 'd</span>' : '' ?>
                    </span>
                    <?php if ($canReschedule): ?>
                    <div class="input-group input-group-sm ms-1" style="width:auto" title="Reschedule">
                        <span class="input-group-text px-1 <?= $isOverdue ? 'bg-danger text-white border-danger' : '' ?>">
                            <i class="bi bi-calendar3"></i>
                        </span>
                        <input type="date" class="form-control form-control-sm op-reschedule-input <?= $isOverdue ? 'border-danger' : '' ?>"
                               data-op-id="<?= $op['id'] ?>"
                               value="<?= htmlspecialchars($op['scheduled_at']) ?>"
                               style="width:130px">
                    </div>
                    <?php else: ?>
                    <span class="text-muted">Scheduled <?= formatDate($op['scheduled_at']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Financials tab (Won leads only) -->
    <?php if ($lead['stage'] === 'Won'):
        $finTotalAmt  = (float) ($financials['total_amount'] ?? 0);
        $finDiscount  = (float) ($financials['discount'] ?? 0);
        $finTotalPaid = array_sum(array_column($installments, 'amount'));
        $finBalance   = $finTotalAmt - $finDiscount - $finTotalPaid;
    ?>
    <div class="tab-pane fade" id="tabFinancials">

        <!-- Summary cards -->
        <div class="row g-2 mb-3" id="finSummary">
            <div class="col-6 col-md-3">
                <div class="card text-center py-2">
                    <div class="fs-5 fw-bold text-primary" id="finTotalAmt"><?= formatCurrency($finTotalAmt) ?></div>
                    <div class="small text-muted">Total Amount</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-2">
                    <div class="fs-5 fw-bold text-success" id="finTotalPaid"><?= formatCurrency($finTotalPaid) ?></div>
                    <div class="small text-muted">Total Paid</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-2">
                    <div class="fs-5 fw-bold text-secondary" id="finDiscount"><?= $finDiscount > 0 ? formatCurrency($finDiscount) : '—' ?></div>
                    <div class="small text-muted">Discount</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card text-center py-2">
                    <div class="fs-5 fw-bold <?= $finBalance > 0 ? 'text-danger' : 'text-success' ?>" id="finBalance"><?= formatCurrency($finBalance) ?></div>
                    <div class="small text-muted">Balance</div>
                </div>
            </div>
        </div>

        <?php if (hasRole('admin', 'sales_rep')): ?>
        <!-- Edit financials form -->
        <div class="card mb-3">
            <div class="card-header fw-semibold bg-white small d-flex justify-content-between align-items-center">
                Financial Details
                <button class="btn btn-sm btn-outline-secondary py-0" type="button"
                        data-bs-toggle="collapse" data-bs-target="#finEditForm">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            </div>
            <div class="collapse <?= $financials ? '' : 'show' ?>" id="finEditForm">
            <div class="card-body">
                <div class="row g-2 fin-form">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Total Amount (₹)</label>
                        <input type="number" class="form-control form-control-sm" id="finInputTotal"
                               value="<?= htmlspecialchars($financials['total_amount'] ?? '') ?>" min="0" step="1000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Discount (₹)</label>
                        <input type="number" class="form-control form-control-sm" id="finInputDiscount"
                               value="<?= htmlspecialchars($financials['discount'] ?? '0') ?>" min="0" step="1000">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-semibold">Payment Status</label>
                        <select class="form-select form-select-sm" id="finInputStatus">
                            <option value="">— None —</option>
                            <?php foreach (paymentStatuses() as $ps): ?>
                            <option value="<?= htmlspecialchars($ps) ?>"
                                    <?= ($financials['payment_status'] ?? '') === $ps ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ps) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Feasibility Status</label>
                        <select class="form-select form-select-sm" id="finInputFeasibility">
                            <option value="">— None —</option>
                            <?php foreach (feasibilityStatuses() as $fs): ?>
                            <option value="<?= htmlspecialchars($fs) ?>"
                                    <?= ($financials['feasibility_status'] ?? '') === $fs ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fs) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Structure Type</label>
                        <input type="text" class="form-control form-control-sm" id="finInputStructure"
                               value="<?= htmlspecialchars($financials['structure_type'] ?? '') ?>"
                               placeholder="e.g. RCC, Tile, Sheet…">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Equipment Brand</label>
                        <input type="text" class="form-control form-control-sm" id="finInputBrand"
                               value="<?= htmlspecialchars($financials['equipment_brand'] ?? '') ?>"
                               placeholder="e.g. Luminous, Premier…">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Notes</label>
                        <textarea class="form-control form-control-sm" id="finInputNotes" rows="2"><?= htmlspecialchars($financials['notes'] ?? '') ?></textarea>
                    </div>
                </div>
                <button type="button" id="finSaveBtn" class="btn btn-primary btn-sm mt-2">Save</button>
            </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Installments table -->
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 small fw-semibold">Payment Installments</h6>
            <?php if (hasRole('admin', 'sales_rep')): ?>
            <button class="btn btn-sm btn-outline-primary py-0" data-bs-toggle="modal" data-bs-target="#addInstallmentModal">
                <i class="bi bi-plus-lg me-1"></i>Add Payment
            </button>
            <?php endif; ?>
        </div>

        <div class="card mb-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Cheque</th>
                            <th>Cheque Amt</th>
                            <th>Notes</th>
                            <?php if (hasRole('admin')): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="installmentsBody">
                    <?php foreach ($installments as $inst): ?>
                    <tr id="inst-row-<?= $inst['id'] ?>">
                        <td><span class="badge bg-light text-dark text-capitalize"><?= htmlspecialchars($inst['installment_type']) ?></span></td>
                        <td class="fw-semibold"><?= formatCurrency((float)$inst['amount']) ?></td>
                        <td class="text-nowrap"><?= formatDate($inst['received_date']) ?></td>
                        <td>
                            <?php if ($inst['cheque_status'] !== 'none'): ?>
                            <span class="badge <?= match($inst['cheque_status']) {
                                'received' => 'bg-info text-dark',
                                'cleared'  => 'bg-success',
                                'bounced'  => 'bg-danger',
                                default    => 'bg-secondary'
                            } ?>"><?= ucfirst($inst['cheque_status']) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><?= $inst['cheque_amount'] ? formatCurrency((float)$inst['cheque_amount']) : '—' ?></td>
                        <td class="text-muted"><?= $inst['notes'] ? htmlspecialchars($inst['notes']) : '' ?></td>
                        <?php if (hasRole('admin')): ?>
                        <td>
                            <button class="btn btn-sm btn-outline-danger py-0 delete-inst-btn"
                                    data-id="<?= $inst['id'] ?>"><i class="bi bi-trash"></i></button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($installments)): ?>
                    <tr id="noInstallments"><td colspan="7" class="text-muted text-center py-3">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Audit trail tab -->
    <div class="tab-pane fade" id="tabAudit">
        <div class="table-responsive">
            <table class="table table-sm small">
                <thead class="table-light">
                    <tr><th>When</th><th>User</th><th>Action</th><th>Field</th><th>From</th><th>To</th></tr>
                </thead>
                <tbody>
                <?php foreach ($auditLog as $entry): ?>
                <tr>
                    <td class="text-muted text-nowrap"><?= formatDateTime($entry['created_at']) ?></td>
                    <td><?= htmlspecialchars($entry['user_name'] ?? 'System') ?></td>
                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($entry['action']) ?></span></td>
                    <td class="text-muted"><?= htmlspecialchars($entry['field_name'] ?? '—') ?></td>
                    <td class="text-danger"><?= htmlspecialchars($entry['old_value'] ?? '—') ?></td>
                    <td class="text-success"><?= htmlspecialchars($entry['new_value'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($auditLog)): ?>
                <tr><td colspan="6" class="text-muted text-center py-3">No audit entries.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /tab-content -->

<!-- Add Payment Installment Modal -->
<?php if ($lead['stage'] === 'Won' && hasRole('admin', 'sales_rep')): ?>
<div class="modal fade" id="addInstallmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Type</label>
                        <select id="instType" class="form-select form-select-sm">
                            <option value="advance">Advance</option>
                            <option value="installation">Installation</option>
                            <option value="commissioning">Commissioning</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="instAmount" class="form-control form-control-sm" min="0" step="1000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Received Date <span class="text-danger">*</span></label>
                        <input type="date" id="instDate" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cheque Status</label>
                        <select id="instChequeStatus" class="form-select form-select-sm">
                            <option value="none">None</option>
                            <option value="received">Received</option>
                            <option value="cleared">Cleared</option>
                            <option value="bounced">Bounced</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Cheque Amount (₹)</label>
                        <input type="number" id="instChequeAmt" class="form-control form-control-sm" min="0" step="1000">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Notes</label>
                        <input type="text" id="instNotes" class="form-control form-control-sm" placeholder="Optional…">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveInstBtn" class="btn btn-primary btn-sm">Save Payment</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($lead['stage'] === 'Won'): ?>
<script>
'use strict';
const LEAD_ID_OPS = <?= (int)$lead['id'] ?>;

// Schedule new operation from detail tab
const opFormSaveBtn = document.getElementById('opFormSaveBtn');
if (opFormSaveBtn) {
    opFormSaveBtn.addEventListener('click', async () => {
        const type     = document.getElementById('opFormType').value.trim() || 'Cleaning';
        const teamId   = parseInt(document.getElementById('opFormTeamId').value) || null;
        const date     = document.getElementById('opFormDate').value;
        const interval = parseInt(document.getElementById('opFormInterval').value) || 120;
        const notes    = document.getElementById('opFormNotes').value.trim();
        if (!date) { showToast('danger', 'Date is required.'); return; }

        const res = await apiFetch('/operations/save', {
            method: 'POST',
            body: JSON.stringify({ lead_id: LEAD_ID_OPS, team_id: teamId, operation_type: type,
                                   scheduled_at: date, interval_days: interval, notes }),
        });
        if (res.ok) {
            document.getElementById('noOperations')?.remove();
            const list = document.getElementById('operationsList');
            const div  = document.createElement('div');
            div.className = 'd-flex align-items-start gap-2 mb-2 small';
            div.id = 'op-detail-' + res.id;
            div.innerHTML = `
                <button class="btn btn-sm btn-outline-success py-0 flex-shrink-0 op-done-btn" data-id="${res.id}">
                    <i class="bi bi-circle"></i>
                </button>
                <div>
                    <span class="fw-semibold">${escHtml(res.operation_type)}</span>
                    — Scheduled ${escHtml(res.scheduled_at)}
                    <span class="text-muted"> · Every ${res.interval_days}d</span>
                </div>`;
            list.appendChild(div);
            attachOpDoneBtn(div.querySelector('.op-done-btn'));
            document.getElementById('opFormDate').value = '<?= date('Y-m-d') ?>';
            document.getElementById('opFormNotes').value = '';
            showToast('success', 'Operation scheduled.');
        } else {
            showToast('danger', res.error ?? 'Could not save.');
        }
    });
}

// Mark done buttons
document.querySelectorAll('.op-done-btn').forEach(attachOpDoneBtn);

function attachOpDoneBtn(btn) {
    if (!btn) return;
    btn.addEventListener('click', async function () {
        const id    = parseInt(this.dataset.id);
        const today = '<?= date('Y-m-d') ?>';
        const res   = await apiFetch('/operations/done', {
            method: 'POST',
            body: JSON.stringify({ id, done_at: today, notes: '' }),
        });
        if (res.ok) {
            const row = document.getElementById('op-detail-' + id);
            if (row) {
                this.replaceWith(document.createRange().createContextualFragment(
                    '<span class="text-success flex-shrink-0 mt-1"><i class="bi bi-check-circle-fill"></i></span>'
                ));
            }
            if (res.next_id) {
                showToast('success', 'Done! Next scheduled: ' + res.next_scheduled);
            } else {
                showToast('success', 'Marked as done.');
            }
        } else {
            showToast('danger', res.error ?? 'Could not mark done.');
        }
    });
}

// ── Operation reschedule ─────────────────────────────────────────────────────
document.querySelectorAll('.op-reschedule-input').forEach(function(input) {
    const original = input.value;
    input.addEventListener('change', async function() {
        const opId = parseInt(this.dataset.opId);
        const date = this.value;
        if (!date) { this.value = original; return; }
        const res = await apiFetch('/operations/reschedule', {
            body: JSON.stringify({ id: opId, scheduled_at: date }),
        });
        if (res.ok) {
            showToast('success', 'Operation rescheduled to ' + date + '.');
        } else {
            showToast('danger', res.message || 'Could not reschedule.');
            this.value = original;
        }
    });
});

// ── Financials ────────────────────────────────────────────────────────────────
const FIN_LEAD_ID = <?= (int)$lead['id'] ?>;

const finSaveBtn = document.getElementById('finSaveBtn');
if (finSaveBtn) {
    finSaveBtn.addEventListener('click', async () => {
        const res = await apiFetch('/payments/save-financials', {
            method: 'POST',
            body: JSON.stringify({
                lead_id:            FIN_LEAD_ID,
                total_amount:       parseFloat(document.getElementById('finInputTotal').value) || 0,
                discount:           parseFloat(document.getElementById('finInputDiscount').value) || 0,
                payment_status:     document.getElementById('finInputStatus').value,
                feasibility_status: document.getElementById('finInputFeasibility').value,
                structure_type:     document.getElementById('finInputStructure').value.trim(),
                equipment_brand:    document.getElementById('finInputBrand').value.trim(),
                notes:              document.getElementById('finInputNotes').value.trim(),
            }),
        });
        if (res.ok) {
            const fmt = v => v > 0 ? new Intl.NumberFormat('en-IN', {style:'currency',currency:'INR',maximumFractionDigits:0}).format(v) : '—';
            document.getElementById('finTotalAmt').textContent  = fmt(res.total_amount);
            document.getElementById('finTotalPaid').textContent = fmt(res.total_paid);
            document.getElementById('finDiscount').textContent  = res.discount > 0 ? fmt(res.discount) : '—';
            const balEl = document.getElementById('finBalance');
            balEl.textContent = fmt(Math.abs(res.balance));
            balEl.className = 'fs-5 fw-bold ' + (res.balance > 0 ? 'text-danger' : 'text-success');
            bootstrap.Collapse.getOrCreateInstance(document.getElementById('finEditForm')).hide();
            showToast('success', 'Financial details saved.');
        } else {
            showToast('danger', res.error ?? 'Could not save.');
        }
    });
}

// Add installment
const saveInstBtn = document.getElementById('saveInstBtn');
if (saveInstBtn) {
    saveInstBtn.addEventListener('click', async () => {
        const amount = parseFloat(document.getElementById('instAmount').value) || 0;
        const date   = document.getElementById('instDate').value;
        if (!amount || !date) {
            showToast('danger', 'Amount and date are required.');
            return;
        }
        const chequeAmt = parseFloat(document.getElementById('instChequeAmt').value) || null;
        const res = await apiFetch('/payments/save-installment', {
            method: 'POST',
            body: JSON.stringify({
                lead_id:          FIN_LEAD_ID,
                installment_type: document.getElementById('instType').value,
                amount,
                received_date:    date,
                cheque_status:    document.getElementById('instChequeStatus').value,
                cheque_amount:    chequeAmt,
                notes:            document.getElementById('instNotes').value.trim(),
            }),
        });
        if (res.ok) {
            bootstrap.Modal.getInstance(document.getElementById('addInstallmentModal')).hide();
            // Append row to table
            document.getElementById('noInstallments')?.remove();
            const tbody = document.getElementById('installmentsBody');
            const tr = document.createElement('tr');
            tr.id = 'inst-row-' + res.id;
            const fmt = v => v > 0 ? new Intl.NumberFormat('en-IN', {style:'currency',currency:'INR',maximumFractionDigits:0}).format(v) : '—';
            const chequeLabel = {none:'—',received:'Received',cleared:'Cleared',bounced:'Bounced'}[res.cheque_status] ?? '—';
            const chequeBadge = res.cheque_status !== 'none'
                ? `<span class="badge bg-info text-dark">${chequeLabel}</span>` : '—';
            tr.innerHTML = `
                <td><span class="badge bg-light text-dark text-capitalize">${escHtml(res.installment_type)}</span></td>
                <td class="fw-semibold">${fmt(res.amount)}</td>
                <td class="text-nowrap">${escHtml(res.received_date)}</td>
                <td>${chequeBadge}</td>
                <td>${res.cheque_amount ? fmt(res.cheque_amount) : '—'}</td>
                <td class="text-muted">${res.notes ? escHtml(res.notes) : ''}</td>
                <?php if (hasRole('admin')): ?>
                <td><button class="btn btn-sm btn-outline-danger py-0 delete-inst-btn" data-id="${res.id}"><i class="bi bi-trash"></i></button></td>
                <?php endif; ?>`;
            tbody.appendChild(tr);
            attachInstDeleteBtn(tr.querySelector('.delete-inst-btn'));
            // Update summary cards
            const balEl = document.getElementById('finBalance');
            document.getElementById('finTotalPaid').textContent = fmt(res.total_paid);
            if (balEl) {
                balEl.textContent = fmt(Math.abs(res.balance));
                balEl.className = 'fs-5 fw-bold ' + (res.balance > 0 ? 'text-danger' : 'text-success');
            }
            document.getElementById('instAmount').value = '';
            document.getElementById('instNotes').value = '';
            showToast('success', 'Payment recorded.');
        } else {
            showToast('danger', res.error ?? 'Could not save.');
        }
    });
}

// Delete installment
document.querySelectorAll('.delete-inst-btn').forEach(attachInstDeleteBtn);
function attachInstDeleteBtn(btn) {
    if (!btn) return;
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this payment record?')) return;
        const res = await apiFetch('/payments/delete-installment', {
            method: 'POST',
            body: JSON.stringify({ id: parseInt(btn.dataset.id) }),
        });
        if (res.ok) {
            document.getElementById('inst-row-' + btn.dataset.id)?.remove();
            showToast('success', 'Payment deleted.');
        } else {
            showToast('danger', res.error ?? 'Could not delete.');
        }
    });
}

// Auto-open Financials tab if URL hash matches
if (window.location.hash === '#tabFinancials') {
    const btn = document.getElementById('tabFinancialsBtn');
    if (btn) btn.click();
}
</script>
<?php endif; ?>

<script>
'use strict';
// Phone inline validation
(function () {
    const input = document.getElementById('phoneInput');
    const err   = document.getElementById('phoneError');
    if (!input || !err) return;
    function validate() {
        const digits = input.value.replace(/\D/g, '');
        if (digits.length === 0) {
            input.classList.remove('is-invalid', 'is-valid');
            err.textContent = '';
        } else if (digits.length !== 10) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
            err.textContent = 'Phone must be exactly 10 digits (' + digits.length + '/10)';
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
            err.textContent = '';
        }
    }
    input.addEventListener('input', validate);
    validate(); // run on load to reflect existing value state
})();

// Stage move buttons
// ── Save documents ───────────────────────────────────────────────────────────
document.getElementById('saveDocsBtn')?.addEventListener('click', async function () {
    const original = this.innerHTML;
    this.disabled  = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

    const res = await apiFetch('/documentation/save-docs', {
        body: JSON.stringify({
            lead_id:               <?= (int)$lead['id'] ?>,
            doc_aadhar:            document.getElementById('dv-aadhar-chk').checked ? 1 : 0,
            doc_registered_mobile: document.getElementById('dv-mobile-chk').checked ? 1 : 0,
            doc_kseb_bill:         document.getElementById('dv-kseb-chk').checked   ? 1 : 0,
            doc_bank_passbook:     document.getElementById('dv-passbook-chk').checked ? 1 : 0,
            doc_aadhar_number:     document.getElementById('dv-aadhar-num').value.trim(),
            doc_mobile_number:     document.getElementById('dv-mobile-num').value.trim(),
            doc_kseb_consumer_no:  document.getElementById('dv-kseb-num').value.trim(),
            doc_bank_account_no:   document.getElementById('dv-passbook-num').value.trim(),
        }),
    });

    if (res.ok) location.reload();
    else {
        showToast('danger', res.error ?? 'Could not save documents.');
        this.disabled  = false;
        this.innerHTML = original;
    }
});

// ── Create follow-up ─────────────────────────────────────────────────────────
document.getElementById('createFollowUpBtn')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('followUpModal')).show();
});

document.getElementById('fuConfirmBtn')?.addEventListener('click', async function () {
    const userId = document.getElementById('fu-user').value;
    if (!userId) { showToast('warning', 'Please choose who this follow-up is for.'); return; }

    const title = document.getElementById('fu-title').value.trim();
    if (!title) { showToast('warning', 'Please describe the follow-up.'); return; }

    this.disabled = true;
    const res = await apiFetch('/workflow/create-followup', {
        body: JSON.stringify({
            lead_id:            <?= (int)$lead['id'] ?>,
            assigned_to:        parseInt(userId),
            title:              title,
            due_date:           document.getElementById('fu-due').value || null,
            notes:              document.getElementById('fu-notes').value.trim(),
        }),
    });

    if (res.ok) location.reload();
    else { showToast('danger', res.error ?? 'Could not create the follow-up.'); this.disabled = false; }
});

// Moving into Quoted requires a quote amount. Ask for it up front rather than
// letting the server bounce the request back.
const EXISTING_QUOTE = <?= $lead['quote_amount'] !== null ? (float)$lead['quote_amount'] : 'null' ?>;

function promptQuoteAmount(stage) {
    if (stage !== 'Quoted') return {};
    if (EXISTING_QUOTE !== null && EXISTING_QUOTE > 0) return {};

    const entered = prompt('Enter the quote amount (₹) for this lead:');
    if (entered === null) return null;                    // cancelled

    const amount = parseFloat(String(entered).replace(/[^0-9.]/g, ''));
    if (!isFinite(amount) || amount <= 0) {
        showToast('warning', 'Please enter a valid quote amount.');
        return null;
    }
    return { quote_amount: amount };
}

document.querySelectorAll('.stage-move-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const stage = this.dataset.stage;
        if (!confirm('Move lead to "' + stage + '"?')) return;

        const extra = promptQuoteAmount(stage);
        if (extra === null) return;

        const r = await apiFetch('/leads/inline-edit', {
            method: 'POST',
            body: JSON.stringify(Object.assign({
                lead_id: <?= (int)$lead['id'] ?>,
                field: 'stage',
                value: stage,
            }, extra)),
        });
        if (r.ok) location.reload();
        else showToast('danger', r.error ?? 'Could not change stage.');
    });
});

// Admin delete lead
document.getElementById('deleteLeadBtn')?.addEventListener('click', async function () {
    if (!confirm('Permanently delete this lead? This cannot be undone.')) return;
    const r = await apiFetch('/leads/delete', {
        method: 'POST',
        body: JSON.stringify({ lead_id: <?= (int)$lead['id'] ?> }),
    });
    if (r.ok) location.href = '<?= BASE_URL ?>/leads';
    else showToast('danger', r.error ?? 'Could not delete.');
});
</script>

<!-- ── Outcome modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="outcomeModal" tabindex="-1" aria-labelledby="outcomeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold" id="outcomeModalLabel">Complete Task — Select Outcome</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="om-task-id">

        <!-- Tele Caller outcomes -->
        <div id="om-tc" class="d-none">
          <label class="form-label fw-semibold">Outcome</label>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input om-outcome" type="radio" name="om_outcome_tc"
                     value="Arranged Site Visit" id="om-tc-sv">
              <label class="form-check-label" for="om-tc-sv">Arranged Site Visit</label>
            </div>
            <div class="form-check">
              <input class="form-check-input om-outcome" type="radio" name="om_outcome_tc"
                     value="Reschedule Call" id="om-tc-resched">
              <label class="form-check-label" for="om-tc-resched">
                Reschedule Call
                <small class="d-block text-muted">Closes this task and sets a new one for the date you pick</small>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input om-outcome" type="radio" name="om_outcome_tc"
                     value="Lead Lost" id="om-tc-lost">
              <label class="form-check-label" for="om-tc-lost">Lead Lost</label>
            </div>
          </div>
          <div id="om-tc-reschedwrap" class="mb-3 d-none">
            <label class="form-label">Call again on <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="om-tc-resched-date" min="<?= date('Y-m-d') ?>">
          </div>
          <div id="om-tc-svdate" class="mb-3 d-none">
            <label class="form-label">Site Visit Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="om-site-visit-date">
          </div>
          <div id="om-tc-lost-reason" class="mb-3 d-none">
            <label class="form-label">Lost Reason <span class="text-danger">*</span></label>
            <select class="form-select" id="om-tc-lost-reason-val">
              <option value="">— Select reason —</option>
              <?php foreach (explode('|', getSetting('lost_reasons', '')) as $lr): $lr = trim($lr); if ($lr === '') continue; ?>
              <option><?= htmlspecialchars($lr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Sales Rep outcomes -->
        <div id="om-sr" class="d-none">
          <label class="form-label fw-semibold">Outcome</label>
          <div class="mb-3">
            <?php foreach (['Site Visit Pending', 'Site Visit Postponed', 'Lead Lost', 'Lead Won'] as $opt): ?>
            <div class="form-check">
              <input class="form-check-input om-outcome-sr" type="radio" name="om_outcome_sr"
                     value="<?= $opt ?>"
                     id="om-sr-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
              <label class="form-check-label"
                     for="om-sr-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
                <?= htmlspecialchars($opt) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div id="om-sr-nextdate" class="mb-3 d-none">
            <label class="form-label">Next Site Visit Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="om-next-visit-date">
          </div>
          <div id="om-sr-lost-reason" class="mb-3 d-none">
            <label class="form-label">Lost Reason <span class="text-danger">*</span></label>
            <select class="form-select" id="om-sr-lost-reason-val">
              <option value="">— Select reason —</option>
              <?php foreach (explode('|', getSetting('lost_reasons', '')) as $lr): $lr = trim($lr); if ($lr === '') continue; ?>
              <option><?= htmlspecialchars($lr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Sales Rep — site visit task (stage: Visit Scheduled) -->
        <div id="om-svs" class="d-none">
          <label class="form-label fw-semibold">Site visit outcome</label>
          <div class="mb-3">
            <?php foreach (['Site Visit Completed', 'Site Visit Rescheduled', 'Lead Lost'] as $opt): ?>
            <div class="form-check">
              <input class="form-check-input om-outcome-svs" type="radio" name="om_outcome_svs"
                     value="<?= $opt ?>" id="om-svs-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
              <label class="form-check-label"
                     for="om-svs-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
                <?= htmlspecialchars($opt) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div id="om-svs-quote" class="mb-3 d-none">
            <label class="form-label">Quote Amount (₹) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="om-svs-quote-val" min="1" step="100"
                   placeholder="e.g. 250000">
            <div class="form-text">Saved on the lead and moves it to <strong>Quoted</strong>,
              creating the follow-up task automatically.</div>
          </div>
          <div id="om-svs-nextdate" class="mb-3 d-none">
            <label class="form-label">New Visit Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="om-svs-next-date">
          </div>
          <div id="om-svs-lost" class="mb-3 d-none">
            <label class="form-label">Lost Reason <span class="text-danger">*</span></label>
            <select class="form-select" id="om-svs-lost-val">
              <option value="">— Select reason —</option>
              <?php foreach (explode('|', getSetting('lost_reasons', '')) as $lr): $lr = trim($lr); if ($lr === '') continue; ?>
              <option><?= htmlspecialchars($lr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Sales Rep — follow up and close (stage: Quoted) -->
        <div id="om-quo" class="d-none">
          <label class="form-label fw-semibold">Deal outcome</label>
          <div class="mb-3">
            <?php foreach (['Lead Won', 'Lead Lost', 'Still Following Up'] as $opt): ?>
            <div class="form-check">
              <input class="form-check-input om-outcome-quo" type="radio" name="om_outcome_quo"
                     value="<?= $opt ?>" id="om-quo-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
              <label class="form-check-label"
                     for="om-quo-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $opt))) ?>">
                <?= htmlspecialchars($opt) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <div id="om-quo-lost" class="mb-3 d-none">
            <label class="form-label">Lost Reason <span class="text-danger">*</span></label>
            <select class="form-select" id="om-quo-lost-val">
              <option value="">— Select reason —</option>
              <?php foreach (explode('|', getSetting('lost_reasons', '')) as $lr): $lr = trim($lr); if ($lr === '') continue; ?>
              <option><?= htmlspecialchars($lr) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div id="om-quo-next" class="mb-3 d-none">
            <label class="form-label">Next follow-up date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="om-quo-next-val">
            <div class="form-text">A fresh follow-up task is created for that date.</div>
          </div>
          <div id="om-quo-wonnote" class="alert alert-success small py-2 d-none">
            <i class="bi bi-info-circle me-1"></i>This moves the lead to <strong>Won</strong> and starts
            the delivery tasks for the Operation Manager.
          </div>
        </div>

        <!-- Simple confirm (manager and all other roles) -->
        <div id="om-simple" class="d-none">Mark this task as done?</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="om-submit">Confirm</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Pipeline workflow JS ──────────────────────────────────────────────── -->
<script>
// ── Outcome modal logic ───────────────────────────────────────────────────────
let outcomeModal = null;
function getOutcomeModal() {
    if (!outcomeModal) outcomeModal = new bootstrap.Modal(document.getElementById('outcomeModal'));
    return outcomeModal;
}
let activeCompleteBtn = null;

document.querySelectorAll('.wf-complete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        activeCompleteBtn = this;
        const role  = this.dataset.assignedRole;
        const stage = this.dataset.stage;
        document.getElementById('om-task-id').value = this.dataset.taskId;

        // Reset all panels and inputs
        ['om-tc', 'om-sr', 'om-svs', 'om-quo', 'om-simple'].forEach(id =>
            document.getElementById(id).classList.add('d-none'));
        ['om-tc-svdate', 'om-tc-lost-reason', 'om-tc-reschedwrap', 'om-sr-nextdate', 'om-sr-lost-reason',
         'om-svs-quote', 'om-svs-nextdate', 'om-svs-lost',
         'om-quo-lost', 'om-quo-next', 'om-quo-wonnote']
            .forEach(id => document.getElementById(id).classList.add('d-none'));
        document.querySelectorAll('.om-outcome, .om-outcome-sr, .om-outcome-svs, .om-outcome-quo')
            .forEach(r => r.checked = false);
        ['om-svs-quote-val', 'om-svs-next-date', 'om-quo-next-val']
            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

        if (role === 'tele_caller') {
            document.getElementById('om-tc').classList.remove('d-none');
        } else if (stage === 'New' && role === 'sales_rep') {
            document.getElementById('om-sr').classList.remove('d-none');
        } else if (stage === 'Visit Scheduled' && role === 'sales_rep') {
            document.getElementById('om-svs').classList.remove('d-none');
        } else if (stage === 'Quoted' && role === 'sales_rep') {
            document.getElementById('om-quo').classList.remove('d-none');
        } else {
            document.getElementById('om-simple').classList.remove('d-none');
        }
        getOutcomeModal().show();
    });
});

// TC conditional fields
document.querySelectorAll('.om-outcome').forEach(r => {
    r.addEventListener('change', function () {
        document.getElementById('om-tc-svdate').classList.toggle('d-none', this.value !== 'Arranged Site Visit');
        document.getElementById('om-tc-lost-reason').classList.toggle('d-none', this.value !== 'Lead Lost');
        document.getElementById('om-tc-reschedwrap').classList.toggle('d-none', this.value !== 'Reschedule Call');
    });
});

// SR conditional fields
document.querySelectorAll('.om-outcome-sr').forEach(r => {
    r.addEventListener('change', function () {
        document.getElementById('om-sr-nextdate').classList.toggle('d-none', this.value !== 'Site Visit Postponed');
        document.getElementById('om-sr-lost-reason').classList.toggle('d-none', this.value !== 'Lead Lost');
    });
});

// Site visit outcome conditional fields
document.querySelectorAll('.om-outcome-svs').forEach(r => {
    r.addEventListener('change', function () {
        document.getElementById('om-svs-quote').classList.toggle('d-none', this.value !== 'Site Visit Completed');
        document.getElementById('om-svs-nextdate').classList.toggle('d-none', this.value !== 'Site Visit Rescheduled');
        document.getElementById('om-svs-lost').classList.toggle('d-none', this.value !== 'Lead Lost');
    });
});

// Quoted outcome conditional fields
document.querySelectorAll('.om-outcome-quo').forEach(r => {
    r.addEventListener('change', function () {
        document.getElementById('om-quo-lost').classList.toggle('d-none', this.value !== 'Lead Lost');
        document.getElementById('om-quo-next').classList.toggle('d-none', this.value !== 'Still Following Up');
        document.getElementById('om-quo-wonnote').classList.toggle('d-none', this.value !== 'Lead Won');
    });
});

document.getElementById('om-submit').addEventListener('click', async function () {
    const taskId = parseInt(document.getElementById('om-task-id').value);
    const role   = activeCompleteBtn?.dataset.assignedRole;
    const stage  = activeCompleteBtn?.dataset.stage;
    const body   = { task_id: taskId };

    if (role === 'tele_caller') {
        const outcome = document.querySelector('.om-outcome:checked')?.value;
        if (!outcome) { alert('Please select an outcome.'); return; }
        body.outcome = outcome;
        if (outcome === 'Arranged Site Visit') {
            body.outcome_site_visit_date = document.getElementById('om-site-visit-date').value;
            if (!body.outcome_site_visit_date) { alert('Site Visit Date is required.'); return; }
        }
        if (outcome === 'Lead Lost') {
            body.outcome_lost_reason = document.getElementById('om-tc-lost-reason-val').value;
            if (!body.outcome_lost_reason) { alert('Lost Reason is required.'); return; }
        }
        if (outcome === 'Reschedule Call') {
            body.outcome_next_visit_date = document.getElementById('om-tc-resched-date').value;
            if (!body.outcome_next_visit_date) { alert('Choose the date to call the customer again.'); return; }
        }
    } else if (stage === 'New' && role === 'sales_rep') {
        const outcome = document.querySelector('.om-outcome-sr:checked')?.value;
        if (!outcome) { alert('Please select an outcome.'); return; }
        body.outcome = outcome;
        if (outcome === 'Site Visit Postponed') {
            body.outcome_next_visit_date = document.getElementById('om-next-visit-date').value;
            if (!body.outcome_next_visit_date) { alert('Next Site Visit Date is required.'); return; }
        }
        if (outcome === 'Lead Lost') {
            body.outcome_lost_reason = document.getElementById('om-sr-lost-reason-val').value;
            if (!body.outcome_lost_reason) { alert('Lost Reason is required.'); return; }
        }
    } else if (stage === 'Visit Scheduled' && role === 'sales_rep') {
        const outcome = document.querySelector('.om-outcome-svs:checked')?.value;
        if (!outcome) { alert('Please select an outcome.'); return; }
        body.outcome = outcome;
        if (outcome === 'Site Visit Completed') {
            body.quote_amount = document.getElementById('om-svs-quote-val').value;
            if (!body.quote_amount || parseFloat(body.quote_amount) <= 0) {
                alert('Quote amount is required to complete the site visit.'); return;
            }
        }
        if (outcome === 'Site Visit Rescheduled') {
            body.outcome_next_visit_date = document.getElementById('om-svs-next-date').value;
            if (!body.outcome_next_visit_date) { alert('New Visit Date is required.'); return; }
        }
        if (outcome === 'Lead Lost') {
            body.outcome_lost_reason = document.getElementById('om-svs-lost-val').value;
            if (!body.outcome_lost_reason) { alert('Lost Reason is required.'); return; }
        }
    } else if (stage === 'Quoted' && role === 'sales_rep') {
        const outcome = document.querySelector('.om-outcome-quo:checked')?.value;
        if (!outcome) { alert('Please select an outcome.'); return; }
        body.outcome = outcome;
        if (outcome === 'Lead Lost') {
            body.outcome_lost_reason = document.getElementById('om-quo-lost-val').value;
            if (!body.outcome_lost_reason) { alert('Lost Reason is required.'); return; }
        }
        if (outcome === 'Still Following Up') {
            body.outcome_next_visit_date = document.getElementById('om-quo-next-val').value;
            if (!body.outcome_next_visit_date) { alert('Next follow-up date is required.'); return; }
        }
    }

    const res = await apiFetch('/workflow/complete', {
        method: 'POST',
        body: JSON.stringify(body),
    });
    if (res.ok) {
        getOutcomeModal().hide();
        location.reload();
    } else {
        showToast('danger', res.error || res.message || 'Could not complete task.');
    }
});

// ── Skip task ─────────────────────────────────────────────────────────────────
document.querySelectorAll('.wf-skip-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Skip this task? The pipeline will still advance.')) return;
        const res = await apiFetch('/workflow/skip', {
            body: JSON.stringify({ task_id: parseInt(this.dataset.id) }),
        });
        if (res.ok) location.reload();
        else showToast('danger', res.message || 'Could not skip task.');
    });
});

// ── Cancel task ───────────────────────────────────────────────────────────────
document.querySelectorAll('.wf-cancel-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Cancel this task? The pipeline will NOT advance.')) return;
        const res = await apiFetch('/workflow/cancel', {
            body: JSON.stringify({ task_id: parseInt(this.dataset.id) }),
        });
        if (res.ok) location.reload();
        else showToast('danger', res.message || 'Could not cancel task.');
    });
});

// ── Reopen task ───────────────────────────────────────────────────────────────
document.querySelectorAll('.wf-reopen-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Reopen this task? It will be reset to pending.')) return;
        this.disabled = true;
        const res = await apiFetch('/workflow/reopen', {
            body: JSON.stringify({ task_id: parseInt(this.dataset.id) }),
        });
        if (res.ok) location.reload();
        else { showToast('danger', res.error || 'Could not reopen task.'); this.disabled = false; }
    });
});

// ── Force set stage (admin/manager) ──────────────────────────────────────────
document.getElementById('forceStageSelect')?.addEventListener('change', async function () {
    const stage = this.value;
    if (!stage) return;
    if (!confirm('Force set stage to "' + stage + '"? This bypasses normal flow restrictions.')) {
        this.value = ''; return;
    }
    const extra = promptQuoteAmount(stage);
    if (extra === null) { this.value = ''; return; }

    const res = await apiFetch('/leads/inline-edit', {
        body: JSON.stringify(Object.assign({
            lead_id: <?= (int)$lead['id'] ?>,
            field: 'stage',
            value: stage,
        }, extra)),
    });
    if (res.ok) location.reload();
    else { showToast('danger', res.error ?? 'Could not change stage.'); this.value = ''; }
});

// ── Assign task ───────────────────────────────────────────────────────────────
document.querySelectorAll('.wf-assign-select').forEach(sel => {
    sel.addEventListener('change', async function() {
        const taskId = this.dataset.taskId;
        const userId = this.value;
        const res = await apiFetch('/workflow/assign', {
            method: 'POST',
            body: JSON.stringify({ task_id: parseInt(taskId), user_id: parseInt(userId) || null }),
        });
        if (res.ok) {
            showToast('success', 'Task assigned to ' + (res.assigned_name || 'user') + '.');
            location.reload();
        } else {
            showToast('danger', res.message || 'Could not assign task.');
        }
    });
});

// ── Task due-date change (admin/manager only) ─────────────────────────────────
document.querySelectorAll('.wf-due-date-input').forEach(input => {
    const original = input.value;
    input.addEventListener('change', async function () {
        const taskId  = this.dataset.taskId;
        const dueDate = this.value || null;
        const res = await apiFetch('/workflow/set-due-date', {
            body: JSON.stringify({ task_id: parseInt(taskId), due_date: dueDate }),
        });
        if (res.ok) {
            showToast('success', 'Due date updated.');
        } else {
            showToast('danger', res.message || 'Could not update due date.');
            this.value = original; // revert on failure
        }
    });
});

// ── Phone reveal toggle (admin/manager only) ──────────────────────────────────
// ── KSEB process → auto-show & fill completed date ───────────────────────────
(function () {
    const sel  = document.getElementById('ksebProcessSelect');
    const wrap = document.getElementById('ksebCompletedWrap');
    const dateInput = document.getElementById('ksebCompletedDate');
    if (!sel) return;
    sel.addEventListener('change', function () {
        if (this.value === 'Complete') {
            wrap.style.display = '';
            if (!dateInput.value) {
                dateInput.value = new Date().toISOString().slice(0, 10);
            }
        } else {
            wrap.style.display = 'none';
            dateInput.value = '';
        }
    });
})();

document.getElementById('phoneRevealBtn')?.addEventListener('click', async function () {
    const isRevealed = this.classList.contains('btn-warning');
    const newVal     = isRevealed ? 0 : 1;
    const label      = newVal ? 'Reveal phone to sales rep?' : 'Hide phone from sales rep?';
    if (!confirm(label)) return;

    const res = await apiFetch('/leads/inline-edit', {
        body: JSON.stringify({
            lead_id: <?= (int)$lead['id'] ?>,
            field:   'phone_revealed',
            value:   newVal,
        }),
    });
    if (res.ok) location.reload();
    else showToast('danger', res.error ?? 'Could not update phone visibility.');
});

// ── Mark Won / Mark Lost (sales rep) ─────────────────────────────────────────
(function () {
    const LEAD_ID_OUT = <?= (int)$lead['id'] ?>;

    document.getElementById('markWonBtn')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('markWonModal')).show();
    });
    document.getElementById('markLostBtn')?.addEventListener('click', () => {
        new bootstrap.Modal(document.getElementById('markLostModal')).show();
    });

    document.getElementById('markWonConfirmBtn')?.addEventListener('click', async function () {
        this.disabled = true;
        const res = await apiFetch('/leads/mark-outcome', {
            body: JSON.stringify({
                lead_id:               LEAD_ID_OUT,
                outcome:               'won',
                doc_aadhar:            document.getElementById('won-aadhar').checked  ? 1 : 0,
                doc_registered_mobile: document.getElementById('won-mobile').checked  ? 1 : 0,
                doc_kseb_bill:         document.getElementById('won-kseb').checked    ? 1 : 0,
                doc_bank_passbook:     document.getElementById('won-passbook').checked ? 1 : 0,
            }),
        });
        if (res.ok) location.reload();
        else { showToast('danger', res.error ?? 'Could not mark Won.'); this.disabled = false; }
    });

    document.getElementById('markLostConfirmBtn')?.addEventListener('click', async function () {
        const reason = document.getElementById('lost-reason').value;
        if (!reason) { showToast('warning', 'Please select a status reason.'); return; }
        this.disabled = true;
        const res = await apiFetch('/leads/mark-outcome', {
            body: JSON.stringify({
                lead_id:              LEAD_ID_OUT,
                outcome:              'lost',
                status_reason_code:   reason,
                status_reason_notes:  document.getElementById('lost-notes').value.trim(),
            }),
        });
        if (res.ok) location.reload();
        else { showToast('danger', res.error ?? 'Could not mark Lost.'); this.disabled = false; }
    });
})();

// ── Manager Action Panel — Assign Modals ─────────────────────────────────────
async function amConfirm(btn, taskType, srcId, dateId) {
    const assignedTo = parseInt(document.getElementById(srcId)?.value) || null;
    const dueDate    = document.getElementById(dateId)?.value || '';

    if (!assignedTo) { alert('Please select a user.'); return; }
    if (!dueDate)    { alert('Please set a target date.'); return; }

    btn.disabled    = true;
    btn.textContent = 'Saving…';

    try {
        const res = await apiFetch('/workflow/create-task', {
            method: 'POST',
            body: JSON.stringify({
                lead_id:          <?= (int)$lead['id'] ?>,
                task_type:        taskType,
                assigned_user_id: assignedTo,
                due_date:         dueDate,
            }),
        });

        if (res.ok) {
            bootstrap.Modal.getInstance(btn.closest('.modal'))?.hide();
            location.reload();
        } else {
            alert(res.message || 'Could not create task.');
            btn.disabled    = false;
            btn.textContent = 'Assign';
        }
    } catch (err) {
        alert('Error: ' + err.message);
        btn.disabled    = false;
        btn.textContent = 'Assign';
    }
}
</script>

<style>
/* Pipeline progress bar */
.pipeline-bar {
    overflow-x: auto;
    padding-bottom: .5rem;
    scrollbar-width: thin;
}
.pipeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 80px;
    max-width: 100px;
    flex-shrink: 0;
    text-align: center;
}
.pipeline-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    font-weight: 700;
}
.pipeline-step.done    .pipeline-dot { background:#22c55e; color:#fff; }
.pipeline-step.current .pipeline-dot { background:#3b82f6; color:#fff; box-shadow:0 0 0 3px #bfdbfe; }
.pipeline-step.future  .pipeline-dot { background:#f1f5f9; color:#94a3b8; }

.pipeline-label {
    font-size: .6rem;
    margin-top: 4px;
    color: #64748b;
    line-height: 1.2;
}
.pipeline-step.current .pipeline-label { color:#3b82f6; font-weight:600; }
.pipeline-step.done    .pipeline-label { color:#22c55e; }

.pipeline-connector {
    height: 2px;
    flex: 1;
    background: #e2e8f0;
    min-width: 16px;
    margin-top: -16px;
    flex-shrink: 0;
}
.pipeline-connector.done { background: #22c55e; }
</style>

<?php require __DIR__ . '/../layout/footer.php'; ?>
