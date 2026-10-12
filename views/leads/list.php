<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-2 page-header">
    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2"></i>Leads <span class="text-muted fw-normal fs-6">(<?= number_format($total) ?>)</span></h5>
    <div class="d-flex gap-2">
        <?php if (hasRole('admin', 'manager')): ?>
        <a href="<?= BASE_URL ?>/leads/import" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-upload me-1"></i>Import
        </a>
        <?php endif; ?>
        <?php if (canCreateLead()): ?>
        <a href="<?= BASE_URL ?>/leads/create" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Lead
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole('admin', 'manager')): ?>
<ul class="nav nav-pills nav-sm mb-3 gap-1" style="font-size:.85rem">
    <li class="nav-item">
        <a class="nav-link py-1 px-3 <?= ($filterPartner ?? 0) == 0 ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/leads">All Leads</a>
    </li>
    <li class="nav-item">
        <a class="nav-link py-1 px-3 <?= ($filterPartner ?? 0) == -1 ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/leads?partner=-1">
            <i class="bi bi-handshake me-1"></i>Partner Leads
            <?php if ($partnerLeadCount > 0): ?>
            <span class="badge bg-primary-subtle text-primary ms-1"><?= $partnerLeadCount ?></span>
            <?php endif; ?>
        </a>
    </li>
    <?php foreach ($partners as $p): ?>
    <li class="nav-item">
        <a class="nav-link py-1 px-3 <?= ($filterPartner ?? 0) == $p['id'] ? 'active' : '' ?>"
           href="<?= BASE_URL ?>/leads?partner=<?= $p['id'] ?>">
            <?= htmlspecialchars($p['name']) ?>
        </a>
    </li>
    <?php endforeach; ?>
    <?php if (empty($partners)): ?>
    <li class="nav-item d-flex align-items-center ms-2">
        <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>No partner users yet — <a href="<?= BASE_URL ?>/users/create">add one</a></span>
    </li>
    <?php endif; ?>
</ul>
<?php endif; ?>

