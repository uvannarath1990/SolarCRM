<?php
$currentRoute = trim($_GET['route'] ?? '', '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken()) ?>">
    <meta name="base-url" content="<?= BASE_URL ?>"><?php // used by apiFetch() in app.js ?>
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/app.css') ?>">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=AW-18080650622"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'AW-18080650622');
    </script>
</head>
<body>

<?php if (isLoggedIn()):
    $__user     = currentUser();
    $__initials = strtoupper(substr($__user['name'] ?? 'U', 0, 1));
?>

<!-- Sidebar overlay (mobile tap-to-close) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">

    <div class="sidebar-brand">
        <i class="bi bi-sun-fill text-warning fs-5"></i>
        <span class="fw-bold text-white ms-2"><?= htmlspecialchars(getSetting('company_name', 'KRE Solar')) ?></span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= $__initials ?></div>
        <div class="user-info ms-2" style="min-width:0">
            <div class="fw-semibold text-white text-truncate small"><?= htmlspecialchars($__user['name'] ?? '') ?></div>
            <span class="role-chip"><?= htmlspecialchars($_SESSION['role_label'] ?? ucwords(str_replace('_', ' ', $__user['role'] ?? ''))) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if (hasRole('partner')): ?>
        <a href="<?= BASE_URL ?>/partner/dashboard" class="nav-link <?= str_starts_with($currentRoute, 'partner') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i><span>Dashboard</span>
        </a>
        <a href="<?= BASE_URL ?>/leads" class="nav-link <?= str_starts_with($currentRoute, 'leads') ? 'active' : '' ?>">
            <i class="bi bi-people"></i><span>My Leads</span>
        </a>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/today" class="nav-link <?= ($currentRoute === 'today' || $currentRoute === '') ? 'active' : '' ?>">
            <i class="bi bi-check2-circle"></i><span>Today</span>
        </a>
        <a href="<?= BASE_URL ?>/tasks" class="nav-link <?= str_starts_with($currentRoute, 'tasks') ? 'active' : '' ?>">
            <i class="bi bi-list-task"></i>
            <span>My Tasks</span>
            <span class="notif-badge ms-auto" id="sidebarNotifBadge" style="display:none"></span>
        </a>
        <a href="<?= BASE_URL ?>/leads" class="nav-link <?= (str_starts_with($currentRoute, 'leads') && $currentRoute !== 'leads/kanban') ? 'active' : '' ?>">
            <i class="bi bi-people"></i><span>Leads</span>
        </a>
        <?php if (hasRole('admin', 'manager')): ?>
        <?php // Kanban hidden for now ?>
        <a href="<?= BASE_URL ?>/tasks/review" class="nav-link <?= $currentRoute === 'tasks/review' ? 'active' : '' ?>">
            <i class="bi bi-list-check"></i><span>Task Review</span>
        </a>
        <a href="<?= BASE_URL ?>/reports/lead-review" class="nav-link <?= $currentRoute === 'reports/lead-review' ? 'active' : '' ?>">
            <i class="bi bi-graph-up-arrow"></i><span>Lead Review</span>
        </a>
        <a href="<?= BASE_URL ?>/reports/tele-review" class="nav-link <?= $currentRoute === 'reports/tele-review' ? 'active' : '' ?>">
            <i class="bi bi-headset"></i><span>Tele Caller Review</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'manager', 'operation_manager')): ?>
        <a href="<?= BASE_URL ?>/dashboard" class="nav-link <?= str_starts_with($currentRoute, 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i><span>Dashboard</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'operation_manager', 'accounts')): ?>
        <a href="<?= BASE_URL ?>/payments" class="nav-link <?= str_starts_with($currentRoute, 'payments') ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i><span>Payments</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'installer', 'operation_manager')): ?>
        <a href="<?= BASE_URL ?>/installations" class="nav-link <?= str_starts_with($currentRoute, 'installations') ? 'active' : '' ?>">
            <i class="bi bi-tools"></i><span>Installations</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'operation_manager', 'accounts')): ?>
        <a href="<?= BASE_URL ?>/operations" class="nav-link <?= str_starts_with($currentRoute, 'operations') ? 'active' : '' ?>">
            <i class="bi bi-wrench-adjustable"></i><span>Operations</span>
        </a>
        <?php endif; ?>
        <?php // Documentation page hidden for now — superseded by Pending Docs / Docs Received
        /*
        <a href="<?= BASE_URL ?>/documentation" class="nav-link">
            <i class="bi bi-folder-check"></i><span>Documentation</span>
        </a>
        */ ?>
        <?php if (canSeeDocTabs()): ?>
        <a href="<?= BASE_URL ?>/documentation/pending" class="nav-link <?= $currentRoute === 'documentation/pending' ? 'active' : '' ?>">
            <i class="bi bi-folder-x"></i><span>Pending Docs</span>
        </a>
        <a href="<?= BASE_URL ?>/documentation/received" class="nav-link <?= $currentRoute === 'documentation/received' ? 'active' : '' ?>">
            <i class="bi bi-folder-check"></i><span>Docs Received</span>
        </a>
        <?php endif; ?>

        <?php if (hasRole('admin')): ?>
        <div class="sidebar-section-label">Admin</div>
        <a href="<?= BASE_URL ?>/teams" class="nav-link <?= str_starts_with($currentRoute, 'teams') ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i><span>Teams</span>
        </a>
        <a href="<?= BASE_URL ?>/users" class="nav-link <?= str_starts_with($currentRoute, 'users') ? 'active' : '' ?>">
            <i class="bi bi-person-gear"></i><span>Users</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'manager')): ?>
        <a href="<?= BASE_URL ?>/settings" class="nav-link <?= str_starts_with($currentRoute, 'settings') ? 'active' : '' ?>">
            <i class="bi bi-gear"></i><span>Settings</span>
        </a>
        <?php endif; ?>
        <?php endif; // end else (non-partner) ?>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="<?= BASE_URL ?>/logout" style="margin:0">
            <?= csrfField() ?>
            <button type="submit" class="nav-link btn btn-link p-0" style="color:#f87171;text-decoration:none;width:100%;text-align:left">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </button>
        </form>
    </div>
