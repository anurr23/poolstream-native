<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_login();
$user = current_user();
if (!$user || $user['role'] !== 'customer' || !empty($user['email_verified_at'])) {
    redirect($user && $user['role'] === 'customer' ? 'customer-home.php' : 'index.php');
}

render_page('Auth/VerifyOtp', [
    'email' => $user['email'],
    'mailLogMode' => MailService::driver() === 'log',
], 'Verifikasi Email');
