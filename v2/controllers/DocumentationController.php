<?php
declare(strict_types=1);

class DocumentationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    /**
     * Leads whose site visit is done but whose document set is incomplete.
     * "Incomplete" = any of Aadhar / registered mobile / KSEB bill not ticked.
     * Includes Won leads, since that is where the checklist is actually filled.
     */
    /**
     * Save the document checklist and the document values for one lead.
     *
     * Deliberately separate from LeadDetailController::save(): documentation
     * users are read-only on the lead record, but collecting these documents is
     * precisely their job, so they get a narrow write path to these six fields
     * and nothing else.
     */
    public function saveDocs(): void
    {
        requireLogin();
        csrfVerify();

        $leadId = postInt('lead_id');
        if ($leadId <= 0) jsonError('Invalid lead.', 400);

        $stmt = $this->pdo->prepare(
            'SELECT * FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead) jsonError('Lead not found.', 404);

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];

        if (!in_array($role, ['admin', 'manager', 'operation_manager'], true)) {
            if ($role === 'documentation') {
                $allowed = isDocumentationHead()
                    ? (!empty($lead['site_visit_completed_date']) && $lead['stage'] !== 'Lost')
                    : documentationCanAccessLead($this->pdo, $leadId, $userId);
                if (!$allowed) {
                    jsonError('Access denied.', 403);
                }
            } elseif ($role === 'sales_rep') {
                if ((int) $lead['assigned_rep_id'] !== $userId) {
                    jsonError('Access denied.', 403);
                }
            } else {
                jsonError('You cannot edit documents on this lead.', 403);
            }
        }

        $flags = [
            'doc_aadhar'            => (int) (bool) post('doc_aadhar'),
            'doc_registered_mobile' => (int) (bool) post('doc_registered_mobile'),
            'doc_kseb_bill'         => (int) (bool) post('doc_kseb_bill'),
            'doc_bank_passbook'     => (int) (bool) post('doc_bank_passbook'),
        ];
        $values = [
            'doc_aadhar_number'    => trim((string) post('doc_aadhar_number'))    ?: null,
            'doc_mobile_number'    => trim((string) post('doc_mobile_number'))    ?: null,
            'doc_kseb_consumer_no' => trim((string) post('doc_kseb_consumer_no')) ?: null,
            'doc_bank_account_no'  => trim((string) post('doc_bank_account_no'))  ?: null,
        ];

        // Light validation — reject obvious typos without blocking odd real-world formats
        if ($values['doc_aadhar_number'] !== null) {
            $digits = preg_replace('/\D/', '', $values['doc_aadhar_number']);
            if (strlen($digits) !== 12) {
                jsonError('Aadhar number must be 12 digits.', 422);
            }
            $values['doc_aadhar_number'] = $digits;
        }
        if ($values['doc_mobile_number'] !== null) {
            $digits = preg_replace('/\D/', '', $values['doc_mobile_number']);
            if (strlen($digits) !== 10) {
                jsonError('Registered mobile number must be 10 digits.', 422);
            }
            $values['doc_mobile_number'] = $digits;
        }

        $fields = array_merge($flags, $values);
        $set    = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($fields)));

        $this->pdo->prepare("UPDATE `leads` SET {$set}, `updated_at`=NOW() WHERE `id`=?")
                  ->execute([...array_values($fields), $leadId]);

        foreach ($fields as $col => $new) {
            if ((string) ($lead[$col] ?? '') !== (string) ($new ?? '')) {
                auditUpdate($leadId, $col, $lead[$col] ?? null, $new);
            }
        }

        jsonResponse(['ok' => true]);
    }

    public function pending(): void
    {
        $this->docChecklistPage(false);
    }

    /**
     * Mirror of pending(): leads whose full document set has been received.
     */
    public function received(): void
    {
        $this->docChecklistPage(true);
    }

    /**
     * Shared listing for both document-checklist tabs. $complete flips the page
     * between "all three received" and "at least one outstanding".
     */
    private function docChecklistPage(bool $complete): void
    {
        requireLogin();
        if (!canSeeDocTabs()) {
            http_response_code(403);
            die('Access denied.');
        }

        $stage  = get('stage');
        $missing = $complete ? '' : get('missing');
        $search = trim(get('q'));

        $checklist = '(l.`doc_aadhar` = 1 AND l.`doc_registered_mobile` = 1
                       AND l.`doc_kseb_bill` = 1 AND l.`doc_bank_passbook` = 1)';

        $where = [
            'l.`deleted_at` IS NULL',
            'l.`site_visit_completed_date` IS NOT NULL',
            "l.`stage` <> 'Lost'",
            $complete ? $checklist : "NOT {$checklist}",
        ];
        $params = [];

        // Documentation users other than the Head only see their own leads
        if (!canSeeAllDocumentation()) {
            $where[]  = documentationScopeSql();
            $params[] = (int) $_SESSION['user_id'];
            $params[] = (int) $_SESSION['user_id'];
        }

        if ($stage !== '' && in_array($stage, allStages(), true)) {
            $where[]  = 'l.`stage` = ?';
            $params[] = $stage;
        }
        if (in_array($missing, ['aadhar', 'mobile', 'kseb', 'passbook'], true)) {
            $col = ['aadhar' => 'doc_aadhar', 'mobile' => 'doc_registered_mobile',
                    'kseb'   => 'doc_kseb_bill', 'passbook' => 'doc_bank_passbook'][$missing];
            $where[] = "l.`{$col}` = 0";
        }
        if ($search !== '') {
            $where[]  = '(l.`name` LIKE ? OR l.`location` LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`phone`, l.`phone_revealed`, l.`location`, l.`stage`,
                    l.`potential_kw`, l.`site_visit_completed_date`,
                    l.`doc_aadhar`, l.`doc_registered_mobile`, l.`doc_kseb_bill`, l.`doc_bank_passbook`,
                    l.`documentation_user_id`,
                    rep.`name` AS rep_name,
                    doc.`name` AS doc_user_name,
                    DATEDIFF(CURDATE(), l.`site_visit_completed_date`) AS days_since_visit
             FROM `leads` l
             LEFT JOIN `users` rep ON rep.`id` = l.`assigned_rep_id`
             LEFT JOIN `users` doc ON doc.`id` = l.`documentation_user_id`
             WHERE " . implode(' AND ', $where) . "
             ORDER BY l.`site_visit_completed_date` ASC, l.`name` ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $docUsers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        // Header tallies
        $missAadhar = $missMobile = $missKseb = $missPassbook = $unassigned = $wonCount = 0;
        foreach ($rows as $r) {
            if (!$r['doc_aadhar'])            $missAadhar++;
            if (!$r['doc_registered_mobile']) $missMobile++;
            if (!$r['doc_kseb_bill'])         $missKseb++;
            if (!$r['doc_bank_passbook'])     $missPassbook++;
            if (empty($r['documentation_user_id'])) $unassigned++;
            if ($r['stage'] === 'Won')        $wonCount++;
        }

        $pageTitle = $complete ? 'Documents Received' : 'Pending Documentation';
        require __DIR__ . '/../views/documentation/'
              . ($complete ? 'received.php' : 'pending.php');
    }

    public function index(): void
    {
        requireRole('admin', 'manager', 'documentation');

        // This page is hidden for now — superseded by Pending Docs / Docs Received.
        // Kept intact so it can be switched back on by deleting this redirect.
        redirect('/documentation/pending');

        $role    = $_SESSION['role'];
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        $docScope  = '';
        $docParams = [];
        if (!$isAdmin && !isDocumentationHead()) {
            $userId    = (int) $_SESSION['user_id'];
            $docScope  = 'AND ' . documentationScopeSql();
            $docParams = [$userId, $userId];
        }

        // Show all leads where:
        //   • site visit is completed (site_visit_completed_date IS NOT NULL)
        //   • stage is not Lost / Won / Installation (i.e. deal not closed or failed yet)
        //   • no documentation task has been marked done yet
        //
        // LEFT JOIN the latest pending documentation task so rows without a task
        // still appear (task_id will be NULL for those).
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`                     AS lead_id,
                    l.`name`                   AS lead_name,
                    l.`phone`,
                    l.`phone_revealed`,
                    l.`location`,
                    l.`stage`,
                    l.`visit_scheduled_date`,
                    l.`site_visit_completed_date`,
                    ls.`name`                  AS source_name,
                    rep.`name`                 AS rep_name,
                    wt.`id`                    AS task_id,
                    wt.`title`                 AS task_title,
                    wt.`due_date`,
                    wt.`assigned_to`,
                    wt.`assigned_role`,
                    wt.`created_at`            AS task_created,
                    au.`name`                  AS assignee_name
             FROM `leads` l
             -- Attach the most-recent pending documentation task (if any)
             LEFT JOIN `workflow_tasks` wt
                  ON wt.`id` = (
                      SELECT `id` FROM `workflow_tasks`
                      WHERE `lead_id` = l.`id`
                        AND `assigned_role` = 'documentation'
                        AND `status` = 'pending'
                      ORDER BY `id` DESC LIMIT 1
                  )
             LEFT JOIN `lead_sources` ls  ON ls.`id` = l.`source_id`
             LEFT JOIN `users` rep        ON rep.`id` = l.`assigned_rep_id`
             LEFT JOIN `users` au         ON au.`id`  = wt.`assigned_to`
             WHERE l.`deleted_at` IS NULL
               AND l.`site_visit_completed_date` IS NOT NULL
               AND l.`stage` NOT IN ('Lost','Won','Installation')
               -- Exclude leads where documentation is already done
               AND NOT EXISTS (
                   SELECT 1 FROM `workflow_tasks` wt2
                   WHERE wt2.`lead_id`       = l.`id`
                     AND wt2.`assigned_role` = 'documentation'
                     AND wt2.`status`        = 'done'
               )
               -- Documentation users see only their own work; admin/manager see all
               {$docScope}
             ORDER BY l.`site_visit_completed_date` ASC, l.`name` ASC"
        );
        $stmt->execute($docParams);
        $pendingDocs = $stmt->fetchAll();

        $today    = date('Y-m-d');
        $total    = count($pendingDocs);
        $overdue  = count(array_filter($pendingDocs, fn($r) => $r['due_date'] && $r['due_date'] < $today));
        $dueToday = count(array_filter($pendingDocs, fn($r) => $r['due_date'] === $today));
        $noTask   = count(array_filter($pendingDocs, fn($r) => empty($r['task_id'])));
        $upcoming = $total - $overdue - $dueToday;

        // Documentation users list (for reassign dropdown — admin/manager only)
        $docUsers = $isAdmin ? $this->pdo->query(
            "SELECT `id`,`name` FROM `users`
             WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll() : [];

        $pageTitle = 'Pending Documentation';
        require __DIR__ . '/../views/documentation/index.php';
    }
}
