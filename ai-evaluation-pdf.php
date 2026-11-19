<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$evaluation = $id > 0 ? (new AiEvaluation())->find($id) : null;
if (!$evaluation) {
    flash('error', 'Evaluasi tidak ditemukan.');
    redirect('ai-evaluations.php');
}
if ($evaluation['status'] !== 'completed') {
    flash('error', 'PDF hanya bisa dibuat setelah evaluasi selesai diproses.');
    redirect('ai-evaluation.php?id=' . $id);
}

$payload = $evaluation['payload'] ?? [];
$series = $payload['pendapatan']['deret'] ?? [];
$series = is_array($series) ? array_slice($series, -14) : [];
$values = array_map(static fn (array $row): float => (float) ($row['revenue_total'] ?? 0), $series);
$max = $values ? max($values) : 1;
$max = $max > 0 ? $max : 1;
$chart = [
    'max' => (int) round($max),
    'total' => (int) round(array_sum($values)),
    'bars' => array_map(static fn (array $row, int $index): array => [
        'label' => (string) ($row['label'] ?? ''),
        'value' => (int) round($values[$index]),
        'pct' => round($values[$index] / $max * 100, 1),
    ], $series, array_keys($values)),
];

$sections = ReportDataService::SECTIONS;
$logo = str_replace('\\', '/', APP_ROOT . '/assets/images/logo.jpg');
if (!is_file($logo)) {
    $logo = str_replace('\\', '/', APP_ROOT . '/assets/images/logo.png');
}

$data = [
    'evaluation' => $evaluation,
    'result' => $evaluation['result'] ?? [],
    'payload' => $payload,
    'sections' => $sections,
    'logo' => $logo,
    'generatedAt' => date('d F Y, H:i'),
    'chart' => $chart,
    'presetLabel' => AiEvaluationPresenter::label($evaluation['preset']),
];

$autoload = APP_ROOT . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo 'Dompdf belum terpasang. Jalankan `composer install` di folder poolstream-native.';
    exit;
}
require_once $autoload;

ob_start();
extract($data, EXTR_SKIP);
require APP_ROOT . '/views/ai-evaluation-pdf.php';
$html = (string) ob_get_clean();

$dompdf = new \Dompdf\Dompdf([
    'isRemoteEnabled' => true,
    'chroot' => APP_ROOT,
    'defaultFont' => 'DejaVu Sans',
]);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Evaluasi-AI-PoolStream_' . date('Ymd', strtotime((string) $evaluation['start_date'])) . '-' . date('Ymd', strtotime((string) $evaluation['end_date'])) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;
