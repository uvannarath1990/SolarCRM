<?php
declare(strict_types=1);

// Defaults for admin-configurable role permissions (pipe-delimited tokens)
const DEFAULT_DOC_TAB_ROLES     = 'admin|manager|documentation_head';
const DEFAULT_LEAD_CREATE_ROLES = 'admin|manager|tele_caller|sales_rep|dealer|partner|documentation_head';

// Who may set each assignment field on the lead create form
const DEFAULT_ASSIGN_REP_ROLES     = 'admin|manager|tele_caller';
const DEFAULT_ASSIGN_TELE_ROLES    = 'admin|manager';
const DEFAULT_ASSIGN_PARTNER_ROLES = 'admin|manager';

// ─── Redirect ─────────────────────────────────────────────────────────────────
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

// ─── Flash messages ───────────────────────────────────────────────────────────
function flash(string $type, string $message, bool $isHtml = false): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message, 'is_html' => $isHtml];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ─── AJAX detection ───────────────────────────────────────────────────────────
function isAjax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
}

// ─── JSON responses ───────────────────────────────────────────────────────────
function jsonResponse(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    jsonResponse(['error' => $message], $code);
}

// ─── Input sanitisation ───────────────────────────────────────────────────────
function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

// Parse JSON request body once and cache it (apiFetch sends Content-Type: application/json)
function _jsonBody(): array
{
    static $parsed = null;
    if ($parsed === null) {
        $ct     = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        $parsed = str_contains($ct, 'application/json')
            ? ((array) (json_decode(file_get_contents('php://input'), true) ?? []))
            : [];
    }
    return $parsed;
}

function post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? _jsonBody()[$key] ?? $default;
}

function get(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function postInt(string $key, int $default = 0): int
{
    $val = $_POST[$key] ?? _jsonBody()[$key] ?? null;
    return $val !== null ? (int) $val : $default;
}

function getInt(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $default);
}

// ─── kW estimate from bill amount ─────────────────────────────────────────────
function kwFromBill(?float $bill): ?float
{
    if ($bill === null || $bill <= 0) {
        return null;
    }
    $divisor = (float) getSetting('bill_to_kw_divisor', '1200');
    if ($divisor <= 0) {
        return null;
    }
    return round($bill / $divisor, 2);
}

// ─── Settings helper ──────────────────────────────────────────────────────────
function getSetting(string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $pdo  = getPdo();
        $stmt = $pdo->prepare('SELECT `value` FROM `settings` WHERE `key_name` = ? LIMIT 1');
        $stmt->execute([$key]);
        $row        = $stmt->fetch();
        $cache[$key] = $row ? ($row['value'] ?? $default) : $default;
    }
    return $cache[$key];
}

// ─── Stage helpers ────────────────────────────────────────────────────────────
function allStages(): array
{
    return [
        'New', 'Contacted', 'Visit Scheduled',
        'Site Visit in Progress', 'Site Visit Completed',
        'Quoted', 'Won', 'Lost', 'On Hold',
    ];
}

function stageBadgeClass(string $stage): string
{
    return match ($stage) {
        'New'                    => 'badge-stage-new',
        'Contacted'              => 'badge-stage-contacted',
        'Visit Scheduled'        => 'badge-stage-visit-sched',
        'Site Visit in Progress' => 'badge-stage-visit-prog',
        'Site Visit Completed'   => 'badge-stage-visit-done',
        'Quoted'                 => 'badge-stage-quoted',
        'Won'                    => 'badge-stage-won',
        'Lost'                   => 'badge-stage-lost',
        'On Hold'                => 'badge-stage-hold',
        default                  => 'badge-secondary',
    };
}

/**
 * Returns which stages a user may transition to from the current stage.
 */
