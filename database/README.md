# Database

## Konfigurasi .env

Semua konfigurasi aplikasi dibaca dari `.env` di root project. Sebelum instalasi:

```sh
copy .env.example .env
```

Sesuaikan terutama:

```env
DB_HOST=host.docker.internal
DB_PORT=3306
DB_NAME=poolstream
DB_USER=root
DB_PASSWORD=
DB_TIMEZONE=+07:00
DB_TIMEOUT=3
```

Tidak ada fallback di file config lain. Jika variabel tidak ada, aplikasi akan
menampilkan error yang menyebut nama variabel tersebut.

Ada dua cara memasang database PoolStream Native:

## 1. Installer (paling mudah)

Buka di browser:

```
http://localhost/poolstream-native/install.php
```

Lalu klik **Install / Migrate**. Installer akan:

- membaca koneksi dari `.env`
- membuat database sesuai `DB_NAME`
- membuat semua tabel, index, dan foreign key
- mengisi master data awal
- menampilkan ringkasan jumlah data

Lewat terminal juga bisa:

```sh
php install.php
```

Reset total (hapus semua data lalu install ulang):

```sh
php install.php --reset
```

Di browser, reset tersedia di bagian **Reset total** dan wajib mengetik `RESET`.

> Hapus `install.php` setelah instalasi selesai.

## 2. Import SQL manual

File `poolstream_full.sql` berisi migrasi total yang sama:

```sh
mysql -u root -p < database/poolstream_full.sql
```

Atau buka `database/poolstream_full.sql` di phpMyAdmin lalu **Import**.

Untuk reset total:

```sh
mysql -u root -p -e "DROP DATABASE IF EXISTS poolstream;"
mysql -u root -p < database/poolstream_full.sql
```

## Master data

| Data | Jumlah | Keterangan |
| --- | ---: | --- |
| Users | 3 | admin, kasir, customer |
| Packages | 3 | Regular Siang, Regular Malam, VIP |
| Tables | 16 | Meja 1 - 16 (relay channel 1 - 16) |
| F&B Items | 27 | 8 minuman dingin, 5 minuman panas, 6 snack, 8 makanan |

Akun demo (password semuanya `password`):

| Role | Username | Email |
| --- | --- | --- |
| Admin | `admin` | `admin@poolstream.test` |
| Kasir | `kasir` | `kasir@poolstream.test` |
| Customer | `customer` | `customer@poolstream.test` |

## Gambar master data

Semua gambar sudah ada di repository, tidak perlu di-seed ulang:

- Foto user: `storage/user_photos/admin.svg`, `storage/user_photos/kasir.svg`
- 27 gambar menu F&B: `storage/fnb_images/*.svg`
- `image_path` pada tabel `fnb_items` mengarah ke path relatif tersebut.

File SQL aman dijalankan ulang: tabel memakai `CREATE TABLE IF NOT EXISTS` dan
master data memakai `INSERT IGNORE`.
