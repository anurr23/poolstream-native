<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

function google_popup(string $status, string $redirectUrl, string $message = ''): never
{
    $statusJson = json_encode($status);
    $redirectJson = json_encode($redirectUrl);
    $messageJson = json_encode($message);
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autentikasi Google</title>
    <style>
        body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#0f172a;color:#f8fafc;text-align:center;padding:1rem}.box{max-width:320px}.spinner{width:36px;height:36px;border:3px solid rgba(255,255,255,.15);border-top-color:#06b6d4;border-radius:50%;animation:spin .8s linear infinite;margin:0 auto 16px}@keyframes spin{to{transform:rotate(360deg)}}.text{font-size:.95rem;opacity:.9}
    </style>
</head>
<body>
<div class="box">
    <div class="spinner"></div>
    <div class="text"><?= $status === 'success' ? 'Berhasil masuk. Mengalihkan...' : e($message ?: 'Terjadi kesalahan.') ?></div>
</div>
<script>
    (function () {
        var status = <?= $statusJson ?>;
        var redirectUrl = <?= $redirectJson ?>;
        var message = <?= $messageJson ?>;
        if (window.opener && !window.opener.closed) {
            try {
                window.opener.postMessage({ type: status === 'success' ? 'google-auth-success' : 'google-auth-error', url: redirectUrl, message: message }, window.location.origin);
                window.close();
                return;
            } catch (e) {}
        }
        window.location.href = redirectUrl;
    })();
</script>
</body>
</html><?php
    exit;
}

$action = (string) ($_GET['action'] ?? 'redirect');

if (!google_oauth_configured()) {
    if ($action === 'callback') {
        google_popup('error', url('login.php'), 'Login dengan Google belum dikonfigurasi.');
    }
    flash('error', 'Login dengan Google belum dikonfigurasi.');
    redirect('login.php');
}

$clientId = (string) credentials('google.client_id', '');
$clientSecret = (string) credentials('google.client_secret', '');
$redirectUri = (string) credentials('google.redirect', '');

if ($action === 'redirect') {
    $state = bin2hex(random_bytes(24));
    $_SESSION['google_oauth_state'] = $state;
    $params = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'state' => $state,
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
if ($state === '' || !hash_equals((string) ($_SESSION['google_oauth_state'] ?? ''), $state)) {
    google_popup('error', url('login.php'), 'State autentikasi Google tidak valid.');
}
unset($_SESSION['google_oauth_state']);

if ($code === '') {
    google_popup('error', url('login.php'), 'Gagal masuk dengan Google. Silakan coba lagi.');
}

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'code' => $code,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenRaw = curl_exec($ch);
$tokenStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$token = json_decode((string) $tokenRaw, true);
if ($tokenStatus >= 400 || empty($token['access_token'])) {
    google_popup('error', url('login.php'), 'Gagal menukar token Google.');
}

$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token['access_token']],
]);
$profileRaw = curl_exec($ch);
$profileStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$profile = json_decode((string) $profileRaw, true);
if ($profileStatus >= 400 || empty($profile['email'])) {
    google_popup('error', url('login.php'), 'Akun Google tidak memiliki alamat email.');
}

$email = strtolower((string) $profile['email']);
$googleId = (string) ($profile['id'] ?? '');
$name = (string) ($profile['name'] ?? strstr($email, '@', true));
$avatar = (string) ($profile['picture'] ?? '');

$userModel = new User();
$user = $userModel->findByGoogleId($googleId) ?: $userModel->findByEmail($email);
if ($user) {
    if ($user['role'] !== 'customer') {
        google_popup('error', url('login.php'), 'Email ini terdaftar sebagai akun staff. Silakan login dengan username.');
    }
    $data = ['google_id' => $googleId];
    if (empty($user['email_verified_at'])) {
        $data['email_verified_at'] = now();
    }
    if (empty($user['photo_path']) || str_starts_with((string) $user['photo_path'], 'user_photos/google_avatar_')) {
        $data['photo_path'] = google_download_avatar($user['id'], $avatar) ?? $avatar;
    }
    $user = $userModel->update((string) $user['id'], $data);
} else {
    $user = $userModel->create([
        'name' => $name,
        'username' => $userModel->makeUsernameFromEmail($email),
        'email' => $email,
        'email_verified_at' => now(),
        'google_id' => $googleId,
        'password' => bin2hex(random_bytes(20)),
        'role' => 'customer',
        'is_active' => true,
        'photo_path' => google_download_avatar(uuid_v4(), $avatar) ?? $avatar,
    ]);
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['name'] = $user['name'];
$_SESSION['username'] = $user['username'];

google_popup('success', url('customer-home.php'), 'Berhasil masuk dengan Google.');

function google_download_avatar(string $identifier, string $url): ?string
{
    if ($url === '') {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $binary = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400 || !is_string($binary) || $binary === '') {
        return null;
    }
    ensure_dir(APP_ROOT . '/storage/user_photos');
    $filename = 'user_photos/google_avatar_' . $identifier . '.jpg';
    file_put_contents(APP_ROOT . '/storage/' . $filename, $binary);
    return $filename;
}
