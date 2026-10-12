<?php
declare(strict_types=1);

/**
 * Workflow engine — automated solar lead pipeline.
 * Each stage entry creates tasks; completing all tasks auto-advances the stage.
 */

// ── Stage-to-next-stage map ───────────────────────────────────────────────────
function workflowNextStage(string $stage): ?string
{
    return [
        'New'                  => 'Contacted',
        'Contacted'            => 'Visit Scheduled',
        'Visit Scheduled'      => 'Site Visit Completed',  // checkin/checkout handled separately
        'Site Visit Completed' => 'Quoted',
        // 'Quoted' → Won is intentionally manual (business decision)
        // 'Won'   → no stage change; triggers installation task
    ][$stage] ?? null;
}

// ── Task templates per stage ──────────────────────────────────────────────────
function workflowTemplates(): array
{
    return [
        'New' => [
            ['title' => 'Review lead and assign to Tele Caller or Sales Rep', 'role' => 'manager', 'days' => 0],
        ],
        'Contacted' => [
            ['title' => 'Schedule site visit with customer', 'role' => 'sales_rep', 'days' => 0],
        ],
        'Visit Scheduled' => [
            ['title' => 'Conduct site visit (check in & check out in app)', 'role' => 'sales_rep', 'days' => 0],
        ],
        // 'Site Visit Completed' intentionally generates no task. The rep moves the
        // lead to Quoted themselves, which is where the quote amount is captured.
        'Quoted' => [
            ['title' => 'Follow up with customer and close the deal', 'role' => 'sales_rep', 'days' => 0],
        ],
        'Won' => [
            ['title' => 'Record financial details and collect advance payment', 'role' => 'operation_manager', 'days' => 0],
            ['title' => 'Submit feasibility certificate application',            'role' => 'operation_manager', 'days' => 0],
            ['title' => 'Submit Bank Documents',                                'role' => 'operation_manager', 'days' => 0],
            ['title' => 'Schedule installation date and assign installer',       'role' => 'operation_manager', 'days' => 0],
        ],
        'Installation' => [
            ['title' => 'Complete solar panel installation', 'role' => 'installer', 'days' => 0],
        ],
    ];
}

// ── Resolve which user handles a role for a given lead ───────────────────────
function resolveWorkflowAssignee(string $role, array $lead, PDO $pdo): ?int
{
    switch ($role) {
        case 'tele_caller':
            return $lead['tele_caller_id'] ? (int) $lead['tele_caller_id'] : null;

        case 'sales_rep':
            return $lead['assigned_rep_id'] ? (int) $lead['assigned_rep_id'] : null;

        case 'manager':
            // 1. Try assigned rep's manager (post-assignment path)
            if (!empty($lead['assigned_rep_id'])) {
                $s = $pdo->prepare('SELECT `manager_id` FROM `users` WHERE `id`=? LIMIT 1');
                $s->execute([(int) $lead['assigned_rep_id']]);
                $row = $s->fetch();
                if ($row && $row['manager_id']) return (int) $row['manager_id'];
            }
            // 2. Creator's manager (works for freshly created leads)
            if (!empty($lead['created_by'])) {
                $s = $pdo->prepare('SELECT `manager_id` FROM `users` WHERE `id`=? LIMIT 1');
                $s->execute([(int) $lead['created_by']]);
                $row = $s->fetch();
                if ($row && $row['manager_id']) return (int) $row['manager_id'];
            }
            // 3. First active manager, then first active admin as last resort
            $row = $pdo->query(
                "SELECT `id` FROM `users`
                 WHERE `role` IN ('manager','admin') AND `is_active`=1
                 ORDER BY (`role`='manager') DESC, `id`
                 LIMIT 1"
            )->fetch();
            return $row ? (int) $row['id'] : null;

        case 'installer':
            // Try the installer already assigned in the installations table
            $stmt = $pdo->prepare('SELECT `installer_id` FROM `installations` WHERE `lead_id`=? LIMIT 1');
            $stmt->execute([(int) $lead['id']]);
            $row = $stmt->fetch();
            return $row && $row['installer_id'] ? (int) $row['installer_id'] : null;

        case 'operation_manager':
            $row = $pdo->query(
                "SELECT `id` FROM `users` WHERE `role`='operation_manager' AND `is_active`=1 ORDER BY `id` LIMIT 1"
            )->fetch();
            return $row ? (int) $row['id'] : null;

        case 'documentation':
            // An explicit assignment on the lead always wins.
            if (!empty($lead['documentation_user_id'])) {
                return (int) $lead['documentation_user_id'];
            }
            // Otherwise the documentation user with the fewest open tasks.
            // Taking the first user by id instead left one person holding 25
            // open tasks while a colleague held none. The join to `leads`
            // keeps tasks on deleted leads out of the count, and the id
            // tiebreak keeps the choice deterministic (and so testable).
            $row = $pdo->query(
                "SELECT u.`id`
                   FROM `users` u
                   LEFT JOIN `workflow_tasks` wt
                          ON wt.`assigned_to`   = u.`id`
                         AND wt.`assigned_role` = 'documentation'
                         AND wt.`status`        = 'pending'
                   LEFT JOIN `leads` l
                          ON l.`id` = wt.`lead_id` AND l.`deleted_at` IS NULL
                  WHERE u.`role` = 'documentation' AND u.`is_active` = 1
                  GROUP BY u.`id`
                  ORDER BY COUNT(l.`id`) ASC, u.`id` ASC
                  LIMIT 1"
            )->fetch();
            return $row ? (int) $row['id'] : null;

        default:
            // accounts → admin assigns manually
            return null;
    }
}

