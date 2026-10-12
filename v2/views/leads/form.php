<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-3">
    <a href="<?= BASE_URL ?>/leads" class="btn btn-sm btn-outline-secondary me-2">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <span class="fw-bold fs-5">New Lead</span>
</div>

<div class="card" style="max-width:720px">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/leads/save" novalidate>
            <?= csrfField() ?>

            <div class="row g-3">
                <!-- Customer details -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="name">Customer Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars(post('name')) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="phone">Phone <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" id="phone" name="phone"
                           value="<?= htmlspecialchars(post('phone')) ?>"
                           pattern="[0-9]{10}" maxlength="10"
                           title="Enter exactly 10 digits" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= htmlspecialchars(post('email')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="location">Location</label>
                    <input type="text" class="form-control" id="location" name="location"
                           value="<?= htmlspecialchars(post('location')) ?>"
                           list="locationSuggestions">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="source_id">Lead Source <span class="text-danger">*</span></label>
                    <?php if (hasRole('partner') && $partnerDefaultSourceId): ?>
                    <input type="hidden" name="source_id" value="<?= $partnerDefaultSourceId ?>">
                    <input type="text" class="form-control" value="Referral (Partner)" disabled>
                    <?php else: ?>
                    <select class="form-select" id="source_id" name="source_id" required>
                        <option value="">— Select source —</option>
                        <?php foreach ($sources as $src): ?>
                        <option value="<?= $src['id'] ?>" <?= post('source_id') == $src['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($src['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="col-md-6" id="source_sub_wrap" style="display:none">
                    <label class="form-label fw-semibold" for="source_sub_id">Source Subcategory</label>
                    <select class="form-select" id="source_sub_id" name="source_sub_id">
                        <option value="">— Select subcategory —</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="is_new_construction"
                               name="is_new_construction" value="1"
                               <?= post('is_new_construction') ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="is_new_construction">
                            New Construction
                        </label>
                        <div class="form-text">Check if this lead is for a new building/construction project.</div>
                    </div>
                </div>

                <!-- Assignment & scheduling -->
                <?php if (canAssignPartner()): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="partner_id">Referred by Partner</label>
                    <select class="form-select" id="partner_id" name="partner_id" <?= empty($partners) ? 'disabled' : '' ?>>
                        <option value="">— None —</option>
                        <?php foreach ($partners as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= post('partner_id') == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($partners)): ?>
                    <div class="form-text text-muted"><a href="<?= BASE_URL ?>/users/create" target="_blank">Add a partner user</a> to enable this field.</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if (canAssignTeleCaller()): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="tele_caller_id">Assign Tele Caller</label>
                    <select class="form-select" id="tele_caller_id" name="tele_caller_id">
                        <option value="">— None —</option>
                        <?php foreach ($teleCallers as $tc): ?>
                        <option value="<?= $tc['id'] ?>" <?= post('tele_caller_id') == $tc['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tc['name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $tc['role'])) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if (canAssignRep()): ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="assigned_rep_id">Assign Sales Rep</label>
                    <select class="form-select" id="assigned_rep_id" name="assigned_rep_id">
                        <option value="">— None —</option>
                        <?php foreach ($reps as $r): ?>
                        <option value="<?= $r['id'] ?>" <?= post('assigned_rep_id') == $r['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($r['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="visit_scheduled_date">Site Visit Date</label>
                    <input type="date" class="form-control" id="visit_scheduled_date" name="visit_scheduled_date"
                           value="<?= htmlspecialchars(post('visit_scheduled_date')) ?>">
                    <div class="form-text">If set, a task is created automatically.</div>
                </div>

                <!-- Consumption & size -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="bill_amount">Monthly Bill (₹)</label>
                    <input type="number" class="form-control" id="bill_amount" name="bill_amount"
                           min="0" step="100" value="<?= htmlspecialchars(post('bill_amount')) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="potential_kw">
                        Potential kW
                        <span class="text-muted fw-normal small">(auto-calc)</span>
                    </label>
                    <input type="number" class="form-control" id="potential_kw" name="potential_kw"
                           min="0" step="0.1" value="<?= htmlspecialchars(post('potential_kw')) ?>">
                </div>
            </div>

            <div id="formErrors" class="alert alert-danger d-none mt-3 mb-0" role="alert"></div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <span id="submitSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                    Create Lead
                </button>
                <a href="<?= BASE_URL ?>/leads" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// ── Client-side validation ────────────────────────────────────────────────────
document.querySelector('form').addEventListener('submit', function (e) {
    const errors = [];

    const name    = document.getElementById('name').value.trim();
    const phone   = document.getElementById('phone').value.trim().replace(/\D/g, '');
    const sourceEl2 = document.getElementById('source_id');
    const source  = sourceEl2 ? sourceEl2.value : '1'; // hidden for partners — always valid
    const email   = document.getElementById('email').value.trim();
    const bill    = document.getElementById('bill_amount').value;
    const kw      = document.getElementById('potential_kw').value;

    if (name === '') {
        errors.push('Customer Name is required.');
        document.getElementById('name').classList.add('is-invalid');
    } else {
        document.getElementById('name').classList.remove('is-invalid');
    }

    if (phone === '') {
        errors.push('Phone number is required.');
        document.getElementById('phone').classList.add('is-invalid');
    } else if (!/^\d{10}$/.test(phone)) {
        errors.push('Phone number must be exactly 10 digits.');
        document.getElementById('phone').classList.add('is-invalid');
    } else {
        document.getElementById('phone').classList.remove('is-invalid');
    }

    if (sourceEl2 && source === '') {
        errors.push('Please select a Lead Source.');
        sourceEl2.classList.add('is-invalid');
    } else if (sourceEl2) {
        sourceEl2.classList.remove('is-invalid');
    }

    if (email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Email address is not valid.');
        document.getElementById('email').classList.add('is-invalid');
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }

    if (bill !== '' && (isNaN(parseFloat(bill)) || parseFloat(bill) < 0)) {
        errors.push('Monthly Bill must be a positive number.');
        document.getElementById('bill_amount').classList.add('is-invalid');
    } else {
        document.getElementById('bill_amount').classList.remove('is-invalid');
    }

    if (kw !== '' && (isNaN(parseFloat(kw)) || parseFloat(kw) < 0)) {
        errors.push('Potential kW must be a positive number.');
        document.getElementById('potential_kw').classList.add('is-invalid');
    } else {
        document.getElementById('potential_kw').classList.remove('is-invalid');
    }

    const errBox = document.getElementById('formErrors');
    if (errors.length > 0) {
        e.preventDefault();
        errBox.innerHTML = errors.map(m => `<div>• ${m}</div>`).join('');
        errBox.classList.remove('d-none');
        errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }

    errBox.classList.add('d-none');
    // Show spinner on submit
    document.getElementById('submitBtn').disabled = true;
    document.getElementById('submitSpinner').classList.remove('d-none');
});

// Clear invalid state on input
['name','phone','email','source_id','bill_amount','potential_kw'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', function() { this.classList.remove('is-invalid'); });
    if (el) el.addEventListener('input',  function() { this.classList.remove('is-invalid'); });
});

// Auto-calculate kW when bill changes
document.getElementById('bill_amount').addEventListener('input', function () {
    const divisor = <?= (float) getSetting('bill_to_kw_divisor', '1200') ?>;
    const bill    = parseFloat(this.value);
    const kwEl    = document.getElementById('potential_kw');
    if (!isNaN(bill) && bill > 0 && divisor > 0 && kwEl.value === '') {
        kwEl.value = Math.round((bill / divisor) * 10) / 10;
    }
});

// Dynamic subcategory dropdown
const sourceSubs = <?= json_encode((object)($sourceSubs ?? [])) ?>;
const sourceEl   = document.getElementById('source_id');
const subWrap    = document.getElementById('source_sub_wrap');
const subEl      = document.getElementById('source_sub_id');

function updateSubs() {
    const srcId = sourceEl.value; // keep as string — JS object keys are strings
    const subs  = sourceSubs[srcId] || [];
    subEl.innerHTML = '<option value="">— Select subcategory —</option>';
    if (subs.length === 0) {
        subWrap.style.display = 'none';
        return;
    }
    subs.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name;
        subEl.appendChild(opt);
    });
    subWrap.style.display = '';
}

sourceEl.addEventListener('change', updateSubs);
updateSubs(); // run on load in case of back-navigation
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
