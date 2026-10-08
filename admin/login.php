<?php
/**
 * Admin login form & handler. Accepts a username or an email address.
 */
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/plain-layout.php';
start_session();

if (!empty($_SESSION['admin_logged_in'])) {
    redirect('index.php');
}

$error = '';
$login = '';
$info  = '';
if (isset($_GET['reset'])) {
    $info = 'Your password has been set. Sign in with your new password.';
} elseif (isset($_GET['signed_out'])) {
    $info = 'You have been signed out. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = post_str('login');
    $password = (string)($_POST['password'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($login === '' || $password === '') {
        $error = 'Enter your username (or email) and password.';
    } else {
        try {
            $pdo = db();
            migrate_schema($pdo);

            $stmt = $pdo->prepare(
                'SELECT id, username, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1'
            );
            $stmt->execute([$login, $login]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $hash = $admin['password_hash'];
                if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$hash, $admin['id']]);
                }

                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id']        = (int)$admin['id'];
                $_SESSION['admin_username']  = $admin['username'];
                $_SESSION['pw_fp']           = password_fingerprint($hash);
                $_SESSION['schema_version']  = SCHEMA_VERSION;
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

plain_header('Sign in');
?>
        <h1>Review System</h1>
        <p class="muted">Sign in to manage properties and feedback.</p>
        <?php if ($info): ?><p class="notice notice-success" role="status"><?= e($info) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Username or email
                <input type="text" name="login" required maxlength="150" autocomplete="username" autofocus value="<?= e($login) ?>">
            </label>
            <label>Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
        <p class="auth-alt"><a href="forgot-password.php">Forgot your password?</a></p>
<?php plain_footer(); ?>
