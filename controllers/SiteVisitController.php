<?php
declare(strict_types=1);

class SiteVisitController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function view(): void
    {
        requireRole('admin', 'sales_rep');

        $leadId = getInt('lead_id');
        $lead   = $this->fetchLead($leadId);
        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/leads');
        }

        // Check active visit for this lead
        $activeVisit = $this->fetchActiveVisit($leadId);

        $pageTitle = 'Site Visit — ' . $lead['name'];
        $extraJs   = ['site-visit.js'];
        require __DIR__ . '/../views/site_visits/visit.php';
    }

    public function checkin(): void
    {
        requireRole('admin', 'sales_rep');
        csrfVerify();

        $leadId   = postInt('lead_id');
        $lat      = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng      = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
        $accuracy = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (float) $_POST['accuracy'] : null;
        $provided = ($lat !== null && $lng !== null) ? 1 : 0;

        $lead = $this->fetchLead($leadId);
        if (!$lead) {
            jsonError('Lead not found.', 404);
        }

        // Ownership check: sales_rep can only check in on their assigned lead
        $isAdmin = in_array($_SESSION['role'], ['admin', 'manager'], true);
        if (!$isAdmin && (int)$lead['assigned_rep_id'] !== (int)$_SESSION['user_id']) {
            jsonError('Access denied.', 403);
        }

        // Check for an already-open visit
        $existing = $this->fetchActiveVisit($leadId);
        if ($existing) {
            jsonResponse(['ok' => true, 'visit_id' => $existing['id'], 'resumed' => true]);
        }

        // Create site_visit row
        $stmt = $this->pdo->prepare(
            'INSERT INTO `site_visits`
                (`lead_id`,`rep_id`,`checkin_at`,`checkin_lat`,`checkin_lng`,`checkin_accuracy_m`,`location_provided`)
             VALUES (?,?,NOW(),?,?,?,?)'
        );
        $stmt->execute([$leadId, $_SESSION['user_id'], $lat, $lng, $accuracy, $provided]);
        $visitId = (int) $this->pdo->lastInsertId();

        // Update lead stage
        $this->pdo->prepare(
            "UPDATE `leads` SET `stage`='Site Visit in Progress', `updated_at`=NOW() WHERE `id`=?"
        )->execute([$leadId]);

        auditSiteVisit(
            $leadId,
            'VISIT_CHECKIN',
            $provided
                ? "Check-in at {$lat},{$lng} ±{$accuracy}m"
                : 'Check-in (location not provided)'
        );
        auditStageChange($leadId, $lead['stage'], 'Site Visit in Progress');

        jsonResponse(['ok' => true, 'visit_id' => $visitId]);
    }

    public function checkout(): void
    {
        requireRole('admin', 'sales_rep');
        csrfVerify();

        $visitId     = postInt('visit_id');
        $lat         = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng         = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;
        $accuracy    = isset($_POST['accuracy']) && $_POST['accuracy'] !== '' ? (float) $_POST['accuracy'] : null;
        $voiceNotes  = trim(post('voice_notes'));
        $roofType    = trim(post('roof_type'));
        $roofArea    = post('roof_area_sqft') !== '' ? (float) post('roof_area_sqft') : null;
        $shadingNotes = trim(post('shading_notes'));

        $stmt = $this->pdo->prepare(
            'SELECT sv.*, l.`stage` AS lead_stage
             FROM `site_visits` sv
             JOIN `leads` l ON l.`id` = sv.`lead_id`
             WHERE sv.`id` = ? LIMIT 1'
        );
        $stmt->execute([$visitId]);
        $visit = $stmt->fetch();

        if (!$visit || $visit['checkout_at'] !== null) {
            jsonError('Visit not found or already completed.', 400);
        }

        // Ownership check: sales_rep can only check out their own visit
        $isAdmin = in_array($_SESSION['role'], ['admin', 'manager'], true);
        if (!$isAdmin && (int)$visit['rep_id'] !== (int)$_SESSION['user_id']) {
            jsonError('Access denied.', 403);
        }

        // Compute duration
        $duration = $visit['checkin_at']
            ? (int) (time() - strtotime($visit['checkin_at']))
            : null;

        $this->pdo->prepare(
            'UPDATE `site_visits`
             SET `checkout_at`=NOW(),
                 `checkout_lat`=?,`checkout_lng`=?,`checkout_accuracy_m`=?,
                 `duration_secs`=?,
                 `voice_notes`=?,`roof_type`=?,`roof_area_sqft`=?,`shading_notes`=?
             WHERE `id`=?'
        )->execute([
            $lat, $lng, $accuracy, $duration,
            $voiceNotes ?: null,
            $roofType ?: null,
            $roofArea,
            $shadingNotes ?: null,
            $visitId,
        ]);

        $leadId = (int) $visit['lead_id'];
        $this->pdo->prepare(
            "UPDATE `leads` SET `stage`='Site Visit Completed', `updated_at`=NOW() WHERE `id`=?"
        )->execute([$leadId]);

        auditSiteVisit(
            $leadId,
            'VISIT_CHECKOUT',
            "Check-out" . ($lat ? " at {$lat},{$lng} ±{$accuracy}m" : ' (no GPS)') .
            " · Duration: " . formatDuration($duration)
        );
        auditStageChange($leadId, 'Site Visit in Progress', 'Site Visit Completed');

        // Auto-complete the Visit Scheduled workflow task (visit is now physically done)
        $this->pdo->prepare(
            "UPDATE `workflow_tasks`
             SET `status`='done', `completed_by`=?, `completed_at`=NOW()
             WHERE `lead_id`=? AND `stage`='Visit Scheduled' AND `status`='pending'"
        )->execute([(int) $_SESSION['user_id'], $leadId]);

        // Create tasks for Site Visit Completed stage
        createWorkflowTasks($leadId, 'Site Visit Completed', $this->pdo);

        jsonResponse(['ok' => true, 'duration_secs' => $duration]);
    }

    private function fetchLead(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT `id`,`name`,`phone`,`location`,`stage`,`assigned_rep_id`,`potential_kw`
             FROM `leads` WHERE `id`=? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function fetchActiveVisit(int $leadId): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `site_visits`
             WHERE `lead_id`=? AND `checkout_at` IS NULL
             ORDER BY `checkin_at` DESC LIMIT 1'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetch();
    }
}
