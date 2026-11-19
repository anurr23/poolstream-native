<?php
declare(strict_types=1);

/**
 * Menyusun fact-pack agregat untuk Evaluasi AI.
 * Angka dihitung di server; AI hanya menafsirkan.
 */
class ReportDataService
{
    public const SECTIONS = [
        'ringkasan' => 'Ringkasan KPI',
        'pendapatan' => 'Tren Pendapatan',
        'meja' => 'Utilisasi Meja',
        'fnb' => 'Penjualan F&B',
        'paket' => 'Performa Paket',
        'jam_sibuk' => 'Jam Sibuk',
        'pembatalan' => 'Pembatalan & Booking Hangus',
        'perbandingan' => 'Perbandingan Periode',
    ];

    private const OPERATING_HOURS_PER_DAY = 12;

    public function bucketFor(string $start, string $end): string
    {
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
        if ($days <= 31) {
            return 'day';
        }
        if ($days <= 180) {
            return 'week';
        }
        return 'month';
    }

    public function build(string $start, string $end, array $sections, string $bucket, bool $includeSample = false): array
    {
        $sections = array_values(array_intersect($sections, array_keys(self::SECTIONS)));
        $startDate = date('Y-m-d', strtotime($start));
        $endDate = date('Y-m-d', strtotime($end));
        $startAt = $startDate . ' 00:00:00';
        $endAt = $endDate . ' 23:59:59';

        $payload = [
            'periode' => [
                'mulai' => $startDate,
                'selesai' => $endDate,
                'jumlah_hari' => (new DateTime($startDate))->diff(new DateTime($endDate))->days + 1,
                'bucket' => $bucket,
                'dasar_tanggal' => 'created_at transaksi (hanya transaksi berstatus completed)',
                'mata_uang' => 'IDR',
            ],
        ];

        $rows = fetch_all(
            "SELECT created_at, start_time, end_time, type, table_id, package_id, billiard_cost, fnb_cost, total_cost
             FROM transactions
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :start_date AND :end_date
             ORDER BY created_at ASC",
            [':start_date' => $startDate, ':end_date' => $endDate]
        );

        if (in_array('ringkasan', $sections, true)) {
            $payload['ringkasan'] = $this->summary($rows, $startDate, $endDate);
        }
        if (in_array('pendapatan', $sections, true)) {
            $payload['pendapatan'] = $this->revenueSeries($rows, $bucket);
        }
        if (in_array('meja', $sections, true)) {
            $payload['meja'] = $this->tableUtilization($rows, $startDate, $endDate);
        }
        if (in_array('fnb', $sections, true)) {
            $payload['fnb'] = $this->fnbSales($startDate, $endDate);
        }
        if (in_array('paket', $sections, true)) {
            $payload['paket'] = $this->packageStats($rows);
        }
        if (in_array('jam_sibuk', $sections, true)) {
            $payload['jam_sibuk'] = $this->busyHours($rows);
        }
        if (in_array('pembatalan', $sections, true)) {
            $payload['pembatalan'] = $this->cancellations($startDate, $endDate);
        }
        if (in_array('perbandingan', $sections, true)) {
            $payload['perbandingan'] = $this->comparison($startDate, $endDate);
        }
        if ($includeSample) {
            $payload['sampel_transaksi'] = $this->sample($startDate, $endDate);
        }

        return $payload;
    }

    private function durationMinutes(array $row): float
    {
        if (empty($row['start_time'])) {
            return 0;
        }
        $end = $row['end_time'] ?: $row['created_at'];
        if (!$end) {
            return 0;
        }
        return max(0, (strtotime((string) $end) - strtotime((string) $row['start_time'])) / 60);
    }

