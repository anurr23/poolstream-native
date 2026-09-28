<?php
class Transaction {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function controlRelay($channel, $state) {
        if (!$channel) return;
        $scriptPath = __DIR__ . '/../services/relay_controller.py';
        if (!file_exists($scriptPath)) return;

        // Deteksi python executable
        $pyCmd = null;
        foreach (['python3', 'python', 'py'] as $cmd) {
            $check = @shell_exec("which $cmd 2>/dev/null");
            if (!empty($check)) {
                $pyCmd = trim($check);
                break;
            }
        }
        if (!$pyCmd) {
            $winCheck = @shell_exec("where py 2>nul");
            if (!empty($winCheck)) {
                $pyCmd = 'py';
            }
        }
        if (!$pyCmd) return;

        $cmd = escapeshellcmd("$pyCmd " . escapeshellarg($scriptPath) . " " . escapeshellarg((string)$channel) . " " . escapeshellarg((string)$state) . " 2>&1");
        @shell_exec($cmd);
    }

    public function startSession($tableId, $userId, $customerName, $packageId, $durationHours, $items = []) {
        // 1. Validasi Meja
        $tblStmt = $this->conn->prepare("SELECT * FROM tables WHERE id = :id LIMIT 1");
        $tblStmt->execute([':id' => $tableId]);
        $table = $tblStmt->fetch(PDO::FETCH_ASSOC);
        if (!$table) {
            throw new Exception("Meja tidak ditemukan.");
        }

        // 2. Validasi Paket
        $pkgStmt = $this->conn->prepare("SELECT * FROM packages WHERE id = :id LIMIT 1");
        $pkgStmt->execute([':id' => $packageId]);
        $package = $pkgStmt->fetch(PDO::FETCH_ASSOC);
        if (!$package) {
            throw new Exception("Paket tidak valid.");
        }

        // 3. Hitung Waktu dan Biaya Sewa
        $now = new DateTime();
        $startTime = $now->format('Y-m-d H:i:s');
        $expectedMinutes = round($durationHours * 60);
        $endTimeObj = clone $now;
        $endTimeObj->modify("+{$expectedMinutes} minutes");
        $expectedEndTime = $endTimeObj->format('Y-m-d H:i:s');

        $billiardCost = $package['price'] * $durationHours;
        $fnbCost = 0;

        // 4. Insert Transaksi (id auto_increment)
        $qTx = "INSERT INTO transactions (type, table_id, package_id, user_id, customer_name, start_time, expected_end_time, billiard_cost, fnb_cost, total_cost, status, created_at, updated_at) 
                VALUES ('billiard', :table_id, :package_id, :user_id, :customer_name, :start_time, :expected_end_time, :billiard_cost, 0, :total_cost, 'active', NOW(), NOW())";
        $stmtTx = $this->conn->prepare($qTx);
        $stmtTx->execute([
            ':table_id' => $tableId,
            ':package_id' => $packageId,
            ':user_id' => $userId,
            ':customer_name' => $customerName,
            ':start_time' => $startTime,
            ':expected_end_time' => $expectedEndTime,
            ':billiard_cost' => $billiardCost,
            ':total_cost' => $billiardCost
        ]);

        $txId = (int)$this->conn->lastInsertId();

        // 5. Insert Item F&B jika ada
        if (!empty($items) && is_array($items)) {
            $stmtItem = $this->conn->prepare("INSERT INTO transaction_items (transaction_id, fnb_item_id, price, quantity, subtotal, status, created_at, updated_at) 
                                              VALUES (:tx_id, :fnb_id, :price, :qty, :subtotal, 'pending', NOW(), NOW())");

            foreach ($items as $it) {
                $qty = (int)($it['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $fnbStmt = $this->conn->prepare("SELECT price FROM fnb_items WHERE id = :id LIMIT 1");
                $fnbStmt->execute([':id' => $it['fnb_item_id']]);
                $fnb = $fnbStmt->fetch(PDO::FETCH_ASSOC);

                if ($fnb) {
                    $itemPrice = (float)$fnb['price'];
                    $subtotal = $itemPrice * $qty;
                    $fnbCost += $subtotal;

                    $stmtItem->execute([
                        ':tx_id' => $txId,
                        ':fnb_id' => $it['fnb_item_id'],
                        ':price' => $itemPrice,
                        ':qty' => $qty,
                        ':subtotal' => $subtotal
                    ]);
                }
            }
        }

        // 6. Update Total Biaya Transaksi
        $totalCost = $billiardCost + $fnbCost;
        $updTx = $this->conn->prepare("UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost WHERE id = :id");
        $updTx->execute([
            ':fnb_cost' => $fnbCost,
            ':total_cost' => $totalCost,
            ':id' => $txId
        ]);

        // 7. Update Status Meja menjadi 'active'
        $updTbl = $this->conn->prepare("UPDATE tables SET status = 'active', updated_at = NOW() WHERE id = :id");
        $updTbl->execute([':id' => $tableId]);

        // 8. Nyalakan Lampu Relay
        $this->controlRelay($table['relay_channel'], 'on');

        return $txId;
    }

    public function updateSession($tableId, $txId, $packageId, $durationHours, $items = []) {
        $txStmt = $this->conn->prepare("SELECT * FROM transactions WHERE id = :id AND table_id = :table_id AND status = 'active' LIMIT 1");
        $txStmt->execute([
            ':id' => $txId,
            ':table_id' => $tableId
        ]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            throw new Exception("Sesi transaksi tidak ditemukan atau sudah tidak aktif.");
        }

        // Validasi durasi tidak boleh kurang dari waktu berjalan (sesuai poolstream)
        $elapsedMinutes = (time() - strtotime($tx['start_time'])) / 60;
        $elapsedHours = $elapsedMinutes / 60;
        if ($durationHours < $elapsedHours) {
            throw new Exception("Durasi pesanan tidak boleh kurang dari waktu yang sudah berjalan (" . number_format($elapsedHours, 1) . " jam).");
        }

        $pkgStmt = $this->conn->prepare("SELECT * FROM packages WHERE id = :id LIMIT 1");
        $pkgStmt->execute([':id' => $packageId]);
        $package = $pkgStmt->fetch(PDO::FETCH_ASSOC);

        if (!$package) {
            throw new Exception("Paket tidak valid.");
        }

        $startObj = new DateTime($tx['start_time']);
        $expectedMinutes = round($durationHours * 60);
        $newEndObj = clone $startObj;
        $newEndObj->modify("+{$expectedMinutes} minutes");
        $newExpectedEndTime = $newEndObj->format('Y-m-d H:i:s');

        $billiardCost = $package['price'] * $durationHours;
        $fnbCost = 0;

        // Reset & Re-insert Item F&B
        $delStmt = $this->conn->prepare("DELETE FROM transaction_items WHERE transaction_id = :tx_id");
        $delStmt->execute([':tx_id' => $txId]);

        if (!empty($items) && is_array($items)) {
            $stmtItem = $this->conn->prepare("INSERT INTO transaction_items (transaction_id, fnb_item_id, price, quantity, subtotal, status, created_at, updated_at) 
                                              VALUES (:tx_id, :fnb_id, :price, :qty, :subtotal, 'pending', NOW(), NOW())");

            foreach ($items as $it) {
                $qty = (int)($it['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $fnbStmt = $this->conn->prepare("SELECT price FROM fnb_items WHERE id = :id LIMIT 1");
                $fnbStmt->execute([':id' => $it['fnb_item_id']]);
                $fnb = $fnbStmt->fetch(PDO::FETCH_ASSOC);

                if ($fnb) {
                    $itemPrice = (float)$fnb['price'];
                    $subtotal = $itemPrice * $qty;
                    $fnbCost += $subtotal;

                    $stmtItem->execute([
                        ':tx_id' => $txId,
                        ':fnb_id' => $it['fnb_item_id'],
                        ':price' => $itemPrice,
                        ':qty' => $qty,
                        ':subtotal' => $subtotal
                    ]);
                }
            }
        }

        $totalCost = $billiardCost + $fnbCost;
        $upd = $this->conn->prepare("UPDATE transactions SET package_id = :pkg_id, expected_end_time = :end_time, billiard_cost = :billiard_cost, fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW() WHERE id = :id");
        $upd->execute([
            ':pkg_id' => $packageId,
            ':end_time' => $newExpectedEndTime,
            ':billiard_cost' => $billiardCost,
            ':fnb_cost' => $fnbCost,
            ':total_cost' => $totalCost,
            ':id' => $txId
        ]);

        return true;
    }

    public function stopSession($tableId) {
        $tblStmt = $this->conn->prepare("SELECT * FROM tables WHERE id = :id LIMIT 1");
        $tblStmt->execute([':id' => $tableId]);
        $table = $tblStmt->fetch(PDO::FETCH_ASSOC);

        // Ambil transaksi aktif
        $txStmt = $this->conn->prepare("SELECT t.*, p.name as package_name, p.price as package_price 
                                        FROM transactions t 
                                        LEFT JOIN packages p ON t.package_id = p.id 
                                        WHERE t.table_id = :table_id AND t.status = 'active' 
                                        ORDER BY t.created_at DESC LIMIT 1");
        $txStmt->execute([':table_id' => $tableId]);
        $tx = $txStmt->fetch(PDO::FETCH_ASSOC);

        if ($tx) {
            $now = date('Y-m-d H:i:s');
            // Recalculate total
            $totalCost = $tx['billiard_cost'] + $tx['fnb_cost'];
            $updTx = $this->conn->prepare("UPDATE transactions SET end_time = :now, total_cost = :total_cost, status = 'completed', updated_at = NOW() WHERE id = :id");
            $updTx->execute([
                ':now' => $now,
                ':total_cost' => $totalCost,
                ':id' => $tx['id']
            ]);

            // Ambil items untuk struk
            $stmtItems = $this->conn->prepare("SELECT ti.*, f.name as fnb_name 
                                               FROM transaction_items ti 
                                               LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                                               WHERE ti.transaction_id = :tx_id");
            $stmtItems->execute([':tx_id' => $tx['id']]);
            $tx['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            $tx['end_time'] = $now;
            $tx['total_cost'] = $totalCost;
        }

        // Ubah status meja menjadi 'inactive'
        $updTbl = $this->conn->prepare("UPDATE tables SET status = 'inactive', updated_at = NOW() WHERE id = :id");
        $updTbl->execute([':id' => $tableId]);

        // Matikan Lampu Relay
        if ($table) {
            $this->controlRelay($table['relay_channel'], 'off');
        }

        return $tx;
    }

    public function autoCheckoutExpired() {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->conn->prepare("SELECT t.*, tbl.relay_channel, tbl.name as table_name 
                                      FROM transactions t 
                                      JOIN tables tbl ON t.table_id = tbl.id 
                                      WHERE t.status = 'active' 
                                        AND t.expected_end_time IS NOT NULL 
                                        AND t.expected_end_time <= :now");
        $stmt->execute([':now' => $now]);
        $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expired as $tx) {
            $endTime = $tx['expected_end_time'];
            $totalCost = $tx['billiard_cost'] + $tx['fnb_cost'];
            $updTx = $this->conn->prepare("UPDATE transactions SET end_time = :end_time, total_cost = :total_cost, status = 'completed', updated_at = NOW() WHERE id = :id");
            $updTx->execute([
                ':end_time' => $endTime,
                ':total_cost' => $totalCost,
                ':id' => $tx['id']
            ]);

            $updTbl = $this->conn->prepare("UPDATE tables SET status = 'inactive', updated_at = NOW() WHERE id = :id");
            $updTbl->execute([':id' => $tx['table_id']]);

            $this->controlRelay($tx['relay_channel'], 'off');
        }

        return count($expired);
    }

    // ============================================================
    // F&B STANDALONE ORDERS (KASIR & BILLING)
    // ============================================================

    public function createFnbOrder($userId, $customerName, $items = []) {
        $customerName = trim($customerName);
        if (empty($customerName)) {
            throw new Exception("Nama pelanggan wajib diisi.");
        }

        // Validate user_id foreign key or fallback to null
        if (!empty($userId)) {
            $uStmt = $this->conn->prepare("SELECT id FROM users WHERE id = :uid LIMIT 1");
            $uStmt->execute([':uid' => $userId]);
            if (!$uStmt->fetch()) {
                $userId = null;
            }
        } else {
            $userId = null;
        }

        $now = date('Y-m-d H:i:s');
        $qTx = "INSERT INTO transactions (type, table_id, package_id, user_id, customer_name, start_time, billiard_cost, fnb_cost, total_cost, status, created_at, updated_at) 
                VALUES ('fnb_only', NULL, NULL, :user_id, :customer_name, :start_time, 0, 0, 0, 'active', NOW(), NOW())";
        $stmtTx = $this->conn->prepare($qTx);
        $stmtTx->execute([
            ':user_id' => $userId,
            ':customer_name' => $customerName,
            ':start_time' => $now
        ]);

        $txId = (int)$this->conn->lastInsertId();
        $fnbCost = 0;

        if (!empty($items) && is_array($items)) {
            $stmtItem = $this->conn->prepare("INSERT INTO transaction_items (transaction_id, fnb_item_id, price, quantity, subtotal, status, created_at, updated_at) 
                                              VALUES (:tx_id, :fnb_id, :price, :qty, :subtotal, 'pending', NOW(), NOW())");

            foreach ($items as $it) {
                $fnbId = (int)($it['fnb_item_id'] ?? $it['fnb_id'] ?? 0);
                $qty = (int)($it['quantity'] ?? 0);
                if ($fnbId <= 0 || $qty <= 0) continue;

                $fnbStmt = $this->conn->prepare("SELECT price FROM fnb_items WHERE id = :id LIMIT 1");
                $fnbStmt->execute([':id' => $fnbId]);
                $fnb = $fnbStmt->fetch(PDO::FETCH_ASSOC);

                if ($fnb) {
                    $itemPrice = (float)$fnb['price'];
                    $subtotal = $itemPrice * $qty;
                    $fnbCost += $subtotal;

                    $stmtItem->execute([
                        ':tx_id' => $txId,
                        ':fnb_id' => $fnbId,
                        ':price' => $itemPrice,
                        ':qty' => $qty,
                        ':subtotal' => $subtotal
                    ]);
                }
            }
        }

        $updTx = $this->conn->prepare("UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost WHERE id = :id");
        $updTx->execute([
            ':fnb_cost' => $fnbCost,
            ':total_cost' => $fnbCost,
            ':id' => $txId
        ]);

        return $txId;
    }

    public function getActiveFnbOrders($search = '') {
        $sql = "SELECT t.*, u.name as cashier_name 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.type = 'fnb_only' AND t.status = 'active'";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND t.customer_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY t.created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $stmtItems = $this->conn->prepare("SELECT ti.*, f.name as fnb_name, f.category as fnb_category, f.image_path 
                                               FROM transaction_items ti 
                                               LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                                               WHERE ti.transaction_id = :tx_id 
                                               ORDER BY ti.id ASC");
            $stmtItems->execute([':tx_id' => $order['id']]);
            $order['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }

        return $orders;
    }

    public function getFnbHistory($filterPreset = 'hari_ini', $search = '') {
        $sql = "SELECT t.*, u.name as cashier_name 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                WHERE t.type = 'fnb_only' AND t.status IN ('completed', 'cancelled')";
        $params = [];

        if ($filterPreset === '7_hari') {
            $sql .= " AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } elseif ($filterPreset === '30_hari') {
            $sql .= " AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        } elseif ($filterPreset === 'bulan_ini') {
            $sql .= " AND MONTH(t.created_at) = MONTH(CURRENT_DATE()) AND YEAR(t.created_at) = YEAR(CURRENT_DATE())";
        } else {
            // Default: Hari ini
            $sql .= " AND DATE(t.created_at) = CURDATE()";
        }

        if (!empty($search)) {
            $sql .= " AND t.customer_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $sql .= " ORDER BY t.created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $stmtItems = $this->conn->prepare("SELECT ti.*, f.name as fnb_name, f.category as fnb_category, f.image_path 
                                               FROM transaction_items ti 
                                               LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                                               WHERE ti.transaction_id = :tx_id 
                                               ORDER BY ti.id ASC");
            $stmtItems->execute([':tx_id' => $order['id']]);
            $order['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        }

        return $orders;
    }

    public function getFnbOrderDetails($transactionId) {
        $stmt = $this->conn->prepare("SELECT t.*, u.name as cashier_name 
                                      FROM transactions t 
                                      LEFT JOIN users u ON t.user_id = u.id 
                                      WHERE t.id = :id AND t.type = 'fnb_only' LIMIT 1");
        $stmt->execute([':id' => $transactionId]);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tx) {
            return null;
        }

        $stmtItems = $this->conn->prepare("SELECT ti.*, f.name as fnb_name, f.category as fnb_category, f.image_path 
                                           FROM transaction_items ti 
                                           LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                                           WHERE ti.transaction_id = :tx_id 
                                           ORDER BY ti.id ASC");
        $stmtItems->execute([':tx_id' => $transactionId]);
        $tx['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        return $tx;
    }

    public function addFnbOrderItem($transactionId, $fnbItemId, $quantity = 1) {
        $quantity = max(1, (int)$quantity);

        $tx = $this->getFnbOrderDetails($transactionId);
        if (!$tx || $tx['status'] !== 'active') {
            throw new Exception("Transaksi tidak ditemukan atau sudah tidak aktif.");
        }

        $fnbStmt = $this->conn->prepare("SELECT * FROM fnb_items WHERE id = :id LIMIT 1");
        $fnbStmt->execute([':id' => $fnbItemId]);
        $fnb = $fnbStmt->fetch(PDO::FETCH_ASSOC);
        if (!$fnb) {
            throw new Exception("Item F&B tidak ditemukan.");
        }

        $itemStmt = $this->conn->prepare("SELECT * FROM transaction_items WHERE transaction_id = :tx_id AND fnb_item_id = :fnb_id LIMIT 1");
        $itemStmt->execute([':tx_id' => $transactionId, ':fnb_id' => $fnbItemId]);
        $existing = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $newQty = (int)$existing['quantity'] + $quantity;
            $newSubtotal = (float)$existing['price'] * $newQty;
            $updItem = $this->conn->prepare("UPDATE transaction_items SET quantity = :qty, subtotal = :subtotal, updated_at = NOW() WHERE id = :id");
            $updItem->execute([':qty' => $newQty, ':subtotal' => $newSubtotal, ':id' => $existing['id']]);
        } else {
            $price = (float)$fnb['price'];
            $subtotal = $price * $quantity;
            $insItem = $this->conn->prepare("INSERT INTO transaction_items (transaction_id, fnb_item_id, price, quantity, subtotal, status, created_at, updated_at) 
                                             VALUES (:tx_id, :fnb_id, :price, :qty, :subtotal, 'pending', NOW(), NOW())");
            $insItem->execute([
                ':tx_id' => $transactionId,
                ':fnb_id' => $fnbItemId,
                ':price' => $price,
                ':qty' => $quantity,
                ':subtotal' => $subtotal
            ]);
        }

        $this->recalculateFnbTotals($transactionId);
        return $this->getFnbOrderDetails($transactionId);
    }

    public function updateFnbItemQuantity($itemId, $change = 1) {
        $itemStmt = $this->conn->prepare("SELECT ti.*, t.status as tx_status 
                                          FROM transaction_items ti 
                                          JOIN transactions t ON ti.transaction_id = t.id 
                                          WHERE ti.id = :id LIMIT 1");
        $itemStmt->execute([':id' => $itemId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $item['tx_status'] !== 'active') {
            throw new Exception("Item transaksi tidak ditemukan atau transaksi sudah selesai.");
        }

        $newQty = (int)$item['quantity'] + (int)$change;

        if ($newQty <= 0) {
            $del = $this->conn->prepare("DELETE FROM transaction_items WHERE id = :id");
            $del->execute([':id' => $itemId]);
        } else {
            $subtotal = (float)$item['price'] * $newQty;
            $upd = $this->conn->prepare("UPDATE transaction_items SET quantity = :qty, subtotal = :subtotal, updated_at = NOW() WHERE id = :id");
            $upd->execute([':qty' => $newQty, ':subtotal' => $subtotal, ':id' => $itemId]);
        }

        $this->recalculateFnbTotals($item['transaction_id']);
        return $this->getFnbOrderDetails($item['transaction_id']);
    }

    public function removeFnbItem($itemId) {
        $itemStmt = $this->conn->prepare("SELECT ti.*, t.status as tx_status 
                                          FROM transaction_items ti 
                                          JOIN transactions t ON ti.transaction_id = t.id 
                                          WHERE ti.id = :id LIMIT 1");
        $itemStmt->execute([':id' => $itemId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

        if (!$item || $item['tx_status'] !== 'active') {
            throw new Exception("Item transaksi tidak ditemukan atau transaksi sudah selesai.");
        }

        $del = $this->conn->prepare("DELETE FROM transaction_items WHERE id = :id");
        $del->execute([':id' => $itemId]);

        $this->recalculateFnbTotals($item['transaction_id']);
        return $this->getFnbOrderDetails($item['transaction_id']);
    }

    public function checkoutFnbOrder($transactionId) {
        $tx = $this->getFnbOrderDetails($transactionId);
        if (!$tx || $tx['status'] !== 'active') {
            throw new Exception("Pesanan tidak ditemukan atau sudah selesai.");
        }

        $this->recalculateFnbTotals($transactionId);

        $now = date('Y-m-d H:i:s');
        $upd = $this->conn->prepare("UPDATE transactions SET status = 'completed', end_time = :now, updated_at = NOW() WHERE id = :id");
        $upd->execute([':now' => $now, ':id' => $transactionId]);

        return $this->getFnbOrderDetails($transactionId);
    }

    public function recalculateFnbTotals($transactionId) {
        $sumStmt = $this->conn->prepare("SELECT COALESCE(SUM(subtotal), 0) as total FROM transaction_items WHERE transaction_id = :id");
        $sumStmt->execute([':id' => $transactionId]);
        $totalFnb = (float)$sumStmt->fetchColumn();

        $upd = $this->conn->prepare("UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW() WHERE id = :id");
        $upd->execute([
            ':fnb_cost' => $totalFnb,
            ':total_cost' => $totalFnb,
            ':id' => $transactionId
        ]);
    }
}
