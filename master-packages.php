<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role('admin');
render_page('Master/Packages', [
    'packages' => (new Package())->all(),
], 'Paket & Harga');
