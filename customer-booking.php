<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_login();
require_customer_verified();
$user = current_user();
if (!$user) {
    redirect('login.php');
}

render_page('Customer/Booking', [
    'packages' => (new Package())->all(),
    'today' => date('Y-m-d'),
    'maxDate' => date('Y-m-d', strtotime('+30 days')),
], 'Booking Meja');
