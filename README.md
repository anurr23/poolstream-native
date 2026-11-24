# PoolStream Native

Aplikasi manajemen pool hall / biliar dengan backend **PHP native** (tanpa framework)
dan frontend **Vue 3** (Vite). Struktur halaman mengikuti project `poolstream` (Laravel
+ Inertia + Vue) agar UI/UX dan alur kerjanya sama.

## Menjalankan

1. Salin `.env.example` menjadi `.env`, lalu sesuaikan nilainya:
   ```env
   APP_NAME=PoolStream
   APP_DEBUG=true
   APP_TIMEZONE=Asia/Jakarta

   DB_HOST=host.docker.internal
   DB_PORT=3306
   DB_NAME=poolstream
   DB_USER=root
   DB_PASSWORD=your_password
   DB_TIMEZONE=+07:00
   DB_TIMEOUT=3

   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   GOOGLE_REDIRECT=http://localhost/poolstream-native/google-auth.php?action=callback

   AI_BASE_URL=http://host.docker.internal:20128/v1
   AI_API_KEY=
   AI_MODEL=ag/gemini-3.8-flash-high
   AI_TIMEOUT=0
   AI_MAX_TOKENS=16384

   MAIL_DRIVER=smtp
   MAIL_FROM=emailanda@gmail.com
   MAIL_FROM_NAME=PoolStream
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_ENCRYPTION=tls
   MAIL_USERNAME=emailanda@gmail.com
   MAIL_PASSWORD=APP_PASSWORD
   MAIL_TIMEOUT=15
   ```
   Semua konfigurasi dibaca langsung dari `.env` tanpa fallback.
2. Jalankan installer database (paling mudah):
   - Buka `http://localhost/poolstream-native/install.php`, lalu klik **Install / Migrate**.
   - Atau lewat terminal: `php install.php`
   - Alternatif import SQL manual: `mysql -u root -p < database/poolstream_full.sql`

   Detail ada di `database/README.md`.
3. `composer install` (Dompdf untuk PDF Evaluasi AI)
4. `npm install`
5. `npm run build`
6. Buka `http://localhost/poolstream-native/`

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
