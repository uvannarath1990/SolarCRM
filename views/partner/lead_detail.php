<?php require __DIR__ . '/../layout/header.php'; ?>

<?php
$stageColors = [
    'New'                    => 'secondary',
    'Contacted'              => 'info',
    'Visit Scheduled'        => 'primary',
    'Site Visit in Progress' => 'warning',
    'Site Visit Completed'   => 'primary',
    'Quoted'                 => 'info',
    'Won'                    => 'success',
    'Lost'                   => 'danger',
    'On Hold'                => 'warning',
];
$color = $stageColors[$lead['stage']] ?? 'secondary';

$allStages = ['New','Contacted','Visit Scheduled','Site Visit in Progress',
              'Site Visit Completed','Quoted','Won'];
$currentIdx = array_search($lead['stage'], $allStages, true);
?>

<div class="mt-3 mb-3">
    <a href="<?= BASE_URL ?>/partner/dashboard" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</div>

<div style="max-width:600px">

    <!-- Header card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($lead['name']) ?></h5>
                    <span class="badge bg-<?= $color ?>-subtle text-<?= $color ?> border border-<?= $color ?>-subtle fs-6 fw-semibold">
                        <?= htmlspecialchars($lead['stage']) ?>
                    </span>
                </div>
                <div class="text-end text-muted small">
                    <div>Last updated</div>
                    <div class="fw-semibold"><?= date('d M Y', strtotime($lead['updated_at'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details card -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold border-0 pb-0">
            <i class="bi bi-person-lines-fill me-1"></i> Contact Details
        </div>
        <div class="card-body pt-2">
            <dl class="row mb-0 small">
                <dt class="col-5 text-muted fw-normal">Phone</dt>
                <dd class="col-7 fw-semibold mb-2">
                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $lead['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($lead['phone'] ?? '—') ?>
                    </a>
                </dd>

                <?php if (!empty($lead['location'])): ?>
                <dt class="col-5 text-muted fw-normal">Location</dt>
                <dd class="col-7 mb-2"><?= htmlspecialchars($lead['location']) ?></dd>
                <?php endif; ?>

                <dt class="col-5 text-muted fw-normal">Lead Created</dt>
                <dd class="col-7 mb-0"><?= date('d M Y', strtotime($lead['created_at'])) ?></dd>
            </dl>
        </div>
    </div>

    <!-- Pipeline progress -->
    <?php if (!in_array($lead['stage'], ['Lost', 'On Hold'], true)): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-transparent fw-semibold border-0 pb-0">
            <i class="bi bi-arrow-right-circle me-1"></i> Pipeline Progress
        </div>
        <div class="card-body pt-3">
            <div class="d-flex align-items-center gap-0">
            <?php foreach ($allStages as $i => $s):
                $done    = $currentIdx !== false && $i <= $currentIdx;
                $current = $s === $lead['stage'];
                $sc      = $stageColors[$s] ?? 'secondary';
            ?>
                <div class="d-flex flex-column align-items-center" style="flex:1;min-width:0">
                    <div class="rounded-circle d-flex align-items-center justify-content-center mb-1 fw-bold"
                         style="width:28px;height:28px;font-size:.7rem;flex-shrink:0;
                                background:<?= $done ? ($current ? 'var(--bs-'.$sc.')' : '#198754') : '#e9ecef' ?>;
                                color:<?= $done ? '#fff' : '#adb5bd' ?>;
                                border:2px solid <?= $current ? 'var(--bs-'.$sc.')' : ($done ? '#198754' : '#dee2e6') ?>">
                        <?= $done ? ($current ? ($i+1) : '✓') : ($i+1) ?>
                    </div>
                    <div class="text-center text-muted" style="font-size:.62rem;line-height:1.2;word-break:break-word">
                        <?= htmlspecialchars($s) ?>
                    </div>
                </div>
                <?php if ($i < count($allStages) - 1): ?>
                <div style="height:2px;flex:0 0 6px;background:<?= ($currentIdx !== false && $i < $currentIdx) ? '#198754' : '#dee2e6' ?>"></div>
                <?php endif; ?>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($lead['stage'] === 'Lost'): ?>
    <div class="alert alert-danger">
        <i class="bi bi-x-circle me-1"></i>
        This lead was marked as <strong>Lost</strong><?= !empty($lead['lost_reason']) ? ' — ' . htmlspecialchars($lead['lost_reason']) : '' ?>.
    </div>
    <?php elseif ($lead['stage'] === 'Won'): ?>
    <div class="alert alert-success">
        <i class="bi bi-trophy me-1"></i>
        <strong>Won!</strong> This lead has been successfully converted.
    </div>
    <?php endif; ?>

</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
