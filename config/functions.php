<?php
/**
 * Shared helpers used by the public pages and the admin panel.
 */
require_once __DIR__ . '/db.php';

/** Escape a value for HTML output. */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Start the session with safe cookie settings. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Get (and lazily create) the CSRF token for this session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden form field carrying the CSRF token. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the posted CSRF token matches the session token. */
function csrf_valid(): bool
{
    $posted = $_POST['csrf_token'] ?? '';
    return is_string($posted) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $posted);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Trimmed string from POST. */
function post_str(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
}

/** Only http(s) URLs are accepted for review links. */
function is_valid_url(string $url): bool
{
    return (bool)filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url);
}

/** Splits "a@x.com, b@y.com" into a clean list of addresses. */
function parse_email_list(string $list): array
{
    $parts = preg_split('/[,;\s]+/', $list, -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique($parts ?: []));
}

/** Review platforms: column name => button label. */
function review_platforms(): array
{
    return [
        'link_google'           => 'Google',
        'link_yelp'             => 'Yelp',
        'link_apartments_com'   => 'Apartments.com',
        'link_apartmentratings' => 'ApartmentRatings',
        'link_other'            => 'Other Review Site',
    ];
}


/** "★★★☆☆" for a rating. */
function star_string(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/* --------------------------------------------------------------------------
   Schema upgrades — applied automatically (install.php, login and admin pages)
   -------------------------------------------------------------------------- */

/** Bump when migrate_schema() gains new steps, so signed-in sessions re-run it. */
define('SCHEMA_VERSION', 2);

/** Adds tables/columns introduced after the first release. Safe to call repeatedly. */
function migrate_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $check = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $upgrades = [
        // v1: property logos
        ['properties', 'logo_path',
            'ALTER TABLE properties ADD COLUMN logo_path VARCHAR(255) NULL AFTER custom_wording'],
        // v2: user roles + email for password resets
        ['admins', 'email',
            'ALTER TABLE admins ADD COLUMN email VARCHAR(150) NULL AFTER username,
                                ADD UNIQUE KEY uq_admins_email (email)'],
        ['admins', 'role',
            "ALTER TABLE admins ADD COLUMN role ENUM('super','property') NOT NULL DEFAULT 'super' AFTER password_hash"],
        ['admins', 'property_id',
            'ALTER TABLE admins ADD COLUMN property_id INT NULL AFTER role,
                                ADD UNIQUE KEY uq_admins_property (property_id),
                                ADD CONSTRAINT fk_admins_property FOREIGN KEY (property_id)
                                    REFERENCES properties(id) ON DELETE CASCADE'],
    ];
    foreach ($upgrades as [$table, $column, $sql]) {
        $check->execute([$table, $column]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_resets_admin (admin_id),
            FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $done = true;
}

/* --------------------------------------------------------------------------
   URLs and email
   -------------------------------------------------------------------------- */

/**
 * Absolute URL inside this app, e.g. app_url('admin/login.php').
 * Uses APP_URL from config/db.php when set (recommended on live servers so emailed
 * links can never be pointed at another domain); otherwise works it out from the request.
 */
function app_url(string $path = ''): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        $base = rtrim(APP_URL, '/');
    } else {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host  = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $dir   = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir   = preg_replace('#/admin$#', '', rtrim($dir, '/'));
        $base  = ($https ? 'https' : 'http') . '://' . $host . $dir;
    }
    return $base . '/' . ltrim($path, '/');
}

/** Absolute URL of the public review page for a property. */
function public_review_url(int $propertyId): string
{
    return app_url('review.php?property_id=' . $propertyId);
}

/** Plain-text email via PHP mail(). Returns false (and logs) on failure. */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool
{
    $clean = static fn(string $s): string => trim(preg_replace('/[\r\n]+/', ' ', $s));

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $clean($replyTo);
    }
    $sent = @mail(
        $clean($to),
        '=?UTF-8?B?' . base64_encode($clean($subject)) . '?=',
        str_replace(["\r\n", "\r", "\n"], "\r\n", $body),
        implode("\r\n", $headers)
    );
    if (!$sent) {
        error_log('send_mail: mail() failed for ' . $clean($to));
    }
    return $sent;
}

/* --------------------------------------------------------------------------
   Users, sessions and password resets
   -------------------------------------------------------------------------- */

define('RESET_MINUTES', 60);            // "forgot password" links
define('INVITE_MINUTES', 72 * 60);      // links sent by a super admin (new users, admin-triggered resets)

/** Changes whenever the password changes; stored in the session to sign out other devices after a reset. */
function password_fingerprint(string $hash): string
{
    return hash('sha256', $hash);
}

function role_label(string $role): string
{
    return $role === 'super' ? 'Super admin' : 'Property user';
}

/** Creates a single-use reset token for a user and returns the raw token (only its hash is stored). */
function create_password_reset(PDO $pdo, int $adminId, int $minutes): string
{
    $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ? OR expires_at < NOW()')->execute([$adminId]);

    $token = bin2hex(random_bytes(32));
    $pdo->prepare(
        'INSERT INTO password_resets (admin_id, token_hash, expires_at)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    )->execute([$adminId, hash('sha256', $token), $minutes]);

    return $token;
}

/** Looks up a still-valid reset token; returns the user row or null. */
function find_password_reset(PDO $pdo, string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT a.id, a.username, a.email
           FROM password_resets r
           JOIN admins a ON a.id = r.admin_id
          WHERE r.token_hash = ? AND r.expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    return $stmt->fetch() ?: null;
}

/**
 * Emails a user a link to set their password.
 * $invite = true for new accounts / resets started by a super admin (longer expiry, welcome wording).
 * Returns [bool $sent, string $link] — the link lets a super admin pass it on if email isn't working.
 */
function send_password_email(PDO $pdo, array $user, bool $invite): array
{
    $minutes = $invite ? INVITE_MINUTES : RESET_MINUTES;
    $token   = create_password_reset($pdo, (int)$user['id'], $minutes);
    $link    = app_url('admin/reset-password.php?token=' . $token);
    $expires = $minutes >= 120 ? ($minutes / 60) . ' hours' : $minutes . ' minutes';

    if ($invite) {
        $subject = 'Set your password for the Review System';
        $lines = [
            'Hello ' . $user['username'] . ',',
            '',
            'A password link has been created for your Review System account.',
            'Use the link below to choose your password:',
            '',
            $link,
            '',
            'This link works once and expires in ' . $expires . '.',
            'Your username is: ' . $user['username'],
            'Sign in at: ' . app_url('admin/login.php'),
        ];
    } else {
        $subject = 'Reset your Review System password';
        $lines = [
            'Hello ' . $user['username'] . ',',
            '',
            'We received a request to reset your password. Use the link below to choose a new one:',
            '',
            $link,
            '',
            'This link works once and expires in ' . $expires . '.',
            'If you did not ask for this, you can ignore this email — your password will not change.',
        ];
    }

    $sent = !empty($user['email']) && send_mail($user['email'], $subject, implode("\n", $lines) . "\n");
    return [$sent, $link];
}

/* --------------------------------------------------------------------------
   Logo uploads
   -------------------------------------------------------------------------- */

define('LOGO_DIR', dirname(__DIR__) . '/uploads/logos');   // filesystem path
define('LOGO_URL', 'uploads/logos');                        // URL path relative to site root
define('LOGO_MAX_BYTES', 2 * 1024 * 1024);                  // 2 MB

/**
 * Validates and stores an uploaded logo from $_FILES[$field].
 * Returns [stored filename|null, error message|null]. null/null means no file was chosen.
 */
function handle_logo_upload(string $field): array
{
    $file = $_FILES[$field] ?? null;
    if (!$file || !isset($file['error']) || is_array($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        return [null, 'The logo file is too large (2 MB max).'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return [null, 'The logo could not be uploaded. Please try again.'];
    }
    if ($file['size'] > LOGO_MAX_BYTES) {
        return [null, 'The logo file is too large (2 MB max).'];
    }

    // Trust the file's actual contents, not its name or the browser's claimed type
    $info    = @getimagesize($file['tmp_name']);
    $allowed = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($allowed[$info[2]])) {
        return [null, 'The logo must be a PNG, JPG, GIF or WebP image.'];
    }
    if ($info[0] > 4000 || $info[1] > 4000) {
        return [null, 'The logo is too large (4000 × 4000 pixels max).'];
    }

    if (!is_dir(LOGO_DIR) && !@mkdir(LOGO_DIR, 0755, true)) {
        return [null, 'The uploads folder could not be created. Check folder permissions.'];
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], LOGO_DIR . '/' . $name)) {
        return [null, 'The logo could not be saved. Check that the uploads folder is writable.'];
    }
    return [$name, null];
}

/** Removes a stored logo file (only plain filenames inside the logo folder). */
function delete_logo_file(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(png|jpg|gif|webp)$/', $name)) {
        $path = LOGO_DIR . '/' . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/** Public URL for a stored logo; $prefix is '../' from inside /admin/. */
function logo_url(?string $name, string $prefix = ''): string
{
    return $name ? $prefix . LOGO_URL . '/' . rawurlencode($name) : '';
}
