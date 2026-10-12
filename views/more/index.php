<?php
$narrowShell = true;
require __DIR__ . '/../layout/header.php';

$__u = currentUser();

/** Each row is [label, icon, href, visible?] — filtered once, rendered once. */
$groups = [
    'Work' => [
        ['My Tasks',      'check2-square',      '/tasks',                  true],
        ['Leads',         'people',             '/leads',                  true],
        ['Operations',    'wrench-adjustable',  '/operations',             hasRole('admin', 'operation_manager', 'accounts')],
        ['Installations', 'tools',              '/installations',          hasRole('admin', 'installer', 'operation_manager')],
        ['Payments',      'cash-stack',         '/payments',               hasRole('admin', 'operation_manager', 'accounts')],
    ],
    'Documents' => [
        ['Pending Docs',  'folder-x',           '/documentation/pending',  canSeeDocTabs()],
        ['Docs Received', 'folder-check',       '/documentation/received', canSeeDocTabs()],
    ],
    'Review' => [
        ['Dashboard',        'bar-chart-line',  '/dashboard',              hasRole('admin', 'manager', 'operation_manager')],
        ['Task Review',      'list-check',      '/tasks/review',           hasRole('admin', 'manager')],
        ['Lead Review',      'graph-up-arrow',  '/reports/lead-review',    hasRole('admin', 'manager')],
        ['Tele Caller Review','headset',        '/reports/tele-review',    hasRole('admin', 'manager')],
        ['Team Location',    'geo-alt',         '/reports/team-map',       hasRole('admin', 'manager')],
    ],
    'Admin' => [
        ['Teams',    'people-fill', '/teams',    hasRole('admin')],
        ['Users',    'person-gear', '/users',    hasRole('admin')],
        ['Location Tracking', 'geo', '/users/tracking', hasRole('admin')],
        ['Settings', 'gear',        '/settings', hasRole('admin', 'manager')],
    ],
];
?>

<div class="v2-card" style="margin-top:14px;padding:16px;display:flex;align-items:center;gap:12px">
    <div class="tb-avatar" style="width:46px;height:46px;font-size:17px">
        <?= strtoupper(substr($__u['name'] ?? 'U', 0, 1)) ?>
    </div>
    <div class="min-w-0">
        <div style="font-weight:700;font-size:16px"><?= htmlspecialchars($__u['name'] ?? '') ?></div>
        <div class="text-muted-2" style="font-size:13px">
            <?= htmlspecialchars($_SESSION['role_label'] ?? ucwords(str_replace('_', ' ', $__u['role'] ?? ''))) ?>
        </div>
    </div>
</div>

<?php foreach ($groups as $title => $rows):
    $rows = array_values(array_filter($rows, fn($r) => $r[3]));
    if (!$rows) continue;
?>
<div class="v2-section"><h2><?= htmlspecialchars($title) ?></h2><span class="rule"></span></div>

<div class="v2-card" style="overflow:hidden">
<?php foreach ($rows as $i => [$label, $icon, $href, $_]): ?>
    <a href="<?= BASE_URL . $href ?>"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;color:var(--ink);min-height:52px;<?= $i ? 'border-top:1px solid var(--line)' : '' ?>">
        <i class="bi bi-<?= $icon ?>" style="font-size:17px;color:var(--muted);width:20px"></i>
        <span style="flex:1;font-weight:550;font-size:15px"><?= htmlspecialchars($label) ?></span>
        <i class="bi bi-chevron-right" style="color:var(--muted-2);font-size:13px"></i>
    </a>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<form method="POST" action="<?= BASE_URL ?>/logout" style="margin-top:20px">
    <?= csrfField() ?>
    <button type="submit" class="v2-btn v2-btn-ghost" style="color:var(--danger);border-color:#FECACA">
        <i class="bi bi-box-arrow-right"></i>Log out
    </button>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
