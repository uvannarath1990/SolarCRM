<?php
/**
 * Act — the rep answers one question: what happened?
 *
 * The valid outcomes are fixed by WorkflowController::completeTask() for each
 * (assigned_role, stage) pair. This page mirrors that set exactly, and reveals
 * only the one extra field the chosen outcome actually requires.
 *
 * ?q=12.45.88 carries the queue from the Today page, so finishing a task can
 * drop the rep straight into the next one instead of back to a list.
 */

$isDone   = ($task['status'] ?? '') !== 'pending';
$role     = $_SESSION['role'] ?? '';
$isAdmin  = in_array($role, ['admin', 'manager'], true);
$aRole    = $task['assigned_role'] ?? '';
$tStage   = $task['stage'] ?? '';

// Queue from the Today page, minus this task.
$queue = [];
if (!empty($_GET['q'])) {
    $queue = array_values(array_filter(
        array_map('intval', explode('.', (string) $_GET['q'])),
        fn($id) => $id > 0 && $id !== (int) $task['id']
    ));
}
$queuePos   = 0;
$queueTotal = 0;
if (!empty($_GET['q'])) {
    $all        = array_map('intval', explode('.', (string) $_GET['q']));
    $queueTotal = count($all);
    $idx        = array_search((int) $task['id'], $all, true);
    $queuePos   = $idx === false ? 0 : $idx + 1;
}

$needsOutcome = !$isDone && (
    $aRole === 'tele_caller'
    || ($aRole === 'sales_rep' && in_array($tStage, ['New', 'Visit Scheduled', 'Quoted'], true))
);

// A manager's New-stage task exists to put a name on the lead. Mirrors
// $needsAssignee in WorkflowController::completeTask().
$needsAssignee = !$isDone && $aRole === 'manager' && $tStage === 'New';
$visitDate     = $task['lead_visit_date'] ?? null;
$alreadyOwned  = !empty($task['lead_rep_id']) || !empty($task['lead_tc_id']);

// Must match the server's whitelist exactly.
$outcomes = [];
if ($needsOutcome) {
    if ($aRole === 'tele_caller') {
        $outcomes = [
            ['Arranged Site Visit', 'Visit arranged',  'Pick the visit date',        'calendar-check', 'ok',     'date_visit'],
            ['Reschedule Call',     'Call again later','Pick when to call back',     'telephone',      'info',   'date_next'],
            ['Lead Lost',           'Lost',            'Pick a reason',              'x-circle',       'danger', 'lost'],
        ];
    } elseif ($tStage === 'New') {
        $outcomes = [
            ['Site Visit Pending',   'Visit pending',  'Still to be done',           'hourglass-split', 'info',   null],
            ['Site Visit Postponed', 'Postponed',      'Pick a new date',            'calendar-x',      'warn',   'date_next'],
            ['Follow Up Again',      'Follow up later','Close this, add a new one',  'arrow-repeat',    'info',   'date_next'],
            ['Lead Won',             'Won',            'Customer confirmed',         'trophy',          'ok',     null],
            ['Lead Lost',            'Lost',           'Pick a reason',              'x-circle',        'danger', 'lost'],
        ];
    } elseif ($tStage === 'Visit Scheduled') {
        $outcomes = [
            ['Site Visit Completed',  'Visit done',    'Quote, documents, next step','check-circle',    'ok',     'visit_done'],
            ['Site Visit Rescheduled','Rescheduled',   'New visit date agreed',      'calendar-event',  'warn',   'date_next'],
            ['Lead Won',              'Won',           'Customer confirmed',         'trophy',          'ok',     null],
            ['Lead Lost',             'Lost',          'Pick a reason',              'x-circle',        'danger', 'lost'],
        ];
    } else { // Quoted
        $outcomes = [
            ['Lead Won',           'Won',             'Customer confirmed',          'trophy',          'ok',     null],
            ['Still Following Up', 'Still following',  'Pick the next follow-up',    'arrow-repeat',    'info',   'date_next'],
            ['Lead Lost',          'Lost',             'Pick a reason',              'x-circle',        'danger', 'lost'],
        ];
    }
}

