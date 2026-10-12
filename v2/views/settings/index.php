<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3">
    <h5 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Settings</h5>
</div>

<ul class="nav nav-tabs mb-3" id="settingsTabs">
    <?php if ($isAdmin): ?>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabCompany">Company</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabLeads">Lead Config</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSources">Lead Sources</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRoles" id="tabRolesBtn">Roles</button></li>
    <?php else: ?>
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSources">Lead Sources</button></li>
    <?php endif; ?>
</ul>

<div class="tab-content">

<?php if ($isAdmin): ?>
<!-- Company info -->
<div class="tab-pane fade show active" id="tabCompany">
    <div class="card" style="max-width:600px">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/settings/save">
                <?= csrfField() ?>
                <?php foreach ([
                    'company_name'    => 'Company Name',
                    'company_phone'   => 'Company Phone',
                    'company_email'   => 'Company Email',
                    'company_address' => 'Company Address',
                    'currency_symbol' => 'Currency Symbol',
                ] as $key => $label): ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small"><?= $label ?></label>
                    <input type="text" class="form-control form-control-sm" name="<?= $key ?>"
                           value="<?= htmlspecialchars($settings[$key] ?? '') ?>">
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm">Save Company Info</button>
            </form>
        </div>
    </div>
</div>

<!-- Lead config -->
<div class="tab-pane fade" id="tabLeads">
    <div class="card" style="max-width:600px">
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/settings/save">
                <?= csrfField() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Bill-to-kW Divisor</label>
                    <div class="input-group input-group-sm" style="max-width:200px">
                        <span class="input-group-text">Bill ÷</span>
                        <input type="number" class="form-control" name="bill_to_kw_divisor"
                               value="<?= htmlspecialchars($settings['bill_to_kw_divisor'] ?? '1200') ?>"
                               min="1" step="1">
                        <span class="input-group-text">= kW</span>
                    </div>
                    <div class="form-text">e.g. ₹6,000 bill ÷ 1200 = 5 kW</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Lost/Blocked Reasons (one per line or pipe-separated)</label>
                    <textarea class="form-control form-control-sm" name="status_reasons" rows="5"><?= htmlspecialchars(str_replace('|', "\n", $settings['status_reasons'] ?? '')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Lost Reasons (one per line or pipe-separated)</label>
                    <textarea class="form-control form-control-sm" name="lost_reasons" rows="5"><?= htmlspecialchars(str_replace('|', "\n", $settings['lost_reasons'] ?? '')) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">GPS Visit Radius (metres)</label>
                    <input type="number" class="form-control form-control-sm" name="gps_radius_meters"
                           value="<?= htmlspecialchars($settings['gps_radius_meters'] ?? '500') ?>"
                           min="50" step="50" style="max-width:160px">
                </div>
                <hr class="my-3">
                <?php
                // Role permission groups. The hidden *_submitted marker lets an
                // empty selection save as "nobody" instead of being ignored.
                $roleGroups = [
                    'lead_create_roles' => [
                        'title' => 'Who can create leads',
                        'help'  => 'Controls the New Lead button and the create/save endpoints.',
                        'value' => $settings['lead_create_roles'] ?? DEFAULT_LEAD_CREATE_ROLES,
                    ],
                    'lead_assign_rep_roles' => [
                        'title' => 'Who can assign a Sales Rep when creating a lead',
                        'help'  => 'Sales reps are always assigned to leads they create themselves, '
                                 . 'regardless of this setting.',
                        'value' => $settings['lead_assign_rep_roles'] ?? DEFAULT_ASSIGN_REP_ROLES,
                    ],
                    'lead_assign_tele_roles' => [
                        'title' => 'Who can assign a Tele Caller when creating a lead',
                        'help'  => 'Tele callers are always assigned to leads they create themselves.',
                        'value' => $settings['lead_assign_tele_roles'] ?? DEFAULT_ASSIGN_TELE_ROLES,
                    ],
                    'lead_assign_partner_roles' => [
                        'title' => 'Who can attribute a lead to a Partner',
                        'help'  => 'Partners are always attributed to leads they submit themselves.',
                        'value' => $settings['lead_assign_partner_roles'] ?? DEFAULT_ASSIGN_PARTNER_ROLES,
                    ],
                    'doc_portal_roles' => [
                        'title' => 'Who can sign in through the Documentation login',
                        'help'  => 'The Documentation tab on the login page. Partners see only '
                                 . 'their own leads there, and documentation users other than '
                                 . 'the Head see only leads assigned to them.',
                        'value' => $settings['doc_portal_roles'] ?? DEFAULT_DOC_PORTAL_ROLES,
                    ],
                    'doc_tab_roles' => [
                        'title' => 'Who can see the Pending Docs / Docs Received tabs',
                        'help'  => 'Documentation users other than the Head only ever see leads '
                                 . 'assigned to them, even when ticked here.',
                        'value' => $settings['doc_tab_roles'] ?? DEFAULT_DOC_TAB_ROLES,
                    ],
                ];
                foreach ($roleGroups as $key => $group):
                    $ticked = array_filter(array_map('trim', explode('|', $group['value'])));
                ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold small mb-1"><?= htmlspecialchars($group['title']) ?></label>
                    <div class="form-text small mb-2" style="font-size:.72rem">
                        <?= htmlspecialchars($group['help']) ?>
                    </div>
                    <input type="hidden" name="<?= $key ?>_submitted" value="1">
                    <div class="row g-1">
                    <?php foreach (configurableRoleTokens() as $val => $label): ?>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="<?= $key ?>[]"
                                       value="<?= $val ?>" id="<?= $key ?>-<?= $val ?>"
                                       <?= in_array($val, $ticked, true) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="<?= $key ?>-<?= $val ?>">
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-sm">Save Lead Config</button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lead sources & subcategories -->
<div class="tab-pane fade <?= $isAdmin ? '' : 'show active' ?>" id="tabSources">
    <div class="row g-3" style="max-width:900px">

        <!-- Sources list -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header fw-semibold bg-white small">Lead Sources</div>
                <ul class="list-group list-group-flush" id="sourcesList">
                <?php foreach ($sources as $src): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center src-row"
                    id="src-<?= $src['id'] ?>">
                    <button class="btn btn-link btn-sm text-start p-0 text-decoration-none fw-semibold text-dark expand-src-btn"
                            data-src-id="<?= $src['id'] ?>" data-src-name="<?= htmlspecialchars($src['name']) ?>">
                        <?= htmlspecialchars($src['name']) ?>
                        <?php if (!$src['is_active']): ?><span class="badge bg-secondary ms-1 fw-normal">Inactive</span><?php endif; ?>
                        <span class="badge bg-light text-secondary ms-1 fw-normal">
                            <?= count($subsMap[(int)$src['id']] ?? []) ?> sub
                        </span>
                        <div class="text-muted fw-normal" style="font-size:.68rem">
                            <?php if (!empty($src['created_by_name'])): ?>
                            Added by <?= htmlspecialchars($src['created_by_name']) ?>
                            <?= !empty($src['created_at']) ? ' · ' . htmlspecialchars(formatDate($src['created_at'])) : '' ?>
                            <?php else: ?>
                            Added <?= !empty($src['created_at']) ? htmlspecialchars(formatDate($src['created_at'])) : 'before tracking' ?>
                            <?php endif; ?>
                            <?php if (!empty($src['updated_by_name'])): ?>
                            <br>Last changed by <?= htmlspecialchars($src['updated_by_name']) ?>
                            <?= !empty($src['updated_at']) ? ' · ' . htmlspecialchars(formatDate($src['updated_at'])) : '' ?>
                            <?php endif; ?>
                        </div>
                    </button>
                    <span class="d-flex gap-1 align-items-center flex-shrink-0">
                        <span class="badge bg-light text-secondary fw-normal" title="Leads using this source">
                            <?= (int)($src['lead_count'] ?? 0) ?>
                        </span>
                        <button class="btn btn-sm <?= $src['is_active'] ? 'btn-outline-secondary' : 'btn-outline-success' ?> py-0 toggle-source"
                                data-id="<?= $src['id'] ?>">
                            <?= $src['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                        <button class="btn btn-sm btn-outline-danger py-0 delete-source"
                                data-id="<?= $src['id'] ?>"
                                data-name="<?= htmlspecialchars($src['name']) ?>"
                                data-leads="<?= (int)($src['lead_count'] ?? 0) ?>"
                                title="Delete this source">
                            <i class="bi bi-trash"></i>
                        </button>
                    </span>
                </li>
                <?php endforeach; ?>
                </ul>
                <div class="card-footer">
                    <form id="addSourceForm" class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm" id="newSourceName" placeholder="New source name" required>
                        <button type="submit" class="btn btn-sm btn-primary text-nowrap">Add</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Subcategories panel -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header fw-semibold bg-white small" id="subsCardTitle">Subcategories</div>

                <!-- Add sub form — always visible, source picked from dropdown -->
                <div class="card-body pb-2 border-bottom">
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label small fw-semibold mb-1">Source</label>
                            <select class="form-select form-select-sm" id="addSubSourceId">
                                <option value="">— Select source —</option>
                                <?php foreach ($sources as $src): ?>
                                <?php if ($src['is_active']): ?>
                                <option value="<?= $src['id'] ?>"><?= htmlspecialchars($src['name']) ?></option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-grow-1">
                            <label class="form-label small fw-semibold mb-1">Subcategory Name</label>
                            <input type="text" class="form-control form-control-sm" id="newSubName" placeholder="e.g. Facebook Ad">
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-primary" id="addSubBtn">Add</button>
                        </div>
                    </div>
                    <div id="subAddMsg" class="small mt-1"></div>
                </div>

                <!-- Subcategories listed grouped by source -->
                <ul class="list-group list-group-flush" id="subsList">
                <?php foreach ($sources as $src): ?>
                <?php $subs = $subsMap[(int)$src['id']] ?? []; ?>
                <?php if (!empty($subs)): ?>
                    <li class="list-group-item bg-light py-1">
                        <small class="fw-semibold text-muted text-uppercase" style="font-size:.7rem"><?= htmlspecialchars($src['name']) ?></small>
                    </li>
                    <?php foreach ($subs as $sub): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center ps-4" id="sub-<?= $sub['id'] ?>">
                        <span><?= htmlspecialchars($sub['name']) ?><?php if (!$sub['is_active']): ?> <span class="badge bg-secondary fw-normal">Inactive</span><?php endif; ?></span>
                        <button class="btn btn-sm <?= $sub['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?> py-0 toggle-sub"
                                data-id="<?= $sub['id'] ?>">
                            <?= $sub['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty(array_filter($subsMap))): ?>
                <li class="list-group-item text-muted small">No subcategories yet. Add one above.</li>
                <?php endif; ?>
                </ul>
            </div>
        </div>

    </div>
</div>

<!-- Roles tab (admin only) -->
<?php if ($isAdmin): ?>
<div class="tab-pane fade" id="tabRoles">
    <div class="row g-3" style="max-width:760px">
        <div class="col-12">
            <div class="card">
                <div class="card-header fw-semibold bg-white small d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person-badge me-1"></i>Custom Roles</span>
                    <small class="text-muted fw-normal">Custom roles inherit permissions from a system role</small>
                </div>
                <ul class="list-group list-group-flush" id="rolesList">
                <?php if (empty($customRoles)): ?>
                <li class="list-group-item text-muted small" id="noRolesRow">No custom roles yet. Add one below.</li>
                <?php endif; ?>
                <?php foreach ($customRoles as $cr): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center" id="role-row-<?= $cr['id'] ?>">
                    <div>
                        <span class="fw-semibold"><?= htmlspecialchars($cr['label']) ?></span>
                        <span class="badge bg-light text-secondary ms-2 fw-normal" style="font-size:.72rem">
                            <?= htmlspecialchars(str_replace('_',' ',$cr['base_role'])) ?> permissions
                        </span>
                        <?php if (!$cr['is_active']): ?>
                        <span class="badge bg-secondary ms-1 fw-normal">Inactive</span>
                        <?php endif; ?>
                        <span class="text-muted small ms-2"><?= (int)$cr['user_count'] ?> user<?= $cr['user_count'] != 1 ? 's' : '' ?></span>
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-sm <?= $cr['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?> py-0 toggle-role"
                                data-id="<?= $cr['id'] ?>">
                            <?= $cr['is_active'] ? 'Disable' : 'Enable' ?>
                        </button>
                        <?php if ((int)$cr['user_count'] === 0): ?>
                        <button class="btn btn-sm btn-outline-danger py-0 delete-role"
                                data-id="<?= $cr['id'] ?>" data-name="<?= htmlspecialchars($cr['label']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
                </ul>

                <!-- Add role form -->
                <div class="card-footer">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="newRoleLabel"
                                   placeholder="e.g. Senior Sales Rep">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold mb-1">Permission Level <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="newRoleBase">
                                <option value="">— Same permissions as… —</option>
                                <?php foreach ([
                                    'admin'             => 'Admin',
                                    'manager'           => 'Manager',
                                    'operation_manager' => 'Operation Manager',
                                    'accounts'          => 'Accounts',
                                    'sales_rep'         => 'Sales Rep',
                                    'tele_caller'       => 'Tele Caller',
                                    'installer'         => 'Installer',
                                    'dealer'            => 'Dealer',
                                    'documentation'     => 'Documentation',
                                ] as $val => $lbl): ?>
                                <option value="<?= $val ?>"><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-primary w-100" id="addRoleBtn">Add</button>
                        </div>
                    </div>
                    <div id="roleAddMsg" class="small mt-1"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- /tab-content -->

<script>
// ── Sources ───────────────────────────────────────────────────────────────────
document.querySelectorAll('.toggle-source').forEach(btn => {
    btn.addEventListener('click', async function () {
        const res = await apiFetch('/settings/sources/save', {
            body: JSON.stringify({ action: 'toggle', id: parseInt(this.dataset.id) })
        });
        if (res.ok) location.reload();
        else alert(res.error || res.message || 'Error toggling source.');
    });
});

// ── Delete / replace a lead source ────────────────────────────────────────────
let dsModal = null;
// Delegated: this script block runs before the modal markup exists further down
// the page, so binding directly to #ds-confirm would silently bind to nothing.
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.delete-source');
    if (btn) {
        const id    = parseInt(btn.dataset.id);
        const name  = btn.dataset.name;
        const leads = parseInt(btn.dataset.leads) || 0;

        document.getElementById('ds-id').value      = id;
        document.getElementById('ds-name').textContent = name;
        document.getElementById('ds-count').textContent = leads;

        const inUse = leads > 0;
        document.getElementById('ds-inuse').classList.toggle('d-none', !inUse);
        document.getElementById('ds-empty').classList.toggle('d-none', inUse);

        // Cannot move leads into the source being deleted
        const sel = document.getElementById('ds-replace');
        sel.value = '';
        Array.from(sel.options).forEach(o => {
            o.hidden   = (parseInt(o.value) === id);
            o.disabled = o.hidden;
        });

        dsModal = dsModal || new bootstrap.Modal(document.getElementById('deleteSourceModal'));
        dsModal.show();
        return;
    }

    const confirmBtn = e.target.closest('#ds-confirm');
    if (!confirmBtn) return;

    const id    = parseInt(document.getElementById('ds-id').value);
    const inUse = !document.getElementById('ds-inuse').classList.contains('d-none');
    const body  = { action: 'delete', id };

    if (inUse) {
        const rep = document.getElementById('ds-replace').value;
        if (!rep) { alert('Choose a source to move the leads to.'); return; }
        body.replace_with = parseInt(rep);
    }

    confirmBtn.disabled  = true;
    const original       = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';

    apiFetch('/settings/sources/save', { body: JSON.stringify(body) }).then(res => {
        if (res.ok) {
            location.reload();
        } else {
            alert(res.error || res.message || 'Could not delete source.');
            confirmBtn.disabled  = false;
            confirmBtn.innerHTML = original;
        }
    });
});

document.getElementById('addSourceForm').addEventListener('submit', async function (e) {
    e.preventDefault();
    const name = document.getElementById('newSourceName').value.trim();
    if (!name) return;
    const res = await apiFetch('/settings/sources/save', {
        body: JSON.stringify({ action: 'add', name })
    });
    if (res.ok) location.reload();
    else alert(res.error || res.message || 'Error adding source.');
});

// ── Subcategories ─────────────────────────────────────────────────────────────
document.querySelectorAll('.toggle-sub').forEach(btn => {
    btn.addEventListener('click', async function () {
        const res = await apiFetch('/settings/source-subs/save', {
            body: JSON.stringify({ action: 'toggle', id: parseInt(this.dataset.id) })
        });
        if (res.ok) location.reload();
        else alert(res.message || 'Error toggling subcategory.');
    });
});

const addSubBtn = document.getElementById('addSubBtn');
if (addSubBtn) {
    addSubBtn.addEventListener('click', async function () {
        const sourceId = parseInt(document.getElementById('addSubSourceId').value) || 0;
        const name     = document.getElementById('newSubName').value.trim();
        const msgEl    = document.getElementById('subAddMsg');

        msgEl.className = 'small mt-1 text-danger';
        if (!sourceId) { msgEl.textContent = 'Please select a source.'; return; }
        if (!name)     { msgEl.textContent = 'Please enter a subcategory name.'; return; }
        msgEl.textContent = '';

        this.disabled = true;
        const res = await apiFetch('/settings/source-subs/save', {
            body: JSON.stringify({ action: 'add', source_id: sourceId, name })
        });
        this.disabled = false;

        if (res.ok) {
            location.reload();
        } else {
            msgEl.textContent = res.message || 'Error adding subcategory.';
        }
    });
}

// Highlight selected source when clicking expand button
document.querySelectorAll('.expand-src-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.src-row').forEach(r => r.classList.remove('table-active'));
        this.closest('.src-row').classList.add('table-active');
        const srcId = this.dataset.srcId;
        document.getElementById('addSubSourceId').value = srcId;
    });
});

