<?php
/**
 * Include at the very top of every protected admin page.
 * Signs the visitor in from the session, reloads their account from the database on every
 * request (so role changes, deletions and password resets take effect immediately), and
 * provides the role helpers used by the pages.
 *
 * Roles:
 *   super    — everything: all properties, all feedback, user management.
 *   property — one property: its feedback, and its page settings (wording, logo, links, alert email).
 */
require_once __DIR__ . '/../config/functions.php';
start_session();

if (empty($_SESSION['admin_logged_in'])) {
    redirect('login.php');
}

try {
    $pdo = db();
    if (($_SESSION['schema_version'] ?? 0) !== SCHEMA_VERSION) {
        migrate_schema($pdo);
        $_SESSION['schema_version'] = SCHEMA_VERSION;
    }

    $stmt = $pdo->prepare(
        'SELECT a.id, a.username, a.email, a.role, a.property_id, a.password_hash, p.property_name
           FROM admins a
           LEFT JOIN properties p ON p.id = a.property_id
          WHERE a.id = ?'
    );
    $stmt->execute([(int)($_SESSION['admin_id'] ?? 0)]);
    $currentUser = $stmt->fetch() ?: null;
} catch (PDOException $ex) {
    error_log('admin/auth.php: ' . $ex->getMessage());
    http_response_code(500);
    exit('The admin panel could not reach the database. Please try again shortly.');
}

// Account deleted, password changed elsewhere, or property user without a property → sign out
$stillValid = $currentUser
    && hash_equals((string)($_SESSION['pw_fp'] ?? ''), password_fingerprint($currentUser['password_hash']))
    && ($currentUser['role'] === 'super' || !empty($currentUser['property_id']));

if (!$stillValid) {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('login.php?signed_out=1');
}
unset($currentUser['password_hash']);
$_SESSION['admin_username'] = $currentUser['username'];

function current_user(): array
{
    global $currentUser;
    return $currentUser;
}

function is_super(): bool
{
    return current_user()['role'] === 'super';
}

/** Property the signed-in user is limited to, or null for super admins. */
function scoped_property_id(): ?int
{
    return is_super() ? null : (int)current_user()['property_id'];
}

/** Stops non-super users at super-admin-only pages. */
function require_super(): void
{
    if (!is_super()) {
        flash('You do not have access to that page.', 'error');
        redirect('index.php');
    }
}

/** One-time message shown on the next page load. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
}
