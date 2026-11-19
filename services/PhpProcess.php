<?php
declare(strict_types=1);

class PhpProcess
{
    public static function spawn(string $script, array $args = []): bool
    {
        $php = PHP_BINARY;
        if (!is_file($php) || str_contains(basename($php), 'apache') || str_contains(basename($php), 'httpd')) {
            $candidate = PHP_BINDIR . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php.exe' : 'php');
            $php = is_file($candidate) ? $candidate : 'php';
        }

        $command = escapeshellarg($php) . ' ' . escapeshellarg($script);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg((string) $arg);
        }

        $logDir = APP_ROOT . '/storage/logs';
        ensure_dir($logDir);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" ' . $command . ' > NUL 2>&1';
            $handle = @popen($cmd, 'r');
            if ($handle === false) {
                return false;
            }
            pclose($handle);
            return true;
        }

        $cmd = $command . ' > ' . escapeshellarg($logDir . '/ai-worker.log') . ' 2>&1 &';
        $handle = @popen($cmd, 'r');
        if ($handle === false) {
            return false;
        }
        pclose($handle);
        return true;
    }
}
