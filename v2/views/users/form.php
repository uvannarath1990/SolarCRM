<?php
// Load existing user if editing
$editUser = $user ?? [];
$editId   = getInt('id');
if ($editId > 0 && empty($editUser)) {
    $stmt = getPdo()->prepare('SELECT * FROM `users` WHERE `id`=? LIMIT 1');
    $stmt->execute([$editId]);
    $editUser  = $stmt->fetch() ?: [];
    $pageTitle = 'Edit User';
}
$allUsers = $allUsers ?? [];
require __DIR__ . '/../layout/header.php';
?>

<div class="mt-3 mb-3">
    <a href="<?= BASE_URL ?>/users" class="btn btn-sm btn-outline-secondary me-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <span class="fw-bold fs-5"><?= $editId ? 'Edit User' : 'Add User' ?></span>
</div>

<div class="card" style="max-width:560px">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/users/save" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $editId ?>">

            <div class="mb-3">
                <label class="form-label fw-semibold" for="name">Full Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= htmlspecialchars($editUser['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="email">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= htmlspecialchars($editUser['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="phone">Phone</label>
                <input type="tel" class="form-control" id="phone" name="phone"
                       value="<?= htmlspecialchars($editUser['phone'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="role">Role <span class="text-danger">*</span></label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">— Select role —</option>
                    <?php
                    // Determine the currently selected value for this user
                    $currentRoleVal = '';
                    if (!empty($editUser)) {
                        $currentRoleVal = !empty($editUser['role_custom_id'])
                            ? 'custom:' . $editUser['role_custom_id']
                            : ($editUser['role'] ?? '');
                    }
                    $systemRoles = [
                        'admin' => 'Admin', 'manager' => 'Manager',
                        'operation_manager' => 'Operation Manager', 'accounts' => 'Accounts',
                        'sales_rep' => 'Sales Rep', 'tele_caller' => 'Tele Caller',
                        'installer' => 'Installer', 'dealer' => 'Dealer',
                        'documentation' => 'Documentation', 'partner' => 'Partner',
                    ];
                    ?>
                    <optgroup label="System Roles">
                    <?php foreach ($systemRoles as $val => $lbl): ?>
                    <option value="<?= $val ?>" <?= $currentRoleVal === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                    </optgroup>
                    <?php if (!empty($customRoles)): ?>
                    <optgroup label="Custom Roles">
                    <?php foreach ($customRoles as $cr): ?>
                    <?php $crVal = 'custom:' . $cr['id']; ?>
                    <option value="<?= $crVal ?>" <?= $currentRoleVal === $crVal ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cr['label']) ?>
                        <small>(<?= htmlspecialchars(str_replace('_',' ',$cr['base_role'])) ?> permissions)</small>
                    </option>
                    <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
                <div class="form-text">Custom roles are created in <a href="<?= BASE_URL ?>/settings#tabRoles">Settings → Roles</a>.</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="manager_id">Reports To (Manager)</label>
                <select class="form-select" id="manager_id" name="manager_id">
                    <option value="">— None —</option>
                    <?php foreach ($allUsers as $u): ?>
                    <?php if ((int)($u['id'] ?? 0) !== $editId): ?>
                    <option value="<?= $u['id'] ?>"
                            <?= (int)($editUser['manager_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['name']) ?>
                        (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)
                    </option>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php
            $__ws  = substr((string)($editUser['work_start'] ?? '09:00:00'), 0, 5);
            $__we  = substr((string)($editUser['work_end']   ?? '19:00:00'), 0, 5);
            $__wd  = array_filter(explode(',', (string)($editUser['work_days'] ?? '1,2,3,4,5,6')));
            $__trk = (int)($editUser['track_location'] ?? 0) === 1;
            $__dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
            ?>
            <hr class="my-4">
            <div class="mb-3">
                <label class="form-label fw-semibold">Work hours and location</label>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="track_location" name="track_location" value="1" <?= $__trk ? 'checked' : '' ?>>
                    <label class="form-check-label" for="track_location">
                        Track location during work hours
                        <span class="d-block text-muted small">
                            For field staff. Positions outside the hours below are never stored.
                        </span>
                    </label>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1" for="work_start">Starts</label>
                        <input type="time" class="form-control" id="work_start" name="work_start" value="<?= htmlspecialchars($__ws) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted mb-1" for="work_end">Ends</label>
                        <input type="time" class="form-control" id="work_end" name="work_end" value="<?= htmlspecialchars($__we) ?>">
                    </div>
                </div>

                <label class="form-label small text-muted mb-1">Working days</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($__dayNames as $__n => $__lbl): ?>
                    <input type="checkbox" class="btn-check" name="work_days[]" value="<?= $__n ?>"
                           id="wd<?= $__n ?>" <?= in_array((string)$__n, $__wd, true) ? 'checked' : '' ?>>
                    <label class="btn btn-sm btn-outline-secondary" for="wd<?= $__n ?>"><?= $__lbl ?></label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold" for="password">
                    Password <?= $editId ? '<span class="text-muted fw-normal">(leave blank to keep current)</span>' : '<span class="text-danger">*</span>' ?>
                </label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="new-password" minlength="8"
                           <?= $editId ? '' : 'required' ?>>
                    <button type="button" class="btn btn-outline-secondary" id="togglePw" tabindex="-1">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
                <div class="form-text">Minimum 8 characters.</div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Create User' ?></button>
                <a href="<?= BASE_URL ?>/users" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function () {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    pw.type    = pw.type === 'password' ? 'text' : 'password';
    icon.className = pw.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
