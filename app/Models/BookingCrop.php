<?php

declare(strict_types=1);

namespace App\Models;

class BookingCrop extends BaseModel
{
    protected string $table = 'booking_crops';
    protected bool $softDeletes = false;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CANCELLED = 'CANCELLED';
}
