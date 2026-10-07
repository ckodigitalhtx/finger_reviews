<?php
/**
 * Add / edit a property.
 */
require_once __DIR__ . '/auth.php';

$id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;
$errors   = [];
$linkCols = array_keys(review_platforms());

$property = array_fill_keys(
    array_merge(['property_name', 'notification_email', 'custom_wording'], $linkCols),
    ''
);

// ---- Load existing ----
if ($id) {
    try {
        $stmt = db()->prepare('SELECT * FROM properties WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } catch (PDOException $ex) {
        error_log('admin/property-edit.php: ' . $ex->getMessage());
        $row = false;
    }
    if (!$row) {
        flash('Property not found.', 'error');
        redirect('properties.php');
    }
    foreach ($property as $key => $unused) {
        $property[$key] = (string)($row[$key] ?? '');
    }
}

// ---- Save ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($property as $key => $unused) {
        $property[$key] = post_str($key);
    }

    if (!csrf_valid()) {
        $errors[] = 'Your session expired. Please submit the form again.';
    }
    if ($property['property_name'] === '' || str_len($property['property_name']) > 150) {
        $errors[] = 'Property name is required (150 characters max).';
    }

    $emails = parse_email_list($property['notification_email']);
    $badEmails = array_filter($emails, static fn($a) => !filter_var($a, FILTER_VALIDATE_EMAIL));
    $property['notification_email'] = implode(', ', $emails);
    if (!$emails || $badEmails) {
        $errors[] = 'Enter at least one valid notification email (separate several with commas).';
    } elseif (str_len($property['notification_email']) > 150) {
        $errors[] = 'Notification emails are too long (150 characters max in total).';
    }

    if (str_len($property['custom_wording']) > 2000) {
        $errors[] = 'Custom wording is too long (2,000 characters max).';
    }

    foreach (review_platforms() as $col => $label) {
        if ($property[$col] === '') {
            continue;
        }
        if (!is_valid_url($property[$col])) {
            $errors[] = $label . ' link must be a full URL starting with http:// or https://';
        } elseif (strlen($property[$col]) > 255) {
            $errors[] = $label . ' link is too long (255 characters max).';
        }
    }

    if (!$errors) {
        $columns = array_keys($property);
        $values  = array_map(static fn($v) => $v === '' ? null : $v, array_values($property));

        try {
            if ($id) {
                $set  = implode(', ', array_map(static fn($c) => "$c = ?", $columns));
                $stmt = db()->prepare("UPDATE properties SET $set WHERE id = ?");
                $stmt->execute(array_merge($values, [$id]));
                flash('Property updated.');
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO properties (' . implode(', ', $columns) . ')
                     VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'
                );
                $stmt->execute($values);
                flash('Property added. Its review page link is listed below.');
            }
            redirect('properties.php');
        } catch (PDOException $ex) {
            error_log('admin/property-edit.php: ' . $ex->getMessage());
            $errors[] = 'Could not save the property. Please try again.';
        }
    }
}

$pageTitle = $id ? 'Edit property' : 'Add property';
require __DIR__ . '/header.php';
?>
<div class="page-head">
    <h1><?= e($pageTitle) ?></h1>
    <?php if ($id): ?>
        <a class="btn btn-secondary" href="<?= e(public_review_url($id)) ?>" target="_blank" rel="noopener">View review page</a>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="notice notice-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" class="form box box-narrow">
    <?= csrf_field() ?>

    <label>Property name
        <input type="text" name="property_name" required maxlength="150" value="<?= e($property['property_name']) ?>">
    </label>

    <label>Notification email(s)
        <input type="text" name="notification_email" required maxlength="150" value="<?= e($property['notification_email']) ?>">
        <span class="hint">Who gets alerted when 1–3 star feedback comes in. Separate several addresses with commas.</span>
    </label>

    <label>Custom wording <span class="optional">(optional)</span>
        <textarea name="custom_wording" rows="4" maxlength="2000"><?= e($property['custom_wording']) ?></textarea>
        <span class="hint">Greeting shown above the stars. Leave blank to use the default greeting.</span>
    </label>

    <h2>Review links</h2>
    <p class="muted small">Shown to residents who choose 4 or 5 stars. Only platforms with a link get a button.</p>

    <div class="form-grid">
        <?php foreach (review_platforms() as $col => $label): ?>
            <label><?= e($label) ?>
                <input type="url" name="<?= e($col) ?>" maxlength="255" placeholder="https://" value="<?= e($property[$col]) ?>">
            </label>
        <?php endforeach; ?>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $id ? 'Save changes' : 'Add property' ?></button>
        <a class="btn btn-secondary" href="properties.php">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/footer.php'; ?>
