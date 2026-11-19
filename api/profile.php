<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    $action = (string) ($_GET['action'] ?? '');
    $input = request_input();
    $user = current_user();
    if (!$user) {
        json_error('Unauthorized', 401);
    }

    if ($action === 'update') {
        require_method('POST');
        verify_csrf();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            json_error(['name' => 'Nama wajib diisi.']);
        }

        $data = ['name' => $name];
        try {
            $photo = upload_image('photo', 'user_photos', ['jpg', 'jpeg', 'png', 'webp'], 2097152);
            if ($photo !== null) {
                delete_upload($user['photo_path'] ?? null);
                $data['photo_path'] = $photo;
            }
        } catch (Throwable $e) {
            json_error(['photo' => $e->getMessage()]);
        }

        (new User())->update((string) $user['id'], $data);
        current_user(true);
        flash('success', 'Profil berhasil diupdate.');
        json_ok([], null, 'profile.php');
    }

    if ($action === 'password') {
        require_method('POST');
        verify_csrf();
        $current = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        if (!password_verify($current, (string) $user['password'])) {
            json_error(['current_password' => 'Password saat ini tidak sesuai.']);
        }
        if (strlen($password) < 8) {
            json_error(['password' => 'Password minimal 8 karakter.']);
        }
        if ($password !== $confirmation) {
            json_error(['password' => 'Konfirmasi password tidak cocok.']);
        }

        (new User())->update((string) $user['id'], ['password' => $password]);
        current_user(true);
        flash('success', 'Password berhasil diubah.');
        json_ok([], null, 'profile.php');
    }

    json_error('Aksi tidak dikenal.', 404);
});