function allowedNextStages(string $currentStage, string $role): array
{
    $map = [
        'New' => [
            'admin' => ['Contacted', 'Lost'],
            'tele_caller' => ['Contacted'],
            'sales_rep'   => ['Contacted'],
            'installer'   => [],
        ],
        'Contacted' => [
            'admin' => ['Visit Scheduled', 'Lost', 'On Hold'],
            'tele_caller' => ['Visit Scheduled', 'Lost', 'On Hold'],
            'sales_rep'   => ['Visit Scheduled', 'Lost', 'On Hold'],
            'installer'   => [],
        ],
        'Visit Scheduled' => [
            'admin' => ['Site Visit in Progress', 'Contacted', 'Lost', 'On Hold'],
            'tele_caller' => ['Contacted'],
            'sales_rep'   => ['Site Visit in Progress', 'Lost', 'On Hold'],
            'installer'   => [],
        ],
        'Site Visit in Progress' => [
            'admin' => ['Site Visit Completed'],
            'tele_caller' => [],
            'sales_rep'   => ['Site Visit Completed'],
            'installer'   => [],
        ],
        'Site Visit Completed' => [
            'admin' => ['Quoted', 'On Hold', 'Lost'],
            'tele_caller' => [],
            'sales_rep'   => ['Quoted', 'On Hold', 'Lost'],
            'installer'   => [],
        ],
        'Quoted' => [
            'admin' => ['Won', 'Lost', 'On Hold'],
            'tele_caller' => [],
            'sales_rep'   => ['Won', 'Lost', 'On Hold'],
            'installer'   => [],
        ],
        'Won' => [
            'admin' => ['Lost'],
            'tele_caller' => [],
            'sales_rep'   => [],
            'installer'   => [],
        ],
        'Lost' => [
            'admin' => ['Contacted'],
            'tele_caller' => [],
            'sales_rep'   => [],
            'installer'   => [],
        ],
        'On Hold' => [
            'admin' => ['Contacted', 'Visit Scheduled', 'Lost'],
            'tele_caller' => [],
            'sales_rep'   => ['Contacted', 'Visit Scheduled', 'Lost'],
            'installer'   => [],
        ],
    ];
    // manager and operation_manager have same stage permissions as admin
    if ($role === 'manager' || $role === 'operation_manager') {
        $role = 'admin';
    }
    return $map[$currentStage][$role] ?? [];
}

// ─── Format helpers ───────────────────────────────────────────────────────────
function formatCurrency(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }
    $symbol = getSetting('currency_symbol', '₹');
    return $symbol . number_format($amount, 0, '.', ',');
}

function formatDate(?string $date): string
{
    if (!$date) {
        return '—';
    }
    return date('d M Y', strtotime($date));
}

function formatDateTime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return date('d M Y, h:i A', strtotime($dt));
}

function formatDuration(?int $secs): string
{
    if ($secs === null) {
        return '—';
    }
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    if ($h > 0) {
        return sprintf('%dh %02dm', $h, $m);
    }
    return sprintf('%dm %02ds', $m, $s);
}

function googleMapsUrl(?float $lat, ?float $lng): string
{
    if ($lat === null || $lng === null) {
        return '#';
    }
    return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
}

// ─── Phone visibility ─────────────────────────────────────────────────────────

/**
 * Mask a phone number for roles that don't have reveal access.
 * Shows the first 4 digits and masks the rest with bullets.
 * e.g.  9876543210  →  9876••••••
 */
function maskPhone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) <= 4) return str_repeat('•', strlen($digits));
    return substr($digits, 0, 4) . str_repeat('•', strlen($digits) - 4);
}

/**
 * Whether the current user can see the real phone number.
 * Accepts rows keyed either `phone_revealed` (lead rows) or
 * `lead_phone_revealed` (task rows that alias the joined lead columns).
 */
function canSeePhone(array $lead): bool
{
    if (($_SESSION['role'] ?? '') !== 'sales_rep') return true;
    return !empty($lead['phone_revealed']) || !empty($lead['lead_phone_revealed']);
}

/**
 * Returns the display phone for the current user.
 * Only sales_rep gets a masked number when phone_revealed = 0.
 */
function displayPhone(array $lead): string
{
    $phone = $lead['phone'] ?? $lead['lead_phone'] ?? '';
    return canSeePhone($lead) ? $phone : maskPhone($phone);
}

/**
 * Digits-only phone for tel:/wa.me links — empty string when the current user
 * is not allowed to see the number, which collapses the calling buttons.
 */
function dialablePhone(array $lead): string
{
    if (!canSeePhone($lead)) return '';
    return preg_replace('/[^0-9+]/', '', $lead['phone'] ?? $lead['lead_phone'] ?? '');
}

// ─── Documentation-team lead scoping ─────────────────────────────────────────
//
// A documentation user may only touch leads that are their own work: either the
// lead names them in `documentation_user_id`, or they hold a documentation
// workflow task on it. Everything else in the pipeline is none of their business.

/**
 * The Documentation Head oversees the whole documentation pipeline, so the
 * per-user scoping below does not apply to them. Identified by the custom role
 * record rather than a label string, which users can rename.
 */
