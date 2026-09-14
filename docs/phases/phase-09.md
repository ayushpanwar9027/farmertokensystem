# Phase 09 — Slots + Queue Configuration

## 1. Objective

Implement slot management (date/day-based slot generation tied to centre hours) and queue configuration (capacity, expected processing time, breaks) that downstream queue/procurement flow will use.

## 2. Prerequisites

- Phase 08 (centres with open days/hours)

## 3. Features

- Slot model: centre, date, start/end time, capacity, booked_count (or separate slot_bookings), status (AVAILABLE/FULL/INACTIVE/CANCELLED)
- Slot generation for future dates up to setting (default 7 days ahead) via cron
- Manual slot create/edit/cancel (centre managers/managed )
- Capacity modelling = queue capacity (not just booking slots) — need decision config: `slot_capacity` = max tokens per slot
- Expected per-slot call time stored at night-config (`expected_time_minutes`) used to set closing slot
- Break handling (slots exclude break ranges configured per centre)
- Prevent slot creation on inactive/closed days

## 4. Files to Create

```
app/Controllers/Admin/SlotController.php
app/Services/SlotService.php
app/Services/SlotGenerator.php (cron + on-demand)
app/Models/Slot.php
app/Validators/SlotValidator.php
app/Console/cron --job=generate-slots entry (add to cron.php)
```

## 5. Files to Modify

- `app/Console/cron.php` (register generate-slots job)
- `config/routes.php`

## 6. Database Changes

- Uses slots (Phase 02). Consider additional `slot_bookings` count source: booking count derived from bookings table (status != CANCELLED/REVERSED/EXPIRED). Prefer derived count to avoid drift.

## 7. API Changes

- `GET /slots?centre_id=&date=&type=bookable` (public for app)
- `GET /admin/slots?centre_id=&date_from=&date_to=` 
- `POST /admin/slots` (manual single)
- `PUT /admin/slots/{id}` (capacity/time/cancel)
- `DELETE /admin/slots/{id}` (cancel only if no future bookings; else blocked)
- `POST /basic/generate` (adhoc regenerate range)

## 8. Backend Logic

- SlotGenerator: for each future date within horizon, for each centre, generate slots per configured duration & capacity, skip closed days/breaks, skip existing
- SlotService.adjustCapacity: block change below current booked count
- Slot cancel: only AVAILABLE→CANCELLED; if bookings exist → error
- Derived booked count = count(bookings WHERE slot_id AND status NOT IN (CANCELLED, REVERSED, EXPIRED))

## 9. Flutter Changes

- None (Phase 15): GET /slots for date to pick time

## 10. Staff/Admin Changes

- None (Phase 16): slot management UI

## 11. Permissions

- `slots.view` (public for bookable; admin view scoped)
- `slots.manage` (centre manager + above, scoped to own centre)
- `slots.cancel`

## 12. Validation

- date >= today
- time within centre hours; 15-min granularity
- slot_start < slot_end
- capacity >= 1
- no overlap duplicates for same centre+date+time

## 13. Error Handling

- SLOT_EXISTS, SLOT_NOT_FOUND, SLOT_HAS_BOOKINGS, CAPACITY_BELOW_BOOKED, CENTRE_INACTIVE, DATE_OUT_OF_RANGE, OUTSIDE_CENTRE_HOURS

## 14. Security

- Slot creation only for ACTIVE centres
- No cross-centre ops for centre roles
- Derived counts avoid race/oversell inconsistency mainline (atomic check in booking phase)

## 15. Logging/Audit

- audit: slot create/update/cancel, generator run summary

## 16. Notifications

- None

## 17. Configuration Changes

- `slot.horizon_days=7`, `slot.default_duration_minutes=15`, `slot.default_capacity=10`, `slot.min_capacity=1`

## 18. Dependencies

- None

## 19. Completion Criteria

- [ ] Generator creates slots for N days ahead
- [ ] Closed days/breaks excluded
- [ ] Bookable available only for ACTIVE centres
- [ ] Capacity floor enforced
- [ ] Derived booked_count correct
- [ ] Cancel with bookings blocked

## 20. Testing Checklist

- [ ] Run generate → slots exist tomorrow
- [ ] Centre closed Sunday → no Sunday slots
- [ ] Booking bump count → booked_count reflects (via booking create)
- [ ] Set capacity 5 when 6 booked → 409 CAPACITY_BELOW_BOOKED
- [ ] Cancel slot with bookings → 409 SLOT_HAS_BOOKINGS
- [ ] Duplicate slot same time → 409 SLOT_EXISTS

## 21. What NOT to Implement

- No booking creation (Phase 10)
- No token/queue (Phase 11)
- No procurement/payment

---

**Depends on**: Phase 08
**Feeds into**: Phase 10+