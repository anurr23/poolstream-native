<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect('index.php');
}

$draft = $_SESSION['_register_draft'] ?? null;
unset($_SESSION['_register_draft']);

render_page('Auth/Register', [
    'draft' => is_array($draft) ? $draft : null,
], 'Daftar Akun');