function isDocumentationHead(): bool
{
    if (($_SESSION['role'] ?? '') !== 'documentation') return false;

    static $cache = null;
    if ($cache !== null) return $cache;

    // Resolve against the user row rather than the session: role_custom_id is
    // only written at login, so a session opened before that would miss it and
    // silently demote the Head to an ordinary documentation user.
    $stmt = getPdo()->prepare(
        "SELECT 1
         FROM `users` u
         JOIN `roles` r ON r.`id` = u.`role_custom_id`
         WHERE u.`id` = ?
           AND r.`base_role` = 'documentation'
           AND r.`is_active` = 1
           AND LOWER(r.`label`) LIKE '%head%'
         LIMIT 1"
    );
    $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
    return $cache = (bool) $stmt->fetchColumn();
}

/**
 * Which roles may open the Pending Docs / Docs Received tabs. Admin-configurable
 * from Settings; the token 'documentation_head' is a role *label*, not a base
 * role, so it is resolved separately from $_SESSION['role'].
 */
function docTabRoleTokens(): array
{
    return roleSettingTokens('doc_tab_roles', DEFAULT_DOC_TAB_ROLES);
}

/**
 * Tokens describing the current user, for matching against role settings.
 * Documentation splits into head/other because "Documentation Head" is a role
 * label rather than a base role, and the two are granted different things.
 */
function currentRoleTokens(): array
{
    $role   = $_SESSION['role'] ?? '';
    $tokens = [$role];
    if ($role === 'documentation') {
        $tokens[] = isDocumentationHead() ? 'documentation_head' : 'documentation_user';
    }
    return $tokens;
}

/**
 * Every role token an admin can grant, with display labels. Single source for
 * the Settings checkboxes and for validating what gets saved.
 */
function configurableRoleTokens(): array
{
    return [
        'admin'              => 'Admin',
        'manager'            => 'Manager',
        'operation_manager'  => 'Operation Manager',
        'accounts'           => 'Accounts',
        'tele_caller'        => 'Tele Caller',
        'sales_rep'          => 'Sales Rep',
        'installer'          => 'Installer',
        'dealer'             => 'Dealer',
        'partner'            => 'Partner',
        'documentation_head' => 'Documentation Head',
        'documentation_user' => 'Documentation (other users)',
    ];
}

/** Read a pipe-delimited role setting into a list of tokens. */
function roleSettingTokens(string $key, string $default): array
{
    return array_values(array_filter(array_map('trim', explode('|', getSetting($key, $default)))));
}

/** Whether the current user may open the documentation checklist tabs. */
function canSeeDocTabs(): bool
{
    return (bool) array_intersect(currentRoleTokens(), docTabRoleTokens());
}

/**
 * Whether the user sees every row inside those tabs, or only their own leads.
 * Documentation users other than the Head are scoped to their own work.
 */
function canSeeAllDocumentation(): bool
{
    if (($_SESSION['role'] ?? '') === 'documentation') {
        return isDocumentationHead();
    }
    return hasRole('admin', 'manager', 'operation_manager', 'accounts');
}

/**
 * Leads the Documentation Head may reach: the documentation population only —
 * the same rows the two tabs list — not the whole lead base.
 */
function documentationHeadScopeSql(): string
{
    return "(l.`site_visit_completed_date` IS NOT NULL AND l.`stage` <> 'Lost')";
}

/**
 * SQL predicate for the above, for use in a WHERE clause where the leads table
 * is aliased `l`. Binds two params, both the documentation user's id.
 */
function documentationScopeSql(): string
{
    return "(l.`documentation_user_id` = ?
             OR EXISTS (SELECT 1 FROM `workflow_tasks` dwt
                        WHERE dwt.`lead_id`       = l.`id`
                          AND dwt.`assigned_role` = 'documentation'
                          AND dwt.`assigned_to`   = ?))";
}

/**
 * Single-lead form of documentationScopeSql().
 */
