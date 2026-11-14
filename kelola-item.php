<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['admin', 'kasir'])) {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_table') {
        if ($user_role !== 'admin') {
            $error = 'Hanya admin yang dapat menambah meja.';
        } else {
            $tableName = sanitizeInput($_POST['table_name'] ?? '');
            $relayChannel = intval($_POST['relay_channel'] ?? 0);

            if (!empty($tableName)) {
                try {
                    $tableModel = new Table($db);
                    $tableModel->addTable($tableName, $relayChannel > 0 ? $relayChannel : null);
                    $message = 'Meja baru berhasil ditambahkan!';
                } catch (Exception $e) {
                    $error = 'Gagal menambah meja: ' . $e->getMessage();
                }
            } else {
                $error = 'Nama meja wajib diisi.';
            }
        }
    } elseif ($action === 'delete_table') {
        if ($user_role !== 'admin') {
            $error = 'Hanya admin yang dapat menghapus meja.';
        } else {
            $tableId = $_POST['table_id'] ?? '';
            if (!empty($tableId)) {
                try {
                    $tableModel = new Table($db);
                    $tableModel->deleteTable($tableId);
                    $message = 'Meja berhasil dihapus!';
                } catch (Exception $e) {
                    $error = 'Gagal menghapus meja: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'add_fnb') {
        $fnbName = sanitizeInput($_POST['fnb_name'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? 'Makanan');
        $price = floatval($_POST['price'] ?? 0);
        $imagePath = null;

        // Handle File Upload (Max 2MB)
        if (isset($_FILES['fnb_image']) && $_FILES['fnb_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['fnb_image'];
            $maxSize = 2 * 1024 * 1024; // 2MB

            if ($file['size'] > $maxSize) {
                $error = 'Ukuran foto maksimal 2MB.';
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
                if (!in_array($ext, $allowed)) {
                    $error = 'Format foto harus JPG, PNG, WEBP, atau SVG.';
                } else {
                    $uploadDir = __DIR__ . '/storage/fnb_images/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $fileName = 'fnb_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                        $imagePath = $fileName;
                    } else {
                        $error = 'Gagal mengunggah file foto.';
                    }
                }
            }
        }

        if (empty($error)) {
            if (!empty($fnbName) && $price > 0) {
                try {
                    $fnbModel = new FnbItem($db);
                    $fnbModel->addItem($fnbName, $category, $price, $imagePath);
                    $message = 'Menu FnB baru berhasil ditambahkan!';
                } catch (Exception $e) {
                    $error = 'Gagal menambah menu: ' . $e->getMessage();
                }
            } else {
                $error = 'Nama menu dan harga wajib diisi dengan benar.';
            }
        }
    } elseif ($action === 'delete_fnb') {
        $fnbId = $_POST['fnb_id'] ?? '';
        if (!empty($fnbId)) {
            try {
                $fnbModel = new FnbItem($db);
                $fnbModel->deleteItem($fnbId);
                $message = 'Menu FnB berhasil dihapus!';
            } catch (Exception $e) {
                $error = 'Gagal menghapus menu: ' . $e->getMessage();
            }
        }
    }
}

// Ambil data untuk ditampilkan
$tables = [];
$fnbItems = [];
if ($db) {
    $tableModel = new Table($db);
    $tables = $tableModel->getAllWithActiveAndRecentTransactions();

    $fnbModel = new FnbItem($db);
    $fnbItems = $fnbModel->getAll();
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Meja & Menu FnB - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="bg-body text-body d-flex flex-column min-vh-100">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('poolstream_theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>

    <header class="dashboard-nav px-3 px-md-4 px-xl-5">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="dashboard.php" class="bb-btn bb-btn--ghost bb-btn--sm nav-back-btn">
                <i class="bi bi-arrow-left"></i> <span>Dashboard</span>
            </a>
            <a href="dashboard.php" class="brand-badge">
                <img src="assets/images/logo.png" alt="PoolStream" onerror="this.src='images/logo.png'">
                <span>Manajemen Meja & FnB</span>
            </a>
        </div>
        <div class="user-nav-profile">
            <button id="themeToggle" class="theme-toggle-btn" title="Ganti Tema">
                <i class="bi bi-sun-fill" id="themeIcon"></i>
            </button>
            <a href="logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </a>
        </div>
    </header>

    <main class="container-fluid px-3 px-md-4 px-xl-5 py-4 my-auto">
        <?php if (!empty($message)): ?>
            <div class="alert alert-success custom-alert mb-4" role="alert" style="background: rgba(16, 185, 129, 0.15); border-color: rgba(16, 185, 129, 0.3); color: #10b981;">
                <i class="bi bi-check-circle-fill me-1"></i> <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger custom-alert mb-4" role="alert" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.3); color: #ef4444;">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-5">
            <!-- 1. Form Tambah Meja (Admin Only) -->
            <?php if ($user_role === 'admin'): ?>
                <div class="col-12 col-lg-6">
                    <div class="card bg-body border border-secondary border-opacity-25 shadow-sm p-4 h-100" style="border-radius: 14px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="kpi-icon-wrap kpi-icon--emerald" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                                <i class="bi bi-grid-3x3-gap-fill"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold mb-0">Tambah Meja Baru</h4>
                                <span class="text-muted small">Khusus Hak Akses Admin</span>
                            </div>
                        </div>

                        <form method="POST" action="kelola-item.php">
                            <input type="hidden" name="action" value="add_table">

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nama Meja / Nomor Meja *</label>
                                <input type="text" name="table_name" class="form-control bg-body text-body border-secondary" placeholder="Contoh: Meja 17" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Channel Relay IoT (Opsional)</label>
                                <input type="number" name="relay_channel" class="form-control bg-body text-body border-secondary" placeholder="1-16" min="1" max="16">
                            </div>

                            <button type="submit" class="bb-btn bb-btn--success w-100 mt-2 py-2">
                                <i class="bi bi-plus-circle-fill me-1"></i> Tambahkan Meja Baru
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2. Form Tambah Menu FnB (Admin & Kasir) -->
            <div class="col-12 <?php echo ($user_role === 'admin') ? 'col-lg-6' : 'col-lg-8 mx-auto'; ?>">
                <div class="card bg-body border border-secondary border-opacity-25 shadow-sm p-4 h-100" style="border-radius: 14px;">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="kpi-icon-wrap kpi-icon--amber" style="width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                            <i class="bi bi-cup-hot-fill"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold mb-0">Tambah Menu F&B</h4>
                            <span class="text-muted small">Akses Admin & Kasir</span>
                        </div>
                    </div>

                    <form method="POST" action="kelola-item.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_fnb">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Makanan / Minuman *</label>
                            <input type="text" name="fnb_name" class="form-control bg-body text-body border-secondary" placeholder="Contoh: Es Teh Manis" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold">Kategori *</label>
                                <select name="category" class="form-select bg-body text-body border-secondary" required>
                                    <option value="Minuman Dingin">Minuman Dingin</option>
                                    <option value="Minuman Panas">Minuman Panas</option>
                                    <option value="Snack">Snack</option>
                                    <option value="Makanan">Makanan</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold">Harga (Rp) *</label>
                                <input type="number" name="price" class="form-control bg-body text-body border-secondary" placeholder="15000" min="500" step="500" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Foto Menu (Opsional, Maks 2MB)</label>
                            <input type="file" name="fnb_image" class="form-control bg-body text-body border-secondary" accept=".jpg, .jpeg, .png, .webp, .svg">
                            <small class="text-muted">Format: JPG, PNG, WEBP, SVG. Maksimal 2MB.</small>
                        </div>

                        <button type="submit" class="bb-btn bb-btn--primary w-100 mt-2 py-2">
                            <i class="bi bi-plus-circle-fill me-1"></i> Tambahkan Menu F&B Baru
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 3. Daftar Meja & Menu untuk Penghapusan -->
        <div class="row g-4">
            <?php if ($user_role === 'admin'): ?>
                <div class="col-12 col-lg-6">
                    <div class="card bg-body border border-secondary border-opacity-25 shadow-sm p-4 h-100" style="border-radius: 14px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-grid-3x3-gap me-1"></i> Daftar Meja Terpasang (<?php echo count($tables); ?>)</h5>
                        <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Nama Meja</th>
                                        <th>Status</th>
                                        <th>Relay</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tables as $t): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($t['name']); ?></td>
                                            <td>
                                                <?php if ($t['status'] === 'active'): ?>
                                                    <span class="badge bg-danger">Sedang Main</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Ready</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>Ch-<?php echo $t['relay_channel'] ?? '-'; ?></td>
                                            <td class="text-end">
                                                <form method="POST" action="kelola-item.php" onsubmit="return confirm('Yakin ingin menghapus <?php echo htmlspecialchars($t['name']); ?>?');" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete_table">
                                                    <input type="hidden" name="table_id" value="<?php echo $t['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Hapus Meja">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-12 <?php echo ($user_role === 'admin') ? 'col-lg-6' : 'col-lg-10 mx-auto'; ?>">
                <div class="card bg-body border border-secondary border-opacity-25 shadow-sm p-4 h-100" style="border-radius: 14px;">
                    <h5 class="fw-bold mb-3"><i class="bi bi-cup-hot me-1"></i> Daftar Menu F&B (<?php echo count($fnbItems); ?>)</h5>
                    <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Menu</th>
                                    <th>Kategori</th>
                                    <th>Harga</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fnbItems as $fnb): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($fnb['image_path'])): ?>
                                                    <img src="storage/fnb_items/<?php echo htmlspecialchars($fnb['image_path']); ?>" alt="" style="width: 32px; height: 32px; object-fit: cover; border-radius: 6px;" onerror="this.src='storage/fnb_images/dummy.png'">
                                                <?php else: ?>
                                                    <div style="width: 32px; height: 32px; background: rgba(100,100,100,0.2); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;"><i class="bi bi-image"></i></div>
                                                <?php endif; ?>
                                                <strong><?php echo htmlspecialchars($fnb['name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($fnb['category']); ?></span></td>
                                        <td>Rp <?php echo number_format($fnb['price'], 0, ',', '.'); ?></td>
                                        <td class="text-end">
                                            <form method="POST" action="kelola-item.php" onsubmit="return confirm('Yakin ingin menghapus menu <?php echo htmlspecialchars($fnb['name']); ?>?');" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_fnb">
                                                <input type="hidden" name="fnb_id" value="<?php echo $fnb['id']; ?>">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Hapus Menu">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="py-3 text-center text-muted small border-top border-secondary border-opacity-25 bg-body mt-auto">
        <div class="container-fluid">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;

        function updateThemeIcon(theme) {
            if (themeIcon) {
                themeIcon.className = (theme === 'dark') ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
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
