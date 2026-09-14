# Phase 06 — System Settings + Secret Management + Maintenance Mode

## 1. Objective

Implement system settings (cached, type-safe), encrypted secret management (AES-256-GCM), and maintenance mode for the whole platform.

## 2. Prerequisites

- Phase 03-05 (services, security baseline, RBAC)

## 3. Features

- SettingService: CRUD, caching (file cache), typed accessors (getInt, getBool, getString, getArray), defaults
- Sensitive settings stored encrypted vs plain flag
- SecretService: encrypt/decrypt AES-256-GCM with ENCRYPTION_KEY from env; mask on read
- Secrets never returned in API responses (masked partially)
- Key management notes: ENCRYPTION_KEY stored in .env (and backup), NOT in DB
- Maintenance mode: toggle endpoints (Super Admin), bypass for Super Admin, expected_available_at + message, health status reflects
- Settings validation whitelist

## 4. Files to Create

```
app/Services/SettingService.php
app/Services/SecretService.php
app/Services/CacheService.php (file)
app/Models/SystemSetting.php
app/Models/SystemSecret.php
app/Controllers/Admin/SettingController.php
app/Controllers/Admin/SecretController.php
app/Controllers/Admin/MaintenanceController.php
app/Middleware/MaintenanceMiddleware.php (real logic + secret bypass)
config/settings_defaults.php
```

## 5. Files to Modify

- `app/Middleware/MaintenanceMiddleware.php`
- `config/routes.php` (settings/secret/maintenance endpoints)
- `.env.example` (ENCRYPTION_KEY noted)

## 6. Database Changes

- Uses system_settings, system_secrets (Phase 02)

## 7. API Changes

- `GET /admin/settings` (list, grouping, sensitive masked)
- `PUT /admin/settings` (partial update whitelisted keys)
- `GET /admin/secrets` (keys only + masked values)
- `PUT /admin/secrets` (upsert, accepts plaintext → encrypts)
- `PUT /admin/maintenance` (on/off, expected_available_at, message)
- `GET /health/maintenance` reflects maintenance state

## 8. Backend Logic

- SettingService maintains in-memory cache per request + file cache; invalidation on write; sensitive settings decrypted only for authorized service calls
- Defaults merge: `config/settings_defaults.php` → DB overrides
- SecretService:
  - encrypt: random IV 12 bytes, key from env, `openssl_encrypt(AES-256-GCM)`
  - decrypt: verify tag; store `iv|tag|ciphertext`
  - Failure → SecurityException (no plaintext leak)

## 9. Flutter Changes

- None (Phase 15): maintenance banner + message can be surfaced

## 10. Staff/Admin Changes

- None (Phase 16): UI reads maintenance message

## 11. Permissions

- `settings.view` / `settings.manage` (system:super_admin + district_admin view)
- `settings.secret_manage` (super_admin only)
- `maintenance.manage` (super_admin only)
- Sign-in page still reachable during maintenance for super admin

## 12. Validation

- Whitelist keys; value types enforced by key schema
- Reject unknown setting keys
- Secret keys allowlist sanitized (alnum+underscore)

## 13. Error Handling

- Invalid/encryption key → SECURITY_ERROR 500 (no stack)
- Unknown key → 400
- Secret tamper (tag mismatch) → 500 + security.log

## 14. Security

- AES-256-GCM not ECB; unique IV per encryption
- ENCRYPTION_KEY out of DB; 512-bit min note
- Values masked in all responses: `{ key, exists, last4 }` for secret-type
- Never log decrypted values
- Maintenance bypass only for super_admin role (checked each request)

## 15. Logging/Audit

- audit: settings change, secret change, maintenance toggle
- security.log: secret decrypt failures / tamper

## 16. Notifications

- Optional notify super admins on maintenance toggle (Phase 14 hook)

## 17. Configuration Changes

- `.env` ENCRYPTION_KEY documented; rotation procedure note (decrypt→re-encrypt all records)

## 18. Dependencies

- None (openssl ext)

## 19. Completion Criteria

- [ ] Settings CRUD with validation + cache
- [ ] Setting types respected (int/bool)
- [ ] Secret encrypt/decrypt round-trip; masked on API
- [ ] Tampered ciphertext rejected
- [ ] Maintenance toggle works; non-super users blocked with message; /health still up
- [ ] Defaults present via seeder

## 20. Testing Checklist

- [ ] Update a setting → reflected in subsequent API (cache invalidated)
- [ ] Unknown key → 400
- [ ] Secret write → read shows masked
- [ ] Flip ciphertext byte → decrypt fails safely
- [ ] Maintenance ON → public API 503 + message; super_admin 200
- [ ] /health/maintenance shows ON with expected_available_at

## 21. What NOT to Implement

- No OneSignal/OTP gateway sending yet (Phase 14); secrets placeholders note: keys hardcoded in config for now
- No booking/queue/procurement
- No language/file management (Phase 07)
- No key rotation UI (document procedure only)

---

**Depends on**: Phase 03-05
**Feeds into**: Phase 07+