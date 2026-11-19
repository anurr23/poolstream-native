<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role('admin');

$startDate = (string) ($_GET['start_date'] ?? date('Y-m-d'));
$endDate = (string) ($_GET['end_date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    $startDate = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    $endDate = date('Y-m-d');
}

$data = (new ReportService())->revenue($startDate, $endDate);
render_page('Master/Reports/Revenue', [
    'dailyRevenue' => $data['dailyRevenue'],
    'summary' => $data['summary'],
    'startDate' => $startDate,
    'endDate' => $endDate,
], 'Laporan Total Pendapatan');
