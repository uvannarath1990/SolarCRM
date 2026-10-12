<?php
/**
 * Documentation queue — every lead whose documentation is live.
 *
 * Statuses come from the explicit documentation_status column, but the "active"
 * filter also picks up historical leads still reading 'not_started', since the
 * migration deliberately did not backfill.
 */
$portalShell = true;
$pageSub     = $total . ' lead' . ($total !== 1 ? 's' : '');
require __DIR__ . '/../layout/header.php';

$statusMeta = [
    'not_started' => ['Not started', 'pill-muted'],
    'in_progress' => ['In progress', 'pill-info'],
    'on_hold'     => ['On hold',     'pill-warn'],
    'completed'   => ['Complete',    'pill-ok'],
];
?>

<div class="v2-card" style="margin-top:14px;padding:12px">
    <form method="GET" action="<?= BASE_URL ?>/doc-portal/queue" style="display:flex;gap:8px;flex-wrap:wrap">
        <input type="hidden" name="route" value="doc-portal/queue">
        <input type="hidden" name="view" value="<?= htmlspecialchars($status) ?>">
        <input type="search" name="q" class="v2-input" placeholder="Name, phone or location"
               value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:180px">
        <button type="submit" class="v2-btn v2-btn-primary v2-btn-sm">Search</button>
        <?php if ($q !== ''): ?>
        <a href="<?= BASE_URL ?>/doc-portal/queue?view=<?= htmlspecialchars($status) ?>"
           class="v2-btn v2-btn-ghost v2-btn-sm">Clear</a>
        <?php endif; ?>
    </form>
</div>

<?php
// Pending / Received were separate v1 pages showing the same leads through the
// legacy checklist. They are views of this one queue now.
$views = [
    'active'    => ['In progress',  $tabCounts['active']],
    'overdue'   => ['Overdue',      $tabCounts['overdue'] ?? 0],
    'pending'   => ['Docs pending', $tabCounts['pending']],
    'received'  => ['Docs received',$tabCounts['received']],
    'completed' => ['Completed',    $tabCounts['completed']],
];
?>
<div style="display:flex;gap:7px;margin-top:12px;overflow-x:auto;scrollbar-width:none;padding-bottom:2px">
    <?php foreach ($views as $key => [$label, $n]): ?>
    <a href="<?= BASE_URL ?>/doc-portal/queue?view=<?= $key ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= !empty($ownerId) ? '&user=' . (int) $ownerId : '' ?>"
       style="flex:0 0 auto;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:600;white-space:nowrap;
              <?= $status === $key ? 'background:var(--ink);color:#fff'
                                   : 'border:1px solid var(--line-2);color:var(--ink-2);background:var(--surface)' ?>">
        <?= htmlspecialchars($label) ?> <?= (int) $n ?>
    </a>
    <?php endforeach; ?>
</div>

<?php if (!empty($ownerName)): ?>
<div class="v2-flash v2-flash-info" style="margin-top:12px">
    <i class="bi bi-person-fill"></i>
    <div>Showing <strong><?= htmlspecialchars($ownerName) ?></strong>'s documents.
        <a href="<?= BASE_URL ?>/doc-portal/queue?view=<?= htmlspecialchars($status) ?>">Show everyone</a>
    </div>
</div>
<?php endif; ?>

<?php if (!$leads): ?>
<div class="v2-empty" style="margin-top:20px">
    <i class="bi bi-folder2-open" style="color:var(--muted)"></i>
    <div class="t">Nothing here</div>
    <div class="s"><?php
        if ($q !== '')                echo 'No leads match that search.';
        elseif ($status === 'overdue')  echo 'Nothing has been sitting for 10 days or more.';
        elseif ($status === 'pending')  echo 'Every lead has all four documents.';
        elseif ($status === 'received') echo 'No complete document sets yet.';
        elseif ($status === 'completed')echo 'Nothing marked complete yet.';
        else                            echo 'No documentation in progress.';
    ?></div>
</div>
<?php endif; ?>

