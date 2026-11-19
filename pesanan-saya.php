<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_login();
if ((current_user()['role'] ?? '') === 'customer') {
    redirect('customer-history.php');
}
redirect('dashboard.php');
