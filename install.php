<?php
declare(strict_types=1);

/**
 * PoolStream Native - Installer
 * -----------------------------------------------------------------------------
 * Browser : http://localhost/poolstream-native/install.php
 * CLI     : php install.php
 * Reset   : php install.php --reset
 *
 * File ini membaca konfigurasi dari .env, membuat database,
 * menjalankan database/poolstream_full.sql (tabel + master data), lalu
 * menampilkan ringkasan. Aman dijalankan ulang.
 *
 * Hapus file ini setelah instalasi selesai.
 */

$isCli = PHP_SAPI === 'cli';
$root = __DIR__;
$envFile = $root . '/.env';
$sqlFile = $root . '/database/poolstream_full.sql';

require_once $root . '/config/env.php';

if (!$isCli && session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!$isCli && empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $isCli ? '' : (string) $_SESSION['install_csrf'];

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function valid_db_name(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Nama database tidak valid: ' . $name);
    }
    return $name;
}

/**
 * @return array{0: PDO, 1: string}
 */
function connect_server(array $db): array
{
    $host = (string) $db['host'];
    $port = (string) $db['port'];
    $user = (string) $db['user'];
    $pass = (string) $db['pass'];
    $timeout = (int) $db['timeout'];

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_TIMEOUT => $timeout,
            ]
        );
        return [$pdo, $host];
    } catch (Throwable $e) {
        throw new RuntimeException(
            'Gagal terhubung ke MySQL ' . $host . ':' . $port . '. Periksa DB_HOST, DB_PORT, '
            . 'DB_USER, dan DB_PASSWORD di .env. ' . $e->getMessage(),
            0,
            $e
        );
    }
}

function database_exists(PDO $pdo, string $dbName): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name LIMIT 1');
    $stmt->execute([':name' => $dbName]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Pecah file SQL menjadi statement per titik koma, dengan menghormati
 * string literal, backtick, dan komentar.
 *
 * @return array<int, string>
 */
function split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = $inDouble = $inBacktick = $inLineComment = $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $inBlockComment = false;
            }
            continue;
        }

        if ($inSingle || $inDouble) {
            $buffer .= $char;
            $quote = $inSingle ? "'" : '"';
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $inSingle = $inDouble = false;
            }
            continue;
        }

        if ($inBacktick) {
            $buffer .= $char;
            if ($char === '`') {
                if ($next === '`') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $inBacktick = false;
            }
            continue;
        }

        if ($char === '-' && $next === '-') {
            $inLineComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '#') {
            $inLineComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $buffer .= $char;
            continue;
        }
        if ($char === "'") {
            $inSingle = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '"') {
            $inDouble = true;
            $buffer .= $char;
            continue;
        }
        if ($char === '`') {
            $inBacktick = true;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }

    return $statements;
}

function strip_full_line_comments(string $sql): string
{
    $sql = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? '';
    $sql = preg_replace('/^[ \t]*--[^\n]*$/m', ' ', $sql) ?? '';
    $sql = preg_replace('/^[ \t]*#[^\n]*$/m', ' ', $sql) ?? '';
    return trim($sql);
}

/**
 * @param array<int, array{status: string, message: string}> $logs
 */
function run_sql_file(PDO $pdo, string $sqlFile, string $dbName, array &$logs, bool $isCli = false): int
{
    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('File SQL tidak bisa dibaca: ' . $sqlFile);
    }

    // Ikuti nama database dari .env.
    $sql = str_replace('`poolstream`', '`' . $dbName . '`', $sql);
    $statements = split_sql($sql);
    $executed = 0;

    foreach ($statements as $index => $statement) {
        $clean = strip_full_line_comments($statement);
        if ($clean === '') {
            continue;
        }

        $verb = strtoupper((string) strtok($clean, " \t\n\r("));

        try {
            if (in_array($verb, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'], true)) {
                $result = $pdo->query($statement);
                if ($result instanceof PDOStatement) {
                    $result->fetchAll();
                    $result->closeCursor();
                }
            } else {
                $pdo->exec($statement);
            }
            $executed++;
        } catch (Throwable $e) {
            $snippet = preg_replace('/\s+/', ' ', substr($clean, 0, 180));
            throw new RuntimeException(
                'Statement #' . ($index + 1) . ' gagal: ' . $e->getMessage() . ' | SQL: ' . $snippet,
                0,
                $e
            );
        }
    }

    log_line($isCli, $logs, 'ok', $executed . ' statement SQL berhasil dijalankan.');
    return $executed;
}

