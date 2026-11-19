<?php
declare(strict_types=1);

class RelayService
{
    public static function control(int $channel, string $state): void
    {
        if ($channel <= 0) {
            return;
        }

        $script = APP_ROOT . '/services/relay_controller.py';
        if (!is_file($script)) {
            return;
        }

        $python = null;
        foreach (['python3', 'python', 'py'] as $candidate) {
            $check = @shell_exec((PHP_OS_FAMILY === 'Windows' ? 'where ' : 'command -v ') . $candidate . ' 2>' . (PHP_OS_FAMILY === 'Windows' ? 'nul' : '/dev/null'));
            if (!empty(trim((string) $check))) {
                $python = $candidate;
                break;
            }
        }
        if ($python === null) {
            return;
        }

        $cmd = escapeshellcmd($python)
            . ' ' . escapeshellarg($script)
            . ' ' . escapeshellarg((string) $channel)
            . ' ' . escapeshellarg($state)
            . ' 2>&1';
        @shell_exec($cmd);
    }

    public static function setLight(array $table, bool $on): void
    {
        execute_sql('UPDATE tables SET is_light_on = :is_light_on, updated_at = NOW() WHERE id = :id', [
            ':id' => $table['id'],
            ':is_light_on' => $on ? 1 : 0,
        ]);
        self::control((int) ($table['relay_channel'] ?? 0), $on ? 'on' : 'off');
    }

    public static function toggle(array $table): bool
    {
        $on = !(bool) ($table['is_light_on'] ?? false);
        self::setLight($table, $on);
        return $on;
    }
}
