# Entity Relationship Diagram (ERD)

## Text-based ERD

```
┌──────────────────────────────┐
│          roles               │
├──────────────────────────────┤
│ id PK                        │
│ name UNIQUE                  │
│ display_name                 │
│ level                        │
│ is_system                    │
└──────────────┬───────────────┘
               │ 1
               │
               │ N
┌──────────────▼───────────────┐
│          users               │
├──────────────────────────────┤
│ id PK                        │
│ name                         │
│ mobile UNIQUE                │
│ email UNIQUE                 │
│ username UNIQUE              │
│ password_hash                │
│ role_id FK ──────────────────┼──→ roles
│ status                       │
│ verification_status          │
│ last_login_at                │
│ is_super_admin               │
└───────┬──────────┬──────────┬┘
        │          │          │
        │ 1        │ 1        │ 1
        │          │          │
   ┌────▼───┐  ┌───▼────┐  ┌──▼──────────┐
   │farmers│  │user_devices│  │user_sessions│
   ├────────┤  ├──────────┤  ├────────────┤
   │id PK   │  │id PK      │  │id PK       │
   │user_id │  │user_id FK │  │user_id FK  │
   │district│  │device_id  │  │token_hash  │
   │...     │  │...        │  │expires_at  │
   └────────┘  └──────────┘  └────────────┘
        │                         │ 1
        │ 1                       │
        │                    ┌────▼──────────┐
        │                    │remember_tokens│
   ┌────▼─────────┐          ├───────────────┤
   │  login_history│          │token_hash     │
   ├──────────────┤          │expires_at     │
   │user_id FK    │          │...            │
   │login_at      │          └───────────────┘
   │status        │
   └──────────────┘

┌──────────────┐
│  permissions │
├──────────────┤
│ id PK        │
│ name UNIQUE  │
│ module       │
└──────┬───────┘
       │ M:N via role_permissions / N:1 via user_permissions
       │
┌──────▼───────┐     ┌──────────────┐
│role_permissions│     │user_permissions│
├──────────────┤     ├──────────────┤
│role_id FK    │     │user_id FK    │
│permission_id │     │permission_id │
└──────────────┘     │granted       │
                     └──────────────┘

┌──────────────┐     1     N ┌──────────────────┐
│  districts   │────────────▶│procurement_centres│
├──────────────┤             ├──────────────────┤
│ id PK        │             │ id PK            │
│ name UNIQUE  │             │ code UNIQUE      │
│ state        │             │ district_id FK   │
└──────────────┘             │ manager_user_id  │
                             │ ...              │
                             └───────┬──────────┘
                                     │ 1
                                     │
                        ┌────────────▼──────────┐
                        │      centre_staff     │
                        ├───────────────────────┤
                        │centre_id FK           │
                        │user_id FK             │
                        │role (man/op)          │
                        └───────────────────────┘

┌──────────────┐     1     N ┌──────────────────┐
│ procurement_centres │──────│      slots        │
└──────────────┘            ├──────────────────┤
                             │ id PK            │
                             │ centre_id FK     │
                             │ date             │
                             │ start_time       │
                             │ end_time         │
                             │ capacity         │
                             │ booked_count     │
                             │ status           │
                             └──────────────────┘

┌──────────────┐     1     N ┌──────────────────┐
│     users    │────────────▶│     bookings     │
└──────────────┘            ├──────────────────┤
                             │ id PK            │
                             │ booking_number   │
                             │ user_id FK       │
                             │ centre_id FK ───┼→ centres
                             │ slot_id FK    ───┼→ slots
                             │ date             │
                             │ status           │
                             └───────┬──────────┘
                                     │ 1
                        ┌────────────▼──────────┐
                        │     booking_crops     │
                        ├───────────────────────┤
                        │ id PK                 │
                        │ booking_id FK         │
                        │ crop_name             │
                        │ variety               │
                        │ quantity_kg           │
                        └───────────────────────┘

                             ┌──────────────────┐  1:N  ┌───────────────────────┐
                             │     bookings     │──────▶│       tokens          │
                             └──────────────────┘       ├───────────────────────┤ 1:1
                             │                          │ id PK                 │   │
                             │                          │ booking_id UNIQUE FK  │   │
                             │                          │ token_number UNIQUE   │   │
                             │                          └───────────────────────┘   │
                             │                                                       │
                             ▼                                                       │
                     ┌──────────────┐                                          ┌────▼─────────────┐
                     │queue_entries │  1:1 (booking)                          │                  │
                     ├──────────────┤                                          │                  │
                     │id PK         │                                          │                  │
                     │booking_id FK │                                          │                  │
                     │centre_id FK  │                                          │                  │
                     │date          │                                          │                  │
                     │status        │                                          │                  │
                     │position      │                                          │                  │
                     └──────────────┘                                          │                  │
                                                                               │                  │
┌──────────────────┐    N:1 (booking)    1:N (per crop in booking)      ┌────▼─────────────┐
│  bookings        │────────────────────▶│  procurements               │  payments        │
└──────────────────┘                     ├─────────────────────────────┤                  │
                                         │ id PK                       │ 1:1 (procurement)│
                                         │ booking_id FK               │                  │
                                         │ booking_crop_id FK         │                  │
                                         │ crop_name                   │                  │
                                         │ quantity_kg                 │                  │
                                         │ status                      │                  │
                                         │ rate_per_kg, amount         │                  │
                                         └─────────────────────────────┘                  │
```

