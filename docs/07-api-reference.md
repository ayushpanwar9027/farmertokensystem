# API Reference

Complete API contract for v1. Base URL: `https://your-domain.com/api/v1/`

## Response Envelope

All responses follow:

```json
{
  "success": true,
  "data": {},
  "meta": { "timestamp": "2026-09-08T10:30:00.000Z", "request_id": "..." }
}
```

Errors:

```json
{
  "success": false,
  "error": { "code": "...", "message": "...", "details": {} },
  "meta": {}
}
```

---

## 1. HEALTH

### GET /health
Public. System health check.

**Response 200**
```json
{
  "success": true,
  "data": {
    "status": "UP",
    "database": "UP",
    "storage": "UP",
    "version": "1.0.0",
    "time": "2026-09-08T10:30:00.000Z"
  }
}
```

### GET /health/maintenance
Public. Check if the system is in maintenance mode.

**Response 200**
```json
{
  "success": true,
  "data": {
    "maintenance": false,
    "message": null,
    "expected_available_at": null,
    "support_contact": null
  }
}
```

---

## 2. AUTHENTICATION (Farmer)

All endpoints below are for Farmer registration/login (Flutter app + Web login for staff uses `/staff/auth`).

### POST /auth/register
Public. Step 1: Register with mobile number.

**Request**
```json
{
  "mobile": "9876543210",
  "device_id": "fps_1234abcd_efgh5678"
}
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "verification_id": "ver_8f3a2b4c",
    "otp_required": true,
    "resend_after": 60,
    "existing_user": false
  }
}
```

**Errors:**
- 400 `VALIDATION_ERROR` - invalid mobile
- 409 `ALREADY_REGISTERED` - account already exists (suggest login)

### POST /auth/verify-otp
Public. Step 2: Verify OTP.

**Request**
```json
{
  "verification_id": "ver_8f3a2b4c",
  "otp": "482913"
}
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "verified": true,
    "registration_token": "reg_token_abc123"
  }
}
```

**Errors:**
- 400 `INVALID_OTP` - wrong code
- 400 `OTP_EXPIRED` - code expired
- 429 `RATE_LIMITED` - too many attempts

### POST /auth/resend-otp
Public. Resend OTP after cooldown.

**Request**
```json
{
  "verification_id": "ver_8f3a2b4c"
}
```

**Response 200**
```json
{ "success": true, "data": { "resend_after": 60 } }
```

### POST /auth/complete-registration
Public. Step 3: Complete farmer details + set password.

**Request**
```json
{
  "registration_token": "reg_token_abc123",
  "name": "Ramesh Kumar",
  "password": "SecurePass@123",
  "password_confirmation": "SecurePass@123",
  "village": "Belapur",
  "district_id": 5,
  "state": "Maharashtra",
  "land_area_acres": 2.5,
  "primary_crops": ["Wheat", "Soybean"],
  "aadhaar_last4": "1234"
}
```

**Response 201**
```json
{
  "success": true,
  "data": {
    "farmer": {
      "id": 101,
      "name": "Ramesh Kumar",
      "mobile": "9876543210",
      "status": "PENDING"
    },
    "message": "Registration submitted for verification"
  }
}
```

**Errors:**
- 400 `VALIDATION_ERROR`
- 401 `INVALID_TOKEN` - registration token expired/invalid

### POST /auth/login
Public (but rate limited). Login with mobile + password. If the user has 2FA enabled, returns **202** with `two_factor_required`.

**Request**
```json
{
  "mobile": "9876543210",
  "password": "SecurePass@123",
  "device_id": "fps_1234abcd_efgh5678",
  "device_name": "Samsung Galaxy M31",
  "platform": "android",
  "app_version": "1.0.0",
  "remember_me": true
}
```

**Response 200** (2FA disabled — immediate tokens)
```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "sha256hash_of_refresh_token...",
    "token_type": "bearer",
    "expires_in": 900,
    "remember_token": "rm_9f3b21c4d5e6..." /* if remember_me=true */,
    "user": {
      "id": 101,
      "name": "Ramesh Kumar",
      "mobile": "9876543210",
      "role": "FARMER",
      "status": "APPROVED",
      "two_factor_enabled": false,
      "farmer": {
        "id": 101,
        "verification_status": "APPROVED",
        "village": "Belapur",
        "district_id": 5
      }
    },
    "permissions": ["view_own_bookings", "book_slot"]
  }
}
```

**Response 202** (2FA enabled — must call /auth/verify-2fa)
```json
{
  "success": true,
  "code": "TWO_FA_REQUIRED",
  "data": {
    "two_factor_required": true,
    "verification_id": "2fa_a1b2c3d4",
    "resend_after": 60,
    "expires_in": 300
  }
}
```

**Errors:**
- 400 `VALIDATION_ERROR`
- 404 `ACCOUNT_NOT_FOUND`
- 403 `ACCOUNT_PENDING` - verification pending
- 403 `ACCOUNT_REJECTED` - verification rejected (with reason in message)
- 401 `INVALID_CREDENTIALS`

### POST /auth/verify-2fa
Public (but rate limited). Step 2 of 2FA login. Verify OTP sent by SMS (`otp.login_2fa` template), returns tokens.

**Request**
```json
{
  "verification_id": "2fa_a1b2c3d4",
  "otp": "482913",
  "device_id": "fps_1234abcd_efgh5678",
  "device_name": "Samsung Galaxy M31",
  "platform": "android",
  "app_version": "1.0.0",
  "remember_me": true
}
```

**Response 200** — same structure as login response 200 above (tokens + user).

