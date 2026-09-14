# Code Revision — 2FA (OTP) + OneSignal Push Migration for Phases 01-13

## 1. Objective

Backfill the already-implemented Phase 01-13 code so it matches the updated docs decision set:

- **2FA (OTP)**: optional per-user; login is 2-step (password → OTP); enable/disable/step-up flows; OTP sent via a dedicated **OTP SMS gateway**.
- **Notifications**: push notifications via **OneSignal** + **in-app**; **OTP SMS is a separate path** (OTP gateway), not part of the notification system.
- **Keys hardcoded in config for now** (`config/onesignal.php`, `config/otp.php`); later moved to encrypted `system_secrets` (documented as target state).

Remove every Twilio reference from application code/config/seed/test scripts. Do **not** build the full event-dispatch/retry engine or admin/portal/Flutter UI here (that is Phase 14/16/17 scope) — but make the touched code consistent with the new enum/state model so nothing breaks when Phase 14 lands.

## 2. Design Reference Docs (source of truth)

- [22-notification-system.md](22-notification-system.md) — OneSignal push + in-app, OTP gateway, OTP templates, data models, send/retry flow
- [11-authentication.md](11-authentication.md) — 2FA login flow (2a), OTP templetes, enable/disable, step-up, endpoints, audit events
- [09-database-schema.md](09-database-schema.md) — schema: `users.two_factor_*`, `user_devices.onesignal_player_id`, enum changes, settings seed, secrets
- [25-system-settings.md](25-system-settings.md) — NOTIFICATION group additions
- [26-secret-management.md](26-secret-management.md) — OneSignal/OTP secret keys + hardcoded-config current state
- [06-api-architecture.md](06-api-architecture.md) / [07-api-reference.md](07-api-reference.md) — `TWO_FA_REQUIRED`/`INVALID_2FA` error codes, 2FA + devices endpoints

## 3. Config Files

### 3.1 New: `config/onesignal.php`
```php
return [
    'onesignal_app_id'       => env('ONESIGNAL_APP_ID', 'your-onesignal-app-id'),
    'onesignal_rest_api_key' => env('ONESIGNAL_REST_API_KEY', 'your-onesignal-rest-api-key'),
];
```
(Hardcoded placeholders for now; OneSignal HTTP calls are NOT made in this revision — Phase 14 wires the REST send.)

### 3.2 New: `config/otp.php`
```php
return [
    'otp_api_key'      => env('OTP_API_KEY', 'your-otp-gateway-api-key'),
    'otp_sender_id'    => env('OTP_SENDER_ID', 'FPS'),
    'otp_template_id'  => env('OTP_TEMPLATE_ID', 'your-otp-template-id'),
];
```
(Hardcoded placeholders for now; live HTTP send wired in Phase 14. Dev fallback = log OTP.)

### 3.3 `config/config.php`
- Remove nothing used elsewhere, but add:
  - `'otp_gateway'` => load `config/otp.php` values (api_key, sender_id, template_id) under one block
  - `'onesignal'` => load `config/onesignal.php` values
- Keep the existing generic `'otp'` block (length/expiry/cooldown). Expiry/cooldown default to the new settings: `otp_expiry_minutes` (5), `otp_resend_cooldown_seconds` (60).

### 3.4 `.env.example` and `.env`
Remove:
```
SMS_PROVIDER=twilio
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=
```
Add:
```
ONESIGNAL_APP_ID=your-onesignal-app-id
ONESIGNAL_REST_API_KEY=your-onesignal-rest-api-key
OTP_API_KEY=your-otp-gateway-api-key
OTP_SENDER_ID=FPS
OTP_TEMPLATE_ID=your-otp-template-id
```

## 4. Database Schema (new migration batch)

Create a migration with this batch (single up/down unit is fine, one file per concern preferred):

1. **users**: add `two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0` and `two_factor_enabled_at DATETIME NULL DEFAULT NULL` (after `mobile_verified_at`).
2. **user_devices**: add `onesignal_player_id VARCHAR(190) NULL DEFAULT NULL`; no unique constraint (one row per device).
3. **otp_verifications.purpose**: change to
   `ENUM('REGISTER','LOGIN','LOGIN_2FA','PASSWORD_RESET','MOBILE_CHANGE','2FA_ENABLE','2FA_STEP_UP')`.
   Existing rows with `'REGISTRATION'` → `'REGISTER'`, `'LOGIN'` stays.
4. **notifications.channel**: change to `ENUM('PUSH','IN_APP','BOTH')`.
5. **notification_logs.channel**: change to `ENUM('PUSH','IN_APP','SMS')`; widen `recipient` to `VARCHAR(190)` (player id for PUSH, mobile for SMS).

Apply via the app migration runner; rollback must reverse to previous values.

## 5. Seeders & Settings

