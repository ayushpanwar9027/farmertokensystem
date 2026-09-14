# Onboarding Guide

Farmer + Staff onboarding for the Farmer Procurement System (Kisan — किसान समन्वय).

Sections: [Farmers](#1-farmers) and [Staff / Portal](#2-staff--portal-admins-managers-operators).
Screenshot slots are marked `[SCREENSHOT: ...]` — paste an image and replace the
slot before distributing.

**Live URLs (deployment `testing.deepjyotimicrofinance.com`):**
- Admin/staff portal: `https://testing.deepjyotimicrofinance.com/portal/`
- Farmer app (release APK): built with `--dart-define=API_BASE=https://testing.deepjyotimicrofinance.com`
- Health: `https://testing.deepjyotimicrofinance.com/health`

---

## 1. Farmers

### 1.1 Install the App (Android)

1. Open the APK you received:
   - `[SCREENSHOT: welcome / install prompt]`
   - Source: official link/QR sent by the centre (see **Release APK** note at end).
2. Tap **Install**. If Play Protect warns, choose "Install anyway" only for this
   known file.
3. After install open **Kisan**.
   `[SCREENSHOT: app icon on home screen]`

### 1.2 Register via OTP SMS

1. Tap **Register**.
2. Enter your mobile number, name (Hindi/English), village, district, state, pincode.
3. Tap **Send OTP** — an SMS OTP arrives on your phone.
   `[SCREENSHOT: OTP received in SMS]`
4. Enter the 6-digit OTP. If it expires, tap **Resend** (new OTP sent).
5. Your account is created and **pending approval** by a centre operator/admin.
   You cannot book until approved.

### 1.3 Enable 2FA (recommended)

1. After login, go to **Profile → Security → Enable 2FA**.
2. You will receive an OTP SMS to confirm enabling.
3. Every new login then asks for a fresh OTP SMS → enter to proceed.
   `[SCREENSHOT: 2FA enable screen]`

### 1.4 Allow Notifications

1. On first open the app asks **Allow notifications?** → tap **Allow**.
2. Later: phone **Settings → Apps → Kisan → Notifications → Allow**.
3. Also **allow this device** in the app once (may be prompted on login) so the
   server can push to this phone.
   `[SCREENSHOT: notification permission dialog]`

### 1.5 Make a Booking

1. Home → **Slots** → pick your centre/date/slot with capacity left.
2. Add crops and expected quantity (kg) → **Confirm Booking**.
3. Order confirmed — you get a **token number (GKP-...) worth waiting for**.
   `[SCREENSHOT: booking confirmation + token]`

### 1.6 Track Your Queue

1. Home → **My Queue** (or **Queue** tab).
2. Shows your **position, farmers ahead, expected time** (live, ~5-sec refresh).
3. You will get a **push notification when your turn is approaching**.
   `[SCREENSHOT: live queue with position + ETA]`

### 1.7 At the Centre

Listen for your token to be called. Show your message/QR to the operator. They
measure and record each crop; the operator may ask for photos (per policy).

### 1.8 Receive Payment Push

1. After quality check and approval, the payment is released.
2. You receive a **push notification + in-app message** when payment is
   **RELEASED** — see your amount in **My Payments** / statement.
   `[SCREENSHOT: payment released push notification]`
3. Payments may be **reversed** only by district admin with a reason — check the
   statement for details.

### 1.9 Language

App supports **English and Hindi**. Switch from the settings menu; the portal
remembers your choice. `[SCREENSHOT: language picker]`

### 1.10 Help

- Forgot PIN/password → **Forgot password** → OTP SMS → reset.
- Wrong details / not approved → contact your centre.
- App problems → report to staff with a screenshot.

---

## 2. Staff / Portal (Admins, Managers, Operators)

The portal is a browser app at `https://<domain>/portal/` (same-origin API).

### 2.1 First login (set password + 2FA)

1. Your Super Admin gave you an **initial password over a secure channel**
   (do NOT share; change on first login).
   `[SCREENSHOT: portal login form]`
2. Portal login: username + password → **Send OTP** → enter SMS OTP (2FA).
   `[SCREENSHOT: 2FA prompt]`
3. Strong password rules apply. Set a fresh password you keep private.

### 2.2 Home / dashboard

Server-aware: your role (Super Admin / District Admin / Centre Manager / Centre
Operator) determines which menus appear and which centres/districts you can see.
`[SCREENSHOT: dashboard with role-aware menu]`

| Role | Typical menus | Scope |
|------|---------------|-------|
| Super Admin | All menus incl. Settings, Secrets, Maintenance, Staff, Roles/Permissions | Everything |
| District Admin | Centres, Slots, Staff, Approvals, Payments (reverse), Rates | Own district |
| Centre Manager | Queue, Procurements, Approvals, Payments (release), Staff(centre) | Own centre(s) |
| Centre Operator | Queue (call/skip/no-show/recall), Procurements (capture/submit/reject) | Assigned centre |

### 2.3 Queue operations

1. Open **Queue** for a centre/date.
2. **Call Next** — moves next waiting farmer to called; only one call succeeds at
   a time (anti-race).
3. Actions per entry: **Skip** (farmer away), **No-Show** (after grace), **Recall**
   (if allowed).
   `[SCREENSHOT: queue screen with actions]`

### 2.4 Procurement capture → payment

1. Start procurement on a called farmer; **capture** weights (+optional photos) per crop.
2. **Submit** → if approval required, admin approves; payment is created
   automatically for **verified** procurement (qty × effective rate).
3. **Release payment** (reference + method) — farmer gets a **push**.
   `[SCREENSHOT: payment release form]`

### 2.5 Test push / test OTP (admin)

Notifications page has **Test Push** and **Test OTP** buttons — verify live
delivery to your own phone before go-live.
`[SCREENSHOT: notifications admin with test buttons]`

### 2.6 Maintenance toggle (Super Admin)

System → Maintenance → toggle ON/OFF. Public sees the banner while ON; `/health`
stays up; Super Admin can still log in.

### 2.7 Translations (admin)

Languages → add/edit `en`/`hi` strings. Changes invalidate caches immediately.

### 2.8 Audit & reports

Audit log + reports pages show live operations data, filtered to your scope.

### 2.9 2FA & security (all staff)

- Enable 2FA in your profile (OTP on your real phone).
- Never share passwords/OtPs. Unusual login → report to Super Admin.

---

## 3. Release APK note (distribution)

- Artifact: `farmer_app/build/app/outputs/flutter-apk/app-release.apk`
  (build: `flutter build apk --release --dart-define=API_BASE=https://<domain> --dart-define=ONESIGNAL_APP_ID=<LIVE_APP_ID>`).
- Currently distributed by APK hand-off; Play Store optional later.
- `[SCREENSHOT: release page of app with version]`