**Errors:**
- 400 `VALIDATION_ERROR`
- 401 `INVALID_2FA` / `OTP_EXPIRED`
- 429 `RATE_LIMITED`

### POST /auth/resend-2fa
Public (but rate limited). Resend 2FA OTP after cooldown (60s).

**Request**
```json
{ "verification_id": "2fa_a1b2c3d4" }
```

**Response 200**
```json
{ "success": true, "data": { "resend_after": 60 } }
```

### POST /auth/2fa/enable
Authenticated. Issue OTP to enable 2FA on the account (sent via `otp.mobile_change`-style template step). Must confirm with `/auth/2fa/enable/confirm` before the second attempt is opened on the next login.

**Response 200**
```json
{ "success": true, "data": { "verification_id": "2fa_en_5566", "resend_after": 60 } }
```

### POST /auth/2fa/enable/confirm
Authenticated. Verify enable OTP → sets `two_factor_enabled = 1`.

**Request**
```json
{ "verification_id": "2fa_en_5566", "otp": "482913" }
```

**Response 200**
```json
{ "success": true, "data": { "two_factor_enabled": true } }
```

### POST /auth/2fa/disable
Authenticated. Verify a fresh 2FA OTP (`2fa_disable`) → sets `two_factor_enabled = 0`. Audited.

**Request**
```json
{ "mobile": "9876543210", "otp": "482913" }
```

**Response 200**
```json
{ "success": true, "data": { "two_factor_enabled": false } }
```

### POST /auth/2fa/challenge
Authenticated. Issue a fresh OTP for step-up on a sensitive action already logged in.

**Response 200**
```json
{ "success": true, "data": { "challenge_id": "step_7788", "resend_after": 60, "expires_in": 300 } }
```

### POST /auth/2fa/confirm
Authenticated. Verify step-up OTP → returns short-lived claim to include on sensitive requests.

**Request**
```json
{ "challenge_id": "step_7788", "otp": "482913", "purpose": "mobile_change" }
```

**Response 200**
```json
{ "success": true, "data": { "claim": "stepup_claim_token", "expires_in": 300 } }
```

### POST /auth/devices
Authenticated. Register/update the OneSignal player_id for push notifications.

**Request**
```json
{ "onesignal_player_id": "44120bc3-5f6a-4b06-9fc7-ef3d5226ad3c" }
```

**Response 200**
```json
{ "success": true, "data": { "message": "Device registered for push" } }
```

### POST /auth/logout
Authenticated. Revoke current session/token.

**Request** (optional body for Flutter)
```json
{
  "refresh_token": "...",
  "revoke_all_devices": false
}
```

**Response 200**
```json
{ "success": true, "data": { "message": "Logged out" } }
```

### POST /auth/refresh
Authenticated (refresh token). Get new access token.

**Request**
```json
{
  "refresh_token": "..."
}
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "access_token": "new_access_token...",
    "refresh_token": "new_refresh_token...",
    "expires_in": 900
  }
}
```

**Errors:**
- 401 `TOKEN_INVALID`
- 401 `TOKEN_REVOKED`
- 400 `TOKEN_EXPIRED` (must re-login)

### GET /auth/me
Authenticated. Current user profile.

**Response 200**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "name": "Ramesh Kumar",
    "mobile": "9876543210",
    "role": "FARMER",
    "status": "APPROVED",
    "farmer": { "id": 101, "verification_status": "APPROVED", "village": "Belapur" }
  }
}
```

### POST /auth/forgot-password
Public. Request OTP to reset password.

**Request**
```json
{ "mobile": "9876543210" }
```

**Response 200**
```json
{
  "success": true,
  "data": { "reset_id": "rst_abc123", "resend_after": 60 }
}
```

### POST /auth/reset-password
Public. Reset password with OTP + new password.

**Request**
```json
{
  "reset_id": "rst_abc123",
  "otp": "482913",
  "password": "NewSecurePass@123",
  "password_confirmation": "NewSecurePass@123"
}
```

**Response 200**
```json
{ "success": true, "data": { "message": "Password reset successful" } }
```

---

## 3. STAFF AUTH (Web Portal)

### POST /staff/auth/login
Public (custom staff login). Staff use this with mobile/username + password.

**Request**
```json
{
  "username": "operator1",
  "mobile": "9876543210",
  "password": "StaffPass@123",
  "remember_me": true
}
```

**Response 200** (session cookie set)
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 50,
      "name": "Operator One",
      "username": "operator1",
      "role": "CENTRE_OPERATOR",
      "status": "ACTIVE",
      "centre": { "id": 3, "name": "Centre A" },
      "district": { "id": 5, "name": "Pune" }
    },
    "permissions": [
      "view_queue", "manage_queue", "view_procurement", "manage_procurement"
    ]
  },
  "meta": { "session_lifetime": 1800 }
}
```

### POST /staff/auth/logout
Authenticated. Staff logout (destroys session).

### GET /staff/auth/me
Authenticated. Current staff user + permissions + scope.

Example scope response:
```json
{
  "success": true,
  "data": {
    "user": { "id": 50, "name": "Operator One", "role": "CENTRE_OPERATOR" },
    "permissions": ["view_queue", "manage_queue"],
    "scope": {
      "type": "centre",
      "centre_id": 3,
      "centre_name": "Centre A",
      "district_id": 5
    }
  }
}
```

### POST /staff/auth/change-password
Authenticated. Change own password.

**Request**
```json
{
  "current_password": "oldPass@123",
  "new_password": "newPass@123",
  "new_password_confirmation": "newPass@123"
}
```