// ── Create workflow tasks for a stage (idempotent) ───────────────────────────
function createWorkflowTasks(int $leadId, string $stage, PDO $pdo): void
{
    // Before the early returns below, deliberately: all three routes to Won
    // funnel through here, and a lead that already has its Won tasks must
    // still pick up documentation. This makes the handoff self-healing.
    if ($stage === 'Won') startDocumentationHandoff($leadId, $pdo);

    $templates = workflowTemplates();
    if (!isset($templates[$stage])) return;

    // Idempotency: skip if tasks already exist for this stage
    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM `workflow_tasks` WHERE `lead_id`=? AND `stage`=?'
    );
    $check->execute([$leadId, $stage]);
    if ((int) $check->fetchColumn() > 0) return;

    // Fetch lead for assignee resolution
    $stmt = $pdo->prepare(
        'SELECT `id`,`tele_caller_id`,`assigned_rep_id`,`created_by`,`documentation_user_id` FROM `leads` WHERE `id`=? LIMIT 1'
    );
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if (!$lead) return;

    $today = date('Y-m-d');
    $insert = $pdo->prepare(
        'INSERT INTO `workflow_tasks`
            (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
         VALUES (?,?,?,?,?,?)'
    );

    foreach ($templates[$stage] as $tpl) {
        $assignee = resolveWorkflowAssignee($tpl['role'], $lead, $pdo);
        $dueDate  = $tpl['days'] >= 0
            ? date('Y-m-d', strtotime($today . ' +' . $tpl['days'] . ' days'))
            : null;

        $insert->execute([
            $leadId,
            $stage,
            $tpl['title'],
            $assignee,
            $tpl['role'],
            $dueDate,
        ]);

        auditTaskCreate((int) $pdo->lastInsertId(), $leadId, $tpl['title'], 'workflow engine: ' . $stage);
    }
}

// ── Check if all tasks for current stage are done → auto-advance ──────────────
function checkAndAdvanceWorkflow(int $leadId, PDO $pdo): void
{
    // Get lead's current stage
    $stmt = $pdo->prepare('SELECT `stage` FROM `leads` WHERE `id`=? LIMIT 1');
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();
    if (!$lead) return;

    $currentStage = $lead['stage'];

    // Stages that should NOT auto-advance
    // 'Site Visit Completed' is a manual decision point: it has no tasks of its own,
    // so without this an unrelated task closing on the lead would satisfy the
    // "nothing pending" test and push it to Quoted with no quote amount recorded.
    $noAutoAdvance = ['Site Visit Completed', 'Quoted', 'Won', 'Lost', 'On Hold'];
    if (in_array($currentStage, $noAutoAdvance, true)) return;

    // Check if any tasks remain pending for this stage
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM `workflow_tasks`
         WHERE `lead_id`=? AND `stage`=? AND `status`='pending'"
    );
    $check->execute([$leadId, $currentStage]);
    if ((int) $check->fetchColumn() > 0) return;

    // All done — advance to next stage
    $nextStage = workflowNextStage($currentStage);
    if (!$nextStage) return;

    $pdo->prepare(
        "UPDATE `leads` SET `stage`=?, `updated_at`=NOW() WHERE `id`=?"
    )->execute([$nextStage, $leadId]);

    auditStageChange($leadId, $currentStage, $nextStage);

    // Create tasks for the next stage
    createWorkflowTasks($leadId, $nextStage, $pdo);

    // If Won just triggered → also trigger Installation tasks
    if ($nextStage === 'Won') {
        createWorkflowTasks($leadId, 'Won', $pdo);
    }
}

