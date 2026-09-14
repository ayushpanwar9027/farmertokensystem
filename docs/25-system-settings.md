# System Settings & Configuration

## Overview

Central configuration system. Non-sensitive settings stored as normal rows; sensitive values go through the database secrets manager (see [26-secret-management.md](26-secret-management.md)).

## Data Model

### system_settings
```sql
CREATE TABLE system_settings (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    key_value TEXT NULL,
    value_type ENUM('STRING','INT','BOOL','JSON','FLOAT') DEFAULT 'STRING',
    is_public TINYINT(1) DEFAULT 0,      -- exposed to non-admin endpoints
    is_sensitive TINYINT(1) DEFAULT 0,   -- masked in API output
    group_name VARCHAR(50) NULL,         -- GENERAL, NOTIFICATION, SECURITY...
    description VARCHAR(500) NULL,
    updated_by INT NULL,
    created_at, updated_at
);
```

## Setting Groups & Keys

### GENERAL
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `system_name` | STRING | Farmer Procurement System | yes |
| `default_language` | STRING | en | yes |
| `timezone` | STRING | Asia/Kolkata | yes |
| `support_contact_phone` | STRING | - | yes |
| `support_contact_email` | STRING | - | yes |

### MAINTENANCE
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `maintenance_mode` | BOOL | 0 | yes |
| `maintenance_message` | STRING | - | yes |
| `maintenance_expected_available_at` | STRING | - | yes |

### NOTIFICATION
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `push_enabled` | BOOL | 1 | no |
| `sms_enabled` | BOOL | 1 | no |
| `otp_expiry_minutes` | INT | 5 | no |
| `otp_resend_cooldown_seconds` | INT | 60 | no |
| `two_factor_enabled_default` | BOOL | 0 | no |
| `notification_retry_count` | INT | 3 | no |
| `notification_retry_interval_seconds` | INT | 300 | no |
| `queue_notification_threshold` | INT | 3 | no |
| `sms_from_name` | STRING | FPS | no |

> OneSignal/OTP gateway keys are NOT settings — they are hardcoded in `config/onesignal.php` and `config/otp.php` for now (see [26-secret-management.md](26-secret-management.md)).

### BOOKING
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `booking_cancellation_window_minutes` | INT | 120 | no |
| `max_crops_per_booking` | INT | 10 | no |
| `max_quantity_kg_per_booking` | INT | 5000 | no |

### SECURITY & SESSION
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `session_timeout_minutes` | INT | 30 | no |
| `remember_me_expiry_days` | INT | 30 | no |
| `max_concurrent_sessions` | INT | 10 | no |
| `max_login_attempts` | INT | 5 | no |
| `lockout_minutes` | INT | 15 | no |

### FILE
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `file_upload_max_size_mb` | INT | 5 | no |
| `allowed_file_types` | JSON | ["jpg","png","pdf"] | no |

### RATE LIMIT
| Key | Type | Default | Public |
|-----|------|---------|--------|
| `rate_limit_login_per_min` | INT | 5 | no |
| `rate_limit_otp_per_5min` | INT | 3 | no |
| `rate_limit_booking_per_min` | INT | 10 | no |
| `rate_limit_sms_per_min` | INT | 10 | no |
| `rate_limit_general_per_min` | INT | 60 | no |

## Setting Access Levels

- **Public**: returned to any authenticated (and some public) endpoints
  - E.g., system_name, maintenance_mode, default_language, support_contact
- **Admin**: only `manage_system_settings` (Super Admin) can view/edit
- **Sensitive**: stored securely, masked; see secrets doc

## PHP Helper

```php
// Helper to read setting (with in-memory cache)
function setting(string $key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];
    $row = Database::selectOne(
        "SELECT key_value, value_type FROM system_settings WHERE key_name = ? AND deleted_at IS NULL",
        [$key]
    );
    $value = $row ? SettingService::cast($row['key_value'], $row['value_type']) : $default;
    $cache[$key] = $value;
    return $value;
}
```

Type casting:
```php
switch ($type) {
    case 'BOOL':  return (bool)$value;
    case 'INT':   return (int)$value;
    case 'FLOAT': return (float)$value;
    case 'JSON':  return json_decode($value, true);
    default:      return (string)$value;
}
```

## Settings Service

```php
class SettingService {
    public function all(bool $includePrivate = false): array;
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;      // audits
    public function setMany(array $values): void;         // audits
    private function cast($value, string $type);
    private function validate(string $key, $value);        // type + allowed
}
```

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | /settings | Public-safe subset (authenticated) |
| GET | /settings/admin | All settings (Super Admin) |
| PUT | /settings | Update (Super Admin, manage_system_settings) |

## Validation

- Key must exist in settings registry (no arbitrary new keys)
- Value validated against expected type
- Some settings have range constraints
- Changing maintenance_type via this endpoint captured (audit) + triggers maintenance middleware behavior

## Caching

- In-memory cache per request
- Optional file cache (storage/cache/settings.php) on shared hosting for cross-request performance — invalidated on update
- Avoid stale maintenecache — maintenance_mode read fresh

## Audit

- Every setting change → audit (old_value, new_value, user)
- Settings admin access → audit

## Seed Data

Phase 06 seeds all default settings.

---

**Next**: [26-secret-management.md](26-secret-management.md) for secrets.