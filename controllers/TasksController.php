<?php
declare(strict_types=1);

class TasksController
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

        // For reassign dropdown AND user filter (admin/manager/operation_manager only)
        $allUsers = [];
        $isAdminMgr = in_array($role, ['admin', 'manager', 'operation_manager'], true);
        if ($isAdminMgr) {
            $allUsers = $this->pdo->query(
                "SELECT `id`,`name`,`role` FROM `users` WHERE `is_active`=1 ORDER BY `name`"
            )->fetchAll();
        }

        // Filter params (admin/manager/operation_manager only)
        $filterUserId   = $isAdminMgr ? (int)($_GET['filter_user'] ?? 0) : 0;
        $filterDateFrom = $isAdminMgr ? (trim($_GET['filter_date_from'] ?? '')) : '';
        $filterDateTo   = $isAdminMgr ? (trim($_GET['filter_date_to']   ?? '')) : '';

        $tasks            = $this->buildTasks($role, $userId, $filterUserId, $filterDateFrom, $filterDateTo);
        $completedTasks   = $this->buildCompletedWorkflowTasks($role, $userId);
        $overdueFollowups = $this->buildOverdueFollowups($role, $userId);

        $pageTitle = 'My Tasks';
        require __DIR__ . '/../views/tasks/index.php';
    }

    public function notificationsJson(): void
    {
        requireLogin();

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];
        $tasks  = $this->buildTasks($role, $userId);

        $urgent = array_filter($tasks, fn($t) =>
            $t['date'] !== null && $t['date'] <= date('Y-m-d') && !($t['done'] ?? false)
        );

        jsonResponse([
            'count' => count($urgent),
            'items' => array_values(array_slice($urgent, 0, 8)),
        ]);
    }

    // ─── Build completed workflow tasks (last 60 days) ────────────────────────

    private function buildCompletedWorkflowTasks(string $role, int $userId): array
    {
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        if ($isAdmin) {
            $stmt = $this->pdo->query(
                "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`,
                        wt.`status`, wt.`completed_at`, wt.`outcome`,
                        wt.`assigned_role`,
                        l.`name` AS lead_name,
                        u.`name` AS completer_name
                 FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 LEFT JOIN `users` u ON u.`id` = wt.`completed_by`
                 WHERE wt.`status` IN ('done','skipped')
                   AND wt.`completed_at` >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                   AND l.`deleted_at` IS NULL
                 ORDER BY wt.`completed_at` DESC
                 LIMIT 100"
            );
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`,
                    wt.`status`, wt.`completed_at`, wt.`outcome`,
                    wt.`assigned_role`,
                    l.`name` AS lead_name,
                    u.`name` AS completer_name
             FROM `workflow_tasks` wt
             JOIN `leads` l ON l.`id` = wt.`lead_id`
             LEFT JOIN `users` u ON u.`id` = wt.`completed_by`
             WHERE wt.`status` IN ('done','skipped')
               AND wt.`completed_by` = ?
               AND wt.`completed_at` >= DATE_SUB(NOW(), INTERVAL 60 DAY)
               AND l.`deleted_at` IS NULL
             ORDER BY wt.`completed_at` DESC
             LIMIT 100"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // ─── Build overdue follow-up leads ────────────────────────────────────────

    private function buildOverdueFollowups(string $role, int $userId): array
    {
        $roleFilter = '';
        $params     = [];

        if ($role === 'sales_rep') {
            $roleFilter = 'AND l.`assigned_rep_id` = ?';
            $params[]   = $userId;
        } elseif ($role === 'tele_caller') {
            $roleFilter = 'AND (l.`created_by` = ? OR l.`tele_caller_id` = ?)';
            $params[]   = $userId;
            $params[]   = $userId;
        } elseif ($role === 'dealer') {
            $roleFilter = 'AND l.`created_by` = ?';
            $params[]   = $userId;
        }
        // admin / manager / operation_manager see all

        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`phone`, l.`phone_revealed`, l.`stage`,
                    l.`next_followup_date`,
                    rep.`name`  AS rep_name,
                    tc.`name`   AS tc_name
             FROM `leads` l
             LEFT JOIN `users` rep ON rep.`id` = l.`assigned_rep_id`
             LEFT JOIN `users` tc  ON tc.`id`  = l.`tele_caller_id`
             WHERE l.`next_followup_date` < CURDATE()
               AND l.`stage` NOT IN ('Won','Lost')
               AND l.`deleted_at` IS NULL
               {$roleFilter}
             ORDER BY l.`next_followup_date` ASC
             LIMIT 200"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // ─── Build unified task list ───────────────────────────────────────────────

    private function buildTasks(string $role, int $userId, int $filterUserId = 0, string $filterDateFrom = '', string $filterDateTo = ''): array
    {
        $tasks = [];

        // ── Pending reminders ───────────────────────────────────────────────
        $repFilter = in_array($role, ['admin', 'manager'], true) ? '' : 'AND r.`user_id` = ?';
        $stmt = $this->pdo->prepare(
            "SELECT r.`id`, r.`remind_at` AS date_raw, r.`message`, r.`is_done`,
                    l.`id` AS lead_id, l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed
             FROM `reminders` r
             JOIN `leads` l ON l.`id` = r.`lead_id`
             WHERE r.`is_done` = 0 {$repFilter}
             ORDER BY r.`remind_at` ASC
             LIMIT 100"
        );
        $params = in_array($role, ['admin', 'manager'], true) ? [] : [$userId];
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $tasks[] = [
                'id'        => 'rem-' . $row['id'],
                'raw_id'    => (int) $row['id'],
                'type'      => 'reminder',
                'icon'      => 'bi-bell-fill',
                'color'     => 'warning',
                'label'     => 'Reminder',
                'message'   => $row['message'],
                'lead_id'   => (int) $row['lead_id'],
                'lead_name' => $row['lead_name'],
                'lead_phone'=> $row['lead_phone'],
                'lead_phone_revealed' => $row['lead_phone_revealed'],
                'date'      => substr($row['date_raw'], 0, 10),
                'date_raw'  => $row['date_raw'],
                'done'      => false,
            ];
        }

        // ── Pending operations (admin / operation_manager only) ──────────────
        if (in_array($role, ['admin', 'operation_manager', 'accounts'], true)) {
            $stmt = $this->pdo->query(
                "SELECT o.`id`, o.`scheduled_at` AS date_raw, o.`operation_type`, o.`notes`,
                        l.`id` AS lead_id, l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed
                 FROM `operations` o
                 JOIN `leads` l ON l.`id` = o.`lead_id`
                 WHERE o.`done_at` IS NULL
                 ORDER BY o.`scheduled_at` ASC
                 LIMIT 100"
            );
            foreach ($stmt->fetchAll() as $row) {
                $isOverdue = $row['date_raw'] < date('Y-m-d');
                $tasks[] = [
                    'id'        => 'op-' . $row['id'],
                    'raw_id'    => (int) $row['id'],
                    'type'      => 'operation',
                    'icon'      => 'bi-wrench-adjustable-circle-fill',
                    'color'     => $isOverdue ? 'danger' : 'primary',
                    'label'     => $isOverdue ? 'Overdue Operation' : 'Operation',
                    'message'   => $row['operation_type'] . ($row['notes'] ? ' — ' . $row['notes'] : ''),
                    'lead_id'   => (int) $row['lead_id'],
                    'lead_name' => $row['lead_name'],
                    'lead_phone'=> $row['lead_phone'],
                    'lead_phone_revealed' => $row['lead_phone_revealed'],
                    'date'      => $row['date_raw'],
                    'date_raw'  => $row['date_raw'],
                    'done'      => false,
                ];
            }
        }

        // ── Workflow pipeline tasks ──────────────────────────────────────────
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        if ($isAdmin) {
            // "My Tasks" means the signed-in user's tasks, for a manager too.
            // Defaulting admins to every open task in the business turned this
            // page into a few hundred cards that belonged to other people.
            // ?scope=all restores the everything view, and the existing
            // filter_user still narrows to one person.
            $seeAll = (($_GET['scope'] ?? '') === 'all') || $filterUserId > 0;

            if ($filterUserId) {
                $wUserWhere  = 'AND wt.`assigned_to` = ?';
                $wUserParams = [$filterUserId];
            } elseif ($seeAll) {
                $wUserWhere  = '';
                $wUserParams = [];
            } else {
                $wUserWhere  = 'AND wt.`assigned_to` = ?';
                $wUserParams = [$userId];
            }
            $wStmt = $this->pdo->prepare(
                "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`, wt.`due_date`,
                        wt.`assigned_to`, wt.`assigned_role`, wt.`status`,
                        l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed,
                        u.`name` AS assignee_name
                 FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
                 WHERE wt.`status`='pending' AND l.`deleted_at` IS NULL {$wUserWhere}
                 ORDER BY wt.`due_date` ASC
                 LIMIT 200"
            );
            $wStmt->execute($wUserParams);
        } else {
            $wStmt = $this->pdo->prepare(
                "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`, wt.`due_date`,
                        wt.`assigned_to`, wt.`assigned_role`, wt.`status`,
                        l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed,
                        u.`name` AS assignee_name
                 FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
                 WHERE wt.`status`='pending' AND wt.`assigned_to`=? AND l.`deleted_at` IS NULL
                 ORDER BY wt.`due_date` ASC
                 LIMIT 100"
            );
            $wStmt->execute([$userId]);
        }

        foreach ($wStmt->fetchAll() as $row) {
            $isUnassigned = $row['assigned_to'] === null;
            $isOverdue    = $row['due_date'] && $row['due_date'] < date('Y-m-d');
            $tasks[] = [
                'id'        => 'wf-' . $row['id'],
                'raw_id'    => (int) $row['id'],
                'type'      => 'workflow',
                'icon'      => 'bi-diagram-3-fill',
                'color'     => $isUnassigned ? 'secondary' : ($isOverdue ? 'danger' : 'primary'),
                'label'     => $isUnassigned ? 'Needs Assignment' : 'Pipeline Task',
                'message'   => '[' . $row['stage'] . '] ' . $row['title'],
                'lead_id'   => (int) $row['lead_id'],
                'lead_name' => $row['lead_name'],
                'lead_phone'=> $row['lead_phone'],
                'lead_phone_revealed' => $row['lead_phone_revealed'],
                'date'      => $row['due_date'],
                'date_raw'  => $row['due_date'],
                'done'      => false,
                'assigned_to'   => $row['assigned_to'],
                'assigned_name' => $row['assignee_name'],
                'assigned_role' => $row['assigned_role'],
            ];
        }

        // Apply date range filter (admin/manager/operation_manager)
        if ($filterDateFrom !== '') {
            $tasks = array_values(array_filter($tasks, fn($t) => !empty($t['date']) && $t['date'] >= $filterDateFrom));
        }
        if ($filterDateTo !== '') {
            $tasks = array_values(array_filter($tasks, fn($t) => !empty($t['date']) && $t['date'] <= $filterDateTo));
        }

        // Sort all tasks by date ascending (nulls last)
        usort($tasks, fn($a, $b) => strcmp($a['date'] ?? '9999', $b['date'] ?? '9999'));

        return $tasks;
    }
}
