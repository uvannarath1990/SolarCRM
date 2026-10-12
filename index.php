<?php
declare(strict_types=1);

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/workflow.php';
require_once __DIR__ . '/includes/push.php';

// ─── Route table ──────────────────────────────────────────────────────────────
// Format: 'route' => ['ControllerFile', 'ControllerClass', 'method']
$routes = [
    // Auth
    ''                       => ['controllers/AuthController.php',         'AuthController',         'loginPage'],
    'login'                  => ['controllers/AuthController.php',         'AuthController',         'loginPage'],
    'logout'                 => ['controllers/AuthController.php',         'AuthController',         'logout'],

    // Today landing page
    'today'                  => ['controllers/TodayController.php',        'TodayController',        'index'],

    // Dashboard
    'dashboard'              => ['controllers/DashboardController.php',    'DashboardController',    'index'],
    'api/dashboard-stats'    => ['controllers/DashboardController.php',    'DashboardController',    'statsJson'],

    // Leads
    'leads'                  => ['controllers/LeadsController.php',        'LeadsController',        'index'],
    'leads/create'           => ['controllers/LeadsController.php',        'LeadsController',        'create'],
    'leads/save'             => ['controllers/LeadsController.php',        'LeadsController',        'save'],
    'leads/inline-edit'      => ['controllers/LeadsController.php',        'LeadsController',        'inlineEdit'],
    'leads/delete'           => ['controllers/LeadsController.php',        'LeadsController',        'delete'],
    'leads/mark-outcome'     => ['controllers/LeadsController.php',        'LeadsController',        'markOutcome'],
    'leads/bulk-update'      => ['controllers/LeadsController.php',        'LeadsController',        'bulkUpdate'],
    'leads/kanban'           => ['controllers/LeadsController.php',        'LeadsController',        'kanban'],
    'leads/import'           => ['controllers/LeadsImportController.php',  'LeadsImportController',  'index'],
    'leads/import/template'  => ['controllers/LeadsImportController.php',  'LeadsImportController',  'template'],
    'leads/import/upload'    => ['controllers/LeadsImportController.php',  'LeadsImportController',  'upload'],
    'leads/detail'           => ['controllers/LeadDetailController.php',   'LeadDetailController',   'index'],
    'leads/detail/save'      => ['controllers/LeadDetailController.php',   'LeadDetailController',   'save'],

    // Site visits
    'site-visits/checkin'    => ['controllers/SiteVisitController.php',    'SiteVisitController',    'checkin'],
    'site-visits/checkout'   => ['controllers/SiteVisitController.php',    'SiteVisitController',    'checkout'],
    'site-visits/view'       => ['controllers/SiteVisitController.php',    'SiteVisitController',    'view'],

    // Installations
    'installations'          => ['controllers/InstallationController.php', 'InstallationController', 'index'],
    'installations/update'   => ['controllers/InstallationController.php', 'InstallationController', 'update'],
    'installations/detail'   => ['controllers/InstallationController.php', 'InstallationController', 'detail'],

    // Operations
    'operations'             => ['controllers/OperationsController.php',   'OperationsController',   'index'],
    'operations/save'        => ['controllers/OperationsController.php',   'OperationsController',   'save'],
    'operations/done'        => ['controllers/OperationsController.php',   'OperationsController',   'markDone'],
    'operations/delete'      => ['controllers/OperationsController.php',   'OperationsController',   'delete'],
    'operations/reschedule'  => ['controllers/OperationsController.php',   'OperationsController',   'reschedule'],

    // Teams (admin only)
    'teams'                  => ['controllers/TeamsController.php',        'TeamsController',        'index'],
    'teams/create'           => ['controllers/TeamsController.php',        'TeamsController',        'create'],
    'teams/save'             => ['controllers/TeamsController.php',        'TeamsController',        'save'],
    'teams/delete'           => ['controllers/TeamsController.php',        'TeamsController',        'delete'],

    // Payments
    'payments'                    => ['controllers/PaymentsController.php',     'PaymentsController',     'index'],
    'payments/save-financials'    => ['controllers/PaymentsController.php',     'PaymentsController',     'saveFinancials'],
    'payments/save-installment'   => ['controllers/PaymentsController.php',     'PaymentsController',     'saveInstallment'],
    'payments/delete-installment' => ['controllers/PaymentsController.php',     'PaymentsController',     'deleteInstallment'],

    // Activities
    'activities/save'        => ['controllers/ActivitiesController.php',   'ActivitiesController',   'save'],

    // Reminders
    'reminders/save'         => ['controllers/RemindersController.php',    'RemindersController',    'save'],
    'reminders/done'         => ['controllers/RemindersController.php',    'RemindersController',    'markDone'],
    'reminders/delete'       => ['controllers/RemindersController.php',    'RemindersController',    'delete'],

    // Users (admin only)
    'users'                  => ['controllers/UsersController.php',        'UsersController',        'index'],
    'users/create'           => ['controllers/UsersController.php',        'UsersController',        'create'],
    'users/save'             => ['controllers/UsersController.php',        'UsersController',        'save'],
    'users/toggle-active'    => ['controllers/UsersController.php',        'UsersController',        'toggleActive'],
    'users/delete'           => ['controllers/UsersController.php',        'UsersController',        'delete'],

    // Tasks & notifications
    'tasks'                  => ['controllers/TasksController.php',        'TasksController',        'index'],
    'api/notifications'      => ['controllers/TasksController.php',        'TasksController',        'notificationsJson'],

    // Workflow pipeline
    'workflow/detail'        => ['controllers/WorkflowController.php',     'WorkflowController',     'detail'],
    'workflow/create-task'   => ['controllers/WorkflowController.php',     'WorkflowController',     'createTask'],
    'workflow/create-followup' => ['controllers/WorkflowController.php',   'WorkflowController',     'createFollowUp'],
    'workflow/complete'      => ['controllers/WorkflowController.php',     'WorkflowController',     'completeTask'],
    'workflow/skip'          => ['controllers/WorkflowController.php',     'WorkflowController',     'skipTask'],
    'workflow/assign'        => ['controllers/WorkflowController.php',     'WorkflowController',     'assignTask'],
    'workflow/cancel'        => ['controllers/WorkflowController.php',     'WorkflowController',     'cancelTask'],
    'workflow/reschedule-visit' => ['controllers/WorkflowController.php', 'WorkflowController',     'rescheduleVisit'],
    'workflow/set-due-date'     => ['controllers/WorkflowController.php', 'WorkflowController',     'setDueDate'],
    'workflow/reopen'           => ['controllers/WorkflowController.php', 'WorkflowController',     'reopenTask'],
    'tasks/review'              => ['controllers/WorkflowController.php', 'WorkflowController',     'review'],
    'workflow/bulk-update'      => ['controllers/WorkflowController.php', 'WorkflowController',     'bulkUpdate'],

    // Reports (admin / manager)
    'reports/lead-review'        => ['controllers/ReportsController.php', 'ReportsController', 'leadReview'],
    'reports/lead-review/export' => ['controllers/ReportsController.php', 'ReportsController', 'exportCsv'],
    'reports/tele-review'        => ['controllers/ReportsController.php', 'ReportsController', 'teleCallerReview'],
    'reports/tele-review/export' => ['controllers/ReportsController.php', 'ReportsController', 'teleCallerExport'],

    // Push notifications
    'push/subscribe'         => ['controllers/PushController.php',         'PushController',         'subscribe'],
    'push/unsubscribe'       => ['controllers/PushController.php',         'PushController',         'unsubscribe'],
    'push/vapid-public-key'  => ['controllers/PushController.php',         'PushController',         'vapidPublicKey'],
    'push/setup'             => ['controllers/PushController.php',         'PushController',         'setup'],
    'push/check-reminders'   => ['controllers/PushController.php',         'PushController',         'checkReminders'],

    // Documentation
    'documentation'          => ['controllers/DocumentationController.php', 'DocumentationController', 'index'],
    'documentation/pending'  => ['controllers/DocumentationController.php', 'DocumentationController', 'pending'],
    'documentation/received' => ['controllers/DocumentationController.php', 'DocumentationController', 'received'],
    'documentation/save-docs'=> ['controllers/DocumentationController.php', 'DocumentationController', 'saveDocs'],

    // Partner portal
    'partner/dashboard'      => ['controllers/PartnerController.php',      'PartnerController',      'dashboard'],
    'api/partner-stats'      => ['controllers/PartnerController.php',      'PartnerController',      'statsJson'],

    // Settings (admin only)
    'settings'               => ['controllers/SettingsController.php',     'SettingsController',     'index'],
    'settings/save'          => ['controllers/SettingsController.php',     'SettingsController',     'save'],
    'settings/sources/save'      => ['controllers/SettingsController.php', 'SettingsController', 'saveSources'],
    'settings/source-subs/save'  => ['controllers/SettingsController.php', 'SettingsController', 'saveSourceSubs'],
    'settings/roles/save'        => ['controllers/SettingsController.php', 'SettingsController', 'saveRoles'],
];

// ─── Dispatch ─────────────────────────────────────────────────────────────────
$route = trim($_GET['route'] ?? '', '/');

if (!isset($routes[$route])) {
    http_response_code(404);
    // Show a clean 404 page if layout is available, else plain text
    if (file_exists(__DIR__ . '/views/layout/header.php')) {
        $pageTitle = '404 — Page Not Found';
        require __DIR__ . '/views/layout/header.php';
        echo '<div class="container mt-5"><div class="alert alert-warning"><h4>Page not found</h4><a href="' . BASE_URL . '/today">Go to Today</a></div></div>';
        require __DIR__ . '/views/layout/footer.php';
    } else {
        echo '404 Not Found';
    }
    exit;
}

[$file, $class, $method] = $routes[$route];

require_once __DIR__ . '/' . $file;
(new $class())->$method();
