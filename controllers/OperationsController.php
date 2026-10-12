<?php
declare(strict_types=1);

class OperationsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    // ─── List ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        requireRole('admin', 'operation_manager', 'accounts');

        $status = get('status', 'pending'); // pending | done | all
        $q      = trim(get('q'));

        $where  = ["l.`stage` = 'Won'", "l.`deleted_at` IS NULL"];
        $params = [];

        if ($status === 'pending') {
            $where[] = 'o.`done_at` IS NULL';
        } elseif ($status === 'done') {
            $where[] = 'o.`done_at` IS NOT NULL';
        }

        if ($q !== '') {
            $where[]  = '(l.`name` LIKE ? OR l.`phone` LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $whereStr = implode(' AND ', $where);

        $ops = $this->pdo->prepare(
            "SELECT o.*,
                    l.`name`     AS lead_name,
                    l.`phone`    AS lead_phone,
                    l.`location` AS lead_location,
                    u.`name`     AS performed_by_name,
                    t.`name`     AS team_name
             FROM `operations` o
             JOIN `leads` l ON l.`id` = o.`lead_id`
             LEFT JOIN `users` u ON u.`id` = o.`performed_by_id`
             LEFT JOIN `teams` t ON t.`id` = o.`team_id`
             WHERE {$whereStr}
             ORDER BY
                 o.`done_at` IS NOT NULL ASC,
                 o.`scheduled_at` ASC"
        );
        $ops->execute($params);
        $operations = $ops->fetchAll();

        // Won leads for "Add Operation" dropdown
        $wonLeads = $this->pdo->query(
            "SELECT `id`,`name`,`phone` FROM `leads` WHERE `stage`='Won' ORDER BY `name`"
        )->fetchAll();

        // Active teams for dropdown
        $teams = $this->pdo->query(
            "SELECT `id`,`name` FROM `teams` WHERE `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $pageTitle = 'Operations';
        require __DIR__ . '/../views/operations/list.php';
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function save(): void
    {
        requireRole('admin', 'operation_manager');
        csrfVerify();

        $leadId      = postInt('lead_id');
        $teamId      = postInt('team_id') ?: null;
        $type        = trim(post('operation_type', 'Cleaning')) ?: 'Cleaning';
        $scheduledAt = post('scheduled_at');
        $intervalDays = postInt('interval_days', 120);
        $notes       = trim(post('notes')) ?: null;

        if ($leadId <= 0 || $scheduledAt === '') {
            jsonError('Lead and scheduled date are required.');
        }

        // Verify lead is Won
        $lead = $this->pdo->prepare("SELECT `id` FROM `leads` WHERE `id`=? AND `stage`='Won' LIMIT 1");
        $lead->execute([$leadId]);
        if (!$lead->fetch()) {
            jsonError('Operations can only be added to Won leads.', 400);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `operations`
                (`lead_id`,`team_id`,`operation_type`,`scheduled_at`,`interval_days`,`notes`)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([$leadId, $teamId, $type, $scheduledAt, $intervalDays, $notes]);
        $id = (int) $this->pdo->lastInsertId();

        jsonResponse([
            'ok'           => true,
            'id'           => $id,
            'operation_type' => $type,
            'scheduled_at' => $scheduledAt,
            'interval_days' => $intervalDays,
            'notes'        => $notes,
        ]);
    }

    // ─── Mark done ────────────────────────────────────────────────────────────

    public function markDone(): void
    {
        requireRole('admin', 'operation_manager', 'accounts');
        csrfVerify();

        $id      = postInt('id');
        $doneAt  = post('done_at') ?: date('Y-m-d');
        $notes   = trim(post('notes')) ?: null;

        if ($id <= 0) {
            jsonError('Invalid operation.', 400);
        }

        $op = $this->pdo->prepare('SELECT * FROM `operations` WHERE `id`=? LIMIT 1');
        $op->execute([$id]);
        $operation = $op->fetch();
        if (!$operation) {
            jsonError('Operation not found.', 404);
        }

        $this->pdo->prepare(
            'UPDATE `operations` SET `done_at`=?, `notes`=?, `performed_by_id`=? WHERE `id`=?'
        )->execute([$doneAt, $notes, (int) $_SESSION['user_id'], $id]);

        // Auto-schedule next operation
        $nextId = null;
        $nextScheduled = null;
        if ((int) $operation['interval_days'] > 0) {
            $nextScheduled = date('Y-m-d', strtotime($doneAt . ' + ' . $operation['interval_days'] . ' days'));
            $ins = $this->pdo->prepare(
                'INSERT INTO `operations`
                    (`lead_id`,`team_id`,`operation_type`,`scheduled_at`,`interval_days`)
                 VALUES (?,?,?,?,?)'
            );
            $ins->execute([
                $operation['lead_id'],
                $operation['team_id'] ?? null,
                $operation['operation_type'],
                $nextScheduled,
                $operation['interval_days'],
            ]);
            $nextId = (int) $this->pdo->lastInsertId();
        }

        jsonResponse([
            'ok'            => true,
            'done_at'       => $doneAt,
            'next_id'       => $nextId,
            'next_scheduled' => $nextScheduled,
            'interval_days' => (int) $operation['interval_days'],
            'operation_type' => $operation['operation_type'],
        ]);
    }

    // ─── Reschedule ───────────────────────────────────────────────────────────

    public function reschedule(): void
    {
        requireRole('admin', 'operation_manager');
        csrfVerify();

        $id   = postInt('id');
        $date = trim(post('scheduled_at'));

        if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonError('Invalid operation or date.', 422);
        }

        $op = $this->pdo->prepare('SELECT `id`,`done_at` FROM `operations` WHERE `id`=? LIMIT 1');
        $op->execute([$id]);
        $operation = $op->fetch();
        if (!$operation) jsonError('Operation not found.', 404);
        if ($operation['done_at']) jsonError('Cannot reschedule a completed operation.', 400);

        $this->pdo->prepare('UPDATE `operations` SET `scheduled_at`=? WHERE `id`=?')
            ->execute([$date, $id]);

        jsonResponse(['ok' => true, 'scheduled_at' => $date]);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function delete(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('id');
        if ($id <= 0) {
            jsonError('Invalid operation.', 400);
        }
        $this->pdo->prepare('DELETE FROM `operations` WHERE `id`=?')->execute([$id]);
        jsonResponse(['ok' => true]);
    }
}
