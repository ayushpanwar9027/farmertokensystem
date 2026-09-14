# Cron Jobs

## Overview

Scheduled jobs via shared-hosting cron. Each job documented with frequency, purpose, command, failure behavior, logging.

> **⚠ DISCREPANCY (updated Phase 18, 2026-09-11)**: this document originally
> listed **12 jobs**. The actual scheduler (`app/console/cron.php`) implements
> **6 jobs** (the switch-cases below were verified against the source).
> The following were **never implemented** and are intentionally NOT added
> (Phase 18 = no new features): `expiry-sessions`, `cleanup-temp`,
> `rotate-logs`, `report-daily`, `backup-database`, `backup-files`,
> `monitor-health`, `cleanup-rate-limit`, `archive-audit`.
>
> Backups and log rotation are therefore OUT-OF-BAND (manual commands /
> hosting tools) until a later phase adds those jobs. See `deploy/checklist.md`
> §7 and `docs/OPS-RUNBOOK.md` §6 for the manual backup procedure.

## Job Scheduler Design

Individual cron entries calling CLI scripts through a single runner.

| # | Job | Frequency | Purpose |
|---|-----|-----------|---------|
| 1 | generate-slots | daily 00:15 | Generate slots for ACTIVE centres (horizon, close days, breaks) |
| 2 | expire-pending-bookings | every 5 min | Mark PENDING bookings past slot start → EXPIRED; release slot capacity; cancel tokens; cancel queue entries |
| 3 | expire-unarrived-bookings | every 30 min | Mark CONFIRMED bookings past slot start without arrival → EXPIRED; release capacity |
| 4 | queue-notify | every 5 min | Send QUEUE_APPROACHING push when farmers-ahead < `queue.notify_threshold` (dedup by event_ref) |
| 5 | send-pending-notifications | every 1 min | Flush PENDING push (OneSignal) / SMS immediately |
| 6 | retry-notifications | every 5 min | Retry FAILED notifications within max attempts / backoff |

## Runner

```bash
# Single cron entry calling runner with job name
php /home/user/app/console/cron.php --job=generate-slots
```

`cron.php` maps job name → inline handler, sets CLI context (no auth), writes an **audit_logs** row per run with `reason = cron_<job>`, and prints a summary to stdout. It does NOT write a `cron_runs` table today (that schema lives in monitoring docs); failure is detected via audit-log missing entries + log scanning, and via the alert paths in [33-monitoring-alerts.md](33-monitoring-alerts.md).

### Run tracking (reference schema)
```sql
-- cron_runs (documented in 33-monitoring-alerts.md; not created by cron.php)
CREATE TABLE cron_runs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    job VARCHAR(100) NOT NULL,
    started_at DATETIME,
    finished_at DATETIME,
    status ENUM('RUNNING','OK','FAILED'),
    message TEXT,
    rows_processed INT DEFAULT 0,
    duration_ms INT
);
```
- Monitoring reads last run status per job → alerts on failure.

## Job Details

### 1. generate-slots
- **Frequency**: daily 00:15
- **Purpose**: `extend` the slot horizon for ACTIVE centres; skips existing slots; honours centre hours, closed days, breaks; idempotent (0 generated on re-run)
- **Command**: `php app/console/cron.php --job=generate-slots`
- **Failure**: log + audit; next run continues
- **Logging**: audit `SLOTS_GENERATED` / application.log

### 2. expire-pending-bookings
- **Frequency**: every 5 min
- **Purpose**: bookings in PENDING past slot start → EXPIRED; release `slots.booked_count`; CANCELLED tokens; cancelled queue entries + renumber
- **Command**: `php app/console/cron.php --job=expire-pending-bookings`
- **Failure**: log; audit
- **Logging**: audit `BOOKINGS_EXPIRED` / application.log

### 3. expire-unarrived-bookings
- **Frequency**: every 30 min
- **Purpose**: CONFIRMED bookings past slot start without arrival → EXPIRED + capacity release (guarded update, idempotent)
- **Command**: `php app/console/cron.php --job=expire-unarrived-bookings`
- **Failure**: log; audit
- **Logging**: audit `BOOKINGS_EXPIRED` / application.log

### 4. queue-notify
- **Frequency**: every 5 min
- **Purpose**: for WAITING entries today+tomorrow with farmers-ahead < `queue.notify_threshold` → dispatch `queue_approaching` (dedup via `INSERT IGNORE` on `notification_logs.event_ref`)
- **Command**: `php app/console/cron.php --job=queue-notify`
- **Failure**: per-entry guarded (`\Throwable` swallowed), audit written
- **Logging**: audit `QUEUE_NOTIFY` / notification.log

### 5. send-pending-notifications
- **Frequency**: every 1 min
- **Purpose**: immediately dispatch PENDING push/SMS rows via `NotificationService::retryPending()`
- **Command**: `php app/console/cron.php --job=send-pending-notifications`
- **Failure**: log per attempt; alert on sustained failure
- **Logging**: audit `SEND_PENDING_NOTIFICATIONS` / notification.log

### 6. retry-notifications
- **Frequency**: every 5 min
- **Purpose**: retry FAILED notification rows within `NOTIFICATION_MAX_RETRIES` and backoff via `NotificationService::retryFailed()`
- **Command**: `php app/console/cron.php --job=retry-notifications`
- **Failure**: log per attempt; permanent failure after max
- **Logging**: audit `RETRY_NOTIFICATIONS` / notification.log

## cPanel Cron Examples

```bash
15 0 * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=generate-slots >> /home/user/fps/storage/logs/cron.log 2>&1
*/5 * * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=expire-pending-bookings >> /home/user/fps/storage/logs/cron.log 2>&1
*/30 * * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=expire-unarrived-bookings >> /home/user/fps/storage/logs/cron.log 2>&1
*/5 * * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=queue-notify >> /home/user/fps/storage/logs/cron.log 2>&1
* * * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=send-pending-notifications >> /home/user/fps/storage/logs/cron.log 2>&1
*/5 * * * * /usr/bin/php /home/user/fps/app/console/cron.php --job=retry-notifications >> /home/user/fps/storage/logs/cron.log 2>&1
```

> Path verification: find the host php binary with `which php` (often `/usr/bin/php` or `/usr/local/bin/php`).

## Failure Behavior Summary

- Each job idempotent (safe to re-run)
- Failures logged + audit row; monitor catches missing/FAILED runs → alert
- No job should leave partial state; guarded UPDATEs / transactions where needed
- No job runs `--demo` or `--reset`

## Idempotency

- generate-slots: skip-existing by (centre_id, date, start_time)
- expire-*: guarded UPDATE with status + slot datetime conditions
- queue-notify: dedup key (`notification_logs.event_ref` unique)
- retry/send: only PENDING/FAILED rows within attempt limit

---

**Next**: [36-shared-hosting.md](36-shared-hosting.md) for shared hosting deployment.