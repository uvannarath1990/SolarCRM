<?php
/**
 * Per-lead documentation workspace: status, the free-form file list, and the
 * legacy four-item checklist (which still posts to /documentation/save-docs
 * with its original contract — deliberately unchanged).
 */
$portalShell = true;
$narrowShell = true;
$extraJs     = ['doc-upload.js'];
require __DIR__ . '/../layout/header.php';

$canAssign = hasRole('admin', 'manager') || isDocumentationHead();
$phone     = dialablePhone($lead);
$statuses  = [
    'not_started' => 'Not started',
    'in_progress' => 'In progress',
    'on_hold'     => 'On hold',
    'completed'   => 'Complete',
];
$docsEditable = hasRole('admin', 'manager', 'operation_manager', 'documentation')
             || (hasRole('sales_rep') && (int) $lead['assigned_rep_id'] === (int) $_SESSION['user_id']);

?>

<div class="lead-hero">
    <div class="lh-top">
        <div class="min-w-0" style="flex:1">
            <h1><?= htmlspecialchars($lead['name']) ?></h1>
            <div class="lh-facts">
                <?php if (!empty($lead['location'])): ?>
                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($lead['location']) ?></span>
                <?php endif; ?>
                <?php if (!empty($lead['potential_kw'])): ?>
                <span><i class="bi bi-lightning-charge"></i> <?= htmlspecialchars((string) $lead['potential_kw']) ?> kW</span>
                <?php endif; ?>
                <span><i class="bi bi-flag"></i> <?= htmlspecialchars($lead['stage']) ?></span>
            </div>
        </div>
    </div>

    <?php if ($phone): ?>
    <div style="display:flex;gap:8px;margin-top:14px">
        <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
           style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:var(--r-sm);font-size:14px;font-weight:650;min-height:46px;color:var(--ok);border:1.5px solid #A7F3D0;background:var(--ok-soft)">
            <i class="bi bi-telephone-fill"></i>Call
        </a>
        <a href="https://wa.me/91<?= htmlspecialchars(preg_replace('/^(\+?91)/', '', $phone), ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener"
           style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px;border-radius:var(--r-sm);font-size:14px;font-weight:650;min-height:46px;color:#0B6B5E;border:1.5px solid #A7F3D0;background:#DCFCE7">
            <i class="bi bi-whatsapp"></i>WhatsApp
        </a>
    </div>
    <?php elseif (!empty($lead['phone'])): ?>
    <div class="v2-flash v2-flash-warning" style="margin-top:14px">
        <i class="bi bi-lock-fill"></i>
        <div><?= htmlspecialchars(maskPhone($lead['phone'])) ?> — ask a manager to reveal this number.</div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Customer ─────────────────────────────────────────────────────────── -->
<?php
// The documentation team needs the number itself, not just a Call button —
// they check it against the registered-mobile document. Masking still applies:
// dialablePhone() already returned '' for a rep who may not see it.
$showPhone = !empty($lead['phone']) && canSeePhone($lead);
?>
<div class="v2-card" style="margin-top:12px;padding:16px">
    <div class="v2-section" style="margin-top:0"><h2>Customer</h2><span class="rule"></span></div>

    <table class="fact-list">
        <?php if (!empty($lead['phone'])): ?>
        <tr>
            <td class="k">Phone</td>
            <td class="v">
                <?php if ($showPhone): ?>
                    <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lead['phone']) ?></a>
                <?php else: ?>
                    <i class="bi bi-lock-fill" style="color:var(--muted-2);font-size:12px"></i>
                    <?= htmlspecialchars(maskPhone($lead['phone'])) ?>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php if (!empty($lead['email'])): ?>
        <tr><td class="k">Email</td><td class="v"><?= htmlspecialchars($lead['email']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($lead['location'])): ?>
        <tr><td class="k">Location</td><td class="v"><?= htmlspecialchars($lead['location']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($lead['potential_kw'])): ?>
        <tr><td class="k">System size</td><td class="v"><?= htmlspecialchars((string) $lead['potential_kw']) ?> kW</td></tr>
        <?php endif; ?>
        <?php if (!empty($lead['quote_amount'])): ?>
        <tr><td class="k">Quote</td><td class="v"><?= formatCurrency((float) $lead['quote_amount']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($lead['source'])): ?>
        <tr><td class="k">Source</td><td class="v"><?= htmlspecialchars($lead['source']) ?></td></tr>
        <?php endif; ?>
        <tr><td class="k">Stage</td><td class="v"><?= htmlspecialchars($lead['stage']) ?></td></tr>
        <?php // Whoever sold it is who documentation chases when something is wrong. ?>
        <tr><td class="k">Sales rep</td><td class="v"><?= htmlspecialchars($lead['rep_name'] ?? '—') ?></td></tr>
    </table>
