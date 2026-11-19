<?php
declare(strict_types=1);

/**
 * PoolStream Native
 * -----------------------------------------------------------------------------
 * Bootstrap aplikasi PHP native (tanpa framework) dengan frontend Vue 3.
 * Semua konfigurasi kredensial ada di config/credentials.php (gitignored).
 */

define('APP_NAME', 'PoolStream');
define('APP_ROOT', dirname(__DIR__));

date_default_timezone_set('Asia/Jakarta');

// -----------------------------------------------------------------------------
// Credentials
// -----------------------------------------------------------------------------
$credentialsFile = __DIR__ . '/credentials.php';
$GLOBALS['poolstream_credentials'] = is_file($credentialsFile)
    ? require $credentialsFile
    : [];

function credentials(?string $path = null, mixed $default = null): mixed
{
    $data = $GLOBALS['poolstream_credentials'] ?? [];
    if ($path === null) {
        return $data;
    }

    foreach (explode('.', $path) as $segment) {
        if (!is_array($data) || !array_key_exists($segment, $data)) {
            return $default;
        }
        $data = $data[$segment];
    }

    return $data;
}

// -----------------------------------------------------------------------------
// Base URL (mendukung subfolder, mis. /poolstream-native/)
// -----------------------------------------------------------------------------
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
if ($scriptDir === '.' || $scriptDir === '\\') {
    $scriptDir = '';
}
if (str_ends_with($scriptDir, '/api')) {
    $scriptDir = substr($scriptDir, 0, -4);
}
define('BASE_PATH', $scriptDir);
define('BASE_URL', ($scriptDir === '' ? '' : $scriptDir) . '/');
define('ASSET_URL', BASE_URL . 'assets/');
define('STORAGE_URL', BASE_URL . 'storage/');

// -----------------------------------------------------------------------------
// Session
// -----------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['_csrf'])) {
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------------------
// Autoload
// -----------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    foreach ([
        APP_ROOT . '/models/' . $class . '.php',
        APP_ROOT . '/services/' . $class . '.php',
        __DIR__ . '/' . $class . '.php',
    ] as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/database.php';
require_once APP_ROOT . '/services/helpers.php';

// -----------------------------------------------------------------------------
// Current user
// -----------------------------------------------------------------------------
function current_user(bool $refresh = false): ?array
{
    static $cached = null;
    static $loaded = false;

    if ($refresh) {
        $loaded = false;
        $cached = null;
    }

    if ($loaded) {
        return $cached;
    }

    $loaded = true;
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return $cached = null;
    }

    $user = (new User(db()))->find((string) $id);
    if (!$user || !(bool) $user['is_active']) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        return $cached = null;
    }

    return $cached = $user;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        $target = $_SERVER['REQUEST_URI'] ?? null;
        if ($target) {
            $_SESSION['url_intended'] = $target;
        }
        redirect('login.php');
    }
}

/**
 * @param array<int, string>|string $roles
 */
function require_role(array|string $roles): void
{
    require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    $user = current_user();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render_page('Errors/Forbidden', [], 'Akses Ditolak');
        exit;
    }
}

function require_customer_verified(): void
{
    require_login();
    $user = current_user();
    if ($user && $user['role'] === 'customer' && empty($user['email_verified_at'])) {
        redirect('verify-otp.php');
    }
}

function is_customer(): bool
{
    return (current_user()['role'] ?? null) === 'customer';
}

// -----------------------------------------------------------------------------
// HTTP helpers
// -----------------------------------------------------------------------------
function url(string $path = ''): string
{
    return BASE_URL . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return ASSET_URL . ltrim($path, '/') . '?v=' . $version;
}

function storage_url(?string $path): ?string
{
    if (!$path) {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return STORAGE_URL . ltrim($path, '/');
}

function redirect(string $path, int $status = 302): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

function request_input(): array
{
    $input = $_POST;
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: 'null', true);
        if (is_array($decoded)) {
            $input = array_merge($input, $decoded);
        }
    }
    return $input;
}

function csrf_token(): string
{
    return (string) ($_SESSION['_csrf'] ?? '');
}

