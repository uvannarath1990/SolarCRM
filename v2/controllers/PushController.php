<?php
declare(strict_types=1);

class PushController
{
    /**
     * POST /push/subscribe
     * Save a browser push subscription for the current user.
     */
    public function subscribe(): void
    {
        requireLogin();
        csrfVerify();

        $body     = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        $endpoint = trim($body['endpoint'] ?? '');
        $p256dh   = trim($body['keys']['p256dh'] ?? '');
        $auth     = trim($body['keys']['auth'] ?? '');

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            jsonError('Invalid subscription data.', 400);
        }

        $pdo  = getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `push_subscriptions` (`user_id`,`endpoint`,`p256dh`,`auth`)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE `p256dh`=VALUES(`p256dh`), `auth`=VALUES(`auth`)'
        );
        $stmt->execute([$_SESSION['user_id'], $endpoint, $p256dh, $auth]);

        jsonResponse(['ok' => true]);
    }

    /**
     * POST /push/unsubscribe
     * Remove a push subscription.
     */
    public function unsubscribe(): void
    {
        requireLogin();
        csrfVerify();

        $body     = (array) (json_decode(file_get_contents('php://input'), true) ?? []);
        $endpoint = trim($body['endpoint'] ?? '');

        if ($endpoint !== '') {
            getPdo()->prepare('DELETE FROM `push_subscriptions` WHERE `user_id`=? AND `endpoint`=?')
                ->execute([$_SESSION['user_id'], $endpoint]);
        }

        jsonResponse(['ok' => true]);
    }

    /**
     * GET /push/vapid-public-key
     * Return the VAPID public key so the frontend can subscribe.
     */
    public function vapidPublicKey(): void
    {
        requireLogin();
        $key = getSetting('vapid_public_key');
        if ($key === '') {
            jsonError('VAPID not configured. Run /push/setup as admin.', 503);
        }
        jsonResponse(['publicKey' => $key]);
    }

    /**
     * GET /push/setup  (admin only — run once)
     * Generates and stores VAPID keys.
     */
    public function setup(): void
    {
        requireLogin();
        requireRole('admin');

        $existing = getSetting('vapid_public_key');
        if ($existing !== '') {
            echo 'VAPID keys already generated.<br>Public key:<br><code>' . htmlspecialchars($existing) . '</code>';
            exit;
        }

        $keys = generateVapidKeys();

        $pdo = getPdo();
        $pdo->prepare("UPDATE `settings` SET `value`=? WHERE `key_name`='vapid_public_key'")->execute([$keys['publicKey']]);
        $pdo->prepare("UPDATE `settings` SET `value`=? WHERE `key_name`='vapid_private_key'")->execute([$keys['privateKey']]);

        echo 'VAPID keys generated and saved!<br>Public key:<br><code>' . htmlspecialchars($keys['publicKey']) . '</code>';
        exit;
    }

    /**
     * GET /push/check-reminders
     * Called by JS on page load — pushes due reminders for the current user.
     */
    public function checkReminders(): void
    {
        requireLogin();

        $userId = (int) $_SESSION['user_id'];
        $pdo    = getPdo();

        // Find reminders due in the next 15 minutes that haven't been notified yet
        $stmt = $pdo->prepare(
            "SELECT `id`,`message`,`lead_id`
             FROM `reminders`
             WHERE `user_id`=?
               AND `is_done`=0
               AND `remind_at` <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)
               AND `remind_at` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
               AND (`notified_at` IS NULL OR `notified_at` < DATE_SUB(NOW(), INTERVAL 30 MINUTE))
             ORDER BY `remind_at` ASC
             LIMIT 5"
        );
        $stmt->execute([$userId]);
        $due = $stmt->fetchAll();

        foreach ($due as $rem) {
            $url = BASE_URL . '/leads/detail?id=' . $rem['lead_id'];
            sendPushToUser($userId, 'Reminder', $rem['message'], $url);

            $pdo->prepare('UPDATE `reminders` SET `notified_at`=NOW() WHERE `id`=?')
                ->execute([$rem['id']]);
        }

        jsonResponse(['ok' => true, 'sent' => count($due)]);
    }
}
