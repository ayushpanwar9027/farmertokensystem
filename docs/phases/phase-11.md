# Phase 11 — Queue + Live Queue

## 1. Objective

Implement the queue engine: queue entry creation from confirmed booking/token, call-next flow, statuses, live queue read for app + operator dashboard, active count, processed counts. Concurrency-safe "call next".

## 2. Prerequisites

- Phase 05 (scope), Phase 10 (bookings/tokens confirmed), Phase 03 (pipeline)

## 3. Features

- QueueEntry per booking (1:1 with booking/token)
- Status flow: WAITING → CALLED → IN_PROGRESS → (start procurement) COMPLETED | SKIPPED | (on cancel) CANCELLED | NO_SHOW
- Operator actions: call next (pop up to N?), call specific token, mark skip, mark no-show
- Live queue: current position (ahead count), queue size, last token, current served token, ETA estimate (avg time)
- Turn-based fairness (FIFO by token/creation)
- Concurrent "call next" — only one active call at a time (transaction + advisory/lock)
- Queue position persisted to avoid full scans on big reads (position field maintained)
- Active queue window per date (centre + date)

## 4. Files to Create

```
app/Controllers/QueueController.php
app/Controllers/Operator/QueueOperatorController.php
app/Services/QueueService.php
app/Services/QueuePositionService.php
app/Models/QueueEntry.php
app/Validators/QueueValidator.php
```

## 5. Files to Modify

- `app/Console/cron.php` (queue-notify threshold job placeholder — implement in 14)
- `config/routes.php` (`/queue/*`)
- `app/Middleware/ScopeMiddleware.php` (operator centre list)

## 6. Database Changes

- Uses queue_entries (Phase 02)
- Add index: `(centre_id, queue_date, status)`; position integer column
- (If schema lacks position column → migration add `position INT NULL`)

## 7. API Changes

App/farmer:
- `GET /queue/my` (own entry: position, status, waited_minutes, eta)
- `GET /queue/~live?centre_id=&date=` (public lite: queue_size, last_called_token, current_token, avg_wait_minutes, eta_minutes, status) — no PII
- `GET /queue/{bookingToken}/status` (own verified)

Portal/operator:
- `GET /operator/queue?centre_id=&date=` (entries WAITING + CALLED + IN_PROGRESS)
- `POST /operator/queue/call-next` (centre, date optional → now) returns next token + all statuses updated
- `POST /operator/queue/{entryId}/skip` (reason required)
- `POST /operator/queue/{entryId}/no-show`
- `POST /operator/queue/{entryId}/recall` (re-call already called/skipped-within-window)
- `GET /operator/queue/stats` (served today, waiting, avg time)

## 8. Backend Logic

QueueService:
- Enqueue (from booking confirm): create QUEUE entry WAITING position = max(position)+1 for centre+date
- call-next:
  - Lock row/transaction for centre+date (SELECT ... FOR UPDATE on a per-centre control row or advisory via `GET_LOCK`)
  - Pick lowest position WAITING → set CALLED (now called_at) → close previous IN_PROGRESS if timed out (auto-COMPLETED with timeout rule)
  - Update stats: last_called_token, last_called_at
  - Return token + expected wait
- skip: requires reason; reorder option? default: at end (decision: SKIPPED goes to end once, then auto-NO_SHOW) — simpler: SKIPPED is terminal (farmer must re-approach); status remains SKIPPED_CUR handling = they get recalled or lose position (decision default: SKIPPED move to end once via `recall_eligible` flag)
- no-show: after grace_minutes (setting) → NO_SHOW; booking stays VALID-bit? mark token consumed
- position update on skip/end-move (shift positions)

Live math:
- ahead = COUNT WHERE centre+date AND status in (WAITING, CALLED, IN_PROGRESS) AND position < my.position
- eta = ahead * avg_per_token_minutes (rolling from served last 10)

## 9. Flutter Changes

- None (Phase 15). App will call `/queue/my` and poll `/queue/live`.

## 10. Staff/Admin Changes

- None (Phase 16). Operator APIs consumed by operator dashboard.

## 11. Permissions

- `queue.view` (operator + admin scoped)
- `queue.call_next`, `queue.skip`, `queue.no_show`
- `queue.stats`
- Farmer: `queue.view_own`

## 12. Validation

- call-next: centre+date required; operator must belong to centre (scope)
- skip/no-show: entry id within operator centre; status eligible (CALLED/IN_PROGRESS)
- reason length ≥ 2 for skip

## 13. Error Handling

- QUEUE_EMPTY, QUEUE_NOT_FOUND, ENTRY_STATUS_INVALID, CONCURRENT_UPDATE (double call), CENTRE_MISMATCH, NO_ACTIVE_SHIFT (off time), DATE_ELIGIBILITY

## 14. Security

- Live tallies hide identities (only last N tokens, no names)
- Own-status endpoint only own token
- Operator ops scoped to centre; district admin scoped to district centres
- Concurrency: no double-serving guarantee via locking

## 15. Logging/Audit

- audit: call-next, skip (reason), no-show
- api.log: live poll counts (sanitized)
- Queue stats tracked in stats snapshots (optional)

## 16. Notifications

- QUEUE_APPROACHING when ahead < threshold (module 14)
- CALLED notification when token called (module 14; enqueue event now)
- NO_SHOW notice (14)

## 17. Configuration Changes

- `queue.avg_minutes_per_token=10`, `queue.grace_no_show_minutes=5`, `queue.notify_threshold=3`, `queue.call_batch=1`, `queue.recall_limit=1`

## 18. Dependencies

- None (MySQL `GET_LOCK` or app entity lock)

## 19. Completion Criteria

- [ ] Enqueue on booking confirm
- [ ] call-next atomic (parallel safe, one winner)
- [ ] Skip/no-show state machine working
- [ ] Position/ETA math correct
- [ ] Live endpoint returns aggregate right data
- [ ] Scope enforced (operator only own centre)
- [ ] Queue clears/expires with booking cancel/expire

## 20. Testing Checklist

- [ ] Create 5 confirmed bookings → positions 1..5
- [ ] Two parallel call-next → exactly 1 success returns token, other QUEUE_EMPTY/CONCURRENT
- [ ] Skip first → it goes to end (or terminal per decision); next call picks position 2
- [ ] No-show after grace → NO_SHOW
- [ ] ETA ≈ ahead * avg
- [ ] Other-centre operator call-next → 403
- [ ] Cancel booking → queue entry CANCELLED + position shift

## 21. What NOT to Implement

- No procurement (12)
- No payment (13)
- No OneSignal push / OTP sending (14) yet beyond event logging
- No Realtime push (polling only)

---

**Depends on**: Phase 10
**Feeds into**: Phase 12+