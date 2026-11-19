<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$username = (string) ($_GET['username'] ?? '');
$token = (string) ($_GET['token'] ?? '');
render_page('Auth/ResetPassword', [
    'username' => $username,
    'token' => $token,
], 'Reset Password');
