<?php
class Table {
    private $conn;
    private $table_name = "tables";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllWithActiveAndRecentTransactions() {
        // Ambil semua meja
        $query = "SELECT id, name, status, relay_channel FROM " . $this->table_name . " ORDER BY CAST(SUBSTRING_INDEX(name, ' ', -1) AS UNSIGNED), name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tables as &$table) {
            // Ambil transaksi aktif (jika ada)
            $qActive = "SELECT t.*, p.name as package_name, p.price as package_price 
                        FROM transactions t 
                        LEFT JOIN packages p ON t.package_id = p.id 
                        WHERE t.table_id = :table_id AND t.status = 'active' 
                        LIMIT 1";
            $stmtAct = $this->conn->prepare($qActive);
            $stmtAct->bindParam(':table_id', $table['id']);
            $stmtAct->execute();
            $activeTx = $stmtAct->fetch(PDO::FETCH_ASSOC);

            if ($activeTx) {
                // Format ISO timestamps dengan timezone agar JS tidak salah hitung zona waktu
                if (!empty($activeTx['expected_end_time'])) {
                    $activeTx['expected_end_time_iso'] = date('c', strtotime($activeTx['expected_end_time']));
                }
                if (!empty($activeTx['start_time'])) {
                    $activeTx['start_time_iso'] = date('c', strtotime($activeTx['start_time']));
                }

                // Ambil items FnB untuk transaksi aktif
                $qItems = "SELECT ti.*, f.name as fnb_name, f.category as fnb_category, f.price as fnb_price, f.image_path 
                           FROM transaction_items ti 
                           LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                           WHERE ti.transaction_id = :tx_id";
                $stmtItems = $this->conn->prepare($qItems);
                $stmtItems->bindParam(':tx_id', $activeTx['id']);
                $stmtItems->execute();
                $activeTx['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                $table['transactions'] = [$activeTx];
            } else {
                $table['transactions'] = [];
            }

            // Ambil recent transactions (maks 10 terakhir)
            $qRecent = "SELECT t.*, p.name as package_name, p.price as package_price 
                        FROM transactions t 
                        LEFT JOIN packages p ON t.package_id = p.id 
                        WHERE t.table_id = :table_id 
                        ORDER BY t.created_at DESC LIMIT 10";
            $stmtRec = $this->conn->prepare($qRecent);
            $stmtRec->bindParam(':table_id', $table['id']);
            $stmtRec->execute();
            $recentTxs = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

            foreach ($recentTxs as &$rtx) {
                $stmtItems2 = $this->conn->prepare("SELECT ti.*, f.name as fnb_name, f.category, f.price, f.image_path 
                                                    FROM transaction_items ti 
                                                    LEFT JOIN fnb_items f ON ti.fnb_item_id = f.id 
                                                    WHERE ti.transaction_id = :tx_id");
                $stmtItems2->bindParam(':tx_id', $rtx['id']);
                $stmtItems2->execute();
                $rtx['items'] = $stmtItems2->fetchAll(PDO::FETCH_ASSOC);
            }
            $table['recent_transactions'] = $recentTxs;
        }

        return $tables;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $stmt = $this->conn->prepare("UPDATE " . $this->table_name . " SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
