<?php
declare(strict_types=1);

class RemindersController
{
    public function save(): void
    {
        requireLogin();
        csrfVerify();

        $leadId   = postInt('lead_id');
        $remindAt = post('remind_at');
        $message  = trim(post('message'));

        if ($leadId <= 0 || $remindAt === '' || $message === '') {
            jsonError('Lead, date-time, and message are required.', 400);
        }

        assertLeadAccess($leadId);

        $pdo  = getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `reminders` (`lead_id`,`user_id`,`remind_at`,`message`)
             VALUES (?,?,?,?)'
        );
        $stmt->execute([$leadId, $_SESSION['user_id'], $remindAt, $message]);
        $id = (int) $pdo->lastInsertId();

        jsonResponse([
            'ok'        => true,
            'id'        => $id,
            'remind_at' => $remindAt,
            'message'   => $message,
        ]);
    }

    public function markDone(): void
    {
        requireLogin();
        csrfVerify();

        $id  = postInt('id');
        $pdo = getPdo();

        // Verify ownership (user can only mark their own reminders done, admin can mark any)
        $stmt = $pdo->prepare('SELECT `user_id`,`is_done` FROM `reminders` WHERE `id`=? LIMIT 1');
        $stmt->execute([$id]);
        $rem = $stmt->fetch();

        if (!$rem) {
            jsonError('Reminder not found.', 404);
        }
        if (!hasRole('admin') && (int) $rem['user_id'] !== (int) $_SESSION['user_id']) {
            jsonError('Access denied.', 403);
        }

        $newDone = $rem['is_done'] ? 0 : 1;
        $pdo->prepare('UPDATE `reminders` SET `is_done`=? WHERE `id`=?')
            ->execute([$newDone, $id]);

        jsonResponse(['ok' => true, 'is_done' => (bool) $newDone]);
    }

    public function delete(): void
    {
        requireLogin();
        csrfVerify();

        $id  = postInt('id');
        $pdo = getPdo();

        $stmt = $pdo->prepare('SELECT `user_id` FROM `reminders` WHERE `id`=? LIMIT 1');
        $stmt->execute([$id]);
        $rem = $stmt->fetch();

        if (!$rem) {
            jsonError('Reminder not found.', 404);
        }
        if (!hasRole('admin') && (int) $rem['user_id'] !== (int) $_SESSION['user_id']) {
            jsonError('Access denied.', 403);
        }

        $pdo->prepare('DELETE FROM `reminders` WHERE `id`=?')->execute([$id]);
        jsonResponse(['ok' => true]);
    }
}
