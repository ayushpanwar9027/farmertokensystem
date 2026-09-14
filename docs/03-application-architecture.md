# Application Architecture

## Two Applications, One Backend

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PHP BACKEND API                               │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐   │
│  │   Auth      │ │  Booking    │ │   Queue     │ │  Procurement│   │
│  │   Service   │ │  Service    │ │  Service    │ │  Service    │   │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘   │
│  ┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐   │
│  │  Payment    │ │Notification │ │   File      │ │  Settings   │   │
│  │  Service    │ │  Service    │ │  Service    │ │  Service    │   │
│  └─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘   │
└─────────────────────────────────────────────────────────────────────┘
                              ▲                    ▲
                              │                    │
              ┌───────────────┘                    └───────────────┐
              ▼                                                    ▼
    ┌─────────────────────┐                            ┌─────────────────────┐
    │  FLUTTER FARMER APP │                            │  STAFF/ADMIN PORTAL │
    │     (Android)       │                            │    (Web/HTML/JS)    │
    └─────────────────────┘                            └─────────────────────┘
```

## Flutter Farmer App Architecture

### Directory Structure
```
lib/
├── main.dart
├── core/
│   ├── constants/
│   │   ├── api_constants.dart
│   │   ├── app_constants.dart
│   │   ├── storage_keys.dart
│   │   └── route_names.dart
│   ├── theme/
│   │   ├── app_theme.dart
│   │   ├── colors.dart
│   │   ├── text_styles.dart
│   │   └── spacing.dart
│   ├── localization/
│   │   ├── app_localizations.dart
│   │   ├── translations/
│   │   │   ├── en.json
│   │   │   └── hi.json
│   │   └── locale_provider.dart
│   ├── network/
│   │   ├── api_client.dart
│   │   ├── interceptors/
│   │   │   ├── auth_interceptor.dart
│   │   │   ├── logging_interceptor.dart
│   │   │   └── error_interceptor.dart
│   │   ├── endpoints/
│   │   │   ├── auth_endpoints.dart
│   │   │   ├── booking_endpoints.dart
│   │   │   ├── queue_endpoints.dart
│   │   │   ├── procurement_endpoints.dart
│   │   │   └── payment_endpoints.dart
│   │   └── models/
│   │       ├── api_response.dart
│   │       ├── pagination.dart
│   │       └── error_response.dart
│   ├── storage/
│   │   ├── secure_storage.dart
│   │   ├── preferences.dart
│   │   └── cache_manager.dart
│   └── utils/
│       ├── date_utils.dart
│       ├── validation_utils.dart
│       ├── format_utils.dart
│       └── network_utils.dart
│
├── models/
│   ├── user.dart
│   ├── farmer.dart
│   ├── centre.dart
│   ├── slot.dart
│   ├── booking.dart
│   ├── token.dart
│   ├── queue_entry.dart
│   ├── procurement.dart
│   ├── payment.dart
│   ├── notification.dart
│   └── device.dart
│
├── services/
│   ├── auth_service.dart
│   ├── booking_service.dart
│   ├── queue_service.dart
│   ├── procurement_service.dart
│   ├── payment_service.dart
│   ├── notification_service.dart
│   ├── centre_service.dart
│   └── profile_service.dart
│
├── repositories/
│   ├── auth_repository.dart
│   ├── booking_repository.dart
│   ├── queue_repository.dart
│   └── ...
│
├── features/
│   ├── auth/
│   │   ├── screens/
│   │   │   ├── splash_screen.dart
│   │   │   ├── language_selection_screen.dart
│   │   │   ├── login_screen.dart
│   │   │   ├── register_screen.dart
│   │   │   ├── otp_verification_screen.dart
│   │   │   └── farmer_details_screen.dart
│   │   ├── widgets/
│   │   └── providers/
│   ├── home/
│   │   ├── screens/home_screen.dart
│   │   ├── widgets/
│   │   └── providers/
│   ├── profile/
│   ├── centres/
│   │   ├── screens/centre_list_screen.dart
│   │   ├── screens/centre_detail_screen.dart
│   │   └── widgets/
│   ├── slots/
│   │   ├── screens/slot_selection_screen.dart
│   │   └── widgets/
│   ├── bookings/
│   │   ├── screens/booking_confirmation_screen.dart
│   │   ├── screens/booking_history_screen.dart
│   │   ├── screens/cancellation_screen.dart
│   │   └── widgets/
│   ├── queue/
│   │   ├── screens/live_queue_screen.dart
│   │   ├── screens/token_screen.dart
│   │   ├── widgets/queue_position_widget.dart
│   │   ├── widgets/estimated_wait_widget.dart
│   │   └── providers/queue_polling_provider.dart
│   ├── procurement/
│   │   ├── screens/procurement_status_screen.dart
│   │   └── widgets/
│   ├── payments/
│   │   ├── screens/payment_status_screen.dart
│   │   └── widgets/
│   ├── notifications/
│   │   ├── screens/notification_list_screen.dart
│   │   └── widgets/
│   └── support/
│       ├── screens/help_screen.dart
│       ├── screens/contact_screen.dart
│       └── widgets/
│
├── widgets/
│   ├── common/
│   │   ├── app_button.dart
│   │   ├── app_text_field.dart
│   │   ├── app_dropdown.dart
│   │   ├── loading_indicator.dart
│   │   ├── error_display.dart
│   │   ├── empty_state.dart
│   │   ├── status_badge.dart
│   │   └── qr_code_widget.dart
│   ├── layout/
│   │   ├── app_scaffold.dart
│   │   ├── app_app_bar.dart
│   │   ├── app_bottom_nav.dart
│   │   └── app_drawer.dart
│   └── feedback/
│       ├── snackbar.dart
│       ├── dialog.dart
│       └── toast.dart
│
└── providers/
    ├── auth_provider.dart
    ├── locale_provider.dart
    ├── theme_provider.dart
    └── connectivity_provider.dart
