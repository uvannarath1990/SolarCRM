<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-cash-stack me-2"></i>Payments</h5>
</div>

<!-- Search -->
<form method="GET" action="<?= BASE_URL ?>/payments" class="d-flex gap-2 mb-3">
    <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
    <input type="search" name="q" class="form-control form-control-sm" style="max-width:260px"
           placeholder="Search name or phone…" value="<?= htmlspecialchars($q ?? '') ?>">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Search</button>
    <?php if (!empty($q)): ?>
    <a href="<?= BASE_URL ?>/payments?status=<?= htmlspecialchars($status) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- Status tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $status === 'all' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/payments?status=all<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">All</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'pending' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/payments?status=pending<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">Pending</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $status === 'completed' ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/payments?status=completed<?= !empty($q) ? '&q=' . urlencode($q) : '' ?>">Completed</a>
    </li>
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Customer</th>
                    <th>kW</th>
                    <th>Total Amount</th>
                    <th>Paid</th>
                    <th>Discount</th>
                    <th>Balance</th>
                    <th>Payment Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $p):
                $totalAmt  = (float) ($p['total_amount'] ?? 0);
                $paid      = (float) ($p['total_paid'] ?? 0);
                $discount  = (float) ($p['discount'] ?? 0);
                $balance   = $totalAmt - $discount - $paid;
            ?>
            <tr>
                <td>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $p['id'] ?>" class="fw-semibold text-decoration-none">
                        <?= htmlspecialchars($p['name']) ?>
                    </a>
                    <?php if ($p['phone']): ?>
                    <div class="text-muted"><?= htmlspecialchars($p['phone']) ?></div>
                    <?php endif; ?>
                    <?php if ($p['location']): ?>
                    <div class="text-muted small"><?= htmlspecialchars($p['location']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= $p['potential_kw'] ? htmlspecialchars($p['potential_kw']) . ' kW' : '—' ?></td>
                <td><?= $totalAmt > 0 ? formatCurrency($totalAmt) : '—' ?></td>
                <td><?= $paid > 0 ? formatCurrency($paid) : '—' ?></td>
                <td><?= $discount > 0 ? formatCurrency($discount) : '—' ?></td>
                <td class="<?= $balance > 0 ? 'text-danger fw-semibold' : 'text-success' ?>">
                    <?= $totalAmt > 0 ? formatCurrency($balance) : '—' ?>
                </td>
                <td>
                    <?php if ($p['payment_status']): ?>
                    <span class="badge <?= $p['payment_status'] === 'Payment Completed' ? 'bg-success' : 'bg-warning text-dark' ?> small">
                        <?= htmlspecialchars($p['payment_status']) ?>
                    </span>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $p['id'] ?>#tabFinancials" class="btn btn-sm btn-outline-primary py-0">View</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?>
            <tr><td colspan="8" class="text-center text-muted py-5">No Won leads found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
