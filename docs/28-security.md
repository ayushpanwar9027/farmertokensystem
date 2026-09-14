# Security Implementation

## Overview

Comprehensive security across transport, authentication, authorization, data, input, output, and operations.

## 1. Transport Security (HTTPS)

- HTTPS enforced site-wide
- Force redirect HTTP → HTTPS (.htaccess/node config)
- HSTS header: `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- TLS 1.2+ (via hosting provider)
- Secure cookies (Secure flag)

## 2. Authentication

- Passwords hashed with argon2id via `password_hash($p, PASSWORD_DEFAULT)` (PHP 8.1 → argon2id)
- Session/refresh/remember tokens hashed (SHA-256) at rest
- JWT HMAC-SHA256 via env secret
- OTP hashed, short expiry (5 min), attempt-limited
- Rate-limited login/OTP
- **2FA (OTP)**: optional per user; login 2-step password→OTP via OTP gateway; enable/disable/step-up verified with fresh OTP; OTPs never logged

See [11-authentication.md](11-authentication.md)

## 3. Authorization

- RBAC with role + permission + scope (backend enforced)
- Every protected endpoint runs auth → role → permission → scope middleware
- Never rely on frontend visibility
- Scope filtering applied in SQL

See [13-roles-and-permissions.md](13-roles-and-permissions.md)

## 4. Session & Cookie Security

### Cookies (Web)
- `HttpOnly` — not readable by JS (XSS mitigation)
- `Secure` — HTTPS only
- `SameSite=Lax` — CSRF mitigation
- Session rotation on privilege change
- Session ID regenerated after login

### Tokens (Flutter)
- Stored in SecureStorage (encrypted)
- Access token short-lived (15 min)
- Refresh rotation
- Server-side session status check

## 5. CSRF Protection

- Double-submit cookie pattern for web portal
- Server requires `X-CSRF-Token` header matching a cookie/expected value for all state-changing requests
- Validated in CsrfMiddleware
- Flutter (non-browser) uses Bearer token; CSRF not applicable but origin validated

## 6. Input Validation & Output Escaping

- All input validated server-side (Validators)
- Prepared statements (PDO) — no SQL injection
- JSON responses use `json_encode(..., JSON_UNESCAPED_UNICODE)` — output encoding
- HTML output escaped (htmlspecialchars) in any server-rendered views/emails
- No echo of user input unescaped

## 7. Rate Limiting

See [29-rate-limiting.md](29-rate-limiting.md)

## 8. File Upload Security

- Whitelist MIME types (allowed_file_types)
- Size limit (file_upload_max_size_mb)
- Random storage filename (not user filename)
- Reject executable/script types
- Files stored outside web root (or auth-checked serve)
- `Content-Disposition` for downloads
- No arbitrary code execution from uploads

## 9. Secrets & Configuration Security

- Secrets encrypted at rest (AES-256-GCM)
- Encryption key from env, not in DB
- .env outside web root, chmod 600
- Secrets masked in UI, never logged
- Config files writable only by trusted process
- **Current note**: OneSignal/OTP gateway keys are hardcoded in `config/onesignal.php` / `config/otp.php` for the initial release; they move into encrypted secrets in a later phase

See [26-secret-management.md](26-secret-management.md)

## 10. Security Headers

```apache
# Recommended headers (.htaccess or app middleware)
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set X-XSS-Protection "1; mode=block"
Header always set Content-Security-Policy "default-src 'self'; ..."
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
```

Adjust CSP for admin portal (Chart.js CDN, inline scripts as needed).

## 11. No Raw Errors to Users

- Production `display_errors=Off`
- All errors logged (with details) but generic message to user
- Exception handler maps to safe HTTP responses
- Never expose SQL, paths, stack traces to client in production

## 12. Audit & Security Logging

- Audit logs for sensitive actions ([31-logging-audit.md](31-logging-audit.md))
- security.log for auth/permission/rate-limit events
- No secrets/tokens/passwords/OTPs logged ([31]).

## 13. SQL Injection Prevention

- PDO prepared statements always
- `PDO::ATTR_EMULATE_PREPARES => false`
- Named/positional params
- Never string-concat user input into SQL
- Input validation before query

## 14. XSS Prevention

- Output escaping
- HttpOnly cookies
- CSP header
- No `eval`/`innerHTML` with unescaped data in portal JS (use textContent/escape helpers)

## 15. Brute Force / Abuse Prevention

- Lockout after N failed logins (max_login_attempts)
- Rate limiting on login/OTP/SMS/booking
- Failed-login spikes alert (security log)
- Account lockout configurable

## 16. Data Protection & PII Minimization

- Collect only necessary PII
- Minimal logging of personal data
- Aadhaar: only last 4 (identifier), never full
- Mobile used for auth + notifications

## 17. Dependency & Platform Hygiene

- Minimal dependencies
- Keep PHP + MySQL patched (hosting)
- No hardcoded credentials in source

## 18. File System Permissions

```
/home/user/.env           chmod 600
/home/user/app            r-x (no public access)
/home/user/storage        w-x (logs/uploads writable, not publicly reachable)
/home/user/public         r-x (web root)
```

## 19. Maintenance Mode Security

- Only Super Admin can toggle
- Bypass restricted to authorized admins
- Public APIs return 503 during maintenance

## 20. Security Testing

- Test auth bypass, scope bypass, SQLi, XSS, CSRF, rate limit, upload validation
- See [38-testing-strategy.md](38-testing-strategy.md)

---

**Next**: [29-rate-limiting.md](29-rate-limiting.md) for rate limiting.