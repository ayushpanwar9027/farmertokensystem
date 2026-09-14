# Authentication Architecture

## Overview

Two distinct authentication mechanisms:
1. **Web Portal (Staff/Admin)** - Session-based with CSRF protection
2. **Flutter App (Farmer)** - JWT-based with refresh tokens and device binding

Both are enforced server-side on the same PHP backend.

**Two-Factor Authentication (2FA)**: All users (farmer + staff/admin) can enable 2FA via **OTP** (one-time password sent by SMS through the OTP gateway). When enabled, login completes in two steps: password → OTP verification. Higher-privilege actions can require a fresh 2FA OTP (step-up). See [OTP & 2FA](#otp--2fa) below.

## Authentication Strategy Comparison

| Aspect | Web Portal (Staff) | Flutter App (Farmer) |
|--------|--------------------|----------------------|
| Storage | Server session (DB-backed) | Client holds JWT |
| Token type | Session ID (cookie) | JWT access + refresh |
| Token validation | DB lookup each request | JWT signature verification |
| Expiry | 30 min inactivity (configurable) | Access 15 min, Refresh 7 days |
| Remember-me | Optional 30-day token | Optional remember token |
| CSRF | Required (double-submit cookie) | N/A (same-origin not applicable, but validate origin) |
| Multi-device | Multiple sessions supported | Multiple JWT sessions supported |
| Revocation | Delete session row | Revoke refresh token / session record |
| Stateless? | No (DB-backed) | Access token stateless; refresh DB-backed |

## Farmer (Flutter) Authentication Flow

### 1. Registration (OTP) Flow

```
Farmer App                     PHP API                    MySQL
   │                              │                          │
   ├─ POST /auth/register ──────▶│                          │
   │  {mobile, device_id}        │                          │
   │                              ├─ Check mobile exists     │
│                              ├─ Generate OTP (6-digit)  │
    │                              ├─ Store OTP hash          │
    │                              ├─ Save in otp_verifications│
    │                              ├─ Send OTP via SMS gateway ─▶│ (async SMS via OTP Gateway)
    │                              │                          │
    │◀── {verification_id} ────────┤                          │
    │                              │                          │
    │  (user receives SMS with OTP,│                          │
    │   using otp.register template)│                         │
   │                              │                          │
   ├─ POST /auth/verify-otp ─────▶│                          │
   │  {verification_id, otp}      │                          │
   │                              ├─ VERIFY OTP hash +expiry │
   │                              ├─ Mark mobile_verified    │
   │                              ├─ Return registration_token│
   │◀── {registration_token} ─────┤                          │
   │                              │                          │
   ├─ POST /auth/complete-registration ─▶                    │
   │  {registration_token, name, ├─ Validate token           │
   │   password, details...}     ├─ Hash password (argon2id) │
   │                              ├─ CREATE users (PENDING)  │
   │                              ├─ CREATE farmers (PENDING)│
   │                              ├─ Create session          │
   │◀── {farmer, message} ────────┤  (verification pending)  │
   │                              │                          │
```

### 2. Login Flow (Password)

```
Farmer App                     PHP API                    MySQL
   │                              │                          │
   ├─ POST /auth/login ──────────▶│                          │
   │ {mobile, password,           │                          │
   │  device_id, remember_me}     │                          │
   │                              ├─ Find user by mobile     │
   │                              ├─ Verify password (argon2id)│
   │                              ├─ Check role == FARMER    │
   │                              ├─ Check status ACTIVE     │
   │                              ├─ Check verification APPROVED│
   │                              ├─ Record login_history    │
   │                              ├─ Check two_factor_enabled│
   │                              │  └─ Yes → issue login_step│
   │                              │     token + send 2FA OTP │
   │                              │     (template otp.login_2fa)│
   │                              │      return 202 {2fa_required, verification_id}│
   │                              └─ No  → continue below    │
   │                              ├─ Create user_session     │
   │                              │  (store hashed token)    │
   │                              ├─ Generate JWT access (15m)│
   │                              ├─ Generate refresh token  │
   │                              ├─ Generate remember token (if requested)│
   │◀── {access, refresh, remember, user, permissions} ──────│
   │                              │                          │
```

### 2a. 2FA OTP Verification (when two_factor_enabled)

```
Farmer App                     PHP API                    MySQL
   │                              │                          │
   ├─ POST /auth/verify-2fa ─────▶│                          │
   │ {login_step_token,           │                          │
   │  verification_id, otp}       │                          │
   │                              ├─ Verify login_step token │
   │                              ├─ Verify OTP (hash+expiry)│
   │                              ├─ Mark OTP consumed       │
   │                              ├─ Create user_session     │
   │                              │  (store hashed token)    │
   │                              ├─ Generate JWT access (15m)│
   │                              ├─ Generate refresh token  │
   │                              ├─ Generate remember token (if requested)│
   │◀── {access, refresh, remember, user, permissions} ──────│
   │                              │                          │
```

### 3. Token Refresh Flow

```
Farmer App                     PHP API                    MySQL
   │                              │                          │
   ├─ POST /auth/refresh ────────▶│                          │
   │  {refresh_token}             │                          │
   │                              ├─ Look up refresh token   │
   │                              │  (hashed) in user_sessions│
   │                              ├─ Check status ACTIVE     │
   │                              ├─ Check expiry            │
   │                              ├─ Generate new access     │
   │                              ├─ Rotate refresh token    │
   │                              ├─ Update last_activity    │
   │◀── {new_access, new_refresh}─┤                          │
   │                              │                          │
```

### 4. Logout Flow

```
Farmer App                     PHP API                    MySQL
   │                              │                          │
   ├─ POST /auth/logout ─────────▶│                          │
   │  {refresh_token}             │                          │
   │                              ├─ Revoke session row      │
   │                              ├─ Revoke related remember token│
   │                              ├─ Record logout in login_history│
   │◀── {message: "Logged out"} ──┤                          │
   │                              │                          │
   │  (client clears local tokens)│                          │
```

## Staff (Web) Authentication Flow

### Login

```
Browser                     PHP API                     MySQL
   │                              │                          │
   │  POST /staff/auth/login      │                          │
   │  {username/mobile, password} │                          │
   ├─────────────────────────────▶│                          │
   │                              ├─ Find staff user         │
   │                              ├─ Verify password         │
   │                              ├─ Check role is staff     │
   │                              ├─ Check status ACTIVE     │
   │                              ├─ Start PHP session       │
   │                              ├─ Create user_session row │
   │                              │  (session token hash)    │
   │                              ├─ Set session cookie      │
   │                              │  (HttpOnly, Secure, SameSite)│
   │                              ├─ Record login_history    │
   │◀── {user, permissions, scope}│                          │
   │                              │                          │
   │  (browser stores PHPSESSID   │                          │
   │   cookie automatically)      │                          │
```

### Session Validation (every request)

```
Browser                     PHP API                     MySQL
   │  GET /api/v1/... with PHPSESSID │                      │
   ├─────────────────────────────▶│                          │
   │                              ├─ Start PHP session       │
   │                              ├─ Get session token       │
   │                              ├─ Hash it                │
   │                              ├─ Look up user_sessions   │
   │                              ├─ Check status ACTIVE     │
   │                              ├─ Check expiry            │
   │                              ├─ Check inactivity < 30min│
   │                              ├─ Update last_activity    │
   │                              │  (throttled - every 60s) │
   │                              ├─ Load user + permissions │
   │                              ├─ Verify CSRF on mutations│
   │◀── {data} ───────────────────┤                          │
```

### Logout

```
Browser                     PHP API
   │  POST /staff/auth/logout     │
   ├─────────────────────────────▶│
   │                              ├─ Revoke session row
   │                              ├─ Destroy PHP session
   │                              ├─ Clear session cookie
   │                              ├─ Record logout in login_history
   │◀── {message} ────────────────┤
   │                              │
   │  (client redirects to /login)│
```

## Token/Session Design

### Flutter Access Token (JWT)

```php
// JWT payload
{
  "sub": 101,           // user_id
  "role": "FARMER",
  "session_id": 8001,   // FK to user_sessions
  "device_id": "fps_1234",
  "iat": 1725811200,    // issued at
  "exp": 1725812100,    // 15 min expiry
  "jti": "unique_jwt_id"
}
```

- Signed with HMAC-SHA256 using JWT_SECRET
- NOT stored in DB (stateless validation)
- Contains session_id for revocation checks
- Short expiry (15 min) minimizes risk window
- Never carry sensitive data in payload

### Refresh Token (Flutter)

- Long random string (64+ chars)
- Stored in `user_sessions` table as SHA-256 hash
- Linked to device + user
- Expiry: 7 days (configurable)
- Rotated on each refresh (old revoked, new issued)
- Revocable individually

### Web Session Token

- Stored in PHP session + `user_sessions` table (hashed)
- Cookie: `PHPSESSID` (HttpOnly, Secure, SameSite=Lax)
- DB-backed so revocable and queryable
- Expiry: 30 min inactivity (configurable)
- Extended on active use

### Remember-Me Token

- Independent, long random string (128 chars)
- Stored in `remember_tokens` table as SHA-256 hash
- Separate from refresh token
- Expiry: 30 days (configurable)
- Revocable individually
- Used only to auto-extend/restore session — NOT equivalent to full auth
- Invalidate on logout

## Password Storage

```php
// Always use password_hash with default algorithm (argon2id in PHP 8.1+)
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verify
$valid = password_verify($password, $hash);

// Rehash if algorithm changed
if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
    // update in DB
}
```

## OTP Storage

```php
// Never store raw OTP
$otp = random_int(100000, 999999);
$otpHash = hash('sha256', $otp);
// Store $otpHash with expiry + attempt counter
```

- 6-digit OTP
- Expiry: 5 minutes
- Max 5 attempts per verification_id
- Resend cooldown: 60 seconds
- Rate limited: 3 per 5 minutes per mobile

## OTP & 2FA

### OTP Gateway (config)

OTP SMS is sent through a dedicated **OTP/SMS gateway** (e.g., MSG91-style HTTP API). The API key is **hardcoded in `config/otp.php` for now** (see [26-secret-management.md](26-secret-management.md) for the planned move to encrypted secrets).

```php
// config/otp.php (hardcoded for now)
return [
    'otp_api_key'     => '<OTP_API_KEY>',
    'otp_sender_id'   => '<SENDER_ID>',
    'otp_template_id' => '<TEMPLATE_ID>', // if gateway enforces registered templates
];
```

### OTP Templates

Landing messages use registered templates so the SMS is deliverable (DND-safe). Stored in `resources/templates/otp/` with `{en}` / `{hi}` variants:

| Template Key | Purpose | en text (example) |
|--------------|---------|-------------------|
| `otp.register` | Registration (section 1) | "Your OTP for {app} registration is {otp}. Valid {expiry} min." |
| `otp.login_2fa` | 2FA login (section 2a) | "Your login OTP for {app} is {otp}. Valid {expiry} min. Do not share." |
| `otp.password_reset` | Password reset | "Your OTP to reset password for {app} is {otp}. Valid {expiry} min." |
| `otp.mobile_change` | Change mobile number | "Your OTP to change mobile for {app} is {otp}. Valid {expiry} min." |

- Template service resolves `{app}`, `{otp}`, `{expiry}`, `{name}`, etc.
- Falls back to English if the chosen `{hi}` variant is missing (matching the language-system fallback rule).
- The gateway-side template text must match the app-side template placeholders.

### 2FA Enable / Disable

- A user enables 2FA in the app; staff/users in the web portal.
- `users.two_factor_enabled` = 1. OTP for enabling is sent with template `otp.mobile_change`-style step (enable flow re-verifies mobile).
- Disabling 2FA requires a fresh <a href="#2fa-otp-verification">2FA OTP</a> to be verified (step-up proof) + audit.

### Step-up (sensitive actions)

Sensitive actions (bank/payment details change, mobile change, permission changes, secret updates) may require a **fresh 2FA OTP** even if the session is valid. `POST /auth/2fa/challenge` issues an OTP; `POST /auth/2fa/confirm` verifies it and returns a short-lived step-up claim.

### OTP Endpoints (API Reference)

| Method | Path | Purpose |
|--------|------|---------|
| POST | /auth/register | Request registration OTP (template `otp.register`) |
| POST | /auth/verify-otp | Verify registration/phone OTP |
| POST | /auth/resend-otp | Resend (cooldown 60s, rate limited) |
| POST | /auth/login | Step 1 (password) — returns 202 `{2fa_required}` if enabled |
| POST | /auth/verify-2fa | Step 2 (OTP) — returns tokens |
| POST | /auth/resend-2fa | Resend 2FA OTP |
| POST | /auth/2fa/enable | Issue enable OTP |
| POST | /auth/2fa/enable/confirm | Verify enable OTP → set two_factor_enabled=1 |
| POST | /auth/2fa/disable | Verify OTP → set two_factor_enabled=0 |
| POST | /auth/2fa/challenge | Issue fresh OTP for step-up |
| POST | /auth/2fa/confirm | Verify step-up OTP → claim |
| POST | /auth/password-reset | Request reset OTP (template `otp.password_reset`) |
| POST | /auth/password-reset/confirm | Verify OTP + set new password |
| POST | /auth/mobile-change | Request OTP for new mobile (template `otp.mobile_change`) |
| POST | /auth/mobile-change/confirm | Verify new-mobile OTP + old-mobile OTP |

### 2FA Flow Diagram

```
POST /auth/login  (password)
        │
        ├── two_factor_enabled?
        │       ├── No  → issue tokens immediately
        │       └── Yes → generate OTP, send via gateway (otp.login_2fa)
        │                  return 202 {2fa_required: true, verification_id}
        ▼
POST /auth/verify-2fa {verification_id, otp}
        │
        ├── OTP valid & within expiry & attempts < 5?
        │       ├── No  → 401/422 (retry, or resend after cooldown)
        │       └── Yes → consume OTP, create session, issue tokens
        ▼
   {access, refresh, remember, user, permissions}
```

### OTP & Token Security Notes

- OTPs stored as SHA-256 hashes only (never raw); OTP never logged.
- `login_step_token` is short-lived (5 min) and single-use; it only authorizes OTP verification, not full API access.
- 2FA OTP send/verify recorded in security log + `login_history` (event `2FA_OTP`).
- Failed 2FA attempts count toward the account lockout policy (5/15 min → temporary lock).

## Security Measures

### For Web Sessions
- HttpOnly cookie (prevents XSS token theft)
- Secure flag (HTTPS only)
- SameSite=Lax (CSRF mitigation)
- Session rotation on privilege change
- Regenerate session ID after login

### For Flutter JWT
- Tokens always sent via HTTPS
- Device ID binding (claims checked)
- Session revocation check on each request
- Short-lived access tokens
- Refresh token rotation
- Stored in Secure Storage (encrypted)

### For Remember-Me
- Independent tokens, hashed at rest
- Individual revocation
- Logout invalidates
- Not sufficient alone for high-privilege actions
- Sensitive actions may require re-authentication

## Authentication Middleware

```php
// app/Middleware/AuthMiddleware.php
class AuthMiddleware implements MiddlewareInterface {
    private AuthService $authService;

    public function handle(Request $request, callable $next) {
        // 1. Try Bearer token (Flutter)
        $token = $request->getBearerToken();
        if ($token) {
            $user = $this->authService->validateJwt($token);
            if ($user) {
                $request->setUser($user);
                return $next($request); // JWT validated
            }
            return Response::unauthorized('Invalid or expired token');
        }

        // 2. Try session (Web)
        $sessionUser = $this->authService->resolveSessionUser();
        if ($sessionUser) {
            $request->setUser($sessionUser);
            return $next($request); // Session validated
        }

        return Response::unauthorized('Authentication required');
    }
}
```

## Multi-Device / Multi-Session Support

- Each login creates a new `user_sessions` row
- Earlier sessions remain active (unless revoked)
- Farmer sees all their active sessions in "Session Management"
- Super Admin can view/manage staff sessions
- Revoke specific session or all sessions
- Max concurrent sessions limit (configurable, e.g., 10) — oldest auto-expired

## Failed Login Handling

```php
// Login attempts
- Track failed logins per mobile + IP in login_history
- After 5 failures per 15 min → temporary lock (429)
- After 10 failures → LOCKED status, require admin unlock
- Audit security log for failure spikes
```

## Session Expiry Policy

| Context | Timeout | Renewal |
|---------|---------|---------|
| Web session (inactive) | 30 min | Auto-renew on activity |
| Web remember-me | 30 days | Auto-renew on each login |
| Flutter access token | 15 min | Auto-refresh |
| Flutter refresh token | 7 days | Rotated on refresh |
| Flutter remember_me | +30 days beyond refresh | Not auto-renewed |

## Audit Events (Auth-related)

| Event | Recorded |
|-------|----------|
| Successful login | login_history + user_sessions + audit |
| Failed login | login_history + audit + security log |
| Logout | login_history (logout_at) + session revoke + audit |
| Token refresh | security log (throttled) + session last_activity |
| Password change | audit + security log |
| Password reset | audit + security log + SMS (OTP gateway) |
| OTP send | notification_logs + security log (count) |
| OTP verify | security log |
| 2FA enable/disable | audit + security log + SMS |
| 2FA challenge/confirm (step-up) | audit + security log |
| Session revoke | audit |
| Account lock | audit + security log |

## Session Cleanup (Cron)

- Expire sessions past `expires_at` → status EXPIRED
- Clear remember tokens past `expires_at`
- Delete old login_history (archive > 90 days)
- Delete expired OTP records (> 24h)

## Important: Never Store Plain Tokens

- JWT secret: env only
- Refresh tokens: hashed in DB
- Remember tokens: hashed in DB
- Session tokens: hashed in DB
- OTPs: hashed in DB
- Raw tokens never logged

---

**Next**: [12-session-management.md](12-session-management.md) for detailed session/token management.