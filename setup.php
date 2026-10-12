<?php
/**
 * ONE-TIME SETUP — create (or re-enable) the first admin account.
 *
 * CLI only, and the password is never stored in this file: it comes from the
 * environment, so nothing secret reaches the repository or a web request.
 * An earlier version of this script hardcoded the password and printed it in
 * an HTML page, which meant anyone who found the URL owned the admin account.
 *
 *   KRE_ADMIN_PASSWORD='…' php setup.php [email]
 *
 * Delete the account's password from your shell history afterwards, and remove
 * this file from any deployed directory.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("CLI only.\n");
}

require_once __DIR__ . '/config/db.php';

$email    = $argv[1] ?? 'admin@example.com';
$password = getenv('KRE_ADMIN_PASSWORD') ?: '';

if (strlen($password) < 12) {
    die("Set KRE_ADMIN_PASSWORD to at least 12 characters.\n");
}

getPdo()->prepare(
    "INSERT INTO users (name, email, password_hash, role, is_active)
     VALUES ('Admin', ?, ?, 'admin', 1)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_active = 1"
)->execute([$email, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);

echo "Admin account ready: {$email}\n";