### 5.1 `database/seeders/secrets_seeder.php`
Replace the 4 Twilio rows with:
```php
['key' => 'onesignal_app_id',       'desc' => 'OneSignal app ID (push)'],
['key' => 'onesignal_rest_api_key', 'desc' => 'OneSignal REST API key (push)'],
['key' => 'otp_api_key',            'desc' => 'OTP gateway API key (SMS)'],
['key' => 'otp_sender_id',          'desc' => 'OTP gateway sender ID'],
['key' => 'otp_template_id',        'desc' => 'OTP gateway template ID'],
```
Keep `jwt_secret`, `encryption_key_bootstrap`. All `is_set=0` placeholders stay (config supplies working values for now). Also sync `settings_seeder.php` and any other seed that touches notification/secret keys.

### 5.2 Settings (config/settings_defaults.php + `settings_seeder.php` + any settings seed)
Add under `NOTIFICATION` group:
- `push_enabled` — BOOL, default true — "Enable OneSignal push notifications"
- `otp_expiry_minutes` — INT, default 5
- `otp_resend_cooldown_seconds` — INT, default 60
- `two_factor_enabled_default` — BOOL, default false — "Default 2FA state for new users"

Keep `sms_enabled` (it now gates OTP-gateway SMS sending for OTP only). Re-seed so DB settings table gains the 4 new keys.

## 6. Services

### 6.1 `app/Services/NotificationService.php`
- Delete `sendTwilio()` + all TWILIO reads.
- `sendOtp($mobile, $otp, $purpose)`:
  - Purpose comes in as the new enum value (`REGISTER`, `LOGIN_2FA`, `PASSWORD_RESET`, `MOBILE_CHANGE`, `2FA_ENABLE`, `2FA_STEP_UP`).
  - Map purpose → OTP template id (`otp.register`, `otp.login_2fa`, `otp.password_reset`, `otp.mobile_change`; `2FA_ENABLE`/`2FA_STEP_UP` use `otp.login_2fa` styling).
  - Compose message via `OtpTemplateService` (section 8) using the user's locale (en/hi default `notification_language_default`).
  - Send through OTP gateway config (`config/otp.php`); message stays log-based (dev) until Phase 14 wires HTTP; return true.
  - `event_type` in the `notification_logs` dev insert = the **template id** (lowercase dotted, e.g. `otp.login_2fa`), not `strtoupper($purpose)`.
  - Channel for OTP log row stays `SMS`.
- Keep `log()` helper. Do not add OneSignal send methods here yet (deferred to Phase 14, but a stub `sendPush()` returning false + WARN log is acceptable).

### 6.2 `app/Services/SecretService.php`
Registry: remove the 4 `twilio_*` entries, add the 5 new keys from 5.1 (matching descriptions). Nothing else changes.

### 6.3 `app/Services/OtpService.php`
- Read `expirySeconds` from setting `otp_expiry_minutes` (×60) and `resendCooldown` from `otp_resend_cooldown_seconds`; fall back to existing env/defaults.
- Use the new purpose enum values in generation (callers pass them).
- `verify()` unchanged (hash_sha256 with salt, attempts, expiry).
- No rate-limit change to the mobile-keyed limiter; the 2FA-per-user limiter is implemented in the new endpoints (section 7).

### 6.4 `app/Services/AuthService.php` — 2FA login branch
In `appLogin()` (and web login path), after password + status checks (`checkStatus`) and **before** `establishSession()`:

- If `(int)$user['two_factor_enabled'] === 1`:
  1. generate OTP with purpose `LOGIN_2FA` (via `OtpService::generate($mobile, 'LOGIN_2FA')`);
  2. record login history **SUCCESS (step 1)** with event `TWO_FA_REQUIRED`;
  3. return a pending result `{ two_factor_required: true, verification_id, resend_after, expires_in }` — do **not** create session/tokens.
- Else proceed exactly as today.

Add methods:
- `verify2fa(string $verificationId, string $otp, array $context)`: call `OtpService::verify(...)` (or a focused LOGIN_2FA verify), then run the existing `establishSession()` path and record history SUCCESS. Throw `INVALID_2FA` on wrong OTP (per docs) and record history FAILURE event `INVALID_2FA`; failed 2FA attempts count toward lockout policy (5 per 15 min).
- `enable2fa(int $userId)`: send `2FA_ENABLE` OTP, return verification_id.
- `confirmEnable2fa(int $userId, string $verificationId, string $otp)`: verify OTP → set `two_factor_enabled=1`, `two_factor_enabled_at=now`; audit.
- `disable2fa(int $userId, string $verificationId, string $otp)`: require a fresh `2FA_ENABLE`/step-up OTP verify → set `two_factor_enabled=0`; audit.
- `challenge2fa(int $userId)`: issue `2FA_STEP_UP` OTP → return verification_id.
- `confirmStepUp(int $userId, string $verificationId, string $otp)`: verify → return a short-lived step-up claim (signed token / DB claim row with expiry, e.g. step_up_expiry from settings or 10 min); audit.

