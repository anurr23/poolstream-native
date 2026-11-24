<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

class Database
{
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $host = env_required('DB_HOST');
        $port = env_int('DB_PORT');
        $dbName = env_required('DB_NAME');
        $username = env_required('DB_USER');
        $password = env_required('DB_PASSWORD');
        $timezone = env_required('DB_TIMEZONE');
        $timeout = env_int('DB_TIMEOUT');

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => $timeout,
            ]);
            $pdo->exec('SET time_zone = ' . $pdo->quote($timezone));
            return $this->conn = $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Gagal terhubung ke database MySQL. Periksa DB_HOST, DB_PORT, DB_NAME, '
                . 'DB_USER, dan DB_PASSWORD di .env. ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