<!-- Filter bar -->
<form method="GET" action="<?= BASE_URL ?>/leads" class="row g-2 mb-3 align-items-end filter-bar">
    <div class="col-12 col-md-4 col-lg-3">
        <input type="text" class="form-control form-control-sm" name="q"
               placeholder="Search name, phone, location…" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-6 col-md-2">
        <select class="form-select form-select-sm" name="stage">
            <option value="">All Stages</option>
            <?php foreach (allStages() as $s): ?>
            <option value="<?= htmlspecialchars($s) ?>" <?= $stage === $s ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if (hasRole('admin', 'tele_caller')): ?>
    <div class="col-6 col-md-2">
        <select class="form-select form-select-sm" name="rep">
            <option value="">All Reps</option>
            <?php foreach ($reps as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $repId === (int)$r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="col-6 col-md-2">
        <select class="form-select form-select-sm" name="source">
            <option value="">All Sources</option>
            <?php foreach ($sources as $src): ?>
            <option value="<?= $src['id'] ?>" <?= $sourceId === (int)$src['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($src['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Created date filter -->
    <div class="col-6 col-md-2">
        <select class="form-select form-select-sm" name="date_range" id="dateRangeSelect">
            <option value="">All Dates</option>
            <option value="today"        <?= ($dateRange??'') === 'today'        ? 'selected' : '' ?>>Today</option>
            <option value="yesterday"    <?= ($dateRange??'') === 'yesterday'    ? 'selected' : '' ?>>Yesterday</option>
            <option value="this_week"    <?= ($dateRange??'') === 'this_week'    ? 'selected' : '' ?>>This Week</option>
            <option value="this_month"   <?= ($dateRange??'') === 'this_month'   ? 'selected' : '' ?>>This Month</option>
            <option value="last_month"   <?= ($dateRange??'') === 'last_month'   ? 'selected' : '' ?>>Last Month</option>
            <option value="custom"       <?= ($dateRange??'') === 'custom'       ? 'selected' : '' ?>>Custom Range</option>
        </select>
    </div>
    <div class="col-6 col-md-2 d-none" id="customDateWrap">
        <div class="input-group input-group-sm">
            <input type="date" class="form-control form-control-sm" name="date_from"
                   value="<?= htmlspecialchars($dateFrom ?? '') ?>" placeholder="From">
            <span class="input-group-text px-1">–</span>
            <input type="date" class="form-control form-control-sm" name="date_to"
                   value="<?= htmlspecialchars($dateTo ?? '') ?>" placeholder="To">
        </div>
    </div>
    <div class="col-6 col-md-auto">
        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Filter</button>
    </div>
    <div class="col-6 col-md-auto">
        <a href="<?= BASE_URL ?>/leads" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
    </div>
</form>
<script>
(function () {
    const sel  = document.getElementById('dateRangeSelect');
    const wrap = document.getElementById('customDateWrap');
    function toggle() {
        wrap.classList.toggle('d-none', sel.value !== 'custom');
    }
    sel.addEventListener('change', toggle);
    toggle(); // run on load so custom inputs show if already selected
})();
</script>

<!-- ── Desktop table (hidden on mobile via CSS) ─────────────────────────── -->
<div class="card leads-table-wrap">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
                <tr>
                    <?php if (!empty($canBulk)): ?>
                    <th style="width:34px">
                        <input type="checkbox" class="form-check-input" id="bulkSelectAll"
                               title="Select every lead on this page" aria-label="Select all on this page">
                    </th>
                    <?php endif; ?>
                    <th>Lead</th>
                    <th>Stage</th>
                    <th>kW</th>
                    <th>Quote</th>
                    <th>Rep</th>
                    <th>Follow-up</th>
                    <th>Visit</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $lead): ?>
            <tr>
                <?php if (!empty($canBulk)): ?>
                <td>
                    <input type="checkbox" class="form-check-input bulk-row" value="<?= (int)$lead['id'] ?>"
                           data-name="<?= htmlspecialchars($lead['name']) ?>"
                           aria-label="Select <?= htmlspecialchars($lead['name']) ?>">
                </td>
                <?php endif; ?>
                <td>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>" class="fw-semibold text-decoration-none">
                        <?= htmlspecialchars($lead['name']) ?>
                    </a>
                    <?php if ($lead['phone']): ?>
                    <br><span class="text-muted">
                        <?php if (canSeePhone($lead)): ?>
                        <?= htmlspecialchars($lead['phone']) ?>
                        <?php else: ?>
                        <i class="bi bi-lock me-1"></i><?= htmlspecialchars(maskPhone($lead['phone'])) ?>
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($lead['location']): ?>
                    <br><span class="text-muted"><i class="bi bi-geo-alt-fill text-secondary me-1"></i><?= htmlspecialchars($lead['location']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($lead['partner_name']) && hasRole('admin', 'manager')): ?>
                    <br><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size:.7rem">
                        <i class="bi bi-handshake me-1"></i><?= htmlspecialchars($lead['partner_name']) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="stage-pill badge <?= stageBadgeClass($lead['stage']) ?>"
                          data-lead-id="<?= $lead['id'] ?>"
                          data-stage="<?= htmlspecialchars($lead['stage']) ?>"
                          data-role="<?= $_SESSION['role'] ?>">
                        <?= htmlspecialchars($lead['stage']) ?>
                    </span>
                </td>
                <td><?= $lead['potential_kw'] ? htmlspecialchars($lead['potential_kw']) . ' kW' : '—' ?></td>
                <td>
                    <span class="editable-num"
                          data-lead-id="<?= $lead['id'] ?>"
                          data-field="quote_amount"
                          data-value="<?= $lead['quote_amount'] ?? '' ?>">
                        <?= $lead['quote_amount'] ? formatCurrency((float)$lead['quote_amount']) : '<span class="text-muted">—</span>' ?>
                    </span>
                </td>
                <td class="text-muted"><?= htmlspecialchars($lead['rep_name'] ?? '—') ?></td>
                <td class="<?= ($lead['next_followup_date'] && $lead['next_followup_date'] < date('Y-m-d')) ? 'text-danger fw-semibold' : 'text-muted' ?>">
                    <?= $lead['next_followup_date'] ? formatDate($lead['next_followup_date']) : '—' ?>
                </td>
                <td class="text-muted">
                    <?= $lead['visit_scheduled_date'] ? formatDate($lead['visit_scheduled_date']) : '—' ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($leads)): ?>
            <tr><td colspan="<?= !empty($canBulk) ? 8 : 7 ?>" class="text-center text-muted py-5">No leads found.
                <?php if (canCreateLead()): ?>
                <a href="<?= BASE_URL ?>/leads/create">Create the first one</a>
                <?php endif; ?>
            </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Mobile cards (hidden on desktop via CSS) ─────────────────────────── -->
