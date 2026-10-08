<?php
/**
 * Signed-in user's own account: email address (used for password resets) and password.
 */
require_once __DIR__ . '/auth.php';

$me     = current_user();
$errors = [];
$email  = (string)($me['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_str('action');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($action === 'email') {
        $email = post_str('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || str_len($email) > 150) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = db()->prepare('SELECT COUNT(*) FROM admins WHERE email = ? AND id <> ?');
                $stmt->execute([$email, $me['id']]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $errors[] = 'Another user already has that email address.';
                } else {
                    db()->prepare('UPDATE admins SET email = ? WHERE id = ?')->execute([$email, $me['id']]);
                    flash('Email address updated.');
                    redirect('account.php');
                }
            } catch (PDOException $ex) {
                error_log('admin/account.php: ' . $ex->getMessage());
                $errors[] = 'Could not update the email address. Please try again.';
            }
        }
    } elseif ($action === 'password') {
        $currentPw = (string)($_POST['current_password'] ?? '');
        $new       = (string)($_POST['new_password'] ?? '');
        $confirm   = (string)($_POST['confirm_password'] ?? '');

        if (strlen($new) < 10) {
            $errors[] = 'New password must be at least 10 characters.';
        }
        if ($new !== $confirm) {
            $errors[] = 'New passwords do not match.';
        }
        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
                $stmt->execute([$me['id']]);
                $hash = $stmt->fetchColumn();

                if (!$hash || !password_verify($currentPw, $hash)) {
                    $errors[] = 'Current password is incorrect.';
                } else {
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$newHash, $me['id']]);
                    db()->prepare('DELETE FROM password_resets WHERE admin_id = ?')->execute([$me['id']]);
                    // Keep this session signed in; other devices will be signed out
                    $_SESSION['pw_fp'] = password_fingerprint($newHash);
                    flash('Password updated. Any other devices signed in to this account have been signed out.');
                    redirect('account.php');
                }
            } catch (PDOException $ex) {
                error_log('admin/account.php: ' . $ex->getMessage());
                $errors[] = 'Could not update the password. Please try again.';
            }
        }
    }
}

$pageTitle = 'Account';
require __DIR__ . '/header.php';
?>
<div class="page-head"><h1>Account</h1></div>

<?php if ($errors): ?>
    <div class="notice notice-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="box box-narrow">
    <p class="muted small" style="margin:0">
        Signed in as <strong><?= e($me['username']) ?></strong> · <?= e(role_label($me['role'])) ?>
        <?php if (!is_super()): ?> · <?= e($me['property_name']) ?><?php endif; ?>
    </p>
</div>

<form method="post" class="form box box-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="email">
    <h2>Email address</h2>
    <?php if (empty($me['email'])): ?>
        <p class="notice notice-error">Add an email address so you can reset your password if you forget it.</p>
    <?php endif; ?>
    <label>Email
        <input type="email" name="email" required maxlength="150" autocomplete="email" value="<?= e($email) ?>">
        <span class="hint">Password reset links are sent here. You can also sign in with it.</span>
    </label>
    <button type="submit" class="btn btn-primary">Save email</button>
</form>

<form method="post" class="form box box-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="password">
    <h2>Change password</h2>
    <label>Current password
        <input type="password" name="current_password" required autocomplete="current-password">
    </label>
    <label>New password (10+ characters)
        <input type="password" name="new_password" required minlength="10" autocomplete="new-password">
    </label>
    <label>Confirm new password
        <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password">
    </label>
    <button type="submit" class="btn btn-primary">Update password</button>
    <p class="muted small" style="margin:.75rem 0 0">Forgot your current password? Sign out and use &ldquo;Forgot your password?&rdquo; on the sign-in page.</p>
</form>
<?php require __DIR__ . '/footer.php'; ?>
