<?php require __DIR__ . '/../layout/header.php'; ?>

<div class="mt-3 mb-2 d-flex align-items-center gap-2">
    <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h5 class="mb-0 fw-bold">Site Visit — <?= htmlspecialchars($lead['name']) ?></h5>
</div>

<p class="text-muted small mb-3">
    <i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($lead['location'] ?? 'Location not set') ?>
    <?php if ($lead['potential_kw']): ?>
    · <strong><?= htmlspecialchars($lead['potential_kw']) ?> kW</strong> estimated
    <?php endif; ?>
</p>

<div id="visitApp"
     data-lead-id="<?= $lead['id'] ?>"
     data-visit-id="<?= $activeVisit ? $activeVisit['id'] : '' ?>"
     data-checkin-time="<?= $activeVisit ? $activeVisit['checkin_at'] : '' ?>">

    <!-- ── State 1: Not started ────────────────────────────────────────────── -->
    <?php if (!$activeVisit): ?>
    <div id="stateNotStarted" class="text-center py-5">
        <i class="bi bi-geo-alt display-1 text-primary mb-3 d-block"></i>
        <p class="mb-4 text-muted">Tap the button below to record your arrival at the customer's site.<br>
        Your GPS location will be captured.</p>
        <button id="btnCheckin" class="btn btn-primary btn-lg px-5">
            <i class="bi bi-play-circle me-2"></i>Start Site Visit
        </button>
        <p class="mt-3 small text-muted">The browser will request location permission — please allow it.</p>
    </div>

    <!-- ── State 2: Visit in progress ─────────────────────────────────────── -->
    <?php else: ?>
    <div id="stateInProgress">
    <?php endif; ?>

    <div id="stateInProgressPanel" <?= !$activeVisit ? 'class="d-none"' : '' ?>>
        <div class="card mb-3">
            <div class="card-body text-center">
                <p class="text-muted mb-1 small">Visit in progress</p>
                <div id="visitTimer" class="display-4 fw-bold text-primary font-monospace">00:00:00</div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Observations</h6>

                <div class="mb-3">
                    <label class="form-label fw-semibold small">Site / Visit Notes</label>
                    <div class="position-relative">
                        <textarea id="voiceNotesTA" class="form-control" rows="4"
                                  placeholder="Notes from the site visit…"></textarea>
                        <textarea id="voiceNotesSrc" class="d-none"></textarea>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Roof Type</label>
                        <select class="form-select form-select-sm" id="roofType">
                            <option value="">— Select —</option>
                            <option>RCC Flat</option>
                            <option>Mangalore Tile</option>
                            <option>Metal Sheet</option>
                            <option>Asbestos Sheet</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Roof Area (sq ft)</label>
                        <input type="number" class="form-control form-control-sm" id="roofArea" min="0" step="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Shading Issues</label>
                        <div class="position-relative">
                            <input type="text" class="form-control form-control-sm" id="shadingNotes"
                                   placeholder="e.g. afternoon shadow from tree"
                                   >
                            <input id="shadingNotesSrc" class="d-none">
                        </div>
                    </div>
                </div>

                <button id="btnCheckout" class="btn btn-success btn-lg w-100">
                    <i class="bi bi-stop-circle me-2"></i>Complete Site Visit
                </button>
            </div>
        </div>
    </div>

    <!-- ── State 3: Summary after checkout ────────────────────────────────── -->
    <div id="stateSummary" class="d-none">
        <div class="card border-success">
            <div class="card-body">
                <h5 class="text-success"><i class="bi bi-check-circle-fill me-2"></i>Visit Completed</h5>
                <div class="row g-2 mt-2 small">
                    <div class="col-6"><span class="text-muted">Duration</span><br><strong id="summDuration">—</strong></div>
                    <div class="col-6"><span class="text-muted">Check-in GPS</span><br>
                        <a id="summCheckinMap" href="#" target="_blank" class="text-decoration-none">View on map <i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                    <div class="col-6"><span class="text-muted">Check-out GPS</span><br>
                        <a id="summCheckoutMap" href="#" target="_blank" class="text-decoration-none">View on map <i class="bi bi-box-arrow-up-right"></i></a>
                    </div>
                </div>
                <a href="<?= BASE_URL ?>/leads/detail?id=<?= $lead['id'] ?>" class="btn btn-primary mt-3">
                    Back to Lead
                </a>
            </div>
        </div>
    </div>

</div><!-- /visitApp -->

<?php require __DIR__ . '/../layout/footer.php'; ?>
