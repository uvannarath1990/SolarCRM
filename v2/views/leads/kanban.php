<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-kanban me-2"></i>Kanban Board</h5>
    <a href="<?= BASE_URL ?>/leads" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-list-ul me-1"></i>List View
    </a>
</div>

<div class="d-flex gap-2 overflow-auto pb-3" style="min-height:70vh">
<?php foreach ($stages as $stage): ?>
<div class="flex-shrink-0" style="width:220px">
    <!-- Column header -->
    <div class="fw-semibold small px-2 py-1 mb-2 rounded border bg-light d-flex justify-content-between align-items-center">
        <span><?= htmlspecialchars($stage) ?></span>
        <span class="badge bg-secondary"><?= count($cards[$stage]) ?></span>
    </div>
    <!-- Cards -->
    <div class="d-flex flex-column gap-2">
    <?php foreach ($cards[$stage] as $c): ?>
    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $c['id'] ?>"
       class="card shadow-sm text-decoration-none text-dark" style="min-width:0">
        <div class="card-body py-2 px-3">
            <div class="fw-semibold text-truncate small"><?= htmlspecialchars($c['name']) ?></div>
            <?php if ($c['phone']): ?>
            <div class="text-muted" style="font-size:.76rem"><?= htmlspecialchars($c['phone']) ?></div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php if ($c['rep_name']): ?>
                <span class="badge bg-light text-dark border" style="font-size:.7rem">
                    <i class="bi bi-person me-1"></i><?= htmlspecialchars($c['rep_name']) ?>
                </span>
                <?php endif; ?>
                <?php if ($c['potential_kw']): ?>
                <span class="badge bg-info text-dark" style="font-size:.7rem"><?= $c['potential_kw'] ?> kW</span>
                <?php endif; ?>
                <?php if ($c['source_name']): ?>
                <span class="badge bg-light text-muted border" style="font-size:.68rem"><?= htmlspecialchars($c['source_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($cards[$stage])): ?>
    <div class="text-center text-muted small py-3 rounded border border-dashed bg-light">Empty</div>
    <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
