<?php
/**
 * PostgreSQL database connection.
 */

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'event_management';
$dbUser = getenv('DB_USER') ?: 'postgres';
$dbPassword = getenv('DB_PASSWORD');
$dbSslMode = getenv('DB_SSLMODE') ?: 'prefer';

$forwardedProtocolForHeaders = getenv('APP_TRUST_PROXY') === '1'
    ? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]))
    : '';
$requestIsHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || $forwardedProtocolForHeaders === 'https';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https://images.unsplash.com; font-src 'self' data: https://cdnjs.cloudflare.com; connect-src 'self'");
if ($requestIsHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}

define('DB_DATABASE', $dbName);

try {
    $pdo = new PDO(
        'pgsql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';sslmode=' . $dbSslMode,
        $dbUser,
        $dbPassword === false ? '' : $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('PostgreSQL connection error: ' . $e->getMessage());
    http_response_code(500);
    exit('Database connection failed. Check the application database configuration.');
}

/**
 * Get database connection instance.
 *
 * @return PDO
 */
function getDbConnection()
{
    global $pdo;
    return $pdo;
}

/**
 * Close the database connection.
 */
function closeDbConnection()
{
    global $pdo;
    $pdo = null;
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    $forwardedProtocol = getenv('APP_TRUST_PROXY') === '1'
        ? strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]))
        : '';
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || $forwardedProtocol === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_ROOT', dirname(__FILE__));
date_default_timezone_set('Africa/Accra');
