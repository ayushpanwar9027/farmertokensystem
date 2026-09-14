# Phase 14 — Notification System (OneSignal Push + OTP Gateway)

## 1. Objective

Implement the centralized notification system: **OneSignal push** as primary alert channel, **in-app** notifications, and a separate **OTP gateway (SMS)** for 2FA/registration OTP with templates. Retry with backoff via cron, deduplication, and event-based enqueueing from all phases. Keys hardcoded in config for now.

## 2. Prerequisites

- Phase 03 (services), Phase 04 (2FA OTP groundwork), Phase 06 (settings; OneSignal/OTP keys hardcoded), Phase 10-13 (events), Phase 35 (cron runner)

## 3. Features

- NotificationService: `dispatch(event, user, targets)` → NotificationLog
- Channels: push (OneSignal), in-app (notifications table), sms-otp (OTP gateway via OtpService, only for OTP), log (dev fallback), email (NOT implemented, documented)
- Templates per event with en/hi text + variable interpolation
- Event catalog (from [22-notification-system.md](../22-notification-system.md)):
  - booking_confirmed, booking_cancelled, verification_approved, verification_rejected, queue_approaching, farmer_called, procurement_completed, payment_processing, payment_paid, payment_failed
- OTP templates (via OTP gateway, not notifications): otp.register, otp.login_2fa, otp.password_reset, otp.mobile_change
- Retention: status PENDING → SENT | FAILED | RETRY; attempts + next_retry_at
- Retry cron job (from Phase 35) with max attempts + backoff (interval setting)
- Dedup: key = event + entity id + user, within dedup window
- Masking/logging sanitized (player id partly masked)
- OneSignal integration via curl (REST API `/notifications`); keys from config (hardcoded)

## 4. Files to Create

```
app/Services/NotificationService.php
app/Services/OneSignalService.php        # push via OneSignal (REST, curl)
app/Services/OtpService.php              # OTP send/verify via OTP gateway (2FA + register + reset)
app/Services/OtpTemplateService.php      # OTP template rendering (en/hi)
app/Services/NotificationTemplateService.php
app/Models/NotificationLog.php
app/Models/NotificationTemplate.php
app/Controllers/NotificationsController.php
config/onesignal.php                      # onesignal_app_id, onesignal_rest_api_key (HARDCODED for now)
config/otp.php                            # otp_api_key, otp_sender_id, otp_template_id (HARDCODED for now)
resources/templates/otp/*.php             # otp.register, otp.login_2fa, otp.password_reset, otp.mobile_change (en+hi)
resources/templates/notification/*.php    # event templates (en+hi)
database/seeders/notification_template_seeder.php
app/Console/cron --job=retry-notifications + send-pending
```

## 5. Files to Modify

- `app/Console/cron.php` (register retry/send jobs)
- `config/routes.php`
- `app/Services/AuthService.php` (2FA: login step, verify-2fa, enable/disable, step-up)
- `.env.example` (ONESIGNAL_*/OTP_* hardcoded placeholders)

## 6. Database Changes

- Uses notification_logs + notifications + otp_verifications (Phase 02 schema previously updated).
- `notification_logs`: `channel ENUM('PUSH','IN_APP','SMS')`, `recipient` = player id (PUSH) / mobile (SMS).
- `notifications`: `channel ENUM('PUSH','IN_APP','BOTH')`.
- `users.two_factor_enabled`, `user_devices.onesignal_player_id`, `otp_verifications.purpose` extended (LOGIN_2FA, 2FA_ENABLE, 2FA_STEP_UP, MOBILE_CHANGE).

## 7. API Changes

- `POST /internal/notify` (server-side only, no public)
- `GET /notifications`, `GET /notifications/{id}`, `PATCH /notifications/{id}/read`, `PATCH /notifications/read-all`
- `POST /auth/devices` (register OneSignal player_id)
- 2FA/OTP:
  - `POST /auth/verify-2fa`, `POST /auth/resend-2fa`
  - `POST /auth/2fa/enable`, `POST /auth/2fa/enable/confirm`, `POST /auth/2fa/disable`
  - `POST /auth/2fa/challenge`, `POST /auth/2fa/confirm`
  - `POST /auth/mobile-change`, `POST /auth/mobile-change/confirm`
- Admin:
  - `GET /admin/notifications?status=&event=&date_from=&date_to=&q=` (scoped)
  - `POST /admin/notifications/test-push` (send test push to player id / all; manager+)
  - `GET /admin/notifications/summary` (sent/failed today)

## 8. Backend Logic

- NotificationService::dispatch(event, user, params):
  1. resolve template (locale); interpolate
  2. dedup check (dedup_key, window)
  3. create in-app notification row (IN_APP/BOTH)
  4. if push_enabled and user has devices → insert NotificationLog PENDING for each player id
  5. immediate-first-attempt (sync) if `push.immediate=true` else via cron
