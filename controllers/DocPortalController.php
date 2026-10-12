<?php
declare(strict_types=1);

/**
 * Documentation portal.
 *
 * A separate workspace for the documentation team, partners and managers,
 * reached through the Documentation tab on the login page. It tracks every lead
 * whose documentation is live and holds the uploaded files.
 *
 * Scoping always goes through docPortalScopeSql() / docPortalCanAccessLead(),
 * never canAccessLead() — the latter also grants a lead's creator and its sales
 * rep, which would pull non-documentation leads into this portal.
 *
 * Note: v1 and v2 share a session cookie, so $_SESSION['portal_mode'] is
 * navigation state only. Every route here re-checks access on its own.
 */
class DocPortalController
{
    private PDO $pdo;

    /** Documents the browser may render inline; everything else downloads. */
    private const INLINE_SAFE = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
    ];

    /** Extension => the MIME types finfo may legitimately report for it. */
    private const ALLOWED = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        'doc'  => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip'  => ['application/zip'],
    ];

    public function __construct()
    {
        $this->pdo = getPdo();
    }


    // ── GET /doc-portal ───────────────────────────────────────────────────────
    /**
     * Documentation home: the numbers, then the work.
     *
     * Scoped the same way as everything else in the portal — a documentation
     * user sees their own figures, a manager sees their reportees', admins and
     * the Head see everyone.
     */
    public function home(): void
    {
        requirePortalAccess();

        [$teamIds, $scopeLabel] = docPortalTeamScope($this->pdo);
        $elapsed = documentationElapsedDaysSql('l');

        // Headline figures, over the leads this viewer can see.
        $params = [];
        $scope  = docPortalScopeSql($params);
        $base   = "FROM `leads` l WHERE l.`deleted_at` IS NULL AND l.`stage` <> 'Lost' AND ({$scope})";

        $sql = "SELECT
            (SELECT COUNT(*) {$base} AND l.`documentation_status` IN ('in_progress','on_hold')) AS open_now,
            (SELECT COUNT(*) {$base} AND l.`documentation_status` = 'on_hold') AS with_sales,
            (SELECT COUNT(*) {$base} AND l.`documentation_status` IN ('in_progress','on_hold')
                 AND l.`documentation_started_at` IS NOT NULL AND ({$elapsed}) >= 10) AS overdue,
            (SELECT COUNT(*) {$base} AND l.`documentation_status` = 'completed'
                 AND MONTH(l.`documentation_completed_at`) = MONTH(NOW())
                 AND YEAR(l.`documentation_completed_at`) = YEAR(NOW())) AS done_month,
            (SELECT COUNT(*) {$base} AND l.`stage` = 'Won'
                 AND (l.`doc_aadhar` + l.`doc_registered_mobile`
                    + l.`doc_kseb_bill` + l.`doc_bank_passbook`) < 4) AS won_incomplete,
            (SELECT ROUND(AVG(DATEDIFF(l.`documentation_completed_at`, l.`documentation_started_at`)))
                {$base} AND l.`documentation_status`='completed'
                 AND l.`documentation_completed_at` >= NOW() - INTERVAL 90 DAY) AS avg_days";

        // The scope placeholders repeat once per subquery — six here.
        $all = [];
        for ($i = 0; $i < 6; $i++) $all = array_merge($all, $params);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($all);
        $stats = $stmt->fetch() ?: [];

        // Which document is holding things up, across won leads in scope.
        $mParams = [];
        $mScope  = docPortalScopeSql($mParams);
        $missStmt = $this->pdo->prepare(
            "SELECT SUM(l.`doc_aadhar`=0) AS aadhar, SUM(l.`doc_registered_mobile`=0) AS mobile,
                    SUM(l.`doc_kseb_bill`=0) AS kseb, SUM(l.`doc_bank_passbook`=0) AS passbook
               FROM `leads` l
              WHERE l.`deleted_at` IS NULL AND l.`stage`='Won' AND ({$mScope})"
        );
        $missStmt->execute($mParams);
        $missing = $missStmt->fetch() ?: [];

        // Per-person rows. This is what a manager opens the page for.
        $team = [];
        if ($teamIds) {
            $in = implode(',', array_map('intval', $teamIds));
            $team = $this->pdo->query(
                "SELECT u.`id`, u.`name`,
                        SUM(l.`documentation_status` IN ('in_progress','on_hold')) AS open_now,
                        SUM(l.`documentation_status` = 'on_hold') AS with_sales,
                        SUM(l.`documentation_status` IN ('in_progress','on_hold')
                            AND l.`documentation_started_at` IS NOT NULL
                            AND ({$elapsed}) >= 10) AS overdue,
                        MAX(CASE WHEN l.`documentation_status` IN ('in_progress','on_hold')
                                 THEN ROUND({$elapsed}) END) AS oldest_days,
                        SUM(l.`documentation_status`='completed'
                            AND MONTH(l.`documentation_completed_at`)=MONTH(NOW())
                            AND YEAR(l.`documentation_completed_at`)=YEAR(NOW())) AS done_month
                   FROM `users` u
                   LEFT JOIN `leads` l
                          ON l.`documentation_user_id` = u.`id` AND l.`deleted_at` IS NULL
                  WHERE u.`id` IN ({$in})
                  GROUP BY u.`id`
                  ORDER BY overdue DESC, open_now DESC, u.`name`"
            )->fetchAll();
        }

        // The few things most worth acting on, newest pain first.
        $uParams = [];
        $uScope  = docPortalScopeSql($uParams);
        $urgent  = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`documentation_status`, l.`doc_hold_reason`,
                    ROUND({$elapsed}) AS doc_days, du.`name` AS doc_user_name
               FROM `leads` l
               LEFT JOIN `users` du ON du.`id` = l.`documentation_user_id`
              WHERE l.`deleted_at` IS NULL AND l.`stage` <> 'Lost'
                AND l.`documentation_status` IN ('in_progress','on_hold')
                AND l.`documentation_started_at` IS NOT NULL
                AND ({$uScope})
              ORDER BY ({$elapsed}) DESC
              LIMIT 8"
        );
        $urgent->execute($uParams);
        $urgentLeads = $urgent->fetchAll();

        $pageTitle = 'Documentation';
        require __DIR__ . '/../views/doc_portal/home.php';
    }

    // ── GET /doc-portal/queue ─────────────────────────────────────────────────
    public function index(): void
    {
        requirePortalAccess();

        // One queue, four views. Pending / Received used to be separate v1 pages
        // showing the same leads through the legacy four-item checklist; folding
        // them in here keeps documentation work in a single place.
        $view = (string) ($_GET['view'] ?? ($_GET['status'] ?? 'active'));
        if (!in_array($view, ['active', 'overdue', 'completed', 'pending', 'received'], true)) {
            $view = 'active';
        }
        $status = $view;   // the view template still reads $status

        $q    = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $per  = 50;

        $params = [];
        $where  = ['l.`deleted_at` IS NULL', "l.`stage` <> 'Lost'"];

        // The legacy checklist predicate, unchanged from DocumentationController.
        $checklist = '(l.`doc_aadhar`=1 AND l.`doc_registered_mobile`=1
                       AND l.`doc_kseb_bill`=1 AND l.`doc_bank_passbook`=1)';

        $legacyDone = documentationLegacyDoneSql('l');

        if ($view === 'completed') {
            // Historical leads finished via a task count as complete too.
            $where[] = "(l.`documentation_status` = 'completed'
                         OR (l.`documentation_status` = 'not_started' AND {$legacyDone}))";
        } elseif ($view === 'overdue') {
            // Amber threshold, measured in working time — a lead sitting with
            // sales is not the documentation team's clock.
            $where[] = "l.`documentation_status` IN ('in_progress','on_hold')";
            $where[] = 'l.`documentation_started_at` IS NOT NULL';
            $where[] = '(' . documentationElapsedDaysSql('l') . ') >= 10';
        } elseif ($view === 'pending') {
            $where[] = 'l.`site_visit_completed_date` IS NOT NULL';
            $where[] = 'NOT ' . $checklist;
        } elseif ($view === 'received') {
            $where[] = 'l.`site_visit_completed_date` IS NOT NULL';
            $where[] = $checklist;
        } else {
            // The second arm keeps historical leads visible — they all read
            // 'not_started' because the column was added without a backfill.
            // But not the ones whose documentation task is already done: that
            // work is finished, and showing it as live was simply wrong.
            $where[] = "(l.`documentation_status` IN ('in_progress','on_hold')
                         OR (l.`documentation_status` = 'not_started'
                             AND l.`site_visit_completed_date` IS NOT NULL
                             AND NOT {$legacyDone}))";
        }

        $where[] = docPortalScopeSql($params);

        if ($q !== '') {
            $where[] = "(l.`name` LIKE ? OR l.`location` LIKE ? OR l.`phone` LIKE ?)";
            $like    = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        // Owner filter — how a manager drills in from a team row on the home page.
        $ownerId = (int) ($_GET['user'] ?? 0);
        if ($ownerId > 0) {
            $where[]  = 'l.`documentation_user_id` = ?';
            $params[] = $ownerId;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM `leads` l WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $per;
        $stmt = $this->pdo->prepare(
            "SELECT l.`id`, l.`name`, l.`phone`, l.`phone_revealed`, l.`location`, l.`stage`,
                    l.`potential_kw`, l.`site_visit_completed_date`,
                    l.`documentation_status`, l.`documentation_started_at`,
                    l.`documentation_completed_at`,
                    l.`doc_aadhar`, l.`doc_registered_mobile`, l.`doc_kseb_bill`, l.`doc_bank_passbook`,
                    du.`name` AS doc_user_name,
                    r.`name`  AS rep_name,
                    DATEDIFF(CURDATE(), l.`site_visit_completed_date`) AS days_since_visit,
                    ROUND(" . documentationElapsedDaysSql('l') . ") AS doc_days,
                    l.`doc_hold_reason`, l.`doc_hold_at`,
                    (SELECT COUNT(*) FROM `lead_documents` ld
                      WHERE ld.`lead_id` = l.`id` AND ld.`deleted_at` IS NULL) AS doc_count
             FROM `leads` l
             LEFT JOIN `users` du ON du.`id` = l.`documentation_user_id`
             LEFT JOIN `users` r  ON r.`id`  = l.`assigned_rep_id`
             WHERE {$whereSql}
             ORDER BY FIELD(l.`documentation_status`,'on_hold','in_progress','not_started','completed'),
                      l.`site_visit_completed_date` ASC, l.`id` DESC
             LIMIT {$per} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $leads = $stmt->fetchAll();

        $ownerName = '';
        if ($ownerId > 0) {
            $on = $this->pdo->prepare('SELECT `name` FROM `users` WHERE `id`=?');
            $on->execute([$ownerId]);
            $ownerName = (string) $on->fetchColumn();
        }

        $pageTitle = 'Documents';
        $backLink  = BASE_URL . '/doc-portal';
        $pages     = max(1, (int) ceil($total / $per));
        $tabCounts = $this->viewCounts();
        require __DIR__ . '/../views/doc_portal/index.php';
    }

    // ── GET /doc-portal/lead ──────────────────────────────────────────────────
    public function lead(): void
    {
        requirePortalAccess();

        $leadId = (int) ($_GET['id'] ?? 0);
        if (!docPortalCanAccessLead($this->pdo, $leadId)) {
            http_response_code(403);
            die('Access denied.');
        }

        $stmt = $this->pdo->prepare(
            "SELECT l.*, du.`name` AS doc_user_name, r.`name` AS rep_name
             FROM `leads` l
             LEFT JOIN `users` du ON du.`id` = l.`documentation_user_id`
             LEFT JOIN `users` r  ON r.`id`  = l.`assigned_rep_id`
             WHERE l.`id` = ? LIMIT 1"
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
            flash('danger', 'Lead not found.');
            redirect('/doc-portal');
        }

        $docs = $this->pdo->prepare(
            "SELECT ld.*, u.`name` AS uploader_name
             FROM `lead_documents` ld
             LEFT JOIN `users` u ON u.`id` = ld.`uploaded_by`
             WHERE ld.`lead_id` = ? AND ld.`deleted_at` IS NULL
             ORDER BY ld.`created_at` DESC"
        );
        $docs->execute([$leadId]);
        $documents = $docs->fetchAll();

        $docUsers = $this->pdo->query(
            "SELECT `id`,`name` FROM `users`
             WHERE `role`='documentation' AND `is_active`=1 ORDER BY `name`"
        )->fetchAll();

        // The documentation task and any bounce-backs to sales. The portal has
        // no Tasks page, so without this a documentation user can neither see
        // the task the handoff created for them nor ever close it.
        $tasks = $this->pdo->prepare(
            "SELECT wt.`id`, wt.`title`, wt.`status`, wt.`due_date`, wt.`notes`,
                    wt.`assigned_role`, wt.`assigned_to`, wt.`completed_at`,
                    a.`name` AS assignee_name,
                    c.`name` AS completer_name,
                    DATEDIFF(CURDATE(), wt.`due_date`) AS days_late
               FROM `workflow_tasks` wt
               LEFT JOIN `users` a ON a.`id` = wt.`assigned_to`
               LEFT JOIN `users` c ON c.`id` = wt.`completed_by`
              WHERE wt.`lead_id` = ?
                AND (wt.`assigned_role` = 'documentation' OR wt.`stage` = 'Documentation')
              ORDER BY FIELD(wt.`status`,'pending','skipped','done','cancelled'),
                       wt.`due_date` ASC, wt.`id` DESC"
        );
        $tasks->execute([$leadId]);
        $docTasks = $tasks->fetchAll();

        $pageTitle = $lead['name'];
        $pageSub   = '#' . $lead['id'] . ' · documentation';
        $backLink  = BASE_URL . '/doc-portal';
        require __DIR__ . '/../views/doc_portal/lead.php';
    }

    // ── POST /doc-portal/status ───────────────────────────────────────────────
    public function saveStatus(): void
    {
        requirePortalAccess();
        csrfVerify();

        $leadId = postInt('lead_id');
        if (!docPortalCanAccessLead($this->pdo, $leadId)) {
            jsonError('Access denied.', 403);
        }

        $status = (string) post('status');
        $valid  = ['not_started', 'in_progress', 'on_hold', 'completed'];
        if (!in_array($status, $valid, true)) {
            jsonError('Invalid status.', 422);
        }

        $note = trim((string) post('note'));
        if (mb_strlen($note) > 500) $note = mb_substr($note, 0, 500);

        $cur = $this->pdo->prepare(
            'SELECT `documentation_status`,`documentation_note` FROM `leads` WHERE `id`=?'
        );
        $cur->execute([$leadId]);
        $before = $cur->fetch() ?: [];

        // Timestamps are set once and kept: started_at records when work actually
        // began, so moving back and forth must not keep resetting it.
        //
        // The branching happens here rather than in a SQL CASE because MariaDB
        // cannot resolve a placeholder's type inside `CASE WHEN ? IN (...)` with
        // server-side prepares (ATTR_EMULATE_PREPARES is off) and errors with
        // "Unknown column 'in_progress'". $status is whitelisted above, so
        // deciding in PHP is both safe and easier to read.
        $sets   = ['`documentation_status` = ?', '`documentation_note` = ?'];
        $params = [$status, $note ?: null];

        if (in_array($status, ['in_progress', 'on_hold', 'completed'], true)) {
            $sets[] = '`documentation_started_at` = COALESCE(`documentation_started_at`, NOW())';
        }
        $sets[] = $status === 'completed'
                ? '`documentation_completed_at` = COALESCE(`documentation_completed_at`, NOW())'
                : '`documentation_completed_at` = NULL';

        // `updated_at` is ON UPDATE CURRENT_TIMESTAMP and the dashboard's
        // "won this month" KPI counts it. Assigning the column to itself stops
        // MySQL touching it, so documentation work never re-dates a won deal.
        $sets[] = '`updated_at` = `updated_at`';

        $params[] = $leadId;
        $this->pdo->prepare(
            'UPDATE `leads` SET ' . implode(', ', $sets) . ' WHERE `id` = ?'
        )->execute($params);

        if (($before['documentation_status'] ?? '') !== $status) {
            auditUpdate($leadId, 'documentation_status', $before['documentation_status'] ?? '', $status);
        }
        if (trim((string) ($before['documentation_note'] ?? '')) !== $note) {
            auditUpdate($leadId, 'documentation_note', $before['documentation_note'] ?? '', $note);
        }

        jsonResponse(['ok' => true, 'status' => $status]);
    }

    // ── POST /doc-portal/assign ───────────────────────────────────────────────
    public function assign(): void
    {
        requirePortalAccess();
        csrfVerify();

        if (!hasRole('admin', 'manager') && !isDocumentationHead()) {
            jsonError('Only managers and the documentation head can assign.', 403);
        }

        $leadId = postInt('lead_id');
        if (!docPortalCanAccessLead($this->pdo, $leadId)) {
            jsonError('Access denied.', 403);
        }

        $userId = postInt('user_id') ?: null;
        if ($userId !== null) {
            $chk = $this->pdo->prepare(
                "SELECT 1 FROM `users` WHERE `id`=? AND `role`='documentation' AND `is_active`=1"
            );
            $chk->execute([$userId]);
            if (!$chk->fetchColumn()) jsonError('Choose an active documentation user.', 422);
        }

        $cur = $this->pdo->prepare('SELECT `documentation_user_id` FROM `leads` WHERE `id`=?');
        $cur->execute([$leadId]);
        $before = $cur->fetchColumn();

        $this->pdo->prepare(
            'UPDATE `leads` SET `documentation_user_id`=?, `updated_at`=`updated_at` WHERE `id`=?'
        )->execute([$userId, $leadId]);
        auditUpdate($leadId, 'documentation_user_id', (string) $before, (string) $userId);

        jsonResponse(['ok' => true]);
    }


    // ── POST /doc-portal/return-to-sales ──────────────────────────────────────
    /**
     * Hand a lead back to the sales rep with a reason, and pause the SLA clock.
     *
     * The rep owns the customer relationship, so chasing a mismatched name or an
     * unreadable scan belongs with them. "on_hold" is reused rather than adding a
     * fifth status: doc_hold_reason carries the distinction, and v1 reads this
     * column blind.
     */
    public function returnToSales(): void
    {
        requirePortalAccess();
        csrfVerify();

        $leadId = postInt('lead_id');
        if (!docPortalCanAccessLead($this->pdo, $leadId)) {
            jsonError('Access denied.', 403);
        }

        // A return with no reason is the exact failure this path exists to stop.
        $reason = trim((string) post('reason'));
        if (mb_strlen($reason) < 10) {
            jsonError('Say what is wrong, so the rep knows what to fix.', 422);
        }
        $reason = mb_substr($reason, 0, 500);

        $stmt = $this->pdo->prepare(
            'SELECT `id`,`name`,`stage`,`documentation_status`,`assigned_rep_id`,
                    `tele_caller_id`,`created_by`,`documentation_user_id`
             FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$leadId]);
        $lead = $stmt->fetch();

        if (!$lead)                                       jsonError('Lead not found.', 404);
        if ($lead['documentation_status'] === 'on_hold')  jsonError('This lead is already with sales.', 422);

        // A task assigned to nobody is invisible to everyone, so fall back to a
        // manager when the lead has no rep — many won leads do not.
        $repId = (int) ($lead['assigned_rep_id'] ?? 0)
               ?: (int) (resolveWorkflowAssignee('manager', $lead, $this->pdo) ?? 0);

        $title = mb_substr('Documentation blocked: ' . $reason, 0, 200);
        $this->pdo->prepare(
            'INSERT INTO `workflow_tasks`
                (`lead_id`,`stage`,`title`,`assigned_to`,`assigned_role`,`due_date`,`notes`)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $leadId, 'Documentation', $title, $repId ?: null, 'sales_rep',
            date('Y-m-d', strtotime('+2 days')), $reason,
        ]);
        $taskId = (int) $this->pdo->lastInsertId();
        auditTaskCreate($taskId, $leadId, $title, 'doc return-to-sales');

        $this->pdo->prepare(
            "UPDATE `leads`
                SET `documentation_status` = 'on_hold',
                    `doc_hold_reason`      = ?,
                    `doc_hold_at`          = NOW(),
                    `doc_hold_task_id`     = ?,
                    `updated_at`           = `updated_at`
              WHERE `id` = ?"
        )->execute([$reason, $taskId, $leadId]);

        auditUpdate($leadId, 'documentation_status', (string) $lead['documentation_status'], 'on_hold');

        if ($repId) {
            sendPushToUser(
                $repId,
                'Documentation needs you',
                $lead['name'] . ' — ' . mb_substr($reason, 0, 80),
                BASE_URL . '/leads/detail?id=' . $leadId
            );
        }

        jsonResponse(['ok' => true, 'task_id' => $taskId]);
    }

    // ── POST /doc-portal/upload ───────────────────────────────────────────────
    public function upload(): void
    {
        // requireLogin, not requirePortalAccess: sales reps and tele callers are
        // not portal users but are exactly the people holding the documents.
        requireLogin();

        // Exceeding post_max_size gives PHP an empty $_POST *and* $_FILES, which
        // would otherwise surface as a baffling CSRF failure. Catch it first.
        if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            flash('danger', 'That file is too large to upload.');
            redirect('/doc-portal');
        }

        csrfVerify();

        $leadId = postInt('lead_id');
        // Authorise before touching a single byte of the upload.
        if (!canAccessLeadFiles($this->pdo, $leadId)) {
            http_response_code(403);
            die('Access denied.');
        }

        // Sent by the drag-and-drop uploader, which wants JSON and progress
        // rather than a full page reload.
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        $fail = function (string $msg) use ($wantsJson, $leadId) {
            if ($wantsJson) jsonError($msg, 422);
            flash('danger', $msg);
            redirect('/doc-portal/lead?id=' . $leadId);
        };

        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
            $fail('Choose at least one file.');
        }
        if (disk_free_space(dirname(UPLOAD_ROOT)) < MIN_FREE_BYTES) {
            $fail('The server is low on storage. Contact the administrator.');
        }

        // One label per file. A single `label` is still accepted as a fallback
        // for the no-JavaScript form, but uploading three documents under one
        // shared name was the main thing wrong with the first version.
        $labels   = (array) ($_POST['labels'] ?? []);
        $fallback = mb_substr(trim((string) post('label')), 0, 120);

        $used   = (int) $this->leadBytes($leadId);
        $ok     = 0;
        $errors = [];
        $added  = [];
        $count  = min(count($_FILES['files']['name']), 20);

        for ($i = 0; $i < $count; $i++) {
            $name = (string) $_FILES['files']['name'][$i];
            $tmp  = (string) $_FILES['files']['tmp_name'][$i];
            $err  = (int) $_FILES['files']['error'][$i];
            $size = (int) $_FILES['files']['size'][$i];

            $label = mb_substr(trim((string) ($labels[$i] ?? '')), 0, 120);
            if ($label === '') $label = $fallback;
            // Falling back to the filename beats refusing the upload; the label
            // can be corrected in place afterwards.
            if ($label === '') $label = pathinfo($this->shortName($name), PATHINFO_FILENAME) ?: 'Document';

            $result = $this->storeOne($leadId, $label, $name, $tmp, $err, $size, $used);
            if ($result === true) {
                $ok++;
                $used    = (int) $this->leadBytes($leadId);
                $added[] = $label;
            } else {
                $errors[] = $this->shortName($name) . ': ' . $result;
            }
        }

        if ($ok > 0) {
            // First file starts the clock, if nobody has set a status yet.
            $this->pdo->prepare(
                "UPDATE `leads`
                    SET `documentation_status` = CASE WHEN `documentation_status` = 'not_started'
                                                      THEN 'in_progress' ELSE `documentation_status` END,
                        `documentation_started_at` = COALESCE(`documentation_started_at`, NOW()),
                        `updated_at` = `updated_at`
                  WHERE `id` = ?"
            )->execute([$leadId]);
        }

        if ($wantsJson) {
            jsonResponse(['ok' => $ok > 0, 'uploaded' => $ok, 'errors' => $errors]);
        }

        if ($ok > 0)     flash('success', $ok . ' file' . ($ok !== 1 ? 's' : '') . ' uploaded.');
        if ($errors)     flash('danger', implode('<br>', array_map('htmlspecialchars', $errors)), true);
        redirect('/doc-portal/lead?id=' . $leadId);
    }

    // ── POST /doc-portal/rename ───────────────────────────────────────────────
    public function rename(): void
    {
        requireLogin();
        csrfVerify();

        $id   = postInt('id');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `lead_documents` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) jsonError('File not found.', 404);
        if (!canAccessLeadFiles($this->pdo, (int) $doc['lead_id'])) {
            jsonError('Access denied.', 403);
        }

        $label = mb_substr(trim((string) post('label')), 0, 120);
        if ($label === '') jsonError('Give the document a name.', 422);

        $this->pdo->prepare('UPDATE `lead_documents` SET `label`=? WHERE `id`=?')
                  ->execute([$label, $id]);
        auditUpdate((int) $doc['lead_id'], 'document_renamed', (string) $doc['label'], $label);

        jsonResponse(['ok' => true, 'label' => $label]);
    }

    /**
     * Validate and store one file. Returns true, or a message explaining refusal.
     * Every check runs before the file lands anywhere permanent.
     */
    private function storeOne(int $leadId, string $label, string $name, string $tmp,
                              int $err, int $size, int $used): bool|string
    {
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'too large';
        if ($err === UPLOAD_ERR_NO_FILE)  return 'no file';
        if ($err !== UPLOAD_ERR_OK)       return 'upload failed';

        // Mandatory: without this a crafted tmp_name could point at any file
        // the process can read, e.g. config/db.php.
        if (!is_uploaded_file($tmp)) return 'rejected';

        if ($size <= 0)              return 'empty file';
        if ($size > MAX_DOC_BYTES)   return 'over ' . (MAX_DOC_BYTES / 1048576) . ' MB';
        if ($used + $size > MAX_LEAD_BYTES) return 'this lead has reached its storage limit';

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) return 'file type not allowed';

        // MIME comes from the file's own bytes, never from the browser.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmp);
        if (!in_array($mime, self::ALLOWED[$ext], true)) {
            return 'contents do not match the file type';
        }

        $dir = $this->leadDir($leadId);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return 'could not create storage';
        }

        // A random basename removes path traversal, double extensions
        // (evil.php.jpg), null bytes and collisions in one move.
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest   = $dir . '/' . $stored;

        if (!move_uploaded_file($tmp, $dest)) return 'could not save';
        @chmod($dest, 0600);

        $finalSize = (int) filesize($dest);
        $finalMime = $mime;
        $finalExt  = $ext;
        $origSize  = null;
        $compressed = 0;

        $c = $this->compress($dest, $ext, $mime);
        if ($c !== null) {
            $origSize   = $finalSize;
            $dest       = $c['path'];
            $stored     = basename($dest);
            $finalSize  = $c['size'];
            $finalMime  = $c['mime'];
            $finalExt   = $c['ext'];
            $compressed = 1;
        }

        try {
            $this->pdo->prepare(
                "INSERT INTO `lead_documents`
                    (`lead_id`,`label`,`stored_name`,`original_name`,`mime_type`,`extension`,
                     `size_bytes`,`original_size_bytes`,`compressed`,`sha256`,`uploaded_by`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $leadId, $label, $stored, $this->shortName($name), $finalMime, $finalExt,
                $finalSize, $origSize, $compressed, hash_file('sha256', $dest),
                (int) $_SESSION['user_id'],
            ]);
        } catch (\Throwable $e) {
            @unlink($dest);          // never leave a file with no row pointing at it
            return 'could not record';
        }

        auditUpdate($leadId, 'document_added', '', $label . ' (' . $this->shortName($name) . ')');
        return true;
    }

    // ── GET /doc-portal/file ──────────────────────────────────────────────────
    public function file(): void
    {
        requireLogin();

        // Look up by id only — a filename from the query string is never trusted.
        $id   = (int) ($_GET['id'] ?? 0);
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `lead_documents` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) { http_response_code(404); die('Not found.'); }

        // Re-checked on every single request, so ids cannot be enumerated.
        if (!canAccessLeadFiles($this->pdo, (int) $doc['lead_id'])) {
            http_response_code(403);
            die('Access denied.');
        }

        $path = $this->leadDir((int) $doc['lead_id']) . '/' . $doc['stored_name'];
        $real = realpath($path);
        $root = realpath(UPLOAD_ROOT);

        if ($real === false || $root === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            die('Not found.');
        }

        $mime   = (string) $doc['mime_type'];
        $inline = ($_GET['mode'] ?? 'download') === 'view'
               && in_array($mime, self::INLINE_SAFE, true);

        // Anything not known-safe is forced to download as an opaque blob.
        if (!$inline) $mime = 'application/octet-stream';

        // The download name follows the STORED type: a HEIC that became a JPEG
        // must not be saved as .heic.
        $base = pathinfo($doc['original_name'], PATHINFO_FILENAME);
        $dl   = $base . '.' . $doc['extension'];
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $dl);
        $ascii = str_replace(['"', '\\'], '_', (string) $ascii);

        while (ob_get_level() > 0) ob_end_clean();

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
             . '; filename="' . $ascii . '"'
             . "; filename*=UTF-8''" . rawurlencode($dl));

        readfile($real);
        exit;
    }

    // ── POST /doc-portal/delete-file ──────────────────────────────────────────
    public function deleteFile(): void
    {
        requireLogin();
        csrfVerify();

        $id   = postInt('id');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `lead_documents` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $doc = $stmt->fetch();

        if (!$doc) jsonError('File not found.', 404);
        if (!canAccessLeadFiles($this->pdo, (int) $doc['lead_id'])) {
            jsonError('Access denied.', 403);
        }

        $userId  = (int) $_SESSION['user_id'];
        $isOwner = (int) $doc['uploaded_by'] === $userId;
        if (!$isOwner && !hasRole('admin', 'manager') && !isDocumentationHead()) {
            jsonError('You can only delete files you uploaded.', 403);
        }

        // Soft delete: the file stays for the sweeper. A hard unlink here is
        // unrecoverable and would race an in-flight readfile().
        $this->pdo->prepare(
            'UPDATE `lead_documents` SET `deleted_at`=NOW(), `deleted_by`=? WHERE `id`=?'
        )->execute([$userId, $id]);

        auditUpdate((int) $doc['lead_id'], 'document_deleted', (string) $doc['label'], '');
        jsonResponse(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Row counts for each queue view, so the chips can show numbers.
     * Uses the same scope as the queue itself.
     */
    private function viewCounts(): array
    {
        $params = [];
        $scope  = docPortalScopeSql($params);
        $base   = "FROM `leads` l WHERE l.`deleted_at` IS NULL AND l.`stage` <> 'Lost' AND ({$scope})";
        $check  = '(l.`doc_aadhar`=1 AND l.`doc_registered_mobile`=1
                    AND l.`doc_kseb_bill`=1 AND l.`doc_bank_passbook`=1)';
        $elapsed    = documentationElapsedDaysSql('l');
        $legacyDone = documentationLegacyDoneSql('l');

        $sql = "SELECT
            (SELECT COUNT(*) {$base} AND (l.`documentation_status` IN ('in_progress','on_hold')
                OR (l.`documentation_status`='not_started' AND l.`site_visit_completed_date` IS NOT NULL
                    AND NOT {$legacyDone}))) AS active,
            (SELECT COUNT(*) {$base} AND (l.`documentation_status`='completed'
                OR (l.`documentation_status`='not_started' AND {$legacyDone}))) AS completed,
            (SELECT COUNT(*) {$base} AND l.`site_visit_completed_date` IS NOT NULL AND NOT {$check}) AS pending,
            (SELECT COUNT(*) {$base} AND l.`site_visit_completed_date` IS NOT NULL AND {$check}) AS received,
            (SELECT COUNT(*) {$base} AND l.`documentation_status` IN ('in_progress','on_hold')
                AND l.`documentation_started_at` IS NOT NULL
                AND ({$elapsed}) >= 10) AS overdue";

        // The scope placeholders repeat once per subquery — this loop count must
        // match the number of subqueries above, or PDO raises HY093.
        $all = [];
        for ($i = 0; $i < 5; $i++) $all = array_merge($all, $params);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($all);
        return $stmt->fetch()
            ?: ['active' => 0, 'completed' => 0, 'pending' => 0, 'received' => 0, 'overdue' => 0];
    }

    /** Storage directory for a lead. Derived purely from the id — never from input. */
    private function leadDir(int $leadId): string
    {
        return UPLOAD_ROOT . '/' . ($leadId % 100) . '/' . $leadId;
    }

    private function leadBytes(int $leadId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(`size_bytes`),0) FROM `lead_documents`
              WHERE `lead_id`=? AND `deleted_at` IS NULL'
        );
        $stmt->execute([$leadId]);
        return (int) $stmt->fetchColumn();
    }

    /** Display-safe original filename: no control characters, no header injection. */
    private function shortName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        return mb_substr(basename($name), 0, 255);
    }

    // ── Compression ───────────────────────────────────────────────────────────

    /**
     * Shrink an already-validated file in place.
     *
     * Returns ['path','size','mime','ext'] when the compressed version is worth
     * keeping, or null to leave the original untouched. Never throws: a failed
     * compression must not fail an upload.
     *
     * Only ever called AFTER the extension and MIME checks, so neither Imagick
     * nor Ghostscript is handed an unvalidated file. Office files and zips are
     * skipped — they are already zip-compressed internally.
     */
    private function compress(string $path, string $ext, string $mime): ?array
    {
        try {
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
                return $this->compressImage($path, $ext);
            }
            if ($ext === 'pdf') {
                return $this->compressPdf($path);
            }
        } catch (\Throwable $e) {
            // Fall through: keep the original.
        }
        return null;
    }

    /** Keep the result only when it is a clear win. */
    private function worthKeeping(string $original, string $candidate): bool
    {
        if (!is_file($candidate)) return false;
        $a = (int) filesize($original);
        $b = (int) filesize($candidate);
        return $b > 0 && $b <= $a * 0.8;   // at least 20% smaller
    }

    private function compressImage(string $path, string $ext): ?array
    {
        if (!class_exists('Imagick')) return null;

        // HEIC is converted regardless of size: most browsers cannot display it,
        // so a stored HEIC is effectively an unopenable file.
        $toJpeg = ($ext === 'heic');

        $im = new Imagick();
        $im->readImage($path);
        $im->autoOrient();                 // phone photos are almost always rotated by EXIF
        $im->stripImage();                 // drops GPS coordinates and camera metadata

        if ($im->getImageWidth() > 2200 || $im->getImageHeight() > 2200) {
            // Ample to read a 12-digit Aadhar number.
            $im->resizeImage(2200, 2200, Imagick::FILTER_LANCZOS, 1, true);
        }

        $outExt = $toJpeg ? 'jpg' : $ext;
        if ($toJpeg) {
            $im->setImageFormat('jpeg');
        }
        if (in_array($outExt, ['jpg', 'jpeg'], true)) {
            $im->setImageCompressionQuality(82);
        }

        $tmp = $path . '.opt.' . $outExt;
        $im->writeImage($tmp);
        $im->clear();
        $im->destroy();

        if (!$this->worthKeeping($path, $tmp) && !$toJpeg) {
            @unlink($tmp);
            return null;
        }
        if ($toJpeg && !is_file($tmp)) {
            return null;
        }

        $final = dirname($path) . '/' . pathinfo($path, PATHINFO_FILENAME) . '.' . $outExt;
        if (!@rename($tmp, $final)) { @unlink($tmp); return null; }
        @chmod($final, 0600);
        if ($final !== $path) @unlink($path);

        return [
            'path' => $final,
            'size' => (int) filesize($final),
            'mime' => $outExt === 'png' ? 'image/png' : ($outExt === 'webp' ? 'image/webp' : 'image/jpeg'),
            'ext'  => $outExt === 'jpeg' ? 'jpg' : $outExt,
        ];
    }

    /**
     * Ghostscript via proc_open with an ARGUMENT ARRAY — no shell is involved,
     * so there is nothing to escape and nothing to inject.
     *
     * -dSAFER is mandatory: Ghostscript parses untrusted input and has a history
     * of sandbox escapes. The wall-clock timeout matters because this server has
     * max_execution_time = 0, so a malformed PDF would otherwise hang forever.
     */
    private function compressPdf(string $path): ?array
    {
        if (!function_exists('proc_open') || !is_executable('/usr/bin/gs')) return null;

        $tmp = $path . '.opt.pdf';
        $cmd = [
            '/usr/bin/gs', '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/ebook', '-dNOPAUSE', '-dQUIET', '-dBATCH', '-dSAFER',
            '-sOutputFile=' . $tmp, $path,
        ];

        $proc = @proc_open($cmd, [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($proc)) return null;

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = time() + 20;
        while (true) {
            $st = proc_get_status($proc);
            if (!$st['running']) break;
            if (time() > $deadline) { proc_terminate($proc, 9); @unlink($tmp); }
            usleep(100000);
        }

        foreach ([1, 2] as $i) { if (is_resource($pipes[$i])) fclose($pipes[$i]); }
        proc_close($proc);

        if (!$this->worthKeeping($path, $tmp)) { @unlink($tmp); return null; }

        if (!@rename($tmp, $path)) { @unlink($tmp); return null; }
        @chmod($path, 0600);

        return [
            'path' => $path,
            'size' => (int) filesize($path),
            'mime' => 'application/pdf',
            'ext'  => 'pdf',
        ];
    }
}