// Respects the rep phone-reveal rule: dialablePhone() returns '' for a sales
// rep whose lead has not been revealed, so no tel:/wa.me link is rendered.
$phone = dialablePhone($task);

$narrowShell = true;   // one decision at a time — keep the column tight
$pageTitle = 'Act';
$pageSub   = $queueTotal > 1 ? ('Task ' . $queuePos . ' of ' . $queueTotal) : null;
$backLink  = BASE_URL . '/today';

require __DIR__ . '/../layout/header.php';
?>

<?php if ($queueTotal > 1): ?>
<div style="height:4px;background:var(--line);border-radius:2px;overflow:hidden;margin-top:14px">
    <div style="height:100%;background:var(--brand);width:<?= (int) round(($queuePos / max(1, $queueTotal)) * 100) ?>%"></div>
</div>
<?php endif; ?>

<!-- ── Who ──────────────────────────────────────────────────────────────── -->
<div class="lead-head" style="margin-top:14px">
    <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $task['lead_id'] ?>" style="color:inherit">
        <div class="lh-name"><?= htmlspecialchars($task['lead_name']) ?></div>
    </a>
    <div class="lh-meta">
        <?php if (!empty($task['lead_location'])): ?>
        <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($task['lead_location']) ?></span>
        <?php endif; ?>
        <span><i class="bi bi-flag"></i> <?= htmlspecialchars($task['lead_stage']) ?></span>
    </div>
    <?php if ($phone): ?>
    <div class="lh-contact">
        <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="c-call">
            <i class="bi bi-telephone-fill"></i>Call
        </a>
        <a href="https://wa.me/91<?= htmlspecialchars(preg_replace('/^(\+?91)/', '', $phone), ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener" class="c-wa">
            <i class="bi bi-whatsapp"></i>WhatsApp
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ── What ─────────────────────────────────────────────────────────────── -->
<div class="task-prompt">
    <div class="tp-k">The task</div>
    <div class="tp-v"><?= htmlspecialchars($task['title']) ?></div>
    <?php if (!empty($task['due_date'])):
        $late = (int) floor((strtotime('today') - strtotime($task['due_date'])) / 86400);
    ?>
    <div class="tp-due <?= $late > 0 ? 'overdue' : '' ?>">
        Due <?= date('d M', strtotime($task['due_date'])) ?><?= $late > 0 ? ' — ' . $late . ' day' . ($late > 1 ? 's' : '') . ' late' : '' ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($task['notes']) || !empty($priorNotes)): ?>
<!-- ── What was said before ─────────────────────────────────────────────── -->
<div class="v2-card" style="margin-top:14px;padding:14px">
    <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">
        <i class="bi bi-chat-left-quote"></i> What was said before
    </div>

    <?php
    // The task's own note (a return-to-sales reason, say) first, then the
    // trail of earlier calls, newest first.
    $entries = [];
    if (!empty($task['notes'])) {
        $entries[] = ['notes' => $task['notes'], 'outcome' => null,
                      'completed_at' => $task['created_at'] ?? null, 'by_name' => null];
    }
    foreach (($priorNotes ?? []) as $n) $entries[] = $n;
    ?>

    <?php foreach ($entries as $i => $n): ?>
    <div style="margin-top:<?= $i === 0 ? 10 : 12 ?>px;<?= $i > 0 ? 'padding-top:12px;border-top:1px solid var(--line);' : '' ?>">
        <div style="font-size:12px;color:var(--muted)">
            <?php if (!empty($n['completed_at'])): ?>
            <?= date('d M Y', strtotime($n['completed_at'])) ?>
            <?php endif; ?>
            <?= !empty($n['by_name']) ? ' · ' . htmlspecialchars($n['by_name']) : '' ?>
            <?= !empty($n['outcome']) ? ' · ' . htmlspecialchars($n['outcome']) : '' ?>
        </div>
        <div style="margin-top:3px;font-size:14px;line-height:1.5;white-space:pre-line">
            <?= htmlspecialchars($n['notes']) ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($isDone): ?>