// ── Trigger Installation tasks when all Won tasks are done ────────────────────
function checkAndTriggerInstallation(int $leadId, PDO $pdo): void
{
    $check = $pdo->prepare(
        "SELECT COUNT(*) FROM `workflow_tasks`
         WHERE `lead_id`=? AND `stage`='Won' AND `status`='pending'"
    );
    $check->execute([$leadId]);
    if ((int) $check->fetchColumn() > 0) return;

    // All Won tasks done — create Installation task
    createWorkflowTasks($leadId, 'Installation', $pdo);
}

// ── Stage change with full side-effects ──────────────────────────────────────

/**
 * Move a lead to $newStage, applying everything a stage change implies:
 * audit entry, the new stage's workflow tasks, and — for Won — the installation
 * record and first cleaning operation.
 *
 * Returns null on success, or a short human-readable reason the lead was skipped.
 * Callers are responsible for permission checks; this only enforces data rules.
 */
function applyLeadStage(PDO $pdo, array $lead, string $newStage, ?string $lostReason = null): ?string
{
    $leadId   = (int) $lead['id'];
    $oldStage = (string) $lead['stage'];

    if (!in_array($newStage, allStages(), true)) {
        return "'{$newStage}' is not a valid stage";
    }
    if ($oldStage === $newStage) {
        return "already at {$newStage}";
    }
    // Same rule as single edits: Quoted carries a value or it isn't Quoted
    if ($newStage === 'Quoted' && (float) ($lead['quote_amount'] ?? 0) <= 0) {
        return 'no quote amount recorded';
    }

    if ($newStage === 'Lost' && $lostReason !== null && $lostReason !== '') {
        $pdo->prepare(
            'UPDATE `leads` SET `stage`=?, `status_reason_code`=?, `updated_at`=NOW() WHERE `id`=?'
        )->execute([$newStage, $lostReason, $leadId]);
        auditUpdate($leadId, 'status_reason_code', $lead['status_reason_code'] ?? null, $lostReason);
    } else {
        $pdo->prepare('UPDATE `leads` SET `stage`=?, `updated_at`=NOW() WHERE `id`=?')
            ->execute([$newStage, $leadId]);
    }

    auditStageChange($leadId, $oldStage, $newStage);

    if ($newStage === 'Won') {
        $pdo->prepare('INSERT IGNORE INTO `installations` (`lead_id`) VALUES (?)')->execute([$leadId]);

        $op = $pdo->prepare('SELECT COUNT(*) FROM `operations` WHERE `lead_id`=?');
        $op->execute([$leadId]);
        if ((int) $op->fetchColumn() === 0) {
            $pdo->prepare(
                'INSERT INTO `operations` (`lead_id`,`operation_type`,`scheduled_at`,`interval_days`)
                 VALUES (?,?,DATE_ADD(CURDATE(), INTERVAL 90 DAY),120)'
            )->execute([$leadId, 'Cleaning']);
        }
    }

    // Idempotent per lead+stage, so re-entering a stage never duplicates its tasks
    createWorkflowTasks($leadId, $newStage, $pdo);

    return null;
}

// ── Sales → Documentation handoff ────────────────────────────────────────────

/**
 * Hand a won lead to the documentation team.
 *
 * Creates the documentation task, picks an owner, starts the SLA clock and
 * notifies. Called from createWorkflowTasks() whenever a lead reaches Won, so
 * every route to Won is covered without touching a single controller.
 *
 * Deliberately NOT a workflowTemplates() row:
 *   - createWorkflowTasks() returns early once any task exists for a stage, so
 *     a template would never reach the leads that already have Won tasks;
 *   - every template is due the day it is made, and this one needs Won+3;
 *   - checkAndTriggerInstallation() counts pending tasks with stage='Won', so a
 *     documentation task carrying that stage would silently block installation
 *     scheduling for every won deal until documentation finished.
 *
 * That last point is why the task below uses stage='Documentation'. Changing it
 * to 'Won' would look tidier and would break the installation pipeline.
 *
 * Never throws: a documentation problem must not stop a rep recording a win.
 */
