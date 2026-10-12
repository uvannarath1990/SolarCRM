<?php
declare(strict_types=1);

class ActivitiesController
{
    public function save(): void
    {
        requireLogin();
        csrfVerify();

        $leadId = postInt('lead_id');
        $type   = post('type');
        $notes  = trim(post('notes'));

        $validTypes = ['call', 'email', 'meeting', 'note', 'stage_change', 'quote', 'other'];
        if ($leadId <= 0 || !in_array($type, $validTypes, true)) {
            jsonError('Invalid request.', 400);
        }

        assertLeadAccess($leadId);

        $pdo  = getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO `activities` (`lead_id`,`user_id`,`type`,`notes`,`occurred_at`)
             VALUES (?,?,?,?,NOW())'
        );
        $stmt->execute([$leadId, $_SESSION['user_id'], $type, $notes ?: null]);

        // Update lead updated_at so it surfaces in sorted lists
        $pdo->prepare('UPDATE `leads` SET `updated_at`=NOW() WHERE `id`=?')->execute([$leadId]);

        writeAuditLog($leadId, 'ACTIVITY', 'lead', $type, null, $notes ?: null);

        jsonResponse([
            'ok'         => true,
            'user_name'  => currentUser()['name'],
            'type'       => $type,
            'notes'      => $notes,
            'occurred_at' => date('d M Y, h:i A'),
        ]);
    }
}
