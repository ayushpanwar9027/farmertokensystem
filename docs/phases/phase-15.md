# Phase 15 — Flutter Farmer App

## 1. Objective

Build the Android-only farmer Flutter app: onboarding, auth (OTP + **2FA**), home, centres/slots/booking flow with multi-crop, live queue status, procurement/payment status, notifications-inbox (**push via OneSignal** + in-app), localization, and offline-friendly UX.

## 2. Prerequisites

- Backend phases 04-14 (APIs live + tested)
- Flutter SDK, Android toolchain

## 3. Features

- Splash + onboarding screens
- Language selection (en/hi) persisted
- Registration: mobile → OTP verify → name/state/district/village/photo/declared crops
- Login/remember device; **2FA OTP step if enabled (twofa_screen)**; token refresh; secure storage
- **OneSignal push SDK init; permission prompt; register player_id via POST /auth/devices**
- Dashboard: active booking card, token status, quick actions
- Book flow: select district → centre (list/detail map) → date (7-day horizon) → slot → crops + qty → review → confirm
- My bookings list + detail (crop lines, statuses, cancel button within window)
- My token / live queue panel (poll every 5s): position, ETA, current token, centre name
- Procurement status per crop line
- Payments statement list + status
- Notification inbox (from Phase 14 in-app)
- Maintenance/offline banners; retry
- Settings: language, password change, profile
- All text localized (backend translations + app-side bundle)

## 4. Files to Create (Flutter project root `farmer_app/`)

> Note: `app/` is already the PHP backend application code. The Flutter project lives in a NEW sibling folder `D:\sihproject\farmer_app`.

```
farmer_app/lib/main.dart
farmer_app/lib/app.dart
farmer_app/lib/config/env.dart (base url per build flavor)
farmer_app/lib/core/api_client.dart (dio-free or http; intercept auth 401 → refresh)
farmer_app/lib/core/auth_state.dart
farmer_app/lib/core/secure_storage_wrapper.dart
farmer_app/lib/core/locale_controller.dart
farmer_app/lib/models/{user,centre,slot,crop,booking,booking_crop,token,queue,procurement,payment,notification}.dart
farmer_app/lib/services/{api,booking,queue,procurement,payment,notification}.dart
farmer_app/lib/screens/{splash,onboarding,login,twofa,otp,register,home,dashboard,centres,centre_detail,date_slot,book_crops,review,bookings,booking_detail,token,queue_status,procurements,payments,inbox,settings,profile,maintenance}.dart
farmer_app/lib/services/push_service.dart (OneSignal init + player_id registration)
farmer_app/lib/widgets/{...}.dart
farmer_app/lib/l10n/{en,hi}.arb (or hydrated strings)
farmer_app/test/widget/*_test.dart (Phase 38 checklists)
```

## 5. Files to Modify

- None (new project)

## 6. Database Changes

- None (consumes APIs)

## 7. API Changes

- Consumes existing: `/auth/*` (incl. `/auth/verify-2fa`, `/auth/devices`), `/centres`, `/slots`, `/crops`, `/bookings`, `/tokens`, `/queue/*`, `/my/procurements`, `/my/payments`, `/notifications/*`, `/translations/{locale}`

## 8. Backend Logic

- Client-side only. Keep token refresh via `/auth/refresh`; on refresh failure → logout clean.

## 9. Flutter Changes

- This phase.

Renderer notes:
- Responsive simple Material 3, Hindi content via RTL-safe text (Hindi is LTR but diacritics fine)
- Fonts: use a Devanagari-capable font (bundled Noto Sans Devanagari subset) — no network font dependency

## 10. Staff/Admin Changes

- None

## 11. Permissions

- App uses farmer-scoped endpoints only

## 12. Validation

- Form validators match backend (mobile 10-digit, OTP 6, crop qty>0)
- Handle every documented error code with friendly message
- Prevents double-submit on booking

## 13. Error Handling

- 401 → silent refresh → retry once
- 400 VALIDATION → show field errors
- 409 → contextual action (slot full → suggest next slot)
- 429 → cooldown message
- 503 maintenance → banner + message
- network → retry with backoff + offline banner

## 14. Security

- Tokens in flutter_secure_storage
- Certificate: HTTPS only; base URL per flavor
- No logging of tokens/OTP
- OTP auto-fill via SMS catch

## 15. Logging/Audit

- Local debug logs only (no PII); disable in release

## 16. Notifications

- In-app inbox + deep-link to booking/token (basic)
- OneSignal push init + permission; player_id registered to backend; push received → inbox sync + deep-link

## 17. Configuration Changes

- `app_config.dart`: apiBaseUrl (dev/staging/prod), version, upload sizes
- Flavor `--dart-define=API_BASE=...`

## 18. Dependencies

- flutter_secure_storage, http (or dio), intl, provider/riverpod (choose simple: provider), flutter_localizations, path packages, image_picker (avatar/crop photos), package_info_plus, **onesignal_flutter**
- Keep minimal

## 19. Completion Criteria

- [ ] Onboarding → registration (OTP) → complete → dashboard
- [ ] **2FA login: step1 → twofa_screen → step2 → dashboard**
- [ ] **Push permission prompt; player_id registered; test push received → inbox populated**
- [ ] Full booking flow runs against staging API (multi-crop)
- [ ] Live queue polling shows correct position/ETA
- [ ] Cancel within window works
- [ ] Procurement/payment/notification views render from API
- [ ] Language switch en/hi live
- [ ] Token refresh + secure storage verified
- [ ] Build a release APK (Android)

## 20. Testing Checklist (see Phase 38 too)

- [ ] Fresh install → onboarding → OTP
- [ ] Wrong OTP → error, resend cooldown
- [ ] **2FA wrong OTP, resend, disabled user skips step**
- [ ] Book with 3 crops → booking detail shows 3 lines
- [ ] Slot full error suggests next → retry success
- [ ] Queue screen updates position after call (staging simulation)
- [ ] Expired token → auto refresh → API resumes
- [ ] Offline → banner; retry resumes
- [ ] 503 maintenance → banner + message
- [ ] Release APK builds

## 21. What NOT to Implement

- No offline-first full cache (session data only)
- No map/satellite heavy features
- No iOS
- No admin screens in app

---

**Depends on**: Backend phases 04-14
**Feeds into**: Phase 17 (integration) + 18 (prod)