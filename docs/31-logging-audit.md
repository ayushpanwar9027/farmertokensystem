# Logging & Audit

## Overview

Two distinct logging concerns:
1. **Application/technical logs** (files) — for debugging & ops ([30-error-handling.md](30-error-handling.md))
2. **Audit logs** (DB) — for accountability, compliance, traceability of important actions

This document focuses on **audit logging** plus the log-file separation.

## Audit Log Data Model

### audit_logs table
```sql
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    user_name VARCHAR(190) NULL,
    user_role VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,           -- CREATE, UPDATE, DELETE, LOGIN, APPROVE...
    module VARCHAR(50) NOT NULL,           -- BOOKING, QUEUE, PROCUREMENT...
    entity_type VARCHAR(50) NULL,          -- booking, slot, centre...
    entity_id INT UNSIGNED NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    reason VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    request_id VARCHAR(64) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- indexes on created_at, user_id, action, module, (entity_type, entity_id)
);
```

## What Gets Audited

Every important action:

| Module | Examples |
|--------|----------|
| AUTH | Login, logout, password change, session revoke, account lock |
| PERMISSIONS | Permission change, role change, user override |
| STAFF | Staff creation, update, deactivate, permission custom |
| CENTRES | Create, update, deactivate centre |
| SLOTS | Create, update, activate/deactivate slot |
| BOOKINGS | Create, cancel, expire booking |
| QUEUE | Call next, arrived, start, complete, skip, reposition |
| PROCUREMENT | Create, verify, start, complete, reject, correct |
| PAYMENTS | Status change, reversal, correction |
| APPROVAL | Approve/reject farmer, staff, corrections |
| FILES | Upload, move, rename, copy, delete, reference fix |
| LANGUAGES | Add, update, default change, delete |
| SETTINGS | Setting change |
| SECRETS | Secret set, rotate |
| MAINTENANCE | Enable/disable |
| CORRECTIONS | Submit, approve, reject |

## AuditLog Service

```php
// app/Services/AuditService.php
class AuditService {
    public function log(array $entry): void {
        Database::insert('audit_logs', $this->enrich($entry));
    }

    private function enrich(array $entry): array {
        $entry['user_id'] = $entry['user_id'] ?? auth()->id();
        $entry['ip_address'] = $entry['ip_address'] ?? client_ip();
        $entry['user_agent'] = $entry['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null;
        $entry['request_id'] = $entry['request_id'] ?? Request::requestId();
        return $entry;
    }
}
```

### Sensitive-value handling
- `old_value`/`new_value` exclude secrets/tokens/passwords
- Structure: `{ status: "old" }` not full objects where not needed
- Never include secrets, OTPs, full passwords, bearer tokens

## Access to Audit Logs (Scope)

| Role | Access |
|------|--------|
| Super Admin | Full logs |
| District Admin | District-scoped logs |
| Centre Manager | Centre-scoped logs |
| Centre Operator | None (or strictly self-action history) |
| Farmer | None |

Enforced by AuditController + ScopeMiddleware.

## Filters (Admin UI)

- Date range
- User
- Role
- Action
- Module
- Entity type/id
- Centre
- District
- Paginated

## API

| Method | Path | Purpose |
|--------|------|---------|
| GET | /audit-logs | List (scoped) with filters + pagination |

## Log File Separation

| File | Content |
|------|---------|
| application.log | Exceptions, app-level events |
| api.log | API request metadata (method, endpoint, status, duration) |
| security.log | Auth failures, permission denials, rate-limit hits, lockouts |
| notification.log | OneSignal push + OTP SMS send/retry/fail (without secrets) |

## Never Log

Same as [30]: passwords, OTPs, secrets, tokens, keys, excess PII.

## Retention & Cleanup

- Audit logs: archive > 90 days (cron) to archive table/file
- Log files: rotate (cron), e.g., keep 14 days of .log, then gzip
- Rate limit logs: clean old (>1 day)

## Monitoring Integration

- Audit success/failure rates
- Unauthorized-access attempt spikes → security alert
- See [33-monitoring-alerts.md](33-monitoring-alerts.md)

---

**Next**: [32-login-history.md](32-login-history.md) for login history tracking.