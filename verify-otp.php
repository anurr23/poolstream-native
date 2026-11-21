<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$user = current_user();
if ($user) {
    if ($user['role'] !== 'customer' || !empty($user['email_verified_at'])) {
        redirect($user['role'] === 'customer' ? 'customer-home.php' : 'index.php');
    }

    $cooldown = max(0, 60 - (time() - (int) ($_SESSION['otp_resend_at'] ?? 0)));
    render_page('Auth/VerifyOtp', [
        'email' => (string) $user['email'],
        'resendCooldown' => $cooldown,
        'expiresMinutes' => OtpService::EXPIRES_MINUTES,
    ], 'Verifikasi Email');
    exit;
}

$pending = $_SESSION['pending_registration'] ?? null;
if (!is_array($pending) || empty($pending['email'])) {
    redirect('register.php');
}

$cooldown = max(0, (int) ($pending['resend_available_at'] ?? 0) - time());
render_page('Auth/VerifyOtp', [
    'email' => (string) $pending['email'],
    'resendCooldown' => $cooldown,
    'expiresMinutes' => OtpService::EXPIRES_MINUTES,
], 'Verifikasi Email');
