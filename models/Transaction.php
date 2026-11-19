<?php
declare(strict_types=1);

class Transaction
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function items(int $transactionId): array
    {
        $rows = fetch_all(
            "SELECT ti.*, f.name AS fnb_name, f.category AS fnb_category, f.price AS fnb_price, f.image_path AS fnb_image_path
             FROM transaction_items ti
             LEFT JOIN fnb_items f ON f.id = ti.fnb_item_id
             WHERE ti.transaction_id = :id
             ORDER BY ti.id ASC",
            [':id' => $transactionId]
        );
        return array_map(static fn (array $row): array => shape_transaction_item($row), $rows);
    }

    private function packageFor(?int $packageId): ?array
    {
        if (!$packageId) {
            return null;
        }
        $row = fetch_one('SELECT * FROM packages WHERE id = :id LIMIT 1', [':id' => $packageId]);
        return $row ? shape_package($row) : null;
    }

    private function tableFor(?string $tableId): ?array
    {
        if (!$tableId) {
            return null;
        }
        $row = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tableId]);
        return $row ? shape_table($row) : null;
    }

    private function userFor(?string $userId): ?array
    {
        if (!$userId) {
            return null;
        }
        $row = fetch_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $userId]);
        if (!$row) {
            return null;
        }
        unset($row['password'], $row['otp_code'], $row['remember_token']);
        $row['is_active'] = (bool) $row['is_active'];
        $row['photo_url'] = storage_url($row['photo_path']);
        return $row;
    }

    public function shape(array $row, bool $items = true): array
    {
        return shape_transaction(
            $row,
            $this->tableFor($row['table_id'] ?? null),
            $this->packageFor(isset($row['package_id']) ? (int) $row['package_id'] : null),
            $this->userFor($row['user_id'] ?? null),
            $items ? $this->items((int) $row['id']) : null
        );
    }

    public function find(int $id): ?array
    {
        $row = fetch_one('SELECT * FROM transactions WHERE id = :id LIMIT 1', [':id' => $id]);
        return $row ? $this->shape($row) : null;
    }

    public function activeForTable(string $tableId): ?array
    {
        $row = fetch_one(
            "SELECT * FROM transactions WHERE table_id = :table_id AND status = 'active' ORDER BY created_at DESC LIMIT 1",
            [':table_id' => $tableId]
        );
        return $row ? $this->shape($row) : null;
    }

    public function upcomingBookings(): array
    {
        $rows = fetch_all(
            "SELECT * FROM transactions
             WHERE type = 'billiard' AND status = 'booked' AND expected_end_time >= NOW()
             ORDER BY start_time ASC"
        );
        return array_map(fn (array $row): array => $this->shape($row, false), $rows);
    }

    public function bookingsForUser(string $userId, int $limit = 100): array
    {
        $rows = fetch_all(
            "SELECT * FROM transactions
             WHERE user_id = :user_id AND type = 'billiard'
             ORDER BY start_time DESC LIMIT {$limit}",
            [':user_id' => $userId]
        );
        return array_map(fn (array $row): array => $this->shape($row), $rows);
    }

    public function nextForUser(string $userId): ?array
    {
        $row = fetch_one(
            "SELECT * FROM transactions
             WHERE user_id = :user_id AND type = 'billiard' AND status IN ('booked','active')
               AND (status = 'active' OR expected_end_time >= NOW())
             ORDER BY start_time ASC LIMIT 1",
            [':user_id' => $userId]
        );
        return $row ? $this->shape($row) : null;
    }

    public function busyTableIds(string $start, string $end): array
    {
        $rows = fetch_all(
            "SELECT DISTINCT table_id FROM transactions
             WHERE type = 'billiard' AND status IN ('active','booked')
               AND start_time < :end
               AND (expected_end_time IS NULL OR expected_end_time > :start)",
            [':start' => $start, ':end' => $end]
        );
        return array_values(array_filter(array_map(static fn (array $row): ?string => $row['table_id'], $rows)));
    }

    public function startSession(string $tableId, ?string $userId, string $customerName, int $packageId, float $durationHours, array $items = []): array
    {
        $table = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tableId]);
        if (!$table) {
            throw new RuntimeException('Meja tidak ditemukan.');
        }

        $package = fetch_one('SELECT * FROM packages WHERE id = :id LIMIT 1', [':id' => $packageId]);
        if (!$package) {
            throw new RuntimeException('Paket tidak valid.');
        }

        $start = now();
        $minutes = (int) round($durationHours * 60);
        $end = date('Y-m-d H:i:s', strtotime($start . ' +' . $minutes . ' minutes'));

        $conflict = fetch_one(
            "SELECT id FROM transactions
             WHERE table_id = :table_id AND type = 'billiard' AND status IN ('active','booked')
               AND start_time < :end
               AND (expected_end_time IS NULL OR expected_end_time > :start)
             LIMIT 1",
            [':table_id' => $tableId, ':start' => $start, ':end' => $end]
        );

        if ($table['status'] === 'active' || $conflict) {
            throw new RuntimeException('Meja sedang dipakai atau sudah memiliki booking pada waktu tersebut.');
        }

        $billiardCost = (float) $package['price'] * $durationHours;
        $fnbCost = 0.0;

        $this->db->beginTransaction();
        try {
            execute_sql(
                "INSERT INTO transactions
                    (type, table_id, package_id, user_id, customer_name, start_time, expected_end_time,
                     billiard_cost, fnb_cost, total_cost, status, created_at, updated_at)
                 VALUES
                    ('billiard', :table_id, :package_id, :user_id, :customer_name, :start_time, :expected_end_time,
                     :billiard_cost, 0, :total_cost, 'active', NOW(), NOW())",
                [
                    ':table_id' => $tableId,
                    ':package_id' => $packageId,
                    ':user_id' => $userId,
                    ':customer_name' => $customerName,
                    ':start_time' => $start,
                    ':expected_end_time' => $end,
                    ':billiard_cost' => $billiardCost,
                    ':total_cost' => $billiardCost,
                ]
            );
            $transactionId = (int) $this->db->lastInsertId();

            $fnbCost = $this->insertItems($transactionId, $items);
            execute_sql(
                'UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW() WHERE id = :id',
                [':id' => $transactionId, ':fnb_cost' => $fnbCost, ':total_cost' => $billiardCost + $fnbCost]
            );
            execute_sql("UPDATE tables SET status = 'active', updated_at = NOW() WHERE id = :id", [':id' => $tableId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        RelayService::setLight($table, true);
        return $this->find($transactionId);
    }

    private function insertItems(int $transactionId, array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            $fnbId = (int) ($item['fnb_item_id'] ?? 0);
            if ($quantity <= 0 || $fnbId <= 0) {
                continue;
            }

            $fnb = fetch_one('SELECT * FROM fnb_items WHERE id = :id LIMIT 1', [':id' => $fnbId]);
            if (!$fnb) {
                continue;
            }

            $price = (float) $fnb['price'];
            $subtotal = $price * $quantity;
            $total += $subtotal;
            execute_sql(
                "INSERT INTO transaction_items (transaction_id, fnb_item_id, quantity, price, subtotal, status, created_at, updated_at)
                 VALUES (:transaction_id, :fnb_item_id, :quantity, :price, :subtotal, 'pending', NOW(), NOW())",
                [
                    ':transaction_id' => $transactionId,
                    ':fnb_item_id' => $fnbId,
                    ':quantity' => $quantity,
                    ':price' => $price,
                    ':subtotal' => $subtotal,
                ]
            );
        }
        return $total;
    }

    public function updateSession(string $tableId, int $transactionId, int $packageId, float $durationHours, array $items = []): array
    {
        $tx = fetch_one(
            "SELECT * FROM transactions WHERE id = :id AND table_id = :table_id AND status = 'active' LIMIT 1",
            [':id' => $transactionId, ':table_id' => $tableId]
        );
        if (!$tx) {
            throw new RuntimeException('Sesi transaksi tidak ditemukan atau sudah tidak aktif.');
        }

        $elapsedMinutes = (time() - strtotime((string) $tx['start_time'])) / 60;
        $elapsedHours = $elapsedMinutes / 60;
        if ($durationHours < $elapsedHours) {
            throw new RuntimeException('Durasi pesanan tidak boleh kurang dari waktu yang sudah berjalan (' . number_format($elapsedHours, 1) . ' jam).');
        }

        $package = fetch_one('SELECT * FROM packages WHERE id = :id LIMIT 1', [':id' => $packageId]);
        if (!$package) {
            throw new RuntimeException('Paket tidak valid.');
        }

        $expectedEnd = date('Y-m-d H:i:s', strtotime((string) $tx['start_time'] . ' +' . (int) round($durationHours * 60) . ' minutes'));
        $billiardCost = (float) $package['price'] * $durationHours;

        $this->db->beginTransaction();
        try {
            execute_sql('DELETE FROM transaction_items WHERE transaction_id = :id', [':id' => $transactionId]);
            $fnbCost = $this->insertItems($transactionId, $items);
            execute_sql(
                "UPDATE transactions
                 SET package_id = :package_id, expected_end_time = :expected_end_time,
                     billiard_cost = :billiard_cost, fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW()
                 WHERE id = :id",
                [
                    ':id' => $transactionId,
                    ':package_id' => $packageId,
                    ':expected_end_time' => $expectedEnd,
                    ':billiard_cost' => $billiardCost,
                    ':fnb_cost' => $fnbCost,
                    ':total_cost' => $billiardCost + $fnbCost,
                ]
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->find($transactionId);
    }

    public function stopSession(string $tableId): ?array
    {
        $table = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tableId]);
        $tx = $this->activeForTable($tableId);

        if ($tx) {
            $end = now();
            execute_sql(
                "UPDATE transactions SET end_time = :end_time, status = 'completed', updated_at = NOW() WHERE id = :id",
                [':id' => $tx['id'], ':end_time' => $end]
            );
            $this->recalculate((int) $tx['id']);
            $tx = $this->find((int) $tx['id']);
        }

        execute_sql("UPDATE tables SET status = 'inactive', updated_at = NOW() WHERE id = :id", [':id' => $tableId]);
        if ($table) {
            RelayService::setLight($table, false);
        }

        return $tx ? $this->find((int) $tx['id']) : null;
    }

    public function startBooking(int $transactionId): array
    {
        $tx = fetch_one("SELECT * FROM transactions WHERE id = :id LIMIT 1", [':id' => $transactionId]);
        if (!$tx || $tx['type'] !== 'billiard' || $tx['status'] !== 'booked') {
            throw new RuntimeException('Booking tidak valid atau sudah diproses.');
        }

        $table = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tx['table_id']]);
        if (!$table) {
            throw new RuntimeException('Meja untuk booking ini tidak ditemukan.');
        }
        if (!empty($tx['expected_end_time']) && strtotime((string) $tx['expected_end_time']) < time()) {
            throw new RuntimeException('Jadwal booking sudah lewat.');
        }
        if (strtotime((string) $tx['start_time']) > time() + 30 * 60) {
            throw new RuntimeException('Booking belum bisa dimulai. Tunggu hingga 30 menit sebelum jadwal.');
        }
        if ($table['status'] === 'active') {
            throw new RuntimeException('Meja sedang dipakai sesi lain.');
        }

        $other = fetch_one(
            "SELECT id FROM transactions WHERE table_id = :table_id AND status = 'active' AND id <> :id LIMIT 1",
            [':table_id' => $tx['table_id'], ':id' => $transactionId]
        );
        if ($other) {
            throw new RuntimeException('Meja sedang dipakai sesi lain.');
        }

        execute_sql("UPDATE tables SET status = 'active', updated_at = NOW() WHERE id = :id", [':id' => $tx['table_id']]);
        execute_sql("UPDATE transactions SET status = 'active', updated_at = NOW() WHERE id = :id", [':id' => $transactionId]);
        RelayService::setLight($table, true);

        return $this->find($transactionId);
    }

    public function autoCheckoutExpired(): int
    {
        $expired = fetch_all(
            "SELECT t.*, tbl.relay_channel, tbl.name AS table_name
             FROM transactions t JOIN tables tbl ON tbl.id = t.table_id
             WHERE t.status = 'active' AND t.expected_end_time IS NOT NULL AND t.expected_end_time <= NOW()"
        );

        foreach ($expired as $tx) {
            execute_sql(
                "UPDATE transactions SET end_time = :end_time, total_cost = billiard_cost + fnb_cost, status = 'completed', updated_at = NOW() WHERE id = :id",
                [':end_time' => $tx['expected_end_time'], ':id' => $tx['id']]
            );
            execute_sql("UPDATE tables SET status = 'inactive', updated_at = NOW() WHERE id = :id", [':id' => $tx['table_id']]);
            $table = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tx['table_id']]);
            if ($table) {
                RelayService::setLight($table, false);
            }
        }

        execute_sql(
            "UPDATE transactions SET status = 'cancelled', updated_at = NOW()
             WHERE type = 'billiard' AND status = 'booked' AND expected_end_time IS NOT NULL AND expected_end_time <= NOW()"
        );

        return count($expired);
    }

    public function createBooking(string $tableId, string $userId, string $customerName, int $packageId, string $start, string $end, float $cost): array
    {
        if (in_array($tableId, $this->busyTableIds($start, $end), true)) {
            throw new RuntimeException('Meja ini sudah dibooking pada jam tersebut. Silakan pilih meja lain.');
        }

        execute_sql(
            "INSERT INTO transactions
                (type, table_id, package_id, user_id, customer_name, start_time, expected_end_time,
                 billiard_cost, fnb_cost, total_cost, status, created_at, updated_at)
             VALUES
                ('billiard', :table_id, :package_id, :user_id, :customer_name, :start_time, :expected_end_time,
                 :billiard_cost, 0, :total_cost, 'booked', NOW(), NOW())",
            [
                ':table_id' => $tableId,
                ':package_id' => $packageId,
                ':user_id' => $userId,
                ':customer_name' => $customerName,
                ':start_time' => $start,
                ':expected_end_time' => $end,
                ':billiard_cost' => $cost,
                ':total_cost' => $cost,
            ]
        );

        return $this->find((int) $this->db->lastInsertId());
    }

    public function cancelBooking(int $transactionId, string $userId): void
    {
        $tx = fetch_one('SELECT * FROM transactions WHERE id = :id LIMIT 1', [':id' => $transactionId]);
        if (!$tx || $tx['user_id'] !== $userId || $tx['type'] !== 'billiard') {
            throw new RuntimeException('Booking tidak ditemukan.', 403);
        }
        if ($tx['status'] !== 'booked') {
            throw new RuntimeException('Booking ini tidak bisa dibatalkan.');
        }
        if (strtotime((string) $tx['start_time']) < time()) {
            throw new RuntimeException('Booking yang sudah dimulai tidak bisa dibatalkan.');
        }

        execute_sql("UPDATE transactions SET status = 'cancelled', updated_at = NOW() WHERE id = :id", [':id' => $transactionId]);
    }

    public function createFnbOrder(?string $userId, string $customerName, array $items = []): array
    {
        $customerName = trim($customerName);
        if ($customerName === '') {
            throw new RuntimeException('Nama pelanggan wajib diisi.');
        }
        if ($userId && !fetch_one('SELECT id FROM users WHERE id = :id LIMIT 1', [':id' => $userId])) {
            $userId = null;
        }

        execute_sql(
            "INSERT INTO transactions
                (type, table_id, package_id, user_id, customer_name, start_time,
                 billiard_cost, fnb_cost, total_cost, status, created_at, updated_at)
             VALUES
                ('fnb_only', NULL, NULL, :user_id, :customer_name, :start_time, 0, 0, 0, 'active', NOW(), NOW())",
            [':user_id' => $userId, ':customer_name' => $customerName, ':start_time' => now()]
        );
        $transactionId = (int) $this->db->lastInsertId();
        $fnbCost = $this->insertItems($transactionId, $items);
        execute_sql(
            'UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW() WHERE id = :id',
            [':id' => $transactionId, ':fnb_cost' => $fnbCost, ':total_cost' => $fnbCost]
        );

        return $this->find($transactionId);
    }

    public function getActiveFnbOrders(): array
    {
        $rows = fetch_all(
            "SELECT t.*, u.name AS cashier_name
             FROM transactions t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.type = 'fnb_only' AND t.status = 'active'
             ORDER BY t.created_at DESC"
        );
        return array_map(function (array $row): array {
            $data = $this->shape($row);
            $data['cashier_name'] = $row['cashier_name'] ?? null;
            return $data;
        }, $rows);
    }

    public function getFnbHistory(int $limit = 50): array
    {
        $rows = fetch_all(
            "SELECT t.*, u.name AS cashier_name
             FROM transactions t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.type = 'fnb_only' AND t.status IN ('completed','cancelled')
             ORDER BY t.created_at DESC LIMIT {$limit}"
        );
        return array_map(function (array $row): array {
            $data = $this->shape($row);
            $data['cashier_name'] = $row['cashier_name'] ?? null;
            return $data;
        }, $rows);
    }

    public function fnbOrderDetails(int $id): ?array
    {
        $row = fetch_one(
            "SELECT t.*, u.name AS cashier_name
             FROM transactions t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.id = :id AND t.type = 'fnb_only' LIMIT 1",
            [':id' => $id]
        );
        if (!$row) {
            return null;
        }
        $data = $this->shape($row);
        $data['cashier_name'] = $row['cashier_name'] ?? null;
        return $data;
    }

    public function addItem(int $transactionId, int $fnbItemId, int $quantity = 1): array
    {
        $transaction = fetch_one('SELECT * FROM transactions WHERE id = :id LIMIT 1', [':id' => $transactionId]);
        if (!$transaction || $transaction['status'] !== 'active') {
            throw new RuntimeException('Transaksi tidak ditemukan atau sudah tidak aktif.');
        }

        $fnb = fetch_one('SELECT * FROM fnb_items WHERE id = :id LIMIT 1', [':id' => $fnbItemId]);
        if (!$fnb) {
            throw new RuntimeException('Item F&B tidak ditemukan.');
        }

        $quantity = max(1, $quantity);
        $existing = fetch_one(
            'SELECT * FROM transaction_items WHERE transaction_id = :transaction_id AND fnb_item_id = :fnb_item_id LIMIT 1',
            [':transaction_id' => $transactionId, ':fnb_item_id' => $fnbItemId]
        );

        if ($existing) {
            $newQty = (int) $existing['quantity'] + $quantity;
            execute_sql(
                'UPDATE transaction_items SET quantity = :quantity, subtotal = price * :quantity2, updated_at = NOW() WHERE id = :id',
                [':id' => $existing['id'], ':quantity' => $newQty, ':quantity2' => $newQty]
            );
        } else {
            execute_sql(
                "INSERT INTO transaction_items (transaction_id, fnb_item_id, quantity, price, subtotal, status, created_at, updated_at)
                 VALUES (:transaction_id, :fnb_item_id, :quantity, :price, :subtotal, 'pending', NOW(), NOW())",
                [
                    ':transaction_id' => $transactionId,
                    ':fnb_item_id' => $fnbItemId,
                    ':quantity' => $quantity,
                    ':price' => (float) $fnb['price'],
                    ':subtotal' => (float) $fnb['price'] * $quantity,
                ]
            );
        }

        $this->recalculate($transactionId);
        return $this->find($transactionId) ?? [];
    }

    public function updateItemQuantity(int $itemId, int $change = 1): array
    {
        $item = fetch_one(
            'SELECT ti.*, t.status AS tx_status FROM transaction_items ti JOIN transactions t ON t.id = ti.transaction_id WHERE ti.id = :id LIMIT 1',
            [':id' => $itemId]
        );
        if (!$item || $item['tx_status'] !== 'active') {
            throw new RuntimeException('Item transaksi tidak ditemukan atau transaksi sudah selesai.');
        }

        $newQty = (int) $item['quantity'] + $change;
        if ($newQty <= 0) {
            execute_sql('DELETE FROM transaction_items WHERE id = :id', [':id' => $itemId]);
        } else {
            execute_sql(
                'UPDATE transaction_items SET quantity = :quantity, subtotal = price * :quantity2, updated_at = NOW() WHERE id = :id',
                [':id' => $itemId, ':quantity' => $newQty, ':quantity2' => $newQty]
            );
        }

        $this->recalculate((int) $item['transaction_id']);
        return $this->find((int) $item['transaction_id']) ?? [];
    }

    public function removeItem(int $itemId): array
    {
        $item = fetch_one(
            'SELECT ti.*, t.status AS tx_status FROM transaction_items ti JOIN transactions t ON t.id = ti.transaction_id WHERE ti.id = :id LIMIT 1',
            [':id' => $itemId]
        );
        if (!$item || $item['tx_status'] !== 'active') {
            throw new RuntimeException('Item transaksi tidak ditemukan atau transaksi sudah selesai.');
        }
        execute_sql('DELETE FROM transaction_items WHERE id = :id', [':id' => $itemId]);
        $this->recalculate((int) $item['transaction_id']);
        return $this->find((int) $item['transaction_id']) ?? [];
    }

    public function checkoutFnbOrder(int $transactionId): array
    {
        $row = fetch_one('SELECT * FROM transactions WHERE id = :id LIMIT 1', [':id' => $transactionId]);
        if (!$row || $row['status'] !== 'active') {
            throw new RuntimeException('Pesanan tidak ditemukan atau sudah selesai.');
        }
        if ($row['type'] !== 'fnb_only') {
            throw new RuntimeException('Gunakan checkout meja untuk transaksi biliar.');
        }

        $this->recalculate($transactionId);
        execute_sql(
            "UPDATE transactions SET end_time = NOW(), status = 'completed', updated_at = NOW() WHERE id = :id",
            [':id' => $transactionId]
        );
        return $this->find($transactionId) ?? [];
    }

    public function recalculate(int $transactionId): void
    {
        $row = fetch_one('SELECT billiard_cost FROM transactions WHERE id = :id LIMIT 1', [':id' => $transactionId]);
        if (!$row) {
            return;
        }
        $fnb = (float) fetch_value('SELECT COALESCE(SUM(subtotal), 0) FROM transaction_items WHERE transaction_id = :id', [':id' => $transactionId]);
        $total = (float) $row['billiard_cost'] + $fnb;
        execute_sql(
            'UPDATE transactions SET fnb_cost = :fnb_cost, total_cost = :total_cost, updated_at = NOW() WHERE id = :id',
            [':id' => $transactionId, ':fnb_cost' => $fnb, ':total_cost' => $total]
        );
    }

    public function tablesForDate(string $date): array
    {
        $day = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $rows = fetch_all(
            "SELECT table_id, status, start_time, expected_end_time
             FROM transactions
             WHERE type = 'billiard' AND status IN ('booked','active','completed')
               AND start_time < :day_end
               AND (expected_end_time > :day OR (expected_end_time IS NULL AND start_time >= :day2))
             ORDER BY start_time ASC",
            [':day' => $day, ':day2' => $day, ':day_end' => $dayEnd]
        );

        $byTable = [];
        foreach ($rows as $row) {
            $byTable[$row['table_id']][] = $row;
        }

        $tables = [];
        foreach ($this->allTables() as $table) {
            $bookings = $byTable[$table['id']] ?? [];
            $activeOrBooked = array_values(array_filter($bookings, static fn (array $b): bool => in_array($b['status'], ['booked', 'active'], true)));
            $next = $activeOrBooked[0]['start_time'] ?? null;
            $tables[] = [
                'id' => $table['id'],
                'name' => $table['name'],
                'relay_channel' => $table['relay_channel'],
                'bookings_count' => count($activeOrBooked),
                'total_bookings' => count($bookings),
                'is_active' => in_array('active', array_column($bookings, 'status'), true),
                'next_booking' => to_iso($next),
            ];
        }

        return $tables;
    }

    private function allTables(): array
    {
        $rows = fetch_all('SELECT * FROM tables');
        usort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return array_map(static fn (array $row): array => shape_table($row), $rows);
    }

    public function tableBookings(string $tableId, string $date, string $userId): array
    {
        $day = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $rows = fetch_all(
            "SELECT t.*, p.name AS package_name, p.price AS package_price
             FROM transactions t LEFT JOIN packages p ON p.id = t.package_id
             WHERE t.table_id = :table_id AND t.type = 'billiard'
               AND t.status IN ('booked','active','completed')
               AND t.start_time < :day_end
               AND (t.expected_end_time > :day OR (t.expected_end_time IS NULL AND t.start_time >= :day2))
             ORDER BY t.start_time ASC",
            [':table_id' => $tableId, ':day' => $day, ':day2' => $day, ':day_end' => $dayEnd]
        );

        return array_map(function (array $row) use ($userId): array {
            return [
                'id' => (int) $row['id'],
                'start_time' => to_iso($row['start_time']),
                'expected_end_time' => to_iso($row['expected_end_time']),
                'end_time' => to_iso($row['end_time']),
                'status' => $row['status'],
                'is_own' => $row['user_id'] === $userId,
                'package_name' => $row['package_name'] ?? null,
            ];
        }, $rows);
    }
}