<div class="v2-card" style="margin-top:14px;padding:16px">
    <div class="pill pill-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars(ucfirst($task['status'])) ?></div>
    <?php if (!empty($task['outcome'])): ?>
    <div style="margin-top:10px;font-weight:700"><?= htmlspecialchars($task['outcome']) ?></div>
    <?php endif; ?>
    <?php if (!empty($task['completer_name'])): ?>
    <div class="text-muted-2" style="font-size:13px;margin-top:4px">
        by <?= htmlspecialchars($task['completer_name']) ?>
        <?= !empty($task['completed_at']) ? ' · ' . date('d M, H:i', strtotime($task['completed_at'])) : '' ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($needsAssignee): ?>

<div class="v2-section"><h2>Who takes this lead?</h2><span class="rule"></span></div>

<?php
// When a visit is already booked the Sales Rep is the only sensible answer, so
// that group leads and the tele-caller group is tucked behind a disclosure.
$groups = $visitDate
    ? [['sales_rep', 'Sales Rep', $salesRepUsers], ['tele_caller', 'Tele Caller', $teleCallerUsers]]
    : [['tele_caller', 'Tele Caller', $teleCallerUsers], ['sales_rep', 'Sales Rep', $salesRepUsers]];
?>

<div class="v2-card" style="margin-top:14px;padding:14px">
    <div style="font-size:13px;color:var(--muted)">
        <?php if ($visitDate): ?>
        Visit booked for <strong><?= date('d M Y', strtotime($visitDate)) ?></strong>.
        Picking a Sales Rep moves this lead to <strong>Visit Scheduled</strong> and puts the
        site visit in their list for that day.
        <?php else: ?>
        Picking somebody assigns the lead to them and creates their first task for today.
        <?php endif; ?>
    </div>
</div>

<form id="assign-form" novalidate>
    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">

    <?php foreach ($groups as $gi => [$gRole, $gLabel, $gUsers]): ?>
        <?php if (empty($gUsers)) continue; ?>
        <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;
                    letter-spacing:.04em;margin:<?= $gi === 0 ? 18 : 22 ?>px 0 8px">
            <?= htmlspecialchars($gLabel) ?>
        </div>
        <div class="decision-list">
        <?php foreach ($gUsers as $u): ?>
            <button type="button" class="decision tone-<?= $gRole === 'sales_rep' ? 'ok' : 'info' ?>"
                    data-user="<?= (int) $u['id'] ?>" data-role="<?= $gRole ?>">
                <span class="ic"><i class="bi bi-person-fill"></i></span>
                <span class="lbl"><?= htmlspecialchars($u['name']) ?><small><?= htmlspecialchars($gLabel) ?></small></span>
                <span class="chev"><i class="bi bi-chevron-right"></i></span>
            </button>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div id="form-error" class="v2-flash v2-flash-danger" style="margin-top:12px;display:none"></div>

    <div class="v2-commit">
        <button type="submit" class="v2-btn v2-btn-primary" id="submit-btn" disabled>
            <i class="bi bi-check-lg"></i>Assign &amp; close task
        </button>
    </div>
</form>

<?php if ($alreadyOwned): ?>
<div style="margin-top:14px;text-align:center">
    <button type="button" class="v2-btn v2-btn-ghost" id="already-assigned-btn">
        Already assigned — just close this task
    </button>
</div>
<?php endif; ?>

<?php elseif ($needsOutcome): ?>

<div class="v2-section"><h2>What happened?</h2><span class="rule"></span></div>

