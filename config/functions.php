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

/** Absolute URL of the public review page for a property. */
function public_review_url(int $propertyId): string
{
    $https  = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/x.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $base . '/review.php?property_id=' . $propertyId;
}

/** "★★★☆☆" for a rating. */
function star_string(int $rating): string
{
    $rating = max(0, min(5, $rating));
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}
