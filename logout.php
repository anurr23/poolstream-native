<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'POST') {
    verify_csrf();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

if (is_ajax()) {
    json_ok([], null, 'index.php');
}

redirect('index.php');
