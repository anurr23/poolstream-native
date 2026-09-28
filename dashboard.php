<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
requireLogin();

$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Pengguna';
$user_role = $_SESSION['user_role'] ?? 'kasir';

// Penentuan nama shift otomatis
$hour = (int)date('H');
if ($hour >= 6 && $hour < 14) {
    $shift_name = 'Shift Pagi';
} elseif ($hour >= 14 && $hour < 22) {
    $shift_name = 'Shift Sore';
} else {
    $shift_name = 'Shift Malam';
}

// Data operasional realtime untuk kartu interaktif & KPI ribbon
$total_tables = 16;
$active_tables = 0;
$ready_tables = 16;
$occupancy_pct = 0;
$today_revenue = 0;
$today_fnb_count = 0;
$today_tx_count = 0;
$recent_activities = [];

try {
    $db = (new Database())->getConnection();
    if ($db) {
        // Meja & okupansi
        $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM tables");
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && isset($res['total'])) {
            $total_tables = (int)$res['total'];
            $active_tables = (int)($res['active'] ?? 0);
            $ready_tables = max(0, $total_tables - $active_tables);
            $occupancy_pct = ($total_tables > 0) ? round(($active_tables / $total_tables) * 100) : 0;
        }

        // Omset harian & transaksi selesai
        $stmtRev = $db->query("SELECT COUNT(*) as total_tx, COALESCE(SUM(total_cost), 0) as total_rev FROM transactions WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
        $rowRev = $stmtRev->fetch(PDO::FETCH_ASSOC);
        if ($rowRev) {
            $today_revenue = (float)($rowRev['total_rev'] ?? 0);
            $today_tx_count = (int)($rowRev['total_tx'] ?? 0);
        }

        // Total FnB terjual hari ini
        $stmtFnB = $db->query("SELECT COALESCE(SUM(ti.quantity), 0) as total_fnb FROM transaction_items ti JOIN transactions t ON ti.transaction_id = t.id WHERE DATE(t.created_at) = CURDATE()");
        $rowFnB = $stmtFnB->fetch(PDO::FETCH_ASSOC);
        if ($rowFnB) {
            $today_fnb_count = (int)($rowFnB['total_fnb'] ?? 0);
        }

        // 3 Aktivitas transaksi terbaru
        $stmtAct = $db->query("SELECT t.id, t.status, t.total_cost, t.created_at, t.customer_name, tbl.name as table_name, p.name as package_name 
                               FROM transactions t 
                               LEFT JOIN tables tbl ON t.table_id = tbl.id 
                               LEFT JOIN packages p ON t.package_id = p.id 
                               ORDER BY t.created_at DESC LIMIT 3");
        $recent_activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    // Fallback aman
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3.3 Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Master Unified Stylesheet with Custom Overrides & Cache Busting -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body>
    <!-- AURORA BOREALIS LIGHT EFFECT & TECH DOTS -->
    <div class="aurora-container">
        <div class="aurora-beam aurora-1"></div>
        <div class="aurora-beam aurora-2"></div>
        <div class="aurora-beam aurora-3"></div>
        <div class="aurora-rays"></div>
        <div class="tech-matrix-overlay"></div>
    </div>

    <!-- Top Navigation with User Profile -->
    <header class="dashboard-nav">
        <a href="dashboard.php" class="brand-badge">
            <img src="assets/images/logo.png" alt="PoolStream Logo" onerror="this.src='images/logo.png'">
            <div class="brand-text-wrap">
                <span class="brand-title">PoolStream</span>
                <span class="brand-version-pill">v2.4 Pro</span>
            </div>
        </a>

        <div class="user-nav-profile">
            <!-- Shift Status Chip -->
            <div class="nav-shift-chip">
                <span class="pulse-dot pulse-emerald"></span>
                <span><?php echo $shift_name; ?></span>
            </div>

            <!-- Theme Switcher -->
            <button id="themeToggle" class="theme-toggle-btn" title="Ganti Tema">
                <i class="bi bi-sun-fill" id="themeIcon"></i>
            </button>

            <!-- User Badge -->
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

            <!-- Logout Button -->
            <a href="logout.php" class="logout-btn" title="Keluar dari sesi">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </a>
        </div>
    </header>

    <!-- Hero Header: Modern Executive Split Layout with Bootstrap Grid -->
    <section class="dashboard-hero">
        <div class="container-fluid px-3 px-md-4 px-xl-5">
            <div class="row align-items-center g-4">
                <!-- Left Column: Identity & Status Hub -->
                <div class="col-12 col-lg-7">
                    <div class="hero-status-pills">
                        <span class="status-pill status-pill--live">
                            <span class="status-dot"></span> Sistem Online & Terhubung
                        </span>
                        <span class="status-pill status-pill--relay">
                            <i class="bi bi-cpu-fill"></i> Relay Hardware Ready
                        </span>
                        <span class="status-pill status-pill--tz">
                            <i class="bi bi-globe2"></i> WIB (UTC+7)
                        </span>
                    </div>

                    <h1 class="hero-main-title">
                        Command Center <span class="title-gradient">PoolStream</span>
                    </h1>
                    <p class="hero-main-subtitle">
                        Selamat bertugas, <strong><?php echo htmlspecialchars($user_name); ?></strong>. Sistem billing, timer otomatis 16 meja biliar, dan terminal kasir siap dioperasikan.
                    </p>

                    <!-- Quick Badge Bar -->
                    <div class="hero-meta-badges">
                        <span class="hero-meta-item"><i class="bi bi-shield-check"></i> Hak Akses: <strong><?php echo strtoupper(htmlspecialchars($user_role)); ?></strong></span>
                        <span class="hero-meta-divider">•</span>
                        <span class="hero-meta-item"><i class="bi bi-clock"></i> Sesi: <strong><?php echo $shift_name; ?></strong></span>
                    </div>
                </div>

                <!-- Right Column: Cyber HUD Realtime Clock -->
                <div class="col-12 col-lg-5">
                    <div class="cyber-hud-clock">
                        <div class="hud-clock-top">
                            <span class="hud-label"><i class="bi bi-broadcast"></i> REALTIME CLOCK</span>
                            <span class="hud-live-tag">LIVE SYNC</span>
                        </div>
                        <div class="hud-clock-time" id="liveClock">00:00:00</div>
                        <div class="hud-clock-date" id="liveDate">Memuat Tanggal...</div>
                        <div class="hud-clock-footer">
                            <span class="hud-footer-dot"></span> Sinkronisasi Detik Server Aktif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Animated Wave Effect with Smooth Mask -->
    <div class="wave-container">
        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
             viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs>
                <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
            </defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave" x="48" y="0" />
                <use xlink:href="#gentle-wave" x="48" y="3" />
                <use xlink:href="#gentle-wave" x="48" y="5" />
                <use xlink:href="#gentle-wave" x="48" y="7" />
            </g>
        </svg>
    </div>

    <!-- Dashboard Main Content with Bootstrap Responsive Container -->
    <main class="dashboard-content">
        <div class="container-fluid px-3 px-md-4 px-xl-5">
            <!-- 1. Executive KPI Metrics Ribbon -->
            <section class="kpi-ribbon-section mb-3 mb-md-4">
                <div class="row g-2 g-md-3">
                    <!-- KPI 1: Okupansi Meja -->
                    <div class="col-6 col-xl-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-icon-wrap kpi-icon--emerald">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </div>
                            <div class="kpi-info">
                                <span class="kpi-label">Okupansi Meja</span>
                                <div class="kpi-main-val">
                                    <span class="kpi-val"><?php echo $active_tables; ?></span>
                                    <span class="kpi-denom">/ <?php echo $total_tables; ?> Meja</span>
                                </div>
                                <span class="kpi-subtext text-emerald"><i class="bi bi-arrow-up-right"></i> <?php echo $occupancy_pct; ?>% Terisi</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 2: Omset Transaksi Selesai Hari Ini -->
                    <div class="col-6 col-xl-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-icon-wrap kpi-icon--amber">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div class="kpi-info">
                                <span class="kpi-label">Omset Hari Ini</span>
                                <div class="kpi-main-val">
                                    <span class="kpi-val">Rp <?php echo number_format($today_revenue, 0, ',', '.'); ?></span>
                                </div>
                                <span class="kpi-subtext text-muted"><?php echo $today_tx_count; ?> Transaksi Selesai</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 3: Pesanan FnB Hari Ini -->
                    <div class="col-6 col-xl-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-icon-wrap kpi-icon--cyan">
                                <i class="bi bi-cup-straw"></i>
                            </div>
                            <div class="kpi-info">
                                <span class="kpi-label">Menu FnB Terjual</span>
                                <div class="kpi-main-val">
                                    <span class="kpi-val"><?php echo $today_fnb_count; ?></span>
                                    <span class="kpi-denom">Item</span>
                                </div>
                                <span class="kpi-subtext text-cyan"><i class="bi bi-check2-circle"></i> Terintegrasi Billing</span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI 4: Controller Hardware Relay -->
                    <div class="col-6 col-xl-3">
                        <div class="kpi-card h-100">
                            <div class="kpi-icon-wrap kpi-icon--indigo">
                                <i class="bi bi-motherboard-fill"></i>
                            </div>
                            <div class="kpi-info">
                                <span class="kpi-label">Hardware Relay</span>
                                <div class="kpi-main-val">
                                    <span class="kpi-val">16-Ch</span>
                                </div>
                                <span class="kpi-subtext text-success"><i class="bi bi-circle-fill" style="font-size: 0.55rem;"></i> Sinkron Otomatis</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. 3 Main Menu Cards Section -->
            <section class="menu-cards-section mb-4">
                <div class="row g-4">
                    <!-- 1. Menu Admin -->
                    <div class="col-12 col-md-6 col-lg-4 d-flex">
                        <a href="admin.php" class="menu-card card-admin w-100">
                            <div class="menu-card-header">
                                <span class="card-category-tag">
                                    <span class="pulse-dot pulse-indigo"></span> MANAJEMEN SISTEM
                                </span>
                                <span class="badge-count badge-count--indigo"><?php echo ($user_role === 'admin') ? 'Superuser' : 'Terbatas'; ?></span>
                            </div>

                            <div class="card-hero-row">
                                <div class="card-icon-bubble">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </div>
                                <div class="card-title-group">
                                    <h2 class="menu-card-title">Admin Panel</h2>
                                    <span class="card-subheading">Kontrol Master & Konfigurasi</span>
                                </div>
                            </div>

                            <p class="menu-card-desc">
                                Kelola data pengguna, hak akses, laporan omset terpadu, tarif sewa paket, serta sinkronisasi hardware relay multi-channel.
                            </p>

                            <!-- Capability Chips -->
                            <div class="card-chips-row">
                                <span class="card-chip"><i class="bi bi-sliders2"></i> Relay 16-Ch</span>
                                <span class="card-chip"><i class="bi bi-tags-fill"></i> Kelola Tarif</span>
                                <span class="card-chip"><i class="bi bi-bar-chart-line-fill"></i> Laporan Omset</span>
                            </div>

                            <!-- Live Status Metric Strip -->
                            <div class="card-metric-strip">
                                <div class="metric-item">
                                    <span class="metric-label">Status Sistem</span>
                                    <span class="metric-value text-success"><i class="bi bi-check-circle-fill"></i> Terhubung</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Peran Pengguna</span>
                                    <span class="metric-value"><?php echo strtoupper(htmlspecialchars($user_role)); ?></span>
                                </div>
                            </div>

                            <div class="card-btn-cta">
                                <span>Akses Panel Admin</span>
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                    </div>

                    <!-- 2. Menu Meja -> Navigasi ke meja.php -->
                    <div class="col-12 col-md-6 col-lg-4 d-flex">
                        <a href="meja.php" class="menu-card card-meja card-meja-featured w-100">
                            <div class="menu-card-header">
                                <span class="card-category-tag">
                                    <span class="pulse-dot pulse-emerald"></span> OPERASIONAL REALTIME
                                </span>
                                <span class="badge-count badge-count--emerald"><?php echo $total_tables; ?> Meja Terpasang</span>
                            </div>

                            <div class="card-hero-row">
                                <div class="card-icon-bubble">
                                    <i class="bi bi-grid-3x3-gap-fill"></i>
                                </div>
                                <div class="card-title-group">
                                    <h2 class="menu-card-title">Monitoring Meja</h2>
                                    <span class="card-subheading">Live Visual Billing & Relay</span>
                                </div>
                            </div>

                            <p class="menu-card-desc">
                                Monitoring status 16 meja biliar, kontrol otomatis relay saklar lampu, timer hitung mundur presisi, serta pesanan FnB meja langsung.
                            </p>

                            <!-- Live Occupancy Bar -->
                            <div class="card-occupancy-box">
                                <div class="occupancy-header">
                                    <span class="occupancy-legend">
                                        <span class="legend-dot active"></span> <strong><?php echo $active_tables; ?></strong> Main
                                        <span class="legend-sep">•</span>
                                        <span class="legend-dot ready"></span> <strong><?php echo $ready_tables; ?></strong> Siap
                                    </span>
                                    <span class="occupancy-rate"><?php echo $occupancy_pct; ?>% Terisi</span>
                                </div>
                                <div class="occupancy-bar-track">
                                    <div class="occupancy-bar-fill" style="width: <?php echo max(6, $occupancy_pct); ?>%;"></div>
                                </div>
                            </div>

                            <!-- Capability Chips -->
                            <div class="card-chips-row">
                                <span class="card-chip"><i class="bi bi-stopwatch-fill"></i> Countdown Timer</span>
                                <span class="card-chip"><i class="bi bi-lightning-charge-fill"></i> Relay Otomatis</span>
                                <span class="card-chip"><i class="bi bi-cup-straw"></i> Pesan FnB</span>
                            </div>

                            <div class="card-btn-cta card-btn-cta--primary">
                                <span>Buka Monitoring Meja</span>
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                    </div>

                    <!-- 3. Menu Kasir -->
                    <div class="col-12 col-md-12 col-lg-4 d-flex">
                        <a href="kasir.php" class="menu-card card-kasir w-100">
                            <div class="menu-card-header">
                                <span class="card-category-tag">
                                    <span class="pulse-dot pulse-amber"></span> POINT OF SALE (POS)
                                </span>
                                <span class="badge-count badge-count--amber">POS & FnB Ready</span>
                            </div>

                            <div class="card-hero-row">
                                <div class="card-icon-bubble">
                                    <i class="bi bi-cart-check-fill"></i>
                                </div>
                                <div class="card-title-group">
                                    <h2 class="menu-card-title">Kasir & Billing</h2>
                                    <span class="card-subheading">Checkout & Cetak Nota Struk</span>
                                </div>
                            </div>

                            <p class="menu-card-desc">
                                Terminal kasir cepat untuk pembayaran transaksi sewa meja biliar, pemesanan FnB (makanan/minuman), dan cetak struk nota pelanggan.
                            </p>

                            <!-- Capability Chips -->
                            <div class="card-chips-row">
                                <span class="card-chip"><i class="bi bi-receipt-cutoff"></i> Cetak Struk</span>
                                <span class="card-chip"><i class="bi bi-cash-coin"></i> Multi-Payment</span>
                                <span class="card-chip"><i class="bi bi-clock-history"></i> Rekap Shift</span>
                            </div>

                            <!-- Live Status Metric Strip -->
                            <div class="card-metric-strip">
                                <div class="metric-item">
                                    <span class="metric-label">Terminal Kasir</span>
                                    <span class="metric-value text-amber"><i class="bi bi-hdd-network-fill"></i> Siap Melayani</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-label">Metode Bayar</span>
                                    <span class="metric-value">Cash • QRIS • Transfer</span>
                                </div>
                            </div>

                            <div class="card-btn-cta">
                                <span>Buka Terminal Kasir</span>
                                <i class="bi bi-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </section>

            <!-- 3. Quick Actions & Live Activity Section -->
            <section class="dashboard-aux-section">
                <div class="row g-4">
                    <!-- Left: Recent Activity Feed -->
                    <div class="col-12 col-lg-7">
                        <div class="aux-card aux-activity-card h-100">
                            <div class="aux-card-header">
                                <div class="aux-card-title">
                                    <i class="bi bi-clock-history"></i>
                                    <span>Aktivitas Transaksi Terbaru</span>
                                </div>
                                <a href="meja.php" class="aux-view-all">Monitoring Meja <i class="bi bi-chevron-right"></i></a>
                            </div>
                            <div class="aux-activity-list">
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach ($recent_activities as $act): ?>
                                        <div class="activity-item">
                                            <div class="activity-badge-dot activity-badge-dot--<?php echo ($act['status'] === 'active') ? 'active' : 'completed'; ?>"></div>
                                            <div class="activity-info">
                                                <div class="activity-title-line">
                                                    <strong><?php echo htmlspecialchars($act['table_name'] ?? 'Meja'); ?></strong>
                                                    <span class="activity-package"><?php echo htmlspecialchars($act['package_name'] ?? 'Paket Sewa'); ?></span>
                                                    <span class="activity-badge <?php echo ($act['status'] === 'active') ? 'activity-badge--active' : 'activity-badge--completed'; ?>">
                                                        <?php echo ($act['status'] === 'active') ? 'Sedang Main' : 'Selesai'; ?>
                                                    </span>
                                                </div>
                                                <div class="activity-meta-line">
                                                    <span>Pelanggan: <strong><?php echo htmlspecialchars($act['customer_name'] ?? '-'); ?></strong></span>
                                                    <span>•</span>
                                                    <span>Rp <?php echo number_format($act['total_cost'] ?? 0, 0, ',', '.'); ?></span>
                                                    <span>•</span>
                                                    <span><?php echo date('H:i', strtotime($act['created_at'])); ?> WIB</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="activity-empty">
                                        <i class="bi bi-inbox"></i> Belum ada aktivitas transaksi yang tercatat hari ini.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Quick Operational Shortcuts -->
                    <div class="col-12 col-lg-5">
                        <div class="aux-card aux-shortcuts-card h-100">
                            <div class="aux-card-header">
                                <div class="aux-card-title">
                                    <i class="bi bi-lightning-charge-fill"></i>
                                    <span>Pintasan Cepat Operasional</span>
                                </div>
                                <span class="aux-badge-ready">Sistem Siap</span>
                            </div>
                            <div class="shortcuts-grid">
                                <a href="meja.php" class="shortcut-btn">
                                    <div class="shortcut-icon shortcut-icon--emerald"><i class="bi bi-stopwatch"></i></div>
                                    <div class="shortcut-text">
                                        <span class="shortcut-title">Mulai Meja Baru</span>
                                        <span class="shortcut-sub">Pilih meja & paket</span>
                                    </div>
                                </a>
                                <a href="kasir.php" class="shortcut-btn">
                                    <div class="shortcut-icon shortcut-icon--amber"><i class="bi bi-receipt"></i></div>
                                    <div class="shortcut-text">
                                        <span class="shortcut-title">Terminal Kasir</span>
                                        <span class="shortcut-sub">Checkout & struk</span>
                                    </div>
                                </a>
                                <a href="admin.php" class="shortcut-btn">
                                    <div class="shortcut-icon shortcut-icon--indigo"><i class="bi bi-file-earmark-bar-graph"></i></div>
                                    <div class="shortcut-text">
                                        <span class="shortcut-title">Laporan Keuangan</span>
                                        <span class="shortcut-sub">Rekap omset harian</span>
                                    </div>
                                </a>
                                <button type="button" class="shortcut-btn shortcut-btn--sync" onclick="location.reload();">
                                    <div class="shortcut-icon shortcut-icon--cyan"><i class="bi bi-arrow-repeat"></i></div>
                                    <div class="shortcut-text">
                                        <span class="shortcut-title">Refresh Realtime</span>
                                        <span class="shortcut-sub">Sinkronisasi status</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- Footer with System Metadata -->
    <footer class="dashboard-footer">
        <div class="footer-inner">
            <div class="footer-left">
                &copy; <?php echo date('Y'); ?> <strong>PoolStream</strong>. Sistem Billing & Monitoring Biliar Terpadu.
            </div>
            <div class="footer-right">
                <span class="footer-meta-tag"><i class="bi bi-hdd-rack-fill"></i> Controller: <strong>Active (USB)</strong></span>
                <span class="footer-meta-tag"><i class="bi bi-clock-history"></i> Zona: <strong>WIB (UTC+7)</strong></span>
                <span class="footer-meta-tag"><i class="bi bi-shield-check"></i> Build: <strong>v2.4 Pro</strong></span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5.3.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Clock & Theme Scripts -->
    <script>
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const clockEl = document.getElementById('liveClock');
            if (clockEl) clockEl.textContent = `${hours}:${minutes}:${seconds}`;

            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            const dateStr = now.toLocaleDateString('id-ID', options);
            const dateEl = document.getElementById('liveDate');
            if (dateEl) dateEl.textContent = dateStr;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Theme Switcher
        const toggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const savedTheme = localStorage.getItem('poolstream_theme') || 'dark';

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-bs-theme', theme);
            if (themeIcon) themeIcon.className = (theme === 'dark') ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
            localStorage.setItem('poolstream_theme', theme);
        }

        applyTheme(savedTheme);

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-bs-theme');
                applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
            });
        }
    </script>
</body>
</html>

