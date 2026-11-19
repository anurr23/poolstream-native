<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role('admin');

$sections = [];
foreach (ReportDataService::SECTIONS as $key => $label) {
    $sections[] = ['key' => $key, 'label' => $label];
}

$presets = [];
foreach ([
    'today' => 'Hari ini',
    '7d' => '7 hari terakhir',
    'month' => 'Bulan ini',
    'last_month' => 'Bulan lalu',
    '3m' => '3 bulan terakhir',
    '6m' => '6 bulan terakhir',
    'year' => 'Tahun ini',
    '12m' => '12 bulan terakhir',
    'all' => 'Semua data',
    'custom' => 'Rentang khusus',
] as $key => $label) {
    $presets[] = ['key' => $key, 'label' => $label];
}

$history = array_map(static fn (array $row): array => [
    'id' => (int) $row['id'],
    'preset' => $row['preset'],
    'start_date' => $row['start_date'],
    'end_date' => $row['end_date'],
    'status' => $row['status'],
    'created_at' => $row['created_at'],
], (new AiEvaluation())->latest(20));

render_page('Master/AiEvaluation/Index', [
    'presets' => $presets,
    'sections' => $sections,
    'defaultSections' => ['ringkasan', 'pendapatan', 'meja', 'fnb'],
    'history' => $history,
    'today' => date('Y-m-d'),
], 'Evaluasi AI');
