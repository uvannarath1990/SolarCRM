<?php
/**
 * Documentation home — the numbers first, then the work worth doing now.
 *
 * Scoping matches the rest of the portal: a documentation user sees their own
 * figures, a manager sees their reportees', admins and the Head see everyone.
 */
$portalShell = true;
$pageSub     = $scopeLabel;
require __DIR__ . '/../layout/header.php';

$overdue  = (int) ($stats['overdue'] ?? 0);
$openNow  = (int) ($stats['open_now'] ?? 0);
$withSales= (int) ($stats['with_sales'] ?? 0);
$doneMon  = (int) ($stats['done_month'] ?? 0);
$avgDays  = $stats['avg_days'] !== null ? (int) $stats['avg_days'] : null;
$wonInc   = (int) ($stats['won_incomplete'] ?? 0);
$isMgr    = hasRole('admin', 'manager', 'operation_manager') || isDocumentationHead();
?>

<div class="day-head">
    <div class="greet"><?= htmlspecialchars($scopeLabel) ?></div>
    <h1 class="headline">
        <?php if ($openNow === 0): ?>
            Nothing open
        <?php else: ?>
            <?= $openNow ?> open<?php if ($overdue > 0): ?><br><span class="accent"><?= $overdue ?> running late</span><?php endif; ?>
        <?php endif; ?>
    </h1>
</div>

<div class="stat-strip">
    <a class="stat" href="<?= BASE_URL ?>/doc-portal/queue?view=active" style="color:inherit">
        <div class="v tnum"><?= $openNow ?></div>
        <div class="k">Open</div>
    </a>
    <a class="stat <?= $overdue > 0 ? 'is-alert' : '' ?>" href="<?= BASE_URL ?>/doc-portal/queue?view=overdue" style="color:inherit">
        <div class="v tnum"><?= $overdue ?></div>
        <div class="k">Late</div>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/doc-portal/queue?view=active" style="color:inherit">
        <div class="v tnum"><?= $withSales ?></div>
        <div class="k">With sales</div>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/doc-portal/queue?view=completed" style="color:inherit">
        <div class="v tnum"><?= $doneMon ?></div>
        <div class="k">Done</div>
    </a>
</div>

<div class="text-muted-2" style="font-size:12.5px;margin-top:8px">
    <?php if ($avgDays !== null): ?>
    Averaging <strong><?= $avgDays ?> day<?= $avgDays !== 1 ? 's' : '' ?></strong> to complete
    <?php else: ?>
    No completions yet to average
    <?php endif; ?>
    · <?= $wonInc ?> won lead<?= $wonInc !== 1 ? 's' : '' ?> still short of documents
</div>

<!-- ── What is holding things up ────────────────────────────────────────── -->
<?php
$missTotal = array_sum(array_map('intval', array_values($missing ?: [])));
if ($missTotal > 0):
    $missLabels = ['aadhar' => 'Aadhar', 'mobile' => 'Registered mobile',
                   'kseb' => 'KSEB bill', 'passbook' => 'Bank pass book'];
    arsort($missing);
?>
<div class="v2-section"><h2>Most often missing</h2><span class="rule"></span></div>
<div class="v2-card" style="padding:14px">
    <?php foreach ($missing as $key => $n): $n = (int) $n; if (!isset($missLabels[$key])) continue;
        $pct = $missTotal > 0 ? round($n / max(1, (int) $missing[array_key_first($missing)]) * 100) : 0; ?>
    <div style="display:flex;align-items:center;gap:10px;padding:6px 0">
        <div style="flex:0 0 130px;font-size:13.5px"><?= $missLabels[$key] ?></div>
        <div style="flex:1;height:8px;background:var(--line);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--brand)"></div>
        </div>
        <div class="tnum" style="flex:0 0 32px;text-align:right;font-weight:700;font-size:13.5px"><?= $n ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Team ─────────────────────────────────────────────────────────────── -->
<?php if ($isMgr && $team): ?>
<div class="v2-section">
    <h2><?= htmlspecialchars($scopeLabel) ?></h2>
    <span class="count"><?= count($team) ?></span>
    <span class="rule"></span>
</div>

