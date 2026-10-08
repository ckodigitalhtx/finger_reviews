<?php
/**
 * Property list + delete.
 */
require_once __DIR__ . '/auth.php';
require_super();

// ---- Delete ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!csrf_valid() || !$id) {
        flash('Could not delete the property. Please try again.', 'error');
    } else {
        try {
            $stmt = db()->prepare('SELECT logo_path FROM properties WHERE id = ?');
            $stmt->execute([$id]);
            $logo = $stmt->fetchColumn();

            $stmt = db()->prepare('DELETE FROM properties WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount()) {
                delete_logo_file($logo ?: null);
            }
            flash($stmt->rowCount() ? 'Property deleted.' : 'Property not found.', $stmt->rowCount() ? 'success' : 'error');
        } catch (PDOException $ex) {
            error_log('admin/properties.php: ' . $ex->getMessage());
            flash('Could not delete the property.', 'error');
        }
    }
    redirect('properties.php');
}

// ---- List ----
$properties = [];
$dbError    = '';
try {
    $properties = db()->query(
        'SELECT p.*, (SELECT COUNT(*) FROM captured_reviews r WHERE r.property_id = p.id) AS review_count
           FROM properties p
          ORDER BY p.property_name'
    )->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/properties.php: ' . $ex->getMessage());
    $dbError = 'Could not load properties.';
}

$pageTitle = 'Properties';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1>Properties</h1>
    <a class="btn btn-primary" href="property-edit.php">Add property</a>
</div>

<?php if ($dbError): ?><p class="notice notice-error"><?= e($dbError) ?></p><?php endif; ?>

<div class="box">
<?php if (!$properties): ?>
    <p class="muted">No properties yet. Add your first one to get its review link.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>Property</th><th>Review page link</th><th>Platforms</th><th>Feedback</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($properties as $p): ?>
                <?php $url = public_review_url((int)$p['id']); ?>
                <tr>
                    <td>
                        <?php if (!empty($p['logo_path'])): ?>
                            <img class="logo-thumb" src="<?= e(logo_url($p['logo_path'], '../')) ?>" alt="">
                        <?php endif; ?>
                        <strong><?= e($p['property_name']) ?></strong><br>
                        <span class="muted small"><?= e($p['notification_email']) ?></span>
                    </td>
                    <td>
                        <input class="url-field" type="text" readonly value="<?= e($url) ?>" onfocus="this.select()" aria-label="Review page link for <?= e($p['property_name']) ?>">
                    </td>
                    <td>
                        <?php $any = false; foreach (review_platforms() as $col => $label): if (!empty($p[$col])): $any = true; ?>
                            <span class="tag"><?= e($label) ?></span>
                        <?php endif; endforeach; ?>
                        <?php if (!$any): ?><span class="muted small">None set</span><?php endif; ?>
                    </td>
                    <td><a href="reviews.php?property_id=<?= (int)$p['id'] ?>"><?= (int)$p['review_count'] ?></a></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-secondary btn-small" href="<?= e($url) ?>" target="_blank" rel="noopener">View</a>
                            <a class="btn btn-secondary btn-small" href="property-edit.php?id=<?= (int)$p['id'] ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this property, all of its captured feedback and its property user? This cannot be undone.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-small">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