---

## 4. LANGUAGES

### GET /languages
Public. Available languages for selection screen.

**Response 200**
```json
{
  "success": true,
  "data": [
    { "code": "en", "name": "English", "native_name": "English", "is_default": true },
    { "code": "hi", "name": "Hindi", "native_name": "हिन्दी", "is_default": false }
  ]
}
```

Admin endpoints (in Settings module below):

---

## 5. DISTRICTS

### GET /districts
Authenticated. List districts (filtered by scope).

**Response 200**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Pune", "state": "Maharashtra", "code": "PNQ" },
    { "id": 5, "name": "Nagpur", "state": "Maharashtra", "code": "NGP" }
  ]
}
```

---

## 6. CENTRES

### GET /centres
Authenticated (can be public for browse). List with filters + pagination.

| Param | Type | Description |
|-------|------|-------------|
| `district_id` | int | Filter by district |
| `status` | str | `active`, `inactive` |
| `search` | str | Name/code search |
| `page` | int | Page number |
| `per_page` | int | Items per page |
| `sort` | str | `name`, `-name`, `district`, `-created_at` |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "name": "APMC Pune - Grain Market",
      "code": "APMCPNQ",
      "district": { "id": 5, "name": "Pune" },
      "address": "Grain Market Yard, Pune",
      "contact_phone": "020-1234567",
      "working_hours_start": "09:00:00",
      "working_hours_end": "17:00:00",
      "working_days": ["MON","TUE","WED","THU","FRI","SAT"],
      "daily_capacity": 200,
      "status": "ACTIVE",
      "manager": { "id": 40, "name": "Mr. Sharma" }
    }
  ],
  "meta": {
    "pagination": { "current_page": 1, "per_page": 20, "total": 50, "total_pages": 3 }
  }
}
```

### GET /centres/{id}
Authenticated. Single centre detail.

**Response 200**
```json
{
  "success": true,
  "data": {
    "id": 3,
    "name": "APMC Pune - Grain Market",
    "code": "APMCPNQ",
    "district": { "id": 5, "name": "Pune" },
    "address": "Grain Market Yard, Pune",
    "contact_phone": "020-1234567",
    "contact_email": "centrea@example.com",
    "working_hours_start": "09:00:00",
    "working_hours_end": "17:00:00",
    "working_days": ["MON","TUE","WED","THU","FRI"],
    "daily_capacity": 200,
    "slot_duration_minutes": 30,
    "status": "ACTIVE",
    "manager": { "id": 40, "name": "Mr. Sharma" },
    "operators": [ { "id": 50, "name": "Operator One" } ],
    "has_active_bookings_today": true
  }
}
```

### POST /centres
Authenticated + permission `manage_centres`.

**Request**
```json
{
  "name": "APMC Nagpur - Rice Market",
  "code": "APMCNGP",
  "district_id": 5,
  "address": "Rice Market Yard, Nagpur",
  "contact_phone": "0712-1234567",
  "contact_email": "centrengp@example.com",
  "working_hours_start": "09:00:00",
  "working_hours_end": "17:00:00",
  "working_days": ["MON","TUE","WED","THU","FRI"],
  "daily_capacity": 150,
  "slot_duration_minutes": 30
}
```

**Response 201** - created centre object.

**Errors:**
- 400 `VALIDATION_ERROR`
- 403 `FORBIDDEN` (scope)
- 409 `DUPLICATE_VALUE` (code already exists)

### PUT /centres/{id}
Authenticated + permission `manage_centres`. Update centre.

### PATCH /centres/{id}/status
Authenticated + permission `manage_centres`. Activate/deactivate.

**Request**
```json
{ "status": "ACTIVE" }
```
or
```json
{ "status": "INACTIVE" }
```

### GET /centres/{id}/staff
Authenticated + scope. List centre staff.

### POST /centres/{id}/staff
Authenticated + permission `manage_centres`. Assign staff to centre.

**Request**
```json
{ "staff_user_id": 48 }
```

### GET /centres/{id}/available-slots
Authenticated. Available slots for a given date (farmer-facing).

| Param | Type | Description |
|-------|------|-------------|
| `date` | str | `YYYY-MM-DD` (required) |

**Response 200**
```json
{
  "success": true,
  "data": {
    "date": "2026-09-10",
    "slots": [
      { "id": 501, "start_time": "09:00:00", "end_time": "09:30:00", "capacity": 10, "booked": 7, "remaining": 3, "status": "ACTIVE" },
      { "id": 502, "start_time": "09:30:00", "end_time": "10:00:00", "capacity": 10, "booked": 10, "remaining": 0, "status": "FULL" }
    ]
  }
}
```

---

## 7. SLOTS

### POST /slots
Authenticated + permission `manage_slots`. Create slot (single or bulk).

**Single Request**
```json
{
  "centre_id": 3,
  "date": "2026-09-10",
  "start_time": "09:00:00",
  "end_time": "09:30:00",
  "capacity": 10
}
```

**Bulk Request**
```json
{
  "centre_id": 3,
  "date": "2026-09-10",
  "slots": [
    { "start_time": "09:00:00", "end_time": "09:30:00", "capacity": 10 },
    { "start_time": "09:30:00", "end_time": "10:00:00", "capacity": 10 },
    { "start_time": "10:00:00", "end_time": "10:30:00", "capacity": 10 }
  ]
}
```

**Response 201**
```json
{
  "success": true,
  "data": {
    "created": [501, 502, 503],
    "skipped": [
      { "start_time": "09:00:00", "reason": "DUPLICATE" }
    ]
  }
}
```

