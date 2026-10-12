<?php
declare(strict_types=1);

class LeadDetailController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireLogin();

        $leadId = getInt('id');
        $lead   = $this->fetchLead($leadId);
        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/leads');
        }

        $this->checkAccess($lead);

        // Partners see a read-only summary — no internal data
        if ($_SESSION['role'] === 'partner') {
            $pageTitle = $lead['name'];
            require __DIR__ . '/../views/partner/lead_detail.php';
            return;
        }

        $sources      = $this->pdo->query(
            "SELECT `id`,`name` FROM `lead_sources` WHERE `is_active`=1 ORDER BY `sort_order`"
        )->fetchAll();
        $reps         = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $activities   = $this->fetchActivities($leadId);
        $reminders    = $this->fetchReminders($leadId);
        $siteVisits        = $this->fetchSiteVisits($leadId);
        $siteVisitHistory  = $this->fetchSiteVisitHistory($leadId);
        $auditLog     = $this->fetchAuditLog($leadId);
        $operations   = $this->fetchOperations($leadId);
        $financials   = $this->fetchFinancials($leadId);
        $installments = $this->fetchInstallments($leadId);
        $teams        = $this->pdo->query(
            "SELECT `id`,`name` FROM `teams` WHERE `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $teleCallers  = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `role` IN ('tele_caller','sales_rep') AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $statusReasons = array_filter(array_map('trim', explode('|', getSetting('status_reasons', ''))));

        // Workflow pipeline tasks for this lead
        $workflowTasks = $this->pdo->prepare(
            "SELECT wt.*, u.`name` AS assignee_name
             FROM `workflow_tasks` wt
             LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
             WHERE wt.`lead_id`=?
             ORDER BY FIELD(wt.`status`,'pending','done','skipped'), wt.`created_at` ASC"
        );
        $workflowTasks->execute([$leadId]);
        $workflowTasks = $workflowTasks->fetchAll();

        // All active users for the assign dropdown (admin use)
        $allUsers = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        // Role-filtered user lists for the manager action panel
        $teleCallerUsers   = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='tele_caller' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $salesRepUsers     = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $documentationUsers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        // Uploaded files. Shown on this page too, because the sales rep with the
        // customer in front of them is often the one who can photograph a
        // document — not only the documentation team.
        $docs = $this->pdo->prepare(
            "SELECT ld.*, u.`name` AS uploader_name
               FROM `lead_documents` ld
               LEFT JOIN `users` u ON u.`id` = ld.`uploaded_by`
              WHERE ld.`lead_id` = ? AND ld.`deleted_at` IS NULL
              ORDER BY ld.`created_at` DESC"
        );
        $docs->execute([$leadId]);
        $documents = $docs->fetchAll();

        $pageTitle = $lead['name'];
        $extraJs   = ['lead-detail.js', 'doc-upload.js'];
        require __DIR__ . '/../views/leads/detail.php';
    }

    public function save(): void
    {
        requireLogin();
        csrfVerify();

        $leadId = postInt('lead_id');
        $lead   = $this->fetchLead($leadId);
        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/leads');
        }
        $this->checkAccess($lead);

        // Documentation is read-only on the lead record itself — their work is
        // done through workflow tasks, not by editing lead fields.
        if ($_SESSION['role'] === 'documentation') {
            flash('danger', 'Documentation users cannot edit lead details.');
            redirect('/leads/detail?id=' . $leadId);
        }

        $fields = [
            'name'                  => trim(post('name')),
            'phone'                 => trim(post('phone')),
            'email'                 => trim(post('email')) ?: null,
            'location'              => trim(post('location')) ?: null,
            'source_id'             => postInt('source_id') ?: null,
            'bill_amount'           => post('bill_amount') !== '' ? (float) post('bill_amount') : null,
            'potential_kw'          => post('potential_kw') !== '' ? (float) post('potential_kw') : null,
            'assigned_rep_id'       => in_array($_SESSION['role'], ['admin','manager'], true)
                                         ? (postInt('assigned_rep_id') ?: null)
                                         : ($lead['assigned_rep_id'] ?? null), // non-managers cannot change
            'tele_caller_id'        => null, // validated below
            'documentation_user_id' => null, // validated below
            'visit_scheduled_date'  => post('visit_scheduled_date') ?: null,
            'quote_amount'          => post('quote_amount') !== '' ? (float) post('quote_amount') : null,
            'won_value'             => post('won_value') !== '' ? (float) post('won_value') : null,
            'bank_process'          => in_array(post('bank_process'), ['', 'Pending', 'Complete', 'Blocked'], true) ? post('bank_process') : '',
            'kseb_process'          => in_array(post('kseb_process'), ['', 'Pending', 'Complete', 'Blocked'], true) ? post('kseb_process') : '',
            'kseb_completed_date'   => null, // set below based on kseb_process
            'installation_date'     => post('installation_date') ?: null,
            'status_reason_code'    => trim(post('status_reason_code')) ?: null,
            'status_reason_notes'   => trim(post('status_reason_notes')) ?: null,
        ];

        $isManagerOrAdmin = in_array($_SESSION['role'], ['admin', 'manager'], true);

        // Validate telecaller: only admin/manager may change; others keep existing value
        if ($isManagerOrAdmin) {
            $teleCallerId = postInt('tele_caller_id') ?: null;
            if ($teleCallerId !== null) {
                $chk = getPdo()->prepare(
                    "SELECT `id` FROM `users` WHERE `id`=? AND `role` IN ('tele_caller','sales_rep') AND `is_active`=1 LIMIT 1"
                );
                $chk->execute([$teleCallerId]);
                $teleCallerId = $chk->fetch() ? $teleCallerId : null;
            }
            $fields['tele_caller_id'] = $teleCallerId;
        } else {
            $fields['tele_caller_id'] = $lead['tele_caller_id'] ?? null;
        }

        // Validate documentation user: only admin/manager may change; others keep existing value
        if ($isManagerOrAdmin) {
            $docUserId = postInt('documentation_user_id') ?: null;
            if ($docUserId !== null) {
                $chk = $this->pdo->prepare(
                    "SELECT `id` FROM `users` WHERE `id`=? AND `role`='documentation' AND `is_active`=1 LIMIT 1"
                );
                $chk->execute([$docUserId]);
                $docUserId = $chk->fetch() ? $docUserId : null;
            }
            $fields['documentation_user_id'] = $docUserId;
        } else {
            $fields['documentation_user_id'] = $lead['documentation_user_id'] ?? null;
        }

        // Auto-populate KSEB completed date when status set to Complete
        if ($fields['kseb_process'] === 'Complete') {
            // Keep existing date if already set, otherwise stamp today
            $fields['kseb_completed_date'] = $lead['kseb_completed_date'] ?? date('Y-m-d');
        } else {
            // Clear the date if status is no longer Complete
            $fields['kseb_completed_date'] = null;
        }

        // Auto-calc kW if bill changed but kW not manually set
        if ($fields['bill_amount'] !== null && $fields['potential_kw'] === null) {
            $fields['potential_kw'] = kwFromBill($fields['bill_amount']);
        }

        // If the current user cannot see the real phone (sales_rep, not revealed),
        // preserve the existing phone — the form submitted a masked placeholder.
        if (!canSeePhone($lead)) {
            $fields['phone'] = $lead['phone']; // keep unchanged
        } else {
            $fields['phone'] = preg_replace('/\D/', '', $fields['phone']); // strip non-digits
        }

        if ($fields['phone'] === '') {
            flash('danger', 'Phone number is required.');
            redirect('/leads/detail?id=' . $leadId);
        }

        if (strlen($fields['phone']) !== 10) {
            flash('danger', 'Phone number must be exactly 10 digits.');
            redirect('/leads/detail?id=' . $leadId);
        }

        // Phone uniqueness check (only if phone is changing)
        if ($fields['phone'] !== ($lead['phone'] ?? '')) {
            $dup = $this->pdo->prepare('SELECT `id` FROM `leads` WHERE `phone` = ? AND `id` != ? LIMIT 1');
            $dup->execute([$fields['phone'], $leadId]);
            if ($dup->fetch()) {
                flash('danger', 'That phone number is already in use by another lead.');
                redirect('/leads/detail?id=' . $leadId);
            }
        }

        $prevRepId = (int) ($lead['assigned_rep_id'] ?? 0);

        foreach ($fields as $col => $newVal) {
            $old = $lead[$col] ?? null;
            if ((string)$old !== (string)($newVal ?? '')) {
                auditUpdate($leadId, $col, $old, $newVal);
            }
        }

        $setClauses = implode(', ', array_map(fn($k) => "`{$k}`=?", array_keys($fields)));
        $stmt       = $this->pdo->prepare(
            "UPDATE `leads` SET {$setClauses}, `updated_at`=NOW() WHERE `id`=?"
        );
        $stmt->execute([...array_values($fields), $leadId]);

        $newRepId = (int) ($fields['assigned_rep_id'] ?? 0);
        if ($newRepId && $newRepId !== $prevRepId) {
            sendPushToUser($newRepId, 'Lead Assigned to You', $lead['name'],
                BASE_URL . '/leads/detail?id=' . $leadId);
        }

        // When installation_date is newly set or changed, create "Submit MNRE Document" task
        $prevInstDate = $lead['installation_date'] ?? null;
        $newInstDate  = $fields['installation_date'] ?? null;
        if ($newInstDate && $newInstDate !== $prevInstDate) {
            // Resolve operation_manager assignee
            $omRow = $this->pdo->query(
                "SELECT `id` FROM `users` WHERE `role`='operation_manager' AND `is_active`=1 ORDER BY `id` LIMIT 1"
            )->fetch();
            $omId = $omRow ? (int) $omRow['id'] : null;

            // Cancel any previous pending MNRE task so there's no duplicate
            $this->pdo->prepare(
                "UPDATE `workflow_tasks`
                 SET `status`='cancelled'
                 WHERE `lead_id`=? AND `title`='Submit MNRE Document' AND `status`='pending'"
            )->execute([$leadId]);

            $this->pdo->prepare(
                "INSERT INTO `workflow_tasks`
                    (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $leadId,
                $lead['stage'],
                'Submit MNRE Document',
                $omId,
                'operation_manager',
                $newInstDate,
            ]);
            auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId,
                            'Submit MNRE Document', 'installation date set');

            if ($omId) {
                sendPushToUser($omId, 'MNRE Document Task', 'Submit MNRE Document for ' . $lead['name'],
                    BASE_URL . '/leads/detail?id=' . $leadId);
            }
        }

        flash('success', 'Lead updated.');
        redirect('/leads/detail?id=' . $leadId);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function fetchLead(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.*,
                    u.`name`  AS rep_name,
                    c.`name`  AS creator_name,
                    ls.`name` AS source_name
             FROM `leads` l
             LEFT JOIN `users`        u  ON u.`id`  = l.`assigned_rep_id`
             LEFT JOIN `users`        c  ON c.`id`  = l.`created_by`
             LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
             WHERE l.`id` = ? AND l.`deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function checkAccess(array $lead): void
    {
        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];
        if (in_array($role, ['admin', 'manager', 'operation_manager', 'accounts'], true)) {
            return;
        }
        // The creator can always open their own lead
        if ((int) ($lead['created_by'] ?? 0) === $userId) {
            return;
        }
        if ($role === 'documentation') {
            // The Head may open anything in the documentation population; other doc
            // users only their own leads. Either way, not the whole lead base.
            $allowed = isDocumentationHead()
                ? (!empty($lead['site_visit_completed_date']) && $lead['stage'] !== 'Lost')
                : documentationCanAccessLead($this->pdo, (int) $lead['id'], $userId);

            if (!$allowed) {
                http_response_code(403);
                die('Access denied.');
            }
        }
        if ($role === 'tele_caller'
            && (int) $lead['created_by'] !== $userId
            && (int) ($lead['tele_caller_id'] ?? 0) !== $userId) {
            http_response_code(403);
            die('Access denied.');
        }
        if ($role === 'sales_rep' && (int) $lead['assigned_rep_id'] !== $userId) {
            http_response_code(403);
            die('Access denied.');
        }
        if ($role === 'installer' && $lead['stage'] !== 'Won') {
            http_response_code(403);
            die('Access denied.');
        }
        if ($role === 'partner' && (int) ($lead['partner_id'] ?? 0) !== $userId) {
            http_response_code(403);
            die('Access denied.');
        }
    }

    private function fetchActivities(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, u.`name` AS user_name
             FROM `activities` a
             JOIN `users` u ON u.`id` = a.`user_id`
             WHERE a.`lead_id` = ?
             ORDER BY a.`occurred_at` DESC
             LIMIT 100'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    private function fetchReminders(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.`name` AS user_name
             FROM `reminders` r
             JOIN `users` u ON u.`id` = r.`user_id`
             WHERE r.`lead_id` = ?
             ORDER BY r.`remind_at` ASC
             LIMIT 50'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    private function fetchSiteVisits(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sv.*, u.`name` AS rep_name
             FROM `site_visits` sv
             JOIN `users` u ON u.`id` = sv.`rep_id`
             WHERE sv.`lead_id` = ?
             ORDER BY sv.`checkin_at` DESC
             LIMIT 20'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    private function fetchSiteVisitHistory(int $leadId): array
    {
        // Outcome-based events from workflow tasks
        $stmt = $this->pdo->prepare(
            "SELECT wt.`outcome`, wt.`outcome_site_visit_date`, wt.`outcome_next_visit_date`,
                    wt.`outcome_lost_reason`, wt.`completed_at`, wt.`assigned_role`,
                    u.`name` AS actor_name
             FROM `workflow_tasks` wt
             LEFT JOIN `users` u ON u.`id` = wt.`completed_by`
             WHERE wt.`lead_id` = ?
               AND wt.`outcome` IN (
                   'Arranged Site Visit','Site Visit Pending','Site Visit Postponed',
                   'Site Visit Rescheduled','Site Visit Completed','Lead Won','Lead Lost'
               )
             ORDER BY wt.`completed_at` ASC"
        );
        $stmt->execute([$leadId]);
        $outcomeRows = $stmt->fetchAll();

        // Audit log: any time visit_scheduled_date or site_visit_completed_date changed
        $stmt3 = $this->pdo->prepare(
            "SELECT al.`field_name`, al.`old_value`, al.`new_value`, al.`created_at`,
                    u.`name` AS actor_name
             FROM `audit_log` al
             LEFT JOIN `users` u ON u.`id` = al.`user_id`
             WHERE al.`lead_id` = ?
               AND al.`field_name` IN ('visit_scheduled_date','next_site_visit_date','site_visit_completed_date')
             ORDER BY al.`created_at` ASC"
        );
        $stmt3->execute([$leadId]);
        foreach ($stmt3->fetchAll() as $r) {
            $field = $r['field_name'];
            $newVal = $r['new_value'];
            $oldVal = $r['old_value'];
            $formatted = $newVal ? date('d M Y', strtotime($newVal)) : '—';
            $oldFormatted = $oldVal ? date('d M Y', strtotime($oldVal)) : null;

            [$icon, $color, $label, $detail] = match($field) {
                'visit_scheduled_date' => [
                    'bi-calendar-plus-fill', 'success',
                    $oldVal ? 'Site Visit Date Changed' : 'Site Visit Arranged',
                    ($oldVal ? "Changed from {$oldFormatted} to {$formatted}" : "Scheduled for {$formatted}"),
                ],
                'next_site_visit_date' => [
                    'bi-calendar-x-fill', 'warning',
                    'Site Visit Rescheduled',
                    "New date: {$formatted}" . ($oldVal ? " (was {$oldFormatted})" : ''),
                ],
                'site_visit_completed_date' => [
                    'bi-patch-check-fill', 'success',
                    'Site Visit Completed',
                    "Completed on {$formatted}",
                ],
                default => ['bi-circle-fill', 'secondary', $field, $newVal ?? ''],
            };

            $events[] = [
                'type'     => 'audit',
                'icon'     => $icon,
                'color'    => $color,
                'label'    => $label,
                'detail'   => $detail,
                'actor'    => $r['actor_name'],
                'event_at' => $r['created_at'],
                'raw'      => $r,
            ];
        }

        // Actual check-in/check-out records
        $stmt2 = $this->pdo->prepare(
            "SELECT 'checkin' AS event_type, sv.`checkin_at` AS event_at,
                    sv.`checkout_at`, sv.`duration_secs`, sv.`voice_notes`,
                    sv.`roof_type`, sv.`roof_area_sqft`,
                    sv.`checkin_lat`, sv.`checkin_lng`,
                    sv.`checkout_lat`, sv.`checkout_lng`,
                    sv.`location_provided`,
                    u.`name` AS actor_name
             FROM `site_visits` sv
             JOIN `users` u ON u.`id` = sv.`rep_id`
             WHERE sv.`lead_id` = ?
             ORDER BY sv.`checkin_at` ASC"
        );
        $stmt2->execute([$leadId]);
        $checkinRows = $stmt2->fetchAll();

        $events = [];

        foreach ($outcomeRows as $r) {
            $eventAt = $r['completed_at'] ?? null;
            [$icon, $color, $label, $detail] = match($r['outcome']) {
                'Arranged Site Visit'   => ['bi-calendar-check-fill', 'success',   'Site Visit Arranged',
                    'Scheduled for ' . ($r['outcome_site_visit_date'] ? date('d M Y', strtotime($r['outcome_site_visit_date'])) : '—')],
                'Site Visit Pending'    => ['bi-hourglass-split',      'warning',   'Site Visit Pending',
                    'Visit marked pending — no action taken yet'],
                'Site Visit Postponed'  => ['bi-calendar-x-fill',      'warning',   'Site Visit Postponed',
                    'Rescheduled to ' . ($r['outcome_next_visit_date'] ? date('d M Y', strtotime($r['outcome_next_visit_date'])) : '—')],
                'Site Visit Rescheduled'=> ['bi-calendar-x-fill',      'warning',   'Site Visit Rescheduled',
                    'New date: ' . ($r['outcome_next_visit_date'] ? date('d M Y', strtotime($r['outcome_next_visit_date'])) : '—')],
                'Site Visit Completed'  => ['bi-patch-check-fill',     'success',   'Site Visit Completed',
                    'Visit completed — lead moved forward'],
                'Lead Won'              => ['bi-trophy-fill',           'success',   'Lead Won',
                    'Site visit completed — customer confirmed'],
                'Lead Lost'             => ['bi-x-circle-fill',         'danger',    'Lead Lost',
                    'Reason: ' . ($r['outcome_lost_reason'] ?? '—')],
                default                 => ['bi-circle-fill',           'secondary', $r['outcome'], ''],
            };
            $events[] = [
                'type'     => 'outcome',
                'icon'     => $icon,
                'color'    => $color,
                'label'    => $label,
                'detail'   => $detail,
                'actor'    => $r['actor_name'],
                'event_at' => $eventAt,
                'raw'      => $r,
            ];
        }

        foreach ($checkinRows as $r) {
            $events[] = [
                'type'     => 'checkin',
                'icon'     => 'bi-geo-alt-fill',
                'color'    => 'primary',
                'label'    => 'GPS Check-in / Check-out',
                'detail'   => null,
                'actor'    => $r['actor_name'],
                'event_at' => $r['event_at'],
                'raw'      => $r,
            ];
        }

        usort($events, fn($a, $b) => strcmp($a['event_at'] ?? '', $b['event_at'] ?? ''));

        return $events;
    }

    private function fetchAuditLog(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.*, u.`name` AS user_name, wt.`title` AS task_title
             FROM `audit_log` al
             LEFT JOIN `users` u ON u.`id` = al.`user_id`
             -- entity is "workflow_task:123" for task rows; pull the title so the
             -- trail names the task rather than leaving a bare id.
             LEFT JOIN `workflow_tasks` wt
                    ON al.`entity` LIKE \'workflow_task:%\'
                   AND wt.`id` = CAST(SUBSTRING(al.`entity`, 15) AS UNSIGNED)
             WHERE al.`lead_id` = ?
             ORDER BY al.`created_at` DESC
             LIMIT 200'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    private function fetchOperations(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.*, u.`name` AS performed_by_name
             FROM `operations` o
             LEFT JOIN `users` u ON u.`id` = o.`performed_by_id`
             WHERE o.`lead_id` = ?
             ORDER BY o.`scheduled_at` DESC
             LIMIT 100'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }

    private function fetchFinancials(int $leadId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `lead_financials` WHERE `lead_id` = ? LIMIT 1');
        $stmt->execute([$leadId]);
        return $stmt->fetch() ?: null;
    }

    private function fetchInstallments(int $leadId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT lpi.*, u.`name` AS created_by_name
             FROM `lead_payment_installments` lpi
             LEFT JOIN `users` u ON u.`id` = lpi.`created_by`
             WHERE lpi.`lead_id` = ?
             ORDER BY lpi.`received_date` ASC'
        );
        $stmt->execute([$leadId]);
        return $stmt->fetchAll();
    }
}