<div class="lead-card-mobile">
    <?php if (empty($leads)): ?>
    <div class="text-center text-muted py-5">
        No leads found.
        <?php if (canCreateLead()): ?>
        <a href="<?= BASE_URL ?>/leads/create">Create the first one</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php foreach ($leads as $lead): ?>
    <div class="card mb-2">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div style="min-width:0">
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>" class="fw-semibold text-decoration-none d-block text-truncate">
                        <?= htmlspecialchars($lead['name']) ?>
                    </a>
                    <?php if ($lead['phone']): ?>
                    <?php if (canSeePhone($lead)): ?>
                    <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="text-muted small text-decoration-none">
                        <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($lead['phone']) ?>
                    </a>
                    <?php else: ?>
                    <span class="text-muted small">
                        <i class="bi bi-lock me-1"></i><?= htmlspecialchars(maskPhone($lead['phone'])) ?>
                    </span>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($lead['location']): ?>
                    <div class="text-muted small text-truncate">
                        <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($lead['location']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="stage-pill badge <?= stageBadgeClass($lead['stage']) ?> flex-shrink-0"
                      data-lead-id="<?= $lead['id'] ?>"
                      data-stage="<?= htmlspecialchars($lead['stage']) ?>"
                      data-role="<?= $_SESSION['role'] ?>">
                    <?= htmlspecialchars($lead['stage']) ?>
                </span>
            </div>

            <div class="d-flex gap-3 mt-2 small text-muted flex-wrap">
                <?php if ($lead['quote_amount']): ?>
                <span><i class="bi bi-currency-rupee"></i><?= formatCurrency((float)$lead['quote_amount']) ?></span>
                <?php endif; ?>
                <?php if ($lead['potential_kw']): ?>
                <span><i class="bi bi-lightning-charge me-1"></i><?= $lead['potential_kw'] ?> kW</span>
                <?php endif; ?>
                <?php if ($lead['next_followup_date']): ?>
                <span class="<?= $lead['next_followup_date'] < date('Y-m-d') ? 'text-danger fw-semibold' : '' ?>">
                    <i class="bi bi-calendar-check me-1"></i><?= formatDate($lead['next_followup_date']) ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <span class="small text-muted"><?= htmlspecialchars($lead['rep_name'] ?? '—') ?></span>
                <div class="d-flex gap-1">
                    <?php if ($lead['phone'] && canSeePhone($lead)): ?>
                    <a href="tel:<?= htmlspecialchars($lead['phone']) ?>" class="btn btn-sm btn-outline-success py-0 px-2">
                        <i class="bi bi-telephone"></i>
                    </a>
                    <a href="https://wa.me/<?= preg_replace('/\D/', '', $lead['phone']) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-2">
                        <i class="bi bi-whatsapp"></i>
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paging['total_pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center flex-wrap">
        <li class="page-item <?= !$paging['has_prev'] ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">‹ Prev</a>
        </li>
        <?php for ($p = max(1, $page - 2); $p <= min($paging['total_pages'], $page + 2); $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= !$paging['has_next'] ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next ›</a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<?php if (!empty($canBulk)): ?>
<!-- Page size — lets bulk selection cover more than 25 leads at once -->
<div class="d-flex justify-content-center align-items-center gap-2 mt-2 small text-muted">
    Show
    <?php foreach ([25, 50, 100] as $pp): ?>
    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['per_page' => $pp, 'page' => 1]))) ?>"
       class="<?= $perPage === $pp ? 'fw-bold text-dark' : 'text-muted' ?> text-decoration-none"><?= $pp ?></a>
    <?php endforeach; ?>
    per page
