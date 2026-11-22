<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

// Landing page adalah halaman publik. Customer yang sudah login langsung
// diarahkan ke beranda customer (atau verifikasi OTP bila belum terverifikasi).
$user = current_user();
if ($user && $user['role'] === 'customer') {
    redirect(empty($user['email_verified_at']) ? 'verify-otp.php' : 'customer-home.php');
}

$packages = [];
$totalTables = 0;
$availableNow = 0;

try {
    $txModel = new Transaction();
    $txModel->autoCheckoutExpired();

    $tables = (new Table())->all();
    $totalTables = count($tables);
    foreach ($tables as $table) {
        if (($table['status'] ?? '') !== 'active') {
            $availableNow++;
        }
    }
    $packages = (new Package())->all();
} catch (Throwable $e) {
    // Landing tetap tampil walau database sedang tidak tersedia.
    error_log('[PoolStream Landing] ' . $e->getMessage());
}

render_page('Landing', [
    'packages' => $packages,
    'totalTables' => $totalTables,
    'availableNow' => $availableNow,
], 'Billiard & Lounge');