<form id="outcome-form" novalidate>
    <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">

    <div class="decision-list">
    <?php foreach ($outcomes as [$value, $label, $hint, $icon, $tone, $needs]):
        // Each follow-up block is keyed to its own OUTCOME, not to the kind of
        // input it needs. Two outcomes on the same screen can want a date —
        // "Rescheduled" and "Follow up later" both do — and keying by type
        // opened both at once and read the value from whichever came first.
        $key = 'k' . substr(md5($value), 0, 8);
    ?>
        <button type="button" class="decision tone-<?= $tone ?>"
                data-outcome="<?= htmlspecialchars($value) ?>"
                data-needs="<?= htmlspecialchars((string) $needs) ?>"
                data-key="<?= $key ?>">
            <span class="ic"><i class="bi bi-<?= $icon ?>"></i></span>
            <span class="lbl"><?= htmlspecialchars($label) ?><small><?= htmlspecialchars($hint) ?></small></span>
            <span class="chev"><i class="bi bi-chevron-right"></i></span>
        </button>

        <?php if ($needs === 'visit_done'): ?>
        <div class="decision-followup" data-for="<?= $key ?>" hidden>
            <label for="inp-<?= $key ?>">Quote amount (required)</label>
            <input type="number" class="v2-input" id="inp-<?= $key ?>" name="quote_amount"
                   data-role="quote" inputmode="numeric" min="1" step="1" placeholder="345000">

            <?php // The rep is in the customer's house; this is the easiest
                  // moment there will ever be to collect these. ?>
            <label style="margin-top:14px">Documents collected today</label>
            <div class="doc-chips">
                <?php foreach (['aadhar' => 'Aadhar', 'mobile' => 'Registered mobile',
                                'kseb' => 'KSEB bill', 'passbook' => 'Bank pass book'] as $dk => $dl): ?>
                <label class="doc-chip">
                    <input type="checkbox" data-role="docs" value="<?= $dk ?>">
                    <span><?= $dl ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <label for="fu-<?= $key ?>" style="margin-top:14px">When will you follow up?</label>
            <input type="date" class="v2-input" id="fu-<?= $key ?>"
                   data-role="date_next" min="<?= date('Y-m-d') ?>">
            <div class="text-muted-2" style="font-size:12px;margin-top:5px">
                Leave blank to follow up today.
            </div>
        </div>
        <?php elseif ($needs === 'date_next'): ?>
        <div class="decision-followup" data-for="<?= $key ?>" hidden>
            <label for="inp-<?= $key ?>">
                <?= $value === 'Follow Up Again' ? 'Follow up on (required)' : 'Next date (required)' ?>
            </label>
            <input type="date" class="v2-input" id="inp-<?= $key ?>"
                   data-role="date_next" min="<?= date('Y-m-d') ?>">

            <?php // The call is about to be repeated by whoever picks this task
                  // up next — quite possibly a different person. ?>
            <label for="note-<?= $key ?>" style="margin-top:14px">What did they say?</label>
            <textarea class="v2-input" id="note-<?= $key ?>" data-role="note" rows="3"
                      maxlength="2000" style="resize:vertical"
                      placeholder="e.g. Comparing quotes, wants to decide after Onam. Ask for wife."></textarea>
            <div class="text-muted-2" style="font-size:12px;margin-top:5px">
                Optional — shown to whoever makes the next call.
            </div>
        </div>
        <?php elseif ($needs === 'date_visit'): ?>
        <div class="decision-followup" data-for="<?= $key ?>" hidden>
            <label for="inp-<?= $key ?>">Site visit date (required)</label>
            <input type="date" class="v2-input" id="inp-<?= $key ?>"
                   data-role="date_visit" min="<?= date('Y-m-d') ?>">
        </div>
        <?php elseif ($needs === 'lost'): ?>
        <div class="decision-followup" data-for="<?= $key ?>" hidden>
            <label for="inp-<?= $key ?>">Lost or blocked reason (required)</label>
            <select class="v2-input" id="inp-<?= $key ?>" data-role="lost">
                <option value="">Choose a reason…</option>
                <?php foreach ($lostReasons as $r): ?>
                <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <div id="form-error" class="v2-flash v2-flash-danger" style="margin-top:12px;display:none"></div>

    <div class="v2-commit">
        <button type="submit" class="v2-btn v2-btn-primary" id="submit-btn" disabled>
            <i class="bi bi-check-lg"></i>Save outcome
        </button>
    </div>
</form>

<?php else: ?>

<div class="v2-commit">
    <button type="button" class="v2-btn v2-btn-primary" id="simple-complete-btn">
        <i class="bi bi-check-lg"></i>Mark done
    </button>
</div>

<?php endif; ?>

