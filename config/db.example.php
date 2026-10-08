<?php
/**
 * Database connection + application settings.
 * Edit the values below when moving to another server.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'review_system');
define('DB_USER', 'gated_reviews');
define('DB_PASS', 'your-database-password');
define('DB_CHARSET', 'utf8mb4');

// ---- Email ----
// "From" address used on alert emails. Use an address on a domain your server may send for.
define('MAIL_FROM', 'no-reply@localhost');
define('MAIL_FROM_NAME', 'Resident Feedback');

// ---- Site address ----
// Full address of this install, no trailing slash. Used in emailed password links.
// Set this on live servers, e.g. 'https://reviews.example.com'. Leave '' to detect automatically.
define('APP_URL', '');

/**
 * Returns a shared PDO connection.
 * Pass false to connect to the server without selecting a database (used by install.php).
 */
function db(bool $selectDatabase = true): PDO
{
    static $connections = [];
    $key = $selectDatabase ? 'db' : 'server';

    if (!isset($connections[$key])) {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
        if ($selectDatabase) {
            $dsn .= ';dbname=' . DB_NAME;
        }
        $connections[$key] = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $connections[$key];
}
