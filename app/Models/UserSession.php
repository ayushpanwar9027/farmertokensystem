<?php

declare(strict_types=1);

namespace App\Models;

class UserSession extends BaseModel
{
    protected string $table = 'user_sessions';
    protected bool $softDeletes = false;

    public function byTokenHash(string $hash): ?array
    {
        return $this->findBy('session_token_hash', $hash);
    }

    public function activeForUser(int $userId): array
    {
        return $this->where([
            'user_id' => $userId,
            'status' => 'ACTIVE',
        ]);
    }
}