Wire both mobile (JWT) and web (session) login behind the same 2FA branch.

### 6.5 `app/Services/LoginHistoryService.php`
Support the two new events so they persist correctly:
- `TWO_FA_REQUIRED` — SUCCESS (step 1), reason/event column = `TWO_FA_REQUIRED`
- `INVALID_2FA` — FAILURE reason

### 6.6 `app/Services/FarmerAuthService.php`
Registration OTP purpose changes from `REGISTRATION` to `REGISTER` (align with new enum). Password-reset flow already uses `PASSWORD_RESET`.

### 6.7 (New) `app/Services/OtpTemplateService.php`
Loads `resources/templates/otp/*.php` (section 8) and renders with placeholders: app name, otp, expiry. Returns text appropriate for en/hi. Used by `NotificationService::sendOtp`.

## 7. Controllers, Routes, Endpoints

### 7.1 `app/Controllers/AuthController.php` (+ `SessionController.php` for devices)
Methods (all API):
- `verify2fa(Request)` — body `{verification_id, otp}` → tokens (200) or error (401 `INVALID_2FA` / `OTP_EXPIRED` / 429)
- `resend2fa(Request)` — body `{verification_id}` → `{resend_after}`; 429 `OTP_RESEND_COOLDOWN`
- `enable2fa(Request)` — auth — issues enable OTP → `{verification_id, resend_after}`
- `confirmEnable2fa(Request)` — auth — `{verification_id, otp}` → `{two_factor_enabled: true}`
- `disable2fa(Request)` — auth — `{verification_id, otp}` → `{two_factor_enabled: false}`
- `challenge2fa(Request)` — auth — issues step-up OTP → `{verification_id}`
- `confirmStepUp(Request)` — auth — `{verification_id, otp}` → `{step_up_claim, expires_in}`
- `registerDevice(Request)` — auth — `{device_id, device_name, platform, onesignal_player_id}` upsert row in `user_devices` (player id optional for now, required once OneSignal live)
- Web: `POST /web/verify-2fa` (session-based 2nd step) reusing the same service logic.

### 7.2 `config/routes.php`
Add (mirroring existing auth route style):

```
POST /api/v1/auth/verify-2fa       → AuthController@verify2fa
POST /api/v1/auth/resend-2fa       → AuthController@resend2fa
POST /api/v1/auth/2fa/enable       → AuthController@enable2fa
POST /api/v1/auth/2fa/enable/confirm → AuthController@confirmEnable2fa
POST /api/v1/auth/2fa/disable      → AuthController@disable2fa
POST /api/v1/auth/2fa/challenge    → AuthController@challenge2fa
POST /api/v1/auth/2fa/confirm      → AuthController@confirmStepUp
POST /api/v1/auth/devices          → SessionController@registerDevice (or AuthController)
POST /web/verify-2fa              → AuthController@webVerify2fa (session stack)
```

`/api/v1/auth/login` response contract change handled in controller: 200 normal / **202 `TWO_FA_REQUIRED`** when 2FA per docs.
`/api/v1/auth/me` now includes `two_factor_enabled`.

### 7.3 Validators (`app/Validators/AuthValidator.php`)
Add rules: `verify2fa` (mobile not needed; verification_id + 6-digit OTP), `resend2fa`, `enable2fa` (none / auth), `registerDevice` (device_id required, onesignal_player_id optional string ≤190).

## 8. OTP Templates

Create `resources/templates/otp/` with template files (en + hi variants via `translations`-style lookup or PHP view files). Placeholders: `%appname%`, `%otp%`, `%expiry%`.

| Template | Purpose | en sample |
|----------|---------|-----------|
| `otp.register` | Registration | "Your %appname% verification OTP is %otp%. Valid %expiry% min. Do not share." |
| `otp.login_2fa` | 2FA login / enable / step-up | "Your %appname% login OTP is %otp%. Valid %expiry% min. Do not share." |
| `otp.password_reset` | Password reset | "Your %appname% password reset OTP is %otp%. Valid %expiry% min. Do not share." |
| `otp.mobile_change` | Mobile number change | "Your %appname% mobile change OTP is %otp%. Valid %expiry% min. Do not share." |

Provide Hi variants. Renderer: `OtpTemplateService` (6.7). Templates are data-driven strings; no SMS sending in this revision.

## 9. Event Enqueue Touch-Points (make enum-consistent now)

These already INSERT `notification_logs` with channel `'SMS'` for **non-OTP events**. Change the literal `'SMS'` → `'IN_APP'` (in-app is authoritative now; PUSH log rows + OneSignal send are appended by Phase 14 dispatch):

