# API Architecture

## Design Principles

1. **RESTful** - Resource-oriented URLs, HTTP methods, status codes
2. **JSON** - Request/response format (UTF-8, no HTML)
3. **Versioned** - `/api/v1/` prefix
4. **Stateless** - JWT for Flutter, session for Web
5. **Consistent** - Same response envelope across all endpoints
6. **Documented** - All endpoints in [07-api-reference.md](07-api-reference.md)
7. **Secure** - HTTPS, validation, authorization, rate limiting

## Base URL

```
https://your-domain.com/api/v1/
```

## Response Envelope

### Success

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": "2026-09-08T10:30:00.000Z",
    "request_id": "req_8f3a2b4c"
  }
}
```

### List (with pagination)

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Centre A" },
    { "id": 2, "name": "Centre B" }
  ],
  "meta": {
    "timestamp": "2026-09-08T10:30:00.000Z",
    "request_id": "req_8f3a2b4c",
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 45,
      "total_pages": 3,
      "next_page": 2,
      "prev_page": null
    }
  }
}
```

### Collection (with filters)

```json
{
  "success": true,
  "data": {
    "items": [ ... ],
    "filters": {
      "district_id": 5,
      "status": "active"
    }
  },
  "meta": {
    "timestamp": "2026-09-08T10:30:00.000Z",
    "request_id": "req_8f3a2b4c",
    "pagination": { ... }
  }
}
```

### Error

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid",
    "details": {
      "mobile": ["The mobile field must be 10 digits"]
    }
  },
  "meta": {
    "timestamp": "2026-09-08T10:30:00.000Z",
    "request_id": "req_8f3a2b4c"
  }
}
```

## HTTP Status Codes

| Code | When Used |
|------|-----------|
| 200 | GET/PUT/PATCH success |
| 201 | POST created |
| 202 | Accepted (async operation) |
| 204 | Success, no content |
| 400 | Validation error |
| 401 | Unauthenticated (invalid/expired token/session) |
| 403 | Forbidden (no permission/scope) |
| 404 | Resource not found |
| 409 | Conflict (duplicate, state conflict) |
| 422 | Unprocessable entity (business rule violation) |
| 429 | Too many requests (rate limited) |
| 500 | Server error |
| 503 | Service unavailable (maintenance mode) |

## Error Codes

### Authentication Errors
| Code | Message |
|------|---------|
| `UNAUTHENTICATED` | Authentication required |
| `INVALID_CREDENTIALS` | Invalid mobile or password |
| `INVALID_OTP` | Invalid OTP |
| `OTP_EXPIRED` | OTP has expired |
| `TWO_FA_REQUIRED` | 2FA OTP required (password accepted) |
| `INVALID_2FA` | Invalid 2FA OTP |
| `TOKEN_EXPIRED` | Token has expired |
| `TOKEN_INVALID` | Token is invalid |
| `TOKEN_REVOKED` | Token has been revoked |
| `SESSION_EXPIRED` | Session has expired |

### Authorization Errors
| Code | Message |
|------|---------|
| `FORBIDDEN` | You do not have permission |
| `ROLE_REQUIRED` | Required role not satisfied |
| `PERMISSION_DENIED` | Missing required permission |
| `SCOPE_DENIED` | Outside your resource scope |

### Validation Errors
| Code | Message |
|------|---------|
| `VALIDATION_ERROR` | Input validation failed |
| `REQUIRED_FIELD` | Field is required |
| `INVALID_FORMAT` | Field format is invalid |
| `INVALID_VALUE` | Field value is invalid |
| `DUPLICATE_VALUE` | Value already exists |
| `MAX_LENGTH_EXCEEDED` | Value is too long |
| `MIN_LENGTH_NOT_MET` | Value is too short |
| `INVALID_DATE` | Date format is invalid |
| `INVALID_DATE_RANGE` | Date range is invalid |

### Business Rule Errors
| Code | Message |
|------|---------|
| `SLOT_FULL` | Slot has reached capacity |
| `SLOT_UNAVAILABLE` | Slot is not available |
| `DUPLICATE_BOOKING` | You already have an active booking |
| `BOOKING_NOT_CANCELLABLE` | Booking cannot be cancelled |
| `ALREADY_CALLED` | Farmer has already been called |
| `ALREADY_STARTED` | Queue entry already in progress |
| `ALREADY_COMPLETED` | Already completed |
| `QUEUE_EMPTY` | No farmers in queue |
| `CONCURRENT_UPDATE` | Concurrent modification detected |
| `REJECTION_REASON_REQUIRED` | Reason required for rejection |
| `PAYMENT_ALREADY_PAID` | Payment already marked as paid |
| `MAINTENANCE_MODE` | System is under maintenance |

### System Errors
| Code | Message |
|------|---------|
| `SERVER_ERROR` | Unexpected server error |
| `DATABASE_ERROR` | Database operation failed |
| `EXTERNAL_SERVICE_ERROR` | External service (OneSignal/OTP gateway) failed |
| `NOT_FOUND` | Resource not found |
| `RATE_LIMITED` | Too many requests |
| `MAINTENANCE` | System under maintenance |

## Request Headers

### Common Headers
| Header | Description |
|--------|-------------|
| `Accept` | `application/json` |
| `Content-Type` | `application/json` (for POST/PUT/PATCH) |
| `Accept-Language` | `en`, `hi` (localization) |
| `X-Request-ID` | Client-generated unique ID for tracing |
| `X-Device-ID` | Stable device identifier (Flutter) |
| `X-Platform` | `android`, `ios`, `web`, `flutter`, `js` |
| `X-App-Version` | App version for compatibility checks |

### Authentication Headers
| Client | Header |
|--------|--------|
| Flutter | `Authorization: Bearer <access_token>` |
| Web | Cookie: `PHPSESSID=...` (session cookie) |
| Web (mutating) | `X-CSRF-Token: <csrf_token>` (double-submit cookie) |

## Rate Limiting Headers (Response)

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 58
X-RateLimit-Reset: 1726043400   (unix timestamp)
Retry-After: 30                  (seconds, when limited)
```

