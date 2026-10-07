<?php
/**
 * Change the signed-in admin's password.
 */
require_once __DIR__ . '/auth.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (strlen($new) < 10) {
        $errors[] = 'New password must be at least 10 characters.';
    }
    if ($new !== $confirm) {
        $errors[] = 'New passwords do not match.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
            $stmt->execute([$_SESSION['admin_id'] ?? 0]);
            $hash = $stmt->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
                flash('Password updated.');
                redirect('account.php');
            }
        } catch (PDOException $ex) {
            error_log('admin/account.php: ' . $ex->getMessage());
            $errors[] = 'Could not update the password. Please try again.';
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

<form method="post" class="form box box-narrow">
    <?= csrf_field() ?>
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
</form>
<?php require __DIR__ . '/footer.php'; ?>
