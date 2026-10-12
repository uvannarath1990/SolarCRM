<?php
$pageSub  = 'Admin only';
$backLink = BASE_URL . '/users';
require __DIR__ . '/../layout/header.php';

$dayNames = [1 => 'M', 2 => 'T', 3 => 'W', 4 => 'T', 5 => 'F', 6 => 'S', 7 => 'S'];
$dayFull  = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
             5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$onCount  = count(array_filter($users, fn($u) => (int) $u['track_location'] === 1));
?>

<div class="v2-flash v2-flash-info" style="margin-top:14px">
    <i class="bi bi-info-circle-fill"></i>
    <div>
        Any user can be tracked, whatever their role. Positions are only recorded inside
        the hours and days set here — anything outside is discarded and never stored.
        Tell your staff in writing that this is on.
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/users/tracking/save">
    <?= csrfField() ?>

    <div class="v2-section">
        <h2><?= $onCount ?> of <?= count($users) ?> tracked</h2>
        <span class="rule"></span>
    </div>

    <div class="v2-card" style="overflow:hidden">
    <?php foreach ($users as $i => $u):
        $id  = (int) $u['id'];
        $on  = (int) $u['track_location'] === 1;
        $sel = array_filter(explode(',', (string) $u['work_days']));
    ?>
        <div class="trk-row" style="padding:14px 16px;<?= $i ? 'border-top:1px solid var(--line)' : '' ?>">
            <input type="hidden" name="user_id[]" value="<?= $id ?>">

            <div style="display:flex;align-items:center;gap:12px">
                <div class="min-w-0" style="flex:1">
                    <div style="font-weight:700;font-size:15px"><?= htmlspecialchars($u['name']) ?></div>
                    <div class="text-muted-2" style="font-size:12.5px">
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))) ?>
                    </div>
                </div>

                <div class="form-check form-switch" style="margin:0">
                    <input class="form-check-input trk-toggle" type="checkbox" role="switch"
                           name="track[]" value="<?= $id ?>" id="trk<?= $id ?>"
                           data-row="<?= $id ?>" <?= $on ? 'checked' : '' ?>
                           style="width:44px;height:24px;cursor:pointer">
                    <label class="visually-hidden" for="trk<?= $id ?>">Track <?= htmlspecialchars($u['name']) ?></label>
                </div>
            </div>

            <!-- Hours stay editable even when tracking is off, so they're ready
                 the moment it's switched on. They just read as inactive. -->
            <div class="trk-hours" data-row="<?= $id ?>"
                 style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;<?= $on ? '' : 'opacity:.45' ?>">

                <div style="flex:0 0 108px">
                    <label class="trk-lbl" for="ws<?= $id ?>">Starts</label>
                    <input type="time" class="v2-input" id="ws<?= $id ?>"
                           name="work_start[<?= $id ?>]"
                           value="<?= htmlspecialchars(substr((string) $u['work_start'], 0, 5)) ?>"
                           style="padding:8px 10px;min-height:42px;font-size:14px">
                </div>

                <div style="flex:0 0 108px">
                    <label class="trk-lbl" for="we<?= $id ?>">Ends</label>
                    <input type="time" class="v2-input" id="we<?= $id ?>"
                           name="work_end[<?= $id ?>]"
                           value="<?= htmlspecialchars(substr((string) $u['work_end'], 0, 5)) ?>"
                           style="padding:8px 10px;min-height:42px;font-size:14px">
                </div>

                <div style="flex:1 1 200px;min-width:0">
                    <span class="trk-lbl">Working days</span>
                    <div style="display:flex;gap:4px;flex-wrap:wrap">
                    <?php foreach ($dayNames as $n => $short): ?>
                        <input type="checkbox" class="btn-check" id="d<?= $id ?>_<?= $n ?>"
                               name="work_days[<?= $id ?>][]" value="<?= $n ?>"
                               <?= in_array((string) $n, $sel, true) ? 'checked' : '' ?>>
                        <label class="btn btn-sm btn-outline-secondary" for="d<?= $id ?>_<?= $n ?>"
                               title="<?= $dayFull[$n] ?>"
                               style="min-width:34px;border-radius:8px"><?= $short ?></label>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="v2-commit">
        <button type="submit" class="v2-btn v2-btn-primary">
            <i class="bi bi-check-lg"></i>Save all
        </button>
    </div>
</form>

<style>
.trk-lbl {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--muted);
    margin-bottom: 5px;
}
.form-check-input:checked { background-color: var(--ok); border-color: var(--ok); }
</style>

<script>
(function () {
    // Dim a row's hours when tracking is off — the controls stay usable, they
    // just stop looking like they're doing anything.
    document.querySelectorAll('.trk-toggle').forEach(function (t) {
        t.addEventListener('change', function () {
            var row = document.querySelector('.trk-hours[data-row="' + t.dataset.row + '"]');
            if (row) row.style.opacity = t.checked ? '' : '.45';
        });
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
