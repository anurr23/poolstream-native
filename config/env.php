<?php
declare(strict_types=1);

/**
 * Loader .env sederhana tanpa dependency.
 *
 * Nilai dibaca apa adanya dari file .env. Tidak ada fallback/default di sini
 * maupun di file config lain. Jika variabel tidak ada, env_required() akan
 * melempar error yang jelas.
 */

function env_load(string $path): void
{
    static $loadedPath = null;

    if ($loadedPath === $path) {
        return;
    }

    if (!is_file($path)) {
        throw new RuntimeException(
            'File .env tidak ditemukan: ' . $path . '. Salin .env.example menjadi .env.'
        );
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('File .env tidak bisa dibaca: ' . $path);
    }

    // Buang BOM UTF-8 jika ada.
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
    $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
    $values = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = ltrim(substr($line, 7));
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            continue;
        }

        $key = trim(substr($line, 0, $separator));
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            continue;
        }

        $values[$key] = env_parse_value(trim(substr($line, $separator + 1)));
    }

    $GLOBALS['poolstream_env'] = $values;
    $loadedPath = $path;
}

function env_parse_value(string $raw): string
{
    if ($raw === '') {
        return '';
    }

    $quote = $raw[0];
    if ($quote === '"' || $quote === "'") {
        $length = strlen($raw);
        $value = '';

        for ($i = 1; $i < $length; $i++) {
            $char = $raw[$i];

            if ($char === '\\' && $i + 1 < $length) {
                $next = $raw[$i + 1];

                if ($quote === '"') {
                    $map = [
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '\\' => '\\',
                        '"' => '"',
                        '$' => '$',
                    ];
                    if (array_key_exists($next, $map)) {
                        $value .= $map[$next];
                        $i++;
                        continue;
                    }
                } elseif ($next === "'" || $next === '\\') {
                    $value .= $next;
                    $i++;
                    continue;
                }

                $value .= $char;
                continue;
            }

            if ($char === $quote) {
                return $value;
            }

            $value .= $char;
        }

        return $value;
    }

    // Unquoted value: komentar inline hanya jika didahului whitespace.
    $raw = preg_replace('/\s+#.*$/', '', $raw) ?? $raw;
    $raw = preg_replace('/\s+;.*$/', '', $raw) ?? $raw;

    return trim($raw);
}

function env(string $key): ?string
{
    $values = $GLOBALS['poolstream_env'] ?? null;
    if (!is_array($values)) {
        throw new RuntimeException('File .env belum dimuat.');
    }

    return array_key_exists($key, $values) ? $values[$key] : null;
}

function env_required(string $key): string
{
    $value = env($key);
    if ($value === null) {
        throw new RuntimeException('Variabel ' . $key . ' tidak ditemukan di .env.');
    }

    return $value;
}

function env_bool(string $key): bool
{
    $value = env_required($key);
    $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    if ($bool === null) {
        throw new RuntimeException('Variabel ' . $key . ' harus bernilai true/false di .env.');
    }

    return $bool;
}

function env_int(string $key): int
{
    $value = trim(env_required($key));
    if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
        throw new RuntimeException('Variabel ' . $key . ' harus berupa angka di .env.');
    }

    return (int) $value;
}
