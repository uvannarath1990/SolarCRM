'use strict';

// Stage colour classes (must match app.css)
const STAGE_CLASSES = {
    'New':                    'badge-stage-new',
    'Contacted':              'badge-stage-contacted',
    'Visit Scheduled':        'badge-stage-visit-sched',
    'Site Visit in Progress': 'badge-stage-visit-prog',
    'Site Visit Completed':   'badge-stage-visit-done',
    'Quoted':                 'badge-stage-quoted',
    'Won':                    'badge-stage-won',
    'Lost':                   'badge-stage-lost',
    'On Hold':                'badge-stage-hold',
};

// Allowed stage transitions (mirrors server-side allowedNextStages())
const STAGE_TRANSITIONS = {
    'New':                    { admin: ['Contacted','Lost'], tele_caller: ['Contacted'], sales_rep: ['Contacted'], installer: [] },
    'Contacted':              { admin: ['Visit Scheduled','Lost','On Hold'], tele_caller: ['Visit Scheduled','Lost','On Hold'], sales_rep: ['Visit Scheduled','Lost','On Hold'], installer: [] },
    'Visit Scheduled':        { admin: ['Site Visit in Progress','Contacted','Lost','On Hold'], tele_caller: ['Contacted'], sales_rep: ['Site Visit in Progress','Lost','On Hold'], installer: [] },
    'Site Visit in Progress': { admin: ['Site Visit Completed'], tele_caller: [], sales_rep: ['Site Visit Completed'], installer: [] },
    'Site Visit Completed':   { admin: ['Quoted','On Hold','Lost'], tele_caller: [], sales_rep: ['Quoted','On Hold','Lost'], installer: [] },
    'Quoted':                 { admin: ['Won','Lost','On Hold'], tele_caller: [], sales_rep: ['Won','Lost','On Hold'], installer: [] },
    'Won':                    { admin: ['Lost'], tele_caller: [], sales_rep: [], installer: [] },
    'Lost':                   { admin: ['Contacted'], tele_caller: [], sales_rep: [], installer: [] },
    'On Hold':                { admin: ['Contacted','Visit Scheduled','Lost'], tele_caller: [], sales_rep: ['Contacted','Visit Scheduled','Lost'], installer: [] },
};

document.addEventListener('DOMContentLoaded', () => {

    // ── Stage pill inline edit ─────────────────────────────────────────────────
    document.querySelectorAll('.stage-pill').forEach(pill => {
        pill.addEventListener('click', function () {
            if (this.querySelector('select')) return; // already open

            const leadId  = this.dataset.leadId;
            const current = this.dataset.stage;
            let   role    = this.dataset.role;

            // Reps and tele callers can't switch stages from the list (server enforces this too)
            if (role === 'sales_rep' || role === 'tele_caller') return;
            // Server treats these as admin for stage moves; the table only has an 'admin' key
            if (role === 'manager' || role === 'operation_manager') role = 'admin';

            const next    = (STAGE_TRANSITIONS[current] ?? {})[role] ?? [];

            if (next.length === 0) return; // no allowed transitions for this role

            const originalHTML  = this.innerHTML;
            const originalClass = this.className;

            const select = document.createElement('select');
            select.className = 'form-select form-select-sm d-inline-block w-auto';
            select.innerHTML = `<option value="${current}">${current}</option>` +
                next.map(s => `<option value="${s}">${s}</option>`).join('');
            this.innerHTML = '';
            this.appendChild(select);

            select.focus();

            const revert = () => {
                this.innerHTML = originalHTML;
                this.className = originalClass;
            };

            select.addEventListener('blur',   () => setTimeout(revert, 150));
            select.addEventListener('keydown', e => { if (e.key === 'Escape') revert(); });

            select.addEventListener('change', async () => {
                const newStage = select.value;
                if (newStage === current) { revert(); return; }

                const pill = this;
                pill.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

                const res = await apiFetch('/leads/inline-edit', {
                    method: 'POST',
                    body: JSON.stringify({ lead_id: parseInt(leadId), field: 'stage', value: newStage }),
                });

                if (res.ok) {
                    pill.dataset.stage = newStage;
                    // Remove all old stage classes, add new one
                    Object.values(STAGE_CLASSES).forEach(c => pill.classList.remove(c));
                    pill.classList.add(STAGE_CLASSES[newStage] ?? 'badge-secondary');
                    pill.textContent = newStage;
                    showToast('success', `Stage → ${newStage}`);
                } else {
                    revert();
                    showToast('danger', res.error ?? 'Could not update stage.');
                }
            });
        });
    });

    // ── Numeric cell inline edit (quote_amount, won_value) ────────────────────
    document.querySelectorAll('.editable-num').forEach(cell => {
        cell.style.cursor = 'pointer';
        cell.title        = 'Click to edit';

        cell.addEventListener('click', function () {
            if (this.querySelector('input')) return;

            const leadId     = this.dataset.leadId;
            const field      = this.dataset.field;
            const currentVal = this.dataset.value;
            const originalHTML = this.innerHTML;

            const input = document.createElement('input');
            input.type      = 'number';
            input.min       = '0';
            input.step      = '1000';
            input.value     = currentVal;
            input.className = 'form-control form-control-sm d-inline-block';
            input.style.width = '120px';
            this.innerHTML = '';
            this.appendChild(input);
            input.focus();
            input.select();

            const revert = () => { this.innerHTML = originalHTML; };

            const save = async () => {
                const newVal = input.value.trim();
                if (newVal === currentVal) { revert(); return; }

                const res = await apiFetch('/leads/inline-edit', {
                    method: 'POST',
                    body: JSON.stringify({ lead_id: parseInt(leadId), field, value: newVal }),
                });

                if (res.ok) {
                    this.dataset.value = newVal;
                    const symbol       = '₹';
                    this.innerHTML     = newVal ? symbol + Number(newVal).toLocaleString('en-IN') : '<span class="text-muted">—</span>';
                    this.style.background = '#d4edda';
                    setTimeout(() => this.style.background = '', 800);
                } else {
                    revert();
                    showToast('danger', res.error ?? 'Could not update.');
                }
            };

            input.addEventListener('blur',   save);
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') { e.preventDefault(); save(); }
                if (e.key === 'Escape') revert();
            });
        });
    });
});
