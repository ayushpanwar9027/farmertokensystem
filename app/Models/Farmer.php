<?php

declare(strict_types=1);

namespace App\Models;

class Farmer extends BaseModel
{
    protected string $table = 'farmers';
    protected bool $softDeletes = true;

    public function byUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }
}
