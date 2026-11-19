<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

render_page('Auth/Register', [], 'Daftar Akun');
