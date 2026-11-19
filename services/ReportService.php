<?php
declare(strict_types=1);

class ReportService
{
    public function fnbSales(string $startDate, string $endDate): array
    {
        $sales = fetch_all(
            "SELECT ti.fnb_item_id,
                    SUM(ti.quantity) AS total_qty,
                    SUM(ti.subtotal) AS total_revenue
             FROM transaction_items ti
             JOIN transactions t ON t.id = ti.transaction_id
             WHERE t.status = 'completed' AND DATE(t.created_at) BETWEEN :start_date AND :end_date
             GROUP BY ti.fnb_item_id
             ORDER BY total_revenue DESC",
            [':start_date' => $startDate, ':end_date' => $endDate]
        );

        $sales = array_map(static function (array $row): array {
            $fnb = fetch_one('SELECT * FROM fnb_items WHERE id = :id LIMIT 1', [':id' => $row['fnb_item_id']]);
            return [
                'fnb_item_id' => (int) $row['fnb_item_id'],
                'total_qty' => (int) $row['total_qty'],
                'total_revenue' => (string) $row['total_revenue'],
                'fnb_item' => $fnb ? shape_fnb_item($fnb) : null,
            ];
        }, $sales);

        $summary = fetch_one(
            "SELECT SUM(ti.quantity) AS total_qty, SUM(ti.subtotal) AS total_revenue
             FROM transaction_items ti
             JOIN transactions t ON t.id = ti.transaction_id
             WHERE t.status = 'completed' AND DATE(t.created_at) BETWEEN :start_date AND :end_date",
            [':start_date' => $startDate, ':end_date' => $endDate]
        ) ?? ['total_qty' => 0, 'total_revenue' => 0];

        return [
            'sales' => $sales,
            'summary' => [
                'total_qty' => (int) ($summary['total_qty'] ?? 0),
                'total_revenue' => (string) ($summary['total_revenue'] ?? '0'),
            ],
        ];
    }

    public function tableTransactions(string $startDate, string $endDate): array
    {
        $rows = fetch_all(
            "SELECT t.*, tbl.name AS table_name, tbl.relay_channel AS table_relay_channel,
                    p.name AS package_name, p.price AS package_price,
                    u.name AS user_name
             FROM transactions t
             LEFT JOIN tables tbl ON tbl.id = t.table_id
             LEFT JOIN packages p ON p.id = t.package_id
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.type = 'billiard' AND t.status = 'completed'
               AND DATE(t.created_at) BETWEEN :start_date AND :end_date
             ORDER BY t.created_at DESC",
            [':start_date' => $startDate, ':end_date' => $endDate]
        );

        $transactions = array_map(function (array $row): array {
            $user = $row['user_id'] ? ['id' => $row['user_id'], 'name' => $row['user_name'] ?? ''] : null;
            return shape_transaction(
                $row,
                $row['table_id'] ? ['id' => $row['table_id'], 'name' => $row['table_name'], 'relay_channel' => (int) $row['table_relay_channel']] : null,
                $row['package_id'] ? ['id' => (int) $row['package_id'], 'name' => $row['package_name'], 'price' => (string) $row['package_price']] : null,
                $user,
                []
            );
        }, $rows);

        $summary = fetch_one(
            "SELECT COUNT(*) AS total_transactions,
                    COALESCE(SUM(billiard_cost), 0) AS total_billiard,
                    COALESCE(SUM(fnb_cost), 0) AS total_fnb,
                    COALESCE(SUM(total_cost), 0) AS total_revenue
             FROM transactions
             WHERE type = 'billiard' AND status = 'completed'
               AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':start_date' => $startDate, ':end_date' => $endDate]
        ) ?? [];

