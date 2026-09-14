# Notification System

## Overview

Notification service triggers **push notifications (OneSignal)** and **in-app notifications**. **OTP messages** are sent through a dedicated OTP/SMS gateway (separate from notifications). Architecture is channel-abstracted so more channels can be added without redesign.

## Notification Channels (Abstraction)

```
NotificationService
      │
      ├── Push Channel (OneSignal)   ← primary for alerts, implemented
      ├── In-App Channel             ← implemented (notifications table)
      └── SMS Channel (OTP only)     ← via OTP gateway, for OTP messages
```

### Interface
```php
interface NotificationChannel {
    public function send(NotificationEvent $event): NotificationResult;
    public function canSend(): bool; // config + secrets present
}
```

### Implemented Channels
1. **PushChannel** (OneSignal) — booking/queue/payment/procurement alerts to Flutter app
2. **InAppChannel** (stores in notifications table) — persists alerts for in-app inbox

### OTP Gateway (separate from notifications)
- Handles **only OTP messages** (registration, login 2FA, password reset, mobile change)
- Config in `config/otp.php` — **API key hardcoded for now** (see below)
- Sends via SMS gateway (e.g., MSG91/2factor.in-style HTTP API)
- OTP templates defined in `resources/templates/otp/*.php` (en + hi)

## OneSignal Integration

### Config (hardcoded for now)
- `onesignal_app_id` = `<APP_ID>` — in `config/onesignal.php`
- `onesignal_rest_api_key` = `<REST_API_KEY>` — in `config/onesignal.php`

> **Note**: These keys are **hardcoded in config for now** (development/initial release).
> Later phase: move values to system_secrets (encrypted) and delete from config.

### Push Enabled
Controlled by `push_enabled` setting. If disabled, only in-app notifications are created.

### OneSignal REST API (app notifications)
```php
// Send notification to a player (device) — direct HTTP, no SDK dependency
$url = "https://onesignal.com/api/v1/notifications";
$payload = [
    'app_id'            => $config['onesignal_app_id'],
    'include_player_ids' => [$playerId],       // from user_devices.onesignal_player_id
    'headings'          => ['en' => $headingEn, 'hi' => $headingHi],
    'contents'          => ['en' => $bodyEn, 'hi' => $bodyHi],
    'data'              => ['event' => 'BOOKING_CONFIRMED', 'booking_id' => 42],
];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $config['onesignal_rest_api_key'],
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
```

- `include_player_ids` = devices registered in `user_devices.onesignal_player_id`
- `get_players` not needed; we store player ids on app launch

## OTP Gateway Integration

### Config (hardcoded for now)
| Key | Example |
|-----|---------|
| `otp_api_key` | `<OTP_API_KEY>` |
| `otp_sender_id` | `<SENDER_ID>` |
| `otp_template_id` | `<TEMPLATE_ID>` (if gateway uses templates) |

- Stored in `config/otp.php`
- **Hardcoded for now**; document move to system_secrets later

### OTP SMS Sending (HTTP example — MSG91-style)
```php
$url = "https://api.msg91.com/api/v5/otp";
$payload = [
    'authkey'    => $config['otp_api_key'],
    'template_id'=> $config['otp_template_id'],
    'sender'     => $config['otp_sender_id'],
    'mobile'     => $userMobile,
    'otp'        => $otp,
];
// POST via curl, JSON/query per provider; parse 'otp_id' for verify/resend
```

- Provider-agnostic via `OtpGateway` interface; swap provider = change adapter
- `OtpService` (in [11-authentication.md](11-authentication.md)) calls this gateway

## OTP Templates

OTP messages are not "notifications" in the alert sense — they have strict sender/template
requirements (DND/TRAI templates). Templates stored in `resources/templates/otp/`:

| Template Key | en | hi |
|--------------|----|----|
| `otp.register` | "Your OTP for <%appname%> registration is %otp%. Valid for %expiry% min." | "…हिंदी …" |
| `otp.login_2fa` | "Your login OTP for <%appname%> is %otp%. Do not share." | "…" |
| `otp.password_reset` | "Your OTP to reset password for <%appname%> is %otp%." | "…" |
| `otp.mobile_change` | "Your OTP to change mobile number is %otp%." | "…" |

- Placeholders substituted by `OtpTemplateService`
- On-gateway template (if provider requires registered template ID) must match these strings in the same language/format

## Notification Events

| Event Key | Trigger | Push/In-App Content (example) |
|-----------|---------|------------------------|
| `BOOKING_CONFIRMED` | Booking created | "Your booking at {centre} on {date} {slot} confirmed. Token: {token}" |
| `BOOKING_CANCELLED` | Booking cancelled | "Your booking at {centre} on {date} has been cancelled." |
| `VERIFICATION_APPROVED` | Farmer approved | "Your registration is approved. You can now book slots." |
| `VERIFICATION_REJECTED` | Farmer rejected | "Your registration was rejected. Reason: {reason}" |
| `QUEUE_APPROACHING` | Queue position threshold | "You are {n} places away in the queue at {centre}." |
| `FARMER_CALLED` | Call Next | "Your token {token} has been called. Please report to the counter." |
| `PROCUREMENT_COMPLETED` | Procurement done | "Your procurement of {crop} ({qty}kg) is complete. Payment processing." |
| `PAYMENT_PROCESSING` | Payment → PROCESSING | "Your payment for {crop} is being processed." |
| `PAYMENT_PAID` | Payment → PAID | "₹{amount} paid for {crop}. Ref: {reference}" |
| `PAYMENT_FAILED` | Payment → FAILED | "Your payment failed. Please contact support." |

