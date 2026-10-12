<?php
declare(strict_types=1);

class WorkflowController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
        require_once __DIR__ . '/../includes/workflow.php';
    }

    // ── Task detail page ──────────────────────────────────────────────────────
    public function detail(): void
    {
        requireLogin();

        $taskId = (int) ($_GET['id'] ?? 0);
        if ($taskId <= 0) {
            flash('danger', 'Invalid task.');
            redirect('/tasks');
        }

        $task = $this->pdo->prepare(
            "SELECT wt.*,
                    l.`name`     AS lead_name,
                    l.`phone`    AS lead_phone,
                    l.`email`    AS lead_email,
                    l.`location` AS lead_location,
                    l.`stage`    AS lead_stage,
                    a.`name`     AS assignee_name,
                    a.`role`     AS assignee_role,
                    c.`name`     AS completer_name
             FROM `workflow_tasks` wt
             JOIN `leads` l  ON l.`id`  = wt.`lead_id`
             LEFT JOIN `users` a ON a.`id` = wt.`assigned_to`
             LEFT JOIN `users` c ON c.`id` = wt.`completed_by`
             WHERE wt.`id` = ? LIMIT 1"
        );
        $task->execute([$taskId]);
        $task = $task->fetch();

        if (!$task) {
            flash('danger', 'Task not found.');
            redirect('/tasks');
        }

        $userId  = (int) $_SESSION['user_id'];
        $role    = $_SESSION['role'];
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        // Access: assignee or admin/manager
        if (!$isAdmin && (int) $task['assigned_to'] !== $userId) {
            flash('danger', 'Access denied.');
            redirect('/tasks');
        }

        $lostReasons = array_filter(
            array_map('trim', explode('|', getSetting('lost_reasons', ''))),
            fn($v) => $v !== ''
        );

        // Users eligible for reassignment (TC, SR, sales reps)
        $assignableUsers = $isAdmin ? $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users`
             WHERE `role` IN ('tele_caller','sales_rep') AND `is_active`=1
             ORDER BY `role`, `name`"
        )->fetchAll() : [];

        // Role-specific lists for manager assign buttons on this task page
        $teleCallerUsers    = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='tele_caller' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $salesRepUsers      = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $documentationUsers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $pageTitle = 'Task: ' . $task['title'];
        require __DIR__ . '/../views/workflow/detail.php';
    }

    // ── Mark a task done ──────────────────────────────────────────────────────
    public function completeTask(): void
    {
        requireLogin();
        csrfVerify();

        $taskId = postInt('task_id');
        $task   = $this->fetchTask($taskId);

        if (!$task) {
            jsonError('Task not found.', 404);
        }

        $userId  = (int) $_SESSION['user_id'];
        $role    = $_SESSION['role'];
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        if (!$isAdmin && (int) $task['assigned_to'] !== $userId) {
            jsonError('Access denied.', 403);
        }

        if ($task['status'] !== 'pending') {
            jsonError('Task is already ' . $task['status'] . '.', 400);
        }

        $leadId       = (int) $task['lead_id'];
        $assignedRole = $task['assigned_role'] ?? '';

        // Compute on-time flag: 1 = done on/before due_date, 0 = late, null = no due_date
        $wasOnTime = null;
        if ($task['due_date']) {
            $wasOnTime = (date('Y-m-d') <= $task['due_date']) ? 1 : 0;
        }

        // ── Outcome-gated completion: New/Visit Scheduled tasks for TC or SR ──
        // Tele-caller tasks are gated at any stage: they are raised against the lead's
        // stage at the time, so most sit at Visit Scheduled rather than New — gating only
        // New left tele callers with a bare "mark done" and no way to reschedule.
        $needsOutcome = $assignedRole === 'tele_caller'
                     || ($assignedRole === 'sales_rep'
                         && in_array($task['stage'], ['New', 'Visit Scheduled', 'Quoted'], true));

        if ($needsOutcome) {
            $outcome       = trim(post('outcome') ?? '');
            $siteVisitDate = post('outcome_site_visit_date') ?: null;
            $nextVisitDate = post('outcome_next_visit_date') ?: null;
            $lostReason    = trim(post('outcome_lost_reason') ?? '') ?: null;
            $quoteAmount   = post('quote_amount') !== '' && post('quote_amount') !== null
                           ? (float) post('quote_amount') : null;

            if ($assignedRole === 'tele_caller') {
                $valid = ['Arranged Site Visit', 'Reschedule Call', 'Lead Lost'];
                if (!in_array($outcome, $valid, true))
                    jsonError('Select a valid outcome.', 422);
                if ($outcome === 'Arranged Site Visit' && !$siteVisitDate)
                    jsonError('Site Visit Date is required.', 422);
                if ($outcome === 'Lead Lost' && !$lostReason)
                    jsonError('Lost Reason is required.', 422);
                if ($outcome === 'Reschedule Call') {
                    if (!$nextVisitDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextVisitDate))
                        jsonError('Choose the date to call the customer again.', 422);
                    if ($nextVisitDate < date('Y-m-d'))
                        jsonError('The new call date cannot be in the past.', 422);
                }
            }

            if ($assignedRole === 'sales_rep' && $task['stage'] === 'New') {
                $valid = ['Site Visit Pending', 'Site Visit Postponed', 'Lead Lost', 'Lead Won'];
                if (!in_array($outcome, $valid, true))
                    jsonError('Select a valid outcome.', 422);
                if ($outcome === 'Site Visit Postponed' && !$nextVisitDate)
                    jsonError('Next Site Visit Date is required.', 422);
                if ($outcome === 'Lead Lost' && !$lostReason)
                    jsonError('Lost Reason is required.', 422);
            }

            if ($assignedRole === 'sales_rep' && $task['stage'] === 'Visit Scheduled') {
                $valid = ['Site Visit Completed', 'Site Visit Rescheduled', 'Lead Lost', 'Lead Won'];
                if (!in_array($outcome, $valid, true))
                    jsonError('Select a valid outcome.', 422);
                if ($outcome === 'Site Visit Rescheduled' && !$nextVisitDate)
                    jsonError('New Visit Date is required.', 422);
                if ($outcome === 'Lead Lost' && !$lostReason)
                    jsonError('Lost Reason is required.', 422);
                // The quote is captured here rather than as a separate stage move,
                // so the rep records it while closing the visit.
                if ($outcome === 'Site Visit Completed' && ($quoteAmount === null || $quoteAmount <= 0))
                    jsonError('Quote amount is required to complete the site visit.', 422);
            }

            if ($assignedRole === 'sales_rep' && $task['stage'] === 'Quoted') {
                $valid = ['Lead Won', 'Lead Lost', 'Still Following Up'];
                if (!in_array($outcome, $valid, true))
                    jsonError('Select a valid outcome.', 422);
                if ($outcome === 'Lead Lost' && !$lostReason)
                    jsonError('Lost Reason is required.', 422);
                if ($outcome === 'Still Following Up' && !$nextVisitDate)
                    jsonError('Next follow-up date is required.', 422);
            }

            $this->pdo->prepare(
                "UPDATE `workflow_tasks`
                 SET `status`='done', `completed_by`=?, `completed_at`=NOW(), `was_on_time`=?,
                     `outcome`=?, `outcome_site_visit_date`=?,
                     `outcome_next_visit_date`=?, `outcome_lost_reason`=?
                 WHERE `id`=?"
            )->execute([$userId, $wasOnTime, $outcome, $siteVisitDate, $nextVisitDate, $lostReason, $taskId]);
            auditTaskUpdate($taskId, $leadId, 'status', 'pending', 'done');

            // The lead's actual stage — a task's stage is only the stage it was raised at
            $ls = $this->pdo->prepare('SELECT `stage`,`visit_scheduled_date` FROM `leads` WHERE `id`=?');
            $ls->execute([$leadId]);
            $leadNow      = $ls->fetch() ?: ['stage' => '', 'visit_scheduled_date' => null];
            $leadStageNow = (string) $leadNow['stage'];

            if ($outcome === 'Arranged Site Visit') {
                // Only an early-stage lead moves forward; a lead already past Visit
                // Scheduled keeps its stage and just gets the date — never dragged back.
                if (in_array($leadStageNow, ['New', 'Contacted'], true)) {
                    $this->pdo->prepare(
                        "UPDATE `leads` SET `visit_scheduled_date`=?, `stage`='Visit Scheduled',
                         `updated_at`=NOW() WHERE `id`=?"
                    )->execute([$siteVisitDate, $leadId]);
                    auditStageChange($leadId, $leadStageNow, 'Visit Scheduled');
                    createWorkflowTasks($leadId, 'Visit Scheduled', $this->pdo);
                } else {
                    $this->pdo->prepare(
                        "UPDATE `leads` SET `visit_scheduled_date`=?, `updated_at`=NOW() WHERE `id`=?"
                    )->execute([$siteVisitDate, $leadId]);
                }
                auditUpdate($leadId, 'visit_scheduled_date', $leadNow['visit_scheduled_date'], $siteVisitDate);

            } elseif ($outcome === 'Reschedule Call') {
                // Close this attempt and raise the same task again for the new day.
                // The lead itself is untouched — the customer simply asked for a later call.
                $this->pdo->prepare(
                    "INSERT INTO `workflow_tasks`
                        (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                     VALUES (?,?,?,?,'tele_caller',?)"
                )->execute([$leadId, $task['stage'], $task['title'], $task['assigned_to'], $nextVisitDate]);
                auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId, $task['title'],
                                'outcome: Reschedule Call (from task #' . $taskId . ')');

            } elseif ($outcome === 'Lead Lost') {
                $this->pdo->prepare(
                    "UPDATE `leads` SET `stage`='Lost', `lost_reason`=?, `updated_at`=NOW() WHERE `id`=?"
                )->execute([$lostReason, $leadId]);
                auditStageChange($leadId, $leadStageNow, 'Lost');

            } elseif ($outcome === 'Site Visit Postponed' || $outcome === 'Site Visit Rescheduled') {
                $this->pdo->prepare(
                    "UPDATE `leads` SET `next_site_visit_date`=?, `updated_at`=NOW() WHERE `id`=?"
                )->execute([$nextVisitDate, $leadId]);
                auditUpdate($leadId, 'visit_scheduled_date', null, $nextVisitDate);
                $followUpStage = $task['stage'] === 'Visit Scheduled' ? 'Visit Scheduled' : 'New';
                $followUpTitle = $task['stage'] === 'Visit Scheduled'
                    ? 'Site visit rescheduled — follow up with customer'
                    : 'Follow up: Site visit rescheduled';
                $this->pdo->prepare(
                    "INSERT INTO `workflow_tasks`
                     (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                     VALUES (?,?,?,?,?,?)"
                )->execute([
                    $leadId, $followUpStage,
                    $followUpTitle,
                    $task['assigned_to'], 'sales_rep',
                    $nextVisitDate,
                ]);
                auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId, $followUpTitle,
                                'outcome: ' . $outcome);

            } elseif ($outcome === 'Lead Won') {
                $this->pdo->prepare(
                    "UPDATE `leads` SET `site_visit_completed_date`=CURDATE(),
                     `stage`='Site Visit Completed', `updated_at`=NOW() WHERE `id`=?"
                )->execute([$leadId]);
                auditStageChange($leadId, 'New', 'Site Visit Completed');
                auditUpdate($leadId, 'site_visit_completed_date', null, date('Y-m-d'));
                createWorkflowTasks($leadId, 'Site Visit Completed', $this->pdo);

            } elseif ($outcome === 'Site Visit Completed') {
                // Visit done + quote recorded in one step, so the lead lands on
                // Quoted and picks up the follow-up-and-close task immediately.
                $this->pdo->prepare(
                    "UPDATE `leads` SET `site_visit_completed_date`=CURDATE(),
                     `quote_amount`=?, `stage`='Quoted', `updated_at`=NOW() WHERE `id`=?"
                )->execute([$quoteAmount, $leadId]);

                auditUpdate($leadId, 'site_visit_completed_date', null, date('Y-m-d'));
                auditUpdate($leadId, 'quote_amount', $task['lead_quote_amount'] ?? null, $quoteAmount);
                auditStageChange($leadId, 'Visit Scheduled', 'Quoted');

                createWorkflowTasks($leadId, 'Quoted', $this->pdo);
            }
            // ── Quoted stage: the rep closes the deal from the follow-up task ──
            if ($task['stage'] === 'Quoted' && $assignedRole === 'sales_rep') {
                if ($outcome === 'Lead Won') {
                    $this->pdo->prepare(
                        "UPDATE `leads` SET `stage`='Won', `updated_at`=NOW() WHERE `id`=?"
                    )->execute([$leadId]);
                    auditStageChange($leadId, 'Quoted', 'Won');

                    // Same side-effects as the Mark Won action on the lead page
                    $this->pdo->prepare(
                        'INSERT IGNORE INTO `installations` (`lead_id`) VALUES (?)'
                    )->execute([$leadId]);

                    $opCheck = $this->pdo->prepare('SELECT COUNT(*) FROM `operations` WHERE `lead_id`=?');
                    $opCheck->execute([$leadId]);
                    if ((int) $opCheck->fetchColumn() === 0) {
                        $this->pdo->prepare(
                            'INSERT INTO `operations` (`lead_id`,`operation_type`,`scheduled_at`,`interval_days`)
                             VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 90 DAY),120)'
                        )->execute([$leadId, 'Cleaning']);
                    }

                    createWorkflowTasks($leadId, 'Won', $this->pdo);

                    $leadRow = $this->pdo->prepare('SELECT `name`,`assigned_rep_id` FROM `leads` WHERE `id`=?');
                    $leadRow->execute([$leadId]);
                    $l = $leadRow->fetch();
                    $url = BASE_URL . '/leads/detail?id=' . $leadId;
                    if ($l && $l['assigned_rep_id']) {
                        sendPushToUsers([(int) $l['assigned_rep_id']], 'Lead Won!', $l['name'] ?? '', $url);
                    }
                    sendPushToRole('manager', 'Lead Won!', $l['name'] ?? '', $url);

                } elseif ($outcome === 'Lead Lost') {
                    $this->pdo->prepare(
                        "UPDATE `leads` SET `stage`='Lost', `lost_reason`=?, `updated_at`=NOW() WHERE `id`=?"
                    )->execute([$lostReason, $leadId]);
                    auditStageChange($leadId, 'Quoted', 'Lost');

                } elseif ($outcome === 'Still Following Up') {
                    // Keep the lead at Quoted and raise the next follow-up
                    $this->pdo->prepare(
                        "UPDATE `leads` SET `next_followup_date`=?, `updated_at`=NOW() WHERE `id`=?"
                    )->execute([$nextVisitDate, $leadId]);
                    auditUpdate($leadId, 'next_followup_date', null, $nextVisitDate);
                    $this->pdo->prepare(
                        "INSERT INTO `workflow_tasks`
                         (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
                         VALUES (?,'Quoted',?,?,'sales_rep',?)"
                    )->execute([
                        $leadId,
                        'Follow up with customer and close the deal',
                        $task['assigned_to'],
                        $nextVisitDate,
                    ]);
                    auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId,
                                    'Follow up with customer and close the deal',
                                    'outcome: Still Following Up');
                }
            }

            // 'Site Visit Pending' → task marked done, no lead-level side-effects.

        } else {
            // ── Standard completion for all other stages ──────────────────────
            $this->pdo->prepare(
                "UPDATE `workflow_tasks`
                 SET `status`='done', `completed_by`=?, `completed_at`=NOW(), `was_on_time`=?
                 WHERE `id`=?"
            )->execute([$userId, $wasOnTime, $taskId]);
            auditTaskUpdate($taskId, $leadId, 'status', 'pending', 'done');

            if ($task['stage'] === 'Won') {
                checkAndTriggerInstallation($leadId, $this->pdo);
            } else {
                checkAndAdvanceWorkflow($leadId, $this->pdo);
            }
        }

        $lead = $this->pdo->prepare('SELECT `stage` FROM `leads` WHERE `id`=? LIMIT 1');
        $lead->execute([$leadId]);
        $leadRow = $lead->fetch();

        jsonResponse([
            'ok'         => true,
            'task_id'    => $taskId,
            'lead_stage' => $leadRow['stage'] ?? null,
        ]);
    }

    // ── Skip a task (admin only) ──────────────────────────────────────────────
    public function skipTask(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $taskId = postInt('task_id');
        $task   = $this->fetchTask($taskId);

        if (!$task) {
            jsonError('Task not found.', 404);
        }
        if ($task['status'] !== 'pending') {
            jsonError('Task is already ' . $task['status'] . '.', 400);
        }

        $wasOnTime = $task['due_date'] ? (date('Y-m-d') <= $task['due_date'] ? 1 : 0) : null;

        $this->pdo->prepare(
            "UPDATE `workflow_tasks`
             SET `status`='skipped', `completed_by`=?, `completed_at`=NOW(), `was_on_time`=?
             WHERE `id`=?"
        )->execute([(int) $_SESSION['user_id'], $wasOnTime, $taskId]);
        auditTaskUpdate($taskId, (int)$task['lead_id'], 'status', 'pending', 'skipped');

        $leadId = (int) $task['lead_id'];

        if ($task['stage'] === 'Won') {
            checkAndTriggerInstallation($leadId, $this->pdo);
        } else {
            checkAndAdvanceWorkflow($leadId, $this->pdo);
        }

        $lead = $this->pdo->prepare('SELECT `stage` FROM `leads` WHERE `id`=? LIMIT 1');
        $lead->execute([$leadId]);
        $leadRow = $lead->fetch();

        jsonResponse([
            'ok'        => true,
            'task_id'   => $taskId,
            'lead_stage'=> $leadRow['stage'] ?? null,
        ]);
    }

    // ── Cancel a task (no pipeline advancement) ──────────────────────────────
    public function cancelTask(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $taskId = postInt('task_id');
        $task   = $this->fetchTask($taskId);

        if (!$task) jsonError('Task not found.', 404);
        if ($task['status'] !== 'pending') jsonError('Task is already ' . $task['status'] . '.', 400);

        $this->pdo->prepare(
            "UPDATE `workflow_tasks`
             SET `status`='cancelled', `completed_by`=?, `completed_at`=NOW()
             WHERE `id`=?"
        )->execute([(int) $_SESSION['user_id'], $taskId]);
        auditTaskUpdate($taskId, (int)$task['lead_id'], 'status', 'pending', 'cancelled');

        jsonResponse(['ok' => true, 'task_id' => $taskId]);
    }

    // ── Reopen a closed task (admin/manager only) ────────────────────────────
    public function reopenTask(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $taskId = postInt('task_id');
        $task   = $this->fetchTask($taskId);

        if (!$task) jsonError('Task not found.', 404);
        if ($task['status'] === 'pending') jsonError('Task is already pending.', 400);

        $oldStatus = $task['status'];

        $newTitle   = trim(post('title') ?: '') ?: null;
        $newDueDate = post('due_date') ?: null;
        if ($newDueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDueDate)) $newDueDate = null;
        $newNotes   = trim(post('notes') ?: '') ?: null;

        $this->pdo->prepare(
            "UPDATE `workflow_tasks`
             SET `status`='pending',
                 `completed_by`=NULL,
                 `completed_at`=NULL,
                 `was_on_time`=NULL,
                 `outcome`=NULL,
                 `outcome_site_visit_date`=NULL,
                 `outcome_next_visit_date`=NULL,
                 `outcome_lost_reason`=NULL,
                 `title`    = COALESCE(?, `title`),
                 `due_date` = ?,
                 `notes`    = COALESCE(?, `notes`)
             WHERE `id`=?"
        )->execute([$newTitle, $newDueDate, $newNotes, $taskId]);

        auditTaskUpdate($taskId, (int)$task['lead_id'], 'status', $oldStatus, 'pending');

        jsonResponse(['ok' => true, 'task_id' => $taskId]);
    }

    // ── Assign a task to a user (admin/manager only) ─────────────────────────
    public function assignTask(): void
    {
        requireRole('admin', 'manager', 'operation_manager');
        csrfVerify();

        $taskId = postInt('task_id');
        $userId = postInt('user_id') ?: null;

        $task = $this->fetchTask($taskId);
        if (!$task) {
            jsonError('Task not found.', 404);
        }

        $userRow = null;
        if ($userId) {
            $u = $this->pdo->prepare('SELECT `name`, `role` FROM `users` WHERE `id`=? LIMIT 1');
            $u->execute([$userId]);
            $userRow = $u->fetch() ?: null;
        }

        // Update assigned_to AND assigned_role so outcome logic fires correctly
        $this->pdo->prepare(
            'UPDATE `workflow_tasks` SET `assigned_to`=?, `assigned_role`=? WHERE `id`=?'
        )->execute([$userId, $userRow['role'] ?? $task['assigned_role'], $taskId]);

        // Audit the reassignment
        auditTaskUpdate($taskId, (int)$task['lead_id'], 'assigned_to', $task['assigned_to'], $userId);

        // Sync lead assignment fields so future stage tasks resolve to the right person
        if ($userId && $userRow) {
            if ($userRow['role'] === 'tele_caller') {
                $this->pdo->prepare(
                    'UPDATE `leads` SET `tele_caller_id`=?, `updated_at`=NOW() WHERE `id`=?'
                )->execute([$userId, $task['lead_id']]);
            } elseif ($userRow['role'] === 'sales_rep') {
                $this->pdo->prepare(
                    'UPDATE `leads` SET `assigned_rep_id`=?, `updated_at`=NOW() WHERE `id`=?'
                )->execute([$userId, $task['lead_id']]);
            }
            $leadUrl = BASE_URL . '/leads/detail?id=' . $task['lead_id'];
            sendPushToUser($userId, 'Task Assigned', $task['title'], $leadUrl);
        }

        jsonResponse(['ok' => true, 'task_id' => $taskId, 'assigned_name' => $userRow['name'] ?? null]);
    }

    // ── Manual task creation (admin/manager only) ────────────────────────────
    /**
     * Ad-hoc follow-up task raised from the lead page, assigned to any active user.
     *
     * Tagged with the stage 'Follow-up' rather than the lead's current stage on
     * purpose: checkAndAdvanceWorkflow() treats a pending task carrying the
     * current stage as unfinished work and refuses to advance the lead, so
     * reusing the stage here would silently stall pipelines.
     */
    public function createFollowUp(): void
    {
        requireLogin();
        csrfVerify();

        $leadId     = postInt('lead_id');
        $assignedTo = postInt('assigned_to') ?: null;
        $dueDate    = trim((string) post('due_date')) ?: null;
        $title      = trim((string) post('title')) ?: 'Follow up with customer';
        $notes      = trim((string) post('notes')) ?: null;

        if ($leadId <= 0) {
            jsonError('Invalid lead.', 400);
        }
        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            jsonError('Invalid due date.', 422);
        }
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead) jsonError('Lead not found.', 404);

        // Anyone who can work the lead can raise a follow-up on it
        $userId = (int) $_SESSION['user_id'];
        if (!canAccessLead($this->pdo, $lead)) {
            jsonError('Access denied.', 403);
        }

        $assigneeRole = null;
        if ($assignedTo) {
            $u = $this->pdo->prepare(
                "SELECT `id`,`name`,`role` FROM `users`
                 WHERE `id`=? AND `is_active`=1 AND `role` <> 'partner' LIMIT 1"
            );
            $u->execute([$assignedTo]);
            $assignee = $u->fetch();
            if (!$assignee) jsonError('Selected user is not available.', 422);
            $assigneeRole = $assignee['role'];
        }

        $this->pdo->prepare(
            "INSERT INTO `workflow_tasks`
                (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`,`notes`)
             VALUES (?,'Follow-up',?,?,?,?,?)"
        )->execute([$leadId, $title, $assignedTo, $assigneeRole, $dueDate, $notes]);

        $taskId = (int) $this->pdo->lastInsertId();
        auditTaskCreate($taskId, $leadId, $title, 'created by user');

        if ($assignedTo && $assignedTo !== $userId) {
            sendPushToUser(
                $assignedTo,
                'New follow-up assigned',
                $lead['name'] . ($dueDate ? ' — due ' . $dueDate : ''),
                BASE_URL . '/leads/detail?id=' . $leadId
            );
        }

        jsonResponse(['ok' => true, 'task_id' => $taskId]);
    }

    public function createTask(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $leadId     = postInt('lead_id');
        $taskType   = post('task_type');        // 'tele_caller' | 'sales_rep' | 'documentation'
        $assignedTo = postInt('assigned_user_id') ?: null;
        $dueDate    = post('due_date') ?: null;

        // Validate date format
        if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = null;
        }

        $leadStmt = $this->pdo->prepare(
            'SELECT `id`,`stage` FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $leadStmt->execute([$leadId]);
        $lead = $leadStmt->fetch();
        if (!$lead) jsonError('Lead not found.', 404);

        $titleMap = [
            'tele_caller'   => 'Qualify lead and arrange site visit',
            'sales_rep'     => 'Conduct site visit and follow up with customer',
            'documentation' => 'Prepare and submit documentation',
        ];
        if (!isset($titleMap[$taskType])) jsonError('Invalid task type.', 422);

        if ($assignedTo) {
            $u = $this->pdo->prepare(
                'SELECT `id`,`name`,`role` FROM `users` WHERE `id`=? AND `is_active`=1 LIMIT 1'
            );
            $u->execute([$assignedTo]);
            $user = $u->fetch();
            if (!$user || $user['role'] !== $taskType) jsonError('User role mismatch.', 422);
        }

        // If assigning a sales rep with a date the lead will move to 'Visit Scheduled',
        // so the task must carry that stage too — otherwise checkAndAdvanceWorkflow
        // finds no pending 'Visit Scheduled' tasks and wrongly advances the lead.
        $taskStage = ($taskType === 'sales_rep' && $dueDate) ? 'Visit Scheduled' : $lead['stage'];

        $this->pdo->prepare(
            "INSERT INTO `workflow_tasks` (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
             VALUES (?,?,?,?,?,?)"
        )->execute([$leadId, $taskStage, $titleMap[$taskType], $assignedTo, $taskType, $dueDate]);
        auditTaskCreate((int) $this->pdo->lastInsertId(), $leadId, $titleMap[$taskType],
                        'assigned by user');

        if ($assignedTo) {
            $fieldMap = [
                'tele_caller'   => 'tele_caller_id',
                'sales_rep'     => 'assigned_rep_id',
                'documentation' => 'documentation_user_id',
            ];
            if (isset($fieldMap[$taskType])) {
                $col = '`' . $fieldMap[$taskType] . '`';
                $this->pdo->prepare(
                    "UPDATE `leads` SET {$col}=?,`updated_at`=NOW() WHERE `id`=?"
                )->execute([$assignedTo, $leadId]);
            }

            // When assigning a sales rep with a visit date → move lead to Visit Scheduled
            if ($taskType === 'sales_rep' && $dueDate) {
                $oldStage = $lead['stage'];
                $this->pdo->prepare(
                    "UPDATE `leads` SET `visit_scheduled_date`=?, `stage`='Visit Scheduled', `updated_at`=NOW() WHERE `id`=?"
                )->execute([$dueDate, $leadId]);
                auditStageChange($leadId, $oldStage, 'Visit Scheduled');
                auditUpdate($leadId, 'visit_scheduled_date', null, $dueDate);
            }

            $leadUrl = BASE_URL . '/leads/detail?id=' . $leadId;
            sendPushToUser($assignedTo, 'Task Assigned', $titleMap[$taskType], $leadUrl);
        }

        jsonResponse(['ok' => true]);
    }

    public function rescheduleVisit(): void
    {
        requireRole('admin', 'manager', 'tele_caller');
        csrfVerify();

        $leadId  = postInt('lead_id');
        $newDate = post('new_date') ?: null;

        if (!$leadId || !$newDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $newDate)) {
            jsonError('Lead and a valid new date are required.', 422);
        }

        $leadStmt = $this->pdo->prepare(
            'SELECT `id`,`stage`,`assigned_rep_id`,`visit_scheduled_date` FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $leadStmt->execute([$leadId]);
        $lead = $leadStmt->fetch();
        if (!$lead) jsonError('Lead not found.', 404);

        // Close all pending sales_rep tasks on this lead
        $this->pdo->prepare(
            "UPDATE `workflow_tasks`
             SET `status`='skipped', `completed_by`=?, `completed_at`=NOW()
             WHERE `lead_id`=? AND `assigned_role`='sales_rep' AND `status`='pending'"
        )->execute([(int) $_SESSION['user_id'], $leadId]);

        // Create new task for the same sales rep
        $repId = $lead['assigned_rep_id'] ? (int) $lead['assigned_rep_id'] : null;
        $this->pdo->prepare(
            "INSERT INTO `workflow_tasks` (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
             VALUES (?,?,?,?,?,?)"
        )->execute([
            $leadId,
            'Visit Scheduled',
            'Conduct site visit (rescheduled)',
            $repId,
            'sales_rep',
            $newDate,
        ]);

        // Update visit date on the lead
        $oldDate = $lead['visit_scheduled_date'];
        $this->pdo->prepare(
            "UPDATE `leads` SET `visit_scheduled_date`=?, `stage`='Visit Scheduled', `updated_at`=NOW() WHERE `id`=?"
        )->execute([$newDate, $leadId]);
        auditUpdate($leadId, 'visit_scheduled_date', $oldDate, $newDate);

        if ($repId) {
            $leadUrl = BASE_URL . '/leads/detail?id=' . $leadId;
            sendPushToUser($repId, 'Site Visit Rescheduled', 'New date: ' . $newDate, $leadUrl);
        }

        jsonResponse(['ok' => true]);
    }

    public function setDueDate(): void
    {
        requireRole('admin', 'manager', 'operation_manager');
        csrfVerify();

        $taskId  = postInt('task_id');
        $dueDate = post('due_date') ?: null;

        if (!$taskId) jsonError('Task ID required.', 422);
        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            jsonError('Invalid date format.', 422);
        }

        $task = $this->fetchTask($taskId);
        if (!$task) jsonError('Task not found.', 404);
        if ($task['status'] !== 'pending') jsonError('Only pending tasks can be rescheduled.', 400);

        $this->pdo->prepare(
            'UPDATE `workflow_tasks` SET `due_date`=?, `reschedule_count`=`reschedule_count`+1 WHERE `id`=?'
        )->execute([$dueDate, $taskId]);

        auditTaskUpdate($taskId, (int)$task['lead_id'], 'due_date', $task['due_date'], $dueDate);

        jsonResponse(['ok' => true, 'task_id' => $taskId, 'due_date' => $dueDate]);
    }

    // ── Bulk task review page (admin/manager) ────────────────────────────────
    public function review(): void
    {
        requireRole('admin', 'manager');

        $filterStatus  = get('status', 'pending');
        $filterUser    = getInt('user');
        $filterStage   = get('stage');
        $filterOverdue = get('overdue');
        $search        = trim(get('q'));

        // Date filter — the user picks which of the task's dates it applies to.
        // Column names come from this whitelist only, never from the request.
        $dateFields = [
            'created'   => 'wt.`created_at`',
            'due'       => 'wt.`due_date`',
            'completed' => 'wt.`completed_at`',
        ];
        $dateField = array_key_exists(get('date_field'), $dateFields) ? get('date_field') : 'created';
        $dateFrom  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get('from')) ? get('from') : '';
        $dateTo    = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) get('to'))   ? get('to')   : '';
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        // Sort: closed tasks are most useful newest-first, pending by what's due soonest
        $sortOptions = [
            'due'       => 'wt.`due_date` IS NULL, wt.`due_date` ASC, wt.`id` ASC',
            'completed' => 'wt.`completed_at` IS NULL, wt.`completed_at` DESC, wt.`id` DESC',
            'created'   => 'wt.`created_at` DESC, wt.`id` DESC',
        ];
        $defaultSort = in_array($filterStatus, ['done', 'skipped', 'cancelled'], true) ? 'completed' : 'due';
        $sort        = array_key_exists(get('sort'), $sortOptions) ? get('sort') : $defaultSort;

        $page    = max(1, getInt('page', 1));
        $perPage = in_array(getInt('per_page'), [50, 100, 200], true) ? getInt('per_page') : 100;

        $where  = ['l.`deleted_at` IS NULL'];
        $params = [];

        if ($dateFrom !== '') {
            $where[]  = 'DATE(' . $dateFields[$dateField] . ') >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]  = 'DATE(' . $dateFields[$dateField] . ') <= ?';
            $params[] = $dateTo;
        }

        if (in_array($filterStatus, ['pending', 'done', 'skipped', 'cancelled'], true)) {
            $where[]  = 'wt.`status` = ?';
            $params[] = $filterStatus;
        }
        if ($filterUser > 0) {
            $where[]  = 'wt.`assigned_to` = ?';
            $params[] = $filterUser;
        } elseif ($filterUser === -1) {
            $where[] = 'wt.`assigned_to` IS NULL';
        }
        // 'Installation' and 'Follow-up' are task-only stages, absent from allStages()
        if ($filterStage !== ''
            && in_array($filterStage, array_merge(allStages(), ['Installation', 'Follow-up']), true)) {
            $where[]  = 'wt.`stage` = ?';
            $params[] = $filterStage;
        }
        if ($filterOverdue) {
            $where[] = "wt.`status`='pending' AND wt.`due_date` IS NOT NULL AND wt.`due_date` < CURDATE()";
        }
        if ($search !== '') {
            // Name, task title, phone or lead ID. Phones are stored as 10 bare digits,
            // so the search is normalised the same way — "+91 99460 44082" still matches.
            $conds    = ['l.`name` LIKE ?', 'wt.`title` LIKE ?'];
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";

            $digits = preg_replace('/\D/', '', $search);
            if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                $digits = substr($digits, 2);   // drop the country code
            }
            if (strlen($digits) >= 4) {
                $conds[]  = 'l.`phone` LIKE ?';
                $params[] = "%{$digits}%";
            }
            // "#774" or a bare number that is a lead ID
            if (preg_match('/^#?(\d{1,7})$/', $search, $m)) {
                $conds[]  = 'l.`id` = ?';
                $params[] = (int) $m[1];
            }

            $where[] = '(' . implode(' OR ', $conds) . ')';
        }

        $whereStr = implode(' AND ', $where);

        // Totals for the whole filter, not just this page — so the chips and the
        // "showing X of Y" line describe the real result set.
        $totalStmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS n,
                    SUM(wt.`status`='pending' AND wt.`due_date` < CURDATE())  AS overdue,
                    SUM(wt.`status`='pending' AND wt.`due_date` = CURDATE())  AS due_today,
                    SUM(wt.`assigned_to` IS NULL)                             AS unassigned,
                    SUM(wt.`due_date` IS NULL)                                AS no_date
             FROM `workflow_tasks` wt
             JOIN `leads` l ON l.`id` = wt.`lead_id`
             WHERE {$whereStr}"
        );
        $totalStmt->execute($params);
        $totals = $totalStmt->fetch() ?: ['n' => 0];
        $total  = (int) $totals['n'];
        $paging = paginate($total, $perPage, $page);

        $stmt = $this->pdo->prepare(
            "SELECT wt.`id`, wt.`title`, wt.`stage`, wt.`status`, wt.`due_date`,
                    wt.`assigned_to`, wt.`assigned_role`, wt.`notes`, wt.`created_at`,
                    wt.`completed_at`, wt.`was_on_time`,
                    l.`id` AS lead_id, l.`name` AS lead_name, l.`stage` AS lead_stage,
                    a.`name` AS assignee_name,
                    c.`name` AS completer_name
             FROM `workflow_tasks` wt
             JOIN `leads` l  ON l.`id` = wt.`lead_id`
             LEFT JOIN `users` a ON a.`id` = wt.`assigned_to`
             LEFT JOIN `users` c ON c.`id` = wt.`completed_by`
             WHERE {$whereStr}
             ORDER BY {$sortOptions[$sort]}
             LIMIT {$paging['per_page']} OFFSET {$paging['offset']}"
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();

        $allUsers = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `is_active`=1 AND `role` != 'partner' ORDER BY `name`"
        )->fetchAll();

        // Status counts for filter tabs
        $counts = $this->pdo->query(
            "SELECT wt.`status`, COUNT(*) AS c
             FROM `workflow_tasks` wt
             JOIN `leads` l ON l.`id` = wt.`lead_id`
             WHERE l.`deleted_at` IS NULL
             GROUP BY wt.`status`"
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        // Stage dropdown options — derived from the data, so stages that never
        // generate tasks (Lost, On Hold) don't show up as dead filter choices.
        $stageOptions = $this->pdo->query(
            "SELECT DISTINCT wt.`stage`
             FROM `workflow_tasks` wt
             JOIN `leads` l ON l.`id` = wt.`lead_id`
             WHERE l.`deleted_at` IS NULL AND wt.`stage` IS NOT NULL AND wt.`stage` != ''
             ORDER BY FIELD(wt.`stage`, 'New','Contacted','Visit Scheduled','Site Visit in Progress',
                                        'Site Visit Completed','Quoted','Won','Installation'), wt.`stage`"
        )->fetchAll(PDO::FETCH_COLUMN);

        $pageTitle = 'Task Review';
        require __DIR__ . '/../views/workflow/review.php';
    }

    // ── Bulk update tasks (admin/manager) ────────────────────────────────────
    public function bulkUpdate(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $changes = post('changes');
        if (!is_array($changes) || empty($changes)) {
            jsonError('No changes submitted.', 400);
        }
        if (count($changes) > 100) {
            jsonError('Too many changes in one request (max 100).', 400);
        }

        $userId  = (int) $_SESSION['user_id'];
        $updated = 0;
        $errors  = [];

        foreach ($changes as $ch) {
            $taskId = (int) ($ch['task_id'] ?? 0);
            if ($taskId <= 0) { $errors[] = 'Invalid task id.'; continue; }

            $task = $this->fetchTask($taskId);
            if (!$task) { $errors[] = "Task #{$taskId} not found."; continue; }

            $leadId = (int) $task['lead_id'];
            $sets   = [];
            $vals   = [];

            // Title
            if (array_key_exists('title', $ch)) {
                $title = trim((string) $ch['title']);
                if ($title !== '' && $title !== $task['title']) {
                    $sets[] = '`title`=?';
                    $vals[] = $title;
                    auditTaskUpdate($taskId, $leadId, 'title', $task['title'], $title);
                }
            }

            // Due date
            if (array_key_exists('due_date', $ch)) {
                $due = $ch['due_date'] ?: null;
                if ($due !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $due)) {
                    $errors[] = "Task #{$taskId}: invalid due date."; continue;
                }
                if ($due !== $task['due_date']) {
                    $sets[] = '`due_date`=?';
                    $vals[] = $due;
                    auditTaskUpdate($taskId, $leadId, 'due_date', $task['due_date'], $due);
                }
            }

            // Assignee
            if (array_key_exists('assigned_to', $ch)) {
                $newUser = (int) $ch['assigned_to'] ?: null;
                if ($newUser !== ((int) $task['assigned_to'] ?: null)) {
                    $newRole = $task['assigned_role'];
                    if ($newUser) {
                        $u = $this->pdo->prepare('SELECT `role` FROM `users` WHERE `id`=? AND `is_active`=1 LIMIT 1');
                        $u->execute([$newUser]);
                        $row = $u->fetch();
                        if (!$row) { $errors[] = "Task #{$taskId}: user not found."; continue; }
                        $newRole = $row['role'];
                    }
                    $sets[] = '`assigned_to`=?';
                    $vals[] = $newUser;
                    $sets[] = '`assigned_role`=?';
                    $vals[] = $newRole;
                    auditTaskUpdate($taskId, $leadId, 'assigned_to', $task['assigned_to'], $newUser);
                }
            }

            // Status
            if (array_key_exists('status', $ch)) {
                $newStatus = (string) $ch['status'];
                if (!in_array($newStatus, ['pending', 'done', 'skipped', 'cancelled'], true)) {
                    $errors[] = "Task #{$taskId}: invalid status."; continue;
                }
                if ($newStatus !== $task['status']) {
                    $sets[] = '`status`=?';
                    $vals[] = $newStatus;
                    if ($newStatus === 'pending') {
                        // Reopen: clear completion + outcome fields
                        $sets[] = '`completed_by`=NULL';
                        $sets[] = '`completed_at`=NULL';
                        $sets[] = '`was_on_time`=NULL';
                        $sets[] = '`outcome`=NULL';
                    } else {
                        $sets[] = '`completed_by`=?';
                        $vals[] = $userId;
                        $sets[] = '`completed_at`=NOW()';
                    }
                    auditTaskUpdate($taskId, $leadId, 'status', $task['status'], $newStatus);
                }
            }

            // Notes
            if (array_key_exists('notes', $ch)) {
                $notes = trim((string) $ch['notes']) ?: null;
                if ($notes !== $task['notes']) {
                    $sets[] = '`notes`=?';
                    $vals[] = $notes;
                }
            }

            if (empty($sets)) continue;

            $vals[] = $taskId;
            $this->pdo->prepare(
                'UPDATE `workflow_tasks` SET ' . implode(', ', $sets) . ' WHERE `id`=?'
            )->execute($vals);
            $updated++;
        }

        jsonResponse(['ok' => true, 'updated' => $updated, 'errors' => $errors]);
    }

    private function fetchTask(int $id): array|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `workflow_tasks` WHERE `id`=? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
