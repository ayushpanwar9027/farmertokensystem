<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;

class AuditService
{
    public function log(array $entry): void
    {
        $enriched = $this->enrich($entry);

        $allowed = [
            'user_id', 'user_name', 'user_role', 'action', 'module',
            'entity_type', 'entity_id', 'old_value', 'new_value',
            'reason', 'ip_address', 'user_agent', 'request_id',
        ];

        $data = [];
        foreach ($allowed as $col) {
            if (array_key_exists($col, $enriched) && $enriched[$col] !== null && $enriched[$col] !== '') {
                $value = $enriched[$col];
                if (in_array($col, ['old_value', 'new_value'], true) && is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $data[$col] = $value;
            }
        }

        if (empty($data)) {
            return;
        }

        Database::insert('audit_logs', $data);
    }

    private function enrich(array $entry): array
    {
        if (!isset($entry['ip_address'])) {
            $entry['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        if (!isset($entry['user_agent'])) {
            $entry['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }

        if (!isset($entry['request_id'])) {
            $entry['request_id'] = Request::currentRequestId();
        }

        return $entry;
    }
}