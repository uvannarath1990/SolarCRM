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
            // Use the documentation user already linked to the lead
            if (!empty($lead['documentation_user_id'])) {
                return (int) $lead['documentation_user_id'];
            }
            // Fall back to first active documentation user in the system
            $row = $pdo->query(
                "SELECT `id` FROM `users` WHERE `role`='documentation' AND `is_active`=1 ORDER BY `id` LIMIT 1"
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
