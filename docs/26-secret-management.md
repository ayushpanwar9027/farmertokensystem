# Secret & System Configuration Manager

## Overview

Super Admin manages sensitive credentials via a **Secrets Manager**. Secrets are encrypted at rest, masked in UI, restricted, audited, and rotatable.

## Current State: Hardcoded Keys (Initial Release)

For the initial release (development/start) the following are **hardcoded in config files** and NOT yet encrypted:

| File | Keys |
|------|------|
| `config/onesignal.php` | `onesignal_app_id`, `onesignal_rest_api_key` |
| `config/otp.php` | `otp_api_key`, `otp_sender_id`, `otp_template_id` |

This is a deliberate simplification. Later, these move into `system_secrets` (this doc describes that target state) and are removed from config. Until then, `SecretService` falls back to the config values.

## What's a "Secret"

Secrets include:
- OneSignal App ID / REST API key (planned)
- OTP Gateway API key, sender id, template id (planned)
- JWT Secret (could be env or stored encrypted)
- Encryption key bootstrap (env-managed)
- Any credential not meant for normal admins

## Data Model

### system_secrets
```sql
CREATE TABLE system_secrets (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    encrypted_value TEXT NOT NULL,     -- AES-256-GCM ciphertext
    iv VARCHAR(64) NOT NULL,
    is_set TINYINT(1) DEFAULT 0,
    last_rotated_at DATETIME NULL,
    last_updated_by INT NULL,
    description VARCHAR(500) NULL,
    created_at, updated_at
);
```

## Encryption at Rest (AES-256-GCM)

```php
// Encrypt
$cipherText = openssl_encrypt(
    $plaintext,
    'aes-256-gcm',
    $encryptionKey,   // from env, NOT in DB
    OPENSSL_RAW_DATA,
    $iv,              // random 12 bytes
    $tag              // auth tag (16 bytes)
);
// Store base64($iv).base64($tag).base64($cipherText)

// Decrypt
$plaintext = openssl_decrypt($cipherText, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
```

- Key from environment `ENCRYPTION_KEY` (256-bit)
- Never store the encryption key in the same database
- GCM provides authenticated encryption (tamper detection)

## Bootstrap / Encryption Key Management

### Where does the key live?
- In `.env` (file outside web root, not committed/logged)
- Read via `getenv('ENCRYPTION_KEY')` or a config file with restricted permissions

### Initial Setup (Documented, Phase 06)
1. Generate a 256-bit key: `php -r "echo bin2hex(random_bytes(32));"`
2. Place in `.env` as `ENCRYPTION_KEY=...`
3. Restrict .env permissions: `chmod 600 .env`
4. Ensure .env + storage are OUTSIDE web root (or access-denied via .htaccess)
5. Seed system_secrets rows as placeholders (is_set=0)

### Rotation
- Rotation = generate new key + re-encrypt all secrets with new key
- Must be done carefully (downtime window) — re-encrypt all system_secrets
- Document procedure (manual, admin-run script)

### Recovery Implications
- **If encryption key is lost**: all secrets become unreadable (OneSignal/OTP creds etc.)
  - Not a full system failure (data unaffected), but secret-using features (push/SMS) stop
  - Recovery: re-enter secrets via Secrets Manager (admin) after new key set
  - Note: while keys are hardcoded in config, losing the key does not break push/OTP — they still read from config

### Safety
- System must remain recoverable — since data (bookings, etc.) is NOT encrypted (only secrets are), losing the key only requires re-entering credentials, not full restore.

## Masked UI

```html
OneSignal REST API Key
  ••••••••••••••••••
  [Update Secret]  [Rotate]
```

- Never display full stored secret after saving
- Show only `is_set` status + `last_updated_at`/`last_rotated_at`
- Optionally show last 4 chars / first few for identification

## Access Control

| Operation | Who |
|-----------|-----|
| View masked list | Super Admin (`manage_secrets`) |
| Set/update secret | Super Admin |
| Derive/use secret internally | System service (OneSignalService / OtpService) only |
| Rotate | Super Admin |
| Test SMS / Test push | Super Admin |

- `manage_secrets` permission required (Super Admin only by default)
- Never exposed to other roles
- Secrets never in audit logs / application logs / API responses

## Audit

- Secret set/update → audit (key, masked status, not value)
- Secret rotate → audit
- Test SMS / test push → audit + notification log
- Unauthorized secret access attempt → security log

## Internal Use (OneSignalService / OtpService)

```php
// Services/OneSignalService.php
class OneSignalService {
    protected function getConfig(): array {
        // Current: hardcoded in config/onesignal.php (initial release)
        // Later: encrypted system_secrets
        $appId = setting('onesignal_app_id');           // fallback to config
        return [
            'app_id' => $this->secretService->get('onesignal_app_id') ?: $config['onesignal_app_id'],
            'rest_api_key' => $this->secretService->get('onesignal_rest_api_key') ?: $config['onesignal_rest_api_key'],
        ];
    }
}
```

Only decrypted in-memory, within the service call. Never logged.

## Secret Keys (Registry)

| Key | Required | Notes |
|-----|----------|-------|
| onesignal_app_id | Yes (if push) | hardcoded in config for now |
| onesignal_rest_api_key | Yes (if push) | hardcoded in config for now |
| otp_api_key | Yes (if OTP SMS) | hardcoded in config for now |
| otp_sender_id | Optional | hardcoded in config for now |
| otp_template_id | Optional (gateway-dependent) | hardcoded in config for now |
| jwt_secret | Recommended | or env JWT_SECRET |
| encryption_key_bootstrap | Managed via env | never stored here |

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | /secrets | Masked list (Super) |
| PUT | /secrets/{key} | Set/update |
| POST | /secrets/{key}/rotate | Rotate |
| POST | /secrets/send-test-sms | Test OTP gateway |
| POST | /notifications/test-push | Test OneSignal |

## Comparison: Secrets vs Settings

| | system_settings | system_secrets |
|--|-----------------|----------------|
| Content | Non-sensitive config | Sensitive credentials |
| Storage | Plain (typed) | Encrypted (AES-256-GCM) |
| UI | Visible values | Masked |
| Access | manage_system_settings | manage_secrets |
| Example | cancellation window | OneSignal/OTP keys |

---

**Next**: [27-maintenance-mode.md](27-maintenance-mode.md) for maintenance mode.