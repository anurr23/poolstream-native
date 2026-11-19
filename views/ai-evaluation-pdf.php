<?php
declare(strict_types=1);

/**
 * Template PDF Evaluasi AI (dirender dengan Dompdf).
 * Variabel: $evaluation, $result, $payload, $sections, $logo, $generatedAt, $chart, $presetLabel
 */
$presetLabel = $presetLabel ?? 'Kustom';
$result = $result ?? [];
$payload = $payload ?? [];
$chart = $chart ?? ['bars' => [], 'total' => 0];
$startLabel = date('d/m/Y', strtotime((string) $evaluation['start_date']));
$endLabel = date('d/m/Y', strtotime((string) $evaluation['end_date']));
$startLong = date('d M Y', strtotime((string) $evaluation['start_date']));
$endLong = date('d M Y', strtotime((string) $evaluation['end_date']));
$trendClass = static fn (?string $trend): string => $trend === 'naik' ? 'trend-up' : ($trend === 'turun' ? 'trend-down' : 'trend-flat');
$impactClass = static fn (?string $impact): string => in_array($impact, ['tinggi', 'sedang', 'rendah'], true) ? $impact : 'rendah';
$tableRows = $payload['meja']['data'] ?? [];
$foodRows = $payload['fnb']['data'] ?? [];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Evaluasi AI PoolStream</title>
<style>
@page { margin: 16mm 14mm 24mm 14mm; }
* { box-sizing: border-box; }
body { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 9.5px; line-height: 1.55; color: #1f2937; margin: 0; }
.footer { position: fixed; bottom: -16mm; left: 0; right: 0; font-size: 7.5px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 4px; }
.footer-table { width: 100%; border-collapse: collapse; }
.footer-table td { padding: 0; font-size: 7.5px; color: #9ca3af; }
.footer-table .right { text-align: right; }
.brand { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
.brand td { vertical-align: middle; padding: 0; }
.brand-logo { width: 56px; }
.brand-logo img { height: 42px; }
.brand-text { padding-left: 8px; }
.brand-name { font-size: 17px; font-weight: bold; color: #4f46e5; letter-spacing: .2px; line-height: 1.1; }
.brand-sub { font-size: 8.5px; color: #6b7280; }
.brand-meta { text-align: right; font-size: 8px; color: #4b5563; line-height: 1.5; }
.brand-meta .period { font-weight: bold; color: #111827; }
.rule { height: 2px; background: #4f46e5; margin: 0 0 12px; }
.section { margin-bottom: 12px; }
.section-title { font-size: 10.5px; font-weight: bold; color: #312e81; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .4px; }
.paragraph { text-align: justify; white-space: pre-line; }
.kpi, .data-table, .chart, .meta { width: 100%; border-collapse: collapse; }
.kpi th, .data-table th { background: #eef2ff; color: #3730a3; text-align: left; font-size: 8px; padding: 4px 6px; border: 1px solid #e5e7eb; }
.kpi td, .data-table td { padding: 4px 6px; border: 1px solid #e5e7eb; vertical-align: top; }
.kpi .metric { font-weight: bold; width: 26%; }
.kpi .value { font-weight: bold; color: #111827; width: 18%; }
.kpi .trend { width: 12%; font-weight: bold; }
.trend-up { color: #059669; } .trend-down { color: #dc2626; } .trend-flat { color: #6b7280; }
.analysis-aspect { font-weight: bold; color: #312e81; margin-top: 6px; margin-bottom: 2px; }
.chart { border-collapse: collapse; margin-top: 4px; }
.chart .bar-cell { vertical-align: bottom; text-align: center; padding: 0 2px; }
.chart .bar { background: linear-gradient(180deg, #6366f1, #8b5cf6); border-radius: 3px 3px 0 0; min-height: 4px; }
.chart .bar-value { font-size: 6.5px; color: #6b7280; }
.chart .bar-label { font-size: 6.5px; color: #6b7280; text-align: center; padding-top: 2px; }
.chart-note { font-size: 7.5px; color: #6b7280; margin-top: 3px; }
.finding, .risk, .reco { border: 1px solid #e5e7eb; border-left: 4px solid #6366f1; border-radius: 4px; padding: 7px 9px; margin-bottom: 7px; page-break-inside: avoid; }
.finding.tinggi { border-left-color: #dc2626; } .finding.sedang { border-left-color: #f59e0b; } .finding.rendah { border-left-color: #10b981; }
.finding-head { width: 100%; border-collapse: collapse; }
.finding-title { font-weight: bold; color: #111827; }
.finding-head .impact { text-align: right; width: 60px; }
.pill { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 6.5px; font-weight: bold; text-transform: uppercase; background: #f3f4f6; color: #374151; }
.pill.tinggi { background: #fee2e2; color: #b91c1c; } .pill.sedang { background: #fef3c7; color: #b45309; } .pill.rendah { background: #d1fae5; color: #047857; }
.finding-implication { margin-top: 3px; }
.finding-evidence { margin-top: 4px; padding: 4px 7px; background: #f9fafb; color: #6b7280; font-size: 8px; border-radius: 3px; }
.ordered { margin: 0; padding-left: 16px; }
.ordered > li { margin-bottom: 7px; page-break-inside: avoid; }
.ordered .title, .risk .title { font-weight: bold; color: #111827; }
.ordered .sub, .risk .sub { color: #4b5563; margin: 2px 0; }
.steps { margin: 3px 0 0; padding-left: 14px; }
.expected { margin-top: 3px; font-size: 8px; color: #065f46; }
.conclusion { padding: 8px 10px; background: #eef2ff; border-left: 4px solid #4f46e5; border-radius: 4px; font-weight: bold; color: #312e81; }
.meta { margin-top: 4px; }
.meta td { width: 50%; vertical-align: top; padding: 6px 8px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 8px; color: #4b5563; }
.disclaimer { margin-top: 7px; padding: 6px 9px; background: #f3f4f6; color: #6b7280; font-size: 7.6px; line-height: 1.5; }
.text-right { text-align: right; }
</style>
</head>
<body>
<div class="footer">
    <table class="footer-table"><tr>
        <td class="left">PoolStream &middot; Laporan Evaluasi AI &middot; Dokumen internal</td>
        <td class="right"><?= e($startLabel) ?> &ndash; <?= e($endLabel) ?></td>
    </tr></table>
</div>

<table class="brand"><tr>
    <td class="brand-logo"><img src="<?= e($logo) ?>" alt="PoolStream"></td>
    <td class="brand-text">
        <div class="brand-name">PoolStream</div>
        <div class="brand-sub">Laporan Evaluasi AI &middot; Analisa performa usaha</div>
    </td>
    <td class="brand-meta">
        <div class="period">Periode <?= e($presetLabel) ?></div>
        <div><?= e($startLong) ?> &ndash; <?= e($endLong) ?></div>
        <div>Dibuat <?= e($generatedAt) ?></div>
    </td>
</tr></table>
<div class="rule"></div>

<?php if (!empty($result['ringkasan'])): ?>
<div class="section"><div class="section-title">Ringkasan Eksekutif</div><div class="paragraph"><?= nl2br(e($result['ringkasan'])) ?></div></div>
<?php endif; ?>

<?php if (!empty($result['metrik_kunci'])): ?>
<div class="section"><div class="section-title">Metrik Kunci</div>
<table class="kpi"><thead><tr><th>Metrik</th><th>Nilai</th><th>Tren</th><th>Catatan</th></tr></thead><tbody>
<?php foreach ($result['metrik_kunci'] as $metric): ?>
<tr>
    <td class="metric"><?= e($metric['label'] ?? '-') ?></td>
    <td class="value"><?= e($metric['nilai'] ?? '-') ?></td>
    <td class="trend <?= e($trendClass($metric['tren'] ?? 'stabil')) ?>"><?= e(ucfirst((string) ($metric['tren'] ?? 'stabil'))) ?></td>
    <td><?= e($metric['catatan'] ?? '-') ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php if (!empty($chart['bars'])): ?>
<div class="section"><div class="section-title">Tren Pendapatan</div>
<?php $columnWidth = round(100 / max(1, count($chart['bars'])), 4); ?>
<table class="chart"><tr>
<?php foreach ($chart['bars'] as $bar): ?>
<td class="bar-cell" style="width: <?= e((string) $columnWidth) ?>%;">
    <?php if (count($chart['bars']) <= 10): ?><div class="bar-value"><?= number_format((float) ($bar['value'] ?? 0), 0, ',', '.') ?></div><?php endif; ?>
    <div class="bar" style="height: <?= max(4, (int) round(((float) ($bar['pct'] ?? 0)) * 1.05)) ?>px;"></div>
</td>
<?php endforeach; ?>
</tr><tr>
<?php foreach ($chart['bars'] as $bar): $label = (string) ($bar['label'] ?? ''); if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $label)) { $label = date('d/m', strtotime($label)); } elseif (preg_match('/^\d{4}-\d{2}$/', $label)) { $label = date('M y', strtotime($label . '-01')); } ?>
<td class="bar-label"><?= e($label) ?></td>
<?php endforeach; ?>
</tr></table>
<div class="chart-note">Total pendapatan periode: <strong>Rp <?= number_format((float) ($chart['total'] ?? 0), 0, ',', '.') ?></strong> &middot; <?= count($chart['bars']) ?> titik data terakhir</div>
</div>
<?php endif; ?>

<?php if (!empty($result['analisis'])): ?>
<div class="section"><div class="section-title">Analisis Mendalam</div>
<?php foreach ($result['analisis'] as $item): ?>
<div class="analysis-aspect"><?= e($item['aspek'] ?? '') ?></div><div class="paragraph"><?= nl2br(e($item['paragraf'] ?? '')) ?></div>
<?php endforeach; ?></div>
<?php endif; ?>

<?php if (!empty($result['temuan'])): ?>
<div class="section"><div class="section-title">Temuan</div>
<?php foreach ($result['temuan'] as $finding): $impact = $impactClass(strtolower((string) ($finding['dampak'] ?? 'rendah'))); ?>
<div class="finding <?= e($impact) ?>">
    <table class="finding-head"><tr><td class="finding-title"><?= e($finding['judul'] ?? '-') ?></td><td class="impact"><span class="pill <?= e($impact) ?>"><?= e($impact) ?></span></td></tr></table>
    <div class="finding-implication"><?= nl2br(e($finding['detail'] ?? '')) ?></div>
    <?php if (!empty($finding['implikasi'])): ?><div class="finding-implication"><strong>Implikasi:</strong> <?= nl2br(e($finding['implikasi'])) ?></div><?php endif; ?>
    <?php if (!empty($finding['bukti'])): ?><div class="finding-evidence">Bukti: <?= e($finding['bukti']) ?></div><?php endif; ?>
</div>
<?php endforeach; ?></div>
<?php endif; ?>

<?php if (!empty($result['rekomendasi'])): ?>
<div class="section"><div class="section-title">Rekomendasi</div><ol class="ordered">
<?php foreach ($result['rekomendasi'] as $reco): ?>
<li class="reco"><div class="title"><?= e($reco['aksi'] ?? '-') ?></div><div class="sub"><?= e($reco['alasan'] ?? '') ?></div>
<?php if (!empty($reco['langkah'])): ?><ul class="steps"><?php foreach ($reco['langkah'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (!empty($reco['dampak_diharapkan'])): ?><div class="expected">Target: <?= e($reco['dampak_diharapkan']) ?></div><?php endif; ?>
</li>
<?php endforeach; ?></ol></div>
<?php endif; ?>

<?php if (!empty($result['risiko'])): ?>
<div class="section"><div class="section-title">Risiko</div>
<?php foreach ($result['risiko'] as $risk): ?>
<div class="risk"><div class="title"><?= e($risk['risiko'] ?? '-') ?></div><div class="sub"><?= e($risk['indikasi'] ?? '') ?></div>
<?php if (!empty($risk['mitigasi'])): ?><div class="expected">Mitigasi: <?= e($risk['mitigasi']) ?></div><?php endif; ?></div>
<?php endforeach; ?></div>
<?php endif; ?>

<?php if (!empty($result['kesimpulan'])): ?>
<div class="section"><div class="section-title">Kesimpulan</div><div class="conclusion"><?= nl2br(e($result['kesimpulan'])) ?></div></div>
<?php endif; ?>

<?php if (!empty($tableRows)): ?>
<div class="section"><div class="section-title">Lampiran: Utilisasi Meja</div>
<table class="data-table"><thead><tr><th>Meja</th><th class="text-right">Transaksi</th><th class="text-right">Jam Pakai</th><th class="text-right">Utilisasi</th><th class="text-right">Pendapatan</th></tr></thead><tbody>
<?php foreach (array_slice($tableRows, 0, 10) as $table): ?>
<tr><td><?= e($table['meja'] ?? '-') ?></td><td class="text-right"><?= number_format((float) ($table['transaksi'] ?? 0), 0, ',', '.') ?></td><td class="text-right"><?= number_format((float) ($table['total_jam_pakai'] ?? 0), 1, ',', '.') ?></td><td class="text-right"><?= number_format((float) ($table['estimasi_utilisasi_persen'] ?? 0), 1, ',', '.') ?>%</td><td class="text-right">Rp <?= number_format((float) ($table['revenue'] ?? 0), 0, ',', '.') ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<?php if (!empty($foodRows)): ?>
<div class="section"><div class="section-title">Lampiran: Penjualan F&amp;B</div>
<table class="data-table"><thead><tr><th>Item</th><th>Kategori</th><th class="text-right">Terjual</th><th class="text-right">Pendapatan</th><th class="text-right">Kontribusi</th></tr></thead><tbody>
<?php foreach (array_slice($foodRows, 0, 10) as $food): ?>
<tr><td><?= e($food['item'] ?? '-') ?></td><td><?= e($food['kategori'] ?? '-') ?></td><td class="text-right"><?= number_format((float) ($food['qty'] ?? 0), 0, ',', '.') ?></td><td class="text-right">Rp <?= number_format((float) ($food['revenue'] ?? 0), 0, ',', '.') ?></td><td class="text-right"><?= number_format((float) ($food['kontribusi_persen'] ?? 0), 1, ',', '.') ?>%</td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>

<div class="section"><div class="section-title">Informasi Laporan</div>
<table class="meta"><tr>
<td>Data dianalisis: <?= e(implode(', ', array_map(static fn ($key) => $sections[$key] ?? $key, $evaluation['sections'] ?? []))) ?><br>Diproses oleh: <strong>Sistem AI internal PoolStream</strong></td>
<td>Dibuat oleh: <strong><?= e($evaluation['user_name'] ?? '-') ?></strong><br>Waktu proses: <?= !empty($evaluation['duration_ms']) ? e(round(((float) $evaluation['duration_ms']) / 1000, 1) . ' detik') : '-' ?> &middot; ID #<?= (int) $evaluation['id'] ?></td>
</tr></table>
<div class="disclaimer">Laporan ini dihasilkan otomatis oleh sistem AI internal PoolStream berdasarkan data agregat transaksi pada periode di atas. Hasil bersifat saran untuk pengambilan keputusan; verifikasi angka penting dengan data transaksi mentah. Dokumen ini bersifat internal dan tidak untuk disebarkan ke pihak luar.</div>
</div>
</body>
</html>
