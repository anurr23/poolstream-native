<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_login();
require_customer_verified();
$user = current_user();
if (!$user || $user['role'] !== 'customer') {
    redirect('index.php');
}

$txModel = new Transaction();
$txModel->autoCheckoutExpired();
$activeTableIds = fetch_all(
    "SELECT DISTINCT table_id FROM transactions
     WHERE type = 'billiard' AND status = 'active' AND expected_end_time > NOW()"
);

render_page('Customer/Home', [
    'packages' => (new Package())->all(),
    'nextBooking' => $txModel->nextForUser((string) $user['id']),
    'totalTables' => (int) fetch_value('SELECT COUNT(*) FROM tables'),
    'availableNow' => max(0, (int) fetch_value('SELECT COUNT(*) FROM tables') - count($activeTableIds)),
], 'Beranda');
