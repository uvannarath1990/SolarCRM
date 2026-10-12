<?php
$currentRoute = trim($_GET['route'] ?? '', '/');
$__isRep      = ($_SESSION['role'] ?? '') === 'sales_rep';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#ffffff">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <meta name="base-url" content="<?= BASE_URL ?>">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php // legacy.css first: page styles v1 views still rely on. v2.css last so it wins. ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/css/legacy.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/v2.css') ?>">
</head>
<body class="v2-app">

<?php if (isLoggedIn()):
    $__user     = currentUser();
    $__initials = strtoupper(substr($__user['name'] ?? 'U', 0, 1));
?>

<!-- ── Desktop sidebar (hidden on mobile — the tab bar handles navigation) ─── -->
<aside class="v2-sidebar">
    <div class="sb-brand">
        <i class="bi bi-<?= !empty($portalShell) ? 'folder2-open' : 'sun-fill' ?>"
           style="color:<?= !empty($portalShell) ? 'var(--info)' : 'var(--brand)' ?>"></i>
        <span><?= htmlspecialchars(getSetting('company_name', 'KRE Solar')) ?></span>
    </div>
    <?php if (!empty($portalShell)): ?>
    <div style="padding:0 20px 12px;margin-top:-8px">
        <span class="pill pill-info">Documentation</span>
    </div>
    <?php endif; ?>

    <div class="sb-user">
        <div class="av"><?= $__initials ?></div>
        <div class="min-w-0">
            <div class="nm"><?= htmlspecialchars($__user['name'] ?? '') ?></div>
            <div class="rl"><?= htmlspecialchars($_SESSION['role_label'] ?? ucwords(str_replace('_', ' ', $__user['role'] ?? ''))) ?></div>
        </div>
    </div>

    <div class="sb-logout">
        <form method="POST" action="<?= BASE_URL ?>/logout" style="margin:0">
            <?= csrfField() ?>
            <button type="submit">
                <i class="bi bi-box-arrow-right"></i>Logout
            </button>
        </form>
    </div>

    <nav>
        <?php if (!empty($portalShell)): ?>
        <a href="<?= BASE_URL ?>/doc-portal" class="<?= $currentRoute === 'doc-portal' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>Home
        </a>
        <a href="<?= BASE_URL ?>/doc-portal/queue" class="<?= $currentRoute === 'doc-portal/queue' ? 'active' : '' ?>">
            <i class="bi bi-folder2-open"></i>All documents
        </a>
        <?php // Pending / Received were separate v1 pages. They are views of the
              // same queue now, so the portal never drops into the old UI. ?>
        <a href="<?= BASE_URL ?>/doc-portal/queue?view=pending"><i class="bi bi-folder-x"></i>Docs pending</a>
        <a href="<?= BASE_URL ?>/doc-portal/queue?view=received"><i class="bi bi-folder-check"></i>Docs received</a>
        <?php // Only offered to staff who actually work in the CRM. Documentation
              // users and partners stay in the portal. ?>
        <?php if (hasRole('admin', 'manager')): ?>
        <div class="sb-label">Also</div>
        <a href="<?= BASE_URL ?>/today"><i class="bi bi-arrow-left-right"></i>Back to CRM</a>
        <?php endif; ?>

        <?php elseif (hasRole('partner')): ?>
        <a href="<?= BASE_URL ?>/partner/dashboard" class="<?= str_starts_with($currentRoute, 'partner') ? 'active' : '' ?>"><i class="bi bi-bar-chart-line"></i>Dashboard</a>
        <a href="<?= BASE_URL ?>/leads" class="<?= str_starts_with($currentRoute, 'leads') ? 'active' : '' ?>"><i class="bi bi-people"></i>My Leads</a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/today" class="<?= ($currentRoute === 'today' || $currentRoute === '') ? 'active' : '' ?>"><i class="bi bi-sun"></i>Today</a>
        <a href="<?= BASE_URL ?>/tasks" class="<?= str_starts_with($currentRoute, 'tasks') && $currentRoute !== 'tasks/review' ? 'active' : '' ?>"><i class="bi bi-check2-square"></i>My Tasks</a>
        <a href="<?= BASE_URL ?>/leads" class="<?= (str_starts_with($currentRoute, 'leads')) ? 'active' : '' ?>"><i class="bi bi-people"></i>Leads</a>

        <?php if (hasRole('admin', 'manager')): ?>
        <div class="sb-label">Review</div>
        <a href="<?= BASE_URL ?>/tasks/review" class="<?= $currentRoute === 'tasks/review' ? 'active' : '' ?>"><i class="bi bi-list-check"></i>Task Review</a>
        <a href="<?= BASE_URL ?>/reports/lead-review" class="<?= $currentRoute === 'reports/lead-review' ? 'active' : '' ?>"><i class="bi bi-graph-up-arrow"></i>Lead Review</a>
        <a href="<?= BASE_URL ?>/reports/tele-review" class="<?= $currentRoute === 'reports/tele-review' ? 'active' : '' ?>"><i class="bi bi-headset"></i>Tele Caller Review</a>
        <a href="<?= BASE_URL ?>/reports/team-map" class="<?= $currentRoute === 'reports/team-map' ? 'active' : '' ?>"><i class="bi bi-geo-alt"></i>Team Location</a>
        <?php endif; ?>

        <?php if (hasRole('admin', 'manager', 'operation_manager')): ?>
        <a href="<?= BASE_URL ?>/dashboard" class="<?= str_starts_with($currentRoute, 'dashboard') ? 'active' : '' ?>"><i class="bi bi-bar-chart-line"></i>Dashboard</a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'operation_manager', 'accounts')): ?>
        <a href="<?= BASE_URL ?>/payments" class="<?= str_starts_with($currentRoute, 'payments') ? 'active' : '' ?>"><i class="bi bi-cash-stack"></i>Payments</a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'installer', 'operation_manager')): ?>
        <a href="<?= BASE_URL ?>/installations" class="<?= str_starts_with($currentRoute, 'installations') ? 'active' : '' ?>"><i class="bi bi-tools"></i>Installations</a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'operation_manager', 'accounts')): ?>
        <a href="<?= BASE_URL ?>/operations" class="<?= str_starts_with($currentRoute, 'operations') ? 'active' : '' ?>"><i class="bi bi-wrench-adjustable"></i>Operations</a>
        <?php endif; ?>
        <?php if (canSeeDocTabs()): ?>
        <div class="sb-label">Documents</div>
        <a href="<?= BASE_URL ?>/documentation/pending" class="<?= $currentRoute === 'documentation/pending' ? 'active' : '' ?>"><i class="bi bi-folder-x"></i>Pending Docs</a>
        <a href="<?= BASE_URL ?>/documentation/received" class="<?= $currentRoute === 'documentation/received' ? 'active' : '' ?>"><i class="bi bi-folder-check"></i>Docs Received</a>
        <?php endif; ?>

        <?php if (hasRole('admin')): ?>
        <div class="sb-label">Admin</div>
        <a href="<?= BASE_URL ?>/teams" class="<?= str_starts_with($currentRoute, 'teams') ? 'active' : '' ?>"><i class="bi bi-people-fill"></i>Teams</a>
        <a href="<?= BASE_URL ?>/users" class="<?= str_starts_with($currentRoute, 'users') && $currentRoute !== 'users/tracking' ? 'active' : '' ?>"><i class="bi bi-person-gear"></i>Users</a>
        <a href="<?= BASE_URL ?>/users/tracking" class="<?= $currentRoute === 'users/tracking' ? 'active' : '' ?>"><i class="bi bi-geo"></i>Location Tracking</a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'manager')): ?>
        <a href="<?= BASE_URL ?>/settings" class="<?= str_starts_with($currentRoute, 'settings') ? 'active' : '' ?>"><i class="bi bi-gear"></i>Settings</a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>

