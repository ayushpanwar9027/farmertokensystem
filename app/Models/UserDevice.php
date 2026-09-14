<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class UserDevice extends BaseModel
{
    protected string $table = 'user_devices';
    protected bool $softDeletes = false;

    public function byUserDevice(int $userId, string $deviceId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM user_devices WHERE user_id = ? AND device_id = ? LIMIT 1",
            [$userId, $deviceId]
        );
    }
}
