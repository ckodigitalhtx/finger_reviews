<?php
/**
 * Captured feedback log: filter by property, search, paginate.
 */
require_once __DIR__ . '/auth.php';

$perPage    = 25;
$propertyId = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT) ?: 0;
if (!is_super()) {
    $propertyId = (int)scoped_property_id();   // always limited to their own property
}
$search     = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$page       = max(1, (int)(filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1));

$properties = [];
$reviews    = [];
$total      = 0;
$dbError    = '';

try {
    $pdo = db();
    $properties = $pdo->query('SELECT id, property_name FROM properties ORDER BY property_name')->fetchAll();

    $where  = [];
    $params = [];
    if ($propertyId) {
        $where[]  = 'r.property_id = ?';
        $params[] = $propertyId;
    }
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(r.reviewer_name LIKE ? OR r.reviewer_email LIKE ? OR r.reviewer_phone LIKE ? OR r.feedback_text LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM captured_reviews r $whereSql");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are integers computed above, never user-supplied strings
    $stmt = $pdo->prepare(
        "SELECT r.*, p.property_name
           FROM captured_reviews r
           JOIN properties p ON p.id = r.property_id
           $whereSql
          ORDER BY r.created_at DESC, r.id DESC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log('admin/reviews.php: ' . $ex->getMessage());
    $dbError = 'Could not load feedback.';
    $pages = 1;
}

$pageLink = static function (int $n) use ($propertyId, $search): string {
    return 'reviews.php?' . http_build_query(array_filter([
        'property_id' => $propertyId ?: null,
        'q'           => $search !== '' ? $search : null,
        'page'        => $n,
    ]));
};

$pageTitle = 'Feedback';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1>Captured feedback</h1>
    <span class="muted"><?= $total ?> result<?= $total === 1 ? '' : 's' ?></span>
</div>

<?php if ($dbError): ?><p class="notice notice-error"><?= e($dbError) ?></p><?php endif; ?>

<form method="get" class="form box filters">
    <?php if (is_super()): ?>
    <label>Property
        <select name="property_id">
            <option value="">All properties</option>
            <?php foreach ($properties as $p): ?>
                <option value="<?= (int)$p['id'] ?>"<?= $propertyId === (int)$p['id'] ? ' selected' : '' ?>><?= e($p['property_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <label>Search
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email, phone or comments">
    </label>
    <button type="submit" class="btn btn-primary">Filter</button>
    <?php if ((is_super() && $propertyId) || $search !== ''): ?>
        <a class="btn btn-secondary" href="reviews.php">Clear</a>
    <?php endif; ?>
</form>

<div class="box">
<?php if (!$reviews): ?>
    <p class="muted">No feedback matches these filters.</p>
<?php else: ?>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr><th>Date</th><th>Property</th><th>Rating</th><th>Name</th><th>Contact</th><th>Comments</th></tr>
            </thead>
            <tbody>
            <?php foreach ($reviews as $row): ?>
                <tr>
                    <td class="nowrap">
                        <?= e(date('M j, Y', strtotime($row['created_at']))) ?><br>
                        <span class="muted small"><?= e(date('g:i A', strtotime($row['created_at']))) ?></span>
                    </td>
                    <td><?= e($row['property_name']) ?></td>
                    <td><span class="rating" title="<?= (int)$row['star_rating'] ?> of 5"><?= star_string((int)$row['star_rating']) ?></span></td>
                    <td><?= e($row['reviewer_name']) ?></td>
                    <td>
                        <a href="mailto:<?= e($row['reviewer_email']) ?>"><?= e($row['reviewer_email']) ?></a>
                        <?php if (!empty($row['reviewer_phone'])): ?>
                            <br><span class="nowrap"><?= e($row['reviewer_phone']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="feedback-text"><?= e($row['feedback_text']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a class="btn btn-secondary btn-small" href="<?= e($pageLink($page - 1)) ?>">&larr; Newer</a><?php endif; ?>
            <span class="muted small">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn-secondary btn-small" href="<?= e($pageLink($page + 1)) ?>">Older &rarr;</a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
