<?php
declare(strict_types=1);

class PartnerController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function dashboard(): void
    {
        requireRole('partner');

        $userId = (int) $_SESSION['user_id'];
        $stats  = $this->buildStats($userId);
        $leads  = $this->buildLeads($userId);

        $pageTitle = 'Partner Dashboard';
        require __DIR__ . '/../views/partner/dashboard.php';
    }

    // ── JSON endpoint for stage chart ─────────────────────────────────────────
    public function statsJson(): void
    {
        requireRole('partner');

        $userId = (int) $_SESSION['user_id'];

        $rows = $this->pdo->prepare(
            'SELECT `stage`, COUNT(*) AS cnt FROM `leads`
             WHERE `partner_id` = ? AND `deleted_at` IS NULL
             GROUP BY `stage`'
        );
        $rows->execute([$userId]);

        jsonResponse(['stages' => $rows->fetchAll()]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildStats(int $userId): array
    {
        $pdo = $this->pdo;

        $q = $pdo->prepare('SELECT COUNT(*) FROM `leads` WHERE `partner_id`=? AND `deleted_at` IS NULL');
        $q->execute([$userId]);
        $total = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `partner_id`=? AND `deleted_at` IS NULL AND `stage` NOT IN ('Won','Lost')");
        $q->execute([$userId]);
        $active = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `partner_id`=? AND `deleted_at` IS NULL AND `stage`='Won'");
        $q->execute([$userId]);
        $won = (int) $q->fetchColumn();

        $q = $pdo->prepare("SELECT COUNT(*) FROM `leads` WHERE `partner_id`=? AND `deleted_at` IS NULL AND `stage`='Lost'");
        $q->execute([$userId]);
        $lost = (int) $q->fetchColumn();

        $q = $pdo->prepare(
            "SELECT COUNT(*) FROM `leads`
             WHERE `partner_id`=? AND `deleted_at` IS NULL AND `stage`='Won'
               AND MONTH(`updated_at`)=MONTH(NOW()) AND YEAR(`updated_at`)=YEAR(NOW())"
        );
        $q->execute([$userId]);
        $wonMonth = (int) $q->fetchColumn();

        $closed     = $won + $lost;
        $conversion = $closed > 0 ? round($won / $closed * 100, 1) : 0;

        $q = $pdo->prepare(
            'SELECT `stage`, COUNT(*) AS cnt FROM `leads`
             WHERE `partner_id`=? AND `deleted_at` IS NULL
             GROUP BY `stage` ORDER BY cnt DESC'
        );
        $q->execute([$userId]);
        $stages = $q->fetchAll();

        return compact('total', 'active', 'won', 'lost', 'wonMonth', 'conversion', 'stages');
    }

    private function buildLeads(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT l.`id`, l.`name`, l.`phone`, l.`location`, l.`stage`,
                    l.`created_at`, l.`updated_at`,
                    rep.`name`  AS rep_name,
                    tc.`name`   AS tc_name
             FROM `leads` l
             LEFT JOIN `users` rep ON rep.`id` = l.`assigned_rep_id`
             LEFT JOIN `users` tc  ON tc.`id`  = l.`tele_caller_id`
             WHERE l.`partner_id` = ? AND l.`deleted_at` IS NULL
             ORDER BY l.`updated_at` DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