</div>

<!-- ── Bulk action bar ─────────────────────────────────────────────────────── -->
<div id="bulkBar" class="d-none" role="region" aria-label="Bulk update">
  <div class="bulk-inner">
    <span class="fw-semibold text-nowrap"><span id="bulkCount">0</span> selected</span>

    <select id="bulkAction" class="form-select form-select-sm" style="max-width:210px">
      <option value="">— Choose an action —</option>
      <option value="stage">Set stage</option>
      <option value="assigned_rep_id">Assign sales rep</option>
      <option value="tele_caller_id">Assign tele caller</option>
      <option value="documentation_user_id">Assign documentation user</option>
      <option value="source_id">Set lead source</option>
    </select>

    <!-- One value control per action; only the matching one is shown -->
    <select class="form-select form-select-sm bulk-val d-none" data-for="stage" style="max-width:210px">
      <option value="">— Stage —</option>
      <?php foreach (allStages() as $st): ?>
      <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm d-none" id="bulkLostReason" style="max-width:210px"
            title="Optional reason recorded on each lead">
      <option value="">— Lost reason (optional) —</option>
      <?php foreach (array_filter(array_map('trim', explode('|', getSetting('status_reasons', '')))) as $rsn): ?>
      <option value="<?= htmlspecialchars($rsn) ?>"><?= htmlspecialchars($rsn) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="form-select form-select-sm bulk-val d-none" data-for="assigned_rep_id" style="max-width:210px">
      <option value="">— Sales rep —</option>
      <option value="0">Unassign</option>
      <?php foreach ($reps as $r): ?>
      <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="form-select form-select-sm bulk-val d-none" data-for="tele_caller_id" style="max-width:210px">
      <option value="">— Tele caller —</option>
      <option value="0">Unassign</option>
      <?php foreach ($bulkTele as $t): ?>
      <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $t['role'])) ?>)</option>
      <?php endforeach; ?>
    </select>

    <select class="form-select form-select-sm bulk-val d-none" data-for="documentation_user_id" style="max-width:210px">
      <option value="">— Documentation user —</option>
      <option value="0">Unassign</option>
      <?php foreach ($bulkDocs as $d): ?>
      <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <select class="form-select form-select-sm bulk-val d-none" data-for="source_id" style="max-width:210px">
      <option value="">— Lead source —</option>
      <?php foreach ($sources as $src): ?>
      <option value="<?= (int)$src['id'] ?>"><?= htmlspecialchars($src['name']) ?></option>
      <?php endforeach; ?>
    </select>


    <button id="bulkApply" class="btn btn-primary btn-sm text-nowrap" disabled>Apply</button>
    <button id="bulkClear" class="btn btn-link btn-sm text-muted text-nowrap">Clear selection</button>
  </div>
  <div id="bulkHint" class="small text-muted mt-1 d-none"></div>
</div>

