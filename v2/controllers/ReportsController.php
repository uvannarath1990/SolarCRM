<?php
declare(strict_types=1);

/**
 * Lead Review — funnel, velocity and per-person scorecards for admin/manager.
 *
 * Everything is driven off two sources: the current `leads` row, and the
 * STAGE_CHANGE trail in `audit_log`. Value metrics use `potential_kw` rather
 * than money, because quote_amount is not captured on historical leads.
 */
class ReportsController
{
    private PDO $pdo;

    /** Ordered pipeline used for funnel + velocity. Lost/On Hold sit outside it. */
    private const FUNNEL = [
        'New',
        'Contacted',
        'Visit Scheduled',
        'Site Visit in Progress',
        'Site Visit Completed',
        'Quoted',
        'Won',
    ];

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function leadReview(): void
    {
        requireRole('admin', 'manager');

        [$from, $to, $repId, $filters] = $this->readFilters();

        $leads      = $this->fetchLeads($from, $to, $repId);
        $funnel     = $this->buildFunnel($leads);
        $velocity   = $this->buildVelocity($leads);
        $stuck      = $this->buildStuck($leads);
        $repRows    = $this->buildRepScorecard($from, $to);
        $teleRows   = $this->buildTeleScorecard($from, $to);

        $allReps = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='sales_rep' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $pageTitle = 'Lead Review';
        require __DIR__ . '/../views/reports/lead_review.php';
    }


    // ─── Tele Caller Review ───────────────────────────────────────────────────

    public function teleCallerReview(): void
    {
        requireRole('admin', 'manager');

        [$from, $to, $tcId, $sourceId] = $this->readTeleFilters();

        $scorecard = $this->buildTeleScorecard($from, $to, $tcId);
        $taskStats = $this->buildTeleTaskStats($from, $to, $tcId);
        $outcomes  = $this->buildTeleOutcomes($from, $to, $tcId);
        $leads     = $tcId > 0 ? $this->buildTeleLeads($from, $to, $tcId, 300, $sourceId) : [];
        $matrix    = $this->buildTeleSourceMatrix($from, $to);

        $allTele = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='tele_caller' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $allSources = $this->pdo->query(
            "SELECT `id`,`name` FROM `lead_sources` ORDER BY `name`"
        )->fetchAll();

        $pageTitle = 'Tele Caller Review';
        require __DIR__ . '/../views/reports/tele_review.php';
    }

