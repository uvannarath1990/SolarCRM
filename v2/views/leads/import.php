<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3 d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/leads" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
    <h5 class="mb-0 fw-bold"><i class="bi bi-upload me-2"></i>Import Leads</h5>
    <a href="<?= BASE_URL ?>/leads/import/template" class="btn btn-sm btn-outline-secondary ms-auto">
        <i class="bi bi-download me-1"></i>Download Template
    </a>
</div>

<?php if (isset($results)): ?>
<!-- ── Results view ─────────────────────────────────────────────────────────── -->
<div class="d-flex gap-3 mb-3 flex-wrap">
    <div class="card text-center px-4 py-3">
        <div class="fs-3 fw-bold text-success"><?= $imported ?></div>
        <div class="small text-muted">Imported</div>
    </div>
    <div class="card text-center px-4 py-3">
        <div class="fs-3 fw-bold <?= ($updated ?? 0) > 0 ? 'text-primary' : 'text-muted' ?>"><?= (int)($updated ?? 0) ?></div>
        <div class="small text-muted">Updated</div>
    </div>
    <div class="card text-center px-4 py-3">
        <div class="fs-3 fw-bold <?= $skipped > 0 ? 'text-danger' : 'text-muted' ?>"><?= $skipped ?></div>
        <div class="small text-muted">Skipped / Failed</div>
    </div>
    <div class="card text-center px-4 py-3">
        <div class="fs-3 fw-bold text-secondary"><?= count($results) ?></div>
        <div class="small text-muted">Total Rows</div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <a href="<?= BASE_URL ?>/leads/import" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-upload me-1"></i>Import Another File
        </a>
        <a href="<?= BASE_URL ?>/leads" class="btn btn-primary btn-sm">
            <i class="bi bi-people me-1"></i>View All Leads
        </a>
    </div>
</div>

<?php if (!empty($results)): ?>
<div class="card">
    <div class="card-header fw-semibold bg-white small">Row-by-row Results</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Row</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Detail</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
            <tr class="<?= in_array($r['status'], ['ok','updated'], true) ? '' : ($r['status'] === 'skip' ? 'table-warning' : 'table-danger') ?>">
                <td><?= $r['row'] ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['phone']) ?></td>
                <td>
                    <?php if ($r['status'] === 'ok'): ?>
                        <span class="badge bg-success">Imported</span>
                    <?php elseif ($r['status'] === 'updated'): ?>
                        <span class="badge bg-primary">Updated</span>
                    <?php elseif ($r['status'] === 'skip'): ?>
                        <span class="badge bg-warning text-dark">Skipped</span>
                    <?php else: ?>
                        <span class="badge bg-danger">Error</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (in_array($r['status'], ['ok', 'updated'], true)): ?>
                        <?php if (!empty($r['reason'])): ?>
                        <span class="small text-muted"><?= htmlspecialchars($r['reason']) ?></span><br>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int)$r['lead_id'] ?>" class="text-success small">View lead →</a>
                    <?php else: ?>
                        <span class="text-danger"><?= htmlspecialchars($r['reason']) ?></span>
                        <?php if (!empty($r['lead_id'])): ?>
                        <a href="<?= BASE_URL ?>/leads/detail?id=<?= (int)$r['lead_id'] ?>" class="small ms-1">View lead →</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php foreach ($r['warnings'] ?? [] as $w): ?>
                        <div class="small text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($w) ?></div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ── Upload form ──────────────────────────────────────────────────────────── -->
<div class="row g-3" style="max-width:780px">
    <div class="col-12">
        <div class="alert alert-info small mb-0">
            <strong>How it works:</strong> Upload a CSV with <code>phone</code> (required) and <code>name</code>
            (required for new leads), plus optionally <code>email</code>, <code>location</code>, <code>lead_source</code>,
            <code>lead_source_sub</code>, <code>bill_amount</code>, <code>potential_kw</code>,
            <code>is_new_construction</code> (yes/no), <code>visit_scheduled_date</code> (YYYY-MM-DD),
            <code>lead_stage</code> and <code>quote_amount</code>.
            <br><strong>lead_stage</strong> must be a stage name as shown in the app (e.g. <em>Contacted</em>, <em>Quoted</em>, <em>Won</em>);
            moving to <em>Quoted</em> needs a <code>quote_amount</code>.
            Phones that already exist are skipped unless you tick <em>Update existing leads</em> below.
            <a href="<?= BASE_URL ?>/leads/import/template" class="alert-link">Download the template CSV</a> to get started.
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/leads/import/upload" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv" required>
                        <div class="form-text">Only .csv files. Max 5MB. First row must be headers.</div>
                    </div>

                    <hr class="my-3">
                    <p class="fw-semibold small mb-3 text-muted text-uppercase">Assign All Leads To (optional)</p>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Sales Rep</label>
                            <select class="form-select form-select-sm" name="assigned_rep_id">
                                <option value="">— None —</option>
                                <?php foreach ($reps as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">If set, leads will be assigned and a task created per lead.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Tele Caller</label>
                            <select class="form-select form-select-sm" name="tele_caller_id">
                                <option value="">— None —</option>
                                <?php foreach ($teleCallers as $tc): ?>
                                <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Site Visit Date (apply to all)</label>
                            <input type="date" class="form-control form-control-sm" name="visit_scheduled_date">
                            <div class="form-text">Overridden per row if the CSV has a date. Requires Sales Rep to be selected.</div>
                        </div>
                    </div>

                    <div class="border rounded p-3 mb-3 bg-light">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="update_existing" value="1" id="updateExisting">
                            <label class="form-check-label fw-semibold" for="updateExisting">
                                Update existing leads (matched by phone)
                            </label>
                        </div>
                        <div class="form-text small ms-4">
                            Rows whose phone already exists will update that lead instead of being skipped.
                            Only cells with a value are applied — <strong>blank cells never erase data</strong>.
                            The Sales Rep, Tele Caller and visit date above apply to <em>new</em> leads only.
                            Every change is recorded in the lead's audit trail.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i>Upload & Import
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
