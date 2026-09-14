# Phase 04 — Authentication + Sessions + Tokens + Remember-Me + Device Management

## 1. Objective

Implement the full authentication architecture: farmer registration (OTP), staff login, JWT access/refresh for Flutter, session-based auth for web, remember-me tokens, device tracking, 2FA (OTP) enable + two-step login, login history, and session management APIs.

## 2. Prerequisites

- Phase 01-03 (foundation, DB, pipeline, services)
- OTP via OTP gateway config (`config/otp.php`, hardcoded key for now) or log-based OTP in dev until Phase 14 wires live sending

## 3. Features

- Farmer registration: register → OTP verify → complete details
- OTP storage (hashed), expiry, resend, rate limited + OTP templates (register/2fa/reset/mobile-change, en+hi)
- Password login (farmer + staff unified user table)
- **2FA: optional per user; login 2-step password → OTP (verify-2fa); enable/disable with OTP; step-up challenge for sensitive actions**
- JWT access token issuance/validation (15 min)
- Refresh token issuance/rotation/revocation (7 days)
- Web session + CSRF login for staff portal
- Remember-me tokens (30 days, hashed, revocable)
- Device registration + session metadata + OneSignal player_id on user_devices
- Login history (success/failure/reason)
- Session management APIs: list, revoke current/specific/all
- Password change/reset (OTP flow)
- Auth middleware fully wired (JWT or session)

## 4. Files to Create

```
app/Controllers/AuthController.php
app/Controllers/SessionController.php
app/Services/AuthService.php
app/Services/FarmerAuthService.php
app/Services/TokenService.php
app/Services/JwtService.php
app/Services/OtpService.php
app/Services/PasswordResetService.php
app/Services/LoginHistoryService.php
app/Services/SessionService.php
app/Services/RememberTokenService.php
app/Models/User.php (full)
app/Models/UserSession.php
app/Models/RememberToken.php
app/Models/UserDevice.php
app/Models/LoginHistory.php
app/Models/Farmer.php
app/Models/OtpVerification.php
app/Validators/AuthValidator.php
app/Middleware/AuthMiddleware.php (implemented)
routes for auth + sessions
```

## 5. Files to Modify

- `app/Middleware/AuthMiddleware.php` (real JWT/session resolution)
- `config/routes.php` (auth endpoints)
- `.env.example` (JWT keys)

## 6. Database Changes

- Uses: users, farmers, roles, user_sessions, remember_tokens, user_devices, login_history, otp_verifications (created Phase 02)

## 7. API Changes ([07-api-reference.md])

- `POST /auth/register`
- `POST /auth/verify-otp`
- `POST /auth/resend-otp`
- `POST /auth/complete-registration`
- `POST /auth/login`
- `POST /auth/logout`
- `POST /auth/refresh`
- `GET /auth/me`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`
- `POST /staff/auth/login` (web)
- `POST /staff/auth/logout` (web)
- `GET /staff/auth/me` (web)
- `POST /staff/auth/change-password`
- `GET /auth/sessions`
- `POST /auth/sessions/{id}/revoke`
- `POST /auth/sessions/revoke-all`
- `POST /auth/sessions/refresh-current`
- `GET /login-history`

## 8. Backend Logic

- TokenService: `random_bytes`, SHA-256 hash storage, JWT encode/decode (HMAC-SHA256)
- AuthService: credential verify (password_verify), role/status checks, session create, token issue
- Refresh rotation: issue new hashed token, revoke old
- Device registration tied to device_id
- Remember-me independent from refresh token
- Failed login lockout (max_login_attempts, lockout_minutes)
- Logout revokes session + remember token + records logout

## 9. Flutter Changes

- None (Phase 15), but define API contract usage for tokens/sessions

## 10. Staff/Admin Changes

- Portal login page hooks to `/staff/auth/login` (Phase 16 implements UI; this phase the API)

## 11. Permissions

- Manage sessions: Super Admin (+ self for users)
- Login history: self; admin scoped per role (Phase 05 enforces)

## 12. Validation

- AuthValidator rules per endpoint (mobile 10-digit, OTP 6-digit, password min 8 with complexity, confirmation match, etc.)

## 13. Error Handling

- Auth errors map: INVALID_CREDENTIALS, ACCOUNT_PENDING, ACCOUNT_REJECTED, ACCOUNT_LOCKED, INVALID_OTP, OTP_EXPIRED, TOKEN_INVALID, TOKEN_EXPIRED, TOKEN_REVOKED, SESSION_EXPIRED, RATE_LIMITED

## 14. Security

- argon2id password hashing
- OTP hashed (SHA-256), 5 attempts, 5-min expiry, 60s resend
- JWT secret from env; tokens never logged
- Refresh/remember tokens hashed at rest; rotation; revocation
- Cookies: HttpOnly, Secure, SameSite=Lax
- Session regeneration on privilege change
- Tokens never in logs/audit

## 15. Logging/Audit

- login_history entries (success/failure)
- audit: login, logout, password change/reset, session revoke, account lock
- security.log for failures/rate-limits

## 16. Notifications

- OTP SMS via OTP gateway (dev fallback: log OTP; live gateway wired in Phase 14)
- OTP templates: register, 2fa, password_reset, mobile_change (en+hi)

## 17. Configuration Changes

- JWT_ACCESS_EXPIRY (900), JWT_REFRESH_EXPIRY (604800), SESSION_LIFETIME (1800), REMEMBER_ME_EXPIRY (2592000), MAX_LOGIN_ATTEMPTS, LOCKOUT_MINUTES, MAX_CONCURRENT_SESSIONS
- OTP_EXPIRY_MINUTES (5), OTP_RESEND_COOLDOWN_SECONDS (60), OTP_ATTEMPTS_MAX (5), OTP_API_KEY/OTP_SENDER_ID/OTP_TEMPLATE_ID (config/otp.php, hardcoded)

## 18. Dependencies

- None (implement JWT manually with hash_hmac + base64, or add `firebase/php-jwt` if desired; prefer minimal self-implementation)

## 19. Completion Criteria

- [ ] Farmer can register via OTP and complete registration
- [ ] Login issues access+refresh (+remember token when requested)
- [ ] **2FA: enable → confirm → login returns 202 TWO_FA_REQUIRED → verify-2fa → tokens**
- [ ] **2FA 2-step login + disable (with OTP) + step-up challenge verified**
- [ ] Refresh rotation works; old token invalid after use
- [ ] Web staff login with session cookie + CSRF works
- [ ] Logout revokes session + remember token
- [ ] Session list/revoke APIs work
- [ ] Login history records success + failure with reasons
- [ ] Failed-login lockout works
- [ ] Tokens hashed at rest (verify DB)

## 20. Testing Checklist

- [ ] Register happy path (dev OTP)
- [ ] OTP wrong/expired/resend
- [ ] Login success/failure/locked
- [ ] **2FA happy path + wrong 2FA OTP + resend + rate limit**
- [ ] Token expiry (shorten config for test) → refresh
- [ ] Refresh reuse → rejected after rotation
- [ ] Remember-me restores session within 30 days
- [ ] Logout invalidates
- [ ] Revoke session → next request 401/403
- [ ] Multi-device independent sessions
- [ ] CSRF required on web mutation

## 21. What NOT to Implement

- No RBAC decisions (Phase 05)
- No booking/queue/procurement features
- No OneSignal push wiring (Phase 14); OTP uses gateway config / dev log fallback
- No farmer profile CRUD yet

---

**Depends on**: Phase 01-03
**Feeds into**: Phase 05+