- `app/Services/QueueService.php::notifyForEntry` (events `QUEUE_APPROACHING`, `QUEUE_CALLED`, etc.)
- `app/Services/PaymentService.php::enqueueEvent` (events `payment_released`, `payment_reversed`)
- `app/console/cron.php` QUEUE_APPROACHING insert

Keep `recipient` as the mobile for now (player-id targeting ships with OneSignal in Phase 14). NotificationService dev-OTP insert keeps `channel='SMS'` (correct — it is OTP).

## 10. Housekeeping — scripts/tests referencing Twilio

- `app/console/phase06_e2e_reset.php`: `'twilio_from_number'` → `'otp_api_key'`.
- `app/console/phase06_unit_test.php`: secret get/set test key `'twilio_from_number'` → `'otp_api_key'` (update assertions/comments accordingly).
- Sweep: `rg -n "twilio|Twilio|TWILIO|SMS_PROVIDER" .` must return zero non-doc hits.

## 11. Rate Limiting & Security

- 2FA OTP verify (verify-2fa / 2fa enable/disable) — rate limit **3 per 5 min per user** (DB-backed RateLimiter, key `2fa:<user_id>`).
- Failed 2FA OTP attempts increment the user's failed-attempt counter used by lockout policy (5/15 min).
- OTPs never logged to application/API/security logs; only dev `notification.log` may carry the value when `APP_ENV != production`.
- Audit entries for 2FA enable/disable/step-up per [11-authentication.md §OTP & 2FA] (audit + security log; never the OTP value itself).

## 12. What NOT to Implement in this Revision

- No live HTTP to OneSignal or the OTP gateway (Phase 14 wires these; stubs return log-fallback + warn).
- No full `NotificationService::dispatch()` event engine / retry cron (Phase 14 + Phase 35 revisit).
- No `GET /admin/notifications`, `POST /admin/notifications/test-push`, `/notifications/test-push` UI or summary (Phase 14/16).
- No Flutter app changes (Phase 15), no portal 2FA/settings screens (Phase 16).
- No `notifications`/in-app row creation from events yet (Phase 14 dispatch does this).
- No RBAC/role changes for 2FA.

## 13. Completion Checklist

- [ ] `rg -i "twilio|SMS_PROVIDER" $(rg -l '' -g '*.php' -g '*.env*')` → zero application hits (docs only).
- [ ] `config/onesignal.php`, `config/otp.php` exist and load via `config/config.php`.
- [ ] `.env` + `.env.example` have `ONESIGNAL_*` / `OTP_*`, no TWILIO.
- [ ] Migration applied: `users.two_factor_enabled`, `user_devices.onesignal_player_id`, purpose/channel enums, `notification_logs.recipient` VARCHAR(190).
- [ ] Secrets seeded: 5 new keys (is_set=0), no `twilio_*`.
- [ ] Settings seeded: `push_enabled`, `otp_expiry_minutes`, `otp_resend_cooldown_seconds`, `two_factor_enabled_default`.
- [ ] `login` returns 202 `TWO_FA_REQUIRED` + verification_id for a 2FA user (or 200 otherwise).
- [ ] `verify-2fa` happy path grants tokens; wrong OTP → `INVALID_2FA`; expired → `OTP_EXPIRED`; 3/5min per-user limit enforced.
- [ ] `2fa/enable` → `confirm` sets flag (checked in DB); `2fa/disable` requires fresh OTP; `2fa/challenge`+`confirm` returns short-lived claim.
- [ ] `POST /auth/devices` upserts device row incl. `onesignal_player_id`.
- [ ] Login history rows show `TWO_FA_REQUIRED` and `INVALID_2FA`.
- [ ] `rg "twilio|TWILIO" app config database .env*` clean; `phase06_*` scripts pass.

## 14. Testing Checklist

- [ ] Register OTP still works (purpose `REGISTER`) via API; no regressions in Phase 04 flows.
- [ ] Login without 2FA → tokens immediately (200).
- [ ] 2FA login: 202 → verify-2fa wrong/expired/resend/cooldown/rate-limit → correct OTP → 200 tokens.
- [ ] 2FA enable → confirm → next login requires OTP. Disable asks fresh OTP first.
- [ ] Step-up challenge on sensitive action path returns claim; claim invalid after expiry.
- [ ] Lockout: 5 failed 2FA attempts lock account 15 min.
- [ ] `POST /auth/devices` with player id persists to `user_devices`.
- [ ] Queue/payment event rows now `channel='IN_APP'` (DB spot-check on a booking→call→release run).
- [ ] OTP templates render en + hi text with placeholders substituted (dev log shows final message).
- [ ] Re-run Phase 06 unit/e2e scripts (updated) and a smoke of Phase 13 by re-running its verify script.