</aside>

<!-- ── Main wrapper ─────────────────────────────────────────────────────── -->
<div class="main-wrapper">

    <!-- Mobile topbar -->
    <header class="topbar">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Menu">
            <i class="bi bi-list fs-5"></i>
        </button>
        <span class="fw-bold ms-2">
            <i class="bi bi-sun-fill text-warning me-1"></i>KRE Solar
        </span>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <button id="pushSubscribeBtn" class="btn btn-sm btn-light" title="Enable push notifications" style="display:none">
                <i class="bi bi-bell-slash fs-6"></i>
            </button>
            <a href="<?= BASE_URL ?>/tasks" class="position-relative btn btn-sm btn-light" title="My Tasks">
                <i class="bi bi-bell fs-6"></i>
                <span class="notif-badge position-absolute top-0 start-100 translate-middle" id="topbarNotifBadge" style="display:none"></span>
            </a>
            <?php if (canCreateLead()): ?>
            <a href="<?= BASE_URL ?>/leads/create" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i>
            </a>
            <?php endif; ?>
        </div>
    </header>

    <script>
    (function() {
        function loadNotifCount() {
            fetch('<?= BASE_URL ?>/api/notifications', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.ok ? r.json() : null; })
            .then(function(data) {
                if (!data || data.count === 0) return;
                var ct = data.count > 99 ? '99+' : String(data.count);
                ['sidebarNotifBadge','topbarNotifBadge'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = ct; el.style.display = ''; }
                });
            })
            .catch(function() {});
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadNotifCount);
        } else {
            loadNotifCount();
        }
    })();
    </script>

<?php endif; ?>

<!-- Flash messages -->
<div class="px-3 pt-2" id="flashContainer">
<?php foreach (getFlashes() as $f): ?>
    <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show py-2 mb-2" role="alert">
        <?= ($f['is_html'] ?? false) ? $f['message'] : htmlspecialchars($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
</div>

<main class="container-fluid px-3 pb-5">
