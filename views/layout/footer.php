</main>
</div><!-- /.v2-shell -->

<?php if (isLoggedIn()):
    $r = trim($_GET['route'] ?? '', '/');
    $__canAdd = canCreateLead();
?>
<!-- ── Mobile tab bar ───────────────────────────────────────────────────── -->
<nav class="v2-tabbar">
    <?php if (!empty($portalShell)): ?>
    <?php // Documentation only — no tasks, no leads, no CRM navigation. ?>
    <a href="<?= BASE_URL ?>/doc-portal" class="<?= $r === 'doc-portal' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i><span>Home</span>
    </a>
    <a href="<?= BASE_URL ?>/doc-portal/queue" class="<?= $r === 'doc-portal/queue' || $r === 'doc-portal/lead' ? 'active' : '' ?>">
        <i class="bi bi-folder2-open"></i><span>Documents</span>
    </a>
    <a href="<?= BASE_URL ?>/doc-portal/queue?view=overdue">
        <i class="bi bi-exclamation-triangle"></i><span>Late</span>
    </a>

    <?php elseif (hasRole('partner')): ?>
    <a href="<?= BASE_URL ?>/partner/dashboard" class="<?= str_starts_with($r, 'partner') ? 'active' : '' ?>"><i class="bi bi-bar-chart-line"></i><span>Dashboard</span></a>
    <a href="<?= BASE_URL ?>/leads" class="<?= str_starts_with($r, 'leads') ? 'active' : '' ?>"><i class="bi bi-people"></i><span>Leads</span></a>
    <?php else: ?>

    <a href="<?= BASE_URL ?>/today" class="<?= ($r === 'today' || $r === '') ? 'active' : '' ?>">
        <i class="bi bi-sun"></i><span>Today</span>
    </a>
    <a href="<?= BASE_URL ?>/leads" class="<?= str_starts_with($r, 'leads') ? 'active' : '' ?>">
        <i class="bi bi-people"></i><span>Leads</span>
    </a>

    <?php if ($__canAdd): ?>
    <a href="<?= BASE_URL ?>/leads/create" class="tab-fab" aria-label="New lead">
        <span class="fab"><i class="bi bi-plus-lg"></i></span>
    </a>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/tasks" class="<?= (str_starts_with($r, 'tasks') || str_starts_with($r, 'workflow')) ? 'active' : '' ?>">
        <i class="bi bi-check2-square"></i><span>Tasks</span>
        <span class="tab-badge" id="tabNotifBadge" style="display:none"></span>
    </a>
    <a href="<?= BASE_URL ?>/more" class="<?= $r === 'more' ? 'active' : '' ?>">
        <i class="bi bi-three-dots"></i><span>More</span>
    </a>
    <?php endif; ?>
</nav>
<?php endif; ?>

<script>window.BASE_URL = '<?= BASE_URL ?>';</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= assetUrl('assets/js/app.js') ?>"></script>

<?php if (isLoggedIn()): ?>
<script src="<?= assetUrl('assets/js/push.js') ?>"></script>
<?php
// The ping loop only ships to users who are actually tracked, so no one else
// pays for the script or sees a location prompt.
if (userTracksLocation()):
?>
<script>
window.KRE_TRACK = {
    every: <?= max(5, (int) getSetting('location_ping_minutes', '60')) ?>
};
</script>
<script src="<?= assetUrl('assets/js/location.js') ?>"></script>
<?php endif; ?>

<?php if (empty($portalShell)): ?>
<script>
(function () {
    fetch(BASE_URL + '/api/notifications', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
            if (!d || !d.count) return;
            var t = d.count > 99 ? '99+' : String(d.count);
            ['topbarNotifBadge', 'tabNotifBadge'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.textContent = t; el.style.display = ''; }
            });
        })
        .catch(function () {});
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php if (!empty($extraJs)): ?>
    <?php foreach ((array) $extraJs as $js): ?>
    <script src="<?= assetUrl('assets/js/' . $js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