/**
 * @return array<string, int|null>
 */
function master_counts(PDO $pdo, string $dbName): array
{
    $pdo->exec('USE `' . $dbName . '`');
    $tables = [
        'users',
        'tables',
        'packages',
        'fnb_items',
        'transactions',
        'transaction_items',
        'ai_evaluations',
        'send_wa_api',
    ];

    $counts = [];
    foreach ($tables as $table) {
        try {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
        } catch (Throwable $e) {
            $counts[$table] = null;
        }
    }
    return $counts;
}

/**
 * @param array<int, array{status: string, message: string}> $logs
 */
function log_line(bool $isCli, array &$logs, string $status, string $message): void
{
    $logs[] = ['status' => $status, 'message' => $message];
    if ($isCli) {
        $prefix = $status === 'error' ? '[ERROR]' : ($status === 'warn' ? '[WARN] ' : '[OK]   ');
        echo $prefix . ' ' . $message . PHP_EOL;
    }
}

// -----------------------------------------------------------------------------
// Jalankan instalasi
// -----------------------------------------------------------------------------
$envLoaded = false;
$envError = null;
$dbConfig = [];
$dbName = '';
$logs = [];
$result = null;
$error = null;
$connectedHost = null;
$dbExists = false;
$installed = false;
$statusCounts = [];

try {
    env_load($envFile);
    $dbConfig = [
        'host' => env_required('DB_HOST'),
        'port' => env_int('DB_PORT'),
        'user' => env_required('DB_USER'),
        'pass' => env_required('DB_PASSWORD'),
        'timeout' => env_int('DB_TIMEOUT'),
    ];
    $dbName = valid_db_name(env_required('DB_NAME'));
    $envLoaded = true;
} catch (Throwable $e) {
    $envError = $e->getMessage();
}

if (!$envLoaded) {
    $error = $envError;
}

