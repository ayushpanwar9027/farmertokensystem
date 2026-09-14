<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

Database::query("DELETE FROM rate_limit_logs");

echo "rate_limit_logs cleared\n";