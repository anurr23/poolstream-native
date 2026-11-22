<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role(['admin', 'kasir']);

$user = current_user();
$role = (string) ($user['role'] ?? 'kasir');

render_page('Menu', [
    'role' => $role,
    'displayName' => (string) ($user['name'] ?? ''),
], 'Menu Utama');
