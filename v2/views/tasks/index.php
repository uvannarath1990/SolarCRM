<?php
/**
 * My Tasks — the same card language as Today, so the two pages read as one app.
 *
 * The v1 accordion is gone: it hid overdue work behind a click, which is the
 * exact opposite of what a list of overdue work is for. Sections are always
 * open and ordered overdue → today → upcoming.
 */

$typeFilter     = $_GET['type'] ?? 'all';
$filterUserId   = (int) ($_GET['filter_user'] ?? 0);
$filterDateFrom = trim($_GET['filter_date_from'] ?? '');
$filterDateTo   = trim($_GET['filter_date_to'] ?? '');
$today          = date('Y-m-d');

$isAdminMgr     = in_array($_SESSION['role'] ?? '', ['admin', 'manager', 'operation_manager'], true);
$hasActiveFilter = $filterUserId || $filterDateFrom || $filterDateTo;

$pipelineRoles  = ['tc' => 'tele_caller', 'sv' => 'sales_rep', 'doc' => 'documentation', 'ops_task' => 'operation_manager'];
$isPipelineRole = array_key_exists($typeFilter, $pipelineRoles);

if ($typeFilter === 'all') {
    $filtered = $tasks;
} elseif ($isPipelineRole) {
    $roleVal  = $pipelineRoles[$typeFilter];
    $filtered = array_values(array_filter(
        $tasks,
        fn($t) => $t['type'] === 'workflow' && ($t['assigned_role'] ?? '') === $roleVal
    ));
} else {
    $filtered = array_values(array_filter($tasks, fn($t) => $t['type'] === $typeFilter));
}

$overdueTasks = array_values(array_filter($filtered, fn($t) => !empty($t['date']) && $t['date'] < $today));
$todayTasks   = array_values(array_filter($filtered, fn($t) => ($t['date'] ?? null) === $today));
$futureTasks  = array_values(array_filter($filtered, fn($t) => empty($t['date']) || $t['date'] > $today));