        return [
            'transactions' => $transactions,
            'summary' => [
                'total_transactions' => (int) ($summary['total_transactions'] ?? 0),
                'total_billiard' => (string) ($summary['total_billiard'] ?? '0'),
                'total_fnb' => (string) ($summary['total_fnb'] ?? '0'),
                'total_revenue' => (string) ($summary['total_revenue'] ?? '0'),
            ],
        ];
    }

    public function revenue(string $startDate, string $endDate): array
    {
        $daily = fetch_all(
            "SELECT DATE(created_at) AS date,
                    COALESCE(SUM(billiard_cost), 0) AS billiard_revenue,
                    COALESCE(SUM(fnb_cost), 0) AS fnb_revenue,
                    COALESCE(SUM(total_cost), 0) AS total_revenue,
                    COUNT(*) AS transaction_count
             FROM transactions
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :start_date AND :end_date
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            [':start_date' => $startDate, ':end_date' => $endDate]
        );

        $summary = fetch_one(
            "SELECT COALESCE(SUM(billiard_cost), 0) AS total_billiard,
                    COALESCE(SUM(fnb_cost), 0) AS total_fnb,
                    COALESCE(SUM(total_cost), 0) AS total_revenue,
                    COUNT(*) AS total_transactions
             FROM transactions
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':start_date' => $startDate, ':end_date' => $endDate]
        ) ?? [];

        return [
            'dailyRevenue' => array_map(static fn (array $row): array => [
                'date' => $row['date'],
                'billiard_revenue' => (string) $row['billiard_revenue'],
                'fnb_revenue' => (string) $row['fnb_revenue'],
                'total_revenue' => (string) $row['total_revenue'],
                'transaction_count' => (int) $row['transaction_count'],
            ], $daily),
            'summary' => [
                'total_billiard' => (string) ($summary['total_billiard'] ?? '0'),
                'total_fnb' => (string) ($summary['total_fnb'] ?? '0'),
                'total_revenue' => (string) ($summary['total_revenue'] ?? '0'),
                'total_transactions' => (int) ($summary['total_transactions'] ?? 0),
            ],
        ];
    }

    public function analytics(string $startDate, string $endDate): array
    {
        $rows = fetch_all(
            "SELECT * FROM transactions
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :start_date AND :end_date
             ORDER BY created_at ASC",
            [':start_date' => $startDate, ':end_date' => $endDate]
        );

        $hourly = [];
        foreach ($rows as $row) {
            $hour = date('H', strtotime((string) $row['created_at']));
            $hourly[$hour][] = $row;
        }
        ksort($hourly);
        $hourlyStats = [];
        foreach ($hourly as $hour => $group) {
            $durations = array_map([$this, 'durationMinutes'], array_values(array_filter($group, static fn (array $r): bool => !empty($r['end_time']))));
            $hourlyStats[] = [
                'hour' => $hour . ':00',
                'transactions' => count($group),
                'revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $group)),
                'avg_duration' => $durations ? round(array_sum($durations) / count($durations)) : 0,
            ];
        }

        $tables = fetch_all('SELECT * FROM tables');
        usort($tables, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));
        $tableUtilization = [];
        foreach ($tables as $table) {
            $txns = array_values(array_filter($rows, static fn (array $r): bool => $r['table_id'] === $table['id']));
            $minutes = array_sum(array_map([$this, 'durationMinutes'], array_values(array_filter($txns, static fn (array $r): bool => !empty($r['end_time'])))));
            $tableUtilization[] = [
                'table_number' => (int) $table['relay_channel'],
                'table_name' => $table['name'],
                'transactions' => count($txns),
                'total_hours' => round($minutes / 60, 1),
                'revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $txns)),
            ];
        }
        usort($tableUtilization, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        $packages = fetch_all('SELECT * FROM packages');
        $packageStats = [];
        foreach ($packages as $package) {
            $txns = array_values(array_filter($rows, static fn (array $r): bool => (int) $r['package_id'] === (int) $package['id']));
            $minutes = array_sum(array_map([$this, 'durationMinutes'], array_values(array_filter($txns, static fn (array $r): bool => !empty($r['end_time'])))));
            $packageStats[] = [
                'name' => $package['name'],
                'price_per_hour' => (float) $package['price'],
                'transactions' => count($txns),
                'revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $txns)),
                'total_hours' => round($minutes / 60, 1),
            ];
        }
        usort($packageStats, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        $dailyStats = [];
        $cursor = new DateTime($startDate);
        $end = new DateTime($endDate);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $dayRows = array_values(array_filter($rows, static fn (array $r): bool => date('Y-m-d', strtotime((string) $r['created_at'])) === $date));
            $durations = array_map([$this, 'durationMinutes'], array_values(array_filter($dayRows, static fn (array $r): bool => !empty($r['end_time']))));
            $dailyStats[] = [
                'date' => $date,
                'transactions' => count($dayRows),
                'revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $dayRows)),
                'billiard_revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $dayRows)),
                'fnb_revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['fnb_cost'], $dayRows)),
                'avg_duration' => $durations ? round(array_sum($durations) / count($durations)) : 0,
            ];
            $cursor->modify('+1 day');
        }

        $allDurations = array_map([$this, 'durationMinutes'], array_values(array_filter($rows, static fn (array $r): bool => !empty($r['end_time']))));
        $summary = [
            'total_transactions' => count($rows),
            'total_revenue' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $rows)),
            'total_billiard' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $rows)),
            'total_fnb' => (float) array_sum(array_map(static fn (array $r): float => (float) $r['fnb_cost'], $rows)),
            'avg_transaction_value' => $rows ? array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $rows)) / count($rows) : 0,
            'avg_duration' => $allDurations ? round(array_sum($allDurations) / count($allDurations)) : 0,
            'unique_tables_used' => count(array_unique(array_filter(array_column($rows, 'table_id')))),
        ];

        return [
            'hourlyStats' => $hourlyStats,
            'tableUtilization' => $tableUtilization,
            'packageStats' => $packageStats,
            'dailyStats' => $dailyStats,
            'summary' => $summary,
        ];
    }

    public function durationMinutes(array $row): float
    {
        if (empty($row['start_time']) || empty($row['end_time'])) {
            return 0;
        }
        return max(0, (strtotime((string) $row['end_time']) - strtotime((string) $row['start_time'])) / 60);
    }
}