<div class="v2-card" style="overflow-x:auto">
<table class="table mb-0" style="min-width:520px">
    <thead>
        <tr>
            <th>Person</th>
            <th class="text-end">Open</th>
            <th class="text-end">Late</th>
            <th class="text-end">Oldest</th>
            <th class="text-end">With sales</th>
            <th class="text-end">Done</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($team as $t):
        $tOver = (int) $t['overdue']; $tOld = $t['oldest_days'] !== null ? (int) $t['oldest_days'] : null; ?>
        <tr>
            <td>
                <a href="<?= BASE_URL ?>/doc-portal/queue?view=active&user=<?= (int) $t['id'] ?>"
                   style="font-weight:650"><?= htmlspecialchars($t['name']) ?></a>
            </td>
            <td class="text-end tnum"><?= (int) $t['open_now'] ?></td>
            <td class="text-end tnum">
                <?php if ($tOver > 0): ?>
                <a href="<?= BASE_URL ?>/doc-portal/queue?view=overdue&user=<?= (int) $t['id'] ?>"
                   class="pill pill-danger"><?= $tOver ?></a>
                <?php else: ?><span class="text-muted-2">—</span><?php endif; ?>
            </td>
            <td class="text-end tnum">
                <?php if ($tOld !== null): ?>
                <span class="<?= $tOld >= 21 ? 'text-danger fw-semibold' : '' ?>"><?= $tOld ?>d</span>
                <?php else: ?><span class="text-muted-2">—</span><?php endif; ?>
            </td>
            <td class="text-end tnum"><?= (int) $t['with_sales'] ?: '<span class="text-muted-2">—</span>' ?></td>
            <td class="text-end tnum"><?= (int) $t['done_month'] ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php // The load split is the point of this table, so say it plainly.
$loads = array_map(fn($t) => (int) $t['open_now'], $team);
if ($loads && (max($loads) - min($loads)) >= 5): ?>
<div class="text-muted-2" style="font-size:12.5px;margin-top:8px">
    Workload ranges from <?= min($loads) ?> to <?= max($loads) ?> open items.
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Needs attention ──────────────────────────────────────────────────── -->
<?php if ($urgentLeads): ?>
<div class="v2-section">
    <h2>Longest waiting</h2>
    <span class="rule"></span>
    <a href="<?= BASE_URL ?>/doc-portal/queue?view=active" class="pill pill-muted">View all</a>
</div>

<?php foreach ($urgentLeads as $l):
    $d    = $l['doc_days'] !== null ? (int) $l['doc_days'] : 0;
    $tone = $d >= 21 ? 'overdue' : ($d >= 10 ? 'today' : 'later');
    $hold = $l['documentation_status'] === 'on_hold';
?>
<div class="task-card is-<?= $tone ?>">
    <div class="tc-body">
        <a href="<?= BASE_URL ?>/doc-portal/lead?id=<?= (int) $l['id'] ?>" class="tc-name">
            <?= htmlspecialchars($l['name']) ?>
        </a>
        <div class="tc-meta" style="margin-top:5px">
            <span class="<?= $d >= 21 ? 'text-danger fw-semibold' : '' ?>">
                <?= $d ?> day<?= $d !== 1 ? 's' : '' ?> in documentation
            </span>
            <?php if (!empty($l['doc_user_name'])): ?>
            <span class="sep">·</span><span><i class="bi bi-person"></i> <?= htmlspecialchars($l['doc_user_name']) ?></span>
            <?php endif; ?>
            <?php if ($hold): ?>
            <span class="pill pill-warn"><i class="bi bi-arrow-left-right"></i> With sales</span>
            <?php endif; ?>
        </div>
        <?php if ($hold && !empty($l['doc_hold_reason'])): ?>
        <div class="tc-meta" style="margin-top:4px">
            <span style="color:var(--warn)"><?= htmlspecialchars($l['doc_hold_reason']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <div class="tc-actions">
        <a href="<?= BASE_URL ?>/doc-portal/lead?id=<?= (int) $l['id'] ?>" class="act-go">
            <i class="bi bi-folder2-open"></i>Open
        </a>
    </div>
</div>
<?php endforeach; ?>

<?php else: ?>
<div class="v2-empty" style="margin-top:20px">
    <i class="bi bi-check2-all"></i>
    <div class="t">Nothing waiting</div>
    <div class="s">No documentation is currently in progress.</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