## Notification Data Model

### notifications table (in-app)
```sql
CREATE TABLE notifications (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(30) NOT NULL,       -- BOOKING_CONFIRMED, etc.
    title VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,                  -- related entity IDs, deep-links
    channel ENUM('PUSH','IN_APP','BOTH') NOT NULL DEFAULT 'IN_APP',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

### notification_logs table (delivery tracking)
```sql
CREATE TABLE notification_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    notification_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    recipient VARCHAR(190) NULL,     -- player_id (push) / mobile (sms) / null (in-app)
    channel ENUM('PUSH','IN_APP','SMS') DEFAULT 'PUSH',
    event_type VARCHAR(30) NOT NULL,
    status ENUM('PENDING','SENT','FAILED','RETRY') DEFAULT 'PENDING',
    attempt_count INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 3,
    next_retry_at DATETIME NULL,
    sent_at DATETIME NULL,
    error_message VARCHAR(500) NULL,
    provider_response TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
);
```

> Device registration: `user_devices.onesignal_player_id` holds OneSignal player id per device.

## Send Flow

```
Event occurs (e.g., booking confirmed)
      ↓
NotificationService::dispatch(event)
      ↓
1. Build message (localized, keys + values)
2. Create in-app notification (IN_APP)
3. If push_enabled & user has devices → create notification_log (PUSH) status=PENDING
4. Attempt push via OneSignal (synchronous or queued)
      ↓
Push send result
   ├── SUCCESS → notification_log → SENT, sent_at=NOW()
   └── FAILURE → notification_log → FAILED, attempt_count++, next_retry_at
```

## Push Delivery & Retry

```
notification_log status = PENDING
      ↓
Attempt 1 → send via OneSignal
   ├── success → SENT
   └── fail   → FAILED, attempt_count=1, next_retry_at=NOW()+interval

Cron retries FAILED logs where next_retry_at <= NOW() AND attempt_count < max_attempts:
   Attempt 2, 3, ...
   ├── success → SENT
   └── fail   → attempt_count++, next_retry_at

After max_attempts (default 3) → status=FAILED (permanent), no more retries
```

### Retry Parameters (configurable)
| Setting | Default |
|---------|---------|
| notification_retry_count | 3 |
| notification_retry_interval_seconds | 300 |

## Idempotency / Duplicate Prevention

Prevent the same event from notifying the same user twice:

- **Idempotency key**: `event_type + entity_id + user_id + (optional dedup window)`
- Store dedup marker; skip if already sent within window

```php
// Example: only notify QUEUE_APPROACHING once per threshold crossing per booking/date
if (!shouldSendDedup('QUEUE_APPROACHING', $bookingId, $farmerId)) {
    // skip duplicate
    return;
}
```

## Language Support

- Messages built using translation keys per language
- Farmer's preferred language (or default) used for push content
- Templates stored as translations (configurable content)
- OneSignal `headings`/`contents` accept per-language maps (en + hi)

## Test Notification

`POST /notifications/test-push {device_player_id}` — admin/manager verification of OneSignal config.

## Notification Abstraction

```
+-----------------------+
| NotificationService   |
|  dispatch(event,      |
|    user, targets)     |
+----------+------------+
           |
   +-------+---------+----------+
   |       |         |          |
 IN_APP   PUSH      SMS       EMAIL
(channel)(channel)  (OTP only) (future)
```

The core uses a channel registry, so adding email later = register a new channel + config. No redesign.

## Cron / Batch Jobs

- **Retry failed notifications**: cron every N minutes → retry PENDING/FAILED within max_attempts
- **Queue approaching**: scheduled check (or on queue change) sends QUEUE_APPROACHING when threshold crossed (dedup)
- **Cleanup**: archive old notification_logs

## Audit & Logging

- All sends logged in `notification.log` (no secret material)
- Failures logged with error but never auth tokens/OTPs/API keys
- Notification events audited at source (booking/queue/etc.)
- OTP send count tracked in security log; OTP never logged

## Notification Endpoints (Farmer app)

| Method | Path | Purpose |
|--------|------|---------|
| POST | /auth/devices | Register/update OneSignal player_id for logged-in user |
| GET | /notifications | List notifications (paginated) |
| GET | /notifications/{id} | Detail |
| PATCH | /notifications/{id}/read | Mark read |
| PATCH | /notifications/read-all | Mark all read |

---

**Next**: [23-language-system.md](23-language-system.md) for the language/translation system.