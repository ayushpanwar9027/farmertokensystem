<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Database;
use App\Exceptions\ValidationException;

class Validator implements ValidatorInterface
{
    private array $errors = [];

    public function validate(array $data, array $rules): array
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return $data;
    }

    private function applyRule(string $field, mixed $value, string $rule, array $allData): void
    {
        $param = null;
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
        }

        $this->{$rule}($field, $value, $param, $allData);
    }

    private function required(string $field, mixed $value): void
    {
        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $this->addError($field, 'The :field field is required');
        }
    }

    private function string(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_string($value)) {
            $this->addError($field, 'The :field field must be a string');
        }
    }

    private function int(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !ctype_digit((string) $value)) {
            $this->addError($field, 'The :field field must be an integer');
        }
    }

    private function integer(string $field, mixed $value): void
    {
        $this->int($field, $value);
    }

    private function numeric(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'The :field field must be numeric');
        }
    }

    private function email(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'The :field field must be a valid email address');
        }
    }

    private function mobile(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !preg_match('/^[6-9]\d{9}$/', (string) $value)) {
            $this->addError($field, 'The :field field must be a valid 10-digit Indian mobile number');
        }
    }

    private function date(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $d = \DateTime::createFromFormat('Y-m-d', (string) $value);
            if (!$d || $d->format('Y-m-d') !== (string) $value) {
                $this->addError($field, 'The :field field must be a valid date');
            }
        }
    }

    private function date_format(string $field, mixed $value, string $format): void
    {
        if ($value !== null && $value !== '') {
            $d = \DateTime::createFromFormat($format, (string) $value);
            if (!$d || $d->format($format) !== (string) $value) {
                $this->addError($field, 'The :field field must match format ' . $format);
            }
        }
    }

    private function time(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string) $value)) {
            $this->addError($field, 'The :field field must be a valid time');
        }
    }

    private function in(string $field, mixed $value, string $allowed): void
    {
        if ($value !== null && $value !== '') {
            $options = explode(',', $allowed);
            if (!in_array((string) $value, $options, true)) {
                $this->addError($field, 'The :field field must be one of: ' . $allowed);
            }
        }
    }

    private function max(string $field, mixed $value, string $length): void
    {
        if ($value !== null && $value !== '' && is_string($value) && mb_strlen($value) > (int) $length) {
            $this->addError($field, 'The :field field must not exceed ' . $length . ' characters');
        }
    }

    private function min(string $field, mixed $value, string $length): void
    {
        if ($value !== null && $value !== '' && is_string($value) && mb_strlen($value) < (int) $length) {
            $this->addError($field, 'The :field field must be at least ' . $length . ' characters');
        }
    }

    private function exists(string $field, mixed $value, string $param): void
    {
        if ($value !== null && $value !== '') {
            [$table, $column] = explode(',', $param);
            $row = Database::selectOne(
                "SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1",
                [(string) $value]
            );
            if ($row === null) {
                $this->addError($field, 'The selected :field is invalid');
            }
        }
    }

    private function unique(string $field, mixed $value, string $param): void
    {
        if ($value !== null && $value !== '') {
            [$table, $column] = explode(',', $param);
            $row = Database::selectOne(
                "SELECT 1 FROM {$table} WHERE {$column} = ? LIMIT 1",
                [(string) $value]
            );
            if ($row !== null) {
                $this->addError($field, 'The :field has already been taken');
            }
        }
    }

    private function confirmed(string $field, mixed $value, ?string $param, array $allData): void
    {
        $confirmField = $field . '_confirmation';
        if (!array_key_exists($confirmField, $allData) || $allData[$confirmField] !== $value) {
            $this->addError($field, 'The :field confirmation does not match');
        }
    }

    private function boolean(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !in_array($value, [true, false, 0, 1, '0', '1'], true)) {
            $this->addError($field, 'The :field field must be true or false');
        }
    }

    private function json(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $decoded = json_decode((string) $value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->addError($field, 'The :field field must be valid JSON');
            }
        }
    }

    private function array_type(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !is_array($value)) {
            $this->addError($field, 'The :field field must be an array');
        }
    }

    private function strong_password(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $password = (string) $value;
            if (strlen($password) < 8) {
                $this->addError($field, 'The :field must be at least 8 characters');
            }
            if (!preg_match('/[A-Za-z]/', $password)) {
                $this->addError($field, 'The :field must contain at least one letter');
            }
            if (!preg_match('/\d/', $password)) {
                $this->addError($field, 'The :field must contain at least one digit');
            }
        }
    }

    private function letters_digits(string $field, mixed $value): void
    {
        $this->strong_password($field, $value);
    }

    private function digits(string $field, mixed $value, ?string $length = null): void
    {
        if ($value !== null && $value !== '') {
            $value = (string) $value;
            if (!preg_match('/^\d+$/', $value)) {
                $this->addError($field, 'The :field must contain only digits');
                return;
            }
            if ($length !== null && strlen($value) !== (int) $length) {
                $this->addError($field, 'The :field must be ' . $length . ' digits');
            }
        }
    }

    private function hex(string $field, mixed $value): void
    {
        if ($value !== null && $value !== '' && !preg_match('/^[a-f0-9]+$/i', (string) $value)) {
            $this->addError($field, 'The :field must be a valid hexadecimal string');
        }
    }

    private function regex(string $field, mixed $value, string $pattern): void
    {
        if ($value !== null && $value !== '' && !preg_match($pattern, (string) $value)) {
            $this->addError($field, 'The :field format is invalid');
        }
    }

    private function nullable(string $field, mixed $value): void
    {
    }

    private function addError(string $field, string $message): void
    {
        $label = str_replace('_', ' ', $field);
        $label = ucfirst($label);
        $message = str_replace(':field', $label, $message);
        $this->errors[$field][] = $message;
    }
}
