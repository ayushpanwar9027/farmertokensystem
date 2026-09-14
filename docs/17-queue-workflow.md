# Queue Management Workflow

## Overview

Live queue management at procurement centres. Uses AJAX polling (NOT WebSockets). "Call Next" is concurrency-protected.

## Queue Entry Statuses

```
             ┌──────────────────────────────┐
             │           WAITING            │
             └─────────────┬────────────────┘
                           │
             ┌─────────────┴─────────────┐
             │                           │
        call next                    cancelled
             │                           │
             ▼                           ▼
     ┌───────────────┐           ┌───────────────┐
     │     CALLED    │           │   CANCELLED   │
     └───────┬───────┘           └───────────────┘
             │
     ┌───────┴───────────┐
     │                   │
   start/procure      skip
     │        (reason)  │
     ▼                   ▼
┌───────────┐     ┌───────────┐
│ IN_PROGRESS│     │  SKIPPED  │
└─────┬─────┘     └───────────┘
      │ complete
      ▼
┌───────────┐
│ COMPLETED │
└───────────┘
```

### State Transition Table

| From | To | Action | Requirements |
|------|----|--------|--------------|
| WAITING | CALLED | Call Next | manage_queue + concurrency guard |
| WAITING | CANCELLED | Cancel booking | booking cancel (farmer/staff) |
| CALLED | IN_PROGRESS | Start | manage_queue |
| CALLED | SKIPPED | Skip | reason + manage_queue |
| IN_PROGRESS | COMPLETED | Complete | manage_queue (after procurement) |
| SKIPPED | CALLED | Re-call (optional) | manage_queue + policy |

## Call Next — Concurrency Protected

### Problem
Two operators clicking "Call Next" simultaneously could both claim the same next farmer.

### Solution: Atomic Guarded Update

```php
// app/Services/QueueService.php
public function callNext(int $centreId, string $date, int $operatorId): void {
    // BEGIN TRANSACTION

    // 1. Find oldest WAITING entry (locked)
    $entry = Database::selectOne(
        "SELECT * FROM queue_entries
         WHERE centre_id = ? AND date = ? AND status = 'WAITING'
         ORDER BY created_at, id ASC
         LIMIT 1
         FOR UPDATE",            // MySQL row lock
        [$centreId, $date]
    );

    if (!$entry) {
        throw new QueueEmptyException('No farmers waiting in queue');
    }

    // 2. Guarded UPDATE — verify status is still WAITING
    $affected = Database::update(
        "queue_entries SET status = 'CALLED', called_at = NOW(), called_by = ?
         WHERE id = ? AND status = 'WAITING'",
        [$operatorId, $entry['id']]
    );

    // 3. If 0 rows affected → someone else called it; retry/error
    if ($affected === 0) {
        throw new ConcurrentUpdateException('Another operator just called this token; refresh and retry');
    }

    // 4. Load farmer + token for notification
    // 5. Send CALLED notification (idempotent)
    // COMMIT
}
```

**Double protection**: row lock (FOR UPDATE) + status guard in UPDATE. Even without the lock, the guarded UPDATE would only affect the row if it's still WAITING.

### Alternative (for strict shared-hosting compat / InnoDB locks avoided)
```sql
-- optimistic approach without explicit transaction lock
UPDATE queue_entries
SET status = 'CALLED', called_at = NOW(), called_by = ?
WHERE id = (
    SELECT id FROM (
        SELECT id FROM queue_entries
        WHERE centre_id=? AND date=? AND status='WAITING'
        ORDER BY created_at, id ASC LIMIT 1
    ) AS sub
) AND status = 'WAITING';
-- check affected rows == 1
```

## Live Queue Data (Polling)

Farmer and staff poll `GET /queue/live?centre_id=&date=[&booking_id=]` every ~5 seconds.

```json
{
  "centre_id": 3,
  "date": "2026-09-10",
  "current": {
    "token_number": "APMCPNQ-20260910-0042",
    "farmer_name": "Ramesh Kumar",
    "status": "CALLED"
  },
  "queue_count": { "waiting": 12, "called": 1, "in_progress": 1, "completed": 20 },
  "my_entry": {
    "position": 8,
    "farmers_ahead": 7,
    "estimated_wait_minutes": 40,
    "status": "WAITING"
  },
  "estimated_average_procurement_time_minutes": 5
}
```

### Position & Estimated Wait Computation

For a given farmer's entry:
```php
$farmersAhead = SELECT COUNT(*)
    FROM queue_entries
    WHERE centre_id=? AND date=?
      AND status IN ('WAITING','CALLED','IN_PROGRESS')
      AND (created_at, id) < (myEntry.created_at, myEntry.id);

$estimatedWaitMinutes = $farmersAhead * $avgProcurementMinutes;
// $avgProcurementMinutes = rolling average from queue stats
```

### Polling Implementation (Staff Web)

```javascript
// Poll every 5 seconds
const poller = new QueuePoller(centreId, date, 5000);
poller.start();
poller.onUpdate(renderQueue);
// stop on page leave
```

### Polling Implementation (Flutter)

```dart
// Timer.periodic every 5s
// pause when offline, resume when back
QueuePollingProvider(centreId, date, bookingId)
```

## Queue Operations (Staff)

### Call Next
`POST /queue/call-next {centre_id, date}`
- Concurrency-protected (above)
- Calls oldest WAITING
- Notifies farmer (SMS: "You have been called. Please report to counter.")

### Mark Arrived
`POST /queue/{entryId}/arrived`
- Optionally track arrival time (farmer present)
- Not strictly required for queue progression but useful

### Start
`POST /queue/{entryId}/start`
- CALLED → IN_PROGRESS
- Typically when procurement processing begins

### Complete
`POST /queue/{entryId}/complete`
- IN_PROGRESS → COMPLETED
- Usually after all crops procured
- Notifies farmer (procurement completed)

### Skip
`POST /queue/{entryId}/skip {reason}`
- CALLED → SKIPPED
- Reason required
- Farmer can be re-added (optional): returns to WAITING with new position, or flagged

### Cancel
- Inherited from booking cancellation: WAITING/CALLED → CANCELLED

## Queue Corrections (Manager+)

Operations like reordering are **restricted**:
- Only Manager / District / Super
- Require permission + reason + audit
- Never silently reorder

Example correction endpoint: `POST /queue/{entryId}/reposition {new_position, reason}`

## Dashboard Queue Stats

`GET /queue/stats` returns:
- Total booked / arrived / completed today
- Average wait time
- Average procurement time
- Peak hour
- Completion rate

## Notifications from Queue

| Event | Notification |
|-------|--------------|
| Farmer called | SMS "You have been called" |
| Procurement completed | SMS "Procurement done" |
| Queue approaching (threshold) | SMS "You are X away in queue" (one-time, on crossing threshold) |

## Concurrency & Consistency Summary

| Risk | Mitigation |
|------|------------|
| Two Call Next | Row lock + guarded update + status check |
| Position duplicate | Recompute on read; atomic insert with counter |
| Farmer called twice | status guard: WAITING→CALLED only once |
| Booking cancel vs queue | transactional, queue entry updated |
| Duplicate queue notification | idempotency key per event+booking |

## Audit Events

- Call Next (operator, token)
- Arrived (operator, token)
- Start (operator, token)
- Complete (operator, token)
- Skip (operator, token, reason)
- Reposition (manager, token, old/new position, reason)

---

**Next**: [18-procurement-workflow.md](18-procurement-workflow.md) for procurement workflow.