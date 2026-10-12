<?php
declare(strict_types=1);

class TodayController
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
        $today  = date('Y-m-d');

        $overdue   = [];
        $todayVisits   = [];
        $todayFollowups = [];
        $newLeads  = [];
        $toSchedule = [];
        $wonLeads  = [];

        if ($role === 'admin') {
            $overdue        = $this->queryOverdue(null);
            $todayVisits    = $this->queryTodayVisits(null);
            $todayFollowups = $this->queryTodayFollowups(null);
        } elseif ($role === 'sales_rep') {
            $overdue        = $this->queryOverdue($userId);
            $todayVisits    = $this->queryTodayVisits($userId);
            $todayFollowups = $this->queryTodayFollowups($userId);
        } elseif ($role === 'tele_caller') {
            $overdue        = $this->queryOverdueTele($userId);
            $newLeads       = $this->queryNewLeads($userId);
            $toSchedule     = $this->queryToSchedule($userId);
        } elseif ($role === 'installer') {
            $wonLeads       = $this->queryWonLeads($userId);
        }

        // Workflow tasks split into overdue / today / future
        $wfTasks       = $this->fetchMyWorkflowTasks($role, $userId);
        $wfOverdue     = array_values(array_filter($wfTasks, fn($t) => $t['due_date'] && $t['due_date'] < $today));
        $wfToday       = array_values(array_filter($wfTasks, fn($t) => $t['due_date'] === $today));
        $wfFuture      = array_values(array_filter($wfTasks, fn($t) => !$t['due_date'] || $t['due_date'] > $today));

        // KPI tiles (role-specific)
        $kpis = $this->buildKpis($role, $userId);

        // Overdue operations count (admin + installer + sales_rep)
        $overdueOps = 0;
        if (in_array($role, ['admin', 'installer', 'sales_rep'], true)) {
            $repFilter = ($role === 'installer') ? ' AND l.`assigned_rep_id` = ' . $userId : '';
            $overdueOps = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM `operations` o
                 JOIN `leads` l ON l.`id` = o.`lead_id`
                 WHERE o.`done_at` IS NULL
                   AND o.`scheduled_at` < CURDATE()
                   AND l.`stage` = 'Won'{$repFilter}"
            )->fetchColumn();
        }

        $pageTitle = 'Today';

        // A manager's Today is a different job from a rep's. Showing them the
        // rep queue meant 199 red cards — every open task in the business — which
        // is a firehose, not a to-do list. They get the shape of the backlog and
        // who is carrying it instead.
        if (in_array($role, ['admin', 'manager', 'operation_manager'], true)) {
            $overview = $this->buildManagerOverview($userId);
            require __DIR__ . '/../views/today/manager.php';
            return;
        }

        require __DIR__ . '/../views/today/index.php';
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    private function queryOverdue(?int $repId): array
    {
        $extra = $repId ? 'AND l.`assigned_rep_id` = ' . $repId : '';
        return $this->pdo->query(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`,l.`next_followup_date`,
                    u.`name` AS rep_name
             FROM `leads` l
             LEFT JOIN `users` u ON u.`id` = l.`assigned_rep_id`
             WHERE l.`next_followup_date` < CURDATE()
               AND l.`stage` NOT IN ('Won','Lost')
               {$extra}
             ORDER BY l.`next_followup_date` ASC
             LIMIT 50"
        )->fetchAll();
    }

    private function queryOverdueTele(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`,l.`next_followup_date`
             FROM `leads` l
             WHERE l.`next_followup_date` < CURDATE()
               AND l.`stage` NOT IN ('Won','Lost')
               AND l.`created_by` = ?
             ORDER BY l.`next_followup_date` ASC LIMIT 50"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private function queryTodayVisits(?int $repId): array
    {
        $extra = $repId ? 'AND l.`assigned_rep_id` = ' . $repId : '';
        return $this->pdo->query(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`,
                    l.`visit_scheduled_date`,l.`potential_kw`,
                    u.`name` AS rep_name
             FROM `leads` l
             LEFT JOIN `users` u ON u.`id` = l.`assigned_rep_id`
             WHERE l.`visit_scheduled_date` = CURDATE()
               AND l.`stage` IN ('Visit Scheduled','Site Visit in Progress')
               {$extra}
             ORDER BY l.`visit_scheduled_date` ASC
             LIMIT 30"
        )->fetchAll();
    }

    private function queryTodayFollowups(?int $repId): array
    {
        $extra = $repId ? 'AND l.`assigned_rep_id` = ' . $repId : '';
        return $this->pdo->query(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`,l.`next_followup_date`
             FROM `leads` l
             WHERE l.`next_followup_date` = CURDATE()
               AND l.`stage` NOT IN ('Won','Lost')
               {$extra}
             ORDER BY l.`id` ASC
             LIMIT 30"
        )->fetchAll();
    }

    private function queryNewLeads(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`,l.`created_at`
             FROM `leads` l
             WHERE l.`stage` = 'New' AND l.`created_by` = ?
             ORDER BY l.`created_at` DESC LIMIT 20"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private function queryToSchedule(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`,l.`name`,l.`phone`,l.`phone_revealed`,l.`location`,l.`stage`
             FROM `leads` l
             WHERE l.`stage` = 'Contacted'
               AND l.`visit_scheduled_date` IS NULL
               AND l.`created_by` = ?
             ORDER BY l.`updated_at` ASC LIMIT 20"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private function queryWonLeads(?int $installerId): array
    {
        $extra = $installerId ? 'AND i.`installer_id` = ' . $installerId : '';
        return $this->pdo->query(
            "SELECT l.`id`,l.`name`,l.`location`,l.`potential_kw`,
                    i.`status` AS install_status, i.`scheduled_date`
             FROM `leads` l
             JOIN `installations` i ON i.`lead_id` = l.`id`
             WHERE l.`stage` = 'Won'
               AND i.`status` != 'completed'
               {$extra}
             ORDER BY i.`scheduled_date` ASC
             LIMIT 30"
        )->fetchAll();
    }

    private function fetchMyWorkflowTasks(string $role, int $userId): array
    {
        $isAdmin = in_array($role, ['admin', 'manager'], true);

        if ($isAdmin) {
            $stmt = $this->pdo->query(
                "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`,
                        wt.`due_date`, wt.`assigned_role`, wt.`assigned_to`,
                        l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed, l.`location` AS lead_location,
                        u.`name` AS assignee_name
                 FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
                 WHERE wt.`status` = 'pending' AND l.`deleted_at` IS NULL
                 ORDER BY wt.`due_date` ASC, wt.`id` ASC
                 LIMIT 200"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "SELECT wt.`id`, wt.`lead_id`, wt.`stage`, wt.`title`,
                        wt.`due_date`, wt.`assigned_role`, wt.`assigned_to`,
                        l.`name` AS lead_name, l.`phone` AS lead_phone, l.`phone_revealed` AS lead_phone_revealed, l.`location` AS lead_location,
                        u.`name` AS assignee_name
                 FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
                 WHERE wt.`status` = 'pending'
                   AND wt.`assigned_to` = ?
                   AND l.`deleted_at` IS NULL
                 ORDER BY wt.`due_date` ASC, wt.`id` ASC
                 LIMIT 100"
            );
            $stmt->execute([$userId]);
        }

        return $stmt->fetchAll();
    }

    private function buildKpis(string $role, int $userId): array
    {
        $pdo = $this->pdo;

        if ($role === 'admin') {
            $total   = (int) $pdo->query("SELECT COUNT(*) FROM `leads`")->fetchColumn();
            $wonMonth = (int) $pdo->query(
                "SELECT COUNT(*) FROM `leads` WHERE `stage`='Won'
                  AND MONTH(`updated_at`)=MONTH(NOW()) AND YEAR(`updated_at`)=YEAR(NOW())"
            )->fetchColumn();
            $pipeline = (float) ($pdo->query(
                "SELECT SUM(`quote_amount`) FROM `leads` WHERE `stage` NOT IN ('Won','Lost')"
            )->fetchColumn() ?? 0);
            $overdueCt = (int) $pdo->query(
                "SELECT COUNT(*) FROM `leads`
                  WHERE `next_followup_date` < CURDATE() AND `stage` NOT IN ('Won','Lost')"
            )->fetchColumn();
            return compact('total', 'wonMonth', 'pipeline', 'overdueCt');
        }

        if ($role === 'sales_rep') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `leads` WHERE `assigned_rep_id`=? AND `stage` NOT IN ('Won','Lost')"
            );
            $stmt->execute([$userId]);
            $total = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `leads` WHERE `assigned_rep_id`=? AND `stage`='Won'
                  AND MONTH(`updated_at`)=MONTH(NOW()) AND YEAR(`updated_at`)=YEAR(NOW())"
            );
            $stmt->execute([$userId]);
            $wonMonth = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT SUM(`quote_amount`) FROM `leads`
                  WHERE `assigned_rep_id`=? AND `stage` NOT IN ('Won','Lost')"
            );
            $stmt->execute([$userId]);
            $pipeline = (float) ($stmt->fetchColumn() ?? 0);

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `leads`
                  WHERE `assigned_rep_id`=? AND `next_followup_date` < CURDATE()
                    AND `stage` NOT IN ('Won','Lost')"
            );
            $stmt->execute([$userId]);
            $overdueCt = (int) $stmt->fetchColumn();
            return compact('total', 'wonMonth', 'pipeline', 'overdueCt');
        }

        if ($role === 'tele_caller') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `created_by`=?");
            $stmt->execute([$userId]);
            $total = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `created_by`=? AND `stage`='New'");
            $stmt->execute([$userId]);
            $newCt = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `leads`
                  WHERE `created_by`=? AND `stage`='Contacted' AND `visit_scheduled_date` IS NULL"
            );
            $stmt->execute([$userId]);
            $toSchedCt = (int) $stmt->fetchColumn();
            return compact('total', 'newCt', 'toSchedCt');
        }

        if ($role === 'installer') {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `installations` i JOIN `leads` l ON l.`id`=i.`lead_id`
                  WHERE i.`installer_id`=? AND i.`status` != 'completed'"
            );
            $stmt->execute([$userId]);
            $pending = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `installations` WHERE `installer_id`=? AND `status`='completed'"
            );
            $stmt->execute([$userId]);
            $done = (int) $stmt->fetchColumn();
            return compact('pending', 'done');
        }

        return [];
    }

    /**
     * The manager view: where work is stuck and who is carrying it.
     *
     * Deliberately aggregate. The per-task list already exists in Task Review;
     * what a manager cannot get anywhere today is the shape of the backlog.
     */
    private function buildManagerOverview(int $userId): array
    {
        $pdo = $this->pdo;

        $base = "FROM `workflow_tasks` wt
                 JOIN `leads` l ON l.`id` = wt.`lead_id`
                 WHERE wt.`status`='pending' AND l.`deleted_at` IS NULL";

        $totals = $pdo->query(
            "SELECT COUNT(*) AS pending,
                    SUM(wt.`due_date` < CURDATE()) AS overdue,
                    SUM(wt.`assigned_to` IS NULL) AS unassigned,
                    MAX(CASE WHEN wt.`assigned_to` IS NULL
                             THEN DATEDIFF(CURDATE(), wt.`due_date`) END) AS unassigned_oldest
             {$base}"
        )->fetch() ?: [];

        // Where the backlog sits. A pile at one stage is a process problem, not
        // a person problem, and it reads completely differently.
        $byStage = $pdo->query(
            "SELECT wt.`stage`, COUNT(*) AS n,
                    SUM(wt.`due_date` < CURDATE()) AS overdue
             {$base}
             GROUP BY wt.`stage`
             HAVING overdue > 0
             ORDER BY overdue DESC"
        )->fetchAll();

        // Own FROM clause rather than {$base}: this one needs the users join,
        // and $base already ends in a WHERE.
        $byOwner = $pdo->query(
            "SELECT wt.`assigned_to` AS user_id,
                    COALESCE(u.`name`, 'Unassigned') AS name,
                    u.`role`,
                    COUNT(*) AS n,
                    SUM(wt.`due_date` < CURDATE()) AS overdue,
                    MAX(CASE WHEN wt.`due_date` < CURDATE()
                             THEN DATEDIFF(CURDATE(), wt.`due_date`) END) AS oldest_days
             FROM `workflow_tasks` wt
             JOIN `leads` l ON l.`id` = wt.`lead_id`
             LEFT JOIN `users` u ON u.`id` = wt.`assigned_to`
             WHERE wt.`status`='pending' AND l.`deleted_at` IS NULL
             GROUP BY wt.`assigned_to`, u.`name`, u.`role`
             HAVING overdue > 0
             ORDER BY overdue DESC
             LIMIT 12"
        )->fetchAll();

        // The manager's own queue, kept separate from the team's.
        $mine = $pdo->prepare(
            "SELECT COUNT(*) AS n, SUM(wt.`due_date` < CURDATE()) AS overdue
             {$base} AND wt.`assigned_to` = ?"
        );
        $mine->execute([$userId]);

        return [
            'totals'  => $totals,
            'byStage' => $byStage,
            'byOwner' => $byOwner,
            'mine'    => $mine->fetch() ?: ['n' => 0, 'overdue' => 0],
        ];
    }
}
