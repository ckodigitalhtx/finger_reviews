<?php
/**
 * Database connection + application settings.
 * Edit the values below when moving to another server.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'review_system');
define('DB_USER', 'gated_reviews');
define('DB_PASS', '95Gmcslt!');
define('DB_CHARSET', 'utf8mb4');

// ---- Email ----
// "From" address used on alert emails. Use an address on a domain your server may send for.
define('MAIL_FROM', 'no-reply@localhost');
define('MAIL_FROM_NAME', 'Resident Feedback');

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
