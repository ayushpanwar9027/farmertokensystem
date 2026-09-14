<?php

declare(strict_types=1);

namespace App\Models;

class File extends BaseModel
{
    protected string $table = 'files';
    protected bool $softDeletes = true;

    public function findByPathOrUrl(string $path): ?array
    {
        return $this->findBy('path_or_url', $path);
    }

    public function hardDelete(int $id): int
    {
        return \App\Core\Database::delete($this->table, "{$this->primaryKey} = ?", [$id]);
    }
}