**Errors:**
- 400 `VALIDATION_ERROR`
- 409 `CONFLICT` - overlapping/duplicate slot

### GET /slots
Authenticated + scope. List slots with filters.

| Param | Description |
|-------|-------------|
| `centre_id` | Filter by centre |
| `date` | Filter by date (`YYYY-MM-DD`) |
| `from`/`to` | Date range |
| `status` | `active`, `inactive`, `full` |
| `page`/`per_page` | Pagination |

### PUT /slots/{id}
Authenticated + permission `manage_slots`. Update slot.

### PATCH /slots/{id}/status
Authenticated + permission `manage_slots`. Activate/deactivate.

**Request**
```json
{ "status": "INACTIVE" }
```

### DELETE /slots/{id}
Authenticated + permission `manage_slots`. Soft delete (reject if has confirmed bookings).

---

## 8. BOOKINGS

### POST /bookings
Authenticated (farmer) + permission `book_slot`.

**Request**
```json
{
  "centre_id": 3,
  "slot_id": 501,
  "date": "2026-09-10",
  "crops": [
    { "crop_name": "Wheat", "quantity_kg": 500, "variety": "Lok-1" },
    { "crop_name": "Soybean", "quantity_kg": 300, "variety": "JS-9560" }
  ]
}
```

**Response 201** (transactional)
```json
{
  "success": true,
  "data": {
    "booking": {
      "id": 1001,
      "booking_number": "BK-20260910-0042",
      "date": "2026-09-10",
      "status": "CONFIRMED",
      "centre": { "id": 3, "name": "APMC Pune - Grain Market" },
      "slot": { "id": 501, "start_time": "09:00:00", "end_time": "09:30:00" }
    },
    "token": {
      "id": 2001,
      "token_number": "APMCPNQ-20260910-0042",
      "qr_data": "FPS|1001|APMCPNQ-20260910-0042"
    },
    "queue_entry": {
      "id": 3001,
      "position": 3,
      "status": "WAITING"
    },
    "crops": [
      { "id": 4001, "crop_name": "Wheat", "quantity_kg": 500, "procurement_status": "PENDING" },
      { "id": 4002, "crop_name": "Soybean", "quantity_kg": 300, "procurement_status": "PENDING" }
    ]
  }
}
```

**Errors:**
- 400 `VALIDATION_ERROR`
- 403 `ACCOUNT_REJECTED` / `ACCOUNT_PENDING`
- 409 `SLOT_FULL`
- 409 `DUPLICATE_BOOKING` (already has active booking same/next day)
- 409 `SLOT_UNAVAILABLE`

### GET /bookings
Authenticated (farmer). Own booking history.