<style>
#bulkBar {
    position: sticky; bottom: 0; z-index: 1030;
    margin-top: 12px; padding: 10px 14px;
    background: #fff; border: 1px solid #cbd5e1; border-radius: 8px;
    box-shadow: 0 -4px 16px rgba(15, 23, 42, .08);
}
#bulkBar .bulk-inner { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
tr.bulk-selected > td { background: #eff6ff !important; }
</style>

<script>
'use strict';
(function () {
    const bar       = document.getElementById('bulkBar');
    const countEl   = document.getElementById('bulkCount');
    const actionSel = document.getElementById('bulkAction');
    const applyBtn  = document.getElementById('bulkApply');
    const hint      = document.getElementById('bulkHint');
    const lostSel   = document.getElementById('bulkLostReason');
    const allBox    = document.getElementById('bulkSelectAll');
    if (!bar) return;

    const rows     = () => Array.from(document.querySelectorAll('.bulk-row'));
    const selected = () => rows().filter(b => b.checked);
    const activeVal = () => document.querySelector('.bulk-val[data-for="' + actionSel.value + '"]');

    const HINTS = {
        stage: 'Applies the same side-effects as a single stage change — tasks for the new stage, and for Won an installation record. Leads without a quote are skipped when moving to Quoted.',
        source_id: 'Sub-source is cleared on changed leads, since it belonged to the old source.',
    };

    function refresh() {
        const n = selected().length;
        countEl.textContent = n;
        bar.classList.toggle('d-none', n === 0);
        rows().forEach(b => b.closest('tr').classList.toggle('bulk-selected', b.checked));

        const all = rows();
        if (allBox) {
            allBox.checked       = all.length > 0 && n === all.length;
            allBox.indeterminate = n > 0 && n < all.length;
        }

        const v = activeVal();
        const hasValue = v && (v.type === 'date' || v.value !== '');
        applyBtn.disabled = n === 0 || !actionSel.value || !hasValue;
    }

    actionSel.addEventListener('change', function () {
        document.querySelectorAll('.bulk-val').forEach(el =>
            el.classList.toggle('d-none', el.dataset.for !== this.value));
        lostSel.classList.add('d-none');
        hint.textContent = HINTS[this.value] || '';
        hint.classList.toggle('d-none', !HINTS[this.value]);
        refresh();
    });

    document.querySelectorAll('.bulk-val').forEach(el => el.addEventListener('change', function () {
        if (this.dataset.for === 'stage') lostSel.classList.toggle('d-none', this.value !== 'Lost');
        refresh();
    }));

    document.addEventListener('change', e => {
        if (e.target.classList?.contains('bulk-row')) refresh();
    });

    allBox?.addEventListener('change', function () {
        rows().forEach(b => b.checked = this.checked);
        refresh();
    });

    document.getElementById('bulkClear').addEventListener('click', () => {
        rows().forEach(b => b.checked = false);
        refresh();
    });

    applyBtn.addEventListener('click', async function () {
        const picked = selected();
        const valEl  = activeVal();
        if (!picked.length || !valEl) return;

        const label  = actionSel.options[actionSel.selectedIndex].text;
        const shown  = valEl.tagName === 'SELECT'
                     ? valEl.options[valEl.selectedIndex].text
                     : (valEl.value || '(cleared)');

        if (!confirm(label + ' → "' + shown + '" on ' + picked.length + ' lead(s)?\n\nEvery change is recorded in each lead\'s audit trail.')) {
            return;
        }

        const body = {
            lead_ids: picked.map(b => parseInt(b.value)),
            action:   actionSel.value,
            value:    valEl.value,
        };
        if (actionSel.value === 'stage' && valEl.value === 'Lost' && lostSel.value) {
            body.lost_reason = lostSel.value;
        }

        this.disabled = true;
        const original = this.innerHTML;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Applying…';

        const res = await apiFetch('/leads/bulk-update', { body: JSON.stringify(body) });

        if (!res.ok) {
            alert(res.error || res.message || 'Bulk update failed.');
            this.disabled = false;
            this.innerHTML = original;
            return;
        }

        let msg = 'Updated ' + res.updated + ' lead(s).';
        if (res.skipped && res.skipped.length) {
            msg += '\n\nSkipped ' + res.skipped.length + ':\n' +
                   res.skipped.slice(0, 15).map(s => '• ' + s.name + ' — ' + s.reason).join('\n');
            if (res.skipped.length > 15) msg += '\n…and ' + (res.skipped.length - 15) + ' more';
        }
        alert(msg);
        location.reload();
    });

    refresh();
})();
</script>
<?php endif; ?>

<!-- Stage dropdown template (populated by inline-edit.js) -->
<div id="stageOptions" data-stages='<?= json_encode(allStages()) ?>' class="d-none"></div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
