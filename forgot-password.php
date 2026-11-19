<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$status = $_SESSION['_flash']['success'] ?? null;
render_page('Auth/ForgotPassword', ['status' => $status], 'Lupa Password');
