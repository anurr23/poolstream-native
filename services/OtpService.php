<?php
declare(strict_types=1);

class OtpService
{
    public const EXPIRES_MINUTES = 5;
    public const MAX_ATTEMPTS = 5;

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function sendMail(string $email, string $name, string $code): void
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $body = '<p>Halo ' . $safeName . '!</p>'
            . '<p>Kode OTP untuk verifikasi email Anda adalah:</p>'
            . '<p style="font-size:24px;font-weight:700;letter-spacing:6px">' . $code . '</p>'
            . '<p>Kode ini berlaku selama ' . self::EXPIRES_MINUTES . ' menit.</p>'
            . '<p>Jika Anda tidak merasa mendaftar di PoolStream, abaikan email ini.</p>'
            . '<p>Salam, PoolStream</p>';

        MailService::send($email, 'Kode OTP PoolStream', $body);
    }

    public function send(array $user): void
    {
        $code = self::generateCode();
        execute_sql(
            'UPDATE users SET otp_code = :otp_code, otp_expires_at = :otp_expires_at, otp_attempts = 0, updated_at = NOW() WHERE id = :id',
            [
                ':id' => $user['id'],
                ':otp_code' => password_hash($code, PASSWORD_BCRYPT),
                ':otp_expires_at' => date('Y-m-d H:i:s', time() + self::EXPIRES_MINUTES * 60),
            ]
        );

        self::sendMail((string) $user['email'], (string) $user['name'], $code);
    }

    /**
     * @return string verified|invalid|expired|locked
     */
    public function verify(array $user, string $code): string
    {
        if (empty($user['otp_code']) || empty($user['otp_expires_at'])) {
            return 'invalid';
        }
        if (strtotime((string) $user['otp_expires_at']) < time()) {
            return 'expired';
        }
        if ((int) $user['otp_attempts'] >= self::MAX_ATTEMPTS) {
            return 'locked';
        }
        if (!password_verify($code, (string) $user['otp_code'])) {
            execute_sql('UPDATE users SET otp_attempts = otp_attempts + 1, updated_at = NOW() WHERE id = :id', [':id' => $user['id']]);
            return 'invalid';
        }

        (new User())->markVerified((string) $user['id']);
        return 'verified';
    }
}
