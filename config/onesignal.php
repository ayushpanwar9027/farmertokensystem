<?php

declare(strict_types=1);

return [
    'onesignal_app_id'       => env('ONESIGNAL_APP_ID', 'your-onesignal-app-id'),
    'onesignal_rest_api_key' => env('ONESIGNAL_REST_API_KEY', 'your-onesignal-rest-api-key'),
    'timeout'                => (int) env('ONESIGNAL_TIMEOUT', 10),
];
