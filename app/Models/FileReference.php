<?php

declare(strict_types=1);

namespace App\Models;

class FileReference extends BaseModel
{
    protected string $table = 'file_references';

    public function forFile(int $fileId): array
    {
        return $this->where(['file_id' => $fileId]);
    }
}