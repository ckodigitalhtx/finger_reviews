<?php
/**
 * Include at the very top of every protected admin page.
 * Starts the session and bounces anyone who is not signed in to the login form.
 */
require_once __DIR__ . '/../config/functions.php';
start_session();

if (empty($_SESSION['admin_logged_in'])) {
    redirect('login.php');
}

/** One-time message shown on the next page load. */
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['admin_flash'] = ['message' => $message, 'type' => $type];
}