// Normalise pipe-separated settings to actual pipe before save
document.querySelectorAll('textarea[name="status_reasons"],textarea[name="lost_reasons"]').forEach(ta => {
    ta.closest('form').addEventListener('submit', function () {
        ta.value = ta.value.split('\n').map(s => s.trim()).filter(Boolean).join('|');
    });
});

// ── Roles ─────────────────────────────────────────────────────────────────────
const addRoleBtn = document.getElementById('addRoleBtn');
if (addRoleBtn) {
    addRoleBtn.addEventListener('click', async function () {
        const label    = document.getElementById('newRoleLabel').value.trim();
        const baseRole = document.getElementById('newRoleBase').value;
        const msgEl    = document.getElementById('roleAddMsg');
        msgEl.className = 'small mt-1 text-danger';
        if (!label)    { msgEl.textContent = 'Role name is required.'; return; }
        if (!baseRole) { msgEl.textContent = 'Please select a permission level.'; return; }
        msgEl.textContent = '';

        this.disabled = true;
        const res = await apiFetch('/settings/roles/save', {
            body: JSON.stringify({ action: 'add', label, base_role: baseRole })
        });
        this.disabled = false;

        if (res.ok) {
            document.getElementById('noRolesRow')?.remove();
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            li.id = 'role-row-' + res.id;
            li.innerHTML = `
                <div>
                    <span class="fw-semibold">${escHtmlSt(res.label)}</span>
                    <span class="badge bg-light text-secondary ms-2 fw-normal" style="font-size:.72rem">
                        ${escHtmlSt(res.base_role.replace(/_/g,' '))} permissions
                    </span>
                    <span class="text-muted small ms-2">0 users</span>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-warning py-0 toggle-role" data-id="${res.id}">Disable</button>
                    <button class="btn btn-sm btn-outline-danger py-0 delete-role" data-id="${res.id}" data-name="${escHtmlSt(res.label)}">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>`;
            document.getElementById('rolesList').appendChild(li);
            attachRoleButtons(li);
            document.getElementById('newRoleLabel').value = '';
            document.getElementById('newRoleBase').value  = '';
            msgEl.className = 'small mt-1 text-success';
            msgEl.textContent = 'Role "' + res.label + '" created.';
        } else {
            msgEl.textContent = res.error || 'Error creating role.';
        }
    });
}