foreach ([&$overdueTasks, &$todayTasks, &$futureTasks] as &$bucket) {
    usort($bucket, fn($a, $b) => strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'));
}
unset($bucket);

$counts = ['all' => count($tasks), 'reminder' => 0, 'followup' => count($overdueFollowups),
           'operation' => 0, 'workflow' => 0, 'tc' => 0, 'sv' => 0, 'doc' => 0, 'ops_task' => 0];
foreach ($tasks as $t) {
    $counts[$t['type']] = ($counts[$t['type']] ?? 0) + 1;
    if ($t['type'] === 'workflow') {
        $rk = array_search($t['assigned_role'] ?? '', $pipelineRoles, true);
        if ($rk !== false) $counts[$rk] = ($counts[$rk] ?? 0) + 1;
    }
}

$pageTitle   = 'My Tasks';
$pageSub     = count($filtered) . ' open' . (count($overdueTasks) ? ' · ' . count($overdueTasks) . ' overdue' : '');
$narrowShell = !$isAdminMgr;   // managers get the wider column for the assign controls

require __DIR__ . '/../layout/header.php';

/** Preserve the current filters when switching type. */
function taskUrl(string $type): string
{
    $q = array_filter([
        'type'             => $type === 'all' ? null : $type,
        'scope'            => ($_GET['scope'] ?? '') === 'all' ? 'all' : null,
        'filter_user'      => $_GET['filter_user']      ?? null,
        'filter_date_from' => $_GET['filter_date_from'] ?? null,
        'filter_date_to'   => $_GET['filter_date_to']   ?? null,
    ], fn($v) => $v !== null && $v !== '' && $v !== '0');

    return BASE_URL . '/tasks' . ($q ? '?' . http_build_query($q) : '');
}

/**
 * One task card — deliberately the same markup as the Today queue.
 * $queue lets pipeline tasks chain into the next one after Act.
 */
function v2TaskRow(array $t, string $today, bool $isAdminMgr, array $allUsers, array $queue = []): void
{
    $isOverdue = !empty($t['date']) && $t['date'] < $today;
    $tone      = $isOverdue ? 'overdue' : (($t['date'] ?? null) === $today ? 'today' : 'later');
    $phone     = dialablePhone($t);
    $isWf      = $t['type'] === 'workflow';

    $when = 'No date';
    if (!empty($t['date'])) {
        $days = (int) floor((strtotime('today') - strtotime($t['date'])) / 86400);
        if ($days > 0)       $when = $days . ' day' . ($days > 1 ? 's' : '') . ' late';
        elseif ($days === 0) $when = 'Due today';
        else                 $when = 'Due ' . date('d M', strtotime($t['date']));
    }

    $actUrl = BASE_URL . '/workflow/detail?id=' . (int) $t['raw_id'];
    if ($isWf && $queue) $actUrl .= '&q=' . implode('.', array_map('intval', $queue));
?>
<div class="task-card is-<?= $tone ?>">
    <div class="tc-body">
        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $t['lead_id'] ?>" class="tc-name">
            <?= htmlspecialchars($t['lead_name']) ?>
        </a>
        <div class="tc-title"><?= htmlspecialchars($t['message']) ?></div>

        <div class="tc-meta">
            <span class="pill pill-<?= $t['type'] === 'reminder' ? 'warn' : ($isWf ? 'muted' : 'info') ?>">
                <?= htmlspecialchars($t['label']) ?>
            </span>
            <span class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>"><?= htmlspecialchars($when) ?></span>
            <?php if ($isWf): ?>
                <?php if (!empty($t['assigned_name'])): ?>
                <span><i class="bi bi-person"></i> <?= htmlspecialchars($t['assigned_name']) ?></span>
                <?php else: ?>
                <span class="pill pill-warn"><i class="bi bi-exclamation-triangle"></i> Unassigned</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($isWf && $isAdminMgr && $allUsers): ?>
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <select class="v2-input wf-assign-select" data-task-id="<?= (int) $t['raw_id'] ?>"
                    style="flex:1 1 170px;min-height:40px;padding:8px 10px;font-size:13px">
                <option value="">— <?= empty($t['assigned_to']) ? 'Assign to' : 'Reassign' ?> —</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= (int) ($t['assigned_to'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <input type="date" class="v2-input wf-due-date-input" data-task-id="<?= (int) $t['raw_id'] ?>"
                   value="<?= htmlspecialchars($t['date'] ?? '') ?>"
                   style="flex:0 0 150px;min-height:40px;padding:8px 10px;font-size:13px">
        </div>
        <?php endif; ?>
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

        <?php if ($isWf): ?>
        <a href="<?= htmlspecialchars($actUrl) ?>" class="act-go"><i class="bi bi-check2-square"></i>Act</a>
        <?php elseif ($t['type'] === 'reminder'): ?>
        <button type="button" class="mark-done-btn" data-id="<?= (int) $t['raw_id'] ?>">
            <i class="bi bi-check-lg"></i>Done
        </button>
        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $t['lead_id'] ?>" class="act-go">Open</a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $t['lead_id'] ?>" class="act-go">Open</a>
        <?php endif; ?>
    </div>
</div>
<?php }

// Pipeline tasks chain in the order they're displayed.
$queueIds = array_map(
    fn($t) => (int) $t['raw_id'],
    array_values(array_filter(
        array_merge($overdueTasks, $todayTasks, $futureTasks),
        fn($t) => $t['type'] === 'workflow'
    ))
);
?>

<!-- ── Type filter ──────────────────────────────────────────────────────── -->
<div style="display:flex;gap:7px;overflow-x:auto;padding:14px 0 4px;scrollbar-width:none">
<?php
$chips = [
    'all'       => ['All', $counts['all']],
    'workflow'  => ['Pipeline', $counts['workflow'] ?? 0],
    'reminder'  => ['Reminders', $counts['reminder'] ?? 0],
    'operation' => ['Operations', $counts['operation'] ?? 0],
];
foreach ($chips as $key => [$label, $n]):
    if ($n === 0 && $key !== 'all') continue;
    $on = $typeFilter === $key;
?>
    <a href="<?= htmlspecialchars(taskUrl($key)) ?>"
       style="flex:0 0 auto;padding:7px 14px;border-radius:999px;font-size:13px;font-weight:600;
              <?= $on ? 'background:var(--ink);color:#fff' : 'border:1px solid var(--line-2);color:var(--ink-2);background:var(--surface)' ?>">
        <?= htmlspecialchars($label) ?> <?= $n ?>
    </a>
<?php endforeach; ?>
</div>

<?php if ($isAdminMgr):
    $seeAll = (($_GET['scope'] ?? '') === 'all') || $filterUserId > 0;
?>
<?php // Managers default to their own queue; the whole business is one click away. ?>
<div style="display:flex;gap:7px;margin-top:10px">
    <a href="<?= BASE_URL ?>/tasks?type=<?= htmlspecialchars($typeFilter) ?>"
       style="flex:0 0 auto;padding:6px 13px;border-radius:999px;font-size:12.5px;font-weight:600;
              <?= !$seeAll ? 'background:var(--ink);color:#fff' : 'border:1px solid var(--line-2);color:var(--ink-2);background:var(--surface)' ?>">
        Mine
    </a>
    <a href="<?= BASE_URL ?>/tasks?type=<?= htmlspecialchars($typeFilter) ?>&scope=all"
       style="flex:0 0 auto;padding:6px 13px;border-radius:999px;font-size:12.5px;font-weight:600;
              <?= $seeAll ? 'background:var(--ink);color:#fff' : 'border:1px solid var(--line-2);color:var(--ink-2);background:var(--surface)' ?>">
        Everyone
    </a>
</div>

<details class="v2-card" style="margin-top:10px;padding:12px 14px" <?= $hasActiveFilter ? 'open' : '' ?>>
    <summary style="font-size:13.5px;font-weight:650;cursor:pointer">
        Filters<?= $hasActiveFilter ? ' · active' : '' ?>
    </summary>
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <input type="hidden" name="type" value="<?= htmlspecialchars($typeFilter) ?>">
        <div style="flex:1 1 180px">
            <label class="text-muted-2" style="font-size:12px;display:block;margin-bottom:5px">Assigned to</label>
            <select name="filter_user" class="v2-input" style="min-height:44px;padding:9px 11px;font-size:14px">
                <option value="">Anyone</option>
                <?php foreach ($allUsers as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= $filterUserId === (int) $u['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:0 1 150px">
            <label class="text-muted-2" style="font-size:12px;display:block;margin-bottom:5px">From</label>
            <input type="date" name="filter_date_from" value="<?= htmlspecialchars($filterDateFrom) ?>"
                   class="v2-input" style="min-height:44px;padding:9px 11px;font-size:14px">
        </div>
        <div style="flex:0 1 150px">
            <label class="text-muted-2" style="font-size:12px;display:block;margin-bottom:5px">To</label>
            <input type="date" name="filter_date_to" value="<?= htmlspecialchars($filterDateTo) ?>"
                   class="v2-input" style="min-height:44px;padding:9px 11px;font-size:14px">
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end">
            <button type="submit" class="v2-btn v2-btn-primary v2-btn-sm">Apply</button>
            <?php if ($hasActiveFilter): ?>
            <a href="<?= BASE_URL ?>/tasks?type=<?= htmlspecialchars($typeFilter) ?>"
               class="v2-btn v2-btn-ghost v2-btn-sm">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</details>
<?php endif; ?>

<!-- ── Overdue follow-ups ───────────────────────────────────────────────── -->
<?php if ($overdueFollowups && in_array($typeFilter, ['all', 'followup'], true)): ?>
<a href="<?= BASE_URL ?>/leads?followup=overdue" class="v2-flash v2-flash-danger" style="margin-top:14px">
    <i class="bi bi-calendar-x-fill"></i>
    <div><strong><?= count($overdueFollowups) ?> lead<?= count($overdueFollowups) !== 1 ? 's' : '' ?></strong> past their follow-up date</div>
</a>
<?php endif; ?>

<?php if (!$filtered): ?>
<div class="v2-empty" style="margin-top:20px">
    <i class="bi bi-check2-all"></i>
    <div class="t">Nothing here</div>
    <div class="s"><?= $typeFilter === 'all' ? 'No open tasks assigned to you.' : 'No tasks match this filter.' ?></div>
</div>
<?php endif; ?>

<?php
$sections = [
    ['Overdue',  $overdueTasks, true],
    ['Today',    $todayTasks,   false],
    ['Upcoming', $futureTasks,  false],
];
foreach ($sections as [$label, $list, $isDanger]):
    if (!$list) continue;
?>
<div class="v2-section <?= $isDanger ? 'danger' : '' ?>">
    <h2><?= $label ?></h2>
    <span class="count"><?= count($list) ?></span>
    <span class="rule"></span>
</div>
<?php foreach ($list as $t) v2TaskRow($t, $today, $isAdminMgr, $allUsers, $queueIds); ?>
<?php endforeach; ?>

<!-- ── Recently completed ───────────────────────────────────────────────── -->
<?php if (!empty($completedTasks)): ?>
<details style="margin-top:22px">
    <summary style="font-size:13px;font-weight:650;color:var(--muted);cursor:pointer">
        Recently completed (<?= count($completedTasks) ?>)
    </summary>
    <div style="margin-top:10px">
    <?php foreach ($completedTasks as $c): ?>
        <div class="v2-card" style="padding:11px 14px;margin-bottom:8px;opacity:.85">
            <div style="display:flex;align-items:center;gap:10px">
                <i class="bi bi-check-circle-fill" style="color:var(--ok)"></i>
                <div class="min-w-0" style="flex:1">
                    <div style="font-weight:650;font-size:14px"><?= htmlspecialchars($c['lead_name'] ?? '') ?></div>
                    <div class="text-muted-2" style="font-size:12.5px">
                        <?= htmlspecialchars($c['title'] ?? $c['message'] ?? '') ?>
                        <?= !empty($c['outcome']) ? ' · ' . htmlspecialchars($c['outcome']) : '' ?>
                    </div>
                </div>
                <?php if (!empty($c['lead_id'])): ?>
                <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int) $c['lead_id'] ?>" class="pill pill-muted">Open</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<script>
(function () {
    'use strict';

    // Reassign — fires on change, same endpoint as v1.
    document.querySelectorAll('.wf-assign-select').forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (!sel.value) return;
            sel.disabled = true;
            apiFetch('/workflow/assign', {
                body: JSON.stringify({ task_id: sel.dataset.taskId, user_id: sel.value })
            }).then(function (res) {
                sel.disabled = false;
                if (res && res.ok) showToast('success', 'Reassigned to ' + (res.assigned_name || 'user') + '.');
                else showToast('danger', (res && res.error) || 'Could not reassign.');
            });
        });
    });

    document.querySelectorAll('.wf-due-date-input').forEach(function (inp) {
        inp.addEventListener('change', function () {
            if (!inp.value) return;
            inp.disabled = true;
            apiFetch('/workflow/set-due-date', {
                body: JSON.stringify({ task_id: inp.dataset.taskId, due_date: inp.value })
            }).then(function (res) {
                inp.disabled = false;
                if (res && res.ok) showToast('success', 'Due date updated.');
                else showToast('danger', (res && res.error) || 'Could not update date.');
            });
        });
    });

    document.querySelectorAll('.mark-done-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            apiFetch('/reminders/done', { body: JSON.stringify({ id: btn.dataset.id }) })
                .then(function (res) {
                    if (res && res.ok) {
                        btn.closest('.task-card').style.display = 'none';
                    } else {
                        btn.disabled = false;
                        showToast('danger', (res && res.error) || 'Could not mark done.');
                    }
                });
        });
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
