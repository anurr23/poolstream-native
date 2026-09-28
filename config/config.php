<?php
// Konfigurasi aplikasi
define('BASE_URL', 'http://localhost/poolstream_new/');
define('APP_NAME', 'PoolStream');

// Set timezone sesuai poolstream
date_default_timezone_set('Asia/Jakarta');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload class models / config
spl_autoload_register(function ($class_name) {
    $paths = [
        __DIR__ . '/../models/' . $class_name . '.php',
        __DIR__ . '/../config/' . $class_name . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Require DB
require_once __DIR__ . '/database.php';

// Auth helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $allowedRoles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}