function startDocumentationHandoff(int $leadId, PDO $pdo): void
{
    try {
        // Idempotent across stage AND status, so the leads that already have a
        // hand-made documentation task are left alone, and finishing
        // documentation then re-touching Won never reopens the work.
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM `workflow_tasks`
              WHERE `lead_id`=? AND `assigned_role`='documentation'"
        );
        $check->execute([$leadId]);
        if ((int) $check->fetchColumn() > 0) return;

        $stmt = $pdo->prepare(
            'SELECT `id`,`name`,`stage`,`tele_caller_id`,`assigned_rep_id`,`created_by`,
                    `documentation_user_id`,`documentation_status`
             FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();
        if (!$lead || $lead['stage'] !== 'Won') return;

        $assignee = resolveWorkflowAssignee('documentation', $lead, $pdo);
        $title    = 'Collect and verify customer documents';
        $dueDate  = date('Y-m-d', strtotime('+3 days'));

        $pdo->prepare(
            'INSERT INTO `workflow_tasks`
                (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`)
             VALUES (?,?,?,?,?,?)'
        )->execute([$leadId, 'Documentation', $title, $assignee, 'documentation', $dueDate]);

        $taskId = (int) $pdo->lastInsertId();
        auditTaskCreate($taskId, $leadId, $title, 'handoff: Won');

        // Assignment write-back plus the clock, in one statement.
        // COALESCE never steals an assignment someone made by hand; the CASE
        // will not drag a lead back out of on_hold or completed.
        // `updated_at` = `updated_at` stops MySQL's ON UPDATE touching the
        // column — the "won this month" KPI reads it.
        $pdo->prepare(
            "UPDATE `leads`
                SET `documentation_user_id`    = COALESCE(`documentation_user_id`, ?),
                    `documentation_status`     = CASE WHEN `documentation_status` = 'not_started'
                                                      THEN 'in_progress' ELSE `documentation_status` END,
                    `documentation_started_at` = COALESCE(`documentation_started_at`, NOW()),
                    `updated_at`               = `updated_at`
              WHERE `id` = ?"
        )->execute([$assignee, $leadId]);

        if ($assignee && empty($lead['documentation_user_id'])) {
            auditUpdate($leadId, 'documentation_user_id', '', (string) $assignee);
        }
        if (($lead['documentation_status'] ?? 'not_started') === 'not_started') {
            auditUpdate($leadId, 'documentation_status', 'not_started', 'in_progress');
        }

        // Scoped to the handoff on purpose. Putting push inside
        // createWorkflowTasks() would fire four notifications for the four Won
        // operations tasks alone.
        // The explicit URL matters: sendPushToUser() defaults to
        // /employee/today, which is v1 — the doc team would land in the wrong app.
        $url = BASE_URL . '/doc-portal/lead?id=' . $leadId;
        if ($assignee) {
            sendPushToUser($assignee, 'Documentation assigned', (string) $lead['name'], $url);
        }
        $heads = documentationHeadUserIds($pdo);
        if ($heads) {
            sendPushToUsers($heads, 'New lead for documentation', (string) $lead['name'], $url);
        }
    } catch (\Throwable $e) {
        // Swallowed by design — see the docblock.
        error_log('startDocumentationHandoff failed for lead ' . $leadId . ': ' . $e->getMessage());
    }
}

/**
 * Bring a lead back from sales and resume the documentation clock.
 *
 * The `documentation_status`='on_hold' guard makes this a no-op if a doc user
 * already released it by hand, so a double completion cannot double-count the
 * paused time.
 */
function releaseDocumentationHold(int $leadId, PDO $pdo): void
{
    try {
        $pdo->prepare(
            "UPDATE `leads`
                SET `doc_hold_seconds`     = `doc_hold_seconds`
                                           + COALESCE(TIMESTAMPDIFF(SECOND, `doc_hold_at`, NOW()), 0),
                    `documentation_status` = 'in_progress',
                    `doc_hold_at`          = NULL,
                    `doc_hold_reason`      = NULL,
                    `doc_hold_task_id`     = NULL,
                    `updated_at`           = `updated_at`
              WHERE `id` = ? AND `documentation_status` = 'on_hold'"
        )->execute([$leadId]);

        $stmt = $pdo->prepare(
            'SELECT `name`,`documentation_user_id` FROM `leads` WHERE `id`=? LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        if ($lead && $lead['documentation_user_id']) {
            sendPushToUser(
                (int) $lead['documentation_user_id'],
                'Back from sales',
                (string) $lead['name'],
                BASE_URL . '/doc-portal/lead?id=' . $leadId
            );
        }
    } catch (\Throwable $e) {
        error_log('releaseDocumentationHold failed for lead ' . $leadId . ': ' . $e->getMessage());
    }
}
