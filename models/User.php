<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $name;
    public $username;
    public $role;
    public $photo_path;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        if (!$this->conn) {
            return false;
        }

        $query = "SELECT id, name, username, password, role, is_active 
                  FROM " . $this->table_name . " 
                  WHERE username = :username 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if (!$user['is_active']) {
                return 'inactive';
            }

            if (password_verify($password, $user['password'])) {
                $this->id = $user['id'];
                $this->name = $user['name'];
                $this->username = $user['username'];
                $this->role = $user['role'] ?? 'kasir';
                $this->is_active = $user['is_active'];
                return true;
            }
        }

        return false;
    }

    public function getById($id) {
        $query = "SELECT id, name, username, role, photo_path, is_active 
                  FROM " . $this->table_name . " 
                  WHERE id = :id 
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
