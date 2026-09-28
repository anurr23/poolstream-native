<?php
require_once __DIR__ . '/config/config.php';

// Jika sudah login, redirect langsung ke dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();

        if ($db) {
            $user = new User($db);
            $auth_result = $user->login($username, $password);

            if ($auth_result === true) {
                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                $_SESSION['name'] = $user->name;
                $_SESSION['user_role'] = $user->role;

                if ($remember) {
                    setcookie('poolstream_remember', $user->username, time() + (86400 * 30), "/");
                }

                header('Location: dashboard.php');
                exit();
            } elseif ($auth_result === 'inactive') {
                $error_message = 'Akun Anda telah dinonaktifkan. Silakan hubungi admin.';
            } else {
                $error_message = 'Username atau password tidak sesuai!';
            }
        } else {
            $error_message = 'Gagal terhubung ke database. Silakan periksa konfigurasi server.';
        }
    } else {
        $error_message = 'Username dan password wajib diisi!';
    }
}
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- PoolStream Shared Stylesheet with Cache Busting -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="bb-auth-page">
    <!-- Ambient Background Glows -->
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <!-- Theme Toggle -->
    <button id="themeToggle" class="theme-toggle-btn" title="Ganti Tema">
        <i class="bi bi-sun-fill" id="themeIcon"></i>
    </button>

    <div class="bb-auth-wrapper min-vh-100 d-flex align-items-center justify-content-center p-3 p-sm-4">
        <div class="bb-auth-card">
            <!-- Brand Logo -->
            <div style="text-align: center;">
                <div class="brand-logo-container">
                    <img src="assets/images/logo.png" alt="PoolStream Logo" onerror="this.src='images/logo.png'">
                </div>
                <h1 class="brand-title">PoolStream</h1>
            </div>

            <div style="text-align: center;">
                <h2 class="auth-heading">Selamat Datang Kembali</h2>
                <p class="auth-subheading">Masuk untuk mengelola monitoring meja biliar & kasir</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="custom-alert">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.1rem;"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <!-- Username Input -->
                <div class="form-group-custom">
                    <label for="username" class="custom-label">Username</label>
                    <div class="input-icon-wrapper">
                        <i class="bi bi-person input-icon"></i>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            class="custom-input"
                            placeholder="Masukkan username"
                            required
                            autofocus
                            autocomplete="username"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                        />
                    </div>
                </div>

                <!-- Password Input -->
                <div class="form-group-custom">
                    <label for="password" class="custom-label">Password</label>
                    <div class="input-icon-wrapper">
                        <i class="bi bi-shield-lock input-icon"></i>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="custom-input"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        />
                        <button type="button" class="toggle-password" id="togglePasswordBtn" title="Tampilkan/Sembunyikan password">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember & Forgot -->
                <div class="remember-wrapper">
                    <label class="custom-checkbox-label">
                        <input type="checkbox" id="remember" name="remember" class="custom-checkbox" <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                        <span>Ingat saya</span>
                    </label>
                    <a href="#" class="forgot-link" onclick="alert('Silakan hubungi Administrator untuk reset password akun Anda.'); return false;">
                        Lupa password?
                    </a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span id="btnText">Masuk Sekarang</span>
                    <i class="bi bi-arrow-right" id="btnIcon"></i>
                </button>
            </form>

            <div class="card-footer-info">
                <i class="bi bi-shield-check me-1" style="color: var(--bb-primary);"></i> Sistem Billing & Monitoring Biliar Terintegrasi
            </div>
        </div>
    </div>

    <script>
        // Theme switcher
        const toggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const savedTheme = localStorage.getItem('poolstream_theme') || 'dark';

        function applyTheme(theme) {
            document.documentElement.setAttribute('data-bs-theme', theme);
            if (theme === 'dark') {
                themeIcon.className = 'bi bi-sun-fill';
            } else {
                themeIcon.className = 'bi bi-moon-stars-fill';
            }
            localStorage.setItem('poolstream_theme', theme);
        }

        applyTheme(savedTheme);

        toggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-bs-theme');
            applyTheme(currentTheme === 'dark' ? 'light' : 'dark');
        });

        // Toggle password visibility
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
        });

        // Button feedback
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');

        loginForm.addEventListener('submit', () => {
            submitBtn.style.opacity = '0.85';
            submitBtn.style.pointerEvents = 'none';
            btnText.textContent = 'Memverifikasi...';
            btnIcon.className = 'spinner-border spinner-border-sm';
        });
    </script>
    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
