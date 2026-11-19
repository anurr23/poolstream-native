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

$sections = [];
foreach (ReportDataService::SECTIONS as $key => $label) {
    $sections[] = ['key' => $key, 'label' => $label];
}

render_page('Master/AiEvaluation/Show', [
    'evaluation' => AiEvaluationPresenter::payload($evaluation),
    'sections' => $sections,
], 'Detail Evaluasi AI');
