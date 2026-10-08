<?php
/**
 * Shared admin header/nav. Expects auth.php to be loaded and $pageTitle to be set.
 */
$current = basename($_SERVER['SCRIPT_NAME']);
$user    = current_user();

if (is_super()) {
    $nav = [
        ['index.php', 'Dashboard', ['index.php']],
        ['properties.php', 'Properties', ['properties.php', 'property-edit.php']],
        ['reviews.php', 'Feedback', ['reviews.php']],
        ['users.php', 'Users', ['users.php', 'user-edit.php']],
        ['account.php', 'Account', ['account.php']],
    ];
} else {
    $nav = [
        ['index.php', 'Dashboard', ['index.php']],
        ['property-edit.php?id=' . (int)$user['property_id'], 'My Property', ['property-edit.php']],
        ['reviews.php', 'Feedback', ['reviews.php']],
        ['account.php', 'Account', ['account.php']],
    ];
}

$adminFlash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'Admin') ?> — Review System</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin">
<header class="admin-header">
    <div class="inner">
        <a class="admin-brand" href="index.php">
            Review System<?php if (!is_super()): ?> <span class="admin-brand-sub"><?= e($user['property_name']) ?></span><?php endif; ?>
        </a>
        <nav class="admin-nav" aria-label="Admin">
            <?php foreach ($nav as [$href, $label, $files]): ?>
                <a href="<?= e($href) ?>"<?= in_array($current, $files, true) ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a href="logout.php">Sign out (<?= e($user['username']) ?>)</a>
        </nav>
    </div>
</header>
<main class="admin-main">
<?php if ($adminFlash): ?>
    <p class="notice notice-<?= $adminFlash['type'] === 'error' ? 'error' : 'success' ?>" role="status"><?= e($adminFlash['message']) ?></p>
<?php endif; ?>
