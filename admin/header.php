<?php
/**
 * Shared admin header/nav. Expects auth.php to be loaded and $pageTitle to be set.
 */
$current = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'index.php'      => 'Dashboard',
    'properties.php' => 'Properties',
    'reviews.php'    => 'Feedback',
    'account.php'    => 'Account',
];
$activeMap = ['property-edit.php' => 'properties.php'];
$active = $activeMap[$current] ?? $current;

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
        <a class="admin-brand" href="index.php">Review System</a>
        <nav class="admin-nav" aria-label="Admin">
            <?php foreach ($nav as $file => $label): ?>
                <a href="<?= e($file) ?>"<?= $active === $file ? ' class="is-active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
            <a href="logout.php">Sign out (<?= e($_SESSION['admin_username'] ?? '') ?>)</a>
        </nav>
    </div>
</header>
<main class="admin-main">
<?php if ($adminFlash): ?>
    <p class="notice notice-<?= $adminFlash['type'] === 'error' ? 'error' : 'success' ?>" role="status"><?= e($adminFlash['message']) ?></p>
<?php endif; ?>
