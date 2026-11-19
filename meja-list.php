<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in() && (current_user()['role'] ?? '') === 'customer') {
    redirect('customer-booking.php');
}
redirect('index.php');
