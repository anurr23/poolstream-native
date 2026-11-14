<?php
require_once __DIR__ . '/config/config.php';
$database = new Database();
$db = $database->getConnection();

if (isLoggedIn()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin' || $role === 'kasir') {
        header('Location: dashboard.php');
        exit();
    }
}
$isLoggedIn = isLoggedIn();
$user_role = $_SESSION['user_role'] ?? '';
$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? '';
$user_id = $_SESSION['user_id'] ?? '';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_table') {
    if (!$isLoggedIn) {
        header('Location: login.php');
        exit();
    } else {
        $tableId = sanitizeInput($_POST['table_id'] ?? '');
        $packageId = sanitizeInput($_POST['package_id'] ?? '');
        $durationHours = floatval($_POST['duration_hours'] ?? 1);
        $customerName = $user_name;

        if (empty($tableId) || empty($packageId) || $durationHours <= 0) {
            $error_message = 'Pilih meja dan paket biliar dengan benar.';
        } else {
            try {
                $txModel = new Transaction($db);
                $tblStmt = $db->prepare("SELECT status FROM tables WHERE id = :id LIMIT 1");
                $tblStmt->execute([':id' => $tableId]);
                $tbl = $tblStmt->fetch(PDO::FETCH_ASSOC);

                if (!$tbl || $tbl['status'] === 'active') {
                    $error_message = 'Maaf, meja tersebut sedang digunakan atau tidak tersedia.';
                } else {
                    $txId = $txModel->startSession($tableId, $user_id, $customerName, $packageId, $durationHours, []);
                    $success_message = 'Booking meja berhasil! Lampu meja telah dinyalakan secara otomatis.';
                }
            } catch (Exception $e) {
                $error_message = 'Gagal melakukan booking: ' . $e->getMessage();
            }
        }
    }
}

$tables = [];
$packages = [];

