<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if (!$pdo instanceof PDO) {
        $pdo = (new Database())->getConnection();
    }
    return $pdo;
}

function uuid_v4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function to_iso(?string $datetime): ?string
{
    if (!$datetime) {
        return null;
    }
    $ts = strtotime($datetime);
    return $ts === false ? null : date('c', $ts);
}

function to_iso_utc(?string $datetime): ?string
{
    return to_iso($datetime);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function mask_name(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '-';
    }
    $parts = preg_split('/\s+/', $name) ?: [$name];
    if (count($parts) < 2) {
        return $parts[0];
    }
    return $parts[0] . ' ' . strtoupper(substr((string) end($parts), 0, 1)) . '.';
}

function normalize_money(mixed $value): float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }

    $value = trim((string) $value);
    if ($value === '') {
        return 0.0;
    }

    $value = str_replace(['Rp', 'rp', 'RP', ' '], '', $value);

    // Format Indonesia: 25.000,50 -> 25000.50
    if (str_contains($value, ',') && str_contains($value, '.')) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return (float) $value;
    }

    // Format desimal dengan titik: 25000.00 -> 25000
    if (str_contains($value, ',')) {
        // Koma dianggap desimal bila hanya 1-2 digit di belakangnya.
        if (preg_match('/,\d{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
            return (float) $value;
        }
        $value = str_replace(',', '', $value);
        return (float) $value;
    }

    if (str_contains($value, '.')) {
        // Titik ribuan: 25.000 / 2.500.000
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            return (float) str_replace('.', '', $value);
        }
        // Titik desimal: 25000.00 / 25000.5
        if (preg_match('/^\d+\.\d{1,2}$/', $value)) {
            return (float) $value;
        }
        // Titik lain dianggap pemisah ribuan.
        return (float) str_replace('.', '', $value);
    }

    return (float) $value;
}

/**
 * Baca manifest Vite dan kembalikan URL entry JS + CSS yang sudah di-hash.
 *
 * @return array{js: string, css: array<int, string>}
 */
function vite_assets(string $entry = 'assets/vue/app.js'): array
{
    static $manifest = null;
    if ($manifest === null) {
        $file = APP_ROOT . '/assets/build/.vite/manifest.json';
        $manifest = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    $item = $manifest[$entry] ?? null;
    if (!is_array($item) || empty($item['file'])) {
        return [
            'js' => url('assets/build/app.js'),
            'css' => [url('assets/build/app.css')],
        ];
    }

    $base = 'assets/build/';
    $cssPaths = $item['css'] ?? [];
    if (!$cssPaths) {
        foreach ($manifest as $candidate) {
            $file = (string) ($candidate['file'] ?? '');
            if (str_ends_with($file, '.css')) {
                $cssPaths[] = $file;
                break;
            }
        }
    }
    $css = array_map(static fn (string $path): string => url($base . ltrim($path, '/')), $cssPaths);

    return [
        'js' => url($base . ltrim((string) $item['file'], '/')),
        'css' => $css ?: [url('assets/build/app.css')],
    ];
}

function ensure_dir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

/**
 * Simpan file upload ke storage/<folder>/<filename>, kembalikan path relatif
 * yang kompatibel dengan database Laravel (mis. fnb_images/foo.svg).
 */
function upload_image(string $field, string $folder, array $allowed = ['jpg', 'jpeg', 'png', 'webp'], int $maxBytes = 2097152): ?string
{
    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Gagal mengunggah file.');
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        throw new RuntimeException('Ukuran foto maksimal ' . round($maxBytes / 1024 / 1024) . 'MB.');
    }

    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Format foto harus ' . strtoupper(implode(', ', $allowed)) . '.');
    }

    // Validasi isi file, bukan hanya ekstensi: cegah file non-gambar
    // (mis. PHP/HTML yang di-rename menjadi .jpg) ikut tersimpan.
    $mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tmp = (string) ($file['tmp_name'] ?? '');
    $mime = '';
    if ($tmp !== '' && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
    } elseif ($tmp !== '' && function_exists('getimagesize')) {
        $info = @getimagesize($tmp);
        $mime = (string) ($info['mime'] ?? '');
    }
    $normalizedExt = $ext === 'jpeg' ? 'jpg' : $ext;
    if (($mimeMap[$mime] ?? null) !== $normalizedExt) {
        throw new RuntimeException('Isi file bukan gambar yang valid. Gunakan JPG, PNG, atau WEBP.');
    }

    $directory = APP_ROOT . '/storage/' . trim($folder, '/');
    ensure_dir($directory);
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $directory . '/' . $filename;

    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        throw new RuntimeException('Gagal menyimpan file upload.');
    }

    return trim($folder, '/') . '/' . $filename;
}