```

### State Management Strategy

**Approach**: Provider + ChangeNotifier (simple, built-in, no code generation)

```dart
// Core providers
- AuthProvider: currentUser, token, refreshToken, isAuthenticated, login/logout
- LocaleProvider: currentLocale, supportedLocales, changeLocale
- ThemeProvider: isDarkMode (future), toggleTheme
- ConnectivityProvider: isOnline, connectionType, onConnectivityChanged

// Feature providers (scoped to feature lifecycle)
- QueuePollingProvider: currentQueue, position, estimatedWait, startPolling/stopPolling
- BookingProvider: currentBooking, bookings, createBooking, cancelBooking
```

**Rules**:
- No global mutable state except providers
- Providers dispose polling/timers on destroy
- Repository pattern for data access
- Services contain business logic, providers orchestrate

### Network Layer

```dart
// ApiClient (singleton)
- Base URL from constants
- Dio/HTTP client with interceptors
- Timeout: 30s connect, 60s receive
- Retry: 3x with exponential backoff (idempotent GET only)

// AuthInterceptor
- Attaches Bearer token (JWT access)
- Handles 401: tries refresh token once
- On refresh failure: clears auth, redirects to login

// ErrorInterceptor
- Maps HTTP errors to AppExceptions
- Network errors → OfflineException
- Timeout → TimeoutException
- Server errors → ServerException with message

// LoggingInterceptor (debug only)
- Logs request/response (no sensitive headers)
```

### Offline Handling

```dart
// ConnectivityProvider monitors network
// QueuePollingProvider: pauses polling when offline, resumes when online
// Critical actions (booking, cancellation): show offline error, queue for retry
// Cached data: last known queue position, booking list, profile (SecureStorage)
// Visual indicator: offline banner, disabled actions
```

### Security (Flutter)

```dart
// SecureStorage (flutter_secure_storage)
- access_token, refresh_token, remember_token
- device_id (stable identifier)
- farmer_id (for quick reload)
// NO: passwords, OTPs, database creds, API keys, OneSignal/OTP secret keys

// Certificate Pinning (future enhancement)
// Root CA verification enforced
```

### Navigation

```dart
// go_router or simple Navigator 2.0
// Routes defined in route_names.dart
// Auth guard: redirects to login if unauthenticated
// Role guard: farmer only (web portal separate)
```

### Dependencies (pubspec.yaml)

```yaml
dependencies:
  flutter:
    sdk: flutter
  http: ^1.1.0                    # Or dio: ^5.4.0
  provider: ^6.1.1                # State management
  shared_preferences: ^2.2.2      # Non-sensitive prefs
  flutter_secure_storage: ^9.0.0  # Tokens, secrets
  intl: ^0.18.1                   # Localization, date formatting
  qr_flutter: ^4.1.0              # Token QR code
  connectivity_plus: ^5.0.2       # Network monitoring
  package_info_plus: ^4.2.0       # App version
  device_info_plus: ^9.1.1        # Device identifier
  permission_handler: ^11.0.1     # Permissions (future)
  url_launcher: ^6.2.1            # Support links
  image_picker: ^1.0.4            # Profile photo (future)
  cached_network_image: ^3.3.0    # Image caching
  flutter_local_notifications: ^16.0.0  # Local notifications (future)

