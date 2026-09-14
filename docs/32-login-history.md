# Login History

## Overview

Dedicated login history table tracks both successful and failed login attempts, enabling last-login display, login-history views, and failed-login analysis.

## Data Model

### login_history table
```sql
CREATE TABLE login_history (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,           -- NULL if user not found
    mobile VARCHAR(15) NULL,             -- attempted mobile
    login_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    logout_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    platform VARCHAR(30) NULL,
    device_name VARCHAR(190) NULL,
    status ENUM('SUCCESS','FAILURE') NOT NULL,
    failure_reason VARCHAR(190) NULL,
    session_id INT UNSIGNED NULL,
    -- indexes: user_id, login_at, status, mobile
);
```

## When Records Are Created

| Event | Status | Failure reason (if any) |
|-------|--------|--------------------------|
| Successful login | SUCCESS | - |
| Wrong password | FAILURE | INVALID_PASSWORD |
| Mobile not found | FAILURE | ACCOUNT_NOT_FOUND |
| Account pending verification | FAILURE | ACCOUNT_PENDING |
| Account rejected | FAILURE | ACCOUNT_REJECTED |
| Account inactive/locked | FAILURE | ACCOUNT_INACTIVE / ACCOUNT_LOCKED |
| Wrong role for endpoint | FAILURE | INVALID_ROLE |
| OTP failure during login | FAILURE | INVALID_OTP |
| 2FA required (password OK) | SUCCESS (step 1) | TWO_FA_REQUIRED (recorded; login completes on OTP) |
| 2FA OTP failure | FAILURE | INVALID_2FA |

## Login Flow Recording

```php
// AuthService::login
$result = ...;
if ($success) {
    // Create session, then log success
    LoginHistory::record([
        'user_id' => $user['id'],
        'mobile' => $mobile,
        'login_at' => now(),
        'ip' => $ip,
        'user_agent' => $ua,
        'platform' => $platform,
        'device_name' => $device,
        'status' => 'SUCCESS',
        'session_id' => $sessionId,
    ]);
} else {
    LoginHistory::record(['status' => 'FAILURE','failure_reason' => $reason, ...]);
    // also security log + increment failed counter
}
```

## Logout Recording

On logout:
- Record `logout_at` on the most recent SUCCESS record for that session
- (Or insert a distinct logout event — document choice; simplest: update logout_at on the session's login row)

## Features

### Last Login
- `users.last_login_at` maintained (lightweight)
- Also derivable from login_history

### Login History (per user, self-service Farmer/Staff)
```
GET /login-history  →  my history (paginated, scoped by user)
```

### Login History (admin, scope-based)
```
GET /login-history?user_id=X&status=FAILURE&from=&to=  →  admin, scoped
```

View: time, status, IP, device, platform, user agent (truncated).

### Failed Login History (admin)
- Filter status=FAILURE to see attack patterns
- Group by IP/mobile for spike detection
- Alerts on abnormal failure rate ([33])

## Access Control

| Who | What |
|-----|------|
| User (self) | Own login history |
| Super Admin | All users' history |
| District Admin | Users within district |
| Manager | Staff/centre users |
| Operator | None (except self) |

Enforced server-side + scope.

## Retention

- Login history archived/cleaned after 90 days (configurable)
- Keep for security audit needs

## Audit & Security

- Login events already in audit_logs (LOGIN action) — login_history is the operational log
- Failed-login spikes → security log + alert

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | /login-history | My history (paginated) |
| GET | /login-history/{userId} | Specific user (admin, scoped) |

---

**Next**: [33-monitoring-alerts.md](33-monitoring-alerts.md) for monitoring & alerting.