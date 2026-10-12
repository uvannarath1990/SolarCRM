<?php
/**
 * Today, for a manager or admin.
 *
 * Not the rep queue. A manager who sees every open task in the business sees a
 * few hundred red cards and learns nothing; what they need is the shape of the
 * backlog — which stage it is piling up at, who is carrying it, and what has no
 * owner at all. Every figure links through to the per-task list in Task Review.
 */
$__u    = currentUser();
$__hour = (int) date('H');
$__greet = $__hour < 12 ? 'Good morning' : ($__hour < 17 ? 'Good afternoon' : 'Good evening');

$t          = $overview['totals'];
$pending    = (int) ($t['pending'] ?? 0);
$overdue    = (int) ($t['overdue'] ?? 0);
$unassigned = (int) ($t['unassigned'] ?? 0);
$unOldest   = $t['unassigned_oldest'] !== null ? (int) $t['unassigned_oldest'] : null;
$mine       = (int) ($overview['mine']['n'] ?? 0);
$mineLate   = (int) ($overview['mine']['overdue'] ?? 0);

$pageTitle   = 'Today';
$pageSub     = date('l, d M Y');
$narrowShell = true;
require __DIR__ . '/../layout/header.php';
?>

<div class="day-head">
    <div class="greet"><?= $__greet ?>, <?= htmlspecialchars(explode(' ', $__u['name'] ?? '')[0]) ?></div>
    <h1 class="headline">
        <?php if ($overdue === 0): ?>
            Nothing overdue
        <?php else: ?>
            <?= number_format($overdue) ?> overdue<br><span class="accent">across the team</span>
        <?php endif; ?>
    </h1>
</div>

<div class="stat-strip">
    <a class="stat" href="<?= BASE_URL ?>/tasks/review" style="color:inherit">
        <div class="v tnum"><?= number_format($pending) ?></div>
        <div class="k">Open</div>
    </a>
    <a class="stat <?= $overdue ? 'is-alert' : '' ?>" href="<?= BASE_URL ?>/tasks/review?overdue=1" style="color:inherit">
        <div class="v tnum"><?= number_format($overdue) ?></div>
        <div class="k">Overdue</div>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/tasks/review?unassigned=1" style="color:inherit">
        <div class="v tnum"><?= number_format($unassigned) ?></div>
        <div class="k">No owner</div>
    </a>
    <a class="stat" href="<?= BASE_URL ?>/tasks" style="color:inherit">
        <div class="v tnum"><?= number_format($mine) ?></div>
        <div class="k">Yours</div>
    </a>
</div>

<!-- ── Your own queue ───────────────────────────────────────────────────── -->
<?php if ($mine > 0): ?>
<a href="<?= BASE_URL ?>/tasks" class="v2-flash <?= $mineLate ? 'v2-flash-danger' : 'v2-flash-info' ?>"
   style="margin-top:14px">
    <i class="bi bi-person-fill"></i>
    <div>
        <strong><?= number_format($mine) ?> assigned to you</strong><?php
        if ($mineLate): ?>, <?= number_format($mineLate) ?> overdue<?php endif; ?> — open My Tasks
    </div>
</a>
<?php endif; ?>

<!-- ── Nobody owns these ────────────────────────────────────────────────── -->
<?php if ($unassigned > 0): ?>
<a href="<?= BASE_URL ?>/tasks/review?unassigned=1" class="v2-flash v2-flash-warning" style="margin-top:10px">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div>
        <strong><?= number_format($unassigned) ?> task<?= $unassigned !== 1 ? 's have' : ' has' ?> no owner</strong><?php
        if ($unOldest !== null): ?> — the oldest is <?= number_format($unOldest) ?> days late<?php endif; ?>.
        Nobody is going to pick these up on their own.
    </div>
</a>
<?php endif; ?>

<!-- ── Where it is stuck ────────────────────────────────────────────────── -->
<?php if (!empty($overview['byStage'])):
    $stageMax = max(array_map(fn($r) => (int) $r['overdue'], $overview['byStage'])); ?>
<div class="v2-section">
    <h2>Where it is stuck</h2>
    <span class="rule"></span>
</div>
<div class="v2-card" style="padding:14px">
    <?php // A pile at one stage is a process problem, not a person problem. ?>
    <?php foreach ($overview['byStage'] as $r):
        $n   = (int) $r['overdue'];
        $pct = $stageMax > 0 ? max(3, (int) round($n / $stageMax * 100)) : 0; ?>
    <a href="<?= BASE_URL ?>/tasks/review?stage=<?= urlencode((string) $r['stage']) ?>&overdue=1"
       style="display:flex;align-items:center;gap:10px;padding:7px 0;color:inherit">
        <div style="flex:0 0 150px;font-size:13.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($r['stage']) ?>
        </div>
        <div style="flex:1;height:8px;background:var(--line);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--danger)"></div>
        </div>
        <div class="tnum" style="flex:0 0 42px;text-align:right;font-weight:700;font-size:13.5px"><?= $n ?></div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Who is carrying it ───────────────────────────────────────────────── -->
<?php if (!empty($overview['byOwner'])): ?>
<div class="v2-section">
    <h2>Who is carrying it</h2>
    <span class="count"><?= count($overview['byOwner']) ?></span>
    <span class="rule"></span>
</div>

<div class="v2-card" style="overflow-x:auto">
<table class="table mb-0" style="min-width:420px">
    <thead>
        <tr>
            <th>Person</th>
            <th class="text-end">Open</th>
            <th class="text-end">Overdue</th>
            <th class="text-end">Oldest</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($overview['byOwner'] as $o):
        $late = (int) $o['overdue'];
        $old  = $o['oldest_days'] !== null ? (int) $o['oldest_days'] : null;
        $uid  = $o['user_id'] !== null ? (int) $o['user_id'] : 0;
        $url  = BASE_URL . '/tasks/review?overdue=1' . ($uid ? '&user=' . $uid : '&unassigned=1');
    ?>
        <tr>
            <td>
                <a href="<?= htmlspecialchars($url) ?>" style="font-weight:650">
                    <?= htmlspecialchars($o['name']) ?>
                </a>
                <?php if (!empty($o['role'])): ?>
                <div class="text-muted-2" style="font-size:11.5px">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $o['role']))) ?>
                </div>
                <?php endif; ?>
            </td>
            <td class="text-end tnum"><?= (int) $o['n'] ?></td>
            <td class="text-end tnum">
                <span class="pill <?= $late >= 50 ? 'pill-danger' : ($late >= 15 ? 'pill-warn' : 'pill-muted') ?>">
                    <?= $late ?>
                </span>
            </td>
            <td class="text-end tnum">
                <?= $old !== null
                    ? '<span class="' . ($old >= 30 ? 'text-danger fw-semibold' : '') . '">' . $old . 'd</span>'
                    : '<span class="text-muted-2">—</span>' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="text-muted-2" style="font-size:12.5px;margin-top:8px">
    A large number next to one name usually means work is queued behind that person, not that they are slow.
</div>
<?php endif; ?>

<?php if ($overdue === 0 && $pending === 0): ?>
<div class="v2-empty" style="margin-top:20px">
    <i class="bi bi-check2-all"></i>
    <div class="t">Nothing open</div>
    <div class="s">No pending pipeline tasks anywhere.</div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
