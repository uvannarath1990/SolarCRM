<?php
declare(strict_types=1);

// Harden session cookie before session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ─── Helpers ──────────────────────────────────────────────────────────────────

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('/login');
    }
}

function requireRole(string ...$roles): void
{
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        http_response_code(403);
        die('Access denied.');
    }
}

function currentUser(): array
{
    return $_SESSION['user'] ?? [];
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function hasRole(string ...$roles): bool
{
    return in_array($_SESSION['role'] ?? '', $roles, true);
}

function loginUser(array $user): void
{
    session_regenerate_id(true);

    // Resolve display label — if user has a custom role assigned, use that label
    $roleLabel = null;
    if (!empty($user['role_custom_id'])) {
        try {
            $pdo  = getPdo();
            $stmt = $pdo->prepare('SELECT `label` FROM `roles` WHERE `id`=? AND `is_active`=1 LIMIT 1');
            $stmt->execute([(int)$user['role_custom_id']]);
            $row  = $stmt->fetch();
            if ($row) $roleLabel = $row['label'];
        } catch (\Throwable $e) { /* ignore — custom roles table may not exist yet */ }
    }

    $_SESSION['user_id']     = $user['id'];
    $_SESSION['role']        = $user['role'];                          // base permission level
    $_SESSION['role_label']  = $roleLabel ?? ucwords(str_replace('_', ' ', $user['role'])); // display name
    $_SESSION['role_custom_id'] = $user['role_custom_id'] ?? null;
    $_SESSION['user']        = [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
