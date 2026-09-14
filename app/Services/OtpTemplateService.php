<?php

declare(strict_types=1);

namespace App\Services;

class OtpTemplateService
{
    private const TEMPLATE_DIR = 'resources/templates/otp';

    public function render(string $template, string $locale, array $params = []): string
    {
        $templates = $this->load($locale);
        $text = $templates[$template] ?? $templates['otp.login_2fa'] ?? '';

        foreach ($params as $key => $value) {
            $text = str_replace('%' . $key . '%', (string) $value, $text);
        }

        return $text;
    }

    private function load(string $locale): array
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