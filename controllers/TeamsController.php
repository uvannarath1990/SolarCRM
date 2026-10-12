<?php
declare(strict_types=1);

class TeamsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireRole('admin');

        $teams = $this->pdo->query(
            "SELECT t.*, u.`name` AS leader_name,
                    (SELECT COUNT(*) FROM `team_members` tm WHERE tm.`team_id` = t.`id`) AS member_count
             FROM `teams` t
             JOIN `users` u ON u.`id` = t.`leader_id`
             ORDER BY t.`name`"
        )->fetchAll();

        $pageTitle = 'Teams';
        require __DIR__ . '/../views/teams/list.php';
    }

    public function create(): void
    {
        requireRole('admin');

        $team    = null;
        $members = [];

        $editId = getInt('id');
        if ($editId > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM `teams` WHERE `id` = ? LIMIT 1');
            $stmt->execute([$editId]);
            $team = $stmt->fetch();
            if (!$team) {
                flash('danger', 'Team not found.');
                redirect('/teams');
            }
            $mStmt = $this->pdo->prepare(
                'SELECT `user_id` FROM `team_members` WHERE `team_id` = ?'
            );
            $mStmt->execute([$editId]);
            $members = array_column($mStmt->fetchAll(), 'user_id');
        }

        $allUsers   = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        $installers = $allUsers; // all roles can be team members

        $pageTitle = $team ? 'Edit Team' : 'New Team';
        require __DIR__ . '/../views/teams/form.php';
    }

    public function save(): void
    {
        requireRole('admin');
        csrfVerify();

        $teamId   = postInt('team_id') ?: null;
        $name     = trim(post('name'));
        $leaderId = postInt('leader_id');
        $isActive = postInt('is_active', 1);
        $memberIds = array_map('intval', (array) ($_POST['member_ids'] ?? []));

        if ($name === '' || $leaderId <= 0) {
            flash('danger', 'Name and leader are required.');
            redirect($teamId ? '/teams/create?id=' . $teamId : '/teams/create');
        }

        if ($teamId) {
            $this->pdo->prepare(
                'UPDATE `teams` SET `name`=?, `leader_id`=?, `is_active`=? WHERE `id`=?'
            )->execute([$name, $leaderId, $isActive, $teamId]);

            $this->pdo->prepare('DELETE FROM `team_members` WHERE `team_id`=?')->execute([$teamId]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO `teams` (`name`,`leader_id`,`is_active`) VALUES (?,?,?)'
            )->execute([$name, $leaderId, 1]);
            $teamId = (int) $this->pdo->lastInsertId();
        }

        if (!empty($memberIds)) {
            $ins = $this->pdo->prepare(
                'INSERT IGNORE INTO `team_members` (`team_id`,`user_id`) VALUES (?,?)'
            );
            foreach ($memberIds as $uid) {
                if ($uid > 0) {
                    $ins->execute([$teamId, $uid]);
                }
            }
        }

        flash('success', 'Team saved.');
        redirect('/teams');
    }

    public function delete(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('id');
        if ($id <= 0) {
            jsonError('Invalid team.', 400);
        }

        // Check if any operations reference this team
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM `operations` WHERE `team_id`=?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            // Soft-delete
            $this->pdo->prepare('UPDATE `teams` SET `is_active`=0 WHERE `id`=?')->execute([$id]);
        } else {
            $this->pdo->prepare('DELETE FROM `teams` WHERE `id`=?')->execute([$id]);
        }

        jsonResponse(['ok' => true]);
    }
}
