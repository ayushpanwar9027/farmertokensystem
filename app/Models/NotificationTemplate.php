<?php

declare(strict_types=1);

namespace App\Models;

class NotificationTemplate extends BaseModel
{
    protected string $table = 'notification_templates';
    protected bool $softDeletes = false;
}
