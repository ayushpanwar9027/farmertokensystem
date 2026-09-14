<?php

declare(strict_types=1);

return [
    'immediate'             => (bool) env('PUSH_IMMEDIATE', true),
    'retry_max'             => (int) env('PUSH_RETRY_MAX', 3),
    'backoff_base_seconds'  => (int) env('PUSH_BACKOFF_BASE', 60),
    'backoff_cap_seconds'   => (int) env('PUSH_BACKOFF_CAP', 1800),
    'dedup_window_seconds'  => (int) env('PUSH_DEDUP_WINDOW', 3600),
];