| Param | Description |
|-------|-------------|
| `status` | `confirmed`, `completed`, `cancelled`, `expired` |
| `from`/`to` | Date range |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 1001,
      "booking_number": "BK-20260910-0042",
      "date": "2026-09-10",
      "status": "CONFIRMED",
      "centre": { "id": 3, "name": "APMC Pune" },
      "slot": { "id": 501, "start_time": "09:00:00" },
      "token": { "id": 2001, "token_number": "APMCPNQ-20260910-0042" },
      "crops": [ { "crop_name": "Wheat", "quantity_kg": 500 } ],
      "created_at": "2026-09-08T10:00:00.000Z"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 5, "total_pages": 1 } }
}
```

### GET /bookings/{id}
Authenticated + ownership/scope. Booking detail.

Includes: booking, token, queue entry, crops, procurements, payment.

### PATCH /bookings/{id}/cancel
Authenticated (farmer) within cancellation window, or staff with permission.

**Request**
```json
{ "reason": "Changed my plans", "cancelled_by": "farmer" }
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "id": 1001,
    "status": "CANCELLED",
    "cancelled_at": "2026-09-08T11:30:00.000Z",
    "cancelled_by": "farmer",
    "reason": "Changed my plans"
  }
}
```

**Errors:**
- 403 `BOOKING_NOT_CANCELLABLE` - already started/outside window
- 400 `REJECTION_REASON_REQUIRED`

---

## 9. QUEUE

### GET /queue/live
Authenticated. Live queue for a centre+date, or specific booking position.

| Param | Description |
|-------|-------------|
| `centre_id` | Required |
| `date` | Required (`YYYY-MM-DD`) |
| `booking_id` | Optional - to highlight farmer's entry |

**Response 200**
```json
{
  "success": true,
  "data": {
    "centre_id": 3,
    "date": "2026-09-10",
    "current": {
      "queue_entry_id": 3001,
      "token_number": "APMCPNQ-20260910-0042",
      "farmer_name": "Ramesh Kumar",
      "status": "CALLED",
      "called_at": "2026-09-10T09:05:00.000Z"
    },
    "queue_count": {
      "waiting": 12,
      "called": 1,
      "in_progress": 1,
      "completed": 20,
      "cancelled": 2,
      "skipped": 1
    },
    "my_entry": {
      "queue_entry_id": 3005,
      "token_number": "APMCPNQ-20260910-0046",
      "position": 8,
      "farmers_ahead": 7,
      "estimated_wait_minutes": 40,
      "status": "WAITING"
    },
    "estimated_average_procurement_time_minutes": 5
  }
}
```

### POST /queue/call-next
Authenticated + permission `manage_queue` + scope. Call next waiting farmer. **Concurrency-protected.**

**Request**
```json
{ "centre_id": 3, "date": "2026-09-10" }
```

**Response 200**
```json
{
  "success": true,
  "data": {
    "queue_entry_id": 3001,
    "token_number": "APMCPNQ-20260910-0042",
    "farmer": { "id": 101, "name": "Ramesh Kumar" },
    "booking_id": 1001,
    "status": "CALLED",
    "called_at": "2026-09-10T09:05:00.000Z",
    "called_by": { "id": 50, "name": "Operator One" }
  }
}
```

**Errors:**
- 409 `QUEUE_EMPTY` - no waiting farmers
- 409 `CONCURRENT_UPDATE` - another staff called first (retry)

### POST /queue/{queueEntryId}/arrived
Authenticated + permission `manage_queue`. Mark farmer arrived (optional tracking).

### POST /queue/{queueEntryId}/start
Authenticated + permission `manage_queue`. Transition CALLED → IN_PROGRESS.

### POST /queue/{queueEntryId}/complete
Authenticated + permission `manage_queue`. Transition IN_PROGRESS → COMPLETED.

**Request** (optional)
```json
{ "notes": "All crops processed" }
```

### POST /queue/{queueEntryId}/skip
Authenticated + permission `manage_queue`. Skip called farmer.

**Request**
```json
{ "reason": "Farmer not present" }
```

**Response 200**
```json
{
  "success": true,
  "data": { "queue_entry_id": 3001, "status": "SKIPPED", "reason": "Farmer not present" }
}
```

### GET /queue/stats
Authenticated + scope. Queue statistics for dashboard.

**Response 200**
```json
{
  "success": true,
  "data": {
    "date": "2026-09-10",
    "total_booked": 25,
    "total_arrived": 22,
    "average_wait_minutes": 18,
    "average_procurement_minutes": 6,
    "peak_hour": "11:00-12:00",
    "completion_rate": 0.84
  }
}
```

---

## 10. PROCUREMENT

### POST /bookings/{bookingId}/procurements
Authenticated + permission `manage_procurement`. Create procurement records for booking. **One booking = one queue entry, multiple crops = multiple procurements.**

**Request**
```json
{
  "crops": [
    {
      "crop_name": "Wheat",
      "variety": "Lok-1",
      "quantity_kg": 480,
      "quality_grade": "A",
      "moisture_percent": 12.5,
      "notes": "Good quality"
    },
    {
      "crop_name": "Soybean",
      "variety": "JS-9560",
      "quantity_kg": 290,
      "quality_grade": "B",
      "moisture_percent": 11.0,
      "notes": null
    }
  ]
}
```

**Response 201**
```json
{
  "success": true,
  "data": {
    "procurements": [
      {
        "id": 4001,
        "booking_id": 1001,
        "crop_name": "Wheat",
        "variety": "Lok-1",
        "quantity_kg": 480,
        "quality_grade": "A",
        "moisture_percent": 12.5,
        "status": "PENDING",
        "created_at": "2026-09-10T09:06:00.000Z"
      },
      {
        "id": 4002,
        "booking_id": 1001,
        "crop_name": "Soybean",
        "variety": "JS-9560",
        "quantity_kg": 290,
        "quality_grade": "B",
        "moisture_percent": 11.0,
        "status": "PENDING"
      }
    ],
    "queue_entry_updated": true
  }
}
```

### GET /procurements
Authenticated + scope. List with filters.

| Param | Description |
|-------|-------------|
| `status` | `pending`, `verified`, `in_progress`, `completed`, `rejected` |
| `farmer_id` | Filter by farmer |
| `centre_id` | Filter by centre |
| `date` | Filter by date |
| `from`/`to` | Date range |
| `page`/`per_page` | Pagination |

### GET /procurements/{id}
Authenticated + scope. Detail.

### PATCH /procurements/{id}/status
Authenticated + permission. Valid transitions.

**Request**
```json
{ "status": "VERIFIED", "verified_by": 50 }
```

Valid: `PENDING → VERIFIED`, `VERIFIED → IN_PROGRESS`, `IN_PROGRESS → COMPLETED`

### PATCH /procurements/{id}/reject
Authenticated + permission.

**Request**
```json
{
  "reason": "Fungal contamination detected",
  "notes": "Sample failed quality inspection"
}
```

### PATCH /procurements/{id}/correct
Authenticated + permission + audited. Correction request (weight, grade, etc.)

**Request**
```json
{
  "field": "quantity_kg",
  "old_value": 480,
  "new_value": 475,
  "reason": "Scale recalibration"
}
```
Requires approval (see approval workflow).

---

## 11. PAYMENTS

### GET /payments
Authenticated + scope. List payments (linked to procurements).

| Param | Description |
|-------|-------------|
| `status` | `pending`, `processing`, `paid`, `failed` |
| `farmer_id` | Filter |
| `centre_id` | Filter |
| `date` | Filter |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 6001,
      "procurement_id": 4001,
      "farmer": { "id": 101, "name": "Ramesh Kumar" },
      "crop": "Wheat",
      "quantity_kg": 480,
      "rate_per_kg": 22.50,
      "amount": 10800.00,
      "status": "PENDING",
      "reference": null
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 3, "total_pages": 1 } }
}
```

### GET /payments/{id}
Authenticated + scope. Detail.

### PATCH /payments/{id}/status
Authenticated + permission `manage_payments`. Sensitive update.

**Request**
```json
{
  "status": "PAID",
  "reference": "UTR147258369",
  "payment_method": "NEFT",
  "notes": "Paid via bank transfer"
}
```

