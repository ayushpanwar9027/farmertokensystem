<?php

declare(strict_types=1);

namespace App\Models;

class LoginHistory extends BaseModel
{
    protected string $table = 'login_history';
    protected bool $softDeletes = false;
}
