# Phase 01 — System Architecture & Project Foundation

## 1. Objective

Establish the project skeleton: directory structure, configuration approach, bootstrap, routing, and the request lifecycle. No business features yet — just a working foundation that serves a health check.

## 2. Prerequisites

- Phase 00 complete (business rules documented)
- Local dev environment: PHP 8.1+, MySQL 8.0+, Apache/XAMPP

## 3. Features

- PHP backend skeleton (app/config/routes/public/storage/database/views)
- Front controller (`public/index.php`)
- Autoloader (PSR-4-style)
- Router (simple regex/path matching)
- Request & Response wrapper
- PDO Database wrapper
- Centralized error handler
- Health endpoints (`GET /health`, `GET /health/maintenance`)
- Config via `.env`
- Directory READMEs

## 4. Files to Create

```
.env.example
.gitignore (optional, even no git workflow)
public/index.php
public/.htaccess
app/bootstrap.php
app/Core/App.php
app/Core/Router.php
app/Core/Request.php
app/Core/Response.php
app/Core/Database.php
app/Core/Container.php
app/Core/Bootstrap.php
app/Core/ErrorHandler.php
app/Helpers/helpers.php
app/Controllers/HealthController.php
config/config.php
config/database.php
config/routes.php
storage/.gitkeep
storage/logs/.gitkeep
storage/app/.gitkeep
storage/cache/.gitkeep
storage/temp/.gitkeep
```

## 5. Files to Modify

- None (new project)

## 6. Database Changes

- None yet (Phase 02 defines schema)

## 7. API Changes

- `GET /health` → `{status, database, storage, version, time}`
- `GET /health/maintenance` → `{maintenance, message, expected_available_at, support_contact}` (reads from settings, default off)

## 8. Backend Logic

- Bootstrap loads `.env` → config
- Register autoloader
- Create Database (lazy PDO)
- Match route from REQUEST_URI
- Execute controller → Response
- ErrorHandler catches all exceptions, logs, maps to safe JSON

## 9. Flutter Changes

- None (Phase 15)

## 10. Staff/Admin Changes

- None (Phase 16)

## 11. Permissions

- Health endpoints public (no auth)

## 12. Validation

- Router returns 404 for unknown routes
- Ensure `.env` parsing handles missing keys gracefully

## 13. Error Handling

- Production: `display_errors=Off`, generic message to user
- All exceptions logged to `storage/logs/application.log`
- Health check reports DB/storage status (not raw errors)

## 14. Security

- HTTPS redirect in `.htaccess`
- `.env` outside web root
- No sensitive data in health output

## 15. Logging/Audit

- Basic logger: application.log
- Request ID generated per request

## 16. Notifications

- None

## 17. Configuration Changes

- `.env.example` with all documented keys (DB, JWT, ENCRYPTION, rate limits)
- Config loader reads env

## 18. Dependencies

- None (pure PHP, no composer packages yet)

## 19. Completion Criteria

- [ ] `GET /health` returns UP with DB/storage checks
- [ ] Unknown route returns 404 JSON
- [ ] Exceptions logged
- [ ] `.env.example` present
- [ ] Document root only serves public/

## 20. Testing Checklist

- [ ] Hit `/health` → 200
- [ ] Hit `/health/maintenance` → 200, maintenance=false
- [ ] Hit `/unknown` → 404 JSON
- [ ] Trigger an exception → logged in application.log, no stack leak to client
- [ ] `.htaccess` HTTPS redirect works (configured)

## 21. What NOT to Implement

- No auth
- No DB tables
- No CRUD
- No framework
- No Composer dependency for framework
- No queue/notifications

---

**Depends on**: Phase 00
**Feeds into**: Phase 02, Phase 03