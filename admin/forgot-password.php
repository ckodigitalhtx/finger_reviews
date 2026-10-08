<?php
/**
 * "Forgot password" — emails a one-time reset link.
 * Always shows the same message, so it never reveals which emails have accounts.
 */
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/plain-layout.php';
start_session();

$done  = false;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post_str('email');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = db();
            migrate_schema($pdo);

            $stmt = $pdo->prepare('SELECT id, username, email FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Throttle: a new request replaces the old link, so only allow one every 2 minutes
                $stmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM password_resets
                      WHERE admin_id = ? AND created_at > (NOW() - INTERVAL 2 MINUTE)'
                );
                $stmt->execute([$user['id']]);
                if ((int)$stmt->fetchColumn() === 0) {
                    send_password_email($pdo, $user, false);
                }
            } else {
                usleep(300000); // keep timing similar whether or not the email exists
            }
            $done = true;
        } catch (PDOException $ex) {
            error_log('admin/forgot-password.php: ' . $ex->getMessage());
            $error = 'Something went wrong. Please try again in a moment.';
        }
    }
}

plain_header('Forgot password');
?>
        <h1>Forgot password</h1>
        <?php if ($done): ?>
            <p class="notice notice-success" role="status">
                If an account uses <strong><?= e($email) ?></strong>, a reset link is on its way.
                It expires in <?= RESET_MINUTES ?> minutes.
            </p>
            <p class="muted small">No email? Check your spam folder, or ask a super admin to send you a new link.</p>
        <?php else: ?>
            <p class="muted">Enter the email address on your account and we&rsquo;ll send you a link to choose a new password.</p>
            <?php if ($error): ?><p class="notice notice-error" role="alert"><?= e($error) ?></p><?php endif; ?>
            <form method="post" class="form">
                <?= csrf_field() ?>
                <label>Email
                    <input type="email" name="email" required maxlength="150" autocomplete="email" autofocus value="<?= e($email) ?>">
                </label>
                <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
            </form>
        <?php endif; ?>
        <p class="auth-alt"><a href="login.php">Back to sign in</a></p>
<?php plain_footer(); ?>