function delete_upload(?string $relativePath): void
{
    if (!$relativePath || preg_match('#^https?://#i', $relativePath)) {
        return;
    }
    $file = APP_ROOT . '/storage/' . ltrim($relativePath, '/');
    if (is_file($file)) {
        @unlink($file);
    }
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function fetch_value(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function execute_sql(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function request_bool(mixed $value): bool
{
    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function array_get(array $array, string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = $array;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

/**
 * Format standar item transaksi + relasi fnb_item (bentuk JSON Eloquent).
 */
function shape_transaction_item(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'transaction_id' => (int) $row['transaction_id'],
        'fnb_item_id' => (int) $row['fnb_item_id'],
        'quantity' => (int) $row['quantity'],
        'price' => (string) $row['price'],
        'subtotal' => (string) $row['subtotal'],
        'status' => $row['status'] ?? 'pending',
        'created_at' => to_iso($row['created_at'] ?? null),
        'updated_at' => to_iso($row['updated_at'] ?? null),
        'fnb_item' => isset($row['fnb_name']) ? [
            'id' => (int) $row['fnb_item_id'],
            'name' => $row['fnb_name'],
            'category' => $row['fnb_category'] ?? null,
            'price' => (string) ($row['fnb_price'] ?? $row['price']),
            'image_path' => $row['fnb_image_path'] ?? null,
            'image_url' => storage_url($row['fnb_image_path'] ?? null),
        ] : null,
    ];
}

function shape_package(?array $row): ?array
{
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'price' => (string) $row['price'],
        'price_per_hour' => (float) $row['price'],
        'start_time' => $row['start_time'] ?? null,
        'end_time' => $row['end_time'] ?? null,
        'created_at' => to_iso($row['created_at'] ?? null),
        'updated_at' => to_iso($row['updated_at'] ?? null),
    ];
}

function shape_fnb_item(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'category' => $row['category'],
        'price' => (string) $row['price'],
        'image_path' => $row['image_path'],
        'image_url' => storage_url($row['image_path']),
        'created_at' => to_iso($row['created_at'] ?? null),
        'updated_at' => to_iso($row['updated_at'] ?? null),
    ];
}

function shape_table(array $row): array
{
    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'relay_channel' => (int) $row['relay_channel'],
        'status' => $row['status'],
        'is_light_on' => (bool) ($row['is_light_on'] ?? false),
        'created_at' => to_iso($row['created_at'] ?? null),
        'updated_at' => to_iso($row['updated_at'] ?? null),
    ];
}

function shape_transaction(array $row, ?array $table = null, ?array $package = null, ?array $user = null, ?array $items = null): array
{
    $data = [
        'id' => (int) $row['id'],
        'type' => $row['type'] ?? 'billiard',
        'table_id' => $row['table_id'],
        'package_id' => $row['package_id'] !== null ? (int) $row['package_id'] : null,
        'user_id' => $row['user_id'],
        'customer_name' => $row['customer_name'],
        'start_time' => to_iso($row['start_time'] ?? null),
        'end_time' => to_iso($row['end_time'] ?? null),
        'expected_end_time' => to_iso($row['expected_end_time'] ?? null),
        'billiard_cost' => (string) ($row['billiard_cost'] ?? '0'),
        'fnb_cost' => (string) ($row['fnb_cost'] ?? '0'),
        'total_cost' => (string) ($row['total_cost'] ?? '0'),
        'status' => $row['status'],
        'created_at' => to_iso($row['created_at'] ?? null),
        'updated_at' => to_iso($row['updated_at'] ?? null),
    ];

    if ($table !== null) {
        $data['table'] = $table;
    } elseif (array_key_exists('table_name', $row)) {
        $data['table'] = $row['table_id'] ? [
            'id' => $row['table_id'],
            'name' => $row['table_name'],
            'relay_channel' => (int) ($row['table_relay_channel'] ?? 0),
        ] : null;
    }

    if ($package !== null) {
        $data['package'] = $package;
    } elseif (array_key_exists('package_name', $row)) {
        $data['package'] = $row['package_id'] ? [
            'id' => (int) $row['package_id'],
            'name' => $row['package_name'],
            'price' => (string) ($row['package_price'] ?? '0'),
        ] : null;
    }

    if ($user !== null) {
        $data['user'] = $user;
    }

    if ($items !== null) {
        $data['items'] = $items;
    }

    return $data;
}
