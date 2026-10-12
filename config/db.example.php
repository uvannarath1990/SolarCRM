<?php
declare(strict_types=1);

// ─── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ─── Environment ──────────────────────────────────────────────────────────────
define('APP_DEBUG', false);

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
}

// ─── Database credentials ─────────────────────────────────────────────────────
// Get these from Hostinger hPanel → Databases → MySQL Databases
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// ─── App constants ────────────────────────────────────────────────────────────
define('APP_NAME', 'KRE');
define('BASE_URL', '/employee2');  // matches the folder created in public_html

// ─── Document storage ─────────────────────────────────────────────────────────
// Deliberately OUTSIDE public_html: uploaded KYC and bank documents must never
// be reachable by URL, only through the authenticated doc-portal/file route.
// Defined here so no request input can ever influence the storage root.
// config → employee2 → public_html → kre.org.in → domains → home
define('UPLOAD_ROOT',     dirname(__DIR__, 5) . '/kre_uploads/lead_docs');
define('MAX_DOC_BYTES',   25 * 1024 * 1024);   // per file, well under the 1536M ini
define('MAX_LEAD_BYTES',  250 * 1024 * 1024);  // per lead
define('MIN_FREE_BYTES',  500 * 1024 * 1024);  // refuse uploads below this free space

// ─── PDO singleton ────────────────────────────────────────────────────────────
function getPdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+05:30'");
    }
    return $pdo;
}
