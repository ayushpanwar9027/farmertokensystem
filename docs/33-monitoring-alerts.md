# Monitoring & Alerts

## Overview

Simple, shared-hosting-compatible monitoring and alerting. Uses the internal error tracker, health checks, and scheduled checks. No third-party observability stack.

## What to Monitor

- Database availability
- API errors
- PHP errors
- SMS failures
- Failed logins
- Storage usage
- Critical configuration issues
- Maintenance status
- Scheduled job (cron) failures

## Health Endpoints

### GET /health
```
{
  "success": true,
  "data": {
    "status": "UP",
    "database": "UP",
    "storage": "UP",
    "version": "1.0.0",
    "time": "..."
  }
}
```

Checks:
- DB: `SELECT 1` (PDO)
- Storage: writable check on storage/logs
- (Optional) OneSignal/OTP config presence (not connectivity)

### GET /health/maintenance
Returns maintenance-mode status (always available, even during maintenance).

## Dashboard Metrics (Admin)

`GET /reports/dashboard` includes operational metrics:
- Bookings today (confirmed/completed/cancelled)
- Avg wait / procurement time
- Error counts (last 24h) by endpoint/severity
- Failed logins (last 24h)
- SMS send success/fail rate
- Storage usage
- Cron last-run status
- Maintenance status

## Monitoring Checks (Cron-driven)

A scheduled check runs periodically (e.g., every 5 min) and records/alert on:

| Check | Condition → Alert |
|-------|-------------------|
| DB availability | `SELECT 1` fails |
| API error rate | errors in last 5 min exceed threshold |
| PHP errors | new CRITICAL errors in error_logs |
| SMS failure rate | recent FAILED notification_logs exceed threshold |
| Failed logins | failed-login spike (e.g., >20 in 10 min) |
| Storage | disk/quotiva > threshold |
| Cron failures | scheduled jobs not completing |
| Maintenance | mode unexpectedly ON (or missing) |

## Alert Mechanism

Simple, not overbuilt:
- **In-app/DB alerts** table for admins + dashboard banner
- **Optional email/SMS** to Super Admin for critical alerts (via existing notification channel)
- No pager/telegram/webhook needed unless required

### alerts (or reuse monitoring_events)
```sql
CREATE TABLE monitoring_events (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    check_name VARCHAR(100),
    severity ENUM('INFO','WARNING','CRITICAL'),
    message TEXT,
    status ENUM('OPEN','ACKNOWLEDGED','RESOLVED') DEFAULT 'OPEN',
    count INT DEFAULT 0,
    first_seen DATETIME,
    last_seen DATETIME,
    resolved_at DATETIME
);
```

## Database Monitoring SQL (examples)

```sql
-- Failed logins in last 10 min
SELECT COUNT(*) FROM login_history
WHERE status='FAILURE' AND login_at > NOW() - INTERVAL 10 MINUTE;

-- SMS failures in last 30 min
SELECT COUNT(*) FROM notification_logs
WHERE status='FAILED' AND updated_at > NOW() - INTERVAL 30 MINUTE
  AND attempt_count >= max_attempts;

-- Recent critical errors
SELECT COUNT(*) FROM error_logs
WHERE severity='CRITICAL' AND created_at > NOW() - INTERVAL 5 MINUTE;
```

## Cron Check Runner

```
php app/console/monitor.php     # every 5 min via cron
  - runs health checks
  - records monitoring_events
  - flags alerts
  - (critical) notifies Super Admin
```

## Alert Thresholds (configurable)

Store thresholds in system_settings:
```
alert_db_ok
alert_api_error_rate
alert_sms_failure_rate
alert_login_failure_spike
alert_storage_percent
alert_cron_failure
```

## API for Admin

| Method | Path | Purpose |
|--------|------|---------|
| GET | /monitoring/health | Live health (admin) |
| GET | /monitoring/events | Monitoring events (admin, scoped by role) |
| POST | /monitoring/events/{id}/ack | Acknowledge alert (admin) |

Requires `manage_system_settings` or `view_reports` (scope).

## Critical Configuration Issues

- Missing/invalid OneSignal/OTP credentials (push/sms enabled but keys unset) → WARNING
- Encryption key missing → CRITICAL
- Maintenance left on → WARNING

## Alert Fatigue Control

- Deduplicate by check_name (increment count, update last_seen)
- Only escalate to Super Admin for CRITICAL
- Admin acknowledges to silence

---

**Next**: [34-backup-recovery.md](34-backup-recovery.md) for backup & recovery.