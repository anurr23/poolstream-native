<?php
declare(strict_types=1);

class User
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function find(string $id): ?array
    {
        return fetch_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function findByLogin(string $login): ?array
    {
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        return fetch_one("SELECT * FROM users WHERE {$field} = :login LIMIT 1", [':login' => $login]);
    }

    public function findByEmail(string $email): ?array
    {
        return fetch_one('SELECT * FROM users WHERE email = :email LIMIT 1', [':email' => strtolower($email)]);
    }

    public function findByWhatsapp(string $whatsapp, ?string $excludeId = null): ?array
    {
        if ($excludeId !== null && $excludeId !== '') {
            return fetch_one(
                'SELECT * FROM users WHERE whatsapp = :whatsapp AND id <> :id LIMIT 1',
                [':whatsapp' => $whatsapp, ':id' => $excludeId]
            );
        }
        return fetch_one('SELECT * FROM users WHERE whatsapp = :whatsapp LIMIT 1', [':whatsapp' => $whatsapp]);
    }

    public function findByGoogleId(string $googleId): ?array
    {
        return fetch_one('SELECT * FROM users WHERE google_id = :google_id LIMIT 1', [':google_id' => $googleId]);
    }

    public function all(): array
    {
        return array_map(static function (array $row): array {
            unset($row['password'], $row['remember_token'], $row['otp_code']);
            $row['is_active'] = (bool) $row['is_active'];
            $row['photo_url'] = storage_url($row['photo_path']);
            return $row;
        }, fetch_all('SELECT * FROM users ORDER BY name ASC'));
    }

    public function customers(): array
    {
        return fetch_all(
            "SELECT id, name, username, email, whatsapp
             FROM users
             WHERE role = 'customer' AND is_active = 1
             ORDER BY name ASC"
        );
    }

    public function create(array $data): array
    {
        $id = $data['id'] ?? uuid_v4();
        $username = $data['username'] ?? $this->makeUsernameFromEmail((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = $data['role'] ?? 'customer';
        $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;
        $email = !empty($data['email']) ? strtolower((string) $data['email']) : null;
        $emailVerified = array_key_exists('email_verified_at', $data)
            ? $data['email_verified_at']
            : ($email ? now() : null);

        execute_sql(
            'INSERT INTO users (id, name, username, email, whatsapp, email_verified_at, role, is_active, password, photo_path, google_id, created_at, updated_at)
             VALUES (:id, :name, :username, :email, :whatsapp, :email_verified_at, :role, :is_active, :password, :photo_path, :google_id, NOW(), NOW())',
            [
                ':id' => $id,
                ':name' => $data['name'],
                ':username' => $username,
                ':email' => $email,
                ':whatsapp' => !empty($data['whatsapp']) ? (string) $data['whatsapp'] : null,
                ':email_verified_at' => $emailVerified,
                ':role' => $role,
                ':is_active' => $isActive ? 1 : 0,
                ':password' => !empty($data['password_hash']) ? (string) $data['password_hash'] : password_hash($password, PASSWORD_BCRYPT),
                ':photo_path' => $data['photo_path'] ?? null,
                ':google_id' => $data['google_id'] ?? null,
            ]
        );

        return $this->find($id) ?? [];
    }

    public function update(string $id, array $data): array
    {
        $user = $this->find($id);
        if (!$user) {
            throw new RuntimeException('User tidak ditemukan.');
        }

        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'username', 'email', 'whatsapp', 'role', 'photo_path', 'google_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[':' . $field] = $data[$field] !== '' ? $data[$field] : null;
            }
        }
        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = $data['is_active'] ? 1 : 0;
        }
        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params[':password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        if (array_key_exists('email_verified_at', $data)) {
            $fields[] = 'email_verified_at = :email_verified_at';
            $params[':email_verified_at'] = $data['email_verified_at'];
        }
        if (array_key_exists('otp_code', $data)) {
            $fields[] = 'otp_code = :otp_code';
            $params[':otp_code'] = $data['otp_code'];
        }
        if (array_key_exists('otp_expires_at', $data)) {
            $fields[] = 'otp_expires_at = :otp_expires_at';
            $params[':otp_expires_at'] = $data['otp_expires_at'];
        }
        if (array_key_exists('otp_attempts', $data)) {
            $fields[] = 'otp_attempts = :otp_attempts';
            $params[':otp_attempts'] = (int) $data['otp_attempts'];
        }

        if ($fields) {
            $fields[] = 'updated_at = NOW()';
            execute_sql('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id', $params);
        }

        return $this->find($id) ?? [];
    }

    public function delete(string $id): void
    {
        execute_sql('DELETE FROM users WHERE id = :id', [':id' => $id]);
    }

    public function makeUsernameFromEmail(string $email): string
    {
        $base = slugify(strstr($email, '@', true) ?: 'customer');
        $base = substr($base ?: 'customer', 0, 20);
        $username = $base;
        $counter = 1;
        while (fetch_one('SELECT id FROM users WHERE username = :username LIMIT 1', [':username' => $username])) {
            $username = $base . $counter;
            $counter++;
        }
        return $username;
    }

    public function markVerified(string $id): void
    {
        execute_sql(
            'UPDATE users SET email_verified_at = NOW(), otp_code = NULL, otp_expires_at = NULL, otp_attempts = 0, updated_at = NOW() WHERE id = :id',
            [':id' => $id]
        );
    }
}
