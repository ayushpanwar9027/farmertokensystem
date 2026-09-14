<?php

declare(strict_types=1);

return [
    'otp_api_key'      => env('OTP_API_KEY', 'your-otp-gateway-api-key'),
    'otp_sender_id'    => env('OTP_SENDER_ID', 'FPS'),
    'otp_template_id'  => env('OTP_TEMPLATE_ID', 'your-otp-template-id'),
];