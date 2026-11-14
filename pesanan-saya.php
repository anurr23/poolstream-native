<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

if ($user_role !== 'customer') {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$customer_orders = [];
if ($db) {
    $txModel = new Transaction($db);
    $txModel->autoCheckoutExpired();

    $qCustTxs = "SELECT t.*, tbl.name as table_name, p.name as package_name 
                 FROM transactions t 
                 LEFT JOIN tables tbl ON t.table_id = tbl.id 
                 LEFT JOIN packages p ON t.package_id = p.id 
                 WHERE t.user_id = :user_id 
                 ORDER BY t.created_at DESC LIMIT 20";
    $stmtCust = $db->prepare($qCustTxs);
    $stmtCust->execute([':user_id' => $user_id]);
    $customer_orders = $stmtCust->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Meja Saya - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('poolstream_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="bg-body text-body d-flex flex-column min-vh-100" style="overflow-x: hidden;">
    <!-- AURORA BOREALIS LIGHT EFFECT -->
    <div class="aurora-container">
        <div class="aurora-beam aurora-1"></div>
        <div class="aurora-beam aurora-2"></div>
        <div class="aurora-beam aurora-3"></div>
        <div class="tech-matrix-overlay"></div>
    </div>

    <!-- Sticky Top Navigation -->
    <header class="dashboard-nav px-3 px-md-4 px-xl-5">
        <a href="index.php" class="brand-badge text-decoration-none">
            <img src="assets/images/logo.png" alt="PoolStream Logo" onerror="this.src='images/logo.png'">
            <div class="brand-text-wrap">
                <span class="brand-title">PoolStream</span>
                <span class="brand-version-pill">Lounge & Booking</span>
            </div>
        </a>

        <div class="d-none d-md-flex align-items-center gap-4">
            <a href="index.php" class="text-secondary text-decoration-none small">Beranda</a>
            <a href="meja-list.php" class="text-secondary text-decoration-none small">Seluruh Meja</a>
            <a href="pesanan-saya.php" class="text-body text-decoration-none small fw-bold">Pesanan Saya</a>
        </div>

        <div class="user-nav-profile">
            <button id="themeToggle" class="theme-toggle-btn" title="Ganti Tema">
                <i class="bi bi-sun-fill" id="themeIcon"></i>
            </button>

            <div class="user-badge-box">
                <div class="user-avatar-wrap">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <span class="avatar-status-dot"></span>
                </div>
                <div class="user-info-text">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role-label"><?php echo htmlspecialchars($user_role); ?></span>
                </div>
            </div>
            <a href="logout.php" class="logout-btn" title="Keluar">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </a>
        </div>
    </header>

    <!-- MAIN CONTENT: MY ORDERS -->
    <main class="container-fluid px-3 px-md-4 px-xl-5" style="padding-top: 100px; padding-bottom: 80px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <span class="text-success text-uppercase tracking-wider fw-bold small">RIWAYAT & AKTIF</span>
                <h1 class="fw-bold mt-1 mb-0" style="font-size: 1.8rem;">Pesanan Meja Milik Anda</h1>
            </div>
        </div>

        <?php if (empty($customer_orders)): ?>
            <div class="text-center py-5 bg-body border border-secondary border-opacity-25 shadow-sm" style="border-radius: 12px;">
                <i class="bi bi-inbox" style="font-size: 2.5rem; color: #64748b;"></i>
                <p class="text-muted mt-2 mb-0">Anda belum pernah melakukan pemesanan meja biliar.</p>
                <a href="meja-list.php" class="bb-btn bb-btn--success mt-3 text-decoration-none d-inline-block">Mulai Booking Meja</a>
            </div>
        <?php else: ?>
            <div class="table-responsive shadow-sm" style="border-radius: 12px; overflow: hidden;">
                <table class="table table-hover align-middle mb-0" style="background: var(--bs-body-bg); color: var(--bs-body-color);">
                    <thead class="table-secondary">
                        <tr>
                            <th>ID Transaksi</th>
                            <th>Meja</th>
                            <th>Paket</th>
                            <th>Waktu Mulai</th>
                            <th>Estimasi Selesai</th>
                            <th>Total Biaya</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customer_orders as $ord): ?>
                            <tr>
                                <td><code>#<?php echo substr($ord['id'], 0, 8); ?></code></td>
                                <td class="fw-bold"><?php echo htmlspecialchars($ord['table_name']); ?></td>
                                <td><?php echo htmlspecialchars($ord['package_name']); ?></td>
                                <td><?php echo date('d M Y, H:i', strtotime($ord['start_time'])); ?></td>
                                <td><?php echo !empty($ord['expected_end_time']) ? date('H:i', strtotime($ord['expected_end_time'])) : '-'; ?></td>
                                <td class="fw-bold text-success">Rp <?php echo number_format($ord['total_cost'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if ($ord['status'] === 'active'): ?>
                                        <span class="badge bg-success">Aktif / Berjalan</span>
                                    <?php elseif ($ord['status'] === 'completed'): ?>
                                        <span class="badge bg-secondary">Selesai</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?php echo ucfirst($ord['status']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>

    <!-- FOOTER -->
    <footer class="py-3 text-center text-muted small border-top border-secondary border-opacity-25 bg-body mt-auto">
        <div class="container-fluid">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved. IoT Billiard Command Center.</p>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Theme Toggle Persistence
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;

        function updateThemeIcon(theme) {
            if (themeIcon) {
                if (theme === 'dark') {
                    themeIcon.className = 'bi bi-sun-fill';
                } else {
                    themeIcon.className = 'bi bi-moon-fill';
                }
            }
        }

        updateThemeIcon(htmlElement.getAttribute('data-bs-theme'));

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-bs-theme');
                const newTheme = (currentTheme === 'dark') ? 'light' : 'dark';
                htmlElement.setAttribute('data-bs-theme', newTheme);
                localStorage.setItem('poolstream_theme', newTheme);
                updateThemeIcon(newTheme);
            });
        }
    </script>
</body>
</html>
