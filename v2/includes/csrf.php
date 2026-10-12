<?php
declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfVerify(): void
{
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if (isAjax()) {
            jsonError('Invalid CSRF token.', 403);
        }
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}
