<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$count = (new Transaction())->autoCheckoutExpired();
echo '[' . now() . '] Auto checkout selesai. Transaksi aktif yang ditutup: ' . $count . PHP_EOL;