<?php if ($isAdmin && !$isDone): ?>
<details class="v2-card" style="margin-top:16px;padding:14px">
    <summary style="font-weight:700;font-size:14px;cursor:pointer">Manager actions</summary>

    <div style="margin-top:14px">
        <label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">Reassign to</label>
        <select class="v2-input" id="assign-user" style="margin-top:6px">
            <option value="">Unassigned</option>
            <?php foreach ($assignableUsers as $u): ?>
            <option value="<?= (int) $u['id'] ?>" <?= (int) $u['id'] === (int) $task['assigned_to'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?> — <?= htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))) ?>
            </option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="v2-btn v2-btn-ghost" id="assign-btn" style="margin-top:8px">Reassign</button>
    </div>

    <div style="margin-top:16px">
        <label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">Due date</label>
        <input type="date" class="v2-input" id="due-date" style="margin-top:6px"
               value="<?= htmlspecialchars($task['due_date'] ?? '') ?>">
        <button type="button" class="v2-btn v2-btn-ghost" id="due-btn" style="margin-top:8px">Update due date</button>
    </div>

    <button type="button" class="v2-btn v2-btn-ghost" id="cancel-btn"
            style="margin-top:16px;color:var(--danger);border-color:#FECACA">
        Cancel this task
    </button>
</details>
<?php endif; ?>

