<?php
/**
 * Minimal centred-card layout for the signed-out pages (login, forgot / reset password).
 */
function plain_header(string $title): void
{
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= e($title) ?> — Review System</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin">
<div class="login-wrap">
    <div class="box">
<?php
}

function plain_footer(): void
{
    ?>
    </div>
</div>
</body>
</html>
<?php
}
