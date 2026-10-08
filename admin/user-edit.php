<?php
/**
 * Add / edit a user (super admins only).
 * New users without a password are emailed a link to choose their own.
 */
require_once __DIR__ . '/auth.php';
require_super();

$me     = current_user();
$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$isSelf = $id && $id === (int)$me['id'];
$errors = [];

$user = ['username' => '', 'email' => '', 'role' => 'property', 'property_id' => ''];
$originalRole = null;

try {
    $pdo = db();

    if ($id) {
        $stmt = $pdo->prepare('SELECT id, username, email, role, property_id FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            flash('User not found.', 'error');
            redirect('users.php');
        }
        $user = [
            'username'    => $row['username'],
            'email'       => (string)$row['email'],
            'role'        => $row['role'],
            'property_id' => (string)$row['property_id'],
        ];
        $originalRole = $row['role'];
    }

    // Properties that don't have a user yet (plus this user's own property)
    $stmt = $pdo->prepare(
        'SELECT p.id, p.property_name
           FROM properties p
           LEFT JOIN admins a ON a.property_id = p.id
          WHERE a.id IS NULL OR a.id = ?
          ORDER BY p.property_name'
    );
    $stmt->execute([$id ?: 0]);
    $availableProperties = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/user-edit.php: ' . $ex->getMessage());
    flash('Could not load the user.', 'error');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['username']    = post_str('username');
    $user['email']       = post_str('email');
    $user['role']        = $isSelf ? $originalRole : post_str('role');
    $user['property_id'] = post_str('property_id');
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password_confirm'] ?? '');

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $user['username'])) {
        $errors[] = 'Username must be 3–50 characters (letters, numbers, dot, dash, underscore).';
    }
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL) || str_len($user['email']) > 150) {
        $errors[] = 'Enter a valid email address — it is used for password resets.';
    }
    if (!in_array($user['role'], ['super', 'property'], true)) {
        $errors[] = 'Choose a role.';
    }

    $propertyId = null;
    if ($user['role'] === 'property') {
        $propertyId = (int)$user['property_id'];
        if (!in_array($propertyId, array_map('intval', array_column($availableProperties, 'id')), true)) {
            $errors[] = 'Choose a property that does not already have a user.';
        }
    }

    if ($password !== '' || $confirm !== '') {
        if (strlen($password) < 10) {
            $errors[] = 'Password must be at least 10 characters.';
        }
        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT username, email FROM admins WHERE (username = ? OR email = ?) AND id <> ?');
            $stmt->execute([$user['username'], $user['email'], $id ?: 0]);
            foreach ($stmt->fetchAll() as $clash) {
                if (strcasecmp($clash['username'], $user['username']) === 0) {
                    $errors[] = 'That username is already taken.';
                }
                if ($clash['email'] !== null && strcasecmp($clash['email'], $user['email']) === 0) {
                    $errors[] = 'Another user already has that email address.';
                }
            }

            if ($id && $originalRole === 'super' && $user['role'] !== 'super') {
                $supers = (int)$pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super'")->fetchColumn();
                if ($supers <= 1) {
                    $errors[] = 'This is the last super admin, so it must stay a super admin.';
                }
            }
        } catch (PDOException $ex) {
            error_log('admin/user-edit.php: ' . $ex->getMessage());
            $errors[] = 'Could not check for duplicate users. Please try again.';
        }
    }

    if (!$errors) {
        try {
            if ($id) {
                $pdo->prepare('UPDATE admins SET username = ?, email = ?, role = ?, property_id = ? WHERE id = ?')
                    ->execute([$user['username'], $user['email'], $user['role'], $propertyId, $id]);
                if ($password !== '') {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([$newHash, $id]);
                    $pdo->prepare('DELETE FROM password_resets WHERE admin_id = ?')->execute([$id]);
                    if ($isSelf) {
                        $_SESSION['pw_fp'] = password_fingerprint($newHash);
                    }
                }
                flash('User "' . $user['username'] . '" updated.');
                redirect('users.php');
            }

            // New user: with no password given, store an unusable random one and email a set-password link
            $hash = password_hash($password !== '' ? $password : bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO admins (username, email, password_hash, role, property_id) VALUES (?, ?, ?, ?, ?)')
                ->execute([$user['username'], $user['email'], $hash, $user['role'], $propertyId]);
            $newId = (int)$pdo->lastInsertId();

            if ($password !== '') {
                flash('User "' . $user['username'] . '" created with the password you set.');
            } else {
                [$sent, $link] = send_password_email($pdo, ['id' => $newId] + $user, true);
                if ($sent) {
                    flash('User "' . $user['username'] . '" created. A link to set their password was emailed to ' . $user['email'] . '.');
                } else {
                    $_SESSION['reset_link'] = ['username' => $user['username'], 'link' => $link];
                    flash('User created, but the email could not be sent from this server. Copy the link below and send it to them yourself.', 'error');
                }
            }
            redirect('users.php');
        } catch (PDOException $ex) {
            error_log('admin/user-edit.php: ' . $ex->getMessage());
            $errors[] = 'Could not save the user. Please try again.';
        }
    }
}