function documentationCanAccessLead(PDO $pdo, int $leadId, int $userId): bool
{
    $stmt = $pdo->prepare(
        "SELECT 1 FROM `leads` l WHERE l.`id` = ? AND " . documentationScopeSql() . " LIMIT 1"
    );
    $stmt->execute([$leadId, $userId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Whether the current user may work on a given lead. Single source of truth for
 * lead-level access — LeadDetailController::checkAccess() wraps this, and JSON
 * endpoints call it directly so the two can never drift apart.
 */
function canAccessLead(PDO $pdo, array $lead): bool
{
    $role   = $_SESSION['role'] ?? '';
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if (in_array($role, ['admin', 'manager', 'operation_manager', 'accounts'], true)) {
        return true;
    }

    // Whoever created a lead can always open it, whatever their role — otherwise
    // creating one and being bounced off its own detail page reads as a failure.
    if ((int) ($lead['created_by'] ?? 0) === $userId) {
        return true;
    }

    return match ($role) {
        'documentation' => isDocumentationHead()
            ? (!empty($lead['site_visit_completed_date']) && $lead['stage'] !== 'Lost')
            : documentationCanAccessLead($pdo, (int) $lead['id'], $userId),
        'tele_caller'   => (int) $lead['created_by'] === $userId
                        || (int) ($lead['tele_caller_id'] ?? 0) === $userId,
        'sales_rep'     => (int) $lead['assigned_rep_id'] === $userId,
        'installer'     => $lead['stage'] === 'Won',
        'partner'       => (int) ($lead['partner_id'] ?? 0) === $userId,
        'dealer'        => (int) $lead['created_by'] === $userId,
        default         => false,
    };
}

/**
 * Who may create a lead. The Documentation Head is included even though the
 * documentation base role is not — resolved via isDocumentationHead() so the
 * other documentation users stay excluded.
 */
/**
 * Does the current user hold the permission stored under $settingKey?
 * One place for every admin-configurable role permission.
 */
function roleCan(string $settingKey, string $default): bool
{
    return (bool) array_intersect(currentRoleTokens(), roleSettingTokens($settingKey, $default));
}

function canCreateLead(): bool
{
    return roleCan('lead_create_roles', DEFAULT_LEAD_CREATE_ROLES);
}

/** May the user pick a Sales Rep when creating a lead? */
function canAssignRep(): bool
{
    return roleCan('lead_assign_rep_roles', DEFAULT_ASSIGN_REP_ROLES);
}

/** May the user pick a Tele Caller when creating a lead? */
function canAssignTeleCaller(): bool
{
    return roleCan('lead_assign_tele_roles', DEFAULT_ASSIGN_TELE_ROLES);
}

/** May the user attribute the lead to a Partner? */
function canAssignPartner(): bool
{
    return roleCan('lead_assign_partner_roles', DEFAULT_ASSIGN_PARTNER_ROLES);
}

/**
 * Versioned URL for a local asset, e.g. assetUrl('assets/js/app.js').
 *
 * Appends the file's mtime so browsers pick up a deployed change immediately
 * instead of serving a cached copy — without this, a JS edit can sit invisible
 * behind the browser cache indefinitely.
 */
function assetUrl(string $path): string
{
    $path = ltrim($path, '/');
    $full = __DIR__ . '/../' . $path;
    $ver  = is_file($full) ? filemtime($full) : null;

    return BASE_URL . '/' . $path . ($ver ? '?v=' . $ver : '');
}

// ─── Payment / Financial helpers ─────────────────────────────────────────────
function paymentStatuses(): array
{
    return [
        'Payment Completed',
        'Advance Received',
        'Second Installment Pending',
        'Feasibility Received – Bank Doc in Progress',
        'Bank Loan Approved',
        'Installation in Progress',
        'Pending',
    ];
}

function feasibilityStatuses(): array
{
    return ['Not Started', 'Submitted', 'Received', 'Approved', 'Rejected'];
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = (int) ceil($total / $perPage);
    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => ($currentPage - 1) * $perPage,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

// ─── Lead ownership check (used by Activities, Reminders, etc.) ───────────────
// Fetches the lead and verifies the current user may access it.
// Returns the lead row on success; calls jsonError(403) and exits on failure.
function assertLeadAccess(int $leadId): array
{
    $pdo  = getPdo();
    $stmt = $pdo->prepare(
        'SELECT `id`,`stage`,`assigned_rep_id`,`created_by`,`tele_caller_id`,`partner_id`
         FROM `leads` WHERE `id`=? AND `deleted_at` IS NULL LIMIT 1'
    );
    $stmt->execute([$leadId]);
    $lead = $stmt->fetch();

    if (!$lead) {
        jsonError('Lead not found.', 404);
    }

    $role   = $_SESSION['role'] ?? '';
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if (in_array($role, ['admin', 'manager', 'operation_manager', 'accounts', 'documentation'], true)) {
        return $lead;
    }
    if ($role === 'tele_caller'
        && (int) $lead['created_by']                  !== $userId
        && (int) ($lead['tele_caller_id'] ?? 0)       !== $userId) {
        jsonError('Access denied.', 403);
    }
    if ($role === 'sales_rep' && (int) $lead['assigned_rep_id'] !== $userId) {
        jsonError('Access denied.', 403);
    }
    if ($role === 'partner' && (int) ($lead['partner_id'] ?? 0) !== $userId) {
        jsonError('Access denied.', 403);
    }

    return $lead;
}