    private function summary(array $rows, string $start, string $end): array
    {
        $count = count($rows);
        $billiard = array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $rows));
        $fnb = array_sum(array_map(static fn (array $r): float => (float) $r['fnb_cost'], $rows));
        $total = array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $rows));
        $durations = array_map(fn (array $r): float => $this->durationMinutes($r), array_values(array_filter($rows, static fn (array $r): bool => !empty($r['end_time']))));
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;

        return [
            'total_transaksi' => $count,
            'transaksi_biliar' => count(array_filter($rows, static fn (array $r): bool => $r['type'] === 'billiard')),
            'transaksi_fnb_saja' => count(array_filter($rows, static fn (array $r): bool => $r['type'] === 'fnb_only')),
            'revenue_biliar' => round($billiard, 2),
            'revenue_fnb' => round($fnb, 2),
            'revenue_total' => round($total, 2),
            'rata_rata_nilai_transaksi' => $count ? round($total / $count, 2) : 0,
            'rata_rata_durasi_menit' => $durations ? round(array_sum($durations) / count($durations), 1) : 0,
            'meja_terpakai' => count(array_unique(array_filter(array_column($rows, 'table_id')))),
            'rata_rata_transaksi_per_hari' => $days > 0 ? round($count / $days, 2) : 0,
            'rata_rata_revenue_per_hari' => $days > 0 ? round($total / $days, 2) : 0,
        ];
    }

    private function revenueSeries(array $rows, string $bucket): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $date = new DateTime((string) $row['created_at']);
            $label = match ($bucket) {
                'week' => $date->modify('monday this week')->format('Y-m-d'),
                'month' => $date->format('Y-m'),
                default => $date->format('Y-m-d'),
            };
            $grouped[$label][] = $row;
        }
        ksort($grouped);

        return [
            'bucket' => $bucket,
            'deret' => array_values(array_map(static function (array $group, string $label): array {
                return [
                    'label' => $label,
                    'transaksi' => count($group),
                    'revenue_biliar' => round(array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $group)), 2),
                    'revenue_fnb' => round(array_sum(array_map(static fn (array $r): float => (float) $r['fnb_cost'], $group)), 2),
                    'revenue_total' => round(array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $group)), 2),
                ];
            }, $grouped, array_keys($grouped))),
        ];
    }

    private function tableUtilization(array $rows, string $start, string $end): array
    {
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
        $capacityHours = max(1, $days * self::OPERATING_HOURS_PER_DAY);
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['table_id'] ?? 'none'][] = $row;
        }

        $data = [];
        foreach ($grouped as $tableId => $group) {
            $table = $tableId !== 'none' ? fetch_one('SELECT * FROM tables WHERE id = :id LIMIT 1', [':id' => $tableId]) : null;
            $minutes = array_sum(array_map(fn (array $r): float => $this->durationMinutes($r), $group));
            $hours = round($minutes / 60, 1);
            $durations = array_map(fn (array $r): float => $this->durationMinutes($r), array_values(array_filter($group, static fn (array $r): bool => !empty($r['end_time']))));
            $data[] = [
                'meja' => $table['name'] ?? 'Meja terhapus',
                'nomor_meja' => $table['relay_channel'] ?? null,
                'transaksi' => count($group),
                'total_jam_pakai' => $hours,
                'estimasi_utilisasi_persen' => min(100, round($hours / $capacityHours * 100, 1)),
                'revenue' => round(array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $group)), 2),
                'rata_rata_durasi_menit' => $durations ? round(array_sum($durations) / count($durations), 1) : 0,
            ];
        }
        usort($data, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return [
            'asumsi_jam_operasional_per_hari' => self::OPERATING_HOURS_PER_DAY,
            'data' => $data,
        ];
    }

    private function fnbSales(string $start, string $end): array
    {
        $items = fetch_all(
            "SELECT ti.fnb_item_id, SUM(ti.quantity) AS qty, SUM(ti.subtotal) AS revenue
             FROM transaction_items ti
             JOIN transactions t ON t.id = ti.transaction_id
             WHERE t.status = 'completed' AND DATE(t.created_at) BETWEEN :start_date AND :end_date
             GROUP BY ti.fnb_item_id",
            [':start_date' => $start, ':end_date' => $end]
        );
        $totalRevenue = array_sum(array_map(static fn (array $r): float => (float) $r['revenue'], $items));
        $data = [];
        foreach ($items as $item) {
            $fnb = fetch_one('SELECT * FROM fnb_items WHERE id = :id LIMIT 1', [':id' => $item['fnb_item_id']]);
            $data[] = [
                'item' => $fnb['name'] ?? 'Item terhapus',
                'kategori' => $fnb['category'] ?? null,
                'qty' => (int) $item['qty'],
                'revenue' => round((float) $item['revenue'], 2),
                'kontribusi_persen' => $totalRevenue > 0 ? round((float) $item['revenue'] / $totalRevenue * 100, 1) : 0,
            ];
        }
        usort($data, static fn (array $a, array $b): int => $b['revenue'] <=> $a['revenue']);

        return [
            'total_item_terjual' => array_sum(array_map(static fn (array $r): int => (int) $r['qty'], $items)),
            'total_revenue' => round($totalRevenue, 2),
            'data' => $data,
        ];
    }

    private function packageStats(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['package_id'] ?? 'none'][] = $row;
        }
        $data = [];
        foreach ($grouped as $packageId => $group) {
            $package = $packageId !== 'none' ? fetch_one('SELECT * FROM packages WHERE id = :id LIMIT 1', [':id' => $packageId]) : null;
            $minutes = array_sum(array_map(fn (array $r): float => $this->durationMinutes($r), $group));
            $durations = array_map(fn (array $r): float => $this->durationMinutes($r), array_values(array_filter($group, static fn (array $r): bool => !empty($r['end_time']))));
            $data[] = [
                'paket' => $package['name'] ?? 'Tanpa paket / F&B saja',
                'transaksi' => count($group),
                'total_jam' => round($minutes / 60, 1),
                'revenue_biliar' => round(array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $group)), 2),
                'rata_rata_durasi_menit' => $durations ? round(array_sum($durations) / count($durations), 1) : 0,
            ];
        }
        usort($data, static fn (array $a, array $b): int => $b['revenue_biliar'] <=> $a['revenue_biliar']);
        return $data;
    }

    private function busyHours(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[date('H', strtotime((string) $row['created_at']))][] = $row;
        }
        ksort($grouped);
        $data = [];
        foreach ($grouped as $hour => $group) {
            $durations = array_map(fn (array $r): float => $this->durationMinutes($r), array_values(array_filter($group, static fn (array $r): bool => !empty($r['end_time']))));
            $data[] = [
                'jam' => $hour . ':00',
                'transaksi' => count($group),
                'revenue' => round(array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $group)), 2),
                'rata_rata_durasi_menit' => $durations ? round(array_sum($durations) / count($durations), 1) : 0,
            ];
        }
        return $data;
    }

    private function cancellations(string $start, string $end): array
    {
        $cancelled = fetch_all(
            "SELECT total_cost FROM transactions WHERE status = 'cancelled' AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':start_date' => $start, ':end_date' => $end]
        );
        $noShow = (int) fetch_value(
            "SELECT COUNT(*) FROM transactions
             WHERE status = 'booked' AND expected_end_time IS NOT NULL AND expected_end_time < NOW()
               AND DATE(start_time) BETWEEN :start_date AND :end_date",
            [':start_date' => $start, ':end_date' => $end]
        );

        return [
            'transaksi_dibatalkan' => count($cancelled),
            'nilai_dibatalkan' => round(array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $cancelled)), 2),
            'booking_hangus' => $noShow,
        ];
    }

    private function comparison(string $start, string $end): array
    {
        $days = (new DateTime($start))->diff(new DateTime($end))->days + 1;
        $previousStart = date('Y-m-d', strtotime($start . ' -' . $days . ' days'));
        $previousEnd = date('Y-m-d', strtotime($start . ' -1 day'));
        $previous = fetch_all(
            "SELECT total_cost, billiard_cost, fnb_cost FROM transactions
             WHERE status = 'completed' AND DATE(created_at) BETWEEN :start_date AND :end_date",
            [':start_date' => $previousStart, ':end_date' => $previousEnd]
        );
        $count = count($previous);
        $total = array_sum(array_map(static fn (array $r): float => (float) $r['total_cost'], $previous));

        return [
            'periode_pembanding' => ['mulai' => $previousStart, 'selesai' => $previousEnd],
            'transaksi' => $count,
            'revenue_biliar' => round(array_sum(array_map(static fn (array $r): float => (float) $r['billiard_cost'], $previous)), 2),
            'revenue_fnb' => round(array_sum(array_map(static fn (array $r): float => (float) $r['fnb_cost'], $previous)), 2),
            'revenue_total' => round($total, 2),
            'rata_rata_nilai_transaksi' => $count ? round($total / $count, 2) : 0,
        ];
    }

    private function sample(string $start, string $end): array
    {
        $rows = fetch_all(
            "SELECT t.*, tbl.name AS table_name, p.name AS package_name
             FROM transactions t
             LEFT JOIN tables tbl ON tbl.id = t.table_id
             LEFT JOIN packages p ON p.id = t.package_id
             WHERE t.status = 'completed' AND DATE(t.created_at) BETWEEN :start_date AND :end_date
             ORDER BY t.created_at DESC LIMIT 50",
            [':start_date' => $start, ':end_date' => $end]
        );
        return array_map(function (array $row): array {
            return [
                'tanggal' => $row['created_at'],
                'meja' => $row['table_name'] ?? null,
                'paket' => $row['package_name'] ?? null,
                'durasi_menit' => $this->durationMinutes($row),
                'biliar' => (float) $row['billiard_cost'],
                'fnb' => (float) $row['fnb_cost'],
                'total' => (float) $row['total_cost'],
                'pelanggan' => mask_name($row['customer_name'] ?? null),
            ];
        }, $rows);
    }
}
