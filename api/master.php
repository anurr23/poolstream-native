<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

api_handle(function (): void {
    require_login();
    require_role('admin');
    require_method('POST');
    verify_csrf();

    $resource = (string) ($_GET['resource'] ?? '');
    $action = (string) ($_GET['action'] ?? '');
    $id = (string) ($_GET['id'] ?? '');
    $input = request_input();
    $user = current_user();

    $redirect = match ($resource) {
        'tables' => 'master-tables.php',
        'packages' => 'master-packages.php',
        'fnb' => 'master-fnb-items.php',
        'users' => 'master-users.php',
        default => 'master.php',
    };

    if ($resource === 'tables') {
        if ($action === 'destroy') {
            if ($id === '') {
                json_error(['table' => 'Meja tidak ditemukan.']);
            }
            (new Table())->delete($id);
            flash('success', 'Meja berhasil dihapus.');
            json_ok([], null, $redirect);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $relay = (int) ($input['relay_channel'] ?? 0);
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama meja wajib diisi.';
        }
        if ($relay < 1 || $relay > 16) {
            $errors['relay_channel'] = 'Channel relay harus antara 1 dan 16.';
        }
        if (!$errors) {
            $existing = fetch_one(
                'SELECT id FROM tables WHERE relay_channel = :relay_channel AND id <> :id LIMIT 1',
                [':relay_channel' => $relay, ':id' => $id]
            );
            if ($existing) {
                $errors['relay_channel'] = 'Channel relay sudah digunakan meja lain.';
            }
        }
        if ($errors) {
            json_error($errors);
        }

        if ($action === 'store') {
            (new Table())->create($name, $relay);
            flash('success', 'Meja berhasil ditambahkan.');
        } elseif ($action === 'update') {
            (new Table())->update($id, $name, $relay);
            flash('success', 'Meja berhasil diupdate.');
        } elseif ($action === 'destroy') {
            (new Table())->delete($id);
            flash('success', 'Meja berhasil dihapus.');
        } else {
            json_error('Aksi tidak dikenal.', 404);
        }
        json_ok([], null, $redirect);
    }

    if ($resource === 'packages') {
        if ($action === 'destroy') {
            (new Package())->delete((int) $id);
            flash('success', 'Paket berhasil dihapus.');
            json_ok([], null, $redirect);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $price = normalize_money($input['price'] ?? 0);
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama paket wajib diisi.';
        }
        if ($price < 0) {
            $errors['price'] = 'Harga tidak boleh negatif.';
        }
        if ($errors) {
            json_error($errors);
        }

        if ($action === 'store') {
            (new Package())->create($name, $price);
            flash('success', 'Paket berhasil ditambahkan.');
        } elseif ($action === 'update') {
            (new Package())->update((int) $id, $name, $price);
            flash('success', 'Paket berhasil diupdate.');
        } elseif ($action === 'destroy') {
            (new Package())->delete((int) $id);
            flash('success', 'Paket berhasil dihapus.');
        } else {
            json_error('Aksi tidak dikenal.', 404);
        }
        json_ok([], null, $redirect);
    }

    if ($resource === 'fnb') {
        if ($action === 'destroy') {
            (new FnbItem())->delete((int) $id);
            flash('success', 'Menu F&B berhasil dihapus.');
            json_ok([], null, $redirect);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $price = normalize_money($input['price'] ?? 0);
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama menu wajib diisi.';
        }
        if ($price < 0) {
            $errors['price'] = 'Harga tidak boleh negatif.';
        }

        try {
            $imagePath = upload_image('image', 'fnb_images', ['jpg', 'jpeg', 'png', 'webp'], 2097152);
        } catch (Throwable $e) {
            $errors['image'] = $e->getMessage();
            $imagePath = null;
        }
        if ($errors) {
            json_error($errors);
        }

        $model = new FnbItem();
        if ($action === 'store') {
            $model->create(['name' => $name, 'category' => $category, 'price' => $price, 'image_path' => $imagePath]);
            flash('success', 'Menu F&B berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $item = $model->find((int) $id);
            if (!$item) {
                json_error(['item' => 'Menu tidak ditemukan.'], 404);
            }
            if ($imagePath !== null) {
                delete_upload($item['image_path']);
            }
            $model->update((int) $id, [
                'name' => $name,
                'category' => $category,
                'price' => $price,
                'image_path' => $imagePath ?? $item['image_path'],
            ]);
            flash('success', 'Menu F&B berhasil diupdate.');
        } elseif ($action === 'destroy') {
            $model->delete((int) $id);
            flash('success', 'Menu F&B berhasil dihapus.');
        } else {
            json_error('Aksi tidak dikenal.', 404);
        }
        json_ok([], null, $redirect);
    }

    if ($resource === 'users') {
        if ($action === 'destroy') {
            if ($id === $user['id']) {
                json_error(['user' => 'Tidak bisa menghapus akun sendiri.']);
            }
            $delete = fetch_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
            if ($delete) {
                delete_upload($delete['photo_path'] ?? null);
            }
            (new User())->delete($id);
            flash('success', 'User berhasil dihapus.');
            json_ok([], null, $redirect);
        }

        $existing = $action === 'update' ? fetch_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]) : null;
        if ($action === 'update' && !$existing) {
            json_error(['user' => 'User tidak ditemukan.'], 404);
        }

        $name = trim((string) ($input['name'] ?? ($existing['name'] ?? '')));
        $username = trim((string) ($input['username'] ?? ($existing['username'] ?? '')));
        $email = strtolower(trim((string) ($input['email'] ?? ($existing['email'] ?? ''))));
        $role = (string) ($input['role'] ?? ($existing['role'] ?? 'kasir'));
        $password = (string) ($input['password'] ?? '');
        $isActive = array_key_exists('is_active', $input) ? request_bool($input['is_active']) : (bool) ($existing['is_active'] ?? true);

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Nama wajib diisi.';
        }
        if ($username === '') {
            $errors['username'] = 'Username wajib diisi.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email tidak valid.';
        }
        if (!in_array($role, ['admin', 'kasir', 'customer'], true)) {
            $errors['role'] = 'Role tidak valid.';
        }
        if ($action === 'store' && strlen($password) < 4) {
            $errors['password'] = 'Password minimal 4 karakter.';
        }
        if ($action === 'update' && $password !== '' && strlen($password) < 4) {
            $errors['password'] = 'Password minimal 4 karakter.';
        }
        if (!$errors) {
            if (fetch_one('SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1', [':username' => $username, ':id' => $id])) {
                $errors['username'] = 'Username sudah digunakan.';
            }
            if ($email !== '' && fetch_one('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1', [':email' => $email, ':id' => $id])) {
                $errors['email'] = 'Email sudah digunakan.';
            }
        }
        if ($action === 'update' && !$isActive && $id === $user['id']) {
            $errors['is_active'] = 'Tidak bisa menonaktifkan akun sendiri.';
        }
        if ($action === 'destroy' && $id === $user['id']) {
            $errors['user'] = 'Tidak bisa menghapus akun sendiri.';
        }

        try {
            $photoPath = upload_image('photo', 'user_photos', ['jpg', 'jpeg', 'png', 'webp'], 2097152);
        } catch (Throwable $e) {
            $errors['photo'] = $e->getMessage();
            $photoPath = null;
        }
        if ($errors) {
            json_error($errors);
        }

        $model = new User();
        if ($action === 'store') {
            $model->create([
                'name' => $name,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'role' => $role,
                'is_active' => $isActive,
                'password' => $password,
                'photo_path' => $photoPath,
                'email_verified_at' => $email !== '' ? now() : null,
            ]);
            flash('success', 'User berhasil ditambahkan.');
        } elseif ($action === 'update') {
            $data = [
                'name' => $name,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'role' => $role,
                'is_active' => $isActive,
                'password' => $password,
            ];
            if ($email !== '' && $email !== ($existing['email'] ?? null)) {
                $data['email_verified_at'] = now();
            }
            if ($photoPath !== null) {
                delete_upload($existing['photo_path'] ?? null);
                $data['photo_path'] = $photoPath;
            }
            $model->update($id, $data);
            flash('success', 'User berhasil diupdate.');
        } elseif ($action === 'destroy') {
            $delete = fetch_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
            if ($delete) {
                delete_upload($delete['photo_path'] ?? null);
            }
            $model->delete($id);
            flash('success', 'User berhasil dihapus.');
        } else {
            json_error('Aksi tidak dikenal.', 404);
        }
        current_user(true);
        json_ok([], null, $redirect);
    }

    json_error('Resource tidak dikenal.', 404);
});
