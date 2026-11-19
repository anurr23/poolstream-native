<?php
declare(strict_types=1);

class AiEvaluationPresenter
{
    public const PRESETS = [
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
    ];

    public static function label(?string $preset): string
    {
        return self::PRESETS[$preset] ?? (string) $preset;
    }

    public static function payload(array $evaluation): array
    {
        return [
            'id' => (int) $evaluation['id'],
            'preset' => $evaluation['preset'],
            'preset_label' => self::label($evaluation['preset']),
            'start_date' => $evaluation['start_date'],
            'end_date' => $evaluation['end_date'],
            'bucket' => $evaluation['bucket'],
            'sections' => $evaluation['sections'] ?? [],
            'status' => $evaluation['status'],
            'result' => $evaluation['result'],
            'meta' => [
                'ringkasan_data' => $evaluation['payload']['ringkasan'] ?? null,
                'periode_data' => $evaluation['payload']['periode'] ?? null,
                'sertakan_sampel' => (bool) ($evaluation['payload']['opsi']['sertakan_sampel'] ?? false),
                'prompt_tokens' => $evaluation['prompt_tokens'] ?? null,
                'completion_tokens' => $evaluation['completion_tokens'] ?? null,
                'duration_ms' => $evaluation['duration_ms'] ?? null,
            ],
            'error' => $evaluation['error'],
            'created_by' => $evaluation['user_name'] ?? null,
            'created_at' => $evaluation['created_at'] ? date('Y-m-d H:i:s', strtotime((string) $evaluation['created_at'])) : null,
        ];
    }
}
