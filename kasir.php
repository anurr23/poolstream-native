<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Kasir';
$user_role = $_SESSION['user_role'] ?? 'kasir';
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kasir & Billing (F&B) - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Master Unified Stylesheet with Cache Busting -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body>
    <!-- Ambient Aurora Background Glows -->
    <div class="aurora-container">
        <div class="aurora-beam aurora-1"></div>
        <div class="aurora-beam aurora-2"></div>
        <div class="aurora-beam aurora-3"></div>
    </div>

    <!-- Top Navbar -->
    <header class="dashboard-nav px-3 px-md-4 px-xl-5">
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <a href="dashboard.php" class="bb-btn bb-btn--ghost bb-btn--sm nav-back-btn" title="Kembali ke Dashboard">
                <i class="bi bi-arrow-left"></i> <span>Dashboard</span>
            </a>
            <a href="dashboard.php" class="brand-badge">
                <img src="assets/images/logo.png" alt="PoolStream" onerror="this.src='images/logo.png'">
                <div class="brand-text-wrap">
                    <span class="brand-title">PoolStream</span>
                    <span class="brand-version-pill">POS & Billing</span>
                </div>
            </a>
        </div>

        <div class="user-nav-profile">
            <!-- Realtime Clock Pill in Navbar -->
            <div class="header-clock-pill">
                <i class="bi bi-clock"></i>
                <span id="headerClock">Memuat waktu...</span>
            </div>

            <!-- Theme Toggle -->
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

            <!-- Logout -->
            <a href="logout.php" class="logout-btn" title="Keluar">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </a>
        </div>
    </header>

    <!-- Main Kasir Section -->
    <main class="kasir-main-container container-fluid px-3 px-md-4 px-xl-5 py-3 py-md-4">
        <!-- Title & Stats Strip -->
        <div class="kasir-header-strip mb-3 mb-md-4">
            <div class="d-flex align-items-center gap-3">
                <div class="kasir-icon-badge">
                    <i class="bi bi-cart-check-fill"></i>
                </div>
                <div>
                    <h1 class="kasir-title mb-0">Kasir & Billing F&B</h1>
                    <p class="kasir-subtitle text-muted mb-0">Pesanan makanan & minuman langsung tanpa sesi meja biliar</p>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="kasir-stats-pills d-none d-md-flex align-items-center gap-2">
                <span class="status-pill status-pill--live">
                    <span class="status-dot"></span> Terminal POS Aktif
                </span>
                <span class="status-pill status-pill--relay">
                    <i class="bi bi-receipt"></i> Struk Thermal 80mm
                </span>
            </div>
        </div>

        <!-- Controls Bar: Tabs, Search & Action -->
        <div class="kasir-controls-bar mb-3 mb-md-4">
            <!-- Left: Tab Switcher (Aktif vs Riwayat) -->
            <div class="kasir-tabs-group">
                <button type="button" id="tabBtnActive" class="kasir-tab-btn kasir-tab-btn--active" onclick="switchOrderTab('active')">
                    <i class="bi bi-play-circle-fill me-1"></i> Pesanan Aktif
                    <span id="activeCountBadge" class="kasir-badge-pill">0</span>
                </button>
                <button type="button" id="tabBtnHistory" class="kasir-tab-btn" onclick="switchOrderTab('history')">
                    <i class="bi bi-clock-history me-1"></i> Riwayat
                    <span id="historyCountBadge" class="kasir-badge-pill">0</span>
                </button>
            </div>

            <!-- Right: Search, Filter Preset & New Order CTA -->
            <div class="kasir-actions-group">
                <!-- Preset Filter (Only on History Tab) -->
                <div id="historyPresetWrapper" class="dropdown d-none">
                    <button class="bb-btn bb-btn--sm bb-btn--ghost dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="presetDropdownBtn">
                        <i class="bi bi-calendar3 me-1"></i> <span id="currentPresetLabel">Hari Ini</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 0.75rem;">
                        <li><button class="dropdown-item py-2 px-3 active" onclick="setHistoryPreset('hari_ini', 'Hari Ini', this)">Hari Ini</button></li>
                        <li><button class="dropdown-item py-2 px-3" onclick="setHistoryPreset('7_hari', '7 Hari Terakhir', this)">7 Hari Terakhir</button></li>
                        <li><button class="dropdown-item py-2 px-3" onclick="setHistoryPreset('30_hari', '30 Hari Terakhir', this)">30 Hari Terakhir</button></li>
                        <li><button class="dropdown-item py-2 px-3" onclick="setHistoryPreset('bulan_ini', 'Bulan Ini', this)">Bulan Ini</button></li>
                    </ul>
                </div>

                <!-- Search Input -->
                <div class="kasir-search-wrapper">
                    <i class="bi bi-search kasir-search-icon"></i>
                    <input type="text" id="orderSearchInput" class="custom-input kasir-search-input" placeholder="Cari pelanggan..." oninput="onSearchInput(this.value)">
                </div>

                <!-- New Order Button -->
                <button type="button" class="bb-btn bb-btn--sm bb-btn--warning kasir-new-btn flex-shrink-0" onclick="openNewOrderModal()">
                    <i class="bi bi-plus-lg me-1"></i> <span>Pesanan Baru</span>
                </button>
            </div>
        </div>

        <!-- TAB 1: ACTIVE ORDERS CONTAINER -->
        <section id="activeOrdersSection">
            <div id="activeOrdersGrid" class="row g-3">
                <div class="col-12 text-center py-5 text-muted opacity-75">
                    <i class="bi bi-arrow-repeat spin fs-2 d-block mb-2"></i>
                    <span>Memuat pesanan aktif...</span>
                </div>
            </div>
        </section>

        <!-- TAB 2: HISTORY ORDERS CONTAINER -->
        <section id="historyOrdersSection" class="d-none">
            <div id="historyOrdersGrid" class="row g-3">
                <div class="col-12 text-center py-5 text-muted opacity-75">
                    <i class="bi bi-arrow-repeat spin fs-2 d-block mb-2"></i>
                    <span>Memuat riwayat transaksi...</span>
                </div>
            </div>
        </section>
    </main>

    <!-- ============================================================
         MODAL 1: BUAT PESANAN F&B BARU (CREATE NEW ORDER)
         ============================================================ -->
    <div id="newOrderModal" class="bb-modal-backdrop" style="display: none;">
        <div class="bb-modal" style="max-width: 960px; width: 95%;">
            <div class="bb-modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-cup-hot-fill" style="color: #f59e0b; font-size: 1.3rem;"></i>
                    <div>
                        <h5 class="mb-0 fw-bold">Pesan F&B Baru</h5>
                        <small class="text-muted">Pesanan kasir tanpa sesi meja biliar</small>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeNewOrderModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="bb-modal-body">
                <form id="newOrderForm" onsubmit="submitNewOrder(event)">
                    <div class="modal-split-grid">
                        <!-- Left Column: Catalog F&B -->
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-warning small">
                                    <i class="bi bi-grid-fill me-1"></i> Pilih Menu Makanan & Minuman
                                </span>
                            </div>

                            <!-- Search & Filter -->
                            <div class="position-relative mb-2">
                                <input type="text" id="catalogSearchInput" class="custom-input" placeholder="Cari menu F&B..." style="height: 38px; padding-left: 34px;" oninput="onCatalogSearch(this.value)">
                                <i class="bi bi-search position-absolute top-50 translate-middle-y text-muted" style="left: 12px; font-size: 0.85rem;"></i>
                            </div>

                            <!-- Category Pills -->
                            <div class="category-pill-scroll mb-2" id="catalogCategoryPills">
                                <button type="button" class="category-pill category-pill--active" onclick="filterCatalogCat('all', this)">Semua</button>
                            </div>

                            <!-- Menu Items Grid -->
                            <div class="fnb-catalog-grid" id="newOrderCatalogGrid">
                                <div class="text-center py-4 text-muted col-span-2">Memuat menu...</div>
                            </div>
                        </div>

                        <!-- Right Column: Cart & Customer -->
                        <div class="d-flex flex-column h-100">
                            <!-- Customer Name Input -->
                            <div class="form-group-custom mb-3">
                                <label class="custom-label">Nama Pelanggan <span class="text-danger">*</span></label>
                                <input type="text" id="newCustomerName" class="custom-input" placeholder="Contoh: Andi Wijaya" style="height: 42px; font-weight: 500;" required autocomplete="off">
                            </div>

                            <!-- Cart Items List Header -->
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold small">
                                    <i class="bi bi-basket-fill text-emerald me-1"></i> Keranjang Item
                                </span>
                                <span id="cartItemsCountBadge" class="badge bg-warning text-dark rounded-pill">0 item</span>
                            </div>

                            <!-- Cart Items Scroll Area -->
                            <div class="kasir-cart-list flex-grow-1 mb-3" id="newOrderCartList" style="max-height: 250px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted small opacity-75 border rounded-3 p-3">
                                    <i class="bi bi-cart-x fs-3 d-block mb-1"></i>
                                    Keranjang masih kosong.<br>Klik item menu di sebelah kiri untuk menambahkan.
                                </div>
                            </div>

                            <!-- Total Cost Preview Box -->
                            <div class="p-3 rounded-3 mb-3 kasir-total-box">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold text-warning">Total Tagihan</span>
                                    <span class="fw-bold fs-5 text-warning" id="newOrderTotalDisplay">Rp 0</span>
                                </div>
                            </div>

                            <!-- Footer Buttons -->
                            <div class="d-flex gap-2 mt-auto">
                                <button type="button" class="bb-btn bb-btn--ghost flex-grow-1" onclick="closeNewOrderModal()">
                                    Batal
                                </button>
                                <button type="submit" class="bb-btn bb-btn--warning flex-grow-1">
                                    <i class="bi bi-check2-circle me-1"></i> Simpan Pesanan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MODAL 2: KELOLA PESANAN AKTIF / DETAIL RIWAYAT & CHECKOUT
         ============================================================ -->
    <div id="detailModal" class="bb-modal-backdrop" style="display: none;">
        <div class="bb-modal" style="max-width: 960px; width: 95%;">
            <div class="bb-modal-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-receipt" style="color: #6366f1; font-size: 1.3rem;"></i>
                    <div>
                        <h5 class="mb-0 fw-bold" id="detailModalTitle">Detail Pesanan #0</h5>
                        <small class="text-muted" id="detailModalSubtitle">Status Pesanan</small>
                    </div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeDetailModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="bb-modal-body" id="detailModalBody">
                <!-- Dynamically injected via JS -->
            </div>
        </div>
    </div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Kasir Client Script with Cache Busting -->
    <script src="assets/js/kasir.js?v=<?php echo filemtime(__DIR__ . '/assets/js/kasir.js'); ?>"></script>
</body>
</html>
