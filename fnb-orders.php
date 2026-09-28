<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

// Bridge URL fnb-orders.php -> kasir.php
require __DIR__ . '/kasir.php';
