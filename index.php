<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

// Customer tidak memakai landing page. Setelah login mereka diarahkan langsung
// ke beranda customer (atau verifikasi OTP bila email belum terverifikasi).
$user = current_user();
if ($user && $user['role'] === 'customer') {
    redirect(empty($user['email_verified_at']) ? 'verify-otp.php' : 'customer-home.php');
}

render_page('Landing', [], 'Sistem Manajemen Pool');