    public function teleCallerExport(): void
    {
        requireRole('admin', 'manager');

        [$from, $to, $tcId, $sourceId] = $this->readTeleFilters();
        $section = get('section', 'scorecard');

        switch ($section) {
            case 'tasks':
                $rows    = $this->buildTeleTaskStats($from, $to, $tcId);
                $headers = ['Tele Caller','Assigned','Done','Pending','Overdue','On Time','On-Time %','Avg Days to Close'];
                $map = fn($r) => [$r['name'],$r['assigned'],$r['done'],$r['pending'],$r['overdue'],
                                  $r['on_time'],$r['on_time_pct'],$r['avg_days']];
                break;

            case 'sources':
                $m       = $this->buildTeleSourceMatrix($from, $to);
                $rows    = $m['rows'];
                $srcCols = $m['sources'];
                $headers = array_merge(['Tele Caller'], array_column($srcCols, 'name'), ['Total']);
                $map = function ($r) use ($srcCols) {
                    $line = [$r['name']];
                    foreach ($srcCols as $c) $line[] = $r['cells'][$c['id']]['leads'] ?? 0;
                    $line[] = $r['total'];
                    return $line;
                };
                break;

            case 'leads':
                $rows    = $this->buildTeleLeads($from, $to, $tcId, 2000, $sourceId);
                $headers = ['Lead ID','Lead','Stage','Source','Sales Rep','Created','Next Follow-up','Days Since Change'];
                $map = fn($r) => [$r['id'],$r['name'],$r['stage'],$r['source_name'] ?? '',
                                  $r['rep_name'] ?? '',$r['created_at'],$r['next_followup_date'] ?? '',$r['days_idle']];
                break;

            case 'outcomes':
                $rows    = $this->buildTeleOutcomes($from, $to, $tcId);
                $headers = ['Tele Caller','Arranged Site Visit','Lead Lost','No Outcome Recorded'];
                $map = fn($r) => [$r['name'],$r['arranged'],$r['lost'],$r['none']];
                break;

            default:
                $rows    = $this->buildTeleScorecard($from, $to, $tcId);
                $headers = ['Tele Caller','Leads','Contacted','Visit Scheduled','Won','Lost','Open',
                            'Contact Rate %','Visit Rate %','Win Rate %','Overdue Follow-ups'];
                $map = fn($r) => [$r['name'],$r['leads'],$r['contacted'],$r['scheduled'],$r['won'],
                                  $r['lost'],$r['open'],$r['contact_rate'],$r['visit_rate'],
                                  $r['win_rate'],$r['overdue']];
        }

        $filename = 'tele-caller-' . $section . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) fputcsv($out, $map($r));
        fclose($out);
        exit;
    }

    private function readTeleFilters(): array
    {
        $from     = get('from') ?: date('Y-m-d', strtotime('-90 days'));
        $to       = get('to')   ?: date('Y-m-d');
        $tcId     = getInt('tc');
        // -1 means "leads with no source recorded", 0 means "any"
        $sourceId = get('source') === '-1' ? -1 : getInt('source');

        $d = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($d, $from)) $from = date('Y-m-d', strtotime('-90 days'));
        if (!preg_match($d, $to))   $to   = date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];

        return [$from, $to, $tcId, $sourceId];
    }

    /** Funnel per tele caller, counting stages a lead ever reached. */
    private function buildTeleScorecard(string $from, string $to, int $tcId = 0): array
    {
        $where  = ["u.`role`='tele_caller'", 'u.`is_active`=1'];
        $params = [$from, $to];
        if ($tcId > 0) { $where[] = 'u.`id` = ?'; $params[] = $tcId; }

        $stmt = $this->pdo->prepare(
            "SELECT u.`id`, u.`name`, l.`id` AS lead_id, l.`stage`,
                    l.`next_followup_date`,
                    GROUP_CONCAT(a.`new_value` SEPARATOR '\n') AS hist_stages
             FROM `users` u
             LEFT JOIN `leads` l
                    ON l.`tele_caller_id` = u.`id`
                   AND l.`deleted_at` IS NULL
                   AND DATE(l.`created_at`) BETWEEN ? AND ?
             LEFT JOIN `audit_log` a
                    ON a.`lead_id` = l.`id` AND a.`action` = 'STAGE_CHANGE'
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.`id`, l.`id`
             ORDER BY u.`name`"
        );
        $stmt->execute($params);

        $today = date('Y-m-d');
        $agg   = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['id'];
            $agg[$id] ??= ['name' => $row['name'], 'leads' => 0, 'contacted' => 0,
                           'scheduled' => 0, 'won' => 0, 'lost' => 0, 'open' => 0, 'overdue' => 0];
            if (empty($row['lead_id'])) continue;

            $reached = $this->reachedStages($row);
            $agg[$id]['leads']++;
            if (isset($reached['Contacted']))       $agg[$id]['contacted']++;
            if (isset($reached['Visit Scheduled'])) $agg[$id]['scheduled']++;
            if ($row['stage'] === 'Won')  $agg[$id]['won']++;
            elseif ($row['stage'] === 'Lost') $agg[$id]['lost']++;
            else {
                $agg[$id]['open']++;
                if ($row['next_followup_date'] && $row['next_followup_date'] < $today) {
                    $agg[$id]['overdue']++;
                }
            }
        }

        foreach ($agg as &$a) {
            $closed = $a['won'] + $a['lost'];
            $a['contact_rate'] = $a['leads'] > 0 ? round($a['contacted'] / $a['leads'] * 100, 1) : 0;
            $a['visit_rate']   = $a['leads'] > 0 ? round($a['scheduled'] / $a['leads'] * 100, 1) : 0;
            $a['win_rate']     = $closed > 0 ? round($a['won'] / $closed * 100, 1) : null;
        }
        unset($a);

        usort($agg, fn($x, $y) => $y['leads'] <=> $x['leads']);
        return $agg;
    }

    /** Task throughput and punctuality per tele caller. */
    private function buildTeleTaskStats(string $from, string $to, int $tcId = 0): array
    {
        $where  = ["u.`role`='tele_caller'", 'u.`is_active`=1'];
        $params = [$from, $to];
        if ($tcId > 0) { $where[] = 'u.`id` = ?'; $params[] = $tcId; }

        $stmt = $this->pdo->prepare(
            "SELECT u.`id`, u.`name`,
                    COUNT(wt.`id`)                                              AS assigned,
                    SUM(wt.`status`='done')                                     AS done,
                    SUM(wt.`status`='pending')                                  AS pending,
                    SUM(wt.`status`='pending' AND wt.`due_date` < CURDATE())    AS overdue,
                    SUM(wt.`was_on_time`=1)                                     AS on_time,
                    ROUND(AVG(CASE WHEN wt.`completed_at` IS NOT NULL
                              THEN TIMESTAMPDIFF(HOUR, wt.`created_at`, wt.`completed_at`)/24 END), 1) AS avg_days
             FROM `users` u
             LEFT JOIN `workflow_tasks` wt
                    ON wt.`assigned_to` = u.`id`
                   AND DATE(wt.`created_at`) BETWEEN ? AND ?
             LEFT JOIN `leads` l ON l.`id` = wt.`lead_id` AND l.`deleted_at` IS NULL
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.`id`
             ORDER BY assigned DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['on_time_pct'] = (int) $r['done'] > 0
                ? round($r['on_time'] / $r['done'] * 100, 1) : null;
            $r['avg_days'] = $r['avg_days'] ?? '';
        }
        unset($r);
        return $rows;
    }

    /** What tele callers actually recorded when closing their qualification tasks. */
    private function buildTeleOutcomes(string $from, string $to, int $tcId = 0): array
    {
        $where  = ["u.`role`='tele_caller'", 'u.`is_active`=1'];
        $params = [$from, $to];
        if ($tcId > 0) { $where[] = 'u.`id` = ?'; $params[] = $tcId; }

        $stmt = $this->pdo->prepare(
            "SELECT u.`id`, u.`name`,
                    SUM(wt.`outcome`='Arranged Site Visit')                   AS arranged,
                    SUM(wt.`outcome`='Lead Lost')                             AS lost,
                    SUM(wt.`status`='done' AND wt.`outcome` IS NULL)          AS none
             FROM `users` u
             LEFT JOIN `workflow_tasks` wt
                    ON wt.`assigned_to` = u.`id`
                   AND wt.`assigned_role` = 'tele_caller'
                   AND DATE(wt.`created_at`) BETWEEN ? AND ?
             WHERE " . implode(' AND ', $where) . "
             GROUP BY u.`id`
             ORDER BY u.`name`"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Tele caller × lead source cross-tab. Leads with no source are kept as their
     * own column rather than dropped, so the row totals reconcile.
     */
    private function buildTeleSourceMatrix(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.`id` AS tc_id, u.`name` AS tc_name,
                    COALESCE(l.`source_id`, -1)              AS src_id,
                    COALESCE(ls.`name`, 'No source')         AS src_name,
                    COUNT(l.`id`)                            AS leads,
                    SUM(l.`stage` = 'Won')                   AS won,
                    SUM(l.`stage` = 'Lost')                  AS lost
             FROM `users` u
             JOIN `leads` l
                  ON l.`tele_caller_id` = u.`id`
                 AND l.`deleted_at` IS NULL
                 AND DATE(l.`created_at`) BETWEEN ? AND ?
             LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
             WHERE u.`role` = 'tele_caller' AND u.`is_active` = 1
             GROUP BY u.`id`, COALESCE(l.`source_id`, -1)
             ORDER BY u.`name`"
        );
        $stmt->execute([$from, $to]);

        $rows = $sources = $colTotals = [];
        foreach ($stmt->fetchAll() as $r) {
            $tc  = (int) $r['tc_id'];
            $sid = (int) $r['src_id'];

            $sources[$sid] ??= ['id' => $sid, 'name' => $r['src_name'], 'total' => 0];
            $sources[$sid]['total'] += (int) $r['leads'];

            $rows[$tc] ??= ['id' => $tc, 'name' => $r['tc_name'], 'cells' => [],
                            'total' => 0, 'won' => 0, 'lost' => 0];
            $rows[$tc]['cells'][$sid] = [
                'leads' => (int) $r['leads'],
                'won'   => (int) $r['won'],
                'lost'  => (int) $r['lost'],
            ];
            $rows[$tc]['total'] += (int) $r['leads'];
            $rows[$tc]['won']   += (int) $r['won'];
            $rows[$tc]['lost']  += (int) $r['lost'];
            $colTotals[$sid]     = ($colTotals[$sid] ?? 0) + (int) $r['leads'];
        }

        // Busiest sources first so the important columns are visible without scrolling
        uasort($sources, fn($a, $b) => $b['total'] <=> $a['total']);
        usort($rows, fn($a, $b) => $b['total'] <=> $a['total']);

        return ['rows' => $rows, 'sources' => array_values($sources), 'col_totals' => $colTotals];
    }

    /** Lead-by-lead tracking for one tele caller. */
    private function buildTeleLeads(string $from, string $to, int $tcId, int $limit = 300, int $sourceId = 0): array
    {
        if ($tcId <= 0) return [];

        $srcWhere  = '';
        $srcParams = [];
        if ($sourceId === -1) {
            $srcWhere = 'AND l.`source_id` IS NULL';
        } elseif ($sourceId > 0) {
            $srcWhere    = 'AND l.`source_id` = ?';
            $srcParams[] = $sourceId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`stage`, l.`next_followup_date`, l.`created_at`,
                    ls.`name` AS source_name, r.`name` AS rep_name,
                    DATEDIFF(CURDATE(), COALESCE(MAX(a.`created_at`), l.`created_at`)) AS days_idle
             FROM `leads` l
             LEFT JOIN `lead_sources` ls ON ls.`id` = l.`source_id`
             LEFT JOIN `users` r         ON r.`id`  = l.`assigned_rep_id`
             LEFT JOIN `audit_log` a
                    ON a.`lead_id` = l.`id` AND a.`action` = 'STAGE_CHANGE'
             WHERE l.`tele_caller_id` = ?
               AND l.`deleted_at` IS NULL
               AND DATE(l.`created_at`) BETWEEN ? AND ?
               {$srcWhere}
             GROUP BY l.`id`
             ORDER BY days_idle DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tcId, $from, $to, ...$srcParams]);
        return $stmt->fetchAll();
    }

    /**
     * CSV export. `section` picks which table is dumped.
     */
    public function exportCsv(): void
    {
        requireRole('admin', 'manager');

        [$from, $to, $repId] = $this->readFilters();
        $section = get('section', 'funnel');

        switch ($section) {
            case 'reps':
                $rows    = $this->buildRepScorecard($from, $to);
                $headers = ['Sales Rep', 'Assigned', 'Visits Completed', 'Won', 'Lost',
                            'Open', 'Win Rate %', 'Avg Days to Win', 'kW Won', 'Overdue Follow-ups'];
                $map = fn($r) => [$r['name'], $r['assigned'], $r['visits'], $r['won'], $r['lost'],
                                  $r['open'], $r['win_rate'], $r['avg_days_to_win'], $r['kw_won'], $r['overdue']];
                break;

            case 'tele':
                $rows    = $this->buildTeleScorecard($from, $to);
                $headers = ['Tele Caller', 'Leads', 'Reached Contacted', 'Reached Visit Scheduled',
                            'Reached Won', 'Contact Rate %', 'Visit Rate %'];
                $map = fn($r) => [$r['name'], $r['leads'], $r['contacted'], $r['scheduled'],
                                  $r['won'], $r['contact_rate'], $r['visit_rate']];
                break;

            case 'stuck':
                $leads   = $this->fetchLeads($from, $to, $repId);
                $rows    = $this->buildStuck($leads, 1000);
                $headers = ['Lead ID', 'Lead', 'Stage', 'Sales Rep', 'Days Since Stage Change', 'Last Change'];
                $map = fn($r) => [$r['id'], $r['name'], $r['stage'], $r['rep_name'] ?? '—',
                                  $r['days'], $r['last_change']];
                break;

            case 'velocity':
                $leads   = $this->fetchLeads($from, $to, $repId);
                $rows    = $this->buildVelocity($leads);
                $headers = ['Stage', 'Transitions Measured', 'Avg Days in Stage', 'Median Days'];
                $map = fn($r) => [$r['stage'], $r['n'], $r['avg'], $r['median']];
                break;

            default: // funnel
                $leads   = $this->fetchLeads($from, $to, $repId);
                $rows    = $this->buildFunnel($leads)['rows'];
                $headers = ['Stage', 'Leads Reached', 'Conversion From Previous %', 'Drop-off'];
                $map = fn($r) => [$r['stage'], $r['count'], $r['conv'], $r['dropoff']];
        }

        $filename = 'lead-review-' . $section . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) {
            fputcsv($out, $map($r));
        }
        fclose($out);
        exit;
    }

    // ─── Filters ──────────────────────────────────────────────────────────────

    private function readFilters(): array
    {
        $from  = get('from') ?: date('Y-m-d', strtotime('-90 days'));
        $to    = get('to')   ?: date('Y-m-d');
        $repId = getInt('rep');

        // Guard against malformed dates coming in via the query string
        $d = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($d, $from)) $from = date('Y-m-d', strtotime('-90 days'));
        if (!preg_match($d, $to))   $to   = date('Y-m-d');
        if ($from > $to) [$from, $to] = [$to, $from];

        return [$from, $to, $repId, ['from' => $from, 'to' => $to, 'rep' => $repId]];
    }

    // ─── Data loading ─────────────────────────────────────────────────────────

    /**
     * One row per lead in range, with its full stage history collapsed into a
     * single column so the funnel can tell "reached" from "currently sitting in".
     */
    private function fetchLeads(string $from, string $to, int $repId): array
    {
        $where  = ['l.`deleted_at` IS NULL', 'DATE(l.`created_at`) BETWEEN ? AND ?'];
        $params = [$from, $to];

        if ($repId > 0) {
            $where[]  = 'l.`assigned_rep_id` = ?';
            $params[] = $repId;
        }

        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`stage`, l.`potential_kw`, l.`created_at`,
                    l.`assigned_rep_id`, u.`name` AS rep_name,
                    GROUP_CONCAT(a.`new_value` ORDER BY a.`id` SEPARATOR '\\n')  AS hist_stages,
                    GROUP_CONCAT(a.`created_at` ORDER BY a.`id` SEPARATOR '\\n') AS hist_times,
                    MAX(a.`created_at`) AS last_change
             FROM `leads` l
             LEFT JOIN `users` u ON u.`id` = l.`assigned_rep_id`
             LEFT JOIN `audit_log` a
                    ON a.`lead_id` = l.`id` AND a.`action` = 'STAGE_CHANGE'
             WHERE " . implode(' AND ', $where) . "
             GROUP BY l.`id`
             ORDER BY l.`id`"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Stage names a lead has ever occupied. Every lead starts at New. */
    private function reachedStages(array $lead): array
    {
        $reached = ['New' => true, $lead['stage'] => true];
        if (!empty($lead['hist_stages'])) {
            foreach (explode("\n", $lead['hist_stages']) as $s) {
                if ($s !== '') $reached[$s] = true;
            }
        }
        return $reached;
    }

    // ─── Section builders ─────────────────────────────────────────────────────

    private function buildFunnel(array $leads): array
    {
        $counts = array_fill_keys(self::FUNNEL, 0);
        $lost = $onHold = 0;

        foreach ($leads as $l) {
            $reached = $this->reachedStages($l);
            foreach (self::FUNNEL as $s) {
                if (isset($reached[$s])) $counts[$s]++;
            }
            if ($l['stage'] === 'Lost')    $lost++;
            if ($l['stage'] === 'On Hold') $onHold++;
        }

        $rows = [];
        $prev = null;
        foreach (self::FUNNEL as $s) {
            $c = $counts[$s];
            $rows[] = [
                'stage'   => $s,
                'count'   => $c,
                'conv'    => $prev === null ? '' : ($prev > 0 ? round($c / $prev * 100, 1) : 0),
                'dropoff' => $prev === null ? '' : max(0, $prev - $c),
            ];
            $prev = $c;
        }

        return [
            'rows'   => $rows,
            'lost'   => $lost,
            'onhold' => $onHold,
            'total'  => count($leads),
        ];
    }

    /**
     * Average/median days spent in each stage, from consecutive STAGE_CHANGE
     * events. The clock for "New" starts at lead creation.
     */
    private function buildVelocity(array $leads): array
    {
        $durations = [];

        foreach ($leads as $l) {
            if (empty($l['hist_stages'])) continue;

            $stages = explode("\n", $l['hist_stages']);
            $times  = explode("\n", $l['hist_times']);
            if (count($stages) !== count($times)) continue;

            $prevStage = 'New';
            $prevTime  = strtotime($l['created_at']);

            foreach ($stages as $i => $stage) {
                $t = strtotime($times[$i]);
                if ($t === false || $prevTime === false) continue;
                $days = ($t - $prevTime) / 86400;
                if ($days >= 0) {
                    $durations[$prevStage][] = $days;
                }
                $prevStage = $stage;
                $prevTime  = $t;
            }
        }

        $rows = [];
        foreach (self::FUNNEL as $s) {
            if (empty($durations[$s])) continue;
            $vals = $durations[$s];
            sort($vals);
            $n   = count($vals);
            $mid = (int) floor($n / 2);
            $rows[] = [
                'stage'  => $s,
                'n'      => $n,
                'avg'    => round(array_sum($vals) / $n, 1),
                'median' => round($n % 2 ? $vals[$mid] : ($vals[$mid - 1] + $vals[$mid]) / 2, 1),
            ];
        }
        return $rows;
    }

    /** Open leads ranked by how long they have sat without a stage change. */
    private function buildStuck(array $leads, int $limit = 25): array
    {
        $rows = [];
        $now  = time();

        foreach ($leads as $l) {
            if (in_array($l['stage'], ['Won', 'Lost'], true)) continue;

            $ref  = $l['last_change'] ?: $l['created_at'];
            $days = (int) floor(($now - strtotime($ref)) / 86400);

            $rows[] = [
                'id'          => (int) $l['id'],
                'name'        => $l['name'],
                'stage'       => $l['stage'],
                'rep_name'    => $l['rep_name'],
                'days'        => $days,
                'last_change' => substr((string) $ref, 0, 10),
            ];
        }

        usort($rows, fn($a, $b) => $b['days'] <=> $a['days']);
        return array_slice($rows, 0, $limit);
    }

    private function buildRepScorecard(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.`id`, u.`name`,
                    COUNT(l.`id`)                                              AS assigned,
                    SUM(l.`site_visit_completed_date` IS NOT NULL)             AS visits,
                    SUM(l.`stage` = 'Won')                                     AS won,
                    SUM(l.`stage` = 'Lost')                                    AS lost,
                    SUM(l.`stage` NOT IN ('Won','Lost'))                       AS open,
                    SUM(CASE WHEN l.`stage`='Won' THEN l.`potential_kw` ELSE 0 END) AS kw_won,
                    SUM(l.`next_followup_date` < CURDATE()
                        AND l.`stage` NOT IN ('Won','Lost'))                   AS overdue
             FROM `users` u
             LEFT JOIN `leads` l
                    ON l.`assigned_rep_id` = u.`id`
                   AND l.`deleted_at` IS NULL
                   AND DATE(l.`created_at`) BETWEEN ? AND ?
             WHERE u.`role` = 'sales_rep' AND u.`is_active` = 1
             GROUP BY u.`id`
             ORDER BY won DESC, assigned DESC"
        );
        $stmt->execute([$from, $to]);
        $rows = $stmt->fetchAll();

        // Average days from lead creation to the Won transition
        $avg = $this->pdo->prepare(
            "SELECT l.`assigned_rep_id` AS rep,
                    ROUND(AVG(TIMESTAMPDIFF(HOUR, l.`created_at`, a.`created_at`) / 24), 1) AS d
             FROM `leads` l
             JOIN `audit_log` a
                  ON a.`lead_id` = l.`id` AND a.`action` = 'STAGE_CHANGE' AND a.`new_value` = 'Won'
             WHERE l.`deleted_at` IS NULL
               AND DATE(l.`created_at`) BETWEEN ? AND ?
             GROUP BY l.`assigned_rep_id`"
        );
        $avg->execute([$from, $to]);
        $avgByRep = $avg->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($rows as &$r) {
            $closed          = (int) $r['won'] + (int) $r['lost'];
            $r['win_rate']   = $closed > 0 ? round($r['won'] / $closed * 100, 1) : 0;
            $r['kw_won']     = round((float) $r['kw_won'], 1);
            $r['avg_days_to_win'] = $avgByRep[$r['id']] ?? '';
        }
        unset($r);

        return $rows;
    }

}
