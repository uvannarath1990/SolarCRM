<?php
$__u     = currentUser();
$__role  = $_SESSION['role'] ?? '';
$__hour  = (int) date('H');
$__greet = $__hour < 12 ? 'Good morning' : ($__hour < 17 ? 'Good afternoon' : 'Good evening');

$pageTitle   = 'Today';
$pageSub     = date('l, d M Y');
$narrowShell = true;   // a reading queue, not a data table

$totalWf   = count($wfOverdue) + count($wfToday) + count($wfFuture);
$overdueCt = count($wfOverdue);

require __DIR__ . '/../layout/header.php';

/**
 * One task card. Urgency is carried by the left rail rather than by tinting the
 * whole card, so a bad day doesn't turn into an unreadable wall of red.
 *
 * $queue is the ordered list of task ids in this view — passed through to the
 * Act page so it can advance straight to the next one instead of bouncing the
 * rep back to this list after every single task.
 */
function v2TaskCard(array $t, string $tone, array $queue = []): void
{
    $phone  = dialablePhone($t);
    $isOver = $tone === 'overdue';

    $due = null;
    if (!empty($t['due_date'])) {
        $days = (int) floor((strtotime('today') - strtotime($t['due_date'])) / 86400);
        if ($days > 0)       $due = $days . ' day' . ($days > 1 ? 's' : '') . ' late';
        elseif ($days === 0) $due = 'Due today';
        else                 $due = 'Due ' . date('d M', strtotime($t['due_date']));
    }

    $actUrl = BASE_URL . '/workflow/detail?id=' . (int) $t['id'];
    if ($queue) {
        $actUrl .= '&q=' . implode('.', array_map('intval', $queue));
    }
?>
<div class="task-card is-<?= $tone ?>">
    <div class="tc-body">
        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $t['lead_id'] ?>" class="tc-name">
            <?= htmlspecialchars($t['lead_name']) ?>
        </a>
        <div class="tc-title"><?= htmlspecialchars($t['title']) ?></div>
        <div class="tc-meta">
            <span class="pill pill-stage"><?= htmlspecialchars($t['stage']) ?></span>
            <?php if ($due): ?>
                <span class="<?= $isOver ? 'text-danger fw-semibold' : '' ?>"><?= htmlspecialchars($due) ?></span>
            <?php endif; ?>
            <?php if (!empty($t['lead_location'])): ?>
                <span><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($t['lead_location']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="tc-actions">
        <?php if ($phone): ?>
        <a href="tel:<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>" class="act-call">
            <i class="bi bi-telephone-fill"></i>Call
        </a>
        <a href="https://wa.me/91<?= htmlspecialchars(preg_replace('/^(\+?91)/', '', $phone), ENT_QUOTES, 'UTF-8') ?>"
           target="_blank" rel="noopener" class="act-wa">
            <i class="bi bi-whatsapp"></i>Chat
        </a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($actUrl) ?>" class="act-go">
            <i class="bi bi-check2-square"></i>Act
        </a>
    </div>
</div>
<?php }

// Queue order: overdue first, then today, then upcoming — the same order the
// rep reads down the page, so "next" always means the next card they can see.
$queueIds = array_map(
    fn($t) => (int) $t['id'],
    array_merge($wfOverdue, $wfToday, $wfFuture)
);
?>

<div class="day-head">
    <div class="greet"><?= $__greet ?>, <?= htmlspecialchars(explode(' ', $__u['name'] ?? '')[0]) ?></div>
    <h1 class="headline">
        <?php if ($totalWf === 0): ?>
            You're all clear
        <?php else: ?>
            <?= $totalWf ?> thing<?= $totalWf !== 1 ? 's' : '' ?> need<?= $totalWf === 1 ? 's' : '' ?> you<?php
            if ($overdueCt > 0): ?><br><span class="accent"><?= $overdueCt ?> <?= $overdueCt === 1 ? 'is' : 'are' ?> overdue</span><?php endif; ?>
        <?php endif; ?>
    </h1>
</div>

<?php if (in_array($__role, ['admin', 'sales_rep'], true)): ?>
<div class="stat-strip">
    <div class="stat">
        <div class="v tnum"><?= (int) ($kpis['total'] ?? 0) ?></div>
        <div class="k">Active</div>
    </div>
    <div class="stat">
        <div class="v tnum"><?= formatCurrency($kpis['pipeline'] ?? 0) ?></div>
        <div class="k">Pipeline</div>
    </div>
    <div class="stat">
        <div class="v tnum"><?= (int) ($kpis['wonMonth'] ?? 0) ?></div>
        <div class="k">Won</div>
    </div>
</div>
<?php elseif ($__role === 'tele_caller'): ?>
<div class="stat-strip">
    <div class="stat"><div class="v tnum"><?= (int) ($kpis['total'] ?? 0) ?></div><div class="k">My leads</div></div>
    <div class="stat"><div class="v tnum"><?= (int) ($kpis['newCt'] ?? 0) ?></div><div class="k">To call</div></div>
    <div class="stat"><div class="v tnum"><?= (int) ($kpis['toSchedCt'] ?? 0) ?></div><div class="k">To schedule</div></div>
</div>
<?php endif; ?>

<?php if ($totalWf === 0): ?>
<div class="v2-empty" style="margin-top:20px">
    <i class="bi bi-check2-all"></i>
    <div class="t">Nothing pending</div>
    <div class="s">Every task assigned to you is done.</div>
</div>
<?php endif; ?>

<?php if ($wfOverdue): ?>
<div class="v2-section danger">
    <h2>Overdue</h2>
    <span class="count"><?= count($wfOverdue) ?></span>
    <span class="rule"></span>
</div>
<?php foreach ($wfOverdue as $t) v2TaskCard($t, 'overdue', $queueIds); ?>
<?php endif; ?>

<?php if ($wfToday): ?>
<div class="v2-section">
    <h2>Today</h2>
    <span class="count"><?= count($wfToday) ?></span>
    <span class="rule"></span>
</div>
<?php foreach ($wfToday as $t) v2TaskCard($t, 'today', $queueIds); ?>
<?php endif; ?>

<?php if ($wfFuture): ?>
<div class="v2-section">
    <h2>Upcoming</h2>
    <span class="count"><?= count($wfFuture) ?></span>
    <span class="rule"></span>
</div>
<?php foreach ($wfFuture as $t) v2TaskCard($t, 'later', $queueIds); ?>
<?php endif; ?>

<?php if (!empty($overdueOps) && hasRole('admin', 'installer', 'sales_rep')): ?>
<a href="<?= BASE_URL ?>/operations?status=pending" class="v2-flash v2-flash-warning" style="margin-top:18px">
    <i class="bi bi-tools"></i>
    <div><strong><?= (int) $overdueOps ?> operation<?= $overdueOps !== 1 ? 's' : '' ?> overdue</strong> — tap to review</div>
</a>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
