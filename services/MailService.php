<?php
declare(strict_types=1);

class MailService
{
    public static function driver(): string
    {
        return (string) credentials('mail.driver', 'log');
    }

    public static function send(string $to, string $subject, string $body): void
    {
        $from = (string) credentials('mail.from', 'no-reply@poolstream.test');
        $fromName = (string) credentials('mail.from_name', APP_NAME);

        if (self::driver() === 'mail') {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: {$fromName} <{$from}>\r\n";
            @mail($to, $subject, $body, $headers);
            return;
        }

        $logDir = APP_ROOT . '/storage/logs';
        ensure_dir($logDir);
        $entry = '[' . now() . "] To: {$to}\nSubject: {$subject}\n\n{$body}\n\n" . str_repeat('-', 72) . "\n";
        file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }
}
