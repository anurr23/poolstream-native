<?php
require_once __DIR__ . '/config/config.php';

// Hapus cookie remember me jika ada
if (isset($_COOKIE['poolstream_remember'])) {
    setcookie('poolstream_remember', '', time() - 3600, "/");
}

// Hapus semua session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect ke login
header('Location: login.php');
exit();