if ($isCli) {
    $reset = in_array('--reset', $argv ?? [], true);
    try {
        if (!$envLoaded) {
            throw new RuntimeException($envError ?? '.env tidak siap.');
        }
        if (!is_file($sqlFile)) {
            throw new RuntimeException('File SQL tidak ditemukan: ' . $sqlFile);
        }

        [$pdo, $connectedHost] = connect_server($dbConfig);
        log_line(true, $logs, 'ok', "Terhubung ke MySQL {$connectedHost}.");

        if ($reset) {
            $pdo->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
            log_line(true, $logs, 'ok', "Database `{$dbName}` dihapus (reset total).");
        }

        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $dbName . '` ' .
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        log_line(true, $logs, 'ok', "Database `{$dbName}` siap.");

        $pdo->exec('USE `' . $dbName . '`');
        run_sql_file($pdo, $sqlFile, $dbName, $logs, true);

        $statusCounts = master_counts($pdo, $dbName);
        log_line(true, $logs, 'ok', 'users: ' . ($statusCounts['users'] ?? 0) .
            ', packages: ' . ($statusCounts['packages'] ?? 0) .
            ', tables: ' . ($statusCounts['tables'] ?? 0) .
            ', fnb_items: ' . ($statusCounts['fnb_items'] ?? 0));
        log_line(true, $logs, 'ok', 'Instalasi selesai. Buka aplikasi di browser.');
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $token = (string) ($_POST['_token'] ?? '');
        if (!hash_equals($csrf, $token)) {
            throw new RuntimeException('Token keamanan tidak valid. Muat ulang halaman.');
        }

        if (!is_file($sqlFile)) {
            throw new RuntimeException('File SQL tidak ditemukan: ' . $sqlFile);
        }
        if (!$envLoaded) {
            throw new RuntimeException($envError ?? '.env tidak siap.');
        }

        $action = (string) ($_POST['action'] ?? 'install');
        $isReset = $action === 'reset';

        if ($isReset && (string) ($_POST['confirm'] ?? '') !== 'RESET') {
            throw new RuntimeException('Untuk reset total, ketik RESET pada kolom konfirmasi.');
        }

        [$pdo, $connectedHost] = connect_server($dbConfig);
        log_line(false, $logs, 'ok', "Terhubung ke MySQL {$connectedHost}.");

        if ($isReset) {
            $pdo->exec('DROP DATABASE IF EXISTS `' . $dbName . '`');
            log_line(false, $logs, 'ok', "Database `{$dbName}` dihapus (reset total).");
        }

        $pdo->exec(
            'CREATE DATABASE IF NOT EXISTS `' . $dbName . '` ' .
            'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        log_line(false, $logs, 'ok', "Database `{$dbName}` siap.");

        $pdo->exec('USE `' . $dbName . '`');
        run_sql_file($pdo, $sqlFile, $dbName, $logs, false);

        $statusCounts = master_counts($pdo, $dbName);
        $result = [
            'action' => $isReset ? 'reset' : 'install',
            'counts' => $statusCounts,
        ];
        log_line(false, $logs, 'ok', 'Instalasi selesai.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        log_line(false, $logs, 'error', $error);
    }
}

// -----------------------------------------------------------------------------
// Status untuk halaman web
// -----------------------------------------------------------------------------
$checks = [
    'env' => $envLoaded,
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'database' => null,
    'installed' => null,
];

if ($checks['env'] && $checks['pdo_mysql'] && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        [$pdo, $connectedHost] = connect_server($dbConfig);
        $dbExists = database_exists($pdo, $dbName);
        $checks['database'] = $dbExists;

        if ($dbExists) {
            $statusCounts = master_counts($pdo, $dbName);
            $installed = ($statusCounts['users'] ?? 0) > 0
                && ($statusCounts['packages'] ?? 0) > 0
                && ($statusCounts['tables'] ?? 0) > 0
                && ($statusCounts['fnb_items'] ?? 0) > 0;
            $checks['installed'] = $installed;
        }
    } catch (Throwable $e) {
        $checks['database'] = false;
        $error = $e->getMessage();
    }
} elseif (!empty($statusCounts)) {
    $installed = true;
    $checks['database'] = true;
    $checks['installed'] = true;
}

$countLabels = [
    'users' => 'Users',
    'tables' => 'Meja',
    'packages' => 'Paket',
    'fnb_items' => 'Menu F&B',
    'transactions' => 'Transaksi',
    'transaction_items' => 'Item Transaksi',
    'ai_evaluations' => 'Evaluasi AI',
    'send_wa_api' => 'Antrean WA',
];

?><!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Installer - PoolStream</title>
    <style>
        :root {
            --bg: #0b1020;
            --card: #121a2e;
            --card-2: #0f1628;
            --line: #24314d;
            --text: #e8eefc;
            --muted: #9aa9c7;
            --accent: #4f8cff;
            --accent-2: #22c55e;
            --danger: #ef4444;
            --warn: #f59e0b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--text);
            background:
                radial-gradient(900px 500px at 10% -10%, #1b2b52 0%, transparent 60%),
                radial-gradient(700px 400px at 100% 0%, #0f3b3a 0%, transparent 55%),
                var(--bg);
        }
        .wrap { width: min(900px, calc(100% - 32px)); margin: 40px auto; }
        .brand { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
        .brand-mark {
            width: 48px; height: 48px; border-radius: 14px;
            display: grid; place-items: center; font-weight: 800; font-size: 20px;
            background: linear-gradient(135deg, #4f8cff, #22c55e); color: #06101f;
            box-shadow: 0 10px 30px rgba(79, 140, 255, .35);
        }
        .brand h1 { margin: 0; font-size: 24px; letter-spacing: -.3px; }
        .brand p { margin: 4px 0 0; color: var(--muted); font-size: 14px; }
        .card {
            background: linear-gradient(180deg, var(--card), var(--card-2));
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 22px;
            margin-bottom: 16px;
            box-shadow: 0 18px 50px rgba(0, 0, 0, .28);
        }
        .card h2 { margin: 0 0 14px; font-size: 17px; }
        .card p { color: var(--muted); line-height: 1.6; }
        .status-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
        .status-list li {
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            padding: 11px 14px; border: 1px solid var(--line); border-radius: 12px;
            background: rgba(255, 255, 255, .02); font-size: 14px;
        }
        .badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700;
            border: 1px solid transparent; white-space: nowrap;
        }
        .badge.ok { color: #86efac; background: rgba(34, 197, 94, .12); border-color: rgba(34, 197, 94, .35); }
        .badge.bad { color: #fca5a5; background: rgba(239, 68, 68, .12); border-color: rgba(239, 68, 68, .35); }
        .badge.warn { color: #fcd34d; background: rgba(245, 158, 11, .12); border-color: rgba(245, 158, 11, .35); }
        .badge.info { color: #93c5fd; background: rgba(79, 140, 255, .12); border-color: rgba(79, 140, 255, .35); }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .btn {
            appearance: none; border: 1px solid transparent; border-radius: 12px;
            padding: 11px 18px; font-size: 14px; font-weight: 700; cursor: pointer;
            transition: transform .15s ease, filter .15s ease;
        }
        .btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        .btn-primary { color: #06101f; background: linear-gradient(135deg, #63a0ff, #22c55e); }
        .btn-danger { color: #fff; background: linear-gradient(135deg, #ef4444, #b91c1c); }
        .btn-ghost { color: var(--text); background: transparent; border-color: var(--line); }
        .logs { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        .logs li {
            padding: 9px 12px; border-radius: 10px; font-size: 13px;
            border: 1px solid var(--line); background: rgba(255, 255, 255, .02);
            overflow-wrap: anywhere;
        }
        .logs li.ok { border-color: rgba(34, 197, 94, .3); }
        .logs li.error { border-color: rgba(239, 68, 68, .45); color: #fecaca; background: rgba(239, 68, 68, .08); }
        .counts { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 14px; }
        .count {
            padding: 14px; border: 1px solid var(--line); border-radius: 12px;
            background: rgba(255, 255, 255, .02);
        }
        .count span { display: block; color: var(--muted); font-size: 12px; margin-bottom: 5px; }
        .count strong { font-size: 22px; }
        .alert {
            padding: 14px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px;
            border: 1px solid rgba(239, 68, 68, .4); background: rgba(239, 68, 68, .1); color: #fecaca;
            overflow-wrap: anywhere;
        }
        details.danger {
            margin-top: 16px; border-top: 1px dashed var(--line); padding-top: 16px;
        }
        details.danger summary { cursor: pointer; color: #fca5a5; font-weight: 700; font-size: 14px; }
        .field { display: grid; gap: 6px; margin: 14px 0; max-width: 280px; }
        .field label { font-size: 13px; color: var(--muted); }
        .field input {
            width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--line);
            background: #0a111f; color: var(--text); font-size: 14px; outline: none;
        }
        .field input:focus { border-color: var(--accent); }
        code { background: rgba(255, 255, 255, .07); padding: 2px 6px; border-radius: 6px; font-size: 13px; }
        pre {
            background: #08101e; border: 1px solid var(--line); border-radius: 12px;
            padding: 14px; overflow-x: auto; font-size: 13px; line-height: 1.6;
        }
        .muted { color: var(--muted); font-size: 13px; }
        .footer { text-align: center; color: var(--muted); font-size: 12px; margin: 26px 0 10px; }
    </style>
</head>
<body>
<main class="wrap">
    <header class="brand">
        <div class="brand-mark">PS</div>
        <div>
            <h1>PoolStream Installer</h1>
            <p>Membuat database, tabel, relasi, dan master data awal.</p>
        </div>
    </header>

    <?php if ($error !== null): ?>
        <div class="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($logs): ?>
        <section class="card">
            <h2>Proses</h2>
            <ul class="logs">
                <?php foreach ($logs as $log): ?>
                    <li class="<?= h($log['status']) ?>"><?= h($log['message']) ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($result !== null): ?>
        <section class="card">
            <h2>Instalasi Berhasil</h2>
            <p>
                Database <code><?= h($dbName) ?></code>
                <?= $result['action'] === 'reset' ? 'telah di-reset total dan dibuat ulang' : 'siap digunakan' ?>.
                Master data sudah terpasang.
            </p>
            <div class="counts">
                <?php foreach ($countLabels as $key => $label): ?>
                    <div class="count">
                        <span><?= h($label) ?></span>
                        <strong><?= h($result['counts'][$key] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="<?= h(str_replace('install.php', 'login.php', $_SERVER['SCRIPT_NAME'] ?? '/login.php')) ?>">Buka Login</a>
                <a class="btn btn-ghost" href="">Muat Ulang Installer</a>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Status</h2>
        <ul class="status-list">
            <li>
                <span>File .env</span>
                <span class="badge <?= $checks['env'] ? 'ok' : 'bad' ?>">
                    <?= $checks['env'] ? 'Siap' : 'Bermasalah' ?>
                </span>
            </li>
            <li>
                <span>Ekstensi PDO MySQL</span>
                <span class="badge <?= $checks['pdo_mysql'] ? 'ok' : 'bad' ?>">
                    <?= $checks['pdo_mysql'] ? 'Aktif' : 'Tidak aktif' ?>
                </span>
            </li>
            <li>
                <span>Koneksi MySQL<?= $connectedHost ? ' (' . h($connectedHost) . ')' : '' ?></span>
                <?php if ($checks['database'] === null): ?>
                    <span class="badge warn">Belum dicek</span>
                <?php else: ?>
                    <span class="badge <?= $checks['database'] ? 'ok' : 'bad' ?>">
                        <?= $checks['database'] ? 'Terhubung' : 'Gagal' ?>
                    </span>
                <?php endif; ?>
            </li>
            <li>
                <span>Database <code><?= h($dbName !== '' ? $dbName : '-') ?></code></span>
                <?php if ($checks['installed'] === null): ?>
                    <span class="badge warn">Belum ada</span>
                <?php else: ?>
                    <span class="badge <?= $checks['installed'] ? 'ok' : 'warn' ?>">
                        <?= $checks['installed'] ? 'Sudah terpasang' : 'Belum terpasang' ?>
                    </span>
                <?php endif; ?>
            </li>
        </ul>

        <?php if ($checks['env'] && $checks['pdo_mysql']): ?>
            <p class="muted" style="margin-top:14px">
                Klik <strong>Install / Migrate</strong> untuk membuat database, semua tabel,
                dan master data. Proses ini aman dijalankan berulang kali.
            </p>
            <div class="actions">
                <form method="post">
                    <input type="hidden" name="_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="install">
                    <button class="btn btn-primary" type="submit">Install / Migrate</button>
                </form>
            </div>

            <details class="danger">
                <summary>Reset total (hapus semua data)</summary>
                <p class="muted">
                    Semua tabel dan data pada database <code><?= h($dbName) ?></code> akan dihapus,
                    lalu dibuat ulang dari nol. Ketik <strong>RESET</strong> untuk melanjutkan.
                </p>
                <form method="post" onsubmit="return confirm('Reset total database? Semua data akan hilang.');">
                    <input type="hidden" name="_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="reset">
                    <div class="field">
                        <label for="confirm">Konfirmasi</label>
                        <input id="confirm" name="confirm" type="text" placeholder="RESET" required pattern="RESET" autocomplete="off">
                    </div>
                    <button class="btn btn-danger" type="submit">Reset &amp; Install Ulang</button>
                </form>
            </details>
        <?php else: ?>
            <p class="muted" style="margin-top:14px">
                Lengkapi dulu konfigurasi database di file <code>.env</code>.
                Salin <code>.env.example</code> menjadi <code>.env</code>, lalu sesuaikan
                <code>DB_HOST</code>, <code>DB_PORT</code>, <code>DB_NAME</code>,
                <code>DB_USER</code>, dan <code>DB_PASSWORD</code>.
            </p>
            <pre>copy .env.example .env</pre>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Master Data</h2>
        <?php if (!empty($statusCounts)): ?>
            <div class="counts">
                <?php foreach ($countLabels as $key => $label): ?>
                    <div class="count">
                        <span><?= h($label) ?></span>
                        <strong><?= h($statusCounts[$key] ?? 0) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="muted">Belum ada data. Jalankan installer untuk mengisi master data.</p>
        <?php endif; ?>
        <p class="muted" style="margin-top:14px">
            Akun demo: <code>admin</code>, <code>kasir</code>, <code>customer</code>.
            Password semua akun: <code>password</code>.
        </p>
    </section>

    <p class="footer">
        Installer membaca <code>database/poolstream_full.sql</code>. Hapus <code>install.php</code>
        setelah instalasi selesai.
    </p>
</main>
</body>
</html>
