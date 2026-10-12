<?php
declare(strict_types=1);

/**
 * Field-rep location tracking.
 *
 * A browser can only report position while the page is open and focused, so the
 * trail is deliberately sparse: an hourly heartbeat while the app is in use,
 * plus guaranteed captures at real events (check-in, check-out, task done).
 * Gaps are real and the map shows them rather than interpolating across them.
 *
 * Nothing is stored outside the user's own work window — the check happens here,
 * before the insert, so out-of-hours positions never reach the table at all.
 */
class LocationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    // ── POST /location/ping ───────────────────────────────────────────────────
    public function ping(): void
    {
        requireLogin();
        csrfVerify();

        $userId = (int) $_SESSION['user_id'];

        $lat = post('lat') !== null && post('lat') !== '' ? (float) post('lat') : null;
        $lng = post('lng') !== null && post('lng') !== '' ? (float) post('lng') : null;

        if ($lat === null || $lng === null) {
            jsonError('No coordinates supplied.', 422);
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            jsonError('Coordinates out of range.', 422);
        }

        $user = $this->fetchTrackableUser($userId);
        if (!$user) {
            // Tracking is off for this user — accept quietly so the client simply
            // stops, rather than retrying against an endpoint that will never take it.
            jsonResponse(['stored' => false, 'reason' => 'tracking_off', 'stop' => true]);
        }

        if (!self::withinWorkWindow($user)) {
            jsonResponse(['stored' => false, 'reason' => 'outside_hours']);
        }

        $accuracy = post('accuracy') !== null && post('accuracy') !== ''
                  ? min(65535, max(0, (int) round((float) post('accuracy'))))
                  : null;

        $source = (string) (post('source') ?? 'heartbeat');
        if (!in_array($source, ['heartbeat', 'checkin', 'checkout', 'task', 'login'], true)) {
            $source = 'heartbeat';
        }

        $leadId = postInt('lead_id') ?: null;

        $this->store($userId, $lat, $lng, $accuracy, $source, $leadId);

        jsonResponse(['stored' => true]);
    }

    /**
     * Record a position from inside another flow (site visit, task completion).
     * Silent — a tracking failure must never break the thing the rep was doing.
     */
    public static function record(
        int $userId,
        ?float $lat,
        ?float $lng,
        ?int $accuracy = null,
        string $source = 'heartbeat',
        ?int $leadId = null
    ): void {
        if ($lat === null || $lng === null) return;

        try {
            $self = new self();
            $user = $self->fetchTrackableUser($userId);
            if (!$user || !self::withinWorkWindow($user)) return;
            $self->store($userId, $lat, $lng, $accuracy, $source, $leadId);
        } catch (Throwable $e) {
            // Deliberately swallowed.
        }
    }

    private function store(
        int $userId,
        float $lat,
        float $lng,
        ?int $accuracy,
        string $source,
        ?int $leadId
    ): void {
        $this->pdo->prepare(
            "INSERT INTO `user_locations`
                (`user_id`,`lat`,`lng`,`accuracy_m`,`source`,`lead_id`,`captured_at`)
             VALUES (?,?,?,?,?,?,NOW())"
        )->execute([$userId, $lat, $lng, $accuracy, $source, $leadId]);
    }

    private function fetchTrackableUser(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT `id`,`work_start`,`work_end`,`work_days`,`track_location`
             FROM `users` WHERE `id`=? AND `is_active`=1 LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['track_location'] !== 1) return null;
        return $user;
    }

    /**
     * True when "now" falls inside this user's working days and hours.
     * An end time before the start time is treated as an overnight shift.
     */
    public static function withinWorkWindow(array $user): bool
    {
        $days = array_filter(array_map('trim', explode(',', (string) $user['work_days'])), fn($d) => $d !== '');
        $todayIso = (string) (int) date('N'); // 1 = Mon … 7 = Sun

        if (!in_array($todayIso, $days, true)) return false;

        $now   = date('H:i:s');
        $start = (string) $user['work_start'];
        $end   = (string) $user['work_end'];

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }
        return $now >= $start || $now <= $end;
    }

    // ── GET /reports/team-map ─────────────────────────────────────────────────
    public function teamMap(): void
    {
        requireRole('admin', 'manager');

        $this->purgeExpired();

        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // Point in time. Empty means "now" — the live view.
        $time = (string) ($_GET['time'] ?? '');
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) $time = '';

        $reps      = $this->fetchTrackedUsers();
        $latest    = $this->fetchLatestPositions($date, $time);
        $selected  = (int) ($_GET['user'] ?? 0);
        $trail     = $selected > 0 ? $this->fetchTrail($selected, $date) : [];

        $bounds    = $this->dayBounds($date);
        $pageTitle = 'Team Location';
        $extraJs   = ['team-map.js'];
        require __DIR__ . '/../views/reports/team_map.php';
    }

    // ── GET /api/team-locations ───────────────────────────────────────────────
    public function teamLocationsJson(): void
    {
        requireRole('admin', 'manager');

        $date = (string) ($_GET['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $selected = (int) ($_GET['user'] ?? 0);

        $time = (string) ($_GET['time'] ?? '');
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) $time = '';

        header('Content-Type: application/json');
        echo json_encode([
            'reps'    => $this->fetchLatestPositions($date, $time),
            'at'      => $time,
            'trail'   => $selected > 0 ? $this->fetchTrail($selected, $date) : [],
            'fetched' => date('H:i'),
        ]);
        exit;
    }

    private function fetchTrackedUsers(): array
    {
        return $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users`
             WHERE `is_active`=1 AND `track_location`=1
             ORDER BY `name`"
        )->fetchAll();
    }

    /**
     * Most recent position per user for the given day.
     * Uses a correlated subquery rather than a window function so this keeps
     * working if the database is ever moved to an older MySQL.
     */
    /**
     * Where each tracked user was at a given moment.
     *
     * With no $time this is the live view — their most recent position today.
     * With a $time it answers "where was everyone at 15:30", by taking each
     * user's last ping at or before that instant.
     *
     * Ages are measured from the chosen moment, not from now: viewing 10:00
     * yesterday should say "12 min old", not "a day old".
     */
    private function fetchLatestPositions(string $date, string $time = ''): array
    {
        $at     = $time !== '' ? $date . ' ' . $time . ':59' : null;
        $anchor = $at ?? date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            "SELECT u.`id` AS user_id, u.`name`, u.`role`,
                    ul.`lat`, ul.`lng`, ul.`accuracy_m`, ul.`source`,
                    ul.`captured_at`, ul.`lead_id`,
                    l.`name` AS lead_name,
                    TIMESTAMPDIFF(MINUTE, ul.`captured_at`, ?) AS age_min
             FROM `users` u
             LEFT JOIN `user_locations` ul
                    ON ul.`id` = (
                        SELECT x.`id` FROM `user_locations` x
                        WHERE x.`user_id` = u.`id`
                          AND DATE(x.`captured_at`) = ?
                          AND (? IS NULL OR x.`captured_at` <= ?)
                        ORDER BY x.`captured_at` DESC, x.`id` DESC LIMIT 1
                    )
             LEFT JOIN `leads` l ON l.`id` = ul.`lead_id`
             WHERE u.`is_active`=1 AND u.`track_location`=1
             ORDER BY (ul.`captured_at` IS NULL), ul.`captured_at` DESC"
        );
        $stmt->execute([$anchor, $date, $at, $at]);
        return $stmt->fetchAll();
    }

    /** First and last ping of the day, to bound the time slider. */
    private function dayBounds(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT MIN(TIME(`captured_at`)) AS first_at, MAX(TIME(`captured_at`)) AS last_at,
                    COUNT(*) AS pings
               FROM `user_locations` WHERE DATE(`captured_at`) = ?"
        );
        $stmt->execute([$date]);
        return $stmt->fetch() ?: ['first_at' => null, 'last_at' => null, 'pings' => 0];
    }

    private function fetchTrail(int $userId, string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ul.`lat`, ul.`lng`, ul.`accuracy_m`, ul.`source`,
                    ul.`captured_at`, ul.`lead_id`, l.`name` AS lead_name
             FROM `user_locations` ul
             LEFT JOIN `leads` l ON l.`id` = ul.`lead_id`
             WHERE ul.`user_id`=? AND DATE(ul.`captured_at`)=?
             ORDER BY ul.`captured_at` ASC
             LIMIT 500"
        );
        $stmt->execute([$userId, $date]);
        return $stmt->fetchAll();
    }

    /**
     * Drop rows past the retention cutoff. Runs at most once an hour, piggybacked
     * on the map page so the install needs no cron job.
     */
    private function purgeExpired(): void
    {
        $days = (int) getSetting('location_retention_days', '90');
        if ($days <= 0) return;

        $flag = sys_get_temp_dir() . '/kre_loc_purge';
        if (file_exists($flag) && (time() - (int) filemtime($flag)) < 3600) return;
        @touch($flag);

        try {
            $this->pdo->prepare(
                "DELETE FROM `user_locations`
                 WHERE `captured_at` < DATE_SUB(CURDATE(), INTERVAL ? DAY)
                 LIMIT 5000"
            )->execute([$days]);
        } catch (Throwable $e) {
            // Purging is housekeeping — never let it break the page.
        }
    }
}
