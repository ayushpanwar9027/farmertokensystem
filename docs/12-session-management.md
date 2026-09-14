# Session & Token Management

## Concepts

The system separates several distinct concepts to avoid confusion:

| Concept | Purpose | Storage | Lifetime |
|---------|---------|---------|----------|
| **Access Token (JWT)** | Prove identity for API calls (Flutter) | Client SecureStorage, not in DB | 15 min |
| **Refresh Token** | Obtain new access tokens | Client + `user_sessions` (hashed) | 7 days |
| **Session Token** | Web portal session | PHP session + `user_sessions` (hashed) | 30 min inactivity |
| **Remember-Me Token** | Long-term "stay logged in" | Client + `remember_tokens` (hashed) | 30 days |
| **Device ID** | Stable app-install identifier | Client SecureStorage | Permanent (install) |
| **Push Token (future)** | Push notification target | Client + server | Rotating |

**These are NOT interchangeable.** Do not reuse one type of token for another purpose.

## Session Lifecycle (Flutter)

```
Device First Use
      │
      ▼
Device ID generated (SecureStorage) ──── permanent
      │
      ▼
Login ──────────────────────────────────────────────┐
      │                                             │
      ├─ Create user_session (DB)                   │
      ├─ Issue access JWT (15 min)                  │
      ├─ Issue refresh token (7 days, hashed DB)    │
      └─ Optionally issue remember token (30d)      │
                                                    │
      ▼                                             │
Active Session ─────────────────────────────────────┤
      │                                             │
      ├─ Each API call: Bearer access JWT            │
      ├─ JWT validated (signature + exp + session)   │
      ├─ session checked (active + device match)     │
      ├─ Refresh (async, mid-flight) on 401          │
      │                                             │
      ▼                                             │
Access Expired (15 min)                             │
      │                                             │
      ▼                                             │
Refresh with refresh token ─────────────────────────┤
      ├─ New access token issued                    │
      ├─ New refresh token (rotation)               │
      │                                             │
      ▼                                             │
Refresh Failed (expired/revoked)                    │
      │                                             │
      ▼                                             │
Re-authenticate                                     │
```

## Session Lifecycle (Web)

```
Login
  │
  ├─ Start PHP session
  ├─ Create user_session row (hashed token)
  ├─ Set PHPSESSID cookie (HttpOnly, Secure, SameSite)
  │
  ▼
Active session
  ├─ Validate session on each request
  ├─ Update last_activity (throttled 60s)
  ├─ If activity > 30 min → expire
  │
  ▼
Logout ───────→ revoke session + destroy PHP session
  │
Expire (inactivity / absolute) ──→ session invalidated
  │
Revoke (admin) ──→ session status=REVOKED
```

## Database Tables Involved

### user_sessions

```sql
CREATE TABLE user_sessions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    session_token_hash VARCHAR(64) NOT NULL,  -- SHA-256
    device_id VARCHAR(100) NULL,
    device_name VARCHAR(190) NULL,
    platform VARCHAR(30) NULL,
    app_version VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    remember INT UNSIGNED NULL,               -- FK remember_tokens.id
    is_remembered TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_by INT NULL,
    revoked_reason VARCHAR(190) NULL,
    status ENUM('ACTIVE','EXPIRED','REVOKED','LOGGED_OUT') DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY (session_token_hash),
    KEY (user_id), KEY (expires_at), KEY (status)
);
```

### remember_tokens

```sql
CREATE TABLE remember_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(64) NOT NULL,          -- SHA-256 of 128-char token
    device_id VARCHAR(100) NULL,
    device_name VARCHAR(190) NULL,
    platform VARCHAR(30) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_by INT NULL,
    status ENUM('ACTIVE','EXPIRED','REVOKED') DEFAULT 'ACTIVE',
    PRIMARY KEY (id),
    UNIQUE KEY (token_hash),
    KEY (user_id), KEY (expires_at)
);
```

## Token Generation & Storage Rules

### Access Token (JWT)

```php
// Generate
$payload = [
    'sub' => $userId,
    'role' => $roleName,
    'session_id' => $sessionId,
    'device_id' => $deviceId,
    'iat' => time(),
    'exp' => time() + getSetting('session_timeout_minutes') * 60,
    'jti' => bin2hex(random_bytes(16)),
];
$jwt = createJwt($payload, getenv('JWT_SECRET'));

// Validate
$claims = verifyJwt($jwt, getenv('JWT_SECRET'));
// Check session_id still ACTIVE (server-side revocation check)
```