<?php foreach ($leads as $l):
    $meta    = $statusMeta[$l['documentation_status']] ?? $statusMeta['not_started'];
    $days    = $l['days_since_visit'] !== null ? (int) $l['days_since_visit'] : null;
    $docDays = $l['doc_days'] !== null ? (int) $l['doc_days'] : null;
    $ticks   = (int) $l['doc_aadhar'] + (int) $l['doc_registered_mobile']
             + (int) $l['doc_kseb_bill'] + (int) $l['doc_bank_passbook'];
    $onHold  = $l['documentation_status'] === 'on_hold';

    // Age against the documentation clock once it has started — time a lead
    // spent back with sales does not count. Leads that never started fall back
    // to the visit date, otherwise every historical lead flashes red at once
    // and the colour stops meaning anything.
    if ($docDays !== null) {
        $tone = $docDays >= 21 ? 'overdue' : ($docDays >= 10 ? 'today' : 'later');
    } else {
        $tone = $days !== null && $days >= 30 ? 'overdue' : ($days !== null && $days >= 14 ? 'today' : 'later');
    }
    $phone   = dialablePhone($l);
?>
<div class="task-card is-<?= $tone ?>">
    <div class="tc-body">
        <a href="<?= BASE_URL ?>/doc-portal/lead?id=<?= (int) $l['id'] ?>" class="tc-name">
            <?= htmlspecialchars($l['name']) ?>
        </a>
        <div class="tc-meta" style="margin-top:6px">
            <span class="pill <?= $meta[1] ?>"><?= $meta[0] ?></span>
            <?php if ($phone): ?>
            <span><i class="bi bi-telephone"></i> <?= htmlspecialchars($l['phone']) ?></span>
            <?php endif; ?>
            <?php if (!empty($l['location'])): ?>
            <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($l['location']) ?></span>
            <?php endif; ?>
            <?php if ($docDays !== null): ?>
            <span class="<?= $docDays >= 21 ? 'text-danger fw-semibold' : '' ?>">
                <?= $docDays ?> day<?= $docDays !== 1 ? 's' : '' ?> in documentation
            </span>
            <?php elseif ($days !== null): ?>
            <span><?= $days ?> day<?= $days !== 1 ? 's' : '' ?> since visit</span>
            <?php endif; ?>
        </div>
        <?php if ($onHold && !empty($l['doc_hold_reason'])): ?>
        <div class="tc-meta" style="margin-top:5px">
            <span class="pill pill-warn"><i class="bi bi-arrow-left-right"></i> With sales</span>
            <span style="color:var(--warn)"><?= htmlspecialchars($l['doc_hold_reason']) ?></span>
        </div>
        <?php endif; ?>

        <div class="tc-meta" style="margin-top:5px">
            <span><i class="bi bi-paperclip"></i> <?= (int) $l['doc_count'] ?> file<?= (int) $l['doc_count'] !== 1 ? 's' : '' ?></span>
            <span class="sep">·</span>
            <span class="<?= $ticks === 4 ? '' : 'text-danger' ?>">Checklist <?= $ticks ?>/4</span>
            <span class="sep">·</span>
            <span><?= $l['doc_user_name']
                    ? '<i class="bi bi-person"></i> ' . htmlspecialchars($l['doc_user_name'])
                    : '<span class="pill pill-warn">Unassigned</span>' ?></span>
        </div>
    </div>
    <div class="tc-actions">
        <?php if ($phone): ?>
        <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="act-call">
            <i class="bi bi-telephone-fill"></i>Call
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/doc-portal/lead?id=<?= (int) $l['id'] ?>" class="act-go">
            <i class="bi bi-folder2-open"></i>Open
        </a>
    </div>
</div>
<?php endforeach; ?>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:8px;justify-content:center;align-items:center;margin-top:18px">
    <?php if ($page > 1): ?>
    <a href="<?= BASE_URL ?>/doc-portal/queue?view=<?= htmlspecialchars($status) ?>&page=<?= $page - 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= !empty($ownerId) ? '&user=' . (int) $ownerId : '' ?>"
       class="v2-btn v2-btn-ghost v2-btn-sm">Previous</a>
    <?php endif; ?>
    <span class="text-muted-2" style="font-size:13px">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
    <a href="<?= BASE_URL ?>/doc-portal/queue?view=<?= htmlspecialchars($status) ?>&page=<?= $page + 1 ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?><?= !empty($ownerId) ? '&user=' . (int) $ownerId : '' ?>"
       class="v2-btn v2-btn-ghost v2-btn-sm">Next</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