**Errors:**
- 403 `FORBIDDEN` - only authorized roles
- 409 `PAYMENT_ALREADY_PAID` - cannot unpay
- 400 `REJECTION_REASON_REQUIRED` - for FAILED, reason required

### POST /payments/{id}/correction
Authenticated + permission + audit. Request payment correction.

---

## 12. NOTIFICATIONS

### GET /notifications
Authenticated (farmer). Notification list.

| Param | Description |
|-------|-------------|
| `type` | Filter by type |
| `read` | `true`/`false` |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 7001,
      "type": "BOOKING_CONFIRMED",
      "title": "Booking Confirmed",
      "message": "Your booking for APMC Pune on 2026-09-10 is confirmed. Token: APMCPNQ-20260910-0042",
      "is_read": false,
      "created_at": "2026-09-08T10:05:00.000Z"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 2, "total_pages": 1 } }
}
```

### GET /notifications/{id}
Authenticated + ownership. Detail.

### PATCH /notifications/{id}/read
Authenticated + ownership. Mark read.

**Response 200**
```json
{ "success": true, "data": { "id": 7001, "is_read": true } }
```

### PATCH /notifications/read-all
Authenticated. Mark all read.

---

## 13. FARMER PROFILE

### GET /profile
Authenticated (farmer). Full farmer profile.

**Response 200**
```json
{
  "success": true,
  "data": {
    "id": 101,
    "name": "Ramesh Kumar",
    "mobile": "9876543210",
    "village": "Belapur",
    "district": { "id": 5, "name": "Pune" },
    "state": "Maharashtra",
    "land_area_acres": 2.5,
    "primary_crops": ["Wheat", "Soybean"],
    "verification_status": "APPROVED",
    "verification_rejected_reason": null,
    "created_at": "2026-09-01T10:00:00.000Z"
  }
}
```

### PUT /profile
Authenticated (farmer). Update profile (except mobile/verified fields).

**Request**
```json
{
  "name": "Ramesh Kumar",
  "village": "Belapur",
  "land_area_acres": 3.0,
  "primary_crops": ["Wheat", "Soybean", "Gram"]
}
```

### PATCH /profile/change-password
Authenticated. Change password.

---

## 14. SUPPORT

### POST /support/requests
Authenticated. Create support request.

**Request**
```json
{
  "subject": "Booking not reflected",
  "category": "booking",
  "message": "I booked a slot but it's not showing in my history",
  "booking_id": 1001
}
```

### GET /support/requests
Authenticated. My support requests.

### POST /support/requests/{id}/reply
Authenticated (owner) or staff with permission.

---

## 15. STAFF MANAGEMENT (Admin)

### GET /staff
Authenticated + permission `manage_staff` + scope.

| Param | Description |
|-------|-------------|
| `role` | `centre_operator`, `centre_manager`, `district_admin` |
| `status` | `active`, `inactive`, `pending` |
| `centre_id` | Filter |
| `search` | Search |
| `page`/`per_page` | Pagination |

### POST /staff
Authenticated + permission `manage_staff`. Create staff account.

**Request**
```json
{
  "name": "New Operator",
  "mobile": "9876501234",
  "email": "op@example.com",
  "username": "op_new",
  "role": "CENTRE_OPERATOR",
  "centre_id": 3,
  "district_id": 5,
  "permissions": ["view_queue", "manage_queue"]
}
```

**Response 201**
```json
{
  "success": true,
  "data": {
    "id": 52,
    "name": "New Operator",
    "username": "op_new",
    "mobile": "9876501234",
    "role": "CENTRE_OPERATOR",
    "status": "ACTIVE",
    "centre_id": 3,
    "district_id": 5,
    "temporary_password": "Tmp@12345"
  }
}
```

### GET /staff/{id}
Authenticated + scope. Staff detail (includes permissions).

### PUT /staff/{id}
Authenticated + permission. Update staff (name, contact, role, centre).

### PATCH /staff/{id}/status
Authenticated + permission. Activate/deactivate.

### PATCH /staff/{id}/permissions
Authenticated + permission `manage_staff`. Override permissions.

**Request**
```json
{
  "permissions": ["view_queue", "manage_queue", "view_procurement"],
  "reason": "Added procurement access for week"
}
```

### POST /staff/{id}/reset-password
Authenticated + permission. Generate temporary password.

### DELETE /staff/{id}
Authenticated + permission. Deactivate (soft delete). Cannot self-delete.

---

## 16. REPORTS

### GET /reports/dashboard
Authenticated + staff. Dashboard metrics (scope-based).

**Response 200**
```json
{
  "success": true,
  "data": {
    "today": {
      "bookings": 45,
      "confirmed": 42,
      "completed": 30,
      "pending": 12,
      "cancelled": 3,
      "average_wait_min": 18
    },
    "this_week": {
      "bookings": 280,
      "completed": 210,
      "revenue_estimate": 125000.00
    },
    "active_farmers": 1200,
    "centres_active": 5,
    "slot_utilization_percent": 78
  }
}
```

### GET /reports/centre-performance
Authenticated + scope.

| Param | Description |
|-------|-------------|
| `centre_id` | Filter |
| `from`/`to` | Date range |

Chart-ready data (for Chart.js).

### GET /reports/procurement
Summations by crop, category, quality, etc.

---

## 17. AUDIT LOGS

### GET /audit-logs
Authenticated + permission `view_audit_logs` + scope.

| Param | Description |
|-------|-------------|
| `user_id` | Filter |
| `action` | Filter |
| `module` | Filter |
| `entity_type` | Filter |
| `centre_id` | Filter |
| `district_id` | Filter |
| `from`/`to` | Date range |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 9001,
      "user": { "id": 50, "name": "Operator One", "role": "CENTRE_OPERATOR" },
      "action": "CALL_NEXT",
      "module": "QUEUE",
      "entity_type": "queue_entry",
      "entity_id": 3001,
      "old_value": null,
      "new_value": { "status": "CALLED" },
      "reason": null,
      "ip": "192.168.1.100",
      "created_at": "2026-09-10T09:05:00.000Z"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 156, "total_pages": 8 } }
}
```

