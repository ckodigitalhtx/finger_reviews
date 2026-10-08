<?php
/**
 * Add / edit a property.
 */
require_once __DIR__ . '/auth.php';

$id       = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: null;

// Property users can only edit their own property, and cannot add new ones
if (!is_super() && $id !== scoped_property_id()) {
    redirect('property-edit.php?id=' . scoped_property_id());
}
$listUrl = is_super() ? 'properties.php' : 'property-edit.php?id=' . scoped_property_id();
$errors   = [];
$linkCols = array_keys(review_platforms());

$currentLogo = null;
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
    $currentLogo = $row['logo_path'] ?? null;
}

// ---- Save ----
$postTooLarge = $_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;

if ($postTooLarge) {
    $errors[] = 'The uploaded file is too large for this server. Please use a logo under 2 MB.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Logo: upload only once the rest of the form is valid, so no orphan files are left behind
    $newLogo    = null;
    $removeLogo = !empty($_POST['remove_logo']);
    if (!$errors) {
        [$newLogo, $uploadError] = handle_logo_upload('logo');
        if ($uploadError) {
            $errors[] = $uploadError;
        }
    }

    if (!$errors) {
        $logoValue = $newLogo ?? ($removeLogo ? null : $currentLogo);

        $columns = array_merge(array_keys($property), ['logo_path']);
        $values  = array_map(static fn($v) => $v === '' ? null : $v, array_values($property));
        $values[] = $logoValue;

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
            if ($currentLogo && $currentLogo !== $logoValue) {
                delete_logo_file($currentLogo);   // replaced or removed
            }
            redirect($listUrl);
        } catch (PDOException $ex) {
            delete_logo_file($newLogo);   // don't keep a file the database doesn't know about
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

<form method="post" class="form box box-narrow" enctype="multipart/form-data">
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

    <div class="logo-field">
        <span class="field-label">Logo <span class="optional">(optional)</span></span>
        <?php if ($currentLogo): ?>
            <div class="logo-current">
                <img src="<?= e(logo_url($currentLogo, '../')) ?>" alt="Current logo for <?= e($property['property_name']) ?>">
                <label class="checkbox"><input type="checkbox" name="remove_logo" value="1"> Remove this logo</label>
            </div>
        <?php endif; ?>
        <label><?= $currentLogo ? 'Replace with a new file' : 'Upload a file' ?>
            <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
            <span class="hint">PNG, JPG, GIF or WebP, up to 2 MB. Shown at the top of the review page. A transparent PNG about 600 px wide looks best.</span>
        </label>
    </div>

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
        <a class="btn btn-secondary" href="<?= e(is_super() ? 'properties.php' : 'index.php') ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/footer.php'; ?>
