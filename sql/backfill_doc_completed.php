<?php
declare(strict_types=1);

/**
 * One-off tidy: record the documentation that was already finished.
 *
 * `documentation_status` was added without a backfill, so leads whose
 * documentation was completed through a workflow task months earlier still read
 * 'not_started'. The portal derives the truth from the tasks, but the stored
 * value stays misleading — and any future query that trusts the column would be
 * wrong.
 *
 * Sets status, started_at and completed_at from the task's own dates, so the
 * "days to complete" average reflects what actually happened.
 *
 * Only touches leads that have a completed documentation task, no open one, and
 * a status still reading 'not_started'. Safe to re-run.
 *
 *   php sql/backfill_doc_completed.php --dry-run
 *   php sql/backfill_doc_completed.php --apply
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

$apply = in_array('--apply', $argv, true);
$pdo   = getPdo();

$_SESSION['user_id'] = (int) $pdo->query(
    "SELECT `id` FROM `users` WHERE `role`='admin' AND `is_active`=1 ORDER BY `id` LIMIT 1"
)->fetchColumn();
$_SESSION['role'] = 'admin';

// The task that finished the work, and when it started.
$rows = $pdo->query(
    "SELECT l.`id`, l.`name`, l.`documentation_started_at`,
            MIN(d.`created_at`)   AS task_started,
            MAX(d.`completed_at`) AS task_completed
       FROM `leads` l
       JOIN `workflow_tasks` d
            ON d.`lead_id` = l.`id` AND d.`assigned_role` = 'documentation' AND d.`status` = 'done'
      WHERE l.`deleted_at` IS NULL
        AND l.`documentation_status` = 'not_started'
        AND NOT EXISTS (SELECT 1 FROM `workflow_tasks` p
                         WHERE p.`lead_id` = l.`id`
                           AND p.`assigned_role` = 'documentation'
                           AND p.`status` = 'pending')
      GROUP BY l.`id`
      ORDER BY l.`id`"
)->fetchAll();

printf("%d lead(s) to mark complete.%s\n\n", count($rows), $apply ? '' : ' (dry run)');

// `updated_at` = `updated_at` keeps MySQL's ON UPDATE from firing. The
// dashboard's "won this month" KPI reads that column, and most of these leads
// are Won — without the guard this tidy would silently inflate the figure.
$upd = $pdo->prepare(
    "UPDATE `leads`
        SET `documentation_status`     = 'completed',
            `documentation_started_at` = COALESCE(`documentation_started_at`, ?),
            `documentation_completed_at` = COALESCE(`documentation_completed_at`, ?),
            `updated_at`               = `updated_at`
      WHERE `id` = ? AND `documentation_status` = 'not_started'"
);

$done = 0;
foreach ($rows as $r) {
    $days = ($r['task_started'] && $r['task_completed'])
          ? (int) round((strtotime($r['task_completed']) - strtotime($r['task_started'])) / 86400)
          : null;

    printf("  #%-5d %-26s done %s%s\n",
        (int) $r['id'],
        mb_substr((string) $r['name'], 0, 26),
        $r['task_completed'] ? date('d M Y', strtotime($r['task_completed'])) : 'date unknown',
        $days !== null ? sprintf('  (%d days)', $days) : ''
    );

    if (!$apply) continue;

    $upd->execute([$r['task_started'], $r['task_completed'], (int) $r['id']]);
    if ($upd->rowCount() > 0) {
        auditUpdate((int) $r['id'], 'documentation_status', 'not_started', 'completed');
        $done++;
    }
}

if ($apply) printf("\n%d lead(s) updated.\n", $done);
