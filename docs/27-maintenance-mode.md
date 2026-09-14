# Maintenance Mode

## Overview

Super Admin can enable/disable maintenance mode. When active, public APIs return 503, farmer app shows a maintenance screen, and authorized admins (Super Admin) can bypass.

## Configuration

Stored in `system_settings`:
```
maintenance_mode                    (BOOL, public)
maintenance_message                 (STRING, public)
maintenance_expected_available_at   (STRING, public)
support_contact_phone               (STRING, public)  [reuse]
support_contact_email               (STRING, public)  [reuse]
```

## Behavior When Enabled

| Component | Behavior |
|-----------|----------|
| **Farmer App** | Shows maintenance screen (message, expected availability, support info) |
| **Public APIs** | Return 503 with `MAINTENANCE` error code + message |
| **Flutter Login** | Blocked (503) unless user is authorized admin |
| **Staff/Admin Portal** | Accessible to authorized admins; login allowed for Super Admin; others see maintenance |
| **Health endpoints** | Remain available (`/health`, `/health/maintenance`) |
| **Super Admin** | Can bypass maintenance (send special bypass header/token, or role check) |

## Middleware

```php
// app/Middleware/MaintenanceMiddleware.php
class MaintenanceMiddleware implements MiddlewareInterface {
    public function handle(Request $request, callable $next) {
        // Always allow health + maintenance-status endpoints
        if (in_array($request->getPath(), ['/health', '/health/maintenance'])) {
            return $next($request);
        }

        // Check maintenance mode (fresh DB read)
        if (setting('maintenance_mode') === true) {
            // Super Admin bypass
            $user = $request->getUser();
            if ($user && $user['is_super_admin'] === 1) {
                return $next($request);   // bypass
            }

            // Authorized admin bypass (manage_maintenance or is_admin scope)
            if ($user && $this->hasManageMaintenance($user)) {
                return $next($request);
            }

            // Everyone else blocked
            return Response::maintenance(
                setting('maintenance_message', 'System is under maintenance'),
                setting('maintenance_expected_available_at', '')
            );
        }

        return $next($request);
    }
}
```

### Bypass Mechanism (Super Admin)
Super Admin bypasses automatically by role. Optionally support a header `X-Maintenance-Bypass` validated when Super Admin, for debugging from the Flutter app.

## Maintenance Screen (Farmer App)

When Flutter receives `MAINTENANCE` error (503):
- Store flag from `/health/maintenance`
- On startup + on 503, show MaintenanceScreen
- Display:
  - Message (from setting, localized)
  - Expected available time (if set)
  - Support contact (if set)
- "Retry" button re-checks status

```dart
class MaintenanceScreen extends StatelessWidget {
  final String message;
  final String? expectedAvailableAt;
  final String? supportContact;
  ...
}
```

## Maintenance Screen (Web Portal)

If non-admin hits the portal during maintenance:
- Show maintenance HTML page (message, availability, support)
- Redirect to login only for authorized admin

## Toggle API

`PATCH /maintenance` (Super Admin, manage_maintenance):

```json
{
  "enabled": true,
  "message": "Maintenance on 2026-09-15 02:00-04:00 IST",
  "expected_available_at": "2026-09-15T04:00:00Z"
}
```

## Audit

Every enable/disable action audited:
- user_id, action TURN_ON / TURN_OFF, message, expected_available_at, timestamp

## Notifications (Optional)

- Optionally notify admins via in-app/email when maintenance toggled (nice-to-have)

## Cron / Monitoring

- Health monitor includes maintenance-status in checks
- If maintenance left on accidentally, alert (optional)

## No Hardcoding

- Maintenance state is always read from `system_settings`
- Disabled by default
- Not hardcoded anywhere

---

**Next**: [28-security.md](28-security.md) for security implementation.