## URL Conventions

### Resource Naming
- **Plural nouns** for collections: `/centres`, `/slots`, `/bookings`
- **Singular** for singleton: `/auth/me`, `/profile`
- **Nested** for related resources: `/centres/{id}/slots`

### Query Parameters (for lists)
| Param | Purpose | Example |
|-------|---------|---------|
| `page` | Page number (default 1) | `?page=2` |
| `per_page` | Items per page (default 20, max 100) | `?per_page=50` |
| `search` | Text search | `?search=wheat` |
| `status` | Filter by status | `?status=confirmed` |
| `date` | Filter by date | `?date=2026-09-08` |
| `from` / `to` | Date range | `?from=2026-09-01&to=2026-09-08` |
| `sort` | Sort field | `?sort=-created_at` (desc) |
| `sort_by` / `sort_dir` | Alternative sort syntax | `?sort_by=created_at&sort_dir=desc` |

### Sort Syntax
- `?sort=field` → ascending
- `?sort=-field` → descending
- `?sort=field1,-field2` → multiple

## Date/Time Formats

### Request
- Date only: `YYYY-MM-DD`
- Time: `HH:mm:ss` (24-hour)
- DateTime: `YYYY-MM-DDTHH:mm:ss` (ISO 8601)
- Timezone: Server in `Asia/Kolkata`, client sends local, server converts

### Response
- All datetimes in ISO 8601 UTC: `2026-09-08T10:30:00.000Z`
- Server converts to app-local display when needed
- Timezone field in response meta if relevant

## Status Enums

### Farmer Verification
```
PENDING → APPROVED
PENDING → REJECTED (with reason)
REJECTED → PENDING (re-review)
```

### Staff Verification
```
PENDING → ACTIVE
PENDING → REJECTED
```

### Centre Status
```
ACTIVE → INACTIVE (can reactivate)
INACTIVE → ACTIVE
```

### Slot Status
```
ACTIVE → INACTIVE
ACTIVE → FULL (auto when booked)
FULL → ACTIVE (on cancellation)
```

### Booking Status
```
PENDING → CONFIRMED
CONFIRMED → COMPLETED
CONFIRMED → CANCELLED (by farmer within window)
CONFIRMED → EXPIRED (date passed without arrival)
PENDING → CANCELLED
```

