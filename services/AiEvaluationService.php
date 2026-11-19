<?php
declare(strict_types=1);

class AiEvaluationService
{
    public function __construct(private ?ReportDataService $reportData = null)
    {
        $this->reportData = $reportData ?? new ReportDataService();
    }

    public function generate(int $evaluationId): void
    {
        $model = new AiEvaluation();
        $evaluation = $model->find($evaluationId);
        if (!$evaluation || $evaluation['status'] !== 'running') {
            return;
        }

        @set_time_limit(0);
        $startedAt = microtime(true);
        $start = (string) $evaluation['start_date'];
        $end = (string) $evaluation['end_date'];
        $bucket = $this->reportData->bucketFor($start, $end);
        $includeSample = (bool) (($evaluation['payload']['opsi']['sertakan_sampel'] ?? false));

        $payload = $this->reportData->build($start, $end, $evaluation['sections'] ?? [], $bucket, $includeSample);
        $model->update($evaluationId, [
            'bucket' => $bucket,
            'model' => (string) credentials('ai.model', ''),
            'payload' => $payload,
        ]);

        [$system, $user] = $this->prompts($payload);
        $baseUrl = rtrim((string) credentials('ai.base_url', 'http://127.0.0.1:20128/v1'), '/');
        $apiKey = (string) credentials('ai.api_key', '');
        $aiModel = (string) credentials('ai.model', 'ag/gemini-3.8-flash-high');
        $timeout = (int) credentials('ai.timeout', 0);
        $maxTokens = (int) credentials('ai.max_tokens', 16384);

        $body = json_encode([
            'model' => $aiModel,
            'temperature' => 0.4,
            'max_tokens' => $maxTokens,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => $timeout > 0 ? $timeout : 0,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno) {
            throw new RuntimeException('Gagal menghubungi AI: ' . $error);
        }
        if ($status >= 400) {
            throw new RuntimeException('AI menolak permintaan (HTTP ' . $status . '): ' . mb_substr((string) $raw, 0, 500));
        }

        $json = json_decode((string) $raw, true);
        $content = $this->extractContent(is_array($json) ? $json : null, (string) $raw);
        if (trim($content) === '') {
            throw new RuntimeException('AI mengembalikan respons kosong.');
        }
        if ($this->wasTruncated(is_array($json) ? $json : null, (string) $raw)) {
            throw new RuntimeException(
                'Jawaban AI terpotong karena mencapai batas ' . number_format($maxTokens)
                . ' token output. Naikkan AI_MAX_TOKENS atau kurangi jumlah seksi/periode yang dianalisa.'
            );
        }

        $result = $this->decode($content);
        $usage = $this->extractUsage(is_array($json) ? $json : null, (string) $raw);

        $model->update($evaluationId, [
            'status' => 'completed',
            'result' => $result ?? ['ringkasan' => mb_substr($content, 0, 4000)],
            'error' => $result === null ? 'AI tidak mengembalikan JSON valid; hasil ditampilkan sebagai teks mentah.' : null,
            'prompt_tokens' => $usage['prompt_tokens'],
            'completion_tokens' => $usage['completion_tokens'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    private function wasTruncated(?array $json, string $body): bool
    {
        if (($json['choices'][0]['finish_reason'] ?? null) === 'length') {
            return true;
        }
        return (bool) preg_match('/"finish_reason"\s*:\s*"length"/', $body);
    }

    private function extractContent(?array $json, string $body): string
    {
        $content = (string) ($json['choices'][0]['message']['content'] ?? '');
        if ($content !== '') {
            return $content;
        }
        if (!str_contains($body, 'data:')) {
            return $content;
        }

        $parts = [];
        foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data:')) {
                continue;
            }
            $chunk = trim(substr($line, 5));
            if ($chunk === '' || $chunk === '[DONE]') {
                continue;
            }
            $decoded = json_decode($chunk, true);
            if (is_array($decoded)) {
                $parts[] = (string) ($decoded['choices'][0]['delta']['content'] ?? $decoded['choices'][0]['message']['content'] ?? '');
            }
        }
        return trim(implode('', $parts));
    }

    private function extractUsage(?array $json, string $body): array
    {
        $prompt = $json['usage']['prompt_tokens'] ?? null;
        $completion = $json['usage']['completion_tokens'] ?? null;
        if ($prompt === null && str_contains($body, 'data:')) {
            foreach (preg_split('/\r?\n/', $body) ?: [] as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $decoded = json_decode(trim(substr($line, 5)), true);
                if (is_array($decoded) && is_array($decoded['usage'] ?? null)) {
                    $prompt = $decoded['usage']['prompt_tokens'] ?? $prompt;
                    $completion = $decoded['usage']['completion_tokens'] ?? $completion;
                }
            }
        }
        return [
            'prompt_tokens' => $prompt !== null ? (int) $prompt : null,
            'completion_tokens' => $completion !== null ? (int) $completion : null,
        ];
    }

    private function prompts(array $payload): array
    {
        $system = <<<'PROMPT'
Anda adalah analis bisnis senior untuk pool hall / biliar "PoolStream".

Tugas: memberi evaluasi MENYELURUH dan MENDALAM atas performa usaha berdasarkan DATA AGREGAT yang diberikan, dalam Bahasa Indonesia yang jelas, tajam, dan actionable.

Aturan wajib:
1. Gunakan HANYA angka yang ada pada data. Jangan mengarang, jangan menebak, jangan menghitung ulang total yang sudah tersedia.
2. Sebut angka konkret (revenue, jumlah transaksi, jam, persentase) di hampir setiap bagian agar analisa terasa berbasis data.
3. Jika data tidak cukup untuk sebuah kesimpulan, tulis "data belum cukup" dan jelaskan data apa yang perlu dikumpulkan.
4. Nominal dalam Rupiah (IDR), tanpa desimal. Gunakan format seperti "IDR 155.000".
5. Fokus pada hal yang bisa ditindaklanjuti pemilik usaha: operasional, harga, jam ramai, menu, utilisasi meja, strategi promosi.
6. Bersikaplah analitis: jelaskan sebab-akibat, bukan sekadar mengulang angka.
7. Panjang jawaban mengikuti kebutuhan analisa. Tidak ada batas kalimat: tulis sedetail dan selengkap yang datanya memungkinkan.
8. Jawab HANYA dengan satu objek JSON valid, tanpa markdown, tanpa penjelasan di luar JSON.

Skema JSON yang wajib diikuti:
{
  "ringkasan": "ringkasan kondisi bisnis periode ini: angka kunci dan nada umum performa",
  "metrik_kunci": [{"label": "string", "nilai": "string", "tren": "naik|turun|stabil", "catatan": "1 kalimat konteks"}],
  "analisis": [
    {"aspek": "Pendapatan|Utilisasi Meja|F&B|Operasional|Pelanggan|Efisiensi", "paragraf": "analisa mendalam untuk aspek ini, dengan angka pendukung"}
  ],
  "temuan": [
    {"judul": "string", "detail": "penjelasan lengkap", "dampak": "tinggi|sedang|rendah", "bukti": "angka pendukung dari data", "implikasi": "dampak bisnisnya"}
  ],
  "rekomendasi": [
    {"aksi": "string", "alasan": "string", "langkah": ["langkah konkret 1", "langkah konkret 2"], "dampak_diharapkan": "string", "prioritas": 1}
  ],
  "risiko": [{"risiko": "string", "indikasi": "string", "mitigasi": "string"}],
  "kesimpulan": "penutup: prioritas utama yang perlu dieksekusi"
}
PROMPT;

        $user = "Berikut data agregat periode usaha yang harus dievaluasi (JSON):\n\n"
            . json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . "\n\nBuat evaluasi mendalam sesuai skema JSON yang diminta. Tulis analisa selengkap mungkin sesuai data yang tersedia.";

        return [$system, $user];
    }

    private function decode(string $content): ?array
    {
        $text = trim($content);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;
        $first = strpos($text, '{');
        $last = strrpos($text, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $text = substr($text, $first, $last - $first + 1);
        }
        $decoded = json_decode($text, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }
}