if ($db) {
    $txModel = new Transaction($db);
    $txModel->autoCheckoutExpired();

    $tableModel = new Table($db);
    $tables = $tableModel->getAllWithActiveAndRecentTransactions();

    $packageModel = new Package($db);
    $packages = $packageModel->getAll();
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PoolStream - Premium Billiard Booking & Lounge</title>
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
            <a href="index.php" class="text-body text-decoration-none small fw-bold">Beranda</a>
            <a href="meja-list.php" class="text-secondary text-decoration-none small">Seluruh Meja</a>
            <?php if ($isLoggedIn && $user_role === 'customer'): ?>
                <a href="pesanan-saya.php" class="text-secondary text-decoration-none small">Pesanan Saya</a>
            <?php endif; ?>
        </div>

        <div class="user-nav-profile">
            <button id="themeToggle" class="theme-toggle-btn" title="Ganti Tema">
                <i class="bi bi-sun-fill" id="themeIcon"></i>
            </button>

            <?php if ($isLoggedIn): ?>
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
            <?php else: ?>
                <a href="login.php" class="bb-btn bb-btn--sm bb-btn--primary text-decoration-none">
                    <i class="bi bi-box-arrow-in-right"></i> Masuk / Login
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="min-vh-100 d-flex align-items-center position-relative px-3 px-md-4 px-xl-5" style="padding-top: 60px; padding-bottom: 60px;">
        <div class="container-fluid">
            <div class="row align-items-center g-5">
                <div class="col-12 col-lg-7 text-center text-lg-start">
                    <div class="hero-status-pills justify-content-center justify-content-lg-start mb-3">
                        <span class="status-pill status-pill--live">
                            <span class="status-dot"></span> 16 Meja Professional Terpasang
                        </span>
                        <span class="status-pill status-pill--relay">
                            <i class="bi bi-lightning-charge-fill"></i> IoT Smart Lighting Relay
                        </span>
                    </div>

                    <h1 class="display-4 fw-bold mb-3" style="letter-spacing: -0.02em; line-height: 1.15;">
                        Sensasi Bermain Biliar <span class="title-gradient">Kelas Dunia</span> Tanpa Antre
                    </h1>

                    <p class="lead text-muted mb-4" style="max-width: 600px; font-size: 1.05rem;">
                        PoolStream menghadirkan pengalaman bermain biliar modern dengan sistem billing otomatis, pencahayaan IoT pintar, serta pemesanan makanan dan minuman langsung ke meja Anda.
                    </p>

                    <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                        <a href="meja-list.php" class="bb-btn bb-btn--success text-decoration-none py-2 px-4" style="font-size: 1rem;">
                            <i class="bi bi-grid-3x3-gap-fill me-2"></i> Lihat Seluruh Meja
                        </a>
                        <a href="#preview-tables" class="bb-btn bb-btn--ghost text-decoration-none py-2 px-4" style="font-size: 1rem;">
                            <i class="bi bi-arrow-down-circle me-2"></i> Preview Beberapa Meja
                        </a>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="position-relative p-4 rounded-4 shadow-lg bg-body border border-secondary border-opacity-25" style="backdrop-filter: blur(12px);">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h5 class="fw-bold mb-0"><i class="bi bi-speedometer2 text-success me-2"></i> Status Lounge Live</h5>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">Online</span>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25">
                                    <span class="text-muted d-block small">Total Meja</span>
                                    <h3 class="fw-bold mb-0 text-body">16 Meja</h3>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-3 rounded-3 bg-secondary bg-opacity-10 border border-secondary border-opacity-25">
                                    <span class="text-muted d-block small">Sistem Timer</span>
                                    <h3 class="fw-bold mb-0 text-success">Presisi 1s</h3>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-top border-secondary border-opacity-25 d-flex align-items-center justify-content-between text-muted small">
                            <span><i class="bi bi-shield-check text-emerald me-1"></i> Keamanan Transaksi Terjamin</span>
                            <span>WIB (UTC+7)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ABOUT SECTION -->
    <section class="py-5 px-3 px-md-4 px-xl-5 bg-secondary bg-opacity-10 border-top border-secondary border-opacity-25">
        <div class="container-fluid py-4">
            <div class="text-center mb-5">
                <span class="text-success text-uppercase tracking-wider fw-bold small">FITUR UNGGULAN</span>
                <h2 class="fw-bold mt-2">Mengapa Memilih PoolStream?</h2>
                <p class="text-muted mx-auto" style="max-width: 600px;">Platform manajemen biliar terpadu yang dirancang untuk kenyamanan pemain dan efisiensi operasional.</p>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <div class="p-4 rounded-4 h-100 bg-body border border-secondary border-opacity-25 shadow-sm">
                        <div class="mb-3 text-success" style="font-size: 2rem;"><i class="bi bi-calendar-check-fill"></i></div>
                        <h4 class="fw-bold mb-2">Booking Online Mandiri</h4>
                        <p class="text-muted mb-0">Pesan meja biliar pilihan Anda langsung dari smartphone atau komputer tanpa perlu antre di lokasi.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-4 rounded-4 h-100 bg-body border border-secondary border-opacity-25 shadow-sm">
                        <div class="mb-3 text-amber" style="font-size: 2rem;"><i class="bi bi-lightning-charge-fill"></i></div>
                        <h4 class="fw-bold mb-2">IoT Relay Otomatis</h4>
                        <p class="text-muted mb-0">Lampu meja biliar menyala dan padam secara otomatis sesuai durasi sewa melalui kontrol relay hardware pintar.</p>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="p-4 rounded-4 h-100 bg-body border border-secondary border-opacity-25 shadow-sm">
                        <div class="mb-3 text-cyan" style="font-size: 2rem;"><i class="bi bi-cup-hot-fill"></i></div>
                        <h4 class="fw-bold mb-2">Pesan FnB Terintegrasi</h4>
                        <p class="text-muted mb-0">Nikmati berbagai pilihan makanan dan minuman ringan favorit yang diantar langsung ke meja biliar Anda.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PREVIEW BEBERAPA MEJA SECTION (UNTUK MEMPERPANJANG SCROLL) -->
    <section id="preview-tables" class="py-5 px-3 px-md-4 px-xl-5" style="border-top: 1px solid rgba(255,255,255,0.05);">
        <div class="container-fluid py-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <span class="text-success text-uppercase tracking-wider fw-bold small">PREVIEW MEJA</span>
                    <h2 class="fw-bold mt-1 mb-0">Beberapa Pilihan Meja Populer</h2>
                </div>
                <a href="meja-list.php" class="bb-btn bb-btn--sm bb-btn--success text-decoration-none">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Lihat Seluruh 16 Meja
                </a>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger custom-alert mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i> <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success custom-alert mb-4" role="alert" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: #10b981;">
                    <i class="bi bi-check-circle-fill"></i> <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <div class="row g-3">
                <?php 
                $previewCount = 0;
                foreach ($tables as $tbl): 
                    $previewCount++;
                    if ($previewCount > 4) break; // Tampilkan 4 meja saja sebagai preview
                    $isActive = ($tbl['status'] === 'active');
                    $activeTx = $tbl['transactions'][0] ?? null;
                ?>
                    <div class="col-12 col-sm-6 col-md-4 col-xl-3">
                        <div class="card h-100 border border-secondary border-opacity-25 shadow-sm bg-body" style="border-radius: 12px; overflow: hidden;">
                            <div class="card-body p-3 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="card-title mb-0 text-body" style="font-weight: 700; font-size: 1.1rem;">
                                            <?php echo htmlspecialchars($tbl['name']); ?>
                                        </h5>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-danger text-white" style="font-size: 0.7rem; padding: 4px 8px;">Tidak Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-success text-white" style="font-size: 0.7rem; padding: 4px 8px;">Available</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-muted" style="font-size: 0.85rem; margin-bottom: 12px;">
                                        <?php if ($isActive && $activeTx): ?>
                                            <p class="mb-1 text-danger"><i class="bi bi-person-fill"></i> Pemain: <?php echo htmlspecialchars($activeTx['customer_name']); ?></p>
                                            <p class="mb-0"><i class="bi bi-clock-history"></i> Paket: <?php echo htmlspecialchars($activeTx['package_name']); ?></p>
                                        <?php else: ?>
                                            <p class="mb-0 text-success"><i class="bi bi-check-circle"></i> Meja siap dibooking & lampu siap aktif.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div>
                                    <?php if (!$isActive): ?>
                                        <?php if ($isLoggedIn && $user_role === 'customer'): ?>
                                            <button type="button" class="bb-btn bb-btn--sm bb-btn--success w-100" onclick="openBookingModal('<?php echo $tbl['id']; ?>', '<?php echo htmlspecialchars($tbl['name']); ?>')">
                                                <i class="bi bi-calendar-check-fill me-1"></i> Booking Meja Ini
                                            </button>
                                        <?php else: ?>
                                            <a href="login.php" class="bb-btn bb-btn--sm bb-btn--primary w-100 text-center text-decoration-none">
                                                <i class="bi bi-box-arrow-in-right me-1"></i> Login untuk Booking
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button type="button" class="bb-btn bb-btn--sm w-100" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); cursor: not-allowed;" disabled>
                                            <i class="bi bi-lock-fill me-1"></i> Sedang Digunakan
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="meja-list.php" class="bb-btn bb-btn--success py-3 px-5 text-decoration-none" style="font-size: 1.1rem;">
                    <i class="bi bi-grid-3x3-gap-fill me-2"></i> Lihat Seluruh 16 Meja Biliar
                </a>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="py-3 text-center text-muted small border-top border-secondary border-opacity-25 bg-body mt-auto">
        <div class="container-fluid">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved. IoT Billiard Command Center.</p>
        </div>
    </footer>

    <!-- MODAL: BOOKING MEJA ONLINE -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: #1e293b; color: #f8fafc; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px;">
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="book_table">
                    <input type="hidden" name="table_id" id="modalTableId">

                    <div class="modal-header border-bottom border-secondary">
                        <h5 class="modal-title fw-bold" id="bookingModalTitle">Booking Meja</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label class="form-label text-muted">Nama Pemesan</label>
                            <input type="text" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Pilih Paket Biliar *</label>
                            <select name="package_id" class="form-select bg-dark text-white border-secondary" required>
                                <option value="">-- Pilih Paket --</option>
                                <?php foreach ($packages as $pkg): ?>
                                    <option value="<?php echo $pkg['id']; ?>">
                                        <?php echo htmlspecialchars($pkg['name']); ?> (Rp <?php echo number_format($pkg['price'], 0, ',', '.'); ?>/jam)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durasi Main (Jam) *</label>
                            <input type="number" name="duration_hours" class="form-control bg-dark text-white border-secondary" step="0.5" min="0.5" value="1" required>
                        </div>

                        <div class="alert alert-info py-2 px-3 mb-0" style="font-size: 0.85rem; background: rgba(99,102,241,0.1); border-color: rgba(99,102,241,0.2); color: #818cf8;">
                            <i class="bi bi-info-circle-fill me-1"></i> Dengan melakukan booking, lampu meja biliar akan otomatis menyala dan sesi Anda langsung tercatat.
                        </div>
                    </div>

                    <div class="modal-footer border-top border-secondary">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-check-circle me-1"></i> Konfirmasi Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openBookingModal(tableId, tableName) {
            document.getElementById('modalTableId').value = tableId;
            document.getElementById('bookingModalTitle').innerText = 'Booking Meja: ' + tableName;
            var myModal = new bootstrap.Modal(document.getElementById('bookingModal'));
            myModal.show();
        }

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

        // Init icon state on load
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
