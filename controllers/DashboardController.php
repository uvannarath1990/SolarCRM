<?php
declare(strict_types=1);

class DashboardController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireRole('admin', 'manager', 'operation_manager');

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];

        $kpis              = $this->buildKpis($role, $userId);
        $taskStats         = $this->buildTaskStats();
        $leadStats         = $this->buildLeadStats();
        $taskAuditLog      = $this->buildTaskAuditLog();
        $taskAuditByUser   = $this->buildTaskAuditByUser();

        // Won/Lost by lead source — admin only
        $sourceOutcomes = hasRole('admin') ? $this->buildSourceOutcomes() : [];

        $pageTitle = 'Dashboard';
        $extraJs   = ['dashboard.js'];
        require __DIR__ . '/../views/dashboard/index.php';
    }

    /**
     * Won and Lost counts per lead source, with win rate.
     *
     * Grouped on source_id rather than name so a renamed source keeps its history,
     * and leads with no source fall into a single "Unknown" bucket instead of
     * silently vanishing from the totals.
     */
    private function buildSourceOutcomes(): array
    {
        $rows = $this->pdo->query(
            "SELECT COALESCE(ls.`name`, '— No source set —')            AS source_name,
                    COUNT(*)                                            AS total,
                    SUM(l.`stage` = 'Won')                              AS won,
                    SUM(l.`stage` = 'Lost')                             AS lost,
                    SUM(l.`stage` NOT IN ('Won','Lost'))                AS open,
                    SUM(CASE WHEN l.`stage` = 'Won'
                             THEN COALESCE(l.`potential_kw`, 0) ELSE 0 END) AS kw_won
             FROM `leads` l
             LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
             WHERE l.`deleted_at` IS NULL
             GROUP BY l.`source_id`, ls.`name`
             ORDER BY won DESC, total DESC"
        )->fetchAll();

        foreach ($rows as &$r) {
            $closed        = (int) $r['won'] + (int) $r['lost'];
            $r['win_rate'] = $closed > 0 ? round($r['won'] / $closed * 100, 1) : null;
            $r['kw_won']   = round((float) $r['kw_won'], 1);
        }
        unset($r);

        return $rows;
    }

    public function statsJson(): void
    {
        requireRole('admin', 'manager', 'operation_manager');

        $role   = $_SESSION['role'];
        $userId = (int) $_SESSION['user_id'];

        $isRep     = $role === 'sales_rep';
        $repFilter = $isRep ? 'AND l.`assigned_rep_id` = ?' : '';
        $repParams = $isRep ? [$userId] : [];

        // Stage distribution
        $s = $this->pdo->prepare(
            "SELECT `stage`, COUNT(*) AS cnt FROM `leads` l WHERE l.`deleted_at` IS NULL {$repFilter} GROUP BY `stage`"
        );
        $s->execute($repParams);
        $stageRows = $s->fetchAll();

        // Leads created per week for last 8 weeks
        $w = $this->pdo->prepare(
            "SELECT YEARWEEK(`created_at`,1) AS yw, COUNT(*) AS cnt
             FROM `leads` l WHERE l.`deleted_at` IS NULL {$repFilter}
               AND `created_at` >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
             GROUP BY yw ORDER BY yw ASC"
        );
        $w->execute($repParams);
        $weekRows = $w->fetchAll();

        // Rep performance (admin/manager only)
        $repRows = [];
        $tcRows  = [];
        if (in_array($role, ['admin', 'manager'], true)) {
            $repRows = $this->pdo->query(
                "SELECT u.`name`, COUNT(l.`id`) AS total,
                        SUM(l.`quote_amount`) AS pipeline
                 FROM `users` u
                 LEFT JOIN `leads` l ON l.`assigned_rep_id` = u.`id`
                 WHERE u.`role` = 'sales_rep' AND u.`is_active` = 1
                 GROUP BY u.`id`, u.`name`
                 ORDER BY total DESC"
            )->fetchAll();

            $tcRows = $this->pdo->query(
                "SELECT u.`name`,
                        COUNT(l.`id`)           AS total,
                        SUM(l.`stage`='Won')    AS won,
                        SUM(l.`stage`='Lost')   AS lost
                 FROM `users` u
                 LEFT JOIN `leads` l ON l.`tele_caller_id` = u.`id` AND l.`deleted_at` IS NULL
                 WHERE u.`role` = 'tele_caller' AND u.`is_active` = 1
                 GROUP BY u.`id`, u.`name`
                 ORDER BY total DESC"
            )->fetchAll();
        }

        // Operations completed per month (last 6 months)
        $opsMonthly = $this->pdo->query(
            "SELECT DATE_FORMAT(`done_at`,'%Y-%m') AS mo, COUNT(*) AS ct
             FROM `operations`
             WHERE `done_at` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
             GROUP BY mo ORDER BY mo ASC"
        )->fetchAll();

        // Won / Lost transitions per month (last 12 months), from the stage-change
        // trail — leads.updated_at only reflects the most recent edit, so it can't
        // tell you when a lead actually closed.
        $wonLostMonthly = $this->pdo->query(
            "SELECT DATE_FORMAT(a.`created_at`, '%Y-%m')  AS mo,
                    SUM(a.`new_value` = 'Won')            AS won,
                    SUM(a.`new_value` = 'Lost')           AS lost
             FROM `audit_log` a
             JOIN `leads` l ON l.`id` = a.`lead_id` AND l.`deleted_at` IS NULL
             WHERE a.`action` = 'STAGE_CHANGE'
               AND a.`new_value` IN ('Won','Lost')
               AND a.`created_at` >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY mo
             ORDER BY mo ASC"
        )->fetchAll();

        // Leads created per month — the denominator behind the won/lost line
        $createdMonthly = $this->pdo->query(
            "SELECT DATE_FORMAT(`created_at`, '%Y-%m') AS mo, COUNT(*) AS cnt
             FROM `leads`
             WHERE `deleted_at` IS NULL
               AND `created_at` >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY mo
             ORDER BY mo ASC"
        )->fetchAll();

        jsonResponse([
            'createdMonthly' => $createdMonthly,
            'stages'         => $stageRows,
            'weeks'          => $weekRows,
            'reps'           => $repRows,
            'tcs'            => $tcRows,
            'opsMonthly'     => $opsMonthly,
            'wonLostMonthly' => $wonLostMonthly,
        ]);
    }

    private function buildTaskStats(): array
    {
        // Per-user comprehensive performance stats
        $rows = $this->pdo->query(
            "SELECT u.`id`, u.`name`, u.`role`,
                    COUNT(wt.`id`)                                                                    AS total_assigned,
                    SUM(wt.`status` = 'pending')                                                      AS pending,
                    SUM(wt.`status` = 'done')                                                         AS done_count,
                    SUM(wt.`status` = 'skipped')                                                      AS skipped_count,
                    SUM(wt.`status` IN ('done','skipped'))                                            AS completed,
                    SUM(wt.`was_on_time` = 1)                                                         AS on_time_count,
                    SUM(wt.`was_on_time` = 0)                                                         AS late_count,
                    SUM(wt.`reschedule_count`)                                                        AS total_reschedules,
                    SUM(wt.`status` IN ('done','skipped')
                        AND wt.`completed_at` >= DATE_FORMAT(NOW(),'%Y-%m-01'))                       AS done_this_month,
                    SUM(wt.`status` IN ('done','skipped')
                        AND wt.`completed_at` >= DATE_SUB(CURDATE(), INTERVAL 7 DAY))                AS done_this_week
             FROM `users` u
             JOIN `workflow_tasks` wt ON wt.`assigned_to` = u.`id`
             WHERE u.`is_active` = 1
             GROUP BY u.`id`, u.`name`, u.`role`
             ORDER BY pending DESC, u.`name` ASC"
        )->fetchAll();

        $totalPending   = (int) $this->pdo->query("SELECT COUNT(*) FROM `workflow_tasks` WHERE `status`='pending'")->fetchColumn();
        $totalCompleted = (int) $this->pdo->query("SELECT COUNT(*) FROM `workflow_tasks` WHERE `status` IN ('done','skipped')")->fetchColumn();
        $unassigned     = (int) $this->pdo->query("SELECT COUNT(*) FROM `workflow_tasks` WHERE `status`='pending' AND `assigned_to` IS NULL")->fetchColumn();

        return compact('rows', 'totalPending', 'totalCompleted', 'unassigned');
    }

    private function buildLeadStats(): array
    {
        $stages = $this->pdo->query(
            "SELECT `stage`, COUNT(*) AS cnt FROM `leads` WHERE `deleted_at` IS NULL GROUP BY `stage` ORDER BY cnt DESC"
        )->fetchAll();

        $total    = (int) $this->pdo->query("SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL")->fetchColumn();
        $active   = (int) $this->pdo->query("SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL AND `stage` NOT IN ('Won','Lost')")->fetchColumn();
        $won      = (int) $this->pdo->query("SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL AND `stage`='Won'")->fetchColumn();
        $lost     = (int) $this->pdo->query("SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL AND `stage`='Lost'")->fetchColumn();
        $wonMonth = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM `leads` WHERE `deleted_at` IS NULL AND `stage`='Won'
             AND MONTH(`updated_at`)=MONTH(NOW()) AND YEAR(`updated_at`)=YEAR(NOW())"
        )->fetchColumn();

        return compact('stages', 'total', 'active', 'won', 'lost', 'wonMonth');
    }

    private function buildTaskAuditByUser(): array
    {
        return $this->pdo->query(
            "SELECT u.`name` AS user_name, u.`role`,
                    SUM(al.`field_name` = 'assigned_to') AS reassignments,
                    SUM(al.`field_name` = 'due_date')    AS date_updates,
                    COUNT(*)                             AS total_changes,
                    MAX(al.`created_at`)                 AS last_change_at
             FROM `audit_log` al
             JOIN `users` u ON u.`id` = al.`user_id`
             WHERE al.`action` = 'TASK_UPDATE'
             GROUP BY al.`user_id`, u.`name`, u.`role`
             ORDER BY total_changes DESC"
        )->fetchAll();
    }

    private function buildTaskAuditLog(): array
    {
        return $this->pdo->query(
            "SELECT al.`id`, al.`lead_id`, al.`field_name`, al.`old_value`, al.`new_value`,
                    al.`created_at`, al.`entity`,
                    l.`name` AS lead_name,
                    changer.`name` AS changed_by_name,
                    assignee_old.`name` AS old_user_name,
                    assignee_new.`name` AS new_user_name
             FROM `audit_log` al
             LEFT JOIN `leads`  l            ON l.`id`           = al.`lead_id`
             LEFT JOIN `users`  changer      ON changer.`id`     = al.`user_id`
             LEFT JOIN `users`  assignee_old ON assignee_old.`id` = al.`old_value`
             LEFT JOIN `users`  assignee_new ON assignee_new.`id` = al.`new_value`
             WHERE al.`action` = 'TASK_UPDATE'
             ORDER BY al.`created_at` DESC
             LIMIT 100"
        )->fetchAll();
    }

    private function buildKpis(string $role, int $userId): array
    {
        $pdo    = $this->pdo;
        $isRep  = $role === 'sales_rep';
        $extra  = $isRep ? 'AND assigned_rep_id = ?' : '';
        $p      = $isRep ? [$userId] : [];

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE deleted_at IS NULL AND stage NOT IN ('Won','Lost') {$extra}");
        $q->execute($p);
        $total = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE deleted_at IS NULL AND stage='Won' AND MONTH(updated_at)=MONTH(NOW()) AND YEAR(updated_at)=YEAR(NOW()) {$extra}");
        $q->execute($p);
        $wonMonth = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COALESCE(SUM(quote_amount),0) FROM `leads` WHERE deleted_at IS NULL AND stage NOT IN ('Won','Lost') {$extra}");
        $q->execute($p);
        $pipeline = (float) ($q->fetchColumn() ?? 0);

        $q = $pdo->prepare("SELECT COALESCE(AVG(quote_amount),0) FROM `leads` WHERE deleted_at IS NULL AND quote_amount IS NOT NULL {$extra}");
        $q->execute($p);
        $avgQuote = (float) ($q->fetchColumn() ?? 0);

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE deleted_at IS NULL AND stage IN ('Won','Lost') {$extra}");
        $q->execute($p);
        $totalAll = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE deleted_at IS NULL AND stage='Won' {$extra}");
        $q->execute($p);
        $won = (int) $q->fetchColumn();

        $conversion = $totalAll > 0 ? round($won / $totalAll * 100, 1) : 0;

        $opsOverdue = (int) $pdo->query(
            "SELECT COUNT(*) FROM `operations` o JOIN `leads` l ON l.`id`=o.`lead_id`
             WHERE o.`done_at` IS NULL AND o.`scheduled_at` < CURDATE() AND l.`stage`='Won'"
        )->fetchColumn();

        $opsDoneMonth = (int) $pdo->query(
            "SELECT COUNT(*) FROM `operations`
             WHERE MONTH(`done_at`)=MONTH(NOW()) AND YEAR(`done_at`)=YEAR(NOW())"
        )->fetchColumn();

        $opsUpcoming = (int) $pdo->query(
            "SELECT COUNT(*) FROM `operations` o JOIN `leads` l ON l.`id`=o.`lead_id`
             WHERE o.`done_at` IS NULL
               AND o.`scheduled_at` BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               AND l.`stage`='Won'"
        )->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `next_followup_date` < CURDATE() AND `stage` NOT IN ('Won','Lost') AND `deleted_at` IS NULL {$extra}");
        $q->execute($p);
        $overdueCt = (int) $q->fetchColumn();

        return compact('total', 'wonMonth', 'pipeline', 'avgQuote', 'conversion',
                       'opsOverdue', 'opsDoneMonth', 'opsUpcoming', 'overdueCt');
    }
}
