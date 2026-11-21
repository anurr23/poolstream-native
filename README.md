# PoolStream Native

Aplikasi manajemen pool hall / biliar dengan backend **PHP native** (tanpa framework)
dan frontend **Vue 3** (Vite). Struktur halaman mengikuti project `poolstream` (Laravel
+ Inertia + Vue) agar UI/UX dan alur kerjanya sama.

## Menjalankan

1. Salin `config/credentials.example.php` menjadi `config/credentials.php` lalu sesuaikan nilainya:
   ```php
   <?php
   return [
       'app' => ['debug' => true],
       'db' => ['host' => 'host.docker.internal', 'port' => '3306', 'name' => 'poolstream', 'user' => 'root', 'pass' => 'root123'],
       'google' => ['client_id' => '', 'client_secret' => '', 'redirect' => 'http://localhost/poolstream-native/google-auth.php?action=callback'],
       'ai' => ['base_url' => 'http://host.docker.internal:20128/v1', 'api_key' => '', 'model' => 'ag/gemini-3.8-flash-high', 'timeout' => 0, 'max_tokens' => 16384],
       'mail' => ['driver' => 'smtp', 'from' => 'emailanda@gmail.com', 'from_name' => 'PoolStream', 'smtp' => ['host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'emailanda@gmail.com', 'password' => 'APP_PASSWORD']],
   ];
   ```
2. `composer install` (Dompdf untuk PDF Evaluasi AI)
3. `npm install`
4. `npm run build`
5. Buka `http://localhost/poolstream-native/`

## Scheduler

Jalankan setiap menit (cron / Task Scheduler):

```sh
php scripts/auto-checkout.php
```

Ini menutup sesi meja yang kedaluwarsa dan membatalkan booking no-show, sama seperti
`php artisan tables:auto-checkout` di project asli.

## AI Evaluation

Proses AI dijalankan di background oleh `scripts/ai-worker.php`:

```sh
php scripts/ai-worker.php <evaluation-id>
```

Endpoint AI memakai format OpenAI-compatible (`AI_BASE_URL`, `AI_API_KEY`, `AI_MODEL`).
PDF memakai `dompdf` (Composer).