- OneSignalService::sendPush(playerIds[], headings, contents, data):
  - POST https://onesignal.com/api/v1/notifications
  - header `Authorization: Basic {onesignal_rest_api_key}`, body app_id + include_player_ids + localized headings/contents + data payload
  - parse response; mark SENT or queue retry
- OtpService::sendOtp(mobile, purpose, template):
  - generate 6-digit OTP, hash (SHA-256), store in otp_verifications (expiry 5 min, attempts 0, max 5, resend_at cooldown 60s)
  - POST OTP gateway API (MSG91-style); pattern: `otp_api_key`, `otp_sender_id`, `otp_template_id`, `mobile`, `otp`
  - record security log (count only)
- failed: attempt++ ; if attempt < max → next_retry_at = now + backoff(interval × 2^attempt) ; else FAILED → monitor alert
- Backoff base 60s, max interval 30 min
- sanitize logs: player id masked, number masked `+91****1234`

## 9. Flutter Changes

- OneSignal SDK init (`onesignal_flutter`), permission prompt, player_id capture → `POST /auth/devices` on login/resume
- Notification list screens consume `/notifications`
- 2FA: `twofa_screen.dart` for login OTP entry; enable/disable toggles in profile

## 10. Staff/Admin Changes

- None (Phase 16): admin notification log viewer + test push button

## 11. Permissions

- `notifications.view` (scoped), `notifications.test`
- User self-view of own notifications

## 12. Validation

- recipient phone valid (10-digit Indian) for OTP
- event key must exist in catalog
- template interpolation must satisfy all placeholders (else send fails → logged)
- player id format (UUID) validated before push

## 13. Error Handling

- TEMPLATE_NOT_FOUND, INVALID_RECIPIENT, ONESIGNAL_API_ERROR, OTP_GATEWAY_ERROR, NOTIFICATION_FAILED (retryable), DEDUP_SUPPRESSED (returned as skipped)

## 14. Security

- OneSignal/OTP keys read from `config/onesignal.php` / `config/otp.php` (hardcoded for now; later SecretService)
- Never log full player id / number / OTP
- No PII beyond number; body may contain token names (allowed)
- Test-push endpoint restricted to role + rate limited
- 2FA OTP consumed once; verified only once; never logged

## 15. Logging/Audit

- notification.log per send/retry/fail (sanitized)
- OTP send/verify → security.log (counts, no values)
- audit: test push, template changes, 2FA enable/disable

## 16. Notifications

- This is the notification phase core.

## 17. Configuration Changes

- `push.immediate=true`, `push.retry_max=3`, `push.backoff_base=60`, `push.dedup_window_seconds=3600`, `onesignal.timeout=10`
- `otp.expiry_minutes=5`, `otp.resend_cooldown_seconds=60`, `otp.attempts_max=5`
- `onesignal_app_id`, `onesignal_rest_api_key` (config, hardcoded for now)
- `otp_api_key`, `otp_sender_id`, `otp_template_id` (config, hardcoded for now)

## 18. Dependencies

- curl extension; OneSignal app id + REST API key; OTP gateway API key (all hardcoded in config for now)

## 19. Completion Criteria

- [ ] dispatch() enqueues + immediate send works with OneSignal (or log fallback in dev)
- [ ] Retry cron with backoff updates attempts and escalates FAILED
- [ ] Dedup prevents duplicates within window
- [ ] Notification + OTP templates render en/hi
- [ ] All Phase 10-13 events wired to dispatch
- [ ] 2FA login flow works (two-step OTP), enable/disable/step-up verified
- [ ] Test push endpoint works
- [ ] Player id / number masked in logs

## 20. Testing Checklist

- [ ] Trigger booking confirmed → push received on registered device (dev log fallback)
- [ ] OTP gateway sends registration + 2FA login OTP; invalid/expired OTP rejected
- [ ] 2FA: login 202 → verify-2fa → tokens; disable requires fresh OTP
- [ ] Force OneSignal failure (bad key) → PENDING retry → after max → FAILED + alert
- [ ] Fire same event twice within window → 1 send
- [ ] hi locale farmer → Hindi push + Hindi OTP template
- [ ] Invalid player id → INVALID_RECIPIENT logged
- [ ] admin test push → delivered
- [ ] Log shows masked player id/number

## 21. What NOT to Implement

- No email channel
- No voice/whatsapp via OTP gateway
- No real-time WebSocket notify
- No OneSignal in-app-purchase/segment analytics features

---

**Depends on**: Phase 03, 04, 06, 35
**Feeds into**: Phase 15+