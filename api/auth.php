<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    $action = (string) ($_GET['action'] ?? '');
    $input = request_input();

    switch ($action) {
        case 'login':
            require_method('POST');
            verify_csrf();

            $login = trim((string) ($input['username'] ?? ''));
            $password = (string) ($input['password'] ?? '');

            if ($login === '' || $password === '') {
                json_error(['username' => 'Username dan password wajib diisi.']);
            }

            $key = 'login_attempts';
            $attempts = $_SESSION[$key] ?? ['count' => 0, 'at' => 0];
            if (($attempts['count'] ?? 0) >= 5 && (time() - (int) ($attempts['at'] ?? 0)) < 60) {
                json_error(['username' => 'Terlalu banyak percobaan. Coba lagi dalam ' . (60 - (time() - (int) $attempts['at'])) . ' detik.']);
            }

            $userModel = new User();
            $user = $userModel->findByLogin($login);
            if (!$user || !password_verify($password, (string) $user['password'])) {
                $_SESSION[$key] = ['count' => ((int) ($attempts['count'] ?? 0)) + 1, 'at' => time()];
                json_error(['username' => 'Username atau password tidak sesuai.']);
            }

            if (!(bool) $user['is_active']) {
                json_error(['username' => 'Akun Anda telah dinonaktifkan. Hubungi admin.']);
            }

            unset($_SESSION[$key]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];

            if ($user['role'] === 'customer' && empty($user['email_verified_at'])) {
                json_ok([], 'Login berhasil. Verifikasi email Anda.', 'verify-otp.php');
            }

            if ($user['role'] === 'customer') {
                $intended = $_SESSION['url_intended'] ?? null;
                unset($_SESSION['url_intended']);
                $target = 'customer-home.php';
                if (is_string($intended) && str_contains($intended, '/customer')) {
                    $target = $intended;
                }
                json_ok([], 'Login berhasil.', $target);
            }

            $target = $user['role'] === 'admin' ? 'index.php' : 'dashboard.php';
            json_ok([], 'Login berhasil.', $target);
            break;

        case 'register':
            require_method('POST');
            verify_csrf();

            $name = trim((string) ($input['name'] ?? ''));
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $password = (string) ($input['password'] ?? '');
            $confirmation = (string) ($input['password_confirmation'] ?? '');

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Nama wajib diisi.';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email tidak valid.';
            }
            if (strlen($password) < 8) {
                $errors['password'] = 'Password minimal 8 karakter.';
            }
            if ($password !== $confirmation) {
                $errors['password'] = 'Konfirmasi password tidak cocok.';
            }
            if ($errors) {
                json_error($errors);
            }

            $userModel = new User();
            if ($userModel->findByEmail($email)) {
                json_error(['email' => 'Email sudah terdaftar.']);
            }

            $user = $userModel->create([
                'name' => $name,
                'email' => $email,
                'username' => $userModel->makeUsernameFromEmail($email),
                'password' => $password,
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => null,
            ]);

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['username'] = $user['username'];

            try {
                (new OtpService())->send($user);
                flash('success', 'Akun berhasil dibuat. Cek email untuk kode OTP.');
            } catch (Throwable $e) {
                error_log('[OTP] ' . $e->getMessage());
                flash('error', 'Akun dibuat, tetapi email OTP gagal dikirim. Silakan kirim ulang kode.');
            }

            json_ok([], null, 'verify-otp.php');
            break;

        case 'verify':
            require_method('POST');
            verify_csrf();
            require_login();
            $user = current_user();
            if (!$user || $user['role'] !== 'customer') {
                json_error('Akses ditolak.', 403);
            }
            if (!empty($user['email_verified_at'])) {
                json_ok([], null, 'customer-home.php');
            }

            $code = preg_replace('/\D/', '', (string) ($input['code'] ?? '')) ?? '';
            if (strlen($code) !== 6) {
                json_error(['code' => 'Kode OTP harus 6 digit.']);
            }

            $status = (new OtpService())->verify($user, $code);
            if ($status === 'verified') {
                flash('success', 'Email berhasil diverifikasi. Selamat datang!');
                json_ok([], null, 'customer-home.php');
            }

            $message = match ($status) {
                'expired' => 'Kode OTP sudah kedaluwarsa. Kirim ulang kode baru.',
                'locked' => 'Terlalu banyak percobaan. Kirim ulang kode baru.',
                default => 'Kode OTP tidak valid.',
            };
            json_error(['code' => $message]);
            break;

        case 'resend':
            require_method('POST');
            verify_csrf();
            require_login();
            $user = current_user();
            if (!$user || $user['role'] !== 'customer') {
                json_error('Akses ditolak.', 403);
            }
            if (!empty($user['email_verified_at'])) {
                json_ok([], null, 'customer-home.php');
            }
            if (time() - (int) ($_SESSION['otp_resend_at'] ?? 0) < 60) {
                json_error(['code' => 'Tunggu 60 detik sebelum mengirim ulang kode.']);
            }
            $_SESSION['otp_resend_at'] = time();
            try {
                (new OtpService())->send($user);
            } catch (Throwable $e) {
                unset($_SESSION['otp_resend_at']);
                json_error(['code' => 'Gagal mengirim email OTP. Coba lagi nanti.']);
            }
            flash('success', 'Kode OTP baru sudah dikirim ke email Anda.');
            json_ok([], null, 'verify-otp.php');
            break;

        case 'forgot':
            require_method('POST');
            verify_csrf();
            $username = trim((string) ($input['username'] ?? ''));
            if ($username === '') {
                json_error(['username' => 'Username wajib diisi.']);
            }

            $user = fetch_one('SELECT * FROM users WHERE username = :username LIMIT 1', [':username' => $username]);
            $statusMessage = 'Jika username terdaftar, instruksi reset password telah dibuat.';
            if ($user && !empty($user['email'])) {
                $token = bin2hex(random_bytes(32));
                execute_sql(
                    'INSERT INTO password_reset_tokens (email, token, created_at) VALUES (:email, :token, NOW())
                     ON DUPLICATE KEY UPDATE token = VALUES(token), created_at = VALUES(created_at)',
                    [':email' => $username, ':token' => password_hash($token, PASSWORD_BCRYPT)]
                );
                $link = (isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] : '') . url('reset-password.php?token=' . urlencode($token) . '&username=' . urlencode($username));
                $body = '<p>Halo ' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>Kami menerima permintaan reset password untuk akun PoolStream Anda.</p>'
                    . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">Klik di sini untuk membuat password baru</a></p>'
                    . '<p>Link berlaku 60 menit. Jika Anda tidak meminta reset, abaikan email ini.</p>';
                MailService::send((string) $user['email'], 'Reset Password PoolStream', $body);
            }

            flash('success', $statusMessage);
            json_ok([], null, 'forgot-password.php');
            break;

        case 'reset':
            require_method('POST');
            verify_csrf();
            $token = (string) ($input['token'] ?? '');
            $username = trim((string) ($input['username'] ?? ''));
            $password = (string) ($input['password'] ?? '');
            $confirmation = (string) ($input['password_confirmation'] ?? '');

            if (strlen($password) < 8) {
                json_error(['password' => 'Password minimal 8 karakter.']);
            }
            if ($password !== $confirmation) {
                json_error(['password' => 'Konfirmasi password tidak cocok.']);
            }

            $row = fetch_one('SELECT * FROM password_reset_tokens WHERE email = :username LIMIT 1', [':username' => $username]);
            if (!$row || !password_verify($token, (string) $row['token']) || strtotime((string) $row['created_at']) < time() - 3600) {
                json_error(['username' => 'Token reset password tidak valid atau sudah kedaluwarsa.']);
            }

            $user = fetch_one('SELECT * FROM users WHERE username = :username LIMIT 1', [':username' => $username]);
            if (!$user) {
                json_error(['username' => 'Akun tidak ditemukan.']);
            }

            execute_sql('UPDATE users SET password = :password, remember_token = :remember_token, updated_at = NOW() WHERE id = :id', [
                ':id' => $user['id'],
                ':password' => password_hash($password, PASSWORD_BCRYPT),
                ':remember_token' => bin2hex(random_bytes(30)),
            ]);
            execute_sql('DELETE FROM password_reset_tokens WHERE email = :username', [':username' => $username]);
            flash('success', 'Password berhasil direset. Silakan login.');
            json_ok([], null, 'login.php');
            break;

        case 'logout':
            require_method('POST');
            verify_csrf();
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            json_ok([], null, 'index.php');
            break;

        case 'confirm-password':
            require_method('POST');
            verify_csrf();
            require_login();
            $password = (string) ($input['password'] ?? '');
            $user = current_user();
            if (!$user || !password_verify($password, (string) $user['password'])) {
                json_error(['password' => 'Password tidak sesuai.']);
            }
            $_SESSION['auth_password_confirmed_at'] = time();
            json_ok([], null, 'index.php');
            break;

        default:
            json_error('Aksi tidak dikenal.', 404);
    }
});

