<?php

declare(strict_types=1);

return [
    'permission_negative_priority' => (bool) env('PERMISSION_NEGATIVE_PRIORITY', true),
    'super_admin_role_id' => (int) env('SUPER_ADMIN_ROLE_ID', 1),
];