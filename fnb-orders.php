<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role(['admin', 'kasir']);

$txModel = new Transaction();
$txModel->autoCheckoutExpired();

render_page('Fnb/Index', [
    'fnbItems' => (new FnbItem())->all(),
    'fnbOrders' => $txModel->getActiveFnbOrders(),
    'fnbHistory' => $txModel->getFnbHistory(50),
], 'Pemesanan F&B');
