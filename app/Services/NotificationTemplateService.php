<?php

declare(strict_types=1);

namespace App\Services;

class NotificationTemplateService
{
    private const TEMPLATE_DIR = 'resources/templates/notification';

    private const EVENT_CATALOG = [
        'booking_confirmed' => [
            'heading_en' => 'Booking Confirmed',
            'heading_hi' => 'बुकिंग की पुष्टि',
            'message_en' => 'Your booking at {centre} on {date} {slot} is confirmed. Token: {token}',
            'message_hi' => 'आपकी {centre} पर {date} {slot} की बुकिंग की पुष्टि हो गई है। टोकन: {token}',
        ],
        'booking_cancelled' => [
            'heading_en' => 'Booking Cancelled',
            'heading_hi' => 'बुकिंग रद्द',
            'message_en' => 'Your booking at {centre} on {date} has been cancelled.',
            'message_hi' => 'आपकी {centre} पर {date} की बुकिंग रद्द कर दी गई है।',
        ],
        'verification_approved' => [
            'heading_en' => 'Registration Approved',
            'heading_hi' => 'पंजीकरण स्वीकृत',
            'message_en' => 'Your registration is approved. You can now book slots.',
            'message_hi' => 'आपका पंजीकरण स्वीकृत हो गया है। अब आप स्लॉट बुक कर सकते हैं।',
        ],
        'verification_rejected' => [
            'heading_en' => 'Registration Rejected',
            'heading_hi' => 'पंजीकरण अस्वीकृत',
            'message_en' => 'Your registration was rejected. Reason: {reason}',
            'message_hi' => 'आपका पंजीकरण अस्वीकृत कर दिया गया। कारण: {reason}',
        ],
        'queue_approaching' => [
            'heading_en' => 'Queue Update',
            'heading_hi' => 'कतार अपडेट',
            'message_en' => 'You are {n} places away in the queue at {centre}.',
            'message_hi' => 'आप {centre} पर कतार में {n} स्थान दूर हैं।',
        ],
        'farmer_called' => [
            'heading_en' => 'You Are Called',
            'heading_hi' => 'आपको बुलाया गया है',
            'message_en' => 'Your token {token} has been called. Please report to the counter.',
            'message_hi' => 'आपके टोकन {token} को बुलाया गया है। कृपया काउंटर पर आएं।',
        ],
        'procurement_completed' => [
            'heading_en' => 'Procurement Completed',
            'heading_hi' => 'प्रक्रिया पूर्ण',
            'message_en' => 'Your procurement of {crop} ({qty}kg) is complete. Payment processing.',
            'message_hi' => 'आपकी {crop} ({qty}kg) की प्रक्रिया पूर्ण हो गई है। भुगतान प्रक्रिया जारी है।',
        ],
        'payment_processing' => [
            'heading_en' => 'Payment Processing',
            'heading_hi' => 'भुगतान प्रक्रिया',
            'message_en' => 'Your payment for {crop} is being processed.',
            'message_hi' => 'आपका {crop} के लिए भुगतान प्रक्रिया में है।',
        ],
        'payment_paid' => [
            'heading_en' => 'Payment Received',
            'heading_hi' => 'भुगतान प्राप्त',
            'message_en' => 'Rs.{amount} paid for {crop}. Ref: {reference}',
            'message_hi' => '{crop} के लिए Rs.{amount} का भुगतान हो गया। संदर्भ: {reference}',
        ],
        'payment_failed' => [
            'heading_en' => 'Payment Failed',
            'heading_hi' => 'भुगतान विफल',
            'message_en' => 'Your payment failed. Please contact support.',
            'message_hi' => 'आपका भुगतान विफल हो गया। कृपया सहायता से संपर्क करें।',
        ],
    ];

    public function render(string $event, string $locale, array $params = []): array
    {
        $catalog = self::EVENT_CATALOG[$event] ?? null;
        if ($catalog === null) {
            $fileTemplates = $this->loadFromFiles($locale);
            $catalog = $fileTemplates[$event] ?? null;
        }

        if ($catalog === null) {
            throw new \RuntimeException("TEMPLATE_NOT_FOUND: Event template '{$event}' not found in catalog");
        }

        $heading = $catalog['heading_' . $locale] ?? $catalog['heading_en'] ?? $event;
        $message = $catalog['message_' . $locale] ?? $catalog['message_en'] ?? '';

        $heading = $this->interpolate($heading, $params, $event);
        $message = $this->interpolate($message, $params, $event);

        return [
            'heading' => $heading,
            'message' => $message,
            'heading_en' => $this->interpolate($catalog['heading_en'] ?? $event, $params, $event),
            'message_en' => $this->interpolate($catalog['message_en'] ?? '', $params, $event),
            'heading_hi' => $this->interpolate($catalog['heading_hi'] ?? $catalog['heading_en'] ?? $event, $params, $event),
            'message_hi' => $this->interpolate($catalog['message_hi'] ?? $catalog['message_en'] ?? '', $params, $event),
        ];
    }

    public function renderOtp(string $templateId, string $locale, array $params = []): string
    {
        return (new OtpTemplateService())->render($templateId, $locale, $params);
    }

    public function isValidEvent(string $event): bool
    {
        return array_key_exists($event, self::EVENT_CATALOG);
    }

    public function catalogKeys(): array
    {
        return array_keys(self::EVENT_CATALOG);
    }

    private function interpolate(string $text, array $params, string $event): string
    {
        $placeholders = $this->extractPlaceholders($text);
        $available = array_merge(['event' => $event], $params);

        foreach ($placeholders as $ph) {
            if (!array_key_exists($ph, $available) || $available[$ph] === null || $available[$ph] === '') {
                throw new \RuntimeException(
                    "TEMPLATE_NOT_FOUND: Unresolved placeholder '{\$" . $ph . "}' in event '{$event}'"
                );
            }
            $text = str_replace('{' . $ph . '}', (string) $available[$ph], $text);
        }

        return $text;
    }

    private function extractPlaceholders(string $text): array
    {
        preg_match_all('/\{(\w+)\}/', $text, $matches);
        return array_unique($matches[1]);
    }

    private function loadFromFiles(string $locale): array
    {
        $file = base_path(self::TEMPLATE_DIR . '/' . $locale . '.php');
        if ($locale !== 'en' && !is_file($file)) {
            $file = base_path(self::TEMPLATE_DIR . '/en.php');
        }
        if (!is_file($file)) {
            return [];
        }

        $templates = require $file;
        return is_array($templates) ? $templates : [];
    }
}
