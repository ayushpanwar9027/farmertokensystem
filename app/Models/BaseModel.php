<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Exceptions\DatabaseException;

class BaseModel
{
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected bool $softDeletes = false;

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " LIMIT 1";

        return Database::selectOne($sql, [$id]);
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " LIMIT 1";

        return Database::selectOne($sql, [$value]);
    }

    public function findMany(array $column, mixed $value): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        if ($this->softDeletes) {
            $sql .= " AND deleted_at IS NULL";
        }

        return Database::select($sql, [$value]);
    }

    public function where(array $conditions): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        $clauses = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $operator = $value[0] ?? '=';
                $val = $value[1] ?? null;
                $clauses[] = "{$column} {$operator} ?";
                $params[] = $val;
            } else {
                $clauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        if ($this->softDeletes) {
            if (empty($clauses)) {
                $sql .= " WHERE deleted_at IS NULL";
            } else {
                $sql .= " AND deleted_at IS NULL";
            }
        }

        return Database::select($sql, $params);
    }

    public function insert(array $data): int
    {
        if ($this->softDeletes && !isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return Database::insert($this->table, $data);
    }

    public function update(int $id, array $data): int
    {
        if ($this->softDeletes) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return Database::update(
            $this->table,
            $data,
            "{$this->primaryKey} = ?",
            [$id]
        );
    }

    public function delete(int $id): int
    {
        if ($this->softDeletes) {
            return Database::update(
                $this->table,
                ['deleted_at' => date('Y-m-d H:i:s')],
                "{$this->primaryKey} = ?",
                [$id]
            );
        }

        return Database::delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }

    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        $clauses = [];

        foreach ($conditions as $column => $value) {
            $clauses[] = "{$column} = ?";
            $params[] = $value;
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        if ($this->softDeletes) {
            if (empty($clauses)) {
                $sql .= " WHERE deleted_at IS NULL";
            } else {
                $sql .= " AND deleted_at IS NULL";
            }
        }

        $row = Database::selectOne($sql, $params);
        return $row ? (int) $row['total'] : 0;
    }

    public function paginate(array $conditions = [], int $page = 1, int $perPage = 20, string $orderBy = 'id', string $direction = 'DESC'): array
    {
        $total = $this->count($conditions);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        $clauses = [];

        foreach ($conditions as $column => $value) {
            $clauses[] = "{$column} = ?";
            $params[] = $value;
        }

        if (!empty($clauses)) {
            $sql .= " WHERE " . implode(' AND ', $clauses);
        }

        if ($this->softDeletes) {
            if (empty($clauses)) {
                $sql .= " WHERE deleted_at IS NULL";
            } else {
                $sql .= " AND deleted_at IS NULL";
            }
        }

        $sql .= " ORDER BY {$orderBy} {$direction} LIMIT {$perPage} OFFSET {$offset}";

        $data = Database::select($sql, $params);

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'next_page' => $page < $totalPages ? $page + 1 : null,
                'prev_page' => $page > 1 ? $page - 1 : null,
            ],
        ];
    }
}