### Queue Entry Status
```
WAITING → CALLED
WAITING → CANCELLED
CALLED → IN_PROGRESS
CALLED → SKIPPED
IN_PROGRESS → COMPLETED
```

### Procurement Status
```
PENDING → VERIFIED
VERIFIED → IN_PROGRESS
VERIFIED → REJECTED (with reason)
IN_PROGRESS → COMPLETED
```

### Payment Status
```
PENDING → PROCESSING
PROCESSING → PAID
PROCESSING → FAILED
PROCESSING → PENDING (retry)
```

### Notification Status
```
PENDING → ATTEMPT → SENT
PENDING → ATTEMPT → FAILED → RETRY → SENT
FAILED (after max retries)
```

## Endpoint Organization (Modules)

### 1. Health (`/health`)
- `GET /health` - Public health check
- `GET /health/maintenance` - Maintenance status

### 2. Auth (`/auth`)
- `POST /auth/register` - Farmer registration (OTP via SMS gateway)
- `POST /auth/verify-otp` - OTP verification
- `POST /auth/resend-otp` - Resend OTP
- `POST /auth/complete-registration` - Complete farmer details + account
- `POST /auth/login` - Step 1 login (password) → returns 202 `TWO_FA_REQUIRED` if 2FA enabled
- `POST /auth/verify-2fa` - Step 2: verify 2FA OTP → tokens
- `POST /auth/resend-2fa` - Resend 2FA OTP
- `POST /auth/2fa/enable` + `/auth/2fa/enable/confirm` - Enable 2FA
- `POST /auth/2fa/disable` - Disable 2FA (needs OTP)
- `POST /auth/2fa/challenge` + `/auth/2fa/confirm` - Step-up 2FA for sensitive actions
- `POST /auth/devices` - Register OneSignal player_id
- `POST /auth/mobile-change` + `/auth/mobile-change/confirm` - Change mobile with OTP
- `POST /auth/logout` - Logout
- `POST /auth/refresh` - Refresh token (Flutter)
- `GET /auth/me` - Current user
- `POST /auth/forgot-password` - Request reset (OTP template `otp.password_reset`)
- `POST /auth/reset-password` - Reset with OTP + new password

### 3. Languages (`/languages`)
- `GET /languages` - Public (for selection screen)
- Admin: CRUD with translations

### 4. Districts (`/districts`)
- `GET /districts` - Public/authenticated list

### 5. Centres (`/centres`)
- `GET /centres` - List (farmer can filter by district)
- `GET /centres/{id}` - Details
- `POST /centres` - Create (staff)
- `PUT /centres/{id}` - Update (staff)
- `PATCH /centres/{id}/status` - Activate/deactivate
- `GET /centres/{id}/staff` - Centre staff list
- `POST /centres/{id}/staff` - Assign staff

### 6. Slots (`/centres/{id}/slots`)
- `GET /slots` - Available slots for centre (date filter)
- `GET /slots/{id}` - Single slot
- `POST /slots` - Create (bulk/one)
- `PUT /slots/{id}` - Update
- `PATCH /slots/{id}/status` - Activate/deactivate
- `DELETE /slots/{id}` - Remove (soft)

### 7. Bookings (`/bookings`)
- `POST /bookings` - Create (farmer)
- `GET /bookings` - Farmer's bookings (history)
- `GET /bookings/{id}` - Booking detail
- `PATCH /bookings/{id}/cancel` - Cancel booking
- `GET /bookings/{id}/token` - Get token data

### 8. Queue (`/queue`)
- `GET /queue/live` - Live queue (centre+date, or booking)
- `POST /queue/call-next` - Call next (staff)
- `POST /queue/{id}/arrived` - Mark arrived
- `POST /queue/{id}/start` - Start procurement
- `POST /queue/{id}/complete` - Complete
- `POST /queue/{id}/skip` - Skip
- `GET /queue/stats` - Queue statistics

