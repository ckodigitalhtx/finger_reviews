<?php
/**
 * User management (super admins only): list, delete, send password reset emails.
 */
require_once __DIR__ . '/auth.php';
require_super();

$me = current_user();

/** Number of super admins — used to stop the last one being removed. */
function super_count(): int
{
    return (int)db()->query("SELECT COUNT(*) FROM admins WHERE role = 'super'")->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = post_str('action');

    if (!csrf_valid() || !$id) {
        flash('That action could not be completed. Please try again.', 'error');
        redirect('users.php');
    }

    try {
        $stmt = db()->prepare('SELECT id, username, email, role FROM admins WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        if (!$target) {
            flash('User not found.', 'error');
        } elseif ($action === 'delete') {
            if ((int)$target['id'] === (int)$me['id']) {
                flash('You cannot delete your own account.', 'error');
            } elseif ($target['role'] === 'super' && super_count() <= 1) {
                flash('You cannot delete the last super admin.', 'error');
            } else {
                db()->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
                flash('User "' . $target['username'] . '" deleted.');
            }
        } elseif ($action === 'reset') {
            if (empty($target['email'])) {
                flash('"' . $target['username'] . '" has no email address. Edit the user to add one, or set a password for them.', 'error');
            } else {
                [$sent, $link] = send_password_email(db(), $target, true);
                if ($sent) {
                    flash('Password link emailed to ' . $target['email'] . '. It expires in ' . (INVITE_MINUTES / 60) . ' hours.');
                } else {
                    $_SESSION['reset_link'] = ['username' => $target['username'], 'link' => $link];
                    flash('The email could not be sent from this server. Copy the link below and send it to the user yourself.', 'error');
                }
            }
        }
    } catch (PDOException $ex) {
        error_log('admin/users.php: ' . $ex->getMessage());
        flash('That action could not be completed. Please try again.', 'error');
    }
    redirect('users.php');
}

$resetLink = $_SESSION['reset_link'] ?? null;
unset($_SESSION['reset_link']);

$users   = [];
$dbError = '';
try {
    $users = db()->query(
        "SELECT a.id, a.username, a.email, a.role, a.created_at, p.property_name
           FROM admins a
           LEFT JOIN properties p ON p.id = a.property_id
          ORDER BY a.role = 'super' DESC, p.property_name, a.username"
    )->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/users.php: ' . $ex->getMessage());
    $dbError = 'Could not load users.';
}

$pageTitle = 'Users';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1>Users</h1>
    <a class="btn btn-primary" href="user-edit.php">Add user</a>
</div>

<?php if ($dbError): ?><p class="notice notice-error"><?= e($dbError) ?></p><?php endif; ?>

<?php if ($resetLink): ?>
    <div class="box">
        <p><strong>Password link for <?= e($resetLink['username']) ?></strong> — works once, expires in <?= INVITE_MINUTES / 60 ?> hours:</p>
        <input class="url-field" type="text" readonly value="<?= e($resetLink['link']) ?>" onfocus="this.select()" aria-label="Password link">
    </div>
<?php endif; ?>

<div class="box">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Property</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?= e($u['username']) ?></strong>
                        <?php if ((int)$u['id'] === (int)$me['id']): ?><span class="tag">You</span><?php endif; ?>
                    </td>
                    <td><?= $u['email'] ? e($u['email']) : '<span class="muted small">None — cannot reset by email</span>' ?></td>
                    <td><?= e(role_label($u['role'])) ?></td>
                    <td><?= $u['role'] === 'super' ? '<span class="muted small">All properties</span>' : e($u['property_name']) ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-secondary btn-small" href="user-edit.php?id=<?= (int)$u['id'] ?>">Edit</a>
                            <?php if ($u['email']): ?>
                                <form method="post">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-small">Email password link</button>
                                </form>
                            <?php endif; ?>
                            <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                                <form method="post" onsubmit="return confirm('Delete user <?= e(addslashes($u['username'])) ?>? This cannot be undone.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="muted small">Super admins manage everything. Property users see only their own property&rsquo;s feedback and can edit its review page (wording, logo, links and alert email).</p>
<?php require __DIR__ . '/footer.php'; ?>
