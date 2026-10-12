<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex align-items-center gap-2 mt-3 mb-3">
    <a href="<?= BASE_URL ?>/teams" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><?= $pageTitle ?></h5>
</div>

<div class="row">
<div class="col-md-6">
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/teams/save">
            <?= csrfField() ?>
            <?php if ($team): ?>
            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Team Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm" name="name"
                       value="<?= htmlspecialchars($team['name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Team Leader <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="leader_id" required>
                    <option value="">— select —</option>
                    <?php foreach ($allUsers as $u): ?>
                    <option value="<?= $u['id'] ?>"
                            <?= isset($team) && (int)$team['leader_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                        <span class="text-muted">(<?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?>)</span>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold small">Team Members</label>
                <div class="border rounded p-2" style="max-height:240px;overflow-y:auto">
                    <?php if (empty($installers)): ?>
                    <span class="text-muted small">No active users found.</span>
                    <?php endif; ?>
                    <?php foreach ($installers as $inst): ?>
                    <div class="form-check d-flex align-items-center gap-2">
                        <input class="form-check-input flex-shrink-0" type="checkbox" name="member_ids[]"
                               value="<?= $inst['id'] ?>"
                               id="member_<?= $inst['id'] ?>"
                               <?= in_array((int)$inst['id'], $members, true) ? 'checked' : '' ?>>
                        <label class="form-check-label small flex-grow-1" for="member_<?= $inst['id'] ?>">
                            <?= htmlspecialchars($inst['name']) ?>
                        </label>
                        <span class="badge bg-light text-muted border" style="font-size:.68rem">
                            <?= htmlspecialchars(str_replace('_', ' ', $inst['role'])) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($team): ?>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                           id="isActive" <?= $team['is_active'] ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="isActive">Active</label>
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary btn-sm">Save Team</button>
            <a href="<?= BASE_URL ?>/teams" class="btn btn-outline-secondary btn-sm ms-2">Cancel</a>
        </form>
    </div>
</div>
</div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