dev_dependencies:
  flutter_test:
    sdk: flutter
  flutter_lints: ^3.0.0
```

## Staff/Admin Portal Architecture

### Directory Structure
```
public/portal/
├── index.html                 # Entry point (SPA-style)
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── components.css
│   │   ├── tables.css
│   │   ├── forms.css
│   │   ├── theme.css
│   │   └── print.css
│   ├── js/
│   │   ├── app.js             # Main entry
│   │   ├── core/
│   │   │   ├── api.js         # Fetch wrapper with interceptors
│   │   │   ├── auth.js        # Session management
│   │   │   ├── router.js      # Simple hash-based router
│   │   │   ├── state.js       # Global state (currentUser, permissions)
│   │   │   ├── i18n.js        # Translation system
│   │   │   └── utils.js       # Helpers
│   │   ├── components/
│   │   │   ├── Modal.js
│   │   │   ├── Table.js
│   │   │   ├── Form.js
│   │   │   ├── Select.js
│   │   │   ├── DatePicker.js
│   │   │   ├── Notification.js
│   │   │   ├── ConfirmDialog.js
│   │   │   └── StatusBadge.js
│   │   ├── pages/
│   │   │   ├── LoginPage.js
│   │   │   ├── DashboardPage.js
│   │   │   ├── CentresPage.js
│   │   │   ├── SlotsPage.js
│   │   │   ├── BookingsPage.js
│   │   │   ├── QueuePage.js
│   │   │   ├── ProcurementPage.js
│   │   │   ├── PaymentsPage.js
│   │   │   ├── StaffPage.js
│   │   │   ├── ReportsPage.js
│   │   │   ├── AuditLogsPage.js
│   │   │   ├── SettingsPage.js
│   │   │   ├── SecretsPage.js
│   │   │   ├── LanguagesPage.js
│   │   │   ├── FilesPage.js
│   │   │   └── ProfilePage.js
│   │   └── features/
│   │       ├── queue/
│   │       │   ├── QueuePolling.js
│   │       │   └── QueueActions.js
│   │       ├── booking/
│   │       │   └── BookingActions.js
│   │       └── procurement/
│   │           └── ProcurementActions.js
│   │   └── charts/
│   │       └── ChartWidgets.js
│   │   └── lib/
│   │       └── chart.min.js   # Chart.js (local copy)
│   └── images/
│       ├── logo.svg
│       ├── favicon.ico
│       └── placeholders/
```

### Authentication (Web Portal)

```javascript
// Session-based (PHP session + custom sessions table)
// Cookie: PHPSESSID (HttpOnly, Secure, SameSite=Lax)
// CSRF: Double-submit cookie pattern
//   - Meta tag: <meta name="csrf-token" content="...">
//   - Header: X-CSRF-Token on all mutating requests

// Auth.js
- checkAuth(): validates session via /api/auth/me
- login(credentials): POST /api/auth/login
- logout(): POST /api/auth/logout
- refreshSession(): POST /api/auth/refresh (extends expiry)
- getPermissions(): returns permission array for UI rendering
```

### Routing (Hash-based SPA)

```javascript
// router.js
- Routes: #login, #dashboard, #centres, #centres/:id, #slots, #bookings, #queue, #queue/:centreId/:date, #procurement, #payments, #staff, #reports, #audit, #settings, #secrets, #languages, #files, #profile
- Guards: requireAuth, requirePermission, requireScope
- Navigation: window.location.hash = '#route'
- Browser history: pushState for back button
```

### State Management (Vanilla JS)

```javascript
// state.js (simple observable pattern)
const State = {
  user: null,
  permissions: [],
  scope: { districtId: null, centreId: null },
  theme: 'light',
  locale: 'en',
  notifications: [],
  
  set(key, value) { this[key] = value; notify(); },
  get(key) { return this[key]; },
  hasPermission(perm) { return this.permissions.includes(perm); },
  canAccess(scope) { /* check resource scope */ }
};

// Event bus for cross-component communication
const Events = {
  on(event, callback),
  off(event, callback),
  emit(event, data)
};
```

### API Client (Vanilla JS)

```javascript
// api.js
class ApiClient {
  constructor(baseUrl) { this.baseUrl = baseUrl; }
  
  async request(method, path, options = {}) {
    const url = `${this.baseUrl}${path}`;
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
      ...options.headers
    };
    
