<?php
declare(strict_types=1);

class Database
{
    private ?PDO $conn = null;

    public function getConnection(): PDO
    {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }

        $config = credentials('db', []);
        $dbName = (string) ($config['name'] ?? 'poolstream');
        $username = (string) ($config['user'] ?? 'root');
        $password = (string) ($config['pass'] ?? '');
        $port = (string) ($config['port'] ?? '3306');

        $hosts = array_values(array_filter(array_unique([
            (string) ($config['host'] ?? ''),
            'host.docker.internal',
            '172.17.0.2',
            '127.0.0.1',
            'localhost',
        ])));

        $lastError = null;
        foreach ($hosts as $host) {
            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 3,
                ]);
                $pdo->exec("SET time_zone = '+07:00'");
                return $this->conn = $pdo;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }

        throw new RuntimeException(
            'Gagal terhubung ke database MySQL. Periksa config/credentials.php. '
            . ($lastError?->getMessage() ?? ''),
            0,
            $lastError
        );
    }
}