### Refresh Token

```php
// Generate 64+ random bytes
$refreshToken = bin2hex(random_bytes(64)); // 128 hex chars
$hash = hash('sha256', $refreshToken);

// Store hash in user_sessions.session_token_hash
// Return raw token to client (only once)
```

### Remember-Me Token

```php
// Generate 128 random bytes
$rememberToken = bin2hex(random_bytes(64)); // 128 hex chars
$hash = hash('sha256', $rememberToken);

// Store hash in remember_tokens.token_hash
// Return raw token to client (only once)
```

### Rules
- Use `random_bytes()` (secure random) for all tokens
- NEVER store raw token in DB — always SHA-256 hash
- Return raw token to client exactly once
- Hash used for lookup/validation
- Raw tokens never in logs

## Refresh Token Rotation

For security, refresh tokens rotate on each use:

```
Client sends refresh token R1
       │
       ▼
Server:
  - Hash R1 → lookup in user_sessions
  - If ACTIVE and unexpired:
    - Issue new access JWT
    - Generate R2, store hash
    - Mark R1 as used (OR delete and insert new row, or update hash)
    - Return {access, refresh: R2}
  - Else: revoke session, force re-login
```

**Rotation** prevents replay: if R1 is stolen and used, R2 supersedes; the attacker's R1 becomes invalid.

## Revocation

### Scenarios
| Action | Revokes |
|--------|---------|
| User logout (single device) | That session + related remember token |
| User logout all | All sessions + all remember tokens |
| Password change | All other sessions (security best practice) |
| Admin revoke session | Specific session (+ optional remember token) |
| Admin revoke all user sessions | All sessions + remember tokens |
| Account locked | All sessions |
| Security incident | All sessions immediately |

### Revoke Implementation (Flutter refresh path)
Look up by refresh token hash → update status=REVOKED, revoked_at=NOW(), revoked_reason.

### Revoke Implementation (Web session path)
Look up by session token hash → status=REVOKED. PHP session destroyed.

### Immediate vs. Next-Request
- **Web**: session revoked → next request fails auth, redirected to login
- **Flutter access JWT**: JWT itself may still be valid up to 15 min.
  - To enforce immediate revocation, check `session_id` against `user_sessions.status` on **every** request (DB lookup).
  - This adds a DB query per request but enables instant revocation.
  - For moderate load this is acceptable; document the tradeoff.

## Session Management UI (Super Admin)

Super Admin (and self-service for users) can view:

```
Session Management
├─ Current session (highlighted, "This device")
├─ Active sessions list
│   ├─ Device name
│   ├─ Platform/OS
│   ├─ IP address
│   ├─ Login time (created_at)
│   ├─ Last activity
│   ├─ Expires at
│   ├─ Status (ACTIVE/EXPIRED/REVOKED)
│   └─ [Revoke] button
├─ [Revoke All] button
```

Sensitive actions (revoke) audited.

## Database of Login Sessions → Login History

- `user_sessions` holds ACTIVE session metadata
- `login_history` holds every login event (success/failure) with timestamps
- These are separate: sessions are "current state", login history is "event log"
- On session reunan link: `login_history.session_id → user_sessions.id` (nullable)

## Max Concurrent Sessions

Configurable setting `max_concurrent_sessions` (default 10).
When exceeding: oldest ACTIVE session auto-revoked (or warning shown).

## Device/Session Info for Flutter

From `device_info_plus`:
- Device model, OS version, app version
- Stable device ID: generated once on first run, stored in SecureStorage

```dart
final info = await DeviceInfoPlugin().androidInfo;
final deviceName = '${info.manufacturer} ${info.model}';
final platform = 'android';
final osVersion = info.version.release;
```

## Security Considerations Summary

- Tokens hashed at rest (SHA-256)
- Access tokens short-lived
- Refresh rotation prevents replay
- Remember-me separate & revocable
- Multi-device safe
- Session expiry enforced
- Revocation immediate (DB check)
- Management UI for revoke
- Cleanup cron for expired entries
- Never log raw tokens

---

**Next**: [13-roles-and-permissions.md](13-roles-and-permissions.md) for RBAC.