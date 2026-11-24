-- =============================================================================
-- PoolStream Native - Full Database Migration
-- -----------------------------------------------------------------------------
-- Membuat database `poolstream`, seluruh tabel aplikasi, relasi/index, dan
-- master data awal (user, paket biliar, meja, serta 27 menu F&B).
--
-- Cara import:
--   mysql -u root -p < database/poolstream_full.sql
-- atau buka file ini di phpMyAdmin lalu klik Import.
--
-- File ini idempotent:
--   - Tabel dibuat dengan CREATE TABLE IF NOT EXISTS.
--   - Master data memakai INSERT IGNORE sehingga aman dijalankan ulang.
--
-- Untuk reset total (menghapus semua data lama), jalankan dulu:
--   DROP DATABASE IF EXISTS `poolstream`;
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `poolstream`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `poolstream`;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- =============================================================================
-- 1. TABEL APLIKASI
-- =============================================================================

-- -----------------------------------------------------------------------------
-- users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id` char(36) NOT NULL,
    `name` varchar(255) NOT NULL,
    `username` varchar(255) NOT NULL,
    `email` varchar(255) DEFAULT NULL,
    `email_verified_at` timestamp NULL DEFAULT NULL,
    `whatsapp` varchar(30) DEFAULT NULL,
    `role` varchar(20) NOT NULL DEFAULT 'kasir',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `password` varchar(255) NOT NULL,
    `photo_path` varchar(255) DEFAULT NULL,
    `google_id` varchar(255) DEFAULT NULL,
    `otp_code` varchar(255) DEFAULT NULL,
    `otp_expires_at` timestamp NULL DEFAULT NULL,
    `otp_attempts` tinyint unsigned NOT NULL DEFAULT 0,
    `remember_token` varchar(100) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_username_unique` (`username`),
    UNIQUE KEY `users_email_unique` (`email`),
    UNIQUE KEY `users_whatsapp_unique` (`whatsapp`),
    UNIQUE KEY `users_google_id_unique` (`google_id`),
    KEY `users_role_is_active_index` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- tables (meja biliar)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tables` (
    `id` char(36) NOT NULL,
    `name` varchar(255) NOT NULL,
    `relay_channel` int NOT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'inactive',
    `is_light_on` tinyint(1) NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tables_relay_channel_unique` (`relay_channel`),
    KEY `tables_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- packages (paket biliar)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `packages` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `price` decimal(15,2) NOT NULL,
    `start_time` time DEFAULT NULL,
    `end_time` time DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `packages_price_index` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- fnb_items (menu makanan & minuman)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fnb_items` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `category` varchar(255) DEFAULT NULL,
    `price` decimal(15,2) NOT NULL,
    `image_path` varchar(255) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `fnb_items_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- transactions (sesi biliar, booking, dan pesanan F&B)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `receipt_no` varchar(30) DEFAULT NULL,
    `type` varchar(20) NOT NULL DEFAULT 'billiard',
    `table_id` char(36) DEFAULT NULL,
    `package_id` bigint unsigned DEFAULT NULL,
    `user_id` char(36) DEFAULT NULL,
    `customer_name` varchar(255) NOT NULL,
    `start_time` datetime NOT NULL,
    `end_time` datetime DEFAULT NULL,
    `expected_end_time` datetime DEFAULT NULL,
    `billiard_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
    `fnb_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
    `total_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
    `status` varchar(20) NOT NULL DEFAULT 'active',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `transactions_receipt_no_unique` (`receipt_no`),
    KEY `transactions_table_status_index` (`table_id`, `status`),
    KEY `transactions_type_status_start_index` (`type`, `status`, `start_time`),
    KEY `transactions_user_id_index` (`user_id`),
    KEY `transactions_package_id_index` (`package_id`),
    KEY `transactions_created_at_index` (`created_at`),
    CONSTRAINT `fk_transactions_table_id`
        FOREIGN KEY (`table_id`) REFERENCES `tables` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transactions_package_id`
        FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_transactions_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- transaction_items (detail F&B per transaksi)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `transaction_id` bigint unsigned NOT NULL,
    `fnb_item_id` bigint unsigned NOT NULL,
    `quantity` int NOT NULL,
    `price` decimal(15,2) NOT NULL,
    `subtotal` decimal(15,2) NOT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transaction_items_transaction_id_index` (`transaction_id`),
    KEY `transaction_items_fnb_item_id_index` (`fnb_item_id`),
    CONSTRAINT `fk_transaction_items_transaction_id`
        FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_transaction_items_fnb_item_id`
        FOREIGN KEY (`fnb_item_id`) REFERENCES `fnb_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- ai_evaluations (riwayat evaluasi AI)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_evaluations` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` char(36) DEFAULT NULL,
    `preset` varchar(40) DEFAULT NULL,
    `start_date` date NOT NULL,
    `end_date` date NOT NULL,
    `bucket` varchar(10) NOT NULL DEFAULT 'day',
    `sections` json NOT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'running',
    `model` varchar(255) DEFAULT NULL,
    `payload` json DEFAULT NULL,
    `result` json DEFAULT NULL,
    `prompt_tokens` int unsigned DEFAULT NULL,
    `completion_tokens` int unsigned DEFAULT NULL,
    `duration_ms` int unsigned DEFAULT NULL,
    `error` text DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ai_evaluations_date_range_index` (`start_date`, `end_date`),
    KEY `ai_evaluations_status_index` (`status`),
    KEY `ai_evaluations_user_id_index` (`user_id`),
    CONSTRAINT `fk_ai_evaluations_user_id`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- send_wa_api (antrean pengiriman struk WhatsApp)
-- Dibaca oleh worker terpisah (project wa-bot).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `send_wa_api` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `transaction_id` bigint unsigned DEFAULT NULL,
    `no_destination` varchar(30) NOT NULL,
    `message` text NOT NULL,
    `file` varchar(255) DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `send_wa_api_status_index` (`status`),
    KEY `send_wa_api_transaction_id_index` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. TABEL PENDUKUNG (opsional, bawaan Laravel)
-- Tidak dipakai oleh aplikasi native, aman dibiarkan atau dihapus.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` varchar(255) NOT NULL,
    `token` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
    `id` varchar(255) NOT NULL,
    `user_id` char(36) DEFAULT NULL,
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `payload` longtext NOT NULL,
    `last_activity` int NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
    `key` varchar(255) NOT NULL,
    `value` mediumtext NOT NULL,
    `expiration` bigint NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` varchar(255) NOT NULL,
    `owner` varchar(255) NOT NULL,
    `expiration` bigint NOT NULL,
    PRIMARY KEY (`key`),
    KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `queue` varchar(255) NOT NULL,
    `payload` longtext NOT NULL,
    `attempts` smallint unsigned NOT NULL,
    `reserved_at` int unsigned DEFAULT NULL,
    `available_at` int unsigned NOT NULL,
    `created_at` int unsigned NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` varchar(255) NOT NULL,
    `name` varchar(255) NOT NULL,
    `total_jobs` int NOT NULL,
    `pending_jobs` int NOT NULL,
    `failed_jobs` int NOT NULL,
    `failed_job_ids` longtext NOT NULL,
    `options` mediumtext DEFAULT NULL,
    `cancelled_at` int DEFAULT NULL,
    `created_at` int NOT NULL,
    `finished_at` int DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `uuid` varchar(255) NOT NULL,
    `connection` varchar(255) NOT NULL,
    `queue` varchar(255) NOT NULL,
    `payload` longtext NOT NULL,
    `exception` longtext NOT NULL,
    `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
    KEY `failed_jobs_connection_queue_failed_at_index` (`connection`, `queue`, `failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. MASTER DATA
-- Password semua akun demo: "password"
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Users
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `users`
    (`id`, `name`, `username`, `email`, `email_verified_at`, `whatsapp`, `role`, `is_active`, `password`, `photo_path`, `created_at`, `updated_at`)
VALUES
    ('00000000-0000-4000-8000-000000000001', 'Admin System', 'admin', 'admin@poolstream.test', NOW(), NULL, 'admin', 1, '$2y$12$WMPQQ7LFdKJgSydfUHB8Y.yAKPrr2ozpsdmFYl.4gis3RAC5gfFMi', 'user_photos/admin.svg', NOW(), NOW()),
    ('00000000-0000-4000-8000-000000000002', 'Kasir 1', 'kasir', 'kasir@poolstream.test', NOW(), NULL, 'kasir', 1, '$2y$12$WMPQQ7LFdKJgSydfUHB8Y.yAKPrr2ozpsdmFYl.4gis3RAC5gfFMi', 'user_photos/kasir.svg', NOW(), NOW()),
    ('00000000-0000-4000-8000-000000000003', 'Customer Demo', 'customer', 'customer@poolstream.test', NOW(), NULL, 'customer', 1, '$2y$12$WMPQQ7LFdKJgSydfUHB8Y.yAKPrr2ozpsdmFYl.4gis3RAC5gfFMi', NULL, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- Packages
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `packages` (`id`, `name`, `price`, `start_time`, `end_time`, `created_at`, `updated_at`)
VALUES
    (1, 'Regular Siang', 25000.00, NULL, NULL, NOW(), NOW()),
    (2, 'Regular Malam', 35000.00, NULL, NULL, NOW(), NOW()),
    (3, 'VIP', 50000.00, NULL, NULL, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- Tables (Meja 1 - 16)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `tables` (`id`, `name`, `relay_channel`, `status`, `is_light_on`, `created_at`, `updated_at`)
VALUES
    ('10000000-0000-4000-8000-000000000001', 'Meja 1', 1, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000002', 'Meja 2', 2, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000003', 'Meja 3', 3, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000004', 'Meja 4', 4, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000005', 'Meja 5', 5, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000006', 'Meja 6', 6, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000007', 'Meja 7', 7, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000008', 'Meja 8', 8, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000009', 'Meja 9', 9, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000010', 'Meja 10', 10, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000011', 'Meja 11', 11, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000012', 'Meja 12', 12, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000013', 'Meja 13', 13, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000014', 'Meja 14', 14, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000015', 'Meja 15', 15, 'inactive', 0, NOW(), NOW()),
    ('10000000-0000-4000-8000-000000000016', 'Meja 16', 16, 'inactive', 0, NOW(), NOW());

-- -----------------------------------------------------------------------------
-- F&B Items (27 menu, gambar tersedia di storage/fnb_images/)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `fnb_items` (`id`, `name`, `category`, `price`, `image_path`, `created_at`, `updated_at`)
VALUES
    -- Minuman Dingin
    (1,  'Es Teh Manis',        'Minuman Dingin', 8000.00,  'fnb_images/es-teh-manis.svg',       NOW(), NOW()),
    (2,  'Es Teh Tawar',        'Minuman Dingin', 5000.00,  'fnb_images/es-teh-tawar.svg',       NOW(), NOW()),
    (3,  'Es Jeruk Segar',      'Minuman Dingin', 12000.00, 'fnb_images/es-jeruk-segar.svg',     NOW(), NOW()),
    (4,  'Es Lemon Tea',        'Minuman Dingin', 15000.00, 'fnb_images/es-lemon-tea.svg',       NOW(), NOW()),
    (5,  'Es Milo',             'Minuman Dingin', 15000.00, 'fnb_images/es-milo.svg',            NOW(), NOW()),
    (6,  'Es Good Day',         'Minuman Dingin', 12000.00, 'fnb_images/es-good-day.svg',        NOW(), NOW()),
    (7,  'Es Nutrisari',        'Minuman Dingin', 10000.00, 'fnb_images/es-nutrisari.svg',       NOW(), NOW()),
    (8,  'Air Mineral',         'Minuman Dingin', 5000.00,  'fnb_images/air-mineral.svg',        NOW(), NOW()),
    -- Minuman Panas
    (9,  'Kopi Tubruk',         'Minuman Panas',  8000.00,  'fnb_images/kopi-tubruk.svg',        NOW(), NOW()),
    (10, 'Kopi Susu',           'Minuman Panas',  15000.00, 'fnb_images/kopi-susu.svg',          NOW(), NOW()),
    (11, 'Teh Panas',           'Minuman Panas',  5000.00,  'fnb_images/teh-panas.svg',          NOW(), NOW()),
    (12, 'Kapal Api',           'Minuman Panas',  8000.00,  'fnb_images/kapal-api.svg',          NOW(), NOW()),
    (13, 'Indocafe',            'Minuman Panas',  8000.00,  'fnb_images/indocafe.svg',           NOW(), NOW()),
    -- Snack
    (14, 'Kentang Goreng',      'Snack',          18000.00, 'fnb_images/kentang-goreng.svg',     NOW(), NOW()),
    (15, 'Pisang Goreng',       'Snack',          12000.00, 'fnb_images/pisang-goreng.svg',      NOW(), NOW()),
    (16, 'Roti Bakar',          'Snack',          15000.00, 'fnb_images/roti-bakar.svg',         NOW(), NOW()),
    (17, 'French Fries',        'Snack',          20000.00, 'fnb_images/french-fries.svg',       NOW(), NOW()),
    (18, 'Chicken Wings',       'Snack',          25000.00, 'fnb_images/chicken-wings.svg',      NOW(), NOW()),
    (19, 'Nugget Goreng',       'Snack',          18000.00, 'fnb_images/nugget-goreng.svg',      NOW(), NOW()),
    -- Makanan
    (20, 'Nasi Goreng',         'Makanan',        22000.00, 'fnb_images/nasi-goreng.svg',        NOW(), NOW()),
    (21, 'Mie Goreng',          'Makanan',        20000.00, 'fnb_images/mie-goreng.svg',         NOW(), NOW()),
    (22, 'Mie Rebus',           'Makanan',        20000.00, 'fnb_images/mie-rebus.svg',          NOW(), NOW()),
    (23, 'Nasi Ayam Goreng',    'Makanan',        25000.00, 'fnb_images/nasi-ayam-goreng.svg',   NOW(), NOW()),
    (24, 'Nasi Rendang',        'Makanan',        30000.00, 'fnb_images/nasi-rendang.svg',       NOW(), NOW()),
    (25, 'Gado-gado',           'Makanan',        18000.00, 'fnb_images/gado-gado.svg',          NOW(), NOW()),
    (26, 'Sate Ayam (10 tusuk)','Makanan',        25000.00, 'fnb_images/sate-ayam-10-tusuk.svg', NOW(), NOW()),
    (27, 'Sosis Goreng',        'Makanan',        15000.00, 'fnb_images/sosis-goreng.svg',       NOW(), NOW());

-- =============================================================================
-- 4. VERIFIKASI
-- =============================================================================
SELECT
    'Migrasi selesai' AS `status`,
    (SELECT COUNT(*) FROM `users`)      AS `users`,
    (SELECT COUNT(*) FROM `packages`)   AS `packages`,
    (SELECT COUNT(*) FROM `tables`)     AS `tables`,
    (SELECT COUNT(*) FROM `fnb_items`)  AS `fnb_items`;
