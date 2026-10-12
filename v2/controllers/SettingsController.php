<?php
declare(strict_types=1);

class SettingsController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getPdo();
    }

    public function index(): void
    {
        requireRole('admin', 'manager');

        $isAdmin  = $_SESSION['role'] === 'admin';
        $settings = [];
        if ($isAdmin) {
            $rows = $this->pdo->query('SELECT `key_name`,`value` FROM `settings`')->fetchAll();
            foreach ($rows as $row) {
                $settings[$row['key_name']] = $row['value'];
            }
        }

        $sources = $this->pdo->query(
            'SELECT ls.*, COUNT(l.`id`) AS lead_count,
                    cu.`name` AS created_by_name,
                    uu.`name` AS updated_by_name
             FROM `lead_sources` ls
             LEFT JOIN `leads` l  ON l.`source_id` = ls.`id` AND l.`deleted_at` IS NULL
             LEFT JOIN `users` cu ON cu.`id` = ls.`created_by`
             LEFT JOIN `users` uu ON uu.`id` = ls.`updated_by`
             GROUP BY ls.`id`
             ORDER BY ls.`sort_order`, ls.`name`'
        )->fetchAll();

        // Load subcategories grouped by source_id
        $subRows = $this->pdo->query(
            'SELECT * FROM `lead_source_subs` ORDER BY `source_id`,`sort_order`,`name`'
        )->fetchAll();
        $subsMap = [];
        foreach ($subRows as $sub) {
            $subsMap[(int) $sub['source_id']][] = $sub;
        }

        // Load custom roles (admin only)
        $customRoles = [];
        if ($isAdmin) {
            try {
                $customRoles = $this->pdo->query(
                    'SELECT r.*, COUNT(u.`id`) AS user_count
                     FROM `roles` r
                     LEFT JOIN `users` u ON u.`role_custom_id` = r.`id` AND u.`is_active`=1
                     GROUP BY r.`id`
                     ORDER BY r.`label`'
                )->fetchAll();
            } catch (\Throwable $e) { /* table not yet created */ }
        }

        $pageTitle = 'Settings';
        require __DIR__ . '/../views/settings/index.php';
    }

    public function save(): void
    {
        requireRole('admin');
        csrfVerify();

        $allowed = [
            'company_name', 'company_phone', 'company_email', 'company_address',
            'bill_to_kw_divisor', 'currency_symbol',
            'status_reasons', 'lost_reasons', 'gps_radius_meters',
        ];

        // Role permission checkbox lists. A checkbox group submits nothing when
        // fully unticked, so a hidden marker field tells us the group was on the
        // form at all — otherwise "grant nobody" would be indistinguishable from
        // "this form did not include the field" and could never be saved.
        $roleSettings = ['doc_tab_roles', 'lead_create_roles',
                         'lead_assign_rep_roles', 'lead_assign_tele_roles',
                         'lead_assign_partner_roles', 'doc_portal_roles'];
        $validTokens  = array_keys(configurableRoleTokens());

        foreach ($roleSettings as $key) {
            if (!isset($_POST[$key . '_submitted'])) {
                continue;
            }
            $selected = array_values(array_intersect((array) ($_POST[$key] ?? []), $validTokens));
            $this->pdo->prepare(
                'INSERT INTO `settings` (`key_name`,`value`) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)'
            )->execute([$key, implode('|', $selected)]);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO `settings` (`key_name`,`value`) VALUES (?,?)
             ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)'
        );

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $stmt->execute([$key, trim($_POST[$key])]);
            }
        }

        flash('success', 'Settings saved.');
        redirect('/settings');
    }

    public function saveSources(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $action = post('action');
        $id     = postInt('id');
        $name   = trim(post('name'));

        $actorId = (int) ($_SESSION['user_id'] ?? 0) ?: null;

        if ($action === 'add') {
            if ($name === '') jsonError('Source name is required.', 400);
            $stmt = $this->pdo->prepare(
                'INSERT INTO `lead_sources` (`name`,`sort_order`,`created_by`) VALUES (?,0,?)'
            );
            $stmt->execute([$name, $actorId]);
            $newId = (int) $this->pdo->lastInsertId();
            auditConfig('SOURCE_CREATE', 'lead_source:' . $newId, 'name', null, $name);
            jsonResponse(['ok' => true, 'id' => $newId, 'name' => $name]);
        }

        if ($action === 'toggle' && $id > 0) {
            $before = $this->pdo->prepare('SELECT `name`,`is_active` FROM `lead_sources` WHERE `id`=? LIMIT 1');
            $before->execute([$id]);
            $row = $before->fetch();
            if (!$row) jsonError('Source not found.', 404);

            $this->pdo->prepare(
                'UPDATE `lead_sources`
                 SET `is_active` = 1 - `is_active`, `updated_by`=?, `updated_at`=NOW()
                 WHERE `id`=?'
            )->execute([$actorId, $id]);

            auditConfig('SOURCE_UPDATE', 'lead_source:' . $id, 'is_active',
                        $row['is_active'] ? 'active' : 'inactive',
                        $row['is_active'] ? 'inactive' : 'active');
            jsonResponse(['ok' => true]);
        }

        // How many leads would be affected — used by the UI before confirming
        if ($action === 'usage' && $id > 0) {
            $c = $this->pdo->prepare('SELECT COUNT(*) FROM `leads` WHERE `source_id`=? AND `deleted_at` IS NULL');
            $c->execute([$id]);
            $subs = $this->pdo->prepare('SELECT COUNT(*) FROM `lead_source_subs` WHERE `source_id`=?');
            $subs->execute([$id]);
            jsonResponse(['ok' => true, 'leads' => (int) $c->fetchColumn(), 'subs' => (int) $subs->fetchColumn()]);
        }

        if ($action === 'delete' && $id > 0) {
            $src = $this->pdo->prepare('SELECT `id`,`name` FROM `lead_sources` WHERE `id`=? LIMIT 1');
            $src->execute([$id]);
            $source = $src->fetch();
            if (!$source) jsonError('Source not found.', 404);

            $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM `leads` WHERE `source_id`=? AND `deleted_at` IS NULL');
            $cnt->execute([$id]);
            $leadCount = (int) $cnt->fetchColumn();

            $replaceWith = postInt('replace_with') ?: null;

            // A source still carrying leads can only go if those leads are moved,
            // otherwise they would be left pointing at a source that no longer exists.
            if ($leadCount > 0) {
                if (!$replaceWith) {
                    jsonError("This source is used by {$leadCount} lead(s). Choose a replacement source.", 422);
                }
                if ($replaceWith === $id) {
                    jsonError('Choose a different source to move the leads to.', 422);
                }
                $chk = $this->pdo->prepare('SELECT `name` FROM `lead_sources` WHERE `id`=? LIMIT 1');
                $chk->execute([$replaceWith]);
                $target = $chk->fetch();
                if (!$target) jsonError('Replacement source not found.', 422);
            }

            $this->pdo->beginTransaction();
            try {
                if ($leadCount > 0) {
                    // One audit row per affected lead, written in a single statement
                    $this->pdo->prepare(
                        "INSERT INTO `audit_log`
                            (`lead_id`,`user_id`,`action`,`entity`,`field_name`,`old_value`,`new_value`,`created_at`)
                         SELECT `id`, ?, 'UPDATE', 'lead', 'source_id', ?, ?, NOW()
                         FROM `leads` WHERE `source_id`=? AND `deleted_at` IS NULL"
                    )->execute([
                        $_SESSION['user_id'] ?? null,
                        $source['name'],
                        $target['name'],
                        $id,
                    ]);

                    // Sub-source belongs to the old source, so it cannot survive the move
                    $this->pdo->prepare(
                        'UPDATE `leads` SET `source_id`=?, `source_sub_id`=NULL, `updated_at`=NOW()
                         WHERE `source_id`=?'
                    )->execute([$replaceWith, $id]);
                }

                $this->pdo->prepare('DELETE FROM `lead_source_subs` WHERE `source_id`=?')->execute([$id]);
                $this->pdo->prepare('DELETE FROM `lead_sources` WHERE `id`=?')->execute([$id]);
                $this->pdo->commit();
            } catch (\Throwable $e) {
                $this->pdo->rollBack();
                jsonError('Could not delete source: ' . $e->getMessage(), 500);
            }

            auditConfig(
                'SOURCE_DELETE',
                'lead_source:' . $id,
                'name',
                $source['name'],
                $leadCount > 0
                    ? "deleted — {$leadCount} lead(s) moved to {$target['name']}"
                    : 'deleted (unused)'
            );

            jsonResponse([
                'ok'    => true,
                'moved' => $leadCount,
                'to'    => $leadCount > 0 ? $target['name'] : null,
            ]);
        }

        jsonError('Invalid action.', 400);
    }

    public function saveRoles(): void
    {
        requireRole('admin');
        csrfVerify();

        $action  = post('action');
        $id      = postInt('id');
        $label   = trim(post('label'));
        $baseRole = post('base_role');

        $systemRoles = ['admin', 'tele_caller', 'sales_rep', 'installer', 'manager',
                        'operation_manager', 'accounts', 'dealer', 'documentation'];

        try {
            if ($action === 'add') {
                if ($label === '') jsonError('Role name is required.', 400);
                if (!in_array($baseRole, $systemRoles, true)) jsonError('Invalid permission level.', 400);
                $this->pdo->prepare(
                    'INSERT INTO `roles` (`label`,`base_role`) VALUES (?,?)'
                )->execute([$label, $baseRole]);
                jsonResponse(['ok' => true, 'id' => (int) $this->pdo->lastInsertId(), 'label' => $label, 'base_role' => $baseRole]);
            }

            if ($action === 'toggle' && $id > 0) {
                $this->pdo->prepare(
                    'UPDATE `roles` SET `is_active` = 1 - `is_active` WHERE `id`=?'
                )->execute([$id]);
                jsonResponse(['ok' => true]);
            }

            if ($action === 'delete' && $id > 0) {
                // Check if any users are assigned
                $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM `users` WHERE `role_custom_id`=?');
                $cnt->execute([$id]);
                if ((int)$cnt->fetchColumn() > 0) {
                    jsonError('Cannot delete: users are assigned to this role. Reassign them first.', 422);
                }
                $this->pdo->prepare('DELETE FROM `roles` WHERE `id`=?')->execute([$id]);
                jsonResponse(['ok' => true]);
            }

            jsonError('Invalid action.', 400);
        } catch (\PDOException $e) {
            jsonError('Database error: ' . $e->getMessage(), 500);
        }
    }

    public function saveSourceSubs(): void
    {
        requireRole('admin', 'manager');
        csrfVerify();

        $action   = post('action');
        $id       = postInt('id');
        $sourceId = postInt('source_id');
        $name     = trim(post('name'));

        try {
            if ($action === 'add') {
                if ($sourceId <= 0 || $name === '') jsonError('Source and name are required.', 400);
                $chk = $this->pdo->prepare('SELECT `id` FROM `lead_sources` WHERE `id`=? LIMIT 1');
                $chk->execute([$sourceId]);
                if (!$chk->fetch()) jsonError('Parent source not found.', 404);

                $this->pdo->prepare(
                    'INSERT INTO `lead_source_subs` (`source_id`,`name`,`sort_order`) VALUES (?,?,0)'
                )->execute([$sourceId, $name]);
                jsonResponse(['ok' => true, 'id' => (int) $this->pdo->lastInsertId(), 'name' => $name, 'source_id' => $sourceId]);
            }

            if ($action === 'toggle' && $id > 0) {
                $this->pdo->prepare(
                    'UPDATE `lead_source_subs` SET `is_active` = 1 - `is_active` WHERE `id`=?'
                )->execute([$id]);
                jsonResponse(['ok' => true]);
            }

            jsonError('Invalid action.', 400);
        } catch (\PDOException $e) {
            jsonError('Database error: ' . $e->getMessage(), 500);
        }
    }
}
