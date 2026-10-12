<?php require __DIR__ . '/../layout/header.php';

$tick = fn($v) => $v
    ? '<i class="bi bi-check-circle-fill text-success" title="Received"></i>'
    : '<i class="bi bi-x-circle text-danger" title="Not received"></i>';
?>

<div class="d-flex align-items-center justify-content-between mt-3 mb-2 flex-wrap gap-2">
    <h5 class="mb-0 fw-bold"><i class="bi bi-folder-x me-2"></i>Pending Documentation</h5>
    <span class="text-muted small"><?= count($rows) ?> lead<?= count($rows) === 1 ? '' : 's' ?></span>
</div>

<p class="text-muted small">
    Site visit completed, but at least one of <strong>Aadhar</strong>,
    <strong>registered mobile</strong>, <strong>KSEB bill</strong> or
    <strong>bank pass book</strong> is still outstanding.
    Lost leads are excluded; Won leads are included.
    Fully collected? See <a href="<?= BASE_URL ?>/documentation/received">Docs Received</a>.
</p>

<!-- Tallies -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">Missing Aadhar</div>
            <div class="fs-5 fw-bold text-danger"><?= $missAadhar ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">Missing Mobile</div>
            <div class="fs-5 fw-bold text-danger"><?= $missMobile ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">Missing KSEB Bill</div>
            <div class="fs-5 fw-bold text-danger"><?= $missKseb ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">Missing Pass Book</div>
            <div class="fs-5 fw-bold text-danger"><?= $missPassbook ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md">
        <div class="card"><div class="card-body py-2">
            <div class="text-muted small">No Doc Owner</div>
            <div class="fs-5 fw-bold text-warning"><?= $unassigned ?></div>
        </div></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= BASE_URL ?>/documentation/pending" class="card mb-3">
    <div class="card-body py-2">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
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
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">Missing Document</label>
                <select name="missing" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">— Any —</option>
                    <option value="aadhar" <?= $missing === 'aadhar' ? 'selected' : '' ?>>Aadhar</option>
                    <option value="mobile" <?= $missing === 'mobile' ? 'selected' : '' ?>>Registered Mobile</option>
                    <option value="kseb"   <?= $missing === 'kseb'   ? 'selected' : '' ?>>KSEB Bill</option>
                    <option value="passbook" <?= $missing === 'passbook' ? 'selected' : '' ?>>Bank Pass Book</option>
                </select>
            </div>
            <div class="col-8 col-md-4">
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
<div class="alert alert-success small">
    <i class="bi bi-check2-circle me-1"></i>Nothing pending — every visited lead has its documents.
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
                    <th class="text-center">Aadhar</th>
                    <th class="text-center">Mobile</th>
                    <th class="text-center">KSEB Bill</th>
                    <th class="text-center">Pass Book</th>
                    <th>Sales Rep</th>
                    <th>Doc Owner</th>
                    <th class="text-end">Visit Done</th>
                    <th class="text-end">Days</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $days = (int) $r['days_since_visit'];
            ?>
                <tr class="<?= $days >= 30 ? 'table-danger' : ($days >= 14 ? 'table-warning' : '') ?>">
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
                    <td class="text-center"><?= $tick($r['doc_aadhar']) ?></td>
                    <td class="text-center"><?= $tick($r['doc_registered_mobile']) ?></td>
                    <td class="text-center"><?= $tick($r['doc_kseb_bill']) ?></td>
                    <td class="text-center"><?= $tick($r['doc_bank_passbook']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($r['rep_name'] ?? '—') ?></td>
                    <td class="<?= empty($r['documentation_user_id']) ? 'text-warning fw-semibold' : 'text-muted' ?>">
                        <?= $r['doc_user_name'] ? htmlspecialchars($r['doc_user_name']) : 'Unassigned' ?>
                    </td>
                    <td class="text-end text-muted"><?= htmlspecialchars(formatDate($r['site_visit_completed_date'])) ?></td>
                    <td class="text-end fw-semibold"><?= $days ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="text-muted small mt-2">
    Rows turn amber after 14 days since the site visit, red after 30.
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