### Key Relationships (summary)

1. **roles ↔ users** - 1:N (a user has one role, a role has many users)
2. **roles ↔ permissions** - M:N via `role_permissions`
3. **users ↔ permissions** - M:N via `user_permissions` (overrides)
4. **users ↔ farmers** - 1:1 (each farmer user has one farmer profile)
5. **users ↔ user_sessions** - 1:N
6. **users ↔ remember_tokens** - 1:N
7. **users ↔ user_devices** - 1:N
8. **users ↔ login_history** - 1:N
9. **districts ↔ procurement_centres** - 1:N
10. **procurement_centres ↔ centre_staff** - 1:N
11. **users ↔ centre_staff** - 1:N
12. **procurement_centres ↔ slots** - 1:N
13. **users ↔ bookings** - 1:N (farmer has many bookings)
14. **procurement_centres ↔ bookings** - 1:N
15. **slots ↔ bookings** - 1:N
16. **bookings ↔ booking_crops** - 1:N (**one booking = many crops**)
17. **bookings ↔ tokens** - 1:1 (one token per booking)
18. **bookings ↔ queue_entries** - 1:1 (**one booking = one queue entry**)
19. **procurement_centres ↔ queue_entries** - 1:N
20. **bookings ↔ procurements** - 1:N (**one booking = many procurements, one per crop**)
21. **bookings ↔ booking_crops ↔ procurements** - crop→procurement reference
22. **procurements ↔ payments** - 1:1 (each procurement → one payment)
23. **users ↔ notifications** - 1:N
24. **notifications ↔ notification_logs** - 1:N
25. **languages ↔ translations** - 1:N
26. **file_folders ↔ files** - 1:N (self-referencing folders)
27. **files ↔ file_references** - 1:N
28. **users ↔ audit_logs** - 1:N
29. **users ↔ support_requests** - 1:N

### The Multi-Crop / Multi-Procurement Relationship (Critical)

```
        booking
           │
           │ 1
           ├───────────── one queue entry (token)
           │
           │ N (booking_crops)
           ├──→ booking_crop (Wheat) ──→ procurement (Wheat) ──→ payment (Wheat)
           ├──→ booking_crop (Soybean) ──→ procurement (Soybean) ──→ payment (Soybean)
           └──→ booking_crop (Gram) ──→ procurement (Gram) ──→ payment (Gram)
```

- **1 booking** → **1 token** → **1 queue entry**
- **1 booking** → **N booking_crops**
- **N booking_crops** → **N procurements** (one per crop)
- **N procurements** → **N payments** (one per procurement)

---

**Next**: [11-authentication.md](11-authentication.md) for authentication architecture.