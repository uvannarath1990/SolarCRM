</main><!-- /main -->

</div><!-- /.main-wrapper -->

<?php if (isLoggedIn()): ?>
<!-- Mobile bottom navigation -->
<nav class="mobile-bottom-nav">
    <div class="nav-items">
        <?php
        $r = trim($_GET['route'] ?? '', '/');
        ?>
        <a href="<?= BASE_URL ?>/today" class="nav-item-btn <?= ($r === 'today' || $r === '') ? 'active' : '' ?>">
            <i class="bi bi-check2-circle"></i><span>Today</span>
        </a>
        <a href="<?= BASE_URL ?>/leads" class="nav-item-btn <?= str_starts_with($r, 'leads') ? 'active' : '' ?>">
            <i class="bi bi-people"></i><span>Leads</span>
        </a>
        <?php if (hasRole('admin', 'manager', 'sales_rep')): ?>
        <a href="<?= BASE_URL ?>/dashboard" class="nav-item-btn <?= str_starts_with($r, 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i><span>Stats</span>
        </a>
        <?php endif; ?>
        <?php if (hasRole('admin', 'operation_manager', 'accounts')): ?>
        <a href="<?= BASE_URL ?>/payments" class="nav-item-btn <?= str_starts_with($r, 'payments') ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i><span>Payments</span>
        </a>
        <?php elseif (hasRole('admin', 'installer')): ?>
        <a href="<?= BASE_URL ?>/installations" class="nav-item-btn <?= str_starts_with($r, 'installations') ? 'active' : '' ?>">
            <i class="bi bi-tools"></i><span>Installs</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/logout" class="nav-item-btn">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </div>
</nav>
<?php endif; ?>

<script>window.BASE_URL = '<?= BASE_URL ?>';</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (isLoggedIn()): ?>
<script>
if ('serviceWorker' in navigator && 'PushManager' in window) {
    document.getElementById('pushSubscribeBtn').style.display = '';
}
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= assetUrl('assets/js/app.js') ?>"></script>
<?php if (isLoggedIn()): ?>
<script src="<?= assetUrl('assets/js/push.js') ?>"></script>
<?php endif; ?>
<?php if (!empty($extraJs)): ?>
    <?php foreach ((array)$extraJs as $js): ?>
    <script src="<?= assetUrl('assets/js/' . $js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Sidebar toggle script -->
<script>
(function () {
    const toggle  = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    if (!toggle) return;
    function open()  { document.body.classList.add('sidebar-open'); }
    function close() { document.body.classList.remove('sidebar-open'); }
    toggle.addEventListener('click', function () {
        document.body.classList.contains('sidebar-open') ? close() : open();
    });
    overlay.addEventListener('click', close);
})();
</script>
</body>
</html>