$pageTitle = $id ? 'Edit user' : 'Add user';
require __DIR__ . '/header.php';
?>
<div class="page-head"><h1><?= e($pageTitle) ?></h1></div>

<?php if ($errors): ?>
    <div class="notice notice-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="form box box-narrow" id="user-form">
    <?= csrf_field() ?>

    <div class="form-grid">
        <label>Username
            <input type="text" name="username" required maxlength="50" pattern="[A-Za-z0-9_.\-]{3,50}" autocomplete="off" value="<?= e($user['username']) ?>">
        </label>
        <label>Email
            <input type="email" name="email" required maxlength="150" autocomplete="off" value="<?= e($user['email']) ?>">
        </label>
    </div>

    <?php if ($isSelf): ?>
        <p class="muted small">You are editing your own account, so your role can&rsquo;t be changed here.</p>
    <?php else: ?>
        <div class="form-grid">
            <label>Role
                <select name="role" id="role">
                    <option value="property"<?= $user['role'] === 'property' ? ' selected' : '' ?>>Property user — one property only</option>
                    <option value="super"<?= $user['role'] === 'super' ? ' selected' : '' ?>>Super admin — everything</option>
                </select>
            </label>
            <label id="property-field">Property
                <select name="property_id">
                    <option value="">Choose a property…</option>
                    <?php foreach ($availableProperties as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"<?= (string)$p['id'] === (string)$user['property_id'] ? ' selected' : '' ?>><?= e($p['property_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Each property can have one user. Properties that already have one aren&rsquo;t listed.</span>
            </label>
        </div>
    <?php endif; ?>

    <h2><?= $id ? 'Set a new password' : 'Password' ?> <span class="optional">(optional)</span></h2>
    <p class="muted small">
        <?= $id
            ? 'Leave blank to keep the current password. To let the user choose their own, use “Email password link” on the Users page.'
            : 'Leave blank to email the user a link to choose their own password (recommended).' ?>
    </p>
    <div class="form-grid">
        <label>Password (10+ characters)
            <input type="password" name="password" minlength="10" autocomplete="new-password">
        </label>
        <label>Confirm password
            <input type="password" name="password_confirm" minlength="10" autocomplete="new-password">
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $id ? 'Save changes' : 'Create user' ?></button>
        <a class="btn btn-secondary" href="users.php">Cancel</a>
    </div>
</form>

<script>
/* Only show the property picker for property users */
(function () {
    var role = document.getElementById('role');
    var field = document.getElementById('property-field');
    if (!role || !field) { return; }
    function sync() { field.hidden = role.value !== 'property'; }
    role.addEventListener('change', sync);
    sync();
})();
</script>
<?php require __DIR__ . '/footer.php'; ?>
