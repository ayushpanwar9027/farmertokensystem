<?php

declare(strict_types=1);

namespace App\Models;

class FileFolder extends BaseModel
{
    protected string $table = 'file_folders';
    protected bool $softDeletes = true;
}