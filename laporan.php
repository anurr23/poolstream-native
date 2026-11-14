<?php
require_once __DIR__ . '/config/config.php';
requireRole(['admin']);

$database = new Database();
$db = $database->getConnection();

// Filter tanggal laporan
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

$transactions = [];
$total_revenue = 0;
$total_billiard_revenue = 0;
$total_fnb_revenue = 0;
$total_transactions = 0;

if ($db) {
    $query = "SELECT t.*, tbl.name as table_name, p.name as package_name, u.name as cashier_name 
              FROM transactions t 
              LEFT JOIN tables tbl ON t.table_id = tbl.id 
              LEFT JOIN packages p ON t.package_id = p.id 
              LEFT JOIN users u ON t.user_id = u.id 
              WHERE DATE(t.created_at) BETWEEN :start_date AND :end_date 
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $tx) {
        if ($tx['status'] === 'completed') {
            $total_revenue += (float)$tx['total_cost'];
            $total_billiard_revenue += (float)($tx['billiard_cost'] ?? 0);
            $total_fnb_revenue += (float)($tx['fnb_cost'] ?? 0);
            $total_transactions++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan & Omset - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body>
    <header class="dashboard-nav px-3 px-md-4 px-xl-5">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="dashboard.php" class="bb-btn bb-btn--ghost bb-btn--sm nav-back-btn">
                <i class="bi bi-arrow-left"></i> <span>Dashboard</span>
            </a>
            <a href="dashboard.php" class="brand-badge">
                <img src="assets/images/logo.png" alt="PoolStream" onerror="this.src='images/logo.png'">
                <span>Laporan Keuangan</span>
            </a>
        </div>
        <div class="user-nav-profile">
            <a href="logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> <span>Keluar</span>
            </a>
        </div>
    </header>

    <main class="dashboard-content px-3 px-md-4 px-xl-5 py-4">
        <!-- Filter Card -->
        <div class="card bg-dark border-secondary mb-4 p-3" style="border-radius: 12px;">
            <form method="GET" action="laporan.php" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small">Dari Tanggal</label>
                    <input type="date" name="start_date" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control bg-dark text-white border-secondary" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <button type="submit" class="bb-btn bb-btn--success w-100" style="height: 38px;">
                        <i class="bi bi-filter me-1"></i> Filter Laporan
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="kpi-card h-100">
                    <div class="kpi-icon-wrap kpi-icon--amber">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="kpi-info">
                        <span class="kpi-label">Total Omset Selesai</span>
                        <div class="kpi-main-val">
                            <span class="kpi-val">Rp <?php echo number_format($total_revenue, 0, ',', '.'); ?></span>
                        </div>
                        <span class="kpi-subtext text-muted"><?php echo $total_transactions; ?> Transaksi Sukses</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card h-100">
                    <div class="kpi-icon-wrap kpi-icon--emerald">
                        <i class="bi bi-grid-3x3-gap-fill"></i>
                    </div>
                    <div class="kpi-info">
                        <span class="kpi-label">Pendapatan Sewa Meja</span>
                        <div class="kpi-main-val">
                            <span class="kpi-val">Rp <?php echo number_format($total_billiard_revenue, 0, ',', '.'); ?></span>
                        </div>
                        <span class="kpi-subtext text-emerald">Biliar & Paket</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="kpi-card h-100">
                    <div class="kpi-icon-wrap kpi-icon--cyan">
                        <i class="bi bi-cup-straw"></i>
                    </div>
                    <div class="kpi-info">
                        <span class="kpi-label">Pendapatan FnB</span>
                        <div class="kpi-main-val">
                            <span class="kpi-val">Rp <?php echo number_format($total_fnb_revenue, 0, ',', '.'); ?></span>
                        </div>
                        <span class="kpi-subtext text-cyan">Makanan & Minuman</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="card bg-dark border-secondary p-3" style="border-radius: 12px;">
            <h5 class="mb-3 fw-bold"><i class="bi bi-receipt me-1"></i> Rincian Transaksi Periode <?php echo $start_date; ?> s/d <?php echo $end_date; ?></h5>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle">
                    <thead>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                            <th>Waktu</th>
                            <th>ID Transaksi</th>
                            <th>Meja</th>
                            <th>Pelanggan</th>
                            <th>Paket</th>
                            <th>Biliar (Rp)</th>
                            <th>FnB (Rp)</th>
                            <th>Total (Rp)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">Tidak ada data transaksi pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?></td>
                                    <td><code>#<?php echo substr($tx['id'], 0, 8); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($tx['table_name'] ?? '-'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($tx['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($tx['package_name'] ?? '-'); ?></td>
                                    <td><?php echo number_format($tx['billiard_cost'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><?php echo number_format($tx['fnb_cost'] ?? 0, 0, ',', '.'); ?></td>
                                    <td><strong><?php echo number_format($tx['total_cost'], 0, ',', '.'); ?></strong></td>
                                    <td>
                                        <?php if ($tx['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Selesai</span>
                                        <?php elseif ($tx['status'] === 'active'): ?>
                                            <span class="badge bg-primary">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary"><?php echo ucfirst($tx['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
