<?php
/**
 * Admin dashboard (redirects to login when signed out).
 */
require_once __DIR__ . '/auth.php';

$stats   = ['properties' => 0, 'reviews' => 0, 'last30' => 0];
$recent  = [];
$dbError = '';

try {
    $pdo = db();
    $stats['properties'] = (int)$pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
    $stats['reviews']    = (int)$pdo->query('SELECT COUNT(*) FROM captured_reviews')->fetchColumn();
    $stats['last30']     = (int)$pdo->query(
        'SELECT COUNT(*) FROM captured_reviews WHERE created_at >= (NOW() - INTERVAL 30 DAY)'
    )->fetchColumn();

    $recent = $pdo->query(
        'SELECT r.*, p.property_name
           FROM captured_reviews r
           JOIN properties p ON p.id = r.property_id
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT 5'
    )->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/index.php: ' . $ex->getMessage());
    $dbError = 'Could not load dashboard data.';
}

$pageTitle = 'Dashboard';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1>Dashboard</h1>
    <a class="btn btn-primary" href="property-edit.php">Add property</a>
</div>

<?php if ($dbError): ?><p class="notice notice-error"><?= e($dbError) ?></p><?php endif; ?>

<div class="stats">
    <div class="stat"><div class="stat-value"><?= $stats['properties'] ?></div><div class="stat-label">Properties</div></div>
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
