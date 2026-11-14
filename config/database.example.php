<?php
// Salin file ini menjadi database.php lalu sesuaikan dengan device masing-masing.
// database.php TIDAK di-push (masuk .gitignore) karena beda device beda config.
class Database {
    private $host = 'mysql';          // hostname service MySQL / IP container
    private $port = '3306';
    private $db_name = 'billiard';
    private $username = 'root';
    private $password = 'root';       // ganti sesuai device
    private $conn;

    public function getConnection() {
        $this->conn = null;

        // Coba koneksi host container, jika gagal fallback ke host lain
        $hosts = [$this->host, '172.17.0.2', 'host.docker.internal', '127.0.0.1'];

        foreach ($hosts as $h) {
            try {
                $dsn = "mysql:host=" . $h . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2
                ]);
                $this->conn->exec("SET time_zone = '+07:00'");
                break;
            } catch (PDOException $e) {
                // Lanjut coba host berikutnya
                continue;
            }
        }

        if (!$this->conn) {
            error_log("Database connection error: Gagal terhubung ke MySQL container.");
        }

        return $this->conn;
    }
}
