<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role(['admin', 'kasir']);

$txModel = new Transaction();
$txModel->autoCheckoutExpired();

$tables = (new Table())->allWithActiveAndRecentTransactions();
$packages = (new Package())->all();
$fnbItems = (new FnbItem())->all();
$upcomingBookings = $txModel->upcomingBookings();

render_page('Dashboard', [
    'tables' => $tables,
    'packages' => $packages,
    'fnbItems' => $fnbItems,
    'upcomingBookings' => $upcomingBookings,
], 'Dashboard');
