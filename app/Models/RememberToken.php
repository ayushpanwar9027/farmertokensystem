<?php

declare(strict_types=1);

namespace App\Models;

class RememberToken extends BaseModel
{
    protected string $table = 'remember_tokens';
    protected bool $softDeletes = false;

    public function byTokenHash(string $hash): ?array
    {
        return $this->findBy('token_hash', $hash);
    }
}
