<?php
declare(strict_types=1);

class AiEvaluation
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function find(int $id): ?array
    {
        $row = fetch_one('SELECT e.*, u.name AS user_name FROM ai_evaluations e LEFT JOIN users u ON u.id = e.user_id WHERE e.id = :id LIMIT 1', [':id' => $id]);
        return $row ? $this->decodeRow($row) : null;
    }

    public function latest(int $limit = 20): array
    {
        $rows = fetch_all("SELECT * FROM ai_evaluations ORDER BY created_at DESC LIMIT {$limit}");
        return array_map(fn (array $row): array => $this->decodeRow($row), $rows);
    }

    public function create(array $data): array
    {
        execute_sql(
            "INSERT INTO ai_evaluations
                (user_id, preset, start_date, end_date, bucket, sections, status, model, payload, created_at, updated_at)
             VALUES
                (:user_id, :preset, :start_date, :end_date, :bucket, :sections, 'running', :model, :payload, NOW(), NOW())",
            [
                ':user_id' => $data['user_id'],
                ':preset' => $data['preset'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'],
                ':bucket' => $data['bucket'],
                ':sections' => json_encode($data['sections'], JSON_UNESCAPED_UNICODE),
                ':model' => $data['model'] ?? null,
                ':payload' => json_encode($data['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            ]
        );
        return $this->find((int) $this->db->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['bucket', 'status', 'model', 'error'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[':' . $field] = $data[$field];
            }
        }
        foreach (['sections', 'payload', 'result'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[':' . $field] = $data[$field] === null ? null : json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }
        foreach (['prompt_tokens', 'completion_tokens', 'duration_ms'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[':' . $field] = $data[$field];
            }
        }
        if ($fields) {
            $fields[] = 'updated_at = NOW()';
            execute_sql('UPDATE ai_evaluations SET ' . implode(', ', $fields) . ' WHERE id = :id', $params);
        }
    }

    public function delete(int $id): void
    {
        execute_sql('DELETE FROM ai_evaluations WHERE id = :id', [':id' => $id]);
    }

    public function firstTransactionDate(): ?string
    {
        $value = fetch_value('SELECT MIN(created_at) FROM transactions');
        return $value ? (string) $value : null;
    }

    private function decodeRow(array $row): array
    {
        foreach (['sections', 'payload', 'result'] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : null;
            }
        }
        $row['start_date'] = $row['start_date'] ? date('Y-m-d', strtotime((string) $row['start_date'])) : null;
        $row['end_date'] = $row['end_date'] ? date('Y-m-d', strtotime((string) $row['end_date'])) : null;
        return $row;
    }
}

