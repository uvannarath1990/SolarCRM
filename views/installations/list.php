<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-tools me-2"></i>Installations</h5>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>kW</th>
                    <th>Status</th>
                    <th>Installer</th>
                    <th>Scheduled</th>
                    <th>Completed</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($installs as $inst): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($inst['name']) ?></strong><br>
                    <span class="text-muted"><?= htmlspecialchars($inst['location'] ?? '—') ?></span>
                </td>
                <td><?= $inst['potential_kw'] ? htmlspecialchars($inst['potential_kw']) . ' kW' : '—' ?></td>
                <td>
                    <span class="badge bg-<?= match($inst['status']) {
                        'completed' => 'success',
                        'in_progress' => 'primary',
                        'scheduled' => 'info',
                        'cancelled' => 'danger',
                        default => 'secondary'
                    } ?> text-capitalize">
                        <?= htmlspecialchars(str_replace('_', ' ', $inst['status'])) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($inst['installer_name'] ?? '—') ?></td>
                <td class="text-muted"><?= formatDate($inst['scheduled_date']) ?></td>
                <td class="text-muted"><?= formatDate($inst['completed_date']) ?></td>
                <td>
                    <a href="<?= BASE_URL ?>/installations/detail?lead_id=<?= $inst['lead_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">Update</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($installs)): ?>
            <tr><td colspan="7" class="text-center text-muted py-5">No installations yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
