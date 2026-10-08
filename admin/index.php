<?php
/**
 * Admin dashboard (redirects to login when signed out).
 */
require_once __DIR__ . '/auth.php';

$stats   = ['properties' => 0, 'reviews' => 0, 'last30' => 0];
$recent  = [];
$dbError = '';

try {
    $pdo   = db();
    $scope = scoped_property_id();               // null = all properties (super admin)
    $where = $scope ? 'WHERE r.property_id = ?' : '';
    $args  = $scope ? [$scope] : [];

    $stats['properties'] = (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM captured_reviews r $where");
    $stmt->execute($args);
    $stats['reviews'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM captured_reviews r '
        . ($where ? $where . ' AND' : 'WHERE') . ' r.created_at >= (NOW() - INTERVAL 30 DAY)'
    );
    $stmt->execute($args);
    $stats['last30'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT r.*, p.property_name
           FROM captured_reviews r
           JOIN properties p ON p.id = r.property_id
           $where
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT 5"
    );
    $stmt->execute($args);
    $recent = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/index.php: ' . $ex->getMessage());
    $dbError = 'Could not load dashboard data.';
}

$pageTitle = 'Dashboard';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1>Dashboard<?php if (!is_super()): ?> <span class="muted">· <?= e(current_user()['property_name']) ?></span><?php endif; ?></h1>
    <?php if (is_super()): ?>
        <a class="btn btn-primary" href="property-edit.php">Add property</a>
    <?php else: ?>
        <a class="btn btn-secondary" href="<?= e(public_review_url((int)scoped_property_id())) ?>" target="_blank" rel="noopener">View review page</a>
    <?php endif; ?>
</div>

<?php if ($dbError): ?><p class="notice notice-error"><?= e($dbError) ?></p><?php endif; ?>

<div class="stats">
    <?php if (is_super()): ?>
        <div class="stat"><div class="stat-value"><?= $stats['properties'] ?></div><div class="stat-label">Properties</div></div>
    <?php endif; ?>
    <div class="stat"><div class="stat-value"><?= $stats['last30'] ?></div><div class="stat-label">Feedback, last 30 days</div></div>
    <div class="stat"><div class="stat-value"><?= $stats['reviews'] ?></div><div class="stat-label">Feedback, all time</div></div>
</div>

<div class="box">
    <div class="page-head">
        <h2>Latest feedback</h2>
        <a href="reviews.php">View all</a>
    </div>
    <?php if (!$recent): ?>
        <p class="muted">No feedback has been captured yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Date</th><th>Property</th><th>Rating</th><th>From</th><th>Comments</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e(date('M j, Y', strtotime($row['created_at']))) ?></td>
                        <td><?= e($row['property_name']) ?></td>
                        <td><span class="rating" title="<?= (int)$row['star_rating'] ?> of 5"><?= star_string((int)$row['star_rating']) ?></span></td>
                        <td><?= e($row['reviewer_name']) ?></td>
                        <td class="feedback-text"><?= e($row['feedback_text']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
