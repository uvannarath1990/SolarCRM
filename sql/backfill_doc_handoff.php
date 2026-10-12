<?php
declare(strict_types=1);

/**
 * One-off backfill: hand the already-won leads to documentation.
 *
 * At the time of writing, 33 of 42 Won leads were missing at least one document
 * and 26 had no documentation owner at all — the oldest had been sitting for 94
 * days. Those leads predate the automatic handoff, so nothing will ever pick
 * them up on its own.
 *
 * Drives the SAME startDocumentationHandoff() the live code uses, so the
 * idempotency, assignment and audit trail are identical. Safe to re-run.
 *
 * Usage (on the server, from the app directory):
 *   php sql/backfill_doc_handoff.php --dry-run     # list what would happen
 *   php sql/backfill_doc_handoff.php --no-push     # do it, quietly
 *
 * --no-push is strongly advised for the first run: without it, every affected
 * documentation user gets a notification per lead, all at once.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("CLI only.\n");
}

$root = dirname(__DIR__);
require_once $root . '/config/db.php';
require_once $root . '/includes/functions.php';
require_once $root . '/includes/auth.php';
require_once $root . '/includes/audit.php';
require_once $root . '/includes/push.php';
require_once $root . '/includes/workflow.php';

$dryRun = in_array('--dry-run', $argv, true);
$noPush = in_array('--no-push', $argv, true) || $dryRun;

// _push_ready() checks this constant, so every send in this process is a no-op.
if ($noPush) define('KRE_BACKFILL_NO_PUSH', true);

$pdo = getPdo();

// Audit rows need an actor; attribute the backfill to the first active admin.
$_SESSION['user_id'] = (int) $pdo->query(
    "SELECT `id` FROM `users` WHERE `role`='admin' AND `is_active`=1 ORDER BY `id` LIMIT 1"
)->fetchColumn();
$_SESSION['role'] = 'admin';

$rows = $pdo->query(
    "SELECT `id`, `name`
       FROM `leads`
      WHERE `stage`='Won' AND `deleted_at` IS NULL
        AND NOT EXISTS (SELECT 1 FROM `workflow_tasks` wt
                         WHERE wt.`lead_id` = `leads`.`id`
                           AND wt.`assigned_role` = 'documentation')
      ORDER BY `id`"
)->fetchAll();

printf("%d lead(s) need a documentation handoff.%s\n\n", count($rows), $dryRun ? ' (dry run)' : '');

// When a lead was actually won, recovered from the audit log. Dating a task
// "today + 3" on a lead won 94 days ago would make the SLA meaningless for
// exactly the cohort that needs it most.
$wonAt = $pdo->prepare(
    "SELECT MIN(`created_at`) FROM `audit_log`
      WHERE `lead_id`=? AND `action`='STAGE_CHANGE' AND `new_value`='Won'"
);

$done = 0;
foreach ($rows as $r) {
    $leadId = (int) $r['id'];

    $wonAt->execute([$leadId]);
    $won = $wonAt->fetchColumn() ?: null;

    if ($dryRun) {
        printf("  #%-5d %-28s won %s\n", $leadId, mb_substr((string) $r['name'], 0, 28),
               $won ? date('d M Y', strtotime($won)) : 'unknown');
        continue;
    }

    startDocumentationHandoff($leadId, $pdo);

    // Back-date the clock and the due date to the real win, so the queue ages
    // these leads honestly from day one.
    if ($won) {
        $pdo->prepare(
            "UPDATE `leads`
                SET `documentation_started_at` = ?,
                    `updated_at` = `updated_at`
              WHERE `id`=? AND `documentation_started_at` IS NOT NULL"
        )->execute([$won, $leadId]);

        $pdo->prepare(
            "UPDATE `workflow_tasks`
                SET `due_date` = DATE_ADD(DATE(?), INTERVAL 3 DAY)
              WHERE `lead_id`=? AND `assigned_role`='documentation' AND `status`='pending'"
        )->execute([$won, $leadId]);
    }

    $owner = $pdo->prepare(
        'SELECT u.`name` FROM `leads` l JOIN `users` u ON u.`id`=l.`documentation_user_id` WHERE l.`id`=?'
    );
    $owner->execute([$leadId]);

    printf("  #%-5d %-28s -> %s\n", $leadId, mb_substr((string) $r['name'], 0, 28),
           $owner->fetchColumn() ?: 'UNASSIGNED');
    $done++;
}

if (!$dryRun) {
    printf("\n%d lead(s) handed over.\n", $done);
    echo "\nResulting documentation load:\n";
    foreach ($pdo->query(
        "SELECT u.`name`, COUNT(wt.`id`) AS pending
           FROM `users` u
           LEFT JOIN `workflow_tasks` wt ON wt.`assigned_to`=u.`id`
                AND wt.`assigned_role`='documentation' AND wt.`status`='pending'
          WHERE u.`role`='documentation' AND u.`is_active`=1
          GROUP BY u.`id` ORDER BY pending DESC"
    ) as $u) {
        printf("  %-16s %d pending\n", $u['name'], $u['pending']);
    }
}
