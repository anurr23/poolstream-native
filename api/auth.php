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

            $target = in_array($user['role'], ['admin', 'kasir'], true) ? 'menu.php' : 'dashboard.php';
            json_ok([], 'Login berhasil.', $target);
            break;

        case 'register':
            require_method('POST');
            verify_csrf();

            $name = trim((string) ($input['name'] ?? ''));
            $email = strtolower(trim((string) ($input['email'] ?? '')));
            $whatsapp = normalize_whatsapp($input['whatsapp'] ?? '');
            $password = (string) ($input['password'] ?? '');
            $confirmation = (string) ($input['password_confirmation'] ?? '');

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'Nama wajib diisi.';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email tidak valid.';
            }
            if ($waError = validate_whatsapp($whatsapp)) {
                $errors['whatsapp'] = $waError;
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
            $duplicateErrors = [];
            if ($userModel->findByEmail($email)) {
                $duplicateErrors['email'] = 'Email sudah terdaftar.';
            }
            if ($userModel->findByWhatsapp($whatsapp)) {
                $duplicateErrors['whatsapp'] = 'Nomor WhatsApp sudah terdaftar.';
            }
            if ($duplicateErrors) {
                json_error($duplicateErrors);
            }

            $code = OtpService::generateCode();
            $now = time();
            $_SESSION['pending_registration'] = [
                'name' => $name,
                'email' => $email,
                'whatsapp' => $whatsapp,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'otp_hash' => password_hash($code, PASSWORD_BCRYPT),
                'otp_expires_at' => $now + (OtpService::EXPIRES_MINUTES * 60),
                'otp_attempts' => 0,
                'resend_available_at' => $now + 60,
            ];
            unset($_SESSION['_register_draft']);

            try {
                OtpService::sendMail($email, $name, $code);
                flash('success', 'Kode OTP telah dikirim ke email Anda.');
            } catch (Throwable $e) {
                error_log('[OTP] ' . $e->getMessage());
                $_SESSION['pending_registration']['resend_available_at'] = $now;
                flash('error', 'Kode OTP belum dapat dikirim saat ini. Silakan kirim ulang beberapa saat lagi.');
            }

            json_ok([], null, 'verify-otp.php');
            break;

        case 'verify':
            require_method('POST');
            verify_csrf();

            $code = preg_replace('/\D/', '', (string) ($input['code'] ?? '')) ?? '';
            if (strlen($code) !== 6) {
                json_error(['code' => 'Kode OTP harus 6 digit.']);
            }

            $user = current_user();
            if ($user) {
                if ($user['role'] !== 'customer') {
                    json_error('Akses ditolak.', 403);
                }
                if (!empty($user['email_verified_at'])) {
                    json_ok([], null, 'customer-home.php');
                }

                $status = (new OtpService())->verify($user, $code);
                if ($status === 'verified') {
                    flash('success', 'Email berhasil diverifikasi. Selamat datang!');
                    json_ok([], null, 'customer-home.php');
                }

                $message = match ($status) {
                    'expired' => 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode baru.',
                    'locked' => 'Terlalu banyak percobaan salah. Silakan kirim ulang kode baru.',
                    default => 'Kode OTP tidak valid.',
                };
                json_error(['code' => $message]);
            }

            $pending = $_SESSION['pending_registration'] ?? null;
            if (!is_array($pending) || empty($pending['email'])) {
                json_error(['code' => 'Sesi pendaftaran tidak ditemukan atau sudah berakhir. Silakan daftar ulang.']);
            }

            if (time() > (int) ($pending['otp_expires_at'] ?? 0)) {
                json_error(['code' => 'Kode OTP sudah kedaluwarsa. Silakan kirim ulang kode baru.']);
            }

            if ((int) ($pending['otp_attempts'] ?? 0) >= OtpService::MAX_ATTEMPTS) {
                json_error(['code' => 'Terlalu banyak percobaan salah. Silakan kirim ulang kode baru.']);
            }

            if (!password_verify($code, (string) ($pending['otp_hash'] ?? ''))) {
                $_SESSION['pending_registration']['otp_attempts'] = (int) ($pending['otp_attempts'] ?? 0) + 1;
                json_error(['code' => 'Kode OTP tidak valid.']);
            }

            $userModel = new User();
            if ($userModel->findByEmail((string) $pending['email'])) {
                unset($_SESSION['pending_registration']);
                json_error(['code' => 'Email sudah terdaftar. Silakan masuk atau daftar dengan email lain.']);
            }
            if (!empty($pending['whatsapp']) && $userModel->findByWhatsapp((string) $pending['whatsapp'])) {
                unset($_SESSION['pending_registration']);
                json_error(['code' => 'Nomor WhatsApp sudah terdaftar. Silakan daftar dengan nomor lain.']);
            }

            $created = $userModel->create([
                'name' => (string) $pending['name'],
                'email' => (string) $pending['email'],
                'whatsapp' => (string) ($pending['whatsapp'] ?? ''),
                'username' => $userModel->makeUsernameFromEmail((string) $pending['email']),
                'password_hash' => (string) $pending['password_hash'],
                'role' => 'customer',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            unset($_SESSION['pending_registration'], $_SESSION['_register_draft']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = $created['id'];
            $_SESSION['user_role'] = $created['role'];
            $_SESSION['name'] = $created['name'];
            $_SESSION['username'] = $created['username'];

            flash('success', 'Email berhasil diverifikasi. Selamat datang!');
            json_ok([], null, 'customer-home.php');
            break;

        case 'resend':
            require_method('POST');
            verify_csrf();

            $user = current_user();
            if ($user) {
                if ($user['role'] !== 'customer') {
                    json_error('Akses ditolak.', 403);
                }
                if (!empty($user['email_verified_at'])) {
                    json_ok([], null, 'customer-home.php');
                }
                $remaining = 60 - (time() - (int) ($_SESSION['otp_resend_at'] ?? 0));
                if ($remaining > 0) {
                    json_error(['code' => "Tunggu {$remaining} detik sebelum mengirim ulang kode."]);
                }
                $_SESSION['otp_resend_at'] = time();
                try {
                    (new OtpService())->send($user);
                } catch (Throwable $e) {
                    error_log('[OTP] ' . $e->getMessage());
                    unset($_SESSION['otp_resend_at']);
                    json_error(['code' => 'Gagal mengirim kode OTP. Silakan coba lagi nanti.']);
                }
                json_ok(['cooldown' => 60], 'Kode OTP baru sudah dikirim ke email Anda.');
            }

            $pending = $_SESSION['pending_registration'] ?? null;
            if (!is_array($pending) || empty($pending['email'])) {
                json_error(['code' => 'Sesi pendaftaran tidak ditemukan atau sudah berakhir. Silakan daftar ulang.']);
            }

            $now = time();
            $remaining = (int) ($pending['resend_available_at'] ?? 0) - $now;
            if ($remaining > 0) {
                json_error(['code' => "Tunggu {$remaining} detik sebelum mengirim ulang kode."]);
            }

            $code = OtpService::generateCode();
            $_SESSION['pending_registration']['otp_hash'] = password_hash($code, PASSWORD_BCRYPT);
            $_SESSION['pending_registration']['otp_expires_at'] = $now + (OtpService::EXPIRES_MINUTES * 60);
            $_SESSION['pending_registration']['otp_attempts'] = 0;
            $_SESSION['pending_registration']['resend_available_at'] = $now + 60;

            try {
                OtpService::sendMail((string) $pending['email'], (string) $pending['name'], $code);
            } catch (Throwable $e) {
                error_log('[OTP] ' . $e->getMessage());
                $_SESSION['pending_registration']['resend_available_at'] = $now;
                json_error(['code' => 'Gagal mengirim kode OTP. Silakan coba lagi nanti.']);
            }

            json_ok(['cooldown' => 60], 'Kode OTP baru sudah dikirim ke email Anda.');
            break;

        case 'restart-registration':
            require_method('POST');
            verify_csrf();

            $user = current_user();
            if ($user && $user['role'] === 'customer' && empty($user['email_verified_at'])) {
                $_SESSION['_register_draft'] = [
                    'name' => (string) ($user['name'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'whatsapp' => (string) ($user['whatsapp'] ?? ''),
                ];
                (new User())->delete((string) $user['id']);
                unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['name'], $_SESSION['username'], $_SESSION['otp_resend_at']);
                session_regenerate_id(true);
            } elseif (!empty($_SESSION['pending_registration']) && is_array($_SESSION['pending_registration'])) {
                $pending = $_SESSION['pending_registration'];
                $_SESSION['_register_draft'] = [
                    'name' => (string) ($pending['name'] ?? ''),
                    'email' => (string) ($pending['email'] ?? ''),
                    'whatsapp' => (string) ($pending['whatsapp'] ?? ''),
                ];
                unset($_SESSION['pending_registration']);
            }

            json_ok([], null, 'register.php');
            break;

        case 'cancel-verification':
            require_method('POST');
            verify_csrf();

            $user = current_user();
            if ($user && $user['role'] === 'customer' && empty($user['email_verified_at'])) {
                (new User())->delete((string) $user['id']);
                unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['name'], $_SESSION['username'], $_SESSION['otp_resend_at']);
                session_regenerate_id(true);
            }
            unset($_SESSION['pending_registration'], $_SESSION['_register_draft']);

            flash('success', 'Pendaftaran akun dibatalkan.');
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

