<?php
class FnbItem {
    private $conn;
    private $table_name = "fnb_items";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " ORDER BY category ASC, name ASC");
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as &$item) {
            $item['image_url'] = !empty($item['image_path']) ? 'storage/' . $item['image_path'] : null;
        }

        return $items;
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            $item['image_url'] = !empty($item['image_path']) ? 'storage/' . $item['image_path'] : null;
        }
        return $item;
    }
}
