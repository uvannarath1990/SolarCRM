<?php
declare(strict_types=1);

class LeadsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireLogin();

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];

        // ─── Filters ────────────────────────────────────────────────────────
        $search       = trim(get('q'));
        $stage        = get('stage');
        $repId        = getInt('rep');
        $sourceId     = getInt('source');
        $filterPartner = getInt('partner');   // 0 = all, -1 = any partner lead, >0 = specific partner
        $page         = max(1, getInt('page', 1));
        // Admins/managers can widen the page so bulk selection covers more leads
        $perPage      = in_array($role, ['admin', 'manager'], true)
                        && in_array(getInt('per_page'), [50, 100], true)
                      ? getInt('per_page') : 25;

        $active    = get('active');
        $overdue   = get('overdue');
        $wonMonth  = get('won_month');

        // Created-date filter
        $dateRange = get('date_range');   // today|yesterday|this_week|this_month|last_month|custom
        $dateFrom  = get('date_from');    // custom only, Y-m-d
        $dateTo    = get('date_to');      // custom only, Y-m-d

        $where  = ['1=1'];
        $params = [];

        // Role-based visibility
        $openRoles = ['admin', 'manager', 'operation_manager', 'accounts'];
        if (!in_array($role, $openRoles, true)) {
            if ($role === 'sales_rep') {
                $where[]  = 'l.`assigned_rep_id` = ?';
                $params[] = $userId;
            } elseif ($role === 'tele_caller') {
                $where[]  = '(l.`created_by` = ? OR l.`tele_caller_id` = ?)';
                $params[] = $userId;
                $params[] = $userId;
            } elseif ($role === 'dealer') {
                $where[]  = 'l.`created_by` = ?';
                $params[] = $userId;
            } elseif ($role === 'installer') {
                $where[]  = "l.`stage` = 'Won'";
            } elseif ($role === 'partner') {
                $where[]  = 'l.`partner_id` = ?';
                $params[] = $userId;
            } elseif ($role === 'documentation') {
                // Leads they created are always theirs to see, on top of their scope
                if (isDocumentationHead()) {
                    // The Head oversees the documentation pipeline — the same leads
                    // the doc tabs list — but not the wider lead base.
                    $where[]  = '(' . documentationHeadScopeSql() . ' OR l.`created_by` = ?)';
                    $params[] = $userId;
                } else {
                    $where[]  = '(' . documentationScopeSql() . ' OR l.`created_by` = ?)';
                    $params[] = $userId;
                    $params[] = $userId;
                    $params[] = $userId;
                }
            }
        }
        // Soft-deleted leads are never shown
        $where[] = 'l.`deleted_at` IS NULL';

        // Dashboard tile filters
        if ($active === '1') {
            $where[] = "l.`stage` NOT IN ('Won','Lost')";
        }
        if ($overdue === '1') {
            $where[] = 'l.`next_followup_date` < CURDATE()';
            $where[] = "l.`stage` NOT IN ('Won','Lost')";
        }
        if ($wonMonth === '1') {
            $where[] = "l.`stage` = 'Won'";
            $where[] = 'MONTH(l.`updated_at`) = MONTH(NOW()) AND YEAR(l.`updated_at`) = YEAR(NOW())';
        }

        if ($search !== '') {
            $where[]  = '(l.`name` LIKE ? OR l.`phone` LIKE ? OR l.`location` LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($stage !== '' && in_array($stage, allStages(), true)) {
            $where[]  = 'l.`stage` = ?';
            $params[] = $stage;
        }
        if ($repId > 0) {
            $where[]  = 'l.`assigned_rep_id` = ?';
            $params[] = $repId;
        }
        if ($sourceId > 0) {
            $where[]  = 'l.`source_id` = ?';
            $params[] = $sourceId;
        }
        // Partner tab filter (admin/manager only)
        if (in_array($role, ['admin', 'manager'], true)) {
            if ($filterPartner === -1) {
                $where[] = 'l.`partner_id` IS NOT NULL';
            } elseif ($filterPartner > 0) {
                $where[]  = 'l.`partner_id` = ?';
                $params[] = $filterPartner;
            }
        }

        // Created-date filter
        switch ($dateRange) {
            case 'today':
                $where[] = 'DATE(l.`created_at`) = CURDATE()';
                break;
            case 'yesterday':
                $where[] = 'DATE(l.`created_at`) = CURDATE() - INTERVAL 1 DAY';
                break;
            case 'this_week':
                $where[] = 'YEARWEEK(l.`created_at`, 1) = YEARWEEK(CURDATE(), 1)';
                break;
            case 'this_month':
                $where[] = 'YEAR(l.`created_at`) = YEAR(CURDATE()) AND MONTH(l.`created_at`) = MONTH(CURDATE())';
                break;
            case 'last_month':
                $where[] = 'YEAR(l.`created_at`) = YEAR(CURDATE() - INTERVAL 1 MONTH) AND MONTH(l.`created_at`) = MONTH(CURDATE() - INTERVAL 1 MONTH)';
                break;
            case 'custom':
                if ($dateFrom !== '') {
                    $where[]  = 'DATE(l.`created_at`) >= ?';
                    $params[] = $dateFrom;
                }
                if ($dateTo !== '') {
                    $where[]  = 'DATE(l.`created_at`) <= ?';
                    $params[] = $dateTo;
                }
                break;
        }

        $whereStr = implode(' AND ', $where);

        // Count
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `leads` l WHERE {$whereStr}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $paging = paginate($total, $perPage, $page);

        // Fetch page
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`phone`, l.`location`, l.`stage`,
                    l.`potential_kw`, l.`quote_amount`, l.`won_value`,
                    l.`next_followup_date`, l.`visit_scheduled_date`,
                    l.`created_at`, l.`source_id`,
                    u.`name`  AS rep_name,
                    ls.`name` AS source_name,
                    pt.`name` AS partner_name
             FROM `leads` l
             LEFT JOIN `users`        u  ON u.`id`  = l.`assigned_rep_id`
             LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
             LEFT JOIN `users`        pt ON pt.`id` = l.`partner_id`
             WHERE {$whereStr}
             ORDER BY l.`updated_at` DESC
             LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($params);
        $leads = $stmt->fetchAll();

        // Sidebar data
        $reps    = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $sources = $this->pdo->query(
            "SELECT `id`,`name` FROM `lead_sources` WHERE `is_active`=1 ORDER BY `sort_order`"
        )->fetchAll();
        $partners = [];
        $partnerLeadCount = 0;
        if (in_array($role, ['admin', 'manager'], true)) {
            $partners = $this->pdo->query(
                "SELECT `id`,`name` FROM `users` WHERE `role`='partner' AND `is_active`=1 ORDER BY `name`"
            )->fetchAll();
            $partnerLeadCount = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM `leads` WHERE `partner_id` IS NOT NULL AND `deleted_at` IS NULL"
            )->fetchColumn();
        }

        // Option lists for the bulk-update bar (admin/manager only)
        $canBulk   = in_array($role, ['admin', 'manager'], true);
        $bulkTele  = $bulkDocs = [];
        if ($canBulk) {
            $bulkTele = $this->pdo->query(
                "SELECT `id`,`name`,`role` FROM `users`
                 WHERE `role` IN ('tele_caller','sales_rep') AND `is_active`=1 ORDER BY `name`"
            )->fetchAll();
            $bulkDocs = $this->pdo->query(
                "SELECT `id`,`name` FROM `users` WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
            )->fetchAll();
        }

        $pageTitle = 'Leads';
        $extraJs   = ['inline-edit.js'];
        require __DIR__ . '/../views/leads/list.php';
    }

    public function create(): void
    {
        requireLogin();
        if (!canCreateLead()) {
            http_response_code(403);
            die('Access denied.');
        }

        $sources = $this->pdo->query(
            "SELECT `id`,`name` FROM `lead_sources` WHERE `is_active`=1 ORDER BY `sort_order`"
        )->fetchAll();
        $reps = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $teleCallers = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `role` IN ('tele_caller','sales_rep') AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $partners = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='partner' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        // Subcategories grouped by source_id for dynamic dropdown
        $subRows = $this->pdo->query(
            "SELECT `id`,`source_id`,`name` FROM `lead_source_subs` WHERE `is_active`=1 ORDER BY `source_id`,`sort_order`,`name`"
        )->fetchAll();
        $sourceSubs = [];
        foreach ($subRows as $sub) {
            $sourceSubs[(int) $sub['source_id']][] = ['id' => $sub['id'], 'name' => $sub['name']];
        }

        // For partners: auto-select "Referral" source
        $partnerDefaultSourceId = null;
        if ($_SESSION['role'] === 'partner') {
            $refRow = $this->pdo->query(
                "SELECT `id` FROM `lead_sources` WHERE LOWER(`name`) LIKE '%referral%' AND `is_active`=1 LIMIT 1"
            )->fetch();
            $partnerDefaultSourceId = $refRow ? (int) $refRow['id'] : null;
        }

        $pageTitle = 'New Lead';
        require __DIR__ . '/../views/leads/form.php';
    }

    public function save(): void
    {
        requireLogin();
        if (!canCreateLead()) {
            http_response_code(403);
            die('Access denied.');
        }
        csrfVerify();

        $name         = trim(post('name'));
        $phone        = trim(post('phone'));
        $email        = trim(post('email'));
        $location     = trim(post('location'));
        $sourceId     = postInt('source_id') ?: null;
        // Partners default to "Referral" source if none provided
        if (!$sourceId && $_SESSION['role'] === 'partner') {
            $refRow = $this->pdo->query(
                "SELECT `id` FROM `lead_sources` WHERE LOWER(`name`) LIKE '%referral%' AND `is_active`=1 LIMIT 1"
            )->fetch();
            if ($refRow) $sourceId = (int) $refRow['id'];
        }
        $sourceSubId       = postInt('source_sub_id') ?: null;
        $isNewConstruction = isset($_POST['is_new_construction']) ? 1 : 0;
        $bill              = post('bill_amount') !== '' ? (float) post('bill_amount') : null;
        $kw           = post('potential_kw') !== '' ? (float) post('potential_kw') : kwFromBill($bill);

        // Partners are auto-linked to leads they create
        $partnerId = null;
        if ($_SESSION['role'] === 'partner') {
            $partnerId = (int) $_SESSION['user_id'];
        } elseif (canAssignPartner()) {
            $partnerId = postInt('partner_id') ?: null;
            if ($partnerId) {
                $chk = $this->pdo->prepare(
                    "SELECT `id` FROM `users` WHERE `id`=? AND `role`='partner' AND `is_active`=1 LIMIT 1"
                );
                $chk->execute([$partnerId]);
                if (!$chk->fetch()) $partnerId = null;
            }
        }

        // Tele callers are auto-assigned to their own leads; anyone holding the
        // assign-tele-caller permission may nominate someone else instead.
        $teleCallerId = null;
        if ($_SESSION['role'] === 'tele_caller') {
            $teleCallerId = (int) $_SESSION['user_id'];
        } elseif (canAssignTeleCaller()) {
            $teleCallerId = postInt('tele_caller_id') ?: null;
            if ($teleCallerId) {
                $chk = $this->pdo->prepare(
                    "SELECT `id` FROM `users`
                     WHERE `id`=? AND `role` IN ('tele_caller','sales_rep') AND `is_active`=1 LIMIT 1"
                );
                $chk->execute([$teleCallerId]);
                if (!$chk->fetch()) $teleCallerId = null;
            }
        }

        // Site visit date is open to anyone who can create a lead — it is not an
        // assignment, and coupling it to the rep permission would remove it from
        // users who are simply not allowed to choose the rep.
        $visitScheduledDate = post('visit_scheduled_date') ?: null;
        if ($visitScheduledDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $visitScheduledDate)) {
            $visitScheduledDate = null;
        }

        // Sales reps are auto-assigned to their own leads; others may nominate a
        // rep only when they hold the assign-rep permission.
        $repId = null;
        if ($_SESSION['role'] === 'sales_rep') {
            $repId = (int) $_SESSION['user_id'];
        } elseif (canAssignRep()) {
            $repId = postInt('assigned_rep_id') ?: null;
            if ($repId) {
                $chk = $this->pdo->prepare(
                    "SELECT `id` FROM `users` WHERE `id`=? AND `role`='sales_rep' AND `is_active`=1 LIMIT 1"
                );
                $chk->execute([$repId]);
                if (!$chk->fetch()) $repId = null;
            }
        }

        $phone = preg_replace('/\D/', '', $phone); // strip non-digits

        if ($name === '' || $phone === '') {
            flash('danger', 'Name and phone are required.');
            redirect('/leads/create');
        }

        if (!$sourceId) {
            flash('danger', 'Lead Source is required.');
            redirect('/leads/create');
        }

        if (strlen($phone) !== 10) {
            flash('danger', 'Phone number must be exactly 10 digits.');
            redirect('/leads/create');
        }

        $dup = $this->pdo->prepare('SELECT `id`,`name` FROM `leads` WHERE `phone` = ? LIMIT 1');
        $dup->execute([$phone]);
        $existing = $dup->fetch();
        if ($existing) {
            flash('danger', 'Phone ' . htmlspecialchars($phone) . ' already exists — '
                . '<a href="' . BASE_URL . '/leads/detail?id=' . $existing['id']
                . '">view lead: ' . htmlspecialchars($existing['name']) . '</a>', true);
            redirect('/leads/create');
        }

        // Determine initial stage
        $initialStage = ($repId && $visitScheduledDate) ? 'Visit Scheduled' : 'New';

        $stmt = $this->pdo->prepare(
            'INSERT INTO `leads`
                (`name`,`phone`,`email`,`location`,`source_id`,`source_sub_id`,`is_new_construction`,
                 `bill_amount`,`potential_kw`,`assigned_rep_id`,`tele_caller_id`,`partner_id`,
                 `visit_scheduled_date`,`stage`,`created_by`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $name, $phone, $email ?: null, $location ?: null, $sourceId, $sourceSubId, $isNewConstruction,
            $bill, $kw, $repId, $teleCallerId, $partnerId, $visitScheduledDate, $initialStage, $_SESSION['user_id'],
        ]);
        $leadId = (int) $this->pdo->lastInsertId();

        auditCreate($leadId, "Lead created: {$name}");
        if ($visitScheduledDate) {
            auditUpdate($leadId, 'visit_scheduled_date', null, $visitScheduledDate);
        }

        $leadUrl = BASE_URL . '/leads/detail?id=' . $leadId;

        // Create task based on what was filled
        if ($repId && $visitScheduledDate) {
            // Both rep and date — task for the sales rep
            $this->pdo->prepare(
                "INSERT INTO `workflow_tasks`
                 (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $leadId, 'Visit Scheduled',
                'Conduct site visit and follow up with customer',
                $repId, 'sales_rep', $visitScheduledDate,
            ]);
            auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId,
                            'Conduct site visit and follow up with customer', 'lead created');
            sendPushToUser($repId, 'Site Visit Assigned', "{$name} · {$visitScheduledDate}", $leadUrl);
        } elseif ($visitScheduledDate && !$repId) {
            // Date only — task for manager to assign a sales rep
            $managerRow = $this->pdo->query(
                "SELECT `id` FROM `users`
                 WHERE `role` IN ('manager','admin') AND `is_active`=1
                 ORDER BY FIELD(`role`,'manager','admin') LIMIT 1"
            )->fetch();
            $managerId = $managerRow ? (int) $managerRow['id'] : null;

            $this->pdo->prepare(
                "INSERT INTO `workflow_tasks`
                 (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $leadId, 'New',
                'Site visit scheduled — assign a Sales Rep',
                $managerId, 'manager', $visitScheduledDate,
            ]);
            auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId,
                            'Site visit scheduled — assign a Sales Rep', 'lead created');
            if ($managerId) {
                sendPushToUser($managerId, 'Assign Sales Rep', "{$name} · visit on {$visitScheduledDate}", $leadUrl);
            }
        }

        if ($repId) {
            sendPushToUser($repId, 'New Lead Assigned', "{$name} · {$phone}", $leadUrl);
        }
        sendPushToRole('manager', 'New Lead Created', $name, $leadUrl);

        flash('success', 'Lead created successfully.');
        redirect('/leads/detail?id=' . $leadId);
    }

    /**
     * Apply one change to many leads at once. Admin and manager only.
     *
     * Each lead is processed independently: one failing (e.g. a lead that can't
     * go to Quoted because it has no quote) is reported and skipped rather than
     * aborting the rest. Every change is audited per lead, exactly as a single
     * edit would be.
     */
    public function bulkUpdate(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        require_once __DIR__ . '/../includes/workflow.php';

        $ids    = post('lead_ids');
        $action = (string) post('action');
        $value  = post('value');

        if (!is_array($ids) || empty($ids)) jsonError('No leads selected.', 422);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (count($ids) > 500) jsonError('Select 500 leads or fewer at a time.', 422);

        // next_followup_date is not settable — it is derived from the lead's open tasks
        $actions = ['stage', 'assigned_rep_id', 'tele_caller_id', 'documentation_user_id',
                    'source_id'];
        if (!in_array($action, $actions, true)) jsonError('Unknown bulk action.', 422);

        // ── Validate the target value once, before touching any lead ─────────
        $valueLabel = null;
        $lostReason = null;

        if ($action === 'stage') {
            if (!in_array($value, allStages(), true)) jsonError('Choose a valid stage.', 422);
            $lostReason = trim((string) post('lost_reason')) ?: null;
            $valueLabel = $value;

        } elseif (in_array($action, ['assigned_rep_id', 'tele_caller_id', 'documentation_user_id'], true)) {
            $value = (int) $value ?: null;   // 0 / empty = unassign
            if ($value) {
                $roles = [
                    'assigned_rep_id'       => ['sales_rep'],
                    'tele_caller_id'        => ['tele_caller', 'sales_rep'],
                    'documentation_user_id' => ['documentation'],
                ][$action];
                $in = implode(',', array_fill(0, count($roles), '?'));
                $u  = $this->pdo->prepare(
                    "SELECT `name` FROM `users` WHERE `id`=? AND `is_active`=1 AND `role` IN ({$in}) LIMIT 1"
                );
                $u->execute([$value, ...$roles]);
                $valueLabel = $u->fetchColumn();
                if ($valueLabel === false) jsonError('That user is not available for this role.', 422);
            } else {
                $valueLabel = 'unassigned';
            }

        } elseif ($action === 'source_id') {
            $value = (int) $value;
            $sn = $this->pdo->prepare('SELECT `name` FROM `lead_sources` WHERE `id`=? LIMIT 1');
            $sn->execute([$value]);
            $valueLabel = $sn->fetchColumn();
            if ($valueLabel === false) jsonError('Choose a valid lead source.', 422);

        }

        // ── Apply per lead ───────────────────────────────────────────────────
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM `leads` WHERE `id` IN ({$in}) AND `deleted_at` IS NULL"
        );
        $stmt->execute($ids);
        $leads = $stmt->fetchAll();

        $updated  = 0;
        $skipped  = [];
        $newOwner = [];   // rep id → count, for one summary notification each

        // Source names for readable audit entries
        $srcNames = $action === 'source_id'
            ? $this->pdo->query('SELECT `id`,`name` FROM `lead_sources`')->fetchAll(PDO::FETCH_KEY_PAIR)
            : [];

        foreach ($leads as $lead) {
            $leadId = (int) $lead['id'];
            try {
                if ($action === 'stage') {
                    $why = applyLeadStage($this->pdo, $lead, (string) $value, $lostReason);
                    if ($why !== null) { $skipped[] = ['id' => $leadId, 'name' => $lead['name'], 'reason' => $why]; continue; }

                } elseif ($action === 'source_id') {
                    if ((int) $lead['source_id'] === $value) {
                        $skipped[] = ['id' => $leadId, 'name' => $lead['name'], 'reason' => 'already on that source'];
                        continue;
                    }
                    $this->pdo->prepare(
                        'UPDATE `leads` SET `source_id`=?, `source_sub_id`=NULL, `updated_at`=NOW() WHERE `id`=?'
                    )->execute([$value, $leadId]);
                    auditUpdate($leadId, 'source_id',
                                $srcNames[(int) $lead['source_id']] ?? null, $valueLabel);

                } else { // an assignment column
                    if ((int) ($lead[$action] ?? 0) === (int) $value) {
                        $skipped[] = ['id' => $leadId, 'name' => $lead['name'], 'reason' => 'already assigned'];
                        continue;
                    }
                    $col = '`' . $action . '`';   // $action is whitelisted above
                    $this->pdo->prepare("UPDATE `leads` SET {$col}=?, `updated_at`=NOW() WHERE `id`=?")
                              ->execute([$value, $leadId]);
                    auditUpdate($leadId, $action, $lead[$action], $value);
                    if ($action === 'assigned_rep_id' && $value) {
                        $newOwner[$value] = ($newOwner[$value] ?? 0) + 1;
                    }
                }
                $updated++;

            } catch (\Throwable $e) {
                $skipped[] = ['id' => $leadId, 'name' => $lead['name'], 'reason' => 'error: ' . $e->getMessage()];
            }
        }

        // One notification per rep, not one per lead
        foreach ($newOwner as $repId => $n) {
            sendPushToUser((int) $repId, 'Leads assigned to you',
                           $n . ' lead' . ($n === 1 ? '' : 's') . ' assigned to you',
                           BASE_URL . '/leads');
        }

        jsonResponse([
            'ok'      => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'label'   => $valueLabel,
        ]);
    }

    public function inlineEdit(): void
    {
        requireLogin();
        csrfVerify();

        $allowed = ['stage', 'quote_amount', 'won_value', 'assigned_rep_id',
                    'visit_scheduled_date',
                    'status_reason_code', 'status_reason_notes',
                    'phone_revealed'];

        $leadId = postInt('lead_id');
        $field  = post('field');
        $value  = post('value');

        if ($leadId <= 0 || !in_array($field, $allowed, true)) {
            jsonError('Invalid request.', 400);
        }

        // Fetch current lead
        $stmt = $this->pdo->prepare(
            'SELECT l.*, u.`role` AS rep_role FROM `leads` l
             LEFT JOIN `users` u ON u.`id` = l.`assigned_rep_id`
             WHERE l.`id` = ?'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            jsonError('Lead not found.', 404);
        }

        // Access check — mirrors LeadsController visibility rules
        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];
        $openRoles = ['admin', 'manager', 'operation_manager', 'accounts'];
        if (!in_array($role, $openRoles, true)) {
            // Documentation is a back-office role — read-only on lead fields.
            if ($role === 'documentation') {
                jsonError('Documentation users cannot edit lead fields.', 403);
            }
            if ($role === 'tele_caller'
                && (int) $lead['created_by'] !== $userId
                && (int) ($lead['tele_caller_id'] ?? 0) !== $userId) {
                jsonError('Access denied.', 403);
            }
            if ($role === 'sales_rep' && (int) $lead['assigned_rep_id'] !== $userId) {
                jsonError('Access denied.', 403);
            }
        }

        // phone_revealed — admin/manager only
        if ($field === 'phone_revealed') {
            if (!in_array($role, ['admin', 'manager'], true)) {
                jsonError('Only admin or manager can reveal phone numbers.', 403);
            }
            $value = $value ? 1 : 0;
        }

        // Stage transition validation
        if ($field === 'stage') {
            // Sales reps and tele callers don't switch stages directly — reps close
            // deals through Mark Won / Mark Lost, and stages otherwise move through
            // the workflow. This closes the Leads-list stage pill as a way around that.
            if (in_array($role, ['sales_rep', 'tele_caller'], true)) {
                jsonError('Stage changes go through the lead page — use Mark Won or Mark Lost.', 403);
            }
            // Admin/manager can force any valid stage without restriction
            $forceAllowed = in_array($role, ['admin', 'manager'], true);
            if (!$forceAllowed) {
                $allowed_next = allowedNextStages($lead['stage'], $role);
                if (!in_array($value, $allowed_next, true)) {
                    jsonError("Cannot move from '{$lead['stage']}' to '{$value}'.", 400);
                }
            } elseif (!in_array($value, allStages(), true)) {
                jsonError("Invalid stage '{$value}'.", 400);
            }

            // A lead cannot enter Quoted without a quote amount — without this the
            // stage carries no value and revenue reporting is impossible.
            if ($value === 'Quoted') {
                $quote = post('quote_amount');
                if ($quote !== null && $quote !== '') {
                    $quote = (float) $quote;
                    if ($quote <= 0) {
                        jsonError('Quote amount must be greater than zero.', 400);
                    }
                    $this->pdo->prepare('UPDATE `leads` SET `quote_amount` = ? WHERE `id` = ?')
                              ->execute([$quote, $leadId]);
                    auditUpdate($leadId, 'quote_amount', $lead['quote_amount'], $quote);
                    $lead['quote_amount'] = $quote;
                }
                if ($lead['quote_amount'] === null || (float) $lead['quote_amount'] <= 0) {
                    jsonError('A quote amount is required before moving this lead to Quoted.', 422);
                }
            }
        }

        // Numeric fields
        if (in_array($field, ['quote_amount', 'won_value'], true)) {
            $value = $value !== '' ? (float) $value : null;
        }
        if ($field === 'assigned_rep_id') {
            $value = $value !== '' ? (int) $value : null;
        }

        $old = $lead[$field];

        // Use a column name whitelist-verified string (field already validated above)
        $col  = '`' . str_replace('`', '', $field) . '`';
        $stmt = $this->pdo->prepare("UPDATE `leads` SET {$col}=?, `updated_at`=NOW() WHERE `id`=?");
        $stmt->execute([$value, $leadId]);

        // Stage change → create installation record + initial operation record for Won
        if ($field === 'stage' && $value === 'Won') {
            $this->pdo->prepare(
                'INSERT IGNORE INTO `installations` (`lead_id`) VALUES (?)'
            )->execute([$leadId]);

            $opExists = $this->pdo->prepare(
                'SELECT COUNT(*) FROM `operations` WHERE `lead_id`=?'
            );
            $opExists->execute([$leadId]);
            if ((int) $opExists->fetchColumn() === 0) {
                $this->pdo->prepare(
                    'INSERT INTO `operations` (`lead_id`,`operation_type`,`scheduled_at`,`interval_days`)
                     VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 90 DAY),120)'
                )->execute([$leadId, 'Cleaning']);
            }
        }

        $leadUrl = BASE_URL . '/leads/detail?id=' . $leadId;

        if ($field === 'stage') {
            auditStageChange($leadId, (string) $old, (string) $value);
            createWorkflowTasks($leadId, (string) $value, $this->pdo);
            if ((string) $value === 'Won') {
                createWorkflowTasks($leadId, 'Won', $this->pdo);
            }
            // Notify assigned rep + managers of stage change
            $recipients = [];
            if ($lead['assigned_rep_id']) $recipients[] = (int) $lead['assigned_rep_id'];
            sendPushToUsers($recipients, 'Stage Updated', "{$lead['name']}: {$old} → {$value}", $leadUrl);
            sendPushToRole('manager', 'Stage Updated', "{$lead['name']}: {$old} → {$value}", $leadUrl);
        } else {
            auditUpdate($leadId, $field, $old, $value);
            if ($field === 'assigned_rep_id' && $value) {
                sendPushToUser((int) $value, 'Lead Assigned to You', $lead['name'], $leadUrl);
            }
        }

        jsonResponse(['ok' => true, 'field' => $field, 'value' => $value]);
    }

    public function kanban(): void
    {
        requireRole('admin', 'manager');

        // Kanban is hidden for now — nav link removed, direct URL sent back to Leads
        redirect('/leads');

        $stages = allStages();
        $cards  = [];

        foreach ($stages as $stage) {
            $stmt = $this->pdo->prepare(
                "SELECT l.`id`, l.`name`, l.`phone`, l.`stage`,
                        l.`potential_kw`, l.`quote_amount`, l.`created_at`,
                        u.`name` AS rep_name, ls.`name` AS source_name
                 FROM `leads` l
                 LEFT JOIN `users` u ON u.`id` = l.`assigned_rep_id`
                 LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
                 WHERE l.`stage` = ? AND l.`deleted_at` IS NULL
                 ORDER BY l.`created_at` DESC
                 LIMIT 50"
            );
            $stmt->execute([$stage]);
            $cards[$stage] = $stmt->fetchAll();
        }

        $pageTitle = 'Kanban Board';
        require __DIR__ . '/../views/leads/kanban.php';
    }

    public function markOutcome(): void
    {
        requireLogin();
        requireRole('admin', 'manager', 'sales_rep');
        csrfVerify();

        $leadId  = postInt('lead_id');
        $outcome = trim((string) post('outcome'));

        if ($leadId <= 0 || !in_array($outcome, ['won', 'lost'], true)) {
            jsonError('Invalid request.', 400);
        }

        $stmt = $this->pdo->prepare('SELECT * FROM `leads` WHERE `id` = ? AND `deleted_at` IS NULL');
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead) { jsonError('Lead not found.', 404); }

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];

        // Access check
        if (!in_array($role, ['admin', 'manager', 'operation_manager'], true)) {
            if ($role === 'sales_rep' && (int) $lead['assigned_rep_id'] !== $userId) {
                jsonError('Access denied.', 403);
            }
        }

        $oldStage = (string) $lead['stage'];
        $leadUrl  = BASE_URL . '/leads/detail?id=' . $leadId;

        if ($outcome === 'won') {
            $forceAllowed = in_array($role, ['admin', 'manager'], true);
            if (!$forceAllowed && !in_array('Won', allowedNextStages($oldStage, $role), true)) {
                jsonError("Cannot mark Won from stage '{$oldStage}'.", 400);
            }

            $docAadhar   = (int)(bool) post('doc_aadhar');
            $docMobile   = (int)(bool) post('doc_registered_mobile');
            $docKseb     = (int)(bool) post('doc_kseb_bill');
            $docPassbook = (int)(bool) post('doc_bank_passbook');

            $this->pdo->prepare(
                "UPDATE `leads` SET `stage`='Won', `doc_aadhar`=?, `doc_registered_mobile`=?,
                        `doc_kseb_bill`=?, `doc_bank_passbook`=?, `updated_at`=NOW() WHERE `id`=?"
            )->execute([$docAadhar, $docMobile, $docKseb, $docPassbook, $leadId]);

            $this->pdo->prepare('INSERT IGNORE INTO `installations` (`lead_id`) VALUES (?)')->execute([$leadId]);
            $opCheck = $this->pdo->prepare('SELECT COUNT(*) FROM `operations` WHERE `lead_id`=?');
            $opCheck->execute([$leadId]);
            if ((int) $opCheck->fetchColumn() === 0) {
                $this->pdo->prepare(
                    'INSERT INTO `operations` (`lead_id`,`operation_type`,`scheduled_at`,`interval_days`) VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 90 DAY),120)'
                )->execute([$leadId, 'Cleaning']);
            }

            auditStageChange($leadId, $oldStage, 'Won');
            createWorkflowTasks($leadId, 'Won', $this->pdo);

            $recipients = [];
            if ($lead['assigned_rep_id']) $recipients[] = (int) $lead['assigned_rep_id'];
            sendPushToUsers($recipients, 'Lead Won!', $lead['name'], $leadUrl);
            sendPushToRole('manager', 'Lead Won!', $lead['name'], $leadUrl);

        } else { // lost
            $forceAllowed = in_array($role, ['admin', 'manager'], true);
            if (!$forceAllowed && !in_array('Lost', allowedNextStages($oldStage, $role), true)) {
                jsonError("Cannot mark Lost from stage '{$oldStage}'.", 400);
            }

            $reason = trim((string) post('status_reason_code')) ?: null;
            $notes  = trim((string) post('status_reason_notes')) ?: null;

            $this->pdo->prepare(
                "UPDATE `leads` SET `stage`='Lost', `status_reason_code`=?, `status_reason_notes`=?, `updated_at`=NOW() WHERE `id`=?"
            )->execute([$reason, $notes, $leadId]);

            auditStageChange($leadId, $oldStage, 'Lost');
            sendPushToRole('manager', 'Lead Lost', $lead['name'], $leadUrl);
        }

        jsonResponse(['ok' => true]);
    }

    public function delete(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('lead_id');
        if ($id <= 0) {
            jsonError('Invalid lead.', 400);
        }

        $this->pdo->prepare(
            'UPDATE `leads` SET `deleted_at` = NOW() WHERE `id` = ? AND `deleted_at` IS NULL'
        )->execute([$id]);

        jsonResponse(['ok' => true]);
    }
}
