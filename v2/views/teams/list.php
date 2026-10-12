<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people-fill me-2"></i>Teams</h5>
    <a href="<?= BASE_URL ?>/teams/create" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Team
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Team Name</th>
                    <th>Leader</th>
                    <th>Members</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($teams as $t): ?>
            <tr id="team-row-<?= $t['id'] ?>">
                <td class="fw-semibold"><?= htmlspecialchars($t['name']) ?></td>
                <td><?= htmlspecialchars($t['leader_name']) ?></td>
                <td><?= (int)$t['member_count'] ?> installer<?= (int)$t['member_count'] !== 1 ? 's' : '' ?></td>
                <td>
                    <?php if ($t['is_active']): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </td>
                <td class="text-nowrap">
                    <a href="<?= BASE_URL ?>/teams/create?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-secondary py-0">Edit</a>
                    <button class="btn btn-sm btn-outline-danger py-0 ms-1 delete-team-btn" data-id="<?= $t['id'] ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($teams)): ?>
            <tr><td colspan="5" class="text-center text-muted py-5">No teams yet. <a href="<?= BASE_URL ?>/teams/create">Create one</a>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.delete-team-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!confirm('Delete this team? If it has scheduled operations it will be deactivated instead.')) return;
        const res = await apiFetch('/teams/delete', {
            method: 'POST',
            body: JSON.stringify({ id: parseInt(btn.dataset.id) }),
        });
        if (res.ok) {
            document.getElementById('team-row-' + btn.dataset.id)?.remove();
        } else {
            showToast('danger', res.error ?? 'Could not delete.');
        }
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