</div>

<!-- ── Status ───────────────────────────────────────────────────────────── -->
<div class="v2-card" style="margin-top:14px;padding:16px">
    <div class="v2-section" style="margin-top:0"><h2>Status</h2><span class="rule"></span></div>

    <?php
    // A lead can read "Not started" while its documentation task is long since
    // done: the status column was added later and never backfilled. Say so,
    // rather than leaving the two looking like a contradiction.
    $legacyDone = null;
    if ($lead['documentation_status'] === 'not_started') {
        $open = array_filter($docTasks ?? [], fn($t) => $t['status'] === 'pending');
        $shut = array_filter($docTasks ?? [], fn($t) => $t['status'] === 'done');
        if ($shut && !$open) $legacyDone = end($shut);
    }
    ?>
    <?php if ($legacyDone): ?>
    <div class="v2-flash v2-flash-success" style="margin-bottom:12px">
        <i class="bi bi-check-circle-fill"></i>
        <div>
            Documentation was completed
            <?= $legacyDone['completed_at'] ? 'on ' . date('d M Y', strtotime($legacyDone['completed_at'])) : '' ?>
            <?= $legacyDone['completer_name'] ? 'by ' . htmlspecialchars($legacyDone['completer_name']) : '' ?>,
            before this portal tracked status. Set it to <strong>Complete</strong> to tidy the record.
        </div>
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:7px;flex-wrap:wrap">
    <?php foreach ($statuses as $val => $label): ?>
        <button type="button" class="doc-status-btn v2-btn v2-btn-sm <?= $lead['documentation_status'] === $val ? 'is-on' : '' ?>"
                data-status="<?= $val ?>"><?= $label ?></button>
    <?php endforeach; ?>
    </div>

    <div style="margin-top:12px">
        <label class="text-muted-2" for="docNote"
               style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px">
            Note
        </label>
        <textarea id="docNote" class="v2-input" rows="2" maxlength="500"
                  placeholder="What is this waiting on?"><?= htmlspecialchars($lead['documentation_note'] ?? '') ?></textarea>
        <button type="button" class="v2-btn v2-btn-primary v2-btn-sm" id="saveStatusBtn" style="margin-top:8px">
            <i class="bi bi-check-lg"></i>Save status
        </button>
    </div>

    <table class="fact-list" style="margin-top:14px">
        <tr><td class="k">Site visit</td><td class="v"><?= $lead['site_visit_completed_date'] ? formatDate($lead['site_visit_completed_date']) : '—' ?></td></tr>
        <tr><td class="k">Started</td><td class="v"><?= $lead['documentation_started_at'] ? formatDateTime($lead['documentation_started_at']) : '—' ?></td></tr>
        <tr><td class="k">Completed</td><td class="v"><?= $lead['documentation_completed_at'] ? formatDateTime($lead['documentation_completed_at']) : '—' ?></td></tr>
    </table>

    <?php if ($canAssign): ?>
    <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--line)">
        <label class="text-muted-2" for="docOwner"
               style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;display:block;margin-bottom:6px">
            Documentation owner
        </label>
        <select id="docOwner" class="v2-input">
            <option value="">Unassigned</option>
            <?php foreach ($docUsers as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (int) $lead['documentation_user_id'] === (int) $u['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="v2-btn v2-btn-ghost v2-btn-sm" id="assignBtn" style="margin-top:8px">Assign</button>
    </div>
    <?php else: ?>
    <div class="text-muted-2" style="font-size:13px;margin-top:12px">
        Owner: <?= htmlspecialchars($lead['doc_user_name'] ?? 'Unassigned') ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Tasks ────────────────────────────────────────────────────────────── -->
<?php if ($docTasks): ?>
<div class="v2-card" style="margin-top:12px;padding:16px">
    <div class="v2-section" style="margin-top:0">
        <h2>Tasks</h2>
        <span class="rule"></span>
        <?php $openCount = count(array_filter($docTasks, fn($t) => $t['status'] === 'pending')); ?>
        <span class="pill <?= $openCount ? 'pill-info' : 'pill-ok' ?>">
            <?= $openCount ? $openCount . ' open' : 'All done' ?>
        </span>
    </div>

    <?php foreach ($docTasks as $t):
        $isOpen  = $t['status'] === 'pending';
        $late    = $isOpen && $t['due_date'] && (int) $t['days_late'] > 0 ? (int) $t['days_late'] : 0;
        $isSales = $t['assigned_role'] === 'sales_rep';
        $mine    = (int) $t['assigned_to'] === (int) $_SESSION['user_id'];
    ?>
    <div class="doc-task <?= $isOpen ? '' : 'is-done' ?>">
        <i class="bi bi-<?= $isOpen ? ($isSales ? 'arrow-left-right' : 'clipboard-check') : 'check-circle-fill' ?>"
           style="font-size:18px;flex:0 0 auto;color:<?= $isOpen ? ($late ? 'var(--danger)' : 'var(--muted)') : 'var(--ok)' ?>"></i>

        <div class="min-w-0" style="flex:1">
            <div style="font-weight:650;font-size:14px;<?= $isOpen ? '' : 'text-decoration:line-through;color:var(--muted)' ?>">
                <?= htmlspecialchars($t['title']) ?>
            </div>
            <div class="doc-task-meta">
                <?php // Who it belongs to — the name, not just a role. ?>
                <span><i class="bi bi-person"></i>
                    <?= $t['assignee_name']
                        ? htmlspecialchars($t['assignee_name'])
                        : '<span style="color:var(--warn)">Unassigned</span>' ?>
                </span>
                <?php if ($isSales): ?>
                <span class="pill pill-warn">Sales</span>
                <?php endif; ?>

                <?php if ($isOpen && $t['due_date']): ?>
                <span class="sep">·</span>
                <span class="<?= $late ? 'text-danger fw-semibold' : '' ?>">
                    Due <?= date('d M', strtotime($t['due_date'])) ?><?= $late ? ' · ' . $late . 'd late' : '' ?>
                </span>
                <?php elseif (!$isOpen): ?>
                <span class="sep">·</span>
                <span><?= htmlspecialchars(ucfirst($t['status'])) ?><?php
                    if ($t['completed_at']) echo ' ' . date('d M', strtotime($t['completed_at']));
                    if ($t['completer_name']) echo ' by ' . htmlspecialchars($t['completer_name']); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($isOpen && !empty($t['notes'])): ?>
            <div class="doc-task-meta" style="margin-top:3px">
                <span style="color:var(--warn)"><?= htmlspecialchars($t['notes']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($isOpen && $mine && !$isSales): ?>
        <button type="button" class="v2-btn v2-btn-sm doc-task-done" data-id="<?= (int) $t['id'] ?>"
                style="flex:0 0 auto">
            <i class="bi bi-check-lg"></i>Done
        </button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php // Shared with the CRM lead page — see views/partials/documents_card.php ?>
<?php require __DIR__ . '/../partials/documents_card.php'; ?>

<!-- ── Legacy checklist ─────────────────────────────────────────────────── -->
<?php
$docRows = [
    ['aadhar',   'doc_aadhar',            'Aadhar',           'doc_aadhar_number',   'Aadhar number (12 digits)', 'numeric', 14],
    ['mobile',   'doc_registered_mobile', 'Registered mobile','doc_mobile_number',   'Mobile number (10 digits)', 'numeric', 12],
    ['kseb',     'doc_kseb_bill',         'KSEB bill',        'doc_kseb_consumer_no','KSEB consumer number',      'text',    40],
    ['passbook', 'doc_bank_passbook',     'Bank pass book',   'doc_bank_account_no', 'Bank account number',       'text',    40],
];
$docsDone = 0;
foreach ($docRows as $r) if (!empty($lead[$r[1]])) $docsDone++;
?>
<div class="v2-card" style="margin-top:12px;padding:16px">
    <div class="v2-section" style="margin-top:0">
        <h2>Checklist</h2>
        <span class="rule"></span>
        <span class="pill <?= $docsDone === 4 ? 'pill-ok' : 'pill-muted' ?>"><?= $docsDone ?> of 4</span>
    </div>

    <?php foreach ($docRows as [$key, $flagCol, $label, $numCol, $placeholder, $mode, $maxLen]): ?>
    <div class="doc-row">
        <label class="doc-check">
            <input type="checkbox" id="dv-<?= $key ?>-chk"
                   <?= !empty($lead[$flagCol]) ? 'checked' : '' ?> <?= $docsEditable ? '' : 'disabled' ?>>
            <span><?= htmlspecialchars($label) ?></span>
        </label>
        <input type="text" class="v2-input doc-num" id="dv-<?= $key ?>-num"
               placeholder="<?= htmlspecialchars($placeholder) ?>"
               <?= $mode === 'numeric' ? 'inputmode="numeric"' : '' ?>
               maxlength="<?= (int) $maxLen ?>"
               value="<?= htmlspecialchars($lead[$numCol] ?? '') ?>"
               <?= $docsEditable ? '' : 'disabled' ?>>
    </div>
    <?php endforeach; ?>

    <?php if ($docsEditable): ?>
    <button class="v2-btn v2-btn-primary" id="saveDocsBtn" style="margin-top:14px;border-radius:var(--r-sm)">
        <i class="bi bi-check-lg"></i>Save checklist
    </button>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    var leadId = <?= (int) $lead['id'] ?>;
    var chosen = <?= json_encode($lead['documentation_status']) ?>;

    document.querySelectorAll('.doc-status-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('.doc-status-btn').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            chosen = b.dataset.status;
        });
    });

    document.getElementById('saveStatusBtn').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        apiFetch('/doc-portal/status', {
            body: JSON.stringify({
                lead_id: leadId,
                status:  chosen,
                note:    document.getElementById('docNote').value
            })
        }).then(function (res) {
            btn.disabled = false;
            if (res && res.ok) showToast('success', 'Status saved.');
            else showToast('danger', (res && res.error) || 'Could not save.');
        });
    });

    var assignBtn = document.getElementById('assignBtn');
    if (assignBtn) assignBtn.addEventListener('click', function () {
        assignBtn.disabled = true;
        apiFetch('/doc-portal/assign', {
            body: JSON.stringify({ lead_id: leadId, user_id: document.getElementById('docOwner').value || 0 })
        }).then(function (res) {
            assignBtn.disabled = false;
            if (res && res.ok) showToast('success', 'Owner updated.');
            else showToast('danger', (res && res.error) || 'Could not assign.');
        });
    });

    var saveDocs = document.getElementById('saveDocsBtn');
    if (saveDocs) saveDocs.addEventListener('click', function () {
        saveDocs.disabled = true;
        apiFetch('/documentation/save-docs', {
            body: JSON.stringify({
                lead_id:               leadId,
                doc_aadhar:            document.getElementById('dv-aadhar-chk').checked ? 1 : 0,
                doc_registered_mobile: document.getElementById('dv-mobile-chk').checked ? 1 : 0,
                doc_kseb_bill:         document.getElementById('dv-kseb-chk').checked ? 1 : 0,
                doc_bank_passbook:     document.getElementById('dv-passbook-chk').checked ? 1 : 0,
                doc_aadhar_number:     document.getElementById('dv-aadhar-num').value.trim(),
                doc_mobile_number:     document.getElementById('dv-mobile-num').value.trim(),
                doc_kseb_consumer_no:  document.getElementById('dv-kseb-num').value.trim(),
                doc_bank_account_no:   document.getElementById('dv-passbook-num').value.trim()
            })
        }).then(function (res) {
            saveDocs.disabled = false;
            if (res && res.ok) showToast('success', 'Checklist saved.');
            else showToast('danger', (res && res.error) || 'Could not save.');
        });
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
