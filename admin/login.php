<?php
/**
 * Admin login form & handler.
 */
require_once __DIR__ . '/../config/functions.php';
start_session();

if (!empty($_SESSION['admin_logged_in'])) {
    redirect('index.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post_str('username');
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } else {
        try {
            $stmt = db()->prepare('SELECT id, username, password_hash FROM admins WHERE username = ?');
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = (int)$admin['id'];
                $_SESSION['admin_username']  = $admin['username'];

                if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
                }
                redirect('index.php');
            }

            usleep(500000); // slow down password guessing
            $error = 'Incorrect username or password.';
        } catch (PDOException $ex) {
            error_log('admin/login.php: ' . $ex->getMessage());
            $error = 'Could not reach the database. Please try again.';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in — Review System</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin">
<div class="login-wrap">
    <div class="box">
        <h1>Review System</h1>
        <p class="muted">Sign in to manage properties and feedback.</p>
        <?php if ($error): ?>
            <p class="notice notice-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Username
                <input type="text" name="username" required maxlength="50" autocomplete="username" autofocus value="<?= e($username) ?>">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </div>
</div>
</body>
</html>