</aside>

<!-- ── Top bar ──────────────────────────────────────────────────────────── -->
<header class="v2-topbar">
    <?php if (!empty($backLink)): ?>
    <a href="<?= htmlspecialchars($backLink) ?>" class="tb-btn"><i class="bi bi-arrow-left"></i></a>
    <?php endif; ?>

    <div class="min-w-0" style="flex:1">
        <div class="tb-title"><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></div>
        <?php if (!empty($pageSub)): ?>
        <div class="tb-sub"><?= htmlspecialchars($pageSub) ?></div>
        <?php endif; ?>
    </div>

    <?php if (empty($portalShell)): ?>
    <a href="<?= BASE_URL ?>/tasks" class="tb-btn" aria-label="My tasks">
        <i class="bi bi-bell"></i>
        <span class="tb-dot" id="topbarNotifBadge" style="display:none"></span>
    </a>
    <?php endif; ?>
    <span class="tb-avatar"><?= $__initials ?></span>
</header>

<?php endif; ?>

<div class="v2-shell<?= !empty($narrowShell) ? ' v2-shell-narrow' : '' ?>">

<?php foreach (getFlashes() as $f):
    $__icon = ['success' => 'check-circle-fill', 'danger' => 'exclamation-octagon-fill',
               'warning' => 'exclamation-triangle-fill', 'info' => 'info-circle-fill'][$f['type']] ?? 'info-circle-fill';
?>
    <div class="v2-flash v2-flash-<?= htmlspecialchars($f['type']) ?>" style="margin-top:12px">
        <i class="bi bi-<?= $__icon ?>"></i>
        <div><?= ($f['is_html'] ?? false) ? $f['message'] : htmlspecialchars($f['message']) ?></div>
    </div>
<?php endforeach; ?>

<main>
