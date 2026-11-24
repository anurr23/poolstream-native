<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function ai_resolve_range(string $preset, ?string $startDate, ?string $endDate): array
{
    $today = new DateTime('today');
    $fmt = static fn (DateTime $d): string => $d->format('Y-m-d');

    $range = match ($preset) {
        'today' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
        '7d' => [(clone $today)->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d')],
        'month' => [$today->format('Y-m-01'), $today->format('Y-m-d')],
        'last_month' => [
            (clone $today)->modify('first day of last month')->format('Y-m-d'),
            (clone $today)->modify('last day of last month')->format('Y-m-d'),
        ],
        '3m' => [(clone $today)->modify('-3 months +1 day')->format('Y-m-d'), $today->format('Y-m-d')],
        '6m' => [(clone $today)->modify('-6 months +1 day')->format('Y-m-d'), $today->format('Y-m-d')],
        'year' => [$today->format('Y-01-01'), $today->format('Y-m-d')],
        '12m' => [(clone $today)->modify('-12 months +1 day')->format('Y-m-d'), $today->format('Y-m-d')],
        'all' => [
            (new AiEvaluation())->firstTransactionDate() ? date('Y-m-d', strtotime((string) (new AiEvaluation())->firstTransactionDate())) : $today->format('Y-m-01'),
            $today->format('Y-m-d'),
        ],
        'custom' => [
            $startDate ?: throw new RuntimeException('Isi tanggal mulai dan tanggal selesai untuk rentang khusus.'),
            $endDate ?: throw new RuntimeException('Isi tanggal mulai dan tanggal selesai untuk rentang khusus.'),
        ],
        default => [$today->format('Y-m-01'), $today->format('Y-m-d')],
    };

    [$start, $end] = $range;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        throw new RuntimeException('Format tanggal tidak valid.');
    }
    if ($start > $end) {
        throw new RuntimeException('Tanggal mulai tidak boleh melebihi tanggal selesai.');
    }
    if ((new DateTime($start))->diff(new DateTime($end))->days > 3650) {
        throw new RuntimeException('Rentang maksimal 10 tahun.');
    }

    return [$start, $end, AiEvaluationPresenter::PRESETS[$preset] ?? $preset];
}

api_handle(function (): void {
    require_login();
    require_role('admin');
    $action = (string) ($_GET['action'] ?? 'show');
    $input = request_input();
    $model = new AiEvaluation();

    if ($action === 'show') {
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $evaluation = $model->find($id);
        if (!$evaluation) {
            json_error(['evaluation' => 'Evaluasi tidak ditemukan.'], 404);
        }
        json_ok(['evaluation' => AiEvaluationPresenter::payload($evaluation)]);
    }

    if ($action === 'store') {
        require_method('POST');
        verify_csrf();
        $preset = (string) ($input['preset'] ?? 'month');
        if (!array_key_exists($preset, AiEvaluationPresenter::PRESETS)) {
            json_error(['preset' => 'Preset tidak valid.']);
        }
        $sections = array_values(array_unique(array_intersect(
            is_array($input['sections'] ?? null) ? $input['sections'] : [],
            array_keys(ReportDataService::SECTIONS)
        )));
        if (!$sections) {
            json_error(['sections' => 'Pilih minimal satu bagian data.']);
        }

        try {
            [$start, $end, $label] = ai_resolve_range($preset, $input['start_date'] ?? null, $input['end_date'] ?? null);
        } catch (Throwable $e) {
            json_error(['start_date' => $e->getMessage()]);
        }

        $user = current_user();
        $evaluation = $model->create([
            'user_id' => $user['id'],
            'preset' => $preset,
            'start_date' => $start,
            'end_date' => $end,
            'bucket' => (new ReportDataService())->bucketFor($start, $end),
            'sections' => $sections,
            'model' => env_required('AI_MODEL'),
            'payload' => [
                'opsi' => [
                    'sertakan_sampel' => request_bool($input['include_sample'] ?? false),
                    'label_periode' => $label,
                ],
            ],
        ]);

        if (!PhpProcess::spawn(APP_ROOT . '/scripts/ai-worker.php', [(int) $evaluation['id']])) {
            $model->update((int) $evaluation['id'], [
                'status' => 'failed',
                'error' => 'Gagal menjalankan proses AI di background. Jalankan `php scripts/ai-worker.php ' . $evaluation['id'] . '` secara manual.',
            ]);
        }

        flash('success', 'Evaluasi AI sedang diproses.');
        json_ok(['id' => (int) $evaluation['id']], null, 'ai-evaluation.php?id=' . $evaluation['id']);
    }

    if ($action === 'delete') {
        require_method('POST');
        verify_csrf();
        $id = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        $model->delete($id);
        flash('success', 'Evaluasi berhasil dihapus.');
        json_ok([], null, 'ai-evaluations.php');
    }

    json_error('Aksi tidak dikenal.', 404);
});