document.querySelectorAll('.toggle-role').forEach(btn => attachRoleToggle(btn));
document.querySelectorAll('.delete-role').forEach(btn => attachRoleDelete(btn));

function attachRoleButtons(container) {
    container.querySelectorAll('.toggle-role').forEach(attachRoleToggle);
    container.querySelectorAll('.delete-role').forEach(attachRoleDelete);
}

function attachRoleToggle(btn) {
    btn.addEventListener('click', async function () {
        const res = await apiFetch('/settings/roles/save', {
            body: JSON.stringify({ action: 'toggle', id: parseInt(this.dataset.id) })
        });
        if (res.ok) location.reload();
        else alert(res.error || 'Error toggling role.');
    });
}

function attachRoleDelete(btn) {
    btn.addEventListener('click', async function () {
        if (!confirm('Delete role "' + this.dataset.name + '"?')) return;
        const res = await apiFetch('/settings/roles/save', {
            body: JSON.stringify({ action: 'delete', id: parseInt(this.dataset.id) })
        });
        if (res.ok) {
            document.getElementById('role-row-' + this.dataset.id)?.remove();
        } else {
            alert(res.error || 'Cannot delete role.');
        }
    });
}

function escHtmlSt(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Auto-open Roles tab if URL hash matches
if (window.location.hash === '#tabRoles') {
    document.getElementById('tabRolesBtn')?.click();
}
</script>

<!-- ── Delete / replace a lead source ──────────────────────────────────────── -->
<div class="modal fade" id="deleteSourceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-trash me-2"></i>Delete Lead Source</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ds-id">
        <p class="mb-3">
          Delete <strong id="ds-name"></strong>?
        </p>

        <div id="ds-inuse" class="d-none">
          <div class="alert alert-warning small py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            This source is used by <strong id="ds-count"></strong> lead(s).
            They must be moved to another source first.
          </div>
          <label class="form-label fw-semibold small" for="ds-replace">
            Move those leads to <span class="text-danger">*</span>
          </label>
          <select class="form-select form-select-sm" id="ds-replace">
            <option value="">— Select a source —</option>
            <?php foreach ($sources as $src): ?>
            <option value="<?= (int)$src['id'] ?>" data-self="<?= (int)$src['id'] ?>">
              <?= htmlspecialchars($src['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text small">
            Sub-source is cleared on the moved leads, since it belonged to the deleted source.
            Every move is written to the audit trail.
          </div>
        </div>

        <div id="ds-empty" class="alert alert-secondary small py-2 d-none mb-0">
          No leads use this source, so it can be removed safely.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-danger btn-sm" id="ds-confirm">
          <i class="bi bi-trash me-1"></i>Delete Source
        </button>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
