<?php
/**
 * Public review page: review.php?property_id=X
 * 4-5 stars -> public review links. 1-3 stars -> private feedback form.
 */
require_once __DIR__ . '/config/functions.php';
start_session();

$propertyId = filter_input(INPUT_GET, 'property_id', FILTER_VALIDATE_INT);
$property   = null;
$dbError    = false;

if ($propertyId) {
    try {
        $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
        $stmt->execute([$propertyId]);
        $property = $stmt->fetch() ?: null;
    } catch (PDOException $ex) {
        error_log('review.php: ' . $ex->getMessage());
        $dbError = true;
    }
}

if (!$property) {
    http_response_code($dbError ? 500 : 404);
}

// Flash data from submit-review.php (validation errors / thank-you state)
$flash  = $_SESSION['review_flash'] ?? [];
unset($_SESSION['review_flash']);
$errors = $flash['errors'] ?? [];
$old    = $flash['old'] ?? [];
$thanks = !empty($flash['thanks']);
$initialRating = (int)($old['star_rating'] ?? 0);

// Only platforms with a URL configured get a button
$links = [];
if ($property) {
    foreach (review_platforms() as $column => $label) {
        if (!empty($property[$column])) {
            $links[$label] = $property[$column];
        }
    }
}

$defaultWording = "Thank you for being part of our community. We'd love to hear how your experience has been. Please tap a star to rate us.";
$wording = $property && trim((string)$property['custom_wording']) !== ''
    ? $property['custom_wording']
    : $defaultWording;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $property ? e($property['property_name']) . ' — ' : '' ?>Share Your Experience</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="public">
<main class="card">
<?php if (!$property): ?>
    <h1><?= $dbError ? 'We&rsquo;ll be right back' : 'Page not found' ?></h1>
    <p class="lead">
        <?= $dbError
            ? 'Something went wrong on our end. Please try again in a few minutes.'
            : 'This review link is not valid. Please check the link you were given or contact your leasing office.' ?>
    </p>

<?php elseif ($thanks): ?>
    <p class="eyebrow"><?= e($property['property_name']) ?></p>
    <h1>Thank you for your feedback</h1>
    <p class="lead">Your comments have been sent directly to our management team. We take them seriously and someone will reach out to you soon.</p>

<?php else: ?>
    <p class="eyebrow">How are we doing?</p>
    <h1><?= e($property['property_name']) ?></h1>
    <p class="lead"><?= nl2br(e($wording)) ?></p>

    <div class="stars" id="stars" role="radiogroup" aria-label="Star rating" data-initial="<?= $initialRating ?>">
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" class="star" role="radio" aria-checked="false"
                    data-value="<?= $i ?>" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">★</button>
        <?php endfor; ?>
    </div>
    <p class="rating-label" id="rating-label" aria-live="polite">&nbsp;</p>
    <noscript><p class="notice notice-error">Please enable JavaScript to leave a rating.</p></noscript>

    <!-- 4-5 stars: public review links -->
    <section class="panel" id="panel-positive" hidden>
        <?php if ($links): ?>
            <h2>We&rsquo;re so glad to hear that!</h2>
            <p>Would you take a moment to share your experience? It helps future residents find us.</p>
            <div class="review-links">
                <?php foreach ($links as $label => $url): ?>
                    <a class="btn btn-primary btn-block" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">
                        Review us on <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <h2>Thank you!</h2>
            <p>We&rsquo;re so glad you&rsquo;re enjoying your home. We appreciate you taking the time to let us know.</p>
        <?php endif; ?>
    </section>

    <!-- 1-3 stars: private feedback form -->
    <section class="panel" id="panel-negative" hidden>
        <h2>We&rsquo;re sorry we fell short</h2>
        <p>Please tell us what happened so our management team can make it right.</p>

        <?php if ($errors): ?>
            <div class="notice notice-error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="submit-review.php" class="form" id="feedback-form">
            <?= csrf_field() ?>
            <input type="hidden" name="property_id" value="<?= (int)$property['id'] ?>">
            <input type="hidden" name="star_rating" id="star_rating" value="<?= $initialRating ?: '' ?>">

            <!-- Honeypot: hidden from people, tempting for bots -->
            <div class="hp" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <label>Name
                <input type="text" name="reviewer_name" required maxlength="100" autocomplete="name" value="<?= e($old['reviewer_name'] ?? '') ?>">
            </label>
            <label>Email
                <input type="email" name="reviewer_email" required maxlength="150" autocomplete="email" value="<?= e($old['reviewer_email'] ?? '') ?>">
            </label>
            <label>Phone <span class="optional">(optional)</span>
                <input type="tel" name="reviewer_phone" maxlength="50" autocomplete="tel" value="<?= e($old['reviewer_phone'] ?? '') ?>">
            </label>
            <label>Comments
                <textarea name="feedback_text" required maxlength="5000" rows="5"><?= e($old['feedback_text'] ?? '') ?></textarea>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Send feedback</button>
        </form>
    </section>
<?php endif; ?>
</main>
<script src="assets/js/main.js"></script>
</body>
</html>