### 9. Procurement (`/procurements`)
- `GET /procurements` - List (scope-based)
- `GET /procurements/{id}` - Detail
- `POST /bookings/{id}/procurements` - Create for booking (multi-crop)
- `PATCH /procurements/{id}/status` - Update status
- `PATCH /procurements/{id}/reject` - Reject with reason
- `PATCH /procurements/{id}/correct` - Correction request

### 10. Payments (`/payments`)
- `GET /payments` - List (scope-based)
- `GET /payments/{id}` - Detail
- `PATCH /payments/{id}/status` - Update status (authorized only)

### 11. Notifications (`/notifications`)
- `GET /notifications` - Farmer's notifications
- `GET /notifications/{id}` - Detail
- `PATCH /notifications/{id}/read` - Mark read
- `PATCH /notifications/read-all` - Mark all read
- `POST /notifications/test-push` - Test OneSignal push (staff)

### 12. Staff (`/staff`)
- `GET /staff` - List staff (scope)
- `POST /staff` - Create staff (Super/District admin)
- `GET /staff/{id}` - Staff detail
- `PUT /staff/{id}` - Update
- `PATCH /staff/{id}/status` - Activate/deactivate
- `PATCH /staff/{id}/permissions` - Override permissions

### 13. Reports (`/reports`)
- `GET /reports/dashboard` - Dashboard metrics
- `GET /reports/bookings` - Booking report
- `GET /reports/procurement` - Procurement report
- `GET /reports/payments` - Payment report
- `GET /reports/centre-performance` - Centre performance

### 14. Audit (`/audit-logs`)
- `GET /audit-logs` - List (scope)

### 15. Settings (`/settings`)
- `GET /settings` - System settings (public-safe subset)
- `GET /settings/admin` - All settings (Super Admin)
- `PUT /settings` - Update settings

### 16. Secrets (`/secrets`)
- `GET /secrets` - Masked list (Super Admin)
- `PUT /secrets/{key}` - Update secret
- `POST /secrets/{key}/rotate` - Rotate secret

### 17. Files (`/files`)
- `GET /files?folder_id=` - List files
- `POST /files/upload` - Upload
- `POST /files/url` - Add by URL
- `POST /files/folders` - Create folder
- `PUT /files/{id}` - Rename
- `PUT /files/move` - Move file/folder
- `POST /files/copy` - Copy
- `DELETE /files/{id}` - Delete (soft)
- `GET /files/{id}/preview` - Preview metadata
- `GET /files/{id}/download` - Download

### 18. Support (`/support`)
- `POST /support/requests` - Create request (farmer)
- `GET /support/requests` - My requests
- `GET /support/requests/{id}` - Detail
- `POST /support/requests/{id}/reply` - Reply

### 19. Sessions (`/sessions`)
- `GET /sessions` - My sessions
- `POST /sessions/current/revoke` - Revoke current
- `POST /sessions/{id}/revoke` - Revoke specific
- `POST /sessions/revoke-all` - Revoke all others

### 20. Login History (`/login-history`)
- `GET /login-history` - My history
- `GET /login-history/{userId}` - Specific user (admin)

## API Versioning Rules

- Always keep `/api/v1/` prefix
- Changes backward-compatible (add fields, don't remove)
- Breaking changes → `/api/v2/`
- Deprecated endpoints return `Deprecation` header
- Client sends `X-App-Version` for feature detection

## Webhooks / Callbacks (Future)

- OneSignal delivery/click callbacks (optional): `POST /api/v1/webhooks/onesignal`
- OTP gateway delivery callback (optional, provider-dependent)
- Server-to-server: never expose

## Mock Data (Development)

During development (Phase 17 testing), provide:
- `mocks/` directory with sample JSON responses
- Dummy auth tokens
- Seeded database

## API Testing Checklist

Per endpoint:
- [ ] Success case (201/200)
- [ ] Validation failure (400)
- [ ] Auth required (401)
- [ ] Permission denied (403)
- [ ] Not found (404)
- [ ] Conflict (409)
- [ ] Rate limited (429)
- [ ] Maintenance (503)
- [ ] Pagination works
- [ ] Filters work
- [ ] Sorting works
- [ ] Localization (Accept-Language)

---

**Next**: [07-api-reference.md](07-api-reference.md) for the complete endpoint reference.