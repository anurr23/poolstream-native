<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user['role'] === 'customer') {
        redirect(empty($user['email_verified_at']) ? 'verify-otp.php' : 'customer-home.php');
    }
    redirect($user['role'] === 'admin' ? 'index.php' : 'dashboard.php');
}

$status = $_SESSION['_flash']['success'] ?? null;
render_page('Auth/Login', [
    'canResetPassword' => true,
    'status' => $status,
], 'Log in');
