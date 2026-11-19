<?php
declare(strict_types=1);

class Package
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function all(): array
    {
        return array_map(
            static fn (array $row): array => shape_package($row),
            fetch_all('SELECT * FROM packages ORDER BY price ASC')
        );
    }

    public function find(int $id): ?array
    {
        $row = fetch_one('SELECT * FROM packages WHERE id = :id LIMIT 1', [':id' => $id]);
        return $row ? shape_package($row) : null;
    }

    public function create(string $name, float $price): array
    {
        $stmt = $this->db->prepare('INSERT INTO packages (name, price, created_at, updated_at) VALUES (:name, :price, NOW(), NOW())');
        $stmt->execute([':name' => $name, ':price' => $price]);
        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function update(int $id, string $name, float $price): void
    {
        execute_sql('UPDATE packages SET name = :name, price = :price, updated_at = NOW() WHERE id = :id', [
            ':id' => $id,
            ':name' => $name,
            ':price' => $price,
        ]);
    }

    public function delete(int $id): void
    {
        execute_sql('DELETE FROM packages WHERE id = :id', [':id' => $id]);
    }
}
