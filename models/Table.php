<?php
declare(strict_types=1);

class Table
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function all(): array
    {
        $rows = fetch_all('SELECT * FROM tables');
        usort($rows, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        return array_map(static fn (array $row): array => shape_table($row), $rows);
    }

    public function find(string $id): ?array
    {
        $row = fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $id]);
        return $row ? shape_table($row) : null;
    }

    public function findRaw(string $id): ?array
    {
        return fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function itemsForTransaction(int $transactionId): array
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

    public function activeTransaction(string $tableId): ?array
    {
        $row = fetch_one(
            "SELECT t.*, p.name AS package_name, p.price AS package_price
             FROM transactions t
             LEFT JOIN packages p ON p.id = t.package_id
             WHERE t.table_id = :table_id AND t.status = 'active'
             ORDER BY t.created_at DESC LIMIT 1",
            [':table_id' => $tableId]
        );

        if (!$row) {
            return null;
        }

        $package = $row['package_id'] ? [
            'id' => (int) $row['package_id'],
            'name' => $row['package_name'],
            'price' => (string) ($row['package_price'] ?? '0'),
        ] : null;

        return shape_transaction($row, null, $package, null, $this->itemsForTransaction((int) $row['id']));
    }

    public function recentTransactions(string $tableId): array
    {
        $rows = fetch_all(
            "SELECT t.*, p.name AS package_name, p.price AS package_price
             FROM transactions t
             LEFT JOIN packages p ON p.id = t.package_id
             WHERE t.table_id = :table_id
               AND (t.status = 'active' OR t.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR) OR t.end_time >= DATE_SUB(NOW(), INTERVAL 48 HOUR))
             ORDER BY t.created_at DESC",
            [':table_id' => $tableId]
        );

        return array_map(function (array $row): array {
            $package = $row['package_id'] ? [
                'id' => (int) $row['package_id'],
                'name' => $row['package_name'],
                'price' => (string) ($row['package_price'] ?? '0'),
            ] : null;
            return shape_transaction($row, null, $package, null, $this->itemsForTransaction((int) $row['id']));
        }, $rows);
    }

    public function allWithActiveAndRecentTransactions(): array
    {
        $tables = $this->all();
        foreach ($tables as &$table) {
            $active = $this->activeTransaction($table['id']);
            $table['transactions'] = $active ? [$active] : [];
            $table['recent_transactions'] = $this->recentTransactions($table['id']);
        }
        unset($table);
        return $tables;
    }

    public function create(string $name, int $relayChannel): string
    {
        $id = uuid_v4();
        execute_sql(
            "INSERT INTO tables (id, name, relay_channel, status, is_light_on, created_at, updated_at)
             VALUES (:id, :name, :relay_channel, 'inactive', 0, NOW(), NOW())",
            [':id' => $id, ':name' => $name, ':relay_channel' => $relayChannel]
        );
        return $id;
    }

    public function update(string $id, string $name, int $relayChannel): void
    {
        execute_sql('UPDATE tables SET name = :name, relay_channel = :relay_channel, updated_at = NOW() WHERE id = :id', [
            ':id' => $id,
            ':name' => $name,
            ':relay_channel' => $relayChannel,
        ]);
    }

    public function delete(string $id): void
    {
        $active = fetch_one("SELECT id FROM transactions WHERE table_id = :id AND status = 'active' LIMIT 1", [':id' => $id]);
        if ($active) {
            throw new RuntimeException('Meja sedang digunakan dalam sesi aktif dan tidak dapat dihapus.');
        }
        execute_sql('DELETE FROM tables WHERE id = :id', [':id' => $id]);
    }

    public function setStatus(string $id, string $status): void
    {
        execute_sql('UPDATE tables SET status = :status, updated_at = NOW() WHERE id = :id', [
            ':id' => $id,
            ':status' => $status,
        ]);
    }

    public function setLightState(string $id, bool $on): void
    {
        execute_sql('UPDATE tables SET is_light_on = :is_light_on, updated_at = NOW() WHERE id = :id', [
            ':id' => $id,
            ':is_light_on' => $on ? 1 : 0,
        ]);
    }
}
