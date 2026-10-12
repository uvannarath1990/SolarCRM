<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3 d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/installations" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold">Installation — <?= htmlspecialchars($install['name']) ?></h5>
</div>

<div class="row g-3">
<div class="col-md-5">
    <div class="card mb-3">
        <div class="card-body small">
            <div class="row g-2">
                <div class="col-6"><span class="text-muted">Customer</span><br><strong><?= htmlspecialchars($install['name']) ?></strong></div>
                <div class="col-6"><span class="text-muted">Location</span><br><?= htmlspecialchars($install['location'] ?? '—') ?></div>
                <div class="col-6"><span class="text-muted">Phone</span><br><?= htmlspecialchars($install['phone'] ?? '—') ?></div>
                <div class="col-6"><span class="text-muted">Est. kW</span><br><?= htmlspecialchars($install['potential_kw'] ?? '—') ?></div>
            </div>
        </div>
    </div>
</div>

<div class="col-md-7">
    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/installations/update" novalidate>
                <?= csrfField() ?>
                <input type="hidden" name="install_id" value="<?= $install['id'] ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Status</label>
                        <select class="form-select form-select-sm" name="status" required>
                            <?php foreach (['pending','scheduled','in_progress','completed','cancelled'] as $st): ?>
                            <option value="<?= $st ?>" <?= $install['status'] === $st ? 'selected' : '' ?>>
                                <?= ucwords(str_replace('_',' ',$st)) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (hasRole('admin', 'operation_manager')): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Assign Installer</label>
                        <select class="form-select form-select-sm" name="installer_id">
                            <option value="">— Unassigned —</option>
                            <?php foreach ($installers as $ins): ?>
                            <option value="<?= $ins['id'] ?>" <?= (int)($install['installer_id'] ?? 0) === (int)$ins['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ins['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="installer_id" value="<?= $install['installer_id'] ?? '' ?>">
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Scheduled Date</label>
                        <input type="date" class="form-control form-control-sm" name="scheduled_date"
                               value="<?= htmlspecialchars($install['scheduled_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Completed Date</label>
                        <input type="date" class="form-control form-control-sm" name="completed_date"
                               value="<?= htmlspecialchars($install['completed_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">System kW</label>
                        <input type="number" class="form-control form-control-sm" name="system_kw"
                               min="0" step="0.1" value="<?= htmlspecialchars($install['system_kw'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Panel Count</label>
                        <input type="number" class="form-control form-control-sm" name="panel_count"
                               min="0" value="<?= htmlspecialchars($install['panel_count'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Inverter Model</label>
                        <input type="text" class="form-control form-control-sm" name="inverter_model"
                               value="<?= htmlspecialchars($install['inverter_model'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold small">Notes</label>
                        <textarea class="form-control form-control-sm" name="notes" rows="3"
                                  ><?= htmlspecialchars($install['notes'] ?? '') ?></textarea>
                        <textarea id="instNotesTA" class="d-none"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-sm mt-3">Save</button>
            </form>
        </div>
    </div>
</div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