---

## 18. SYSTEM SETTINGS (Super Admin)

### GET /settings
Authenticated + scope. Safe settings (public subset returned to non-admin).

### GET /settings/admin
Authenticated + permission `manage_system_settings`. All settings.

**Response 200**
```json
{
  "success": true,
  "data": {
    "system_name": "Farmer Procurement System",
    "default_language": "en",
    "maintenance_mode": false,
    "maintenance_message": "",
    "booking_cancellation_window_minutes": 120,
    "queue_notification_threshold": 3,
    "sms_enabled": true,
    "notification_retry_count": 3,
    "file_upload_max_size_mb": 5,
    "support_contact_phone": "1800-123-456",
    "support_contact_email": "support@example.com",
    "timezone": "Asia/Kolkata"
  }
}
```

### PUT /settings
Authenticated + permission `manage_system_settings`. Update settings.

**Request**
```json
{
  "system_name": "Farmer Procurement System",
  "booking_cancellation_window_minutes": 180,
  "queue_notification_threshold": 5,
  "support_contact_phone": "1800-789-456"
}
```

---

## 19. SECRETS (Super Admin)

### GET /secrets
Authenticated + permission `manage_secrets`. List secrets (masked).

**Response 200**
```json
{
  "success": true,
  "data": {
    "secrets": [
      {
        "key": "onesignal_app_id",
        "name": "OneSignal App ID",
        "masked_value": "••••••••••••••••",
        "is_set": false,
        "last_updated_at": null
      },
      {
        "key": "onesignal_rest_api_key",
        "name": "OneSignal REST API Key",
        "masked_value": "••••••••••••",
        "is_set": false,
        "last_updated_at": null
      },
      {
        "key": "otp_api_key",
        "name": "OTP Gateway API Key",
        "masked_value": "••••••••••••",
        "is_set": false,
        "last_updated_at": null
      }
    ]
  }
}
```

> **Note**: For the initial release, OneSignal + OTP keys are **hardcoded in `config/onesignal.php` / `config/otp.php`**. The entries above are placeholders for the later phase when they move into encrypted storage.

### PUT /secrets/{key}
Authenticated + permission `manage_secrets`. Set/update secret.

**Request**
```json
{ "value": "new-auth-token-value" }
```

**Response 200**
```json
{
  "success": true,
  "data": { "key": "onesignal_rest_api_key", "is_set": true, "updated_at": "2026-09-08T12:00:00.000Z" }
}
```

### POST /secrets/{key}/rotate
Authenticated + permission `manage_secrets`. Generate a new secret (for supported types, e.g., encryption keys).

### POST /secrets/send-test-sms
Authenticated + permission `manage_secrets`. Send test OTP-style SMS to verify OTP gateway config.

**Request**
```json
{ "test_phone": "9876543210", "template": "otp.register" }
```

### POST /notifications/test-push
Authenticated + permission (staff/manager). Send test OneSignal push.

**Request**
```json
{ "onesignal_player_id": "44120bc3-5f6a-4b06-9fc7-ef3d5226ad3c", "title": "Test", "message": "OneSignal works" }
```

---

## 20. FILE MANAGER (Super Admin)

### GET /files
Authenticated + permission `manage_files`. List files/folders.

