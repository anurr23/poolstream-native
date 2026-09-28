<?php
require_once __DIR__ . '/config/config.php';

// Jika sudah login arahkan ke dashboard, jika belum arahkan ke login
if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
