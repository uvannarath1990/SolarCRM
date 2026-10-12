<?php
declare(strict_types=1);

class InstallationController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireRole('admin', 'installer', 'operation_manager');

        $userId = (int) $_SESSION['user_id'];
        $role   = $_SESSION['role'];

        $extra = $role === 'installer'
            ? 'AND i.`installer_id` = ' . $userId
            : '';

        $installs = $this->pdo->query(
            "SELECT i.*, l.`name`, l.`location`, l.`potential_kw`,
                    u.`name` AS installer_name
             FROM `installations` i
             JOIN `leads` l ON l.`id` = i.`lead_id`
             LEFT JOIN `users` u ON u.`id` = i.`installer_id`
             WHERE 1=1 {$extra}
             ORDER BY FIELD(i.`status`,'in_progress','scheduled','pending','completed','cancelled'),
                      i.`scheduled_date` ASC
             LIMIT 100"
        )->fetchAll();

        $installers = [];
        if ($role === 'admin') {
            $installers = $this->pdo->query(
                "SELECT `id`,`name` FROM `users` WHERE `role`='installer' AND `is_active`=1 ORDER BY `name`"
            )->fetchAll();
        }

        $pageTitle = 'Installations';
        require __DIR__ . '/../views/installations/list.php';
    }

    public function detail(): void
    {
        requireRole('admin', 'installer', 'operation_manager');

        $leadId = getInt('lead_id');
        $stmt   = $this->pdo->prepare(
            'SELECT i.*, l.`name`, l.`phone`, l.`location`, l.`potential_kw`,
                    u.`name` AS installer_name
             FROM `installations` i
             JOIN `leads` l ON l.`id` = i.`lead_id`
             LEFT JOIN `users` u ON u.`id` = i.`installer_id`
             WHERE i.`lead_id` = ? LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $install = $stmt->fetch();

        if (!$install) {
            flash('danger', 'Installation record not found.');
            redirect('/installations');
        }

        $installers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users` WHERE `role`='installer' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        $pageTitle = 'Installation — ' . $install['name'];
        require __DIR__ . '/../views/installations/detail.php';
    }

    public function update(): void
    {
        requireRole('admin', 'installer', 'operation_manager');
        csrfVerify();

        $installId     = postInt('install_id');
        $status        = post('status');
        $scheduledDate = post('scheduled_date') ?: null;
        $completedDate = post('completed_date') ?: null;
        $systemKw      = post('system_kw') !== '' ? (float) post('system_kw') : null;
        $panelCount    = postInt('panel_count') ?: null;
        $inverter      = trim(post('inverter_model')) ?: null;
        $notes         = trim(post('notes')) ?: null;
        $installerId   = postInt('installer_id') ?: null;

        $validStatuses = ['pending', 'scheduled', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            jsonError('Invalid status.', 400);
        }

        $stmt = $this->pdo->prepare(
            'UPDATE `installations`
             SET `status`=?,`scheduled_date`=?,`completed_date`=?,
                 `system_kw`=?,`panel_count`=?,`inverter_model`=?,`notes`=?,
                 `installer_id`=?,`updated_at`=NOW()
             WHERE `id`=?'
        );
        $stmt->execute([
            $status, $scheduledDate, $completedDate,
            $systemKw, $panelCount, $inverter, $notes,
            $installerId, $installId,
        ]);

        // If completed, mark lead stage and auto-complete installation workflow task
        if ($status === 'completed') {
            $row = $this->pdo->prepare('SELECT `lead_id` FROM `installations` WHERE `id`=?');
            $row->execute([$installId]);
            $inst = $row->fetch();
            if ($inst) {
                $leadId = (int) $inst['lead_id'];
                writeAuditLog($leadId, 'UPDATE', 'installation', 'status', null, 'completed');
                // Mark the Installation workflow task done
                $this->pdo->prepare(
                    "UPDATE `workflow_tasks`
                     SET `status`='done', `completed_by`=?, `completed_at`=NOW()
                     WHERE `lead_id`=? AND `stage`='Installation' AND `status`='pending'"
                )->execute([(int) $_SESSION['user_id'], $leadId]);
            }
        }

        if (isAjax()) {
            jsonResponse(['ok' => true]);
        }
        flash('success', 'Installation updated.');
        redirect('/installations');
    }
}
