<?php
// Nothing public lives at the site root; residents arrive via review.php?property_id=X
http_response_code(404);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Resident Feedback</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="public">
<main class="card">
    <h1>Resident Feedback</h1>
    <p class="lead">Please use the review link provided by your property's management team.</p>
</main>
</body>
</html>
