<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_login();
require_customer_verified();
$user = current_user();
if (!$user) {
    redirect('login.php');
}

$txModel = new Transaction();
$txModel->autoCheckoutExpired();

render_page('Customer/History', [
    'bookings' => $txModel->bookingsForUser((string) $user['id'], 100),
], 'Riwayat Booking');
