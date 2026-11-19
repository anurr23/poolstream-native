<?php
declare(strict_types=1);

class FnbItem
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function all(): array
    {
        return array_map(
            static fn (array $row): array => shape_fnb_item($row),
            fetch_all('SELECT * FROM fnb_items ORDER BY name ASC')
        );
    }

    public function find(int $id): ?array
    {
        $row = fetch_one('SELECT * FROM fnb_items WHERE id = :id LIMIT 1', [':id' => $id]);
        return $row ? shape_fnb_item($row) : null;
    }

    public function create(array $data): array
    {
        execute_sql(
            'INSERT INTO fnb_items (name, category, price, image_path, created_at, updated_at)
             VALUES (:name, :category, :price, :image_path, NOW(), NOW())',
            [
                ':name' => $data['name'],
                ':category' => $data['category'] ?: null,
                ':price' => (float) $data['price'],
                ':image_path' => $data['image_path'] ?? null,
            ]
        );
        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): array
    {
        $item = $this->find($id);
        if (!$item) {
            throw new RuntimeException('Menu F&B tidak ditemukan.');
        }

        $imagePath = $data['image_path'] ?? $item['image_path'];
        execute_sql(
            'UPDATE fnb_items SET name = :name, category = :category, price = :price, image_path = :image_path, updated_at = NOW() WHERE id = :id',
            [
                ':id' => $id,
                ':name' => $data['name'],
                ':category' => $data['category'] ?: null,
                ':price' => (float) $data['price'],
                ':image_path' => $imagePath,
            ]
        );

        return $this->find($id) ?? [];
    }

    public function delete(int $id): void
    {
        $item = $this->find($id);
        if ($item) {
            delete_upload($item['image_path']);
        }
        execute_sql('DELETE FROM fnb_items WHERE id = :id', [':id' => $id]);
    }

    public function categories(): array
    {
        $rows = fetch_all("SELECT DISTINCT category FROM fnb_items WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
        return array_values(array_map(static fn (array $row): string => (string) $row['category'], $rows));
    }
}
