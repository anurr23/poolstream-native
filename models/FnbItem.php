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
            $item['image_url'] = !empty($item['image_path']) ? 'storage/fnb_images/' . $item['image_path'] : null;
        }

        return $items;
    }

    public function addItem($name, $category, $price, $image_path = null) {
        $stmt = $this->conn->prepare("INSERT INTO " . $this->table_name . " (name, category, price, image_path, created_at, updated_at) VALUES (:name, :category, :price, :image_path, NOW(), NOW())");
        return $stmt->execute([
            ':name' => $name,
            ':category' => $category,
            ':price' => $price,
            ':image_path' => $image_path
        ]);
    }

    public function deleteItem($id) {
        // Ambil info image_path untuk dihapus filenya jika ada
        $stmtGet = $this->conn->prepare("SELECT image_path FROM " . $this->table_name . " WHERE id = :id LIMIT 1");
        $stmtGet->execute([':id' => $id]);
        $item = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if ($item && !empty($item['image_path'])) {
            $fullPath = __DIR__ . '/../storage/' . $item['image_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $stmt = $this->conn->prepare("DELETE FROM " . $this->table_name . " WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
