# Phase 03 — PHP Backend Foundation

## 1. Objective

Implement the core backend infrastructure: middleware pipeline, validators, base models/services, error/exception mapping, and the API response envelope. Makes the skeleton production-shaped and testable.

## 2. Prerequisites

- Phase 01 (foundation)
- Phase 02 (schema + seed)

## 3. Features

- Middleware pipeline + MiddlewareInterface
- AuthMiddleware (placeholder), CorsMiddleware, RateLimitMiddleware (stub), CsrfMiddleware (web), MaintenanceMiddleware (reads setting)
- Validator base + field rules
- Base Model with prepared-statement helpers
- Base Service pattern
- Error/Exception mapping to HTTP + error codes ([06-api-architecture.md](06-api-architecture.md))
- Common helpers: response, hashing, token generation, request_id, pagination
- API version prefix handling (`/api/v1/`)

## 4. Files to Create

```
app/Core/MiddlewarePipeline.php
app/Middleware/MiddlewareInterface.php
app/Middleware/AuthMiddleware.php
app/Middleware/CorsMiddleware.php
app/Middleware/CsrfMiddleware.php
app/Middleware/RateLimitMiddleware.php
app/Middleware/MaintenanceMiddleware.php
app/Middleware/ScopeMiddleware.php
app/Validators/ValidatorInterface.php
app/Validators/Validator.php
app/Exceptions/*.php   (AppException, Validation, Authentication, Authorization, NotFound, Conflict, RateLimit, Maintenance, ExternalService, Database)
app/Services/SettingService.php
app/Services/AuditService.php
app/Services/RateLimiter.php
app/Models/BaseModel.php
app/Models/User.php (basic)
config/middleware.php
```

## 5. Files to Modify

- `public/index.php` (pipeline integration)
- `app/Core/Router.php` (middleware support per route)
- `config/routes.php` (apply middleware to example routes)

## 6. Database Changes

- None new (uses Phase 02 tables where needed: system_settings for maintenance, cache for rate limits optional)

## 7. API Changes

- All responses follow the standard envelope
- Errors map to documented codes
- MaintenanceMiddleware returns 503 when `maintenance_mode` on (except health)

## 8. Backend Logic

- Pipeline iterates middleware in order: Maintenance → CORS → Auth → RateLimit → Role → Permission → Scope → (controller)
- Validator parses rule strings (required|string|max:200|email...) and returns field errors
- AuditService inserts into audit_logs (enriching IP/UA/request_id)
- RateLimiter uses DB/file store (per [29-rate-limiting.md](29-rate-limiting.md))

## 9. Flutter Changes

- None yet (API contract ready though)

## 10. Staff/Admin Changes

- None yet

## 11. Permissions

- Middleware hooks ready; permission checks implemented in Phase 05 (stub for now)

## 12. Validation

- Validator rules: required, string, int, integer, email, mobile (10-digit), date, date_format, time, in, max, min, exists, unique, confirmed, boolean, json, array
- Returns 400 VALIDATION_ERROR with details map

## 13. Error Handling

- Exception handler maps each exception type to code+HTTP status
- Production: generic message
- All exceptions to application.log + error_logs table

## 14. Security

- CORS restricted to configured origins
- CSRF double-submit for web-origin mutations
- No internal details in responses

## 15. Logging/Audit

- AuditService functional (used from next phases)
- api.log request logging (sanitized)

## 16. Notifications

- None

## 17. Configuration Changes

- CORS origins, security header settings in config

## 18. Dependencies

- None new

## 19. Completion Criteria

- [ ] Middleware pipeline runs on example routes
- [ ] Validator rejects invalid input with detailed errors
- [ ] Exceptions map to correct codes/status
- [ ] Maintenance mode 503 tested
- [ ] Standard envelope everywhere

## 20. Testing Checklist

- [ ] POST with missing field → 400 VALIDATION_ERROR + details
- [ ] POST invalid email → validation error
- [ ] Maintenance ON → non-health returns 503 MAINTENANCE
- [ ] Maintenance ON → /health still 200
- [ ] CORS blocked origin → no CORS headers / blocked
- [ ] CSRF missing on web mutation → 400/403
- [ ] Exception → logged, generic client message
- [ ] Audit insert works via AuditService

## 21. What NOT to Implement

- No real login
- No farmer/staff features
- No business logic
- No notification sending
- No complex auth decisions (Phase 04)

---

**Depends on**: Phase 01, 02
**Feeds into**: Phase 04+