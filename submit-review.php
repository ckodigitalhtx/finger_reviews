<?php
/**
 * Handles the private feedback form (1-3 stars):
 * validate -> save to captured_reviews -> email the property's team -> thank-you.
 */
require_once __DIR__ . '/config/functions.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$propertyId = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
if (!$propertyId) {
    redirect('index.php');
}
$backUrl = 'review.php?property_id=' . $propertyId;

$input = [
    'star_rating'    => (int)($_POST['star_rating'] ?? 0),
    'reviewer_name'  => post_str('reviewer_name'),
    'reviewer_email' => post_str('reviewer_email'),
    'reviewer_phone' => post_str('reviewer_phone'),
    'feedback_text'  => post_str('feedback_text'),
];

// Bots that fill the honeypot get a silent "success"
if (post_str('website') !== '') {
    $_SESSION['review_flash'] = ['thanks' => true];
    redirect($backUrl);
}

// ---- Validation ----
$errors = [];
if (!csrf_valid()) {
    $errors[] = 'Your session expired. Please submit the form again.';
}
if ($input['star_rating'] < 1 || $input['star_rating'] > 3) {
    $errors[] = 'Please select a star rating.';
}
if ($input['reviewer_name'] === '' || str_len($input['reviewer_name']) > 100) {
    $errors[] = 'Please enter your name (100 characters max).';
}
if (!filter_var($input['reviewer_email'], FILTER_VALIDATE_EMAIL) || str_len($input['reviewer_email']) > 150) {
    $errors[] = 'Please enter a valid email address.';
}
if (str_len($input['reviewer_phone']) > 50) {
    $errors[] = 'Phone number is too long.';
}
if ($input['feedback_text'] === '' || str_len($input['feedback_text']) > 5000) {
    $errors[] = 'Please enter your comments (5,000 characters max).';
}

if ($errors) {
    $_SESSION['review_flash'] = ['errors' => $errors, 'old' => $input];
    redirect($backUrl);
}

// ---- Save ----
try {
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT id, property_name, notification_email FROM properties WHERE id = ?');
    $stmt->execute([$propertyId]);
    $property = $stmt->fetch();

    if (!$property) {
        redirect('index.php');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO captured_reviews
            (property_id, star_rating, reviewer_name, reviewer_email, reviewer_phone, feedback_text)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $property['id'],
        $input['star_rating'],
        $input['reviewer_name'],
        $input['reviewer_email'],
        $input['reviewer_phone'] !== '' ? $input['reviewer_phone'] : null,
        $input['feedback_text'],
    ]);
} catch (PDOException $ex) {
    error_log('submit-review.php: ' . $ex->getMessage());
    $_SESSION['review_flash'] = [
        'errors' => ['We could not save your feedback right now. Please try again in a moment.'],
        'old'    => $input,
    ];
    redirect($backUrl);
}

// ---- Email alert (a mail failure never blocks the resident; the review is already saved) ----
$recipients = array_filter(
    parse_email_list($property['notification_email']),
    static fn($addr) => filter_var($addr, FILTER_VALIDATE_EMAIL)
);

if ($recipients) {
    $clean = static fn(string $s): string => trim(preg_replace('/[\r\n]+/', ' ', $s));

    $subject = $clean('[' . $property['property_name'] . '] New ' . $input['star_rating'] . '-star feedback from ' . $input['reviewer_name']);
    $body = implode("\r\n", [
        'A resident submitted private feedback.',
        '',
        'Property:    ' . $property['property_name'],
        'Star rating: ' . $input['star_rating'] . ' of 5  ' . star_string($input['star_rating']),
        'Submitted:   ' . date('M j, Y g:i A'),
        '',
        'Name:  ' . $input['reviewer_name'],
        'Email: ' . $input['reviewer_email'],
        'Phone: ' . ($input['reviewer_phone'] !== '' ? $input['reviewer_phone'] : '(not provided)'),
        '',
        'Feedback:',
        str_replace(["\r\n", "\r", "\n"], "\r\n", $input['feedback_text']),
        '',
    ]);
    $headers = implode("\r\n", [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . $clean($input['reviewer_email']),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ]);

    $sent = @mail(
        implode(', ', $recipients),
        '=?UTF-8?B?' . base64_encode($subject) . '?=',
        $body,
        $headers
    );
    if (!$sent) {
        error_log('submit-review.php: mail() failed for property #' . $property['id']);
    }
}

$_SESSION['review_flash'] = ['thanks' => true];
redirect($backUrl);
