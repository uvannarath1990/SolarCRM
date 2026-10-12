<?php
declare(strict_types=1);

class UsersController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    /** Load all active custom roles from DB */
    private function loadCustomRoles(): array
    {
        try {
            return $this->pdo->query(
                "SELECT `id`,`label`,`base_role` FROM `roles` WHERE `is_active`=1 ORDER BY `label`"
            )->fetchAll();
        } catch (\Throwable $e) {
            return []; // table may not exist yet
        }
    }

    public function index(): void
    {
        requireRole('admin');
        $users     = $this->pdo->query(
            'SELECT u.`id`,u.`name`,u.`email`,u.`phone`,u.`role`,u.`role_custom_id`,u.`is_active`,
                    u.`last_login`,u.`created_at`,
                    u.`manager_id`, m.`name` AS manager_name,
                    r.`label` AS custom_role_label
             FROM `users` u
             LEFT JOIN `users` m ON m.`id` = u.`manager_id`
             LEFT JOIN `roles` r ON r.`id` = u.`role_custom_id`
             ORDER BY u.`name`'
        )->fetchAll();
        $pageTitle = 'Users';
        require __DIR__ . '/../views/users/list.php';
    }

    public function create(): void
    {
        requireRole('admin');
        $pageTitle    = 'Add User';
        $user         = [];
        $error        = '';
        $customRoles  = $this->loadCustomRoles();
        $allUsers     = $this->pdo->query(
            "SELECT `id`,`name`,`role` FROM `users` WHERE `is_active`=1 ORDER BY `name`"
        )->fetchAll();
        require __DIR__ . '/../views/users/form.php';
    }

    public function save(): void
    {
        requireRole('admin');
        csrfVerify();

        $id    = postInt('id');
        $name  = trim(post('name'));
        $email = trim(post('email'));
        $phone = trim(post('phone'));
        $roleVal = post('role');   // either 'sales_rep' or 'custom:7'

        $managerId  = postInt('manager_id') ?: null;
        $systemRoles = ['admin', 'tele_caller', 'sales_rep', 'installer', 'manager',
                        'operation_manager', 'accounts', 'dealer', 'documentation', 'partner'];
        $error = '';

        // Resolve base role and custom role id
        $baseRole     = null;
        $customRoleId = null;

        if (str_starts_with($roleVal, 'custom:')) {
            $cid = (int) substr($roleVal, 7);
            $cr  = $this->pdo->prepare('SELECT `id`,`base_role` FROM `roles` WHERE `id`=? AND `is_active`=1 LIMIT 1');
            $cr->execute([$cid]);
            $crRow = $cr->fetch();
            if ($crRow) {
                $baseRole     = $crRow['base_role'];
                $customRoleId = $crRow['id'];
            } else {
                $error = 'Selected custom role no longer exists.';
            }
        } elseif (in_array($roleVal, $systemRoles, true)) {
            $baseRole = $roleVal;
        } else {
            $error = 'Please select a valid role.';
        }

        if (!$error && ($name === '' || $email === '')) {
            $error = 'Name and email are required.';
        }
        if (!$error && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        }

        if (!$error) {
            if ($id > 0) {
                // Edit existing
                $stmt = $this->pdo->prepare(
                    'UPDATE `users` SET `name`=?,`email`=?,`phone`=?,`role`=?,`role_custom_id`=?,`manager_id`=? WHERE `id`=?'
                );
                $stmt->execute([$name, $email, $phone ?: null, $baseRole, $customRoleId, $managerId, $id]);
                $pw = post('password');
                if ($pw !== '') {
                    if (strlen($pw) < 8) {
                        flash('danger', 'Password must be at least 8 characters.');
                        redirect('/users/create?id=' . $id);
                    }
                    $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
                    $this->pdo->prepare('UPDATE `users` SET `password_hash`=? WHERE `id`=?')
                              ->execute([$hash, $id]);
                }
                flash('success', 'User updated successfully.');
            } else {
                $pw = post('password');
                if ($pw === '' || strlen($pw) < 8) {
                    $error = 'Password is required and must be at least 8 characters.';
                } else {
                    $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]);
                    $stmt = $this->pdo->prepare(
                        'INSERT INTO `users` (`name`,`email`,`phone`,`role`,`role_custom_id`,`manager_id`,`password_hash`)
                         VALUES (?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([$name, $email, $phone ?: null, $baseRole, $customRoleId, $managerId, $hash]);
                    flash('success', 'User created successfully.');
                }
            }
        }

        if ($error) {
            flash('danger', $error);
        }

        redirect('/users');
    }

    public function delete(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('id');

        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            jsonError('You cannot delete your own account.');
        }

        // Block delete if user has any leads linked
        $check = $this->pdo->prepare(
            'SELECT COUNT(*) FROM `leads`
             WHERE (`assigned_rep_id` = ? OR `created_by` = ? OR `tele_caller_id` = ?)
               AND `deleted_at` IS NULL'
        );
        $check->execute([$id, $id, $id]);
        if ((int) $check->fetchColumn() > 0) {
            jsonError('Cannot delete: this user has leads assigned. Deactivate them instead.', 422);
        }

        $this->pdo->prepare('DELETE FROM `users` WHERE `id` = ?')->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    public function toggleActive(): void
    {
        requireRole('admin');
        csrfVerify();

        $id = postInt('id');
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            jsonError('You cannot deactivate your own account.');
        }
        $stmt = $this->pdo->prepare(
            'UPDATE `users` SET `is_active` = 1 - `is_active` WHERE `id` = ?'
        );
        $stmt->execute([$id]);
        $row = $this->pdo->prepare('SELECT `is_active`,`name` FROM `users` WHERE `id`=?');
        $row->execute([$id]);
        $u = $row->fetch();
        jsonResponse(['ok' => true, 'is_active' => (bool) $u['is_active'], 'name' => $u['name']]);
    }
}