| Param | Description |
|-------|-------------|
| `folder_id` | Root if empty |
| `search` | Name search |
| `type` | `file`, `folder`, `image`, `document` |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": {
    "items": [
      { "id": 101, "type": "folder", "name": "Logos", "children": 3 },
      { "id": 102, "type": "file", "name": "wheat-image.jpg", "mime_type": "image/jpeg", "size": 245000, "url": "/files/102/preview" }
    ]
  }
}
```

### POST /files/upload
Authenticated + permission `manage_files`. Multipart upload.

Form fields: `file` (binary), `folder_id` (optional), `name` (optional rename).

### POST /files/url
Authenticated + permission `manage_files`. Add image by URL.

**Request**
```json
{ "url": "https://example.com/image.jpg", "folder_id": 101 }
```

### POST /files/folders
Authenticated + permission `manage_files`. Create folder.

**Request**
```json
{ "name": "Logos", "parent_id": null }
```

### PUT /files/{id}
Authenticated + permission. Rename.

**Request**
```json
{ "name": "new-name.jpg" }
```

### POST /files/move
Authenticated + permission. Move file(s)/folder(s).

**Request**
```json
{ "items": [101, 102], "target_folder_id": 105 }
```

Response includes `references_affected` count. UI asks "Fix Locations?".

### POST /files/copy
Authenticated + permission. Copy.

**Request**
```json
{ "items": [102], "target_folder_id": 105 }
```

### DELETE /files/{id}
Authenticated + permission. Soft delete.

### GET /files/{id}/preview
Authenticated + permission. Preview metadata.

### GET /files/{id}/download
Authenticated + permission. Download file.

---

## 21. LANGUAGE MANAGEMENT (Admin)

### GET /admin/languages
Authenticated + permission `manage_languages`. List languages.

**Response 200**
```json
{
  "success": true,
  "data": [
    { "id": 1, "code": "en", "name": "English", "native_name": "English", "is_default": true, "is_enabled": true, "translation_count": 200 }
  ]
}
```

### POST /admin/languages
Authenticated + permission `manage_languages`. Add language.

**Request**
```json
{ "code": "mr", "name": "Marathi", "native_name": "मराठी" }
```

### PUT /admin/languages/{id}
Authenticated + permission. Update (enable/disable/default).

### DELETE /admin/languages/{id}
Authenticated + permission. Safe delete (no active users in that language).

### GET /admin/languages/{id}/translations
Authenticated + permission. List translations (paginated).

### PUT /admin/languages/{id}/translations
Authenticated + permission. Bulk update translations.

---

## 22. SESSIONS & LOGIN HISTORY (Admin/Auth)

### GET /auth/sessions
Authenticated (Super Admin/self). List sessions.

**Response 200**
```json
{
  "success": true,
  "data": {
    "sessions": [
      {
        "id": 8001,
        "device_name": "Samsung Galaxy M31",
        "platform": "android",
        "ip": "192.168.1.100",
        "user_agent": "Dart/3.3 (dart:io)",
        "created_at": "2026-09-08T10:00:00.000Z",
        "last_activity_at": "2026-09-08T12:00:00.000Z",
        "expires_at": "2026-09-15T10:00:00.000Z",
        "status": "ACTIVE",
        "is_current": true
      }
    ]
  }
}
```

### POST /auth/sessions/{id}/revoke
Authenticated + scope. Revoke specific session.

### POST /auth/sessions/revoke-all
Authenticated. Revoke all other sessions (except current).

### POST /auth/sessions/refresh-current
Authenticated. Extend currecurrence session lifetime.

### GET /login-history
Authenticated + scope. Login history.

| Param | Description |
|-------|-------------|
| `user_id` | Filter (admin only) |
| `status` | `success`, `failure` |
| `from`/`to` | Date range |
| `page`/`per_page` | Pagination |

**Response 200**
```json
{
  "success": true,
  "data": [
    {
      "id": 9500,
      "user": { "id": 101, "name": "Ramesh Kumar", "role": "FARMER" },
      "login_at": "2026-09-08T10:00:00.000Z",
      "logout_at": null,
      "ip": "103.21.58.4",
      "user_agent": "Dart/3.3 (dart:io)",
      "platform": "android",
      "device_name": "Samsung Galaxy M31",
      "status": "SUCCESS",
      "failure_reason": null
    },
    {
      "id": 9501,
      "user_id": 101,
      "login_at": "2026-09-08T09:30:00.000Z",
      "logout_at": null,
      "ip": "103.21.58.4",
      "status": "FAILURE",
      "failure_reason": "INVALID_PASSWORD"
    }
  ],
  "meta": { "pagination": { "current_page": 1, "per_page": 20, "total": 3, "total_pages": 1 } }
}
```

---

## 23. MAINTENANCE MODE

### PATCH /maintenance
Authenticated + permission `manage_system_settings`.

**Request**
```json
{
  "enabled": true,
  "message": "System maintenance on 2026-09-15 02:00-04:00 IST",
  "expected_available_at": "2026-09-15T04:00:00Z"
}
```

**Response 200**
```json
{
  "success": true,
  "data": { "maintenance_mode": true, "message": "...", "expected_available_at": "...", "changed_by": 1, "changed_at": "..." }
}
```

---

## SHARED ENUMS & OBJECTS

### User Object
```json
{
  "id": 101,
  "name": "Ramesh Kumar",
  "mobile": "9876543210",
  "email": "user@example.com",
  "role": "FARMER",
  "status": "APPROVED",
  "created_at": "2026-09-01T10:00:00.000Z"
}
```

### Centre Object
```json
{
  "id": 3,
  "name": "APMC Pune - Grain Market",
  "code": "APMCPNQ",
  "address": "Grain Market Yard, Pune",
  "contact_phone": "020-1234567",
  "contact_email": "centrea@example.com",
  "district_id": 5,
  "working_hours_start": "09:00:00",
  "working_hours_end": "17:00:00",
  "working_days": ["MON","TUE","WED","THU","FRI"],
  "daily_capacity": 200,
  "slot_duration_minutes": 30,
  "status": "ACTIVE",
  "manager_id": 40,
  "created_at": "2026-08-01T10:00:00.000Z"
}
```

### Booking Object
```json
{
  "id": 1001,
  "booking_number": "BK-20260910-0042",
  "farmer_id": 101,
  "centre_id": 3,
  "slot_id": 501,
  "date": "2026-09-10",
  "status": "CONFIRMED",
  "crop_count": 2,
  "total_quantity_kg": 800,
  "created_at": "2026-09-08T10:00:00.000Z",
  "cancelled_at": null,
  "cancelled_by": null,
  "cancellation_reason": null,
  "completed_at": null
}
```

### Procurement Object
```json
{
  "id": 4001,
  "booking_id": 1001,
  "crop_name": "Wheat",
  "variety": "Lok-1",
  "quantity_kg": 480,
  "quality_grade": "A",
  "moisture_percent": 12.5,
  "status": "PENDING",
  "notes": null,
  "verified_at": null,
  "verified_by": null,
  "completed_at": null,
  "completed_by": null,
  "rejection_reason": null,
  "rate_per_kg": 22.50,
  "amount": 10800.00,
  "payment_id": 6001
}
```

---

**Next**: [08-database-architecture.md](08-database-architecture.md) for database design.