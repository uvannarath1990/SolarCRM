<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-folder-check me-2"></i>Documents Received</h5>
    <span class="text-muted small"><?= count($rows) ?> lead<?= count($rows) === 1 ? '' : 's' ?></span>
</div>

<p class="text-muted small">
    Site visit completed and <strong>all four</strong> documents received —
    Aadhar, registered mobile, KSEB bill and bank pass book. Lost leads are excluded.
    Still outstanding? See <a href="<?= BASE_URL ?>/documentation/pending">Pending Docs</a>.
</p>

<!-- Tallies -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-4">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">Complete Sets</div>
            <div class="fs-5 fw-bold text-success"><?= count($rows) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">At Won Stage</div>
            <div class="fs-5 fw-bold"><?= $wonCount ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">No Doc Owner</div>
            <div class="fs-5 fw-bold <?= $unassigned > 0 ? 'text-warning' : 'text-muted' ?>"><?= $unassigned ?></div>
        </div></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/documentation/received" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold mb-1">Stage</label>
                <select name="stage" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— All —</option>
                    <?php foreach (allStages() as $s): if ($s === 'Lost') continue; ?>
                    <option value="<?= htmlspecialchars($s) ?>" <?= $stage === $s ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-8 col-md-6">
                <label class="form-label small fw-semibold mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Lead name or location…" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-4 col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
            </div>
        </div>
    </div>
</form>

<?php if (empty($rows)): ?>
<div class="alert alert-info small">
    <i class="bi bi-info-circle me-1"></i>No lead has a complete document set yet.
</div>
<?php else: ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <th style="min-width:150px">Lead</th>
                    <th>Stage</th>
                    <th>Location</th>
                    <th class="text-center">Documents</th>
                    <th>Sales Rep</th>
                    <th>Doc Owner</th>
                    <th class="text-end">Visit Done</th>
                    <th class="text-end">kW</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int)$r['id'] ?>" target="_blank"
                           class="text-decoration-none fw-semibold">
                            <?= htmlspecialchars($r['name']) ?>
                            <i class="bi bi-box-arrow-up-right ms-1" style="font-size:.65rem"></i>
                        </a>
                        <?php if (canSeePhone($r) && $r['phone']): ?>
                        <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($r['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?= stageBadgeClass($r['stage']) ?>"><?= htmlspecialchars($r['stage']) ?></span></td>
                    <td class="text-muted"><?= htmlspecialchars($r['location'] ?? '—') ?></td>
                    <td class="text-center">
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            <i class="bi bi-check-circle-fill me-1"></i>All 4
                        </span>
                    </td>
                    <td class="text-muted"><?= htmlspecialchars($r['rep_name'] ?? '—') ?></td>
                    <td class="<?= empty($r['documentation_user_id']) ? 'text-warning fw-semibold' : 'text-muted' ?>">
                        <?= $r['doc_user_name'] ? htmlspecialchars($r['doc_user_name']) : 'Unassigned' ?>
                    </td>
                    <td class="text-end text-muted"><?= htmlspecialchars(formatDate($r['site_visit_completed_date'])) ?></td>
                    <td class="text-end"><?= $r['potential_kw'] !== null ? htmlspecialchars((string)$r['potential_kw']) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
