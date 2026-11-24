<?php
declare(strict_types=1);

class MailService
{
    public static function driver(): string
    {
        return env_required('MAIL_DRIVER');
    }

    public static function send(string $to, string $subject, string $body): void
    {
        $from = env_required('MAIL_FROM');
        $fromName = env_required('MAIL_FROM_NAME');
        $driver = self::driver();

        if ($driver === 'smtp') {
            self::sendSmtp($to, $subject, $body, $from, $fromName);
            return;
        }

        if ($driver === 'mail') {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . self::formatAddress($from, $fromName) . "\r\n";
            if (!@mail($to, self::encodeHeader($subject), $body, $headers)) {
                throw new RuntimeException('Gagal mengirim email melalui PHP mail().');
            }
            return;
        }

        $logDir = APP_ROOT . '/storage/logs';
        ensure_dir($logDir);
        $entry = '[' . now() . "] To: {$to}\nSubject: {$subject}\n\n{$body}\n\n" . str_repeat('-', 72) . "\n";
        file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    private static function sendSmtp(string $to, string $subject, string $body, string $from, string $fromName): void
    {
        $host = trim(env_required('MAIL_HOST'));
        $port = env_int('MAIL_PORT');
        $encryption = strtolower(trim(env_required('MAIL_ENCRYPTION')));
        $username = trim(env_required('MAIL_USERNAME'));
        $password = env_required('MAIL_PASSWORD');
        if (str_contains(strtolower($host), 'gmail.com')) {
            $password = str_replace(' ', '', $password);
        }
        $timeout = env_int('MAIL_TIMEOUT');

        if ($host === '' || $port <= 0) {
            throw new RuntimeException('Konfigurasi MAIL_HOST/MAIL_PORT di .env tidak valid.');
        }

        if (!self::isValidEmail($from)) {
            throw new RuntimeException('Alamat MAIL_FROM di .env tidak valid.');
        }
        $effectiveFrom = $from;

        $transport = $encryption === 'ssl' ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($transport, $errno, $errstr, $timeout);
        if (!is_resource($socket)) {
            throw new RuntimeException("Gagal terhubung ke SMTP {$host}:{$port} ({$errstr}).");
        }

        stream_set_timeout($socket, $timeout);
        $ehloHost = (string) ($_SERVER['HTTP_HOST'] ?? gethostname() ?: 'localhost');
        $ehloHost = preg_replace('/:\d+$/', '', $ehloHost) ?: 'localhost';

        try {
            self::expect($socket, [220]);
            self::command($socket, "EHLO {$ehloHost}", [250]);

            if ($encryption === 'tls') {
                self::command($socket, 'STARTTLS', [220]);
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $crypto |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }
                if (!@stream_socket_enable_crypto($socket, true, $crypto)) {
                    throw new RuntimeException('Gagal mengaktifkan enkripsi STARTTLS.');
                }
                self::command($socket, "EHLO {$ehloHost}", [250]);
            }

            if ($username !== '') {
                self::command($socket, 'AUTH LOGIN', [334]);
                self::command($socket, base64_encode($username), [334]);
                self::command($socket, base64_encode($password), [235]);
            }

            self::command($socket, "MAIL FROM:<{$effectiveFrom}>", [250]);
            self::command($socket, "RCPT TO:<{$to}>", [250, 251]);
            self::command($socket, 'DATA', [354]);

            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::formatAddress($effectiveFrom, $fromName),
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n.";
            fwrite($socket, $message . "\r\n");
            self::expect($socket, [250]);

            @fwrite($socket, "QUIT\r\n");
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     * @param array<int, int> $expectedCodes
     */
    private static function command($socket, string $cmd, array $expectedCodes): void
    {
        fwrite($socket, $cmd . "\r\n");
        self::expect($socket, $expectedCodes);
    }

    /**
     * @param resource $socket
     * @param array<int, int> $expectedCodes
     */
    private static function expect($socket, array $expectedCodes): void
    {
        $response = '';
        $code = 0;
        while (($line = fgets($socket, 512)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
                $code = (int) $m[1];
                if ($m[2] === ' ') {
                    break;
                }
            } else {
                break;
            }
        }

        if ($response === '' || !in_array($code, $expectedCodes, true)) {
            $summary = trim((string) preg_replace('/\s+/', ' ', $response));
            throw new RuntimeException('SMTP error (' . $code . '): ' . ($summary ?: 'Tidak ada respons dari server.'));
        }
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function formatAddress(string $email, string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return "<{$email}>";
        }
        return self::encodeHeader($name) . " <{$email}>";
    }

    private static function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