    const config = { method, headers, credentials: 'include', ...options };
    if (options.body) config.body = JSON.stringify(options.body);
    
    const response = await fetch(url, config);
    const data = await response.json().catch(() => ({}));
    
    if (!response.ok) {
      throw new ApiError(response.status, data.error || 'Request failed', data);
    }
    return data;
  }
  
  get(path) { return this.request('GET', path); }
  post(path, body) { return this.request('POST', path, { body }); }
  put(path, body) { return this.request('PUT', path, { body }); }
  patch(path, body) { return this.request('PATCH', path, { body }); }
  delete(path) { return this.request('DELETE', path); }
}
```

### Queue Polling (Web)

```javascript
// features/queue/QueuePolling.js
class QueuePoller {
  constructor(centreId, date, interval = 5000) {
    this.centreId = centreId;
    this.date = date;
    this.interval = interval;
    this.timer = null;
    this.callbacks = [];
  }
  
  start() {
    this.poll();
    this.timer = setInterval(() => this.poll(), this.interval);
  }
  
  stop() { clearInterval(this.timer); }
  
  async poll() {
    try {
      const data = await api.get(`/queue/live?centre_id=${this.centreId}&date=${this.date}`);
      this.callbacks.forEach(cb => cb(data));
    } catch (e) {
      console.error('Queue poll failed', e);
    }
  }
  
  onUpdate(callback) { this.callbacks.push(callback); }
  offUpdate(callback) { this.callbacks = this.callbacks.filter(cb => cb !== callback); }
}
```

### UI Theme (Shared)

**Colors**:
```css
:root {
  --primary: #2E7D32;        /* Dark green */
  --primary-light: #4CAF50;  /* Medium green */
  --primary-lighter: #81C784;/* Light green */
  --primary-bg: #E8F5E9;     /* Very light green bg */
  --secondary: #1565C0;      /* Blue for info */
  --warning: #F57F17;        /* Amber */
  --danger: #C62828;         /* Red */
  --success: #2E7D32;        /* Green */
  --text-primary: #1B1B1B;   /* Near black */
  --text-secondary: #4A4A4A; /* Dark gray */
  --text-muted: #757575;     /* Medium gray */
  --bg-primary: #FFFFFF;     /* White */
  --bg-secondary: #F5F5F5;   /* Light gray */
  --border: #E0E0E0;         /* Border gray */
  --shadow: 0 2px 8px rgba(0,0,0,0.08);
  --radius: 8px;
  --transition: 0.2s ease;
}
```

**Status Badges**:
```css
.badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.badge-waiting { background: #FFF3E0; color: #E65100; }
.badge-called { background: #E3F2FD; color: #1565C0; }
.badge-in-progress { background: #E8F5E9; color: #2E7D32; }
.badge-completed { background: #E8F5E9; color: #1B5E20; }
.badge-cancelled { background: #FFEBEE; color: #C62828; }
.badge-skipped { background: #F3E5F5; color: #6A1B9A; }
.badge-pending { background: #FFF3E0; color: #E65100; }
.badge-confirmed { background: #E3F2FD; color: #1565C0; }
.badge-paid { background: #E8F5E9; color: #1B5E20; }
.badge-failed { background: #FFEBEE; color: #C62828; }
.badge-rejected { background: #FFEBEE; color: #C62828; }
.badge-verified { background: #E8F5E9; color: #2E7D32; }
```

### Shared Components

Both applications use consistent:
- Status badge colors/labels
- Date/time formatting (ISO 8601 in API, localized in UI)
- Pagination controls
- Empty states
- Loading skeletons
- Error displays
- Confirmation dialogs

## Communication Contract

### API Versioning
- URL prefix: `/api/v1/`
- Header: `Accept: application/vnd.fps.v1+json`
- Backward compatibility: additive only

### Request/Response Format

**Success**:
```json
{
  "success": true,
  "data": {},
  "meta": { "pagination": {}, "timestamp": "2026-09-08T10:30:00Z" }
}
```

**Error**:
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid input",
    "details": { "field": ["error message"] }
  },
  "meta": { "request_id": "req_abc123", "timestamp": "2026-09-08T10:30:00Z" }
}
```

### Common Headers
- `Authorization: Bearer <jwt>` (Flutter)
- `Cookie: PHPSESSID=...` (Web)
- `X-CSRF-Token: ...` (Web mutating)
- `X-Request-ID: uuid` (Generated by client, echoed by server)
- `Accept-Language: en, hi` (For localized responses)

---

**Next**: [04-backend-architecture.md](04-backend-architecture.md) for PHP backend details.