<?php
/**
 * Choose a new password from an emailed link (?token=...). Links are single-use and expire.
 */
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/plain-layout.php';
start_session();
header('Referrer-Policy: no-referrer');

$token  = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$errors = [];
$user   = null;

try {
    $pdo = db();
    migrate_schema($pdo);
    $user = find_password_reset($pdo, $token);
} catch (PDOException $ex) {
    error_log('admin/reset-password.php: ' . $ex->getMessage());
    $errors[] = 'Something went wrong. Please try again in a moment.';
}

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if (strlen($password) < 10) {
        $errors[] = 'Password must be at least 10 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ?')->execute([$user['id']]);
            $pdo->commit();

            // Any signed-in session (here or elsewhere) is now invalid
            $_SESSION = [];
            session_regenerate_id(true);
            redirect('login.php?reset=1');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('admin/reset-password.php: ' . $ex->getMessage());
            $errors[] = 'Could not update the password. Please try again.';
        }
    }
}

plain_header('Choose a new password');
?>
        <h1>Choose a new password</h1>
        <?php if (!$user): ?>
            <p class="notice notice-error" role="alert">This link is invalid, has already been used, or has expired.</p>
            <p><a class="btn btn-primary btn-block" href="forgot-password.php">Request a new link</a></p>
        <?php else: ?>
            <p class="muted">Account: <strong><?= e($user['username']) ?></strong></p>
            <?php if ($errors): ?>
                <div class="notice notice-error" role="alert">
                    <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <form method="post" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="text" name="username" value="<?= e($user['username']) ?>" autocomplete="username" hidden>
                <label>New password (10+ characters)
                    <input type="password" name="password" required minlength="10" autocomplete="new-password" autofocus>
                </label>
                <label>Confirm new password
                    <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
                </label>
                <button type="submit" class="btn btn-primary btn-block">Save password</button>
            </form>
        <?php endif; ?>
        <p class="auth-alt"><a href="login.php">Back to sign in</a></p>
<?php plain_footer(); ?>