function verify_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        return;
    }

    $token = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        json_response([
            'ok' => false,
            'message' => 'Sesi keamanan tidak valid. Muat ulang halaman.',
            'errors' => ['_token' => 'Sesi keamanan tidak valid. Muat ulang halaman.'],
        ], 419);
    }
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data = [], ?string $message = null, ?string $redirect = null): never
{
    $payload = ['ok' => true] + $data;
    if ($message !== null) {
        $payload['message'] = $message;
    }
    if ($redirect !== null) {
        $payload['redirect'] = preg_match('#^https?://#i', $redirect) ? $redirect : url($redirect);
    }
    json_response($payload);
}

function json_error(array|string $errors, int $status = 422, ?string $message = null): never
{
    if (is_string($errors)) {
        $errors = ['_general' => $errors];
    }
    json_response([
        'ok' => false,
        'message' => $message ?? (reset($errors) ?: 'Terjadi kesalahan.'),
        'errors' => $errors,
    ], $status);
}

function api_handle(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        error_log('[PoolStream API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        json_response([
            'ok' => false,
            'message' => APP_DEBUG ? $e->getMessage() : 'Terjadi kesalahan pada server.',
            'errors' => ['_general' => APP_DEBUG ? $e->getMessage() : 'Terjadi kesalahan pada server.'],
        ], 500);
    }
}

function require_method(string ...$methods): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, array_map('strtoupper', $methods), true)) {
        json_error('Metode tidak diizinkan.', 405);
    }
}

function is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

// -----------------------------------------------------------------------------
// Flash & validation errors (untuk halaman Vue)
// -----------------------------------------------------------------------------
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][$type] = $message;
}

function pull_flash(): array
{
    $flash = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return [
        'success' => $flash['success'] ?? null,
        'error' => $flash['error'] ?? null,
    ];
}

function flash_errors(array $errors): void
{
    $_SESSION['_errors'] = $errors;
}

function pull_errors(): array
{
    $errors = $_SESSION['_errors'] ?? [];
    unset($_SESSION['_errors']);
    return $errors;
}

// -----------------------------------------------------------------------------
// User serialization
// -----------------------------------------------------------------------------
function public_user(?array $user): ?array
{
    if (!$user) {
        return null;
    }

    unset($user['password'], $user['remember_token'], $user['otp_code']);
    $user['is_active'] = (bool) ($user['is_active'] ?? true);
    $user['photo_url'] = storage_url($user['photo_path'] ?? null);

    return $user;
}

// -----------------------------------------------------------------------------
// Page renderer
// -----------------------------------------------------------------------------
function render_page(string $component, array $props = [], string $title = 'PoolStream'): void
{
    $user = current_user();
    $page = [
        'component' => $component,
        'props' => array_merge([
            'auth' => ['user' => public_user($user)],
            'flash' => pull_flash(),
            'errors' => pull_errors(),
            'googleAuthEnabled' => google_oauth_configured(),
        ], $props),
    ];
    $page['props']['csrf'] = csrf_token();

    $json = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $titleEscaped = htmlspecialchars($title === 'PoolStream' ? APP_NAME : $title . ' - ' . APP_NAME, ENT_QUOTES, 'UTF-8');
    $vite = vite_assets();
    ?><!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= $titleEscaped ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= asset('images/logo.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php foreach ($vite['css'] as $cssFile): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <script>
        (function () {
            var theme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
</head>
<body>
    <div id="bb-block-ui" class="bb-block-ui" aria-hidden="true">
        <div class="bb-block-ui__box">
            <div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>
            <span class="bb-block-ui__text">Memuat...</span>
        </div>
    </div>
    <div id="app"></div>
    <script>window.__POOLSTREAM__ = <?= $json ?>;</script>
    <script type="module" src="<?= htmlspecialchars($vite['js'], ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html><?php
}

function APP_DEBUG_VALUE(): bool
{
    return (bool) credentials('app.debug', true);
}

define('APP_DEBUG', APP_DEBUG_VALUE());

function google_oauth_configured(): bool
{
    return (string) credentials('google.client_id', '') !== ''
        && (string) credentials('google.client_secret', '') !== '';
}
