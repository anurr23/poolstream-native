<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$id = isset($argv[1]) ? (int) $argv[1] : 0;
if ($id <= 0) {
    fwrite(STDERR, "Usage: php scripts/ai-worker.php <evaluation-id>\n");
    exit(1);
}

try {
    (new AiEvaluationService())->generate($id);
} catch (Throwable $e) {
    (new AiEvaluation())->update($id, [
        'status' => 'failed',
        'error' => mb_substr($e->getMessage(), 0, 900),
    ]);
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
