<?php
declare(strict_types=1);

/**
 * Contoh konfigurasi aplikasi PoolStream.
 * Salin file ini menjadi config/credentials.php dan sesuaikan nilainya.
 * Jangan commit config/credentials.php ke repository.
 */
return [
    'app' => [
        'debug' => true,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'poolstream',
        'user' => 'root',
        'pass' => '',
    ],
    'google' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect' => 'http://localhost/poolstream-native/google-auth.php?action=callback',
    ],
    'ai' => [
        'base_url' => 'http://127.0.0.1:20128/v1',
        'api_key' => '',
        'model' => 'ag/gemini-3.8-flash-high',
        'timeout' => 0,
        'max_tokens' => 16384,
    ],
    'mail' => [
        // 'log' (storage/logs/mail.log), 'smtp' (Gmail/SMTP), atau 'mail' (PHP mail())
        'driver' => 'smtp',
        'from' => 'muhammad.anurohim.fict@krw.horizon.ac.id',
        'from_name' => 'PoolStream',
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587, // 587 (tls) atau 465 (ssl)
            'encryption' => 'tls', // 'tls', 'ssl', atau 'none'
            'username' => 'muhammad.anurohim.fict@krw.horizon.ac.id',
            'password' => 'qjpj mnyj fzxa bzij', // Gunakan App Password Google (16 karakter)
            'timeout' => 15,
        ],
    ],
];
