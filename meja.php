<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$user_name = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Admin';
$user_role = $_SESSION['user_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Meja - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Master Unified Stylesheet with Cache Busting -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body>
    <!-- Top Navbar -->
    <header class="dashboard-nav px-3 px-md-4 px-xl-5">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="dashboard.php" class="bb-btn bb-btn--ghost bb-btn--sm nav-back-btn" title="Kembali ke Dashboard Utama">
                <i class="bi bi-arrow-left"></i> <span>Menu Utama</span>
            </a>
            <a href="dashboard.php" class="brand-badge">
                <img src="assets/images/logo.png" alt="PoolStream" onerror="this.src='images/logo.png'">
                <span>PoolStream</span>
            </a>
        </div>

        <div class="user-nav-profile">
            <!-- Realtime Clock Pill in Navbar -->
            <div class="header-clock-pill">
                <i class="bi bi-calendar3"></i>
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

    <!-- Controls & Filter Section -->
    <div class="dashboard-controls px-3 px-md-4 px-xl-5">
        <div class="filter-btn-group d-flex flex-wrap gap-2">
            <button class="bb-btn bb-btn--sm bb-btn--success filter-btn" data-filter="all">SEMUA MEJA</button>
            <button class="bb-btn bb-btn--sm bb-btn--ghost filter-btn" data-filter="active">SEDANG JALAN</button>
            <button class="bb-btn bb-btn--sm bb-btn--ghost filter-btn" data-filter="ready">KOSONG</button>
        </div>

        <div>
            <span class="status-pill" style="margin-bottom: 0;">
                <span class="status-dot"></span> 16 Meja Terhubung
            </span>
        </div>
    </div>

    <!-- Main Grid 16 Meja -->
    <main class="tables-grid-container px-3 px-md-4 px-xl-5" id="tablesGrid">
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            <i class="bi bi-arrow-repeat spin"></i> Memuat data meja...
        </div>
    </main>

    <!-- ============================================================
         MODAL 1: RIWAYAT MEJA (TABLE HISTORY)
         ============================================================ -->
    <div id="historyModal" class="bb-modal-backdrop" style="display: none;">
        <div class="bb-modal">
            <div class="bb-modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-clock-history" style="color: #10b981; font-size: 1.3rem;"></i>
                    <h5 style="margin: 0; font-weight: 700;" id="historyModalTitle">Riwayat Meja</h5>
                </div>
                <button class="modal-close-btn" onclick="closeHistoryModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div style="padding: 12px 28px 0;">
                <div style="position: relative;">
                    <input type="text" id="historySearchInput" class="custom-input" placeholder="Cari nama pelanggan..." 
                           style="height: 38px; padding-left: 36px;" oninput="state.historySearch = this.value; renderHistoryCards();">
                    <i class="bi bi-search" style="position: absolute; left: 12px; top: 11px; color: #94a3b8;"></i>
                </div>
            </div>

            <div class="bb-modal-body">
                <div class="history-cards-grid" id="historyListContainer"></div>
            </div>

            <div class="bb-modal-footer" style="justify-content: space-between;">
                <button class="bb-btn bb-btn--ghost" onclick="closeHistoryModal()">Tutup</button>
                <div id="historyModalActionBtn"></div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MODAL 2: MULAI SESI BARU (START ORDER)
         ============================================================ -->
    <div id="orderModal" class="bb-modal-backdrop" style="display: none;">
        <div class="bb-modal">
            <div class="bb-modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-play-circle-fill" style="color: #10b981; font-size: 1.3rem;"></i>
                    <h5 style="margin: 0; font-weight: 700;" id="orderModalTitle">Mulai Sesi Meja</h5>
                </div>
                <button class="modal-close-btn" onclick="closeStartOrderModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="bb-modal-body">
                <form id="startOrderForm" onsubmit="submitStartOrder(event)">
                    <div class="modal-split-grid">
                        <!-- Left: FnB Catalog -->
                        <div>
                            <div style="font-weight: 600; font-size: 0.88rem; margin-bottom: 8px; color: #f59e0b;">
                                <i class="bi bi-cup-hot-fill me-1"></i> Pesan F&B (Opsional)
                            </div>

                            <div style="position: relative; margin-bottom: 10px;">
                                <input type="text" class="custom-input" placeholder="Cari makanan / minuman..." style="height: 36px; padding-left: 32px;"
                                       oninput="state.fnbSearch = this.value; renderFnbCatalog('orderFnbCatalog', addDraftItem);">
                                <i class="bi bi-search" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 0.85rem;"></i>
                            </div>

                            <div class="category-pill-scroll">
                                <button type="button" class="category-pill category-pill--active" onclick="setFnbCat(this, 'all', 'orderFnbCatalog', addDraftItem)">Semua</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Minuman Dingin', 'orderFnbCatalog', addDraftItem)">Minuman Dingin</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Minuman Panas', 'orderFnbCatalog', addDraftItem)">Minuman Panas</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Snack', 'orderFnbCatalog', addDraftItem)">Snack</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Makanan', 'orderFnbCatalog', addDraftItem)">Makanan</button>
                            </div>

                            <div class="fnb-catalog-grid" id="orderFnbCatalog"></div>
                        </div>

                        <!-- Right: Session Inputs & Summary -->
                        <div>
                            <div class="form-group-custom" style="margin-bottom: 12px;">
                                <label class="custom-label">Nama Pelanggan *</label>
                                <input type="text" id="orderCustomerName" class="custom-input" style="height: 42px; padding: 0 14px;" placeholder="Contoh: Budi Santoso" required>
                            </div>

                            <div class="form-group-custom" style="margin-bottom: 12px;">
                                <label class="custom-label">Pilih Paket Biliar *</label>
                                <select id="orderPackageSelect" class="custom-input" style="height: 42px; padding: 0 14px;" onchange="renderOrderSummary()"></select>
                            </div>

                            <div class="form-group-custom" style="margin-bottom: 12px;">
                                <label class="custom-label">Durasi Main (Jam)</label>
                                <input type="number" id="orderDuration" class="custom-input" style="height: 42px; padding: 0 14px;" step="0.5" min="0.5" value="1" oninput="renderOrderSummary()">
                            </div>

                            <!-- Selected Items / Cost Summary -->
                            <div class="order-summary-box" id="orderSelectedItems"></div>
                        </div>
                    </div>

                    <div class="bb-modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 20px;">
                        <button type="button" class="bb-btn bb-btn--ghost" onclick="closeStartOrderModal()">Batal</button>
                        <button type="submit" class="bb-btn bb-btn--success">
                            <i class="bi bi-play-circle-fill"></i> Mulai Main & Nyalakan Lampu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ============================================================
         MODAL 3: KELOLA SESI AKTIF & CHECKOUT
         ============================================================ -->
    <div id="sessionModal" class="bb-modal-backdrop" style="display: none;">
        <div class="bb-modal">
            <div class="bb-modal-header">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="bi bi-gear-fill" style="color: #6366f1; font-size: 1.3rem;"></i>
                    <h5 style="margin: 0; font-weight: 700;" id="sessionModalTitle">Kelola Sesi Meja</h5>
                </div>
                <button class="modal-close-btn" onclick="closeSessionModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="modal-tabs">
                <button id="tabBtnEdit" class="modal-tab-btn modal-tab-btn--active" onclick="switchSessionTab('edit')">
                    <i class="bi bi-pencil-square me-1"></i> Edit Sesi & Pesanan
                </button>
                <button id="tabBtnCheckout" class="modal-tab-btn" onclick="switchSessionTab('checkout')">
                    <i class="bi bi-cash-stack me-1"></i> Checkout
                </button>
            </div>

            <div class="bb-modal-body">
                <!-- TAB 1: EDIT SESI -->
                <div id="tabContentEdit">
                    <div class="modal-split-grid">
                        <div>
                            <div style="font-weight: 600; font-size: 0.88rem; margin-bottom: 8px; color: #f59e0b;">
                                <i class="bi bi-cup-hot-fill me-1"></i> Tambah Menu F&B
                            </div>

                            <div style="position: relative; margin-bottom: 10px;">
                                <input type="text" class="custom-input" placeholder="Cari menu F&B..." style="height: 36px; padding-left: 32px;"
                                       oninput="state.fnbSearch = this.value; renderFnbCatalog('editFnbCatalog', addEditItem);">
                                <i class="bi bi-search" style="position: absolute; left: 10px; top: 10px; color: #94a3b8; font-size: 0.85rem;"></i>
                            </div>

                            <div class="category-pill-scroll">
                                <button type="button" class="category-pill category-pill--active" onclick="setFnbCat(this, 'all', 'editFnbCatalog', addEditItem)">Semua</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Minuman Dingin', 'editFnbCatalog', addEditItem)">Minuman Dingin</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Minuman Panas', 'editFnbCatalog', addEditItem)">Minuman Panas</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Snack', 'editFnbCatalog', addEditItem)">Snack</button>
                                <button type="button" class="category-pill" onclick="setFnbCat(this, 'Makanan', 'editFnbCatalog', addEditItem)">Makanan</button>
                            </div>

                            <div class="fnb-catalog-grid" id="editFnbCatalog"></div>
                        </div>

                        <div>
                            <div class="form-group-custom" style="margin-bottom: 12px;">
                                <label class="custom-label">Paket Biliar</label>
                                <select id="editPackageSelect" class="custom-input" style="height: 42px; padding: 0 14px;" onchange="renderEditSummary(); renderCheckoutSummary();"></select>
                            </div>

                            <div class="form-group-custom" style="margin-bottom: 12px;">
                                <label class="custom-label">Durasi Main (Jam)</label>
                                <input type="number" id="editDuration" class="custom-input" style="height: 42px; padding: 0 14px;" step="0.5" min="0.5" oninput="renderEditSummary(); renderCheckoutSummary();">
                            </div>

                            <div class="order-summary-box" id="editSelectedItems"></div>
                        </div>
                    </div>

                    <div class="bb-modal-footer" style="padding-left: 0; padding-right: 0; margin-top: 20px;">
                        <button type="button" class="bb-btn bb-btn--ghost" onclick="closeSessionModal()">Batal</button>
                        <button type="button" class="bb-btn bb-btn--primary" onclick="submitEditSession()">
                            <i class="bi bi-check2-circle"></i> Simpan Perubahan Sesi
                        </button>
                    </div>
                </div>

                <!-- TAB 2: CHECKOUT -->
                <div id="tabContentCheckout" style="display: none; max-width: 500px; margin: 0 auto;">
                    <div id="checkoutSummaryBox"></div>

                    <div style="display: flex; gap: 12px;">
                        <button type="button" class="bb-btn bb-btn--ghost flex-grow-1" onclick="closeSessionModal()" style="flex: 1;">
                            Batal
                        </button>
                        <button type="button" class="bb-btn bb-btn--danger flex-grow-1" onclick="executeCheckout()" style="flex: 2;">
                            <i class="bi bi-receipt me-1"></i> Checkout & Cetak Struk
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Script -->
    <script>
        function setFnbCat(btn, cat, containerId, onSelect) {
            btn.parentElement.querySelectorAll('.category-pill').forEach(p => p.classList.remove('category-pill--active'));
            btn.classList.add('category-pill--active');
            state.fnbCategory = cat;
            renderFnbCatalog(containerId, onSelect);
        }
    </script>
    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dashboard.js?v=<?php echo filemtime(__DIR__ . '/assets/js/dashboard.js'); ?>"></script>
</body>
</html>