<script>
(function () {
    'use strict';

    var taskId = <?= (int) $task['id'] ?>;
    var queue  = <?= json_encode($queue) ?>;
    var qAll   = <?= json_encode($_GET['q'] ?? '') ?>;

    var form     = document.getElementById('outcome-form');
    var errEl    = document.getElementById('form-error');
    var submitBt = document.getElementById('submit-btn');
    var chosen   = null;

    function showErr(msg) {
        if (!errEl) return;
        errEl.textContent = msg;
        errEl.style.display = '';
    }
    function clearErr() { if (errEl) errEl.style.display = 'none'; }

    // Picking an outcome reveals only the field that outcome needs.
    document.querySelectorAll('.decision').forEach(function (btn) {
        btn.addEventListener('click', function () {
            clearErr();
            document.querySelectorAll('.decision').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            chosen = {
                outcome: btn.dataset.outcome,
                needs:   btn.dataset.needs || '',
                key:     btn.dataset.key
            };

            // Exactly one block opens: the one belonging to this outcome.
            document.querySelectorAll('.decision-followup').forEach(function (f) {
                f.hidden = (f.dataset.for !== chosen.key);
            });

            if (submitBt) submitBt.disabled = false;
        });
    });

    function nextUrl() {
        if (queue.length === 0) return BASE_URL + '/today';
        return BASE_URL + '/workflow/detail?id=' + queue[0] + '&q=' + encodeURIComponent(qAll);
    }

    // Read from the block belonging to the chosen outcome, never "the first
    // visible one" — with two date fields on screen that picked the wrong input
    // and reported an empty date the user had already filled in.
    function chosenChecked(role) {
        if (!chosen || !chosen.key) return [];
        return [].slice.call(document.querySelectorAll(
            '.decision-followup[data-for="' + chosen.key + '"] [data-role="' + role + '"]:checked'
        )).map(function (el) { return el.value; });
    }

    function chosenValue(role) {
        if (!chosen || !chosen.key) return '';
        var el = document.querySelector(
            '.decision-followup[data-for="' + chosen.key + '"] [data-role="' + role + '"]'
        );
        return el ? el.value : '';
    }

    if (form) form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErr();
        if (!chosen) return showErr('Choose what happened first.');

        var body = { task_id: taskId, outcome: chosen.outcome };

        if (chosen.needs === 'visit_done') {
            var q = chosenValue('quote');
            if (!q || parseFloat(q) <= 0) return showErr('Enter the quote amount.');
            body.quote_amount = q;

            // Both optional: a visit with no documents and no agreed date is
            // still a completed visit.
            body.docs_received = chosenChecked('docs');
            var fu = chosenValue('date_next');
            if (fu) body.outcome_next_visit_date = fu;
        }
        if (chosen.needs === 'date_visit') {
            var dv = chosenValue('date_visit');
            if (!dv) return showErr('Pick the site visit date.');
            body.outcome_site_visit_date = dv;
        }
        if (chosen.needs === 'date_next') {
            var dn = chosenValue('date_next');
            if (!dn) {
                return showErr(chosen.outcome === 'Follow Up Again'
                    ? 'Pick the date to follow up on.'
                    : 'Pick a date.');
            }
            body.outcome_next_visit_date = dn;

            // Optional, and only some of these blocks carry a note box.
            var note = chosenValue('note');
            if (note && note.trim()) body.outcome_note = note.trim();
        }
        if (chosen.needs === 'lost') {
            var lr = chosenValue('lost');
            if (!lr) return showErr('Choose a reason.');
            body.outcome_lost_reason = lr;
        }

        submitBt.disabled = true;
        submitBt.textContent = 'Saving…';

        if (window.kreCaptureLocation) window.kreCaptureLocation('task');

        apiFetch('/workflow/complete', { body: JSON.stringify(body) }).then(function (res) {
            if (res && res.ok) {
                window.location.href = nextUrl();
            } else {
                showErr((res && res.error) || 'Could not save. Try again.');
                submitBt.disabled = false;
                submitBt.innerHTML = '<i class="bi bi-check-lg"></i>Save outcome';
            }
        });
    });

    // ── Assign & close (manager's New-stage tasks) ───────────────────────────
    // The generic .decision handler above already does the selecting and the
    // enabling; all that is left is to read who was picked.
    var assignForm = document.getElementById('assign-form');
    if (assignForm) assignForm.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErr();
        var picked = document.querySelector('.decision.is-selected');
        if (!picked) return showErr('Choose who this lead goes to.');

        submitBt.disabled = true;
        submitBt.textContent = 'Saving…';

        apiFetch('/workflow/complete', {
            body: JSON.stringify({
                task_id:     taskId,
                assign_to:   picked.dataset.user,
                assign_role: picked.dataset.role
            })
        }).then(function (res) {
            if (res && res.ok) {
                window.location.href = nextUrl();
            } else {
                showErr((res && res.error) || 'Could not save. Try again.');
                submitBt.disabled = false;
                submitBt.innerHTML = '<i class="bi bi-check-lg"></i>Assign &amp; close task';
            }
        });
    });

    var already = document.getElementById('already-assigned-btn');
    if (already) already.addEventListener('click', function () {
        already.disabled = true;
        apiFetch('/workflow/complete', { body: JSON.stringify({ task_id: taskId }) })
            .then(function (res) {
                if (res && res.ok) {
                    window.location.href = nextUrl();
                } else {
                    showToast('danger', (res && res.error) || 'Could not close task.');
                    already.disabled = false;
                }
            });
    });

    var simple = document.getElementById('simple-complete-btn');
    if (simple) simple.addEventListener('click', function () {
        simple.disabled = true;
        simple.textContent = 'Saving…';
        if (window.kreCaptureLocation) window.kreCaptureLocation('task');
        apiFetch('/workflow/complete', { body: JSON.stringify({ task_id: taskId }) }).then(function (res) {
            if (res && res.ok) {
                window.location.href = nextUrl();
            } else {
                showToast('danger', (res && res.error) || 'Could not complete task.');
                simple.disabled = false;
                simple.innerHTML = '<i class="bi bi-check-lg"></i>Mark done';
            }
        });
    });

    function adminPost(url, body, btn, label) {
        btn.disabled = true;
        apiFetch(url, { body: JSON.stringify(body) }).then(function (res) {
            if (res && res.ok) {
                window.location.reload();
            } else {
                showToast('danger', (res && res.error) || 'Could not save.');
                btn.disabled = false;
                btn.textContent = label;
            }
        });
    }

    var aBtn = document.getElementById('assign-btn');
    if (aBtn) aBtn.addEventListener('click', function () {
        adminPost('/workflow/assign',
            { task_id: taskId, user_id: document.getElementById('assign-user').value || 0 },
            aBtn, 'Reassign');
    });

    var dBtn = document.getElementById('due-btn');
    if (dBtn) dBtn.addEventListener('click', function () {
        var d = document.getElementById('due-date').value;
        if (!d) return showToast('danger', 'Pick a date.');
        adminPost('/workflow/set-due-date', { task_id: taskId, due_date: d }, dBtn, 'Update due date');
    });

    var cBtn = document.getElementById('cancel-btn');
    if (cBtn) cBtn.addEventListener('click', function () {
        if (!confirm('Cancel this task?')) return;
        adminPost('/workflow/cancel', { task_id: taskId }, cBtn, 'Cancel this task');
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
