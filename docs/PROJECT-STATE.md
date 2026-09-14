# Project State Tracker

## Current Phase
**Phase 18** — Deployment + Production Readiness — ⏳ In Progress (prep/artifacts complete; live-host actions pending real domain/host/keys — see §"Phase 18 Status (2026-09-11)")

## Phase Status

| Phase | Status | Started | Completed | Notes |
|-------|--------|---------|-----------|-------|
| 00 - Project Definition & Business Rules | ✅ Complete | - | - | Documentation complete |
| 01 - System Architecture & Foundation | ✅ Complete | - | - | Project skeleton built, health endpoints live |
| 02 - Database Schema & Migrations | ✅ Complete | 2026-09-08 | 2026-09-08 | DB created, 34 tables, migration runner + idempotent seeder |
| 03 - PHP Backend Foundation | ✅ Complete | 2026-09-08 | 2026-09-08 | Middleware pipeline, validators, base model/services, exception mapping, rate limiter, audit service |
| 04 - Authentication + Sessions + Tokens | ✅ Complete | 2026-09-08 | 2026-09-08 | JWT+session auth, OTP registration/reset, lockout, session mgmt, CSRF, remember-me |
| 05 - RBAC + Permission Manager | ✅ Complete | 2026-09-08 | 2026-09-08 | RbacService+ScopeService, Role/Permission/Scope middleware, admin staff/role/permission API, negative-priority overrides
| 06 - System Settings + Secrets + Maintenance | ✅ Complete | 2026-09-08 | 2026-09-08 | Type-safe cached settings, AES-256-GCM encrypted secrets, platform maintenance mode with SA bypass |
| 07 - Language System + File Manager | ✅ Complete | 2026-09-09 | 2026-09-09 | Locale detection chain, en/hi packs, admin translation CRUD, safe upload/download/delete with scopes |
| 08 - Centre + Staff Management | ✅ Complete | 2026-09-09 | 2026-09-09 | Centre CRUD + ACTIVE/INACTIVE/CLOSED status, staff create/update/status/centre-assign with role hierarchy + district/centre scoping |
| 09 - Slots + Capacity Management | ✅ Complete | 2026-09-09 | 2026-09-09 | Slot model/generator/service/validator/controller, CANCELLED status ENUM, generate-slots cron, capacity floor, derived booked counts, scoped slots RBAC |
| 10 - Bookings + Tokens | ✅ Complete | 2026-09-09 | 2026-09-09 | Booking CRUD + PENDING/CONFIRMED status machine, token issuance (hashed), farmer/admin cancel + capacity release, booking settings + crops, `expire-*` cron jobs |
| 11 - Queue Management | ✅ Complete | 2026-09-09 | 2026-09-09 | Queue engine + live queue: atomic call-next (GET_LOCK + guarded UPDATE + 2s wave), skip/no-show/recall, FIFO positions + renumber, live PII-safe aggregates, ETA, farmer self-service, scoped operator stats, cancel/expire integration, queue-notify cron + 42/42 E2E |
| 12 - Procurement + Approval/Reversal | ✅ Complete | 2026-09-09 | 2026-09-09 | Start-per-called-entry multi-crop PENDING flow, QC capture, submit with manual/auto verification policy, operator reject with reason, manager approve/reject, farmer own-status views, scoped read routes + 28/28 E2E |
| 13 - Payment Status | ✅ Complete | 2026-09-09 | 2026-09-09 | Payment auto-created per VERIFIED procurement (amount = wt × effective rate, 2dp), crop rate management (base + centre/date overrides), release with reference/method + idempotency + duplicate guard, cancel (PENDING/INITIATED) / reverse (RELEASED, district+), farmer statement, scoped operator/admin lists, notification events enqueued + 55/55 E2E |
| 14 - Notification System (OneSignal Push + OTP Gateway) | ✅ Complete | 2026-09-11 | 2026-09-11 | OneSignalService + NotificationService + NotificationTemplateService/OtpTemplateService, en/hi templates, NotificationLog/NotificationTemplate models, send-pending/retry cron jobs, notifications API + admin index/summary/test-push, 2FA endpoints (verify/resend/enable/disable/challenge), POST /auth/devices device registration, masked logging; runtime push delivery requires real OneSignal keys (blocked) |
| 15 - Flutter Farmer App | ✅ Complete (code) | 2026-09-11 | 2026-09-11 | Full app built: 24 routes, auth/2FA/OTP/registration, multi-crop booking, live queue (5s poll), procurement/payment views, inbox, en/hi ARB l10n, OneSignal deep-links, secure storage; 13/13 widget+unit tests green, analyze 0 issues, release APK built; runtime E2E vs staging pending (no staging URL in env) |
| 16 - Staff/Admin Portal | ✅ Complete (code) | 2026-09-11 | 2026-09-11 | Full vanilla-JS portal built in `public/portal/` (18 pages: dashboard, queue + call-next/skip/no-show/recall, procurements capture/submit/reject, payments release/reverse, bookings, approvals, centres, slots + generate, staff + permissions matrix, rates, notifications, settings, secrets, maintenance toggle, translations, files, audit, reports + CSV export; en/hi; session + CSRF web auth); all 26 JS files pass `node --check`, live-server login+CSRF smoke OK, 19 admin/operator endpoints return 200; browser click-through verification pending |
| 17 - Integration + Testing + Security | ✅ Complete | 2026-09-11 | 2026-09-11 | Smoke 37/37, edge 8/9, load 6/6 phases (24 OK / 0 Fail, no oversell), security scan 13/13 (3 CRITICAL, 6 HIGH, 4 MEDIUM — all pass), Flutter analyze clean + 13/13 tests pass; booking race fix (booking_sequences atomic table), probe fixes (HTTP method + operator mobile), security headers + HttpOnly session cookie |
| 18 - Deployment + Production Readiness | ⏳ In Progress | 2026-09-11 | - | Deploy artifacts + docs + code guards DONE; live-host actions (DNS/SSL/migrate/seed/cron/OTP/push/APK on device) require real domain+host+LIVE keys (see §"Phase 18 Status (2026-09-11)") |

## Completed Work
- [x] Complete documentation structure created
- [x] All 40 core documentation files generated
- [x] All 19 phase files generated
- [x] README.md created
- [x] PROJECT-STATE.md created
- [x] Phase 01: Project skeleton (public/app/config/database/storage directories)
- [x] Phase 01: Front controller + autoloader + router + request/response wrappers
- [x] Phase 01: PDO Database wrapper + centralized ErrorHandler
- [x] Phase 01: Health endpoints (GET /health, GET /health/maintenance)
- [x] Phase 01: .env config loader + .env.example + .gitignore
- [x] Phase 01: Storage directories with .gitkeep files + directory protection (.htaccess)
- [x] Phase 02: Database `farmer_procurement` created (utf8mb4 / utf8mb4_unicode_ci)
- [x] Phase 02: 33 migration files (one per table) + `migrations` runner table = 34 tables
- [x] Phase 02: Custom migration runner (app/console/migrate.php: run / --rollback / --status)
- [x] Phase 02: Idempotent seeder (app/console/seed.php + database/seeders/*)
- [x] Phase 02: Seeded 5 roles, 28 permissions, 74 role_permissions, 2 languages, 29 settings, 6 secrets (placeholders), 5 districts (Phase 06 later added settings.view/settings.manage/settings.secret_manage/maintenance.manage → 32 perms, 77 mappings)
- [x] Phase 02: Verified: clean/migrate, no-op re-run, status, rollback, idempotent re-seed, FK enforcement, Hindi round-trip
- [x] Phase 03: Exception hierarchy (10 exceptions) + ErrorHandler mapping (400/401/403/404/409/429/502/503/500)
- [x] Phase 03: Middleware pipeline + MiddlewareInterface + pipeline order (Maintenance → CORS → Auth stub → RateLimit)
- [x] Phase 03: Middleware: Maintenance (reads system_settings), CORS (origin allowlist), Auth (pass-through stub w/ header user-context), Csrf (double-submit web), RateLimit (DB sliding window), Scope (placeholder)
- [x] Phase 03: Validator rule engine (19 rules) → 400 VALIDATION_ERROR with field-error map
- [x] Phase 03: BaseModel (find/findBy/insert/update/soft-delete/count/paginate) + User model
- [x] Phase 03: SettingService (per-request cache, type casting) + AuditService (enriched inserts) + RateLimiter (rate_limit_logs table, headers)
- [x] Phase 03: Standard response envelope everywhere (success/data/error + meta.request_id)
- [x] Phase 03: api.log sanitized request logging (method/endpoint/status/duration)
- [x] Phase 03: config/middleware.php stacks (api/web/health), routes support per-route + group middleware
- [x] Phase 03: New migration: rate_limit_logs (+34 → 35 tables)
- [x] Phase 03: Verified all phase-03.md testing-checklist items (validation, maintenance 503 + health 200 + SA bypass, CORS, CSRF, exception, audit, rate limit 429)
- [x] Phase 04: JwtService (HS256 claims sub/type/jti/iat/exp) + TokenService; Access 900s, refresh 604800s
- [x] Phase 04: OtpService (deterministic verification_id salt, sha256 OTP hash, expiry/cooldown/attempts/resend caps) + LoginHistoryService (attempt log w/ IP, UA, status)
- [x] Phase 04: SessionService (create/find/validate/rotate/extend/revoke/login-history, web 30min vs app 7day context) + RememberTokenService (persistent 30-day)
- [x] Phase 04: AuthService (login/webLogin/refresh rotation/logout/changePassword/resetPassword/authenticateAccessToken/presentUser/establishSession) + FarmerAuthService + PasswordResetService
- [x] Phase 04: AuthValidator (register/verifyOtp/resendOtp/completeRegistration/appLogin/webLogin/logout/refresh/forgot/reset/change) + Validator rules strong_password/letters_digits/digits/hex/regex
- [x] Phase 04: AuthMiddleware (real JWT + web-session resolution, user context w/ permissions, 401 TOKEN_EXPIRED/UNAUTHENTICATED/TOKEN_REVOKED, security audit)
- [x] Phase 04: AuthController (register/verify-otp/resend-otp/complete-registration/login/refresh/me/logout/forgot/reset + webLogin/webLogout/webMe/webChangePassword) + SessionController (list/revoke/revoke-all/refresh-current/login-history)
- [x] Phase 04: Exceptions → (errorCode, message, httpStatus[, details]) arg order; single-arg NotFoundException sites fixed to explicit codes (USER_NOT_FOUND/SESSION_NOT_FOUND/OTP_NOT_FOUND)
- [x] Phase 04: CsrfMiddleware primes csrf_token cookie on GET/HEAD/OPTIONS; web change-password keeps current session; web logout revokes remember-token + records logout
- [x] Phase 04: Routes + config (config/routes.php all auth/session routes; config.php jwt/otp blocks; .env.example OTP_* keys)
- [x] Phase 04: Verified full E2E + security checklist (register→otp→complete→login→me→refresh rotation→logout; revoked/expired-token 401/400; CSRF 403; web login/logout; cooldown 429; OTP rate-limit 429; lockout after 5 fails)
- [x] Phase 05: Models Role/Permission/RolePermission/UserPermission (overridesFor/upsert/remove; `Database::insert` returns lastInsertId)
- [x] Phase 05: RbacService (can/assertCan/assertRoleLevel; super-admin bypass incl. config role id; negative-priority overrides `granted=0`; per-request static cache w/ clearCache/clearAllCache; grant/revoke/removeOverride; allPermissionNames)
- [x] Phase 05: ScopeService (scopeFor all/district/centre/self/none; canAccessCentre; centreIdsFor via centre_staff; districtIdsFor via centres+district fallback from farmers; assertCentreScope/assertUserScope)
- [x] Phase 05: RoleMiddleware (`RoleMiddleware:30` min-level or name list, 403 ROLE_REQUIRED), PermissionMiddleware (key list, 403 PERMISSION_DENIED), ScopeMiddleware (auto/centre/district, route-param/query/body extraction, 403 SCOPE_DENIED); denials logged to storage/logs/security.log
- [x] Phase 05: MiddlewarePipeline/Bootstrap `Class:param` support; per-route guard chain role→permission→scope from config/middleware.php route_defaults
- [x] Phase 05: AuthMiddleware buildUserContext attaches resolved effective permissions; User::permissionNames delegates to RbacService
- [x] Phase 05: RolePermissionController + routes (GET/POST /api/v1/admin/staff, PUT .../{id}/permissions, PUT .../{id}/role, GET /api/v1/admin/roles, GET /api/v1/admin/permissions); guards: hierarchy (no role ≥ actor), SUPER_ADMIN creation SA-only, SINGLE_SUPER_ADMIN 409 on demoting last active SA, per-user audit (STAFF_CREATED/ROLE_CHANGED/PERMISSION_OVERRIDE)
- [x] Phase 05: Verified Unit 26/26 + E2E 35/35 (FARMER 403, CO cross-centre 403, DA cross-district 403, SA 200, grant→revoke override next-request enforced, DA creating SUPER_ADMIN 403, last-SA demote 409, role-change cache flush, audit rows + security.log)
- [x] Phase 06: config/settings_defaults.php registry (29 defaults, type/public/sensitive/group/description)
- [x] Phase 06: SecurityException (errorCode SECURITY_ERROR, httpStatus 500)
- [x] Phase 06: CacheService (file-based, TTL, atomic rename writes, namespace-stamped cache)
- [x] Phase 06: SecretService (AES-256-GCM, 12-byte IV, 128-bit tag, `base64(iv)|base64(tag)|base64(cipher)` envelope, 64-hex ENCRYPTION_KEY from env, mask/last4 on read, tamper detection → SecurityException + security.log, list() masked only)
- [x] Phase 06: Models SystemSetting (findByKey/upsertByKey/allRows) + SystemSecret (findByKey/upsert/allRows, both encrypted_value+iv columns)
- [x] Phase 06: SettingService rewrite (per-request memory + CacheService file cache; typed getters getString/Int/Float/Bool/Array/Json; registry merge; whitelist validation by value_type; set/setMany; grouped all() with sensitive streaming as exists-flag only; maintenance_mode read fresh from DB every request bypassing cache)
- [x] Phase 06: Admin controllers SettingController (GET grouped + PUT whitelist + audit) / SecretController (GET masked metadata + PUT upsert + audit) / MaintenanceController (PUT toggle + audit) with settings.view/Manage, settings.secret_manage, maintenance.manage gates
- [x] Phase 06: MaintenanceMiddleware rewritten (fresh DB read, resolves SA user itself for bypass, 503 MAINTENANCE_MODE + message + expected_available_at, allows /health/*; fixes pre-existing missing AuthMiddleware::resolveFromHeaders crash)
- [x] Phase 06: Seeders — added permissions settings.view/settings.manage/settings.secret_manage/maintenance.manage (28→32); SA granted all 4, DA granted settings.view (72→77 mappings); seed.php count updated
- [x] Phase 06: Routes + middleware route_defaults (admin_settings_view/admin_settings_manage/admin_secrets/admin_maintenance)
- [x] Phase 06: .env ENCRYPTION_KEY set to valid 64-char hex; .env.example docs + generation command
- [x] Phase 06: Verified Unit 39/39 + E2E 36/36 (settings typed GET/PUT + type-violation 400, secret set→masked last4→no plaintext leak, maintenance on→503 code/message/expected_at→SA bypass 200→health live; DA view 200 vs SA-manage-only 403 on settings/secrets/maintenance; anonymous 401/403) via app/console/phase06_unit_test.php + phase06_e2e_verify.php
- [x] Phase 07: config/locales.php (DEFAULT_LOCALE=en, fallback=en, supported en/hi) + resources/strings/en.php + hi.php (hi omits internal.admin.tooltip deliberately for fallback test)
- [x] Phase 07: LocalizationService (normalize en-IN→en, isSupported, preferredLocale chain X-Locale→Accept-Language→user pref→DEFAULT_LOCALE, parseAcceptLanguage q-values, guarded information_schema hasColumn cached) + LocaleMiddleware after Auth in api+web stacks (static Request::setLocale/currentLocale, removed instance setLocale that caused "Cannot redeclare")
- [x] Phase 07: TranslationService (rawPack/packFor with CacheService namespace l10n, DB overrides, en-fallback fill, translate with {k}/:k interpolation, listForAdmin, bulkUpsert MapUpdatingKeyPattern, export/import, invalidate() clears all packs+langs, throttled missing-key log 1/min/static-set to storage/logs/translations_missing.log) + Models Language/Translation
- [x] Phase 07: TranslationsController + routes (GET /api/v1/languages, GET /api/v1/translations/{code}, GET/PUT /api/v1/admin/languages/{code}/translations, GET /api/v1/admin/translations, GET /api/v1/admin/translations/export, DELETE .../translations; translations.view all roles, translations.manage SA/DA)
- [x] Phase 07: config/files.php (ALLOWED_MIME_TYPES default jpg,png,webp,pdf,csv,xlsx; FILE_UPLOAD_MAX_SIZE merge) — .env.example updated with DEFAULT_LOCALE + ALLOWED_MIME_TYPES
- [x] Phase 07: FileService (storeUpload validate→finfo mime detection→uuid-relative store private/files/<32hex>.<ext>→insert→file_references, validateUpload rejection of executables/php, present, list w/ applyVisibilityClause, listFolders/createFolder, soft delete w/ FILE_IN_USE vs force hard delete, centreIdsForFile, accessibleCentreIds) + FileException + Models File/FileFolder/FileReference
- [x] Phase 07: FileController + routes (POST /api/v1/files/upload, GET /api/v1/files, GET/DELETE /api/v1/files/{id} w/ sandbox CSP download headers, GET/POST /api/v1/files/folders) + Admin/FileManagerController (GET/DELETE /api/v1/admin/files w/ files.manage_all; force delete staff-only, FARMER blocked)
- [x] Phase 07: Migration 20260909000035_add_scope_columns_to_files_and_file_folders.php (ENUM system/user/centre, batch 4 applied); DB version → 4
- [x] Phase 07: Seeders — permissions +7 (translations.view/translations.manage/files.upload/files.download/files.delete/files.folders/files.manage_all → 39 total; 109 role_permissions; grants: all roles incl FARMER on files.upload/download/delete, staff on folders/manage_all; translations.seeder upserts 181 strings; DB restored to 181 post-E2E)
- [x] Phase 07: Fixed pre-existing config/routes.php crash (`$rbacMiddlewareConfig` undefined; routes.php now requires config/middleware.php itself)
- [x] Phase 07: Verified Unit 38/38 + E2E 62/62 via app/console/phase07_unit_test.php / phase07_e2e_seed.php / phase07_e2e_verify.php / phase07_e2e_reset.php (locale chain, pack fallback + removal fallback + unknown-key log, X-Locale override, admin CRUD cache invalidation, jpg uuid path in DB, .php 400 INVALID_MIME, 6MB 400 FILE_TOO_LARGE, owner 200 vs other-farmer 403, centre-scope file staff-only, referenced delete 409 FILE_IN_USE, force delete hard-removes, unreferenced delete soft + audit log; E2E data reset post-verification)
- [x] Phase 08: Migration 20260909000036_add_closed_to_procurement_centre_status.php (batch 5) adds 'CLOSED' to procurement_centres.status ENUM
- [x] Phase 08: Seeders — permissions +6 (centres.view/centres.manage/centres.status.manage/staff.view/staff.manage/districts.manage → 45 total; 125 role_permissions; SA +6, DA +5, CENTRE_MANAGER +3, CENTRE_OPERATOR +1 centres.view, FARMER +1 centres.view)
- [x] Phase 08: Models District (active/isActive), ProcurementCentre (byCode/codeExists/hasFutureBookings/districtId/manager/operators/staff/countActiveInDistrict/STATUS constants), CentreStaff (assignmentForUser/assign/role-aware reassign/removeForRole/removeAllForUser/centreForUser), User + changeStatus/roleNameFor/centreFor helpers
- [x] Phase 08: Validators CentreValidator (create/update/status/listFilters; code `^[A-Z0-9]{2,10}$`) + StaffValidator (create/update/status/centre/listFilters/resolveRole/isCentreRole) + BadRequestException (configurable errorCode, HTTP 400)
- [x] Phase 08: CentreService (create w/ code suggestion + CENTRE_CODE_EXISTS 409 + DISTRICT_NOT_FOUND + scope assert + audit; update; changeStatus w/ CENTRE_HAS_BOOKINGS guard; show; scoped list; details) + StaffService (create w/ ROLE_NOT_ALLOWED 400 hierarchy guard + centre-required for CM/CO + sentinel password + rbac cache clear; update; changeStatus revokes sessions + SA undeactivatable; assignCentre; show; scoped list; details w/ permissions preview)
- [x] Phase 08: DistrictController (public index) + Admin\CentreController (list/show/store/update/status) + Admin\StaffController (index/store/show/update/status/centre)
- [x] Phase 08: ScopeMiddleware 'staff_centre' mode (extracts centre_id from body, asserts CentreScope for centre-association requests per phase-08.md)
- [x] Phase 08: Routes + middleware route_defaults (centres_view/centres_manage/centres_status_manage/staff_view/staff_manage/staff_centre_assign/districts_manage; staff base routes repointed from RolePermissionController to Admin\StaffController, legacy /permissions + /role stay on RBAC)
- [x] Phase 08: Verified E2E 50/50 via app/console/phase08_e2e_verify.php (public districts/centres ACTIVE-only, centre create + duplicate-code 409, DA 403 on other district, status INACTIVE/ACTIVE/CLOSED + DA own 200/other 403 + CM own 200, staff CRUD + role hierarchy 400 ROLE_NOT_ALLOWED + no temp-password + permissions preview, deactivate→login 401 + reactivate, SA cross-district reassign 200, DA reassign within 200 / other 403; future-booking 409 skipped — requires slots Phase 09)
- [x] Phase 09: Migration 20260909000037_add_cancelled_to_slots_status.php (batch 6) adds 'CANCELLED' to slots.status ENUM
- [x] Phase 09: Migration 20260909000038_add_slot_booking_status_index.php (batch 6) adds composite `booking_slot_status_idx (slot_id, status)` on bookings
- [x] Phase 09: Seeders — permissions +3 (slots.view/slots.manage/slots.cancel → 48 total; role_permissions +11 → 136; grants: SA +3 slots.view/manage/cancel, DA +3, CENTRE_MANAGER +3, CENTRE_OPERATOR +1 slots.view, FARMER +1 slots.view); settings +5 slot.* (slot.horizon_days/default_duration_minutes/default_capacity/min_capacity/breaks → 34 total)
- [x] Phase 09: Model Slot (STATUS_ACTIVE/INACTIVE/FULL/CANCELLED constants, findByCentreDateTime, bookedCount derived from bookings NOT IN CANCELLED/REVERSED/EXPIRED, hasActiveBookings)
- [x] Phase 09: SlotGenerator (horizon default 7d, 15-min granularity default, ACTIVE centres only, skip closed days + break ranges, idempotent skip-existing, on-demand single-centre/range + cron) + SlotValidator (create/update/listFilters/generate)
- [x] Phase 09: SlotService (manual create w/ ACTIVE-centre + date>=today + centre-hours + granularity + no-duplicate guards; update w/ CAPACITY_BELOW_BOOKED floor + SLOT_EXISTS + cancel transition; cancel only AVAILABLE→CANCELLED else SLOT_HAS_BOOKINGS / SLOT_NOT_CANCELLABLE; delete only AVAILABLE zero-bookings else SLOT_HAS_BOOKINGS; public bookable list + scoped admin list/details; create/update/cancel audit)
- [x] Phase 09: Admin\SlotController (bookable/index/show/store/update/destroy/generate) + routes (GET /api/v1/slots public bookable; GET/POST /api/v1/admin/slots, GET /api/v1/admin/slots/{id}, PUT/DELETE /api/v1/admin/slots/{id}, POST /api/v1/admin/slots/generate) + middleware route_defaults slots_view/slots_manage/slots_cancel
- [x] Phase 09: app/console/cron.php (new, mirrors app/console/ convention) registers `--job=generate-slots` → SlotGenerator + AuditService SLOTS_GENERATED summary
- [x] Phase 09: Verified generator (16 slots/centre/day at centre 30-min duration, weekend/closed days skipped, idempotent re-run 0 generated) + cron end-to-end (18 ACTIVE centres → 1728 slots, 36 closed-day skips, audit row written; test rows cleaned from centre 10)
- [x] Phase 10: Migrations batch 7 (crops table + crops.id linkage; booking_crops +crop_id/FK + status ENUM; tokens +unique token_hash + status 'CANCELLED'; bookings default status → 'PENDING') applied, schema verified
- [x] Phase 10: Seeders — permissions +7 (bookings.create/bookings.view_own/bookings.view_any/bookings.cancel_any/tokens.view_own/tokens.view_any/crops.view → 53 total; role_permissions → 149; SA +7, DA/CM +5, CO +4, FARMER +4); settings +5 (booking.horizon_days/booking.min_lead_hours/booking.cancel_lead_minutes/booking.max_active/auto_confirm_on_payment → 39 total); +16 crops seeded via new crop_seeder.php (crop_seeder registered in seed.php)
- [x] Phase 10: Models Crop, Booking (incl. nextBookingSequence by DATE(created_at), countActiveForUser, crops, token), BookingCrop, Token (incl. activeForUser with centre/slot join)
- [x] Phase 10: BookingValidator (create/cancel/adminCancel/listFilters; manual crops validation using booking.max_crops_per_booking) + BookingCropService (resolve/insertLines/cancelLines/listForBooking/totalQuantityKg)
- [x] Phase 10: BookingService core orchestration (requireActiveCentre → requireBookableSlot → assertBookingWindow → assertActiveLimit → resolve crops → in-tx insert booking + crop lines + atomic reserveCapacity + issueToken on CONFIRMED + audit); cancel/reason + adminCancel (window guard vs reason bypass) + releaseCapacity; TokenService::qrData() added (FPS-TOKEN:<display>); exceptions ConflictException/BadRequestException/NotFoundException with errorCode
- [x] Phase 10: Controllers BookingController (store/index/show/crops/cancel/myToken) + Admin\BookingAdminController (index/show/cancel) + CropController (public index, no RBAC)
- [x] Phase 10: Routes + middleware route_defaults (bookings_create/bookings_view_own/tokens_view_own/bookings_view_any+Scope/bookings_cancel_any+Scope) — GET /crops, GET+POST /bookings, GET /bookings/{id}, GET /bookings/{id}/crops, POST /bookings/{id}/cancel, GET /my/token, GET+GET-{id}+POST-cancel /admin/bookings
- [x] Phase 10: app/console/cron.php + `expire-pending-bookings` and `expire-unarrived-bookings` jobs via expireBookingsByStatus helper (guarded UPDATE on status + slot datetime < NOW, releases capacity, CANCELLED tokens, audit)
- [x] Phase 10: Verified E2E 35/35 via app/console/phase10_e2e_verify.php (farmer gate FARMER_NOT_APPROVED via approved farmer, multi-crop PENDING no-token, DUPLICATE_BOOKING, cancel-within-window + crop lines emptied, CROP_NOT_FOUND, BOOKING_WINDOW_CLOSED, CENTRE_INACTIVE, SLOT_FULL via 2nd farmer, CANCELLATION_WINDOW_CLOSED, admin cancel + audit, auto-confirm → CONFIRMED + SHA-256 hashed token GKP-YYYY-NNNNN + stored hashed, /my/token, own list pagination + admin list)
- [x] Phase 11: Migrations batch 8 (queue_entries status ENUM +NO_SHOW, recall_eligible, recall_count, recalled_at, no_show_at, no_show_by, INDEX(centre_id,date,status); notification_logs +event_ref VARCHAR(64) + UNIQUE uq_nlog_event_ref) applied, schema verified
- [x] Phase 11: Seeders — permissions +6 (queue.view/queue.view_own/queue.call_next/queue.skip/queue.no_show/queue.stats → 61 total; role_permissions → SA/DA/CM/CO +view/call_next/skip/no_show/stats, FARMER +view/view_own); settings +5 (queue.avg_minutes_per_token=10/queue.grace_no_show_minutes=5/queue.notify_threshold=3/queue.call_batch=1/queue.recall_limit=1 → 44 total); settings_defaults registry desc typo fixed; seed.php expected counts bumped
- [x] Phase 11: Models QueueEntry (+byBookingId/countsByStatus) + QueueValidator (live/callNext/skip/noShow/recall/listFilters/stats) + QueuePositionService (nextPosition = MAX(position)+1, aheadCount over active rows, renumber 1..N by position,id — no transaction management)
- [x] Phase 11: QueueService — enqueueFromBooking (on CONFIRMED, in booking tx, dedup by booking_id); atomic callNext via GET_LOCK('fps_queue_call_<c>_<d>',5) + guarded UPDATE (id=? AND status='WAITING', rowCount=1) + 2s call-wave debounce (recent CALLED row) + auto-complete stale IN_PROGRESS (>60m) → single deterministic winner, 409 QUEUE_BUSY/CONCURRENT_UPDATE/QUEUE_EMPTY otherwise; skip (CALLED/IN_PROGRESS, sets recall_eligible = recall_count<queue.recall_limit); noShow (CALLED only, grace after queue.grace_no_show_minutes else 409); recall (SKIPPED, recall_count<limit, appended at end, recall_eligible=0, RECALL_LIMIT_REACHED 409); live aggregate (status counts, current = IN_PROGRESS else latest CALLED, last_called_token, avg_wait over last 10 completed, eta = waiting×avg, NO PII); myEntry (via Token::activeForUser → 404 QUEUE_NOT_FOUND; position/ahead/waited/eta); statusByToken (Token::byTokenNumber exact + booking.user_id ownership else 404); operatorList (farmer name/mobile + token, scoped); stats (served_today/avg_wait/completion_rate); markBookingCancelled (CALLED→CANCELLED + renumber, caller owns tx); notifyForEntry (INSERT IGNORE notification_logs QUEUE_CALLED event_ref queue_called_<id>)
- [x] Phase 11: Controllers QueueController (my/live/status) + Operator\QueueOperatorController (index/callNext/skip/noShow/recall/stats); routes GET /queue/my, GET /queue/live, GET /queue/{bookingToken}/status, GET /operator/queue, GET /operator/queue/stats, POST /operator/queue/call-next, POST /operator/queue/{entryId}/skip|no-show|recall; middleware route_defaults queue_view/queue_view_own/queue_call_next/queue_skip/queue_no_show/queue_recall/queue_stats/operator_queue_view; ScopeMiddleware +queue_entry mode (resolve centre via route entryId from queue_entries before assertCentreScope)
- [x] Phase 11: BookingService hooks — enqueueFromBooking after issueToken on CONFIRMED (in booking tx); markBookingCancelled on doCancel (token cancel path); cron expireBookingsByStatus now also marks cancelled queue entries + renumber
- [x] Phase 11: cron `queue-notify` job — scans WAITING entries for today+tomorrow, ahead < queue.notify_threshold → INSERT IGNORE notification_logs QUEUE_APPROACHING (event_ref queue_approach_<id>) → idempotent, deduped by event_ref unique
- [x] Phase 11: Verified E2E 42/42 via app/console/phase11_e2e_verify.php + phase11_callnext_probe.php parallel probe (5 farmers → jobs 1..5; /queue/my + /queue/live no-PII; parallel call-next exactly one winner = token1; operator list; skip + renumber; recall appends at end, 2nd recall 409; no-show pre-grace 409 + grace=0 → NO_SHOW; call-next FIFO token3; ETA = ahead×avg; other-centre operator 403 x2; live counts; token-status ownership 200/404; recall-limit RECALL_LIMIT_REACHED; cancel → CANCELLED + contiguous positions; stats fields; queue-notify dedup)
- [x] Phase 12: Migration 20260910000045_add_approval_columns_to_procurements.php (batch 9) — accepted_weight DECIMAL(12,3), damaged_qty DECIMAL(12,3), procurement_number VARCHAR(30) + UNIQUE uq_procurement_number, operator_note TEXT, approved_amount DECIMAL(14,2), approved_by + FK fk_proc_approved_by, approved_at, status ENUM +'PENDING_APPROVAL' ('PENDING','PENDING_APPROVAL','VERIFIED','IN_PROGRESS','COMPLETED','REJECTED'); down() drops accepted_weight/damaged_qty/operator_note; applied, DB version → 9
- [x] Phase 12: Seeders — permissions +6 (procurements.create/update/reject/approve/view_any/view_own → 67 total); role_permissions → 205 (SUPER_ADMIN +6, DISTRICT_ADMIN +6, CENTRE_MANAGER +6 incl approve, CENTRE_OPERATOR +5 create/update/reject/view_any/view_own — no approve, FARMER +1 view_own); settings +3 (procurement.approval_required=BOOL default 1/procurement.max_photos=INT 3/procurement.weight_precision=INT 2 → 47 total); settings_defaults registry updated
- [x] Phase 12: Model Procurement (STATUS constants PENDING/PENDING_APPROVAL/VERIFIED/IN_PROGRESS/COMPLETED/REJECTED) + ProcurementValidator (start/capture/reject/listFilters/approvalListFilters; listFilters statuses expanded to all six)
- [x] Phase 12: ProcurementService — start (requires CALLED/IN_PROGRESS entry + no existing procurements + scope; one PENDING per PENDING booking_crop; queue_entry → IN_PROGRESS + started_by; PROC_START audit; in-tx); capture (PENDING-only, weight_precision rounding, damaged_qty ≤ booked 400 WEIGHT_INVALID, max_photos, INSERT IGNORE photo refs via file_references unique (file_id,source_type,source_id), quality_grade/moisture_percent DB mapping, quantity_kg=accepted_weight, PROC_CAPTURE audit); submit (→ PENDING_APPROVAL if approval_required else → VERIFIED + verified_by/at + PROC_VERIFIED_PAYMENT_HOOK audit); reject (empty reason → 400 REASON_REQUIRED first, PENDING/PENDING_APPROVAL only, PROC_REJECT audit); operatorList/show (centreIdsFor scoping); farmerList/farmerShow (user_id ownership else 404 PROC_NOT_FOUND); approvalList/approvalShow (PENDING_APPROVAL filter default); helpers assertCentreScope→CentreMismatchException (403 CENTRE_MISMATCH), bookedQtyFor, replacePhotos, fireVerificationPaymentHook, nextProcurementNumber (PROC-Ymd-00001), present() DB→API mapping (grade←quality_grade, moisture_pct←moisture_percent, approved_amount stays null until Phase 13)
- [x] Phase 12: ApprovalService — approve (PENDING_APPROVAL → VERIFIED + approved_by/approved_at + verified_by/verified_at, approved_amount kept null Phase 13, PROC_APPROVE audit w/ payment PENDING_PHASE_13), reject (REASON_REQUIRED 400, PENDING_APPROVAL only, PROC_APPROVAL_REJECT audit)
- [x] Phase 12: Controllers Operator\ProcurementController (start/capture/submit/reject/index/show; gates procurements.create/update/reject/view_any) + Admin\ApprovalController (index/show/approve/reject; gate procurements.approve) + MyProcurementController (index/show; farmers' own view via procurements.view_own)
- [x] Phase 12: ScopeMiddleware +'procurement' mode (resolves centre via procurements.id → assertCentreScope, 403 SCOPE_DENIED/Procurement not found) + CentreMismatchException (extends AuthorizationException, errorCode CENTRE_MISMATCH HTTP 403 — AuthorizationException fixed code FORBIDDEN, hence dedicated class)
- [x] Phase 12: Routes + middleware route_defaults — POST /api/v1/operator/queue/{entryId}/start (procurements_create + ScopeMiddleware:queue_entry), PUT /api/v1/operator/procurements/{id} (capture, procurements_update + :procurement), POST .../{id}/submit (procurements_update + :procurement), POST .../{id}/reject (procurements_reject + :procurement), GET /api/v1/operator/procurements (list, permission-only + service-side scoping), GET .../{id} (show, procurements_show + :procurement), GET /api/v1/admin/approvals (list, permission-only), GET /api/v1/admin/approvals/{id} (approvals_show + :procurement), POST .../approve|reject (approvals_manage + :procurement), GET /api/v1/admin/procurements/{id} (approvals_show + :procurement), GET /api/v1/my/procurements[/{id}] (view_own, service-side ownership)
- [x] Phase 12: Verified E2E 28/28 via app/console/phase12_e2e_verify.php (2 centres + farmer1 3-crop booking / farmer2 1-crop booking; call-next → start → 3 PENDING + entry IN_PROGRESS; capture negative 400 WEIGHT_INVALID, damaged>booked 400, valid 200 weight/grade/moisture; submit → PENDING_APPROVAL; reject no-reason 400 REASON_REQUIRED + with-reason REJECTED; other-centre operator start/reject/show 403; manager approve → VERIFIED approved_amount NULL + approved_at; capture after VERIFIED 409 STATUS_IMMUTABLE; approval_required=0 → submit auto-VERIFIED; farmer list 3 independent statuses REJECTED/VERIFIED/VERIFIED; own 200 vs other-farmer 404; operator list/show 200, approval list/show 200, other-centre operator show 403)
- [x] Phase 13: Migration batch 10 (20260911000046_add_payment_enums_and_crop_rates.php — payments.status ENUM PENDING/INITIATED/RELEASED/CANCELLED/REVERSED, +rate_per_kg DECIMAL(10,2)/crop_name VARCHAR(100)/reversal_reason TEXT/reversed_by+FK/reversed_at/failure_reason/notes, UNIQUE uq_payment_procurement(procurement_id), new crop_rates table crop_id/centre_id NULL/district_id NULL/rate_per_kg DECIMAL(10,2)/effective_from DATE/effective_to DATE NULL/is_active TINYINT(1)/created_by+FK) + batch 11 (20260912000047_add_payment_release_columns.php — +payment_reference VARCHAR(100) NULL, released_by+FK fk_payment_released_by, released_at DATETIME NULL, booking_crop_id INT NULL, attempts INT UNSIGNED DEFAULT 0); applied, DB version → 11, 37 tables
- [x] Phase 13: Seeders — permissions +5 (payments.view/payments.release/payments.cancel/payments.reverse/rates.manage → 72 total); role_permissions → 219 (SUPER_ADMIN +5, DISTRICT_ADMIN +5, CENTRE_MANAGER +4 payments.view/release/cancel, CENTRE_OPERATOR +1 payments.view); settings +1 (payment.allow_operator_release=BOOL default 0 → 48 total); settings_defaults registry updated; crop_rate_seeder.php (16 crop base rates incl WHEAT 22.50, idempotent) registered in seed.php; re-seed applied (5 perms, 14 mappings, 1 setting, 16 rates)
- [x] Phase 13: Models Payment (STATUS constants, ELIGIBLE_FOR_RELEASE PENDING/INITIATED, ELIGIBLE_FOR_CANCEL PENDING/INITIATED, byProcurementId/byId/allForUserScoped, present) + CropRate (byId/activeForCropCentre/allFiltered with crop/centre/district joins, RATE_NOT_FOUND)
- [x] Phase 13: PaymentValidator (release → REFERENCE_REQUIRED 400 / METHOD_INVALID 400 on bad method, ref max 100; cancel/reverse require reason; createRate/updateRate rules; listFilters) + RateService (resolveRate/resolveRateByName centre+date effective override else base; createRate deactivates overlapping active rate + AUDIT RATE_CREATE; updateRate AUDIT RATE_UPDATE; listRates; getRatesForCrop flat present crop/centre/rate_per_kg/rate_source/effective_from)
- [x] Phase 13: PaymentService — createForProcurement (VERIFIED-gated, skip-existing, amount = accepted_weight × effective_rate ROUND 2dp, INSERT PENDING, rate fallback by crop id); release (validation-first, idempotent same ref+method returns existing, DUPLICATE_RELEASE 409 on different ref, PAYMENT_STATUS_INVALID 409, service double-guard: assertRoleLevel 50 else payment.allow_operator_release setting required, audit w/ masked ref + payment_released event enqueued); cancel (RELEASED/REVERSED → PAYMENT_STATUS_INVALID 409, conditional UPDATE + CONCURRENT_UPDATE guard, audit); reverse (RELEASED-only 409, RbacService assertRoleLevel 70 + payments.reverse in controller, audit + payment_reversed event); operatorList/adminList (scopeCentreIds w/ district fallback, status/date/q filters, farmer/centre/proc join present); farmerStatement/farmerShow (user_id ownership else 404 PAYMENT_NOT_FOUND); assertCentreScope → CentreMismatchException 403; enqueueEvent (INSERT IGNORE notification_logs channel SMS PENDING event_ref payment_released_<id>/payment_reversed_<id>); maskReference
- [x] Phase 13: Controllers Operator\PaymentController (index/show/release; payments.view/release gates, Request::getUser) + Admin\PaymentAdminController (index/show/cancel/reverse + rates storeRate 201/updateRate/listRates/getCropRates; rates.manage + RoleMiddleware:70 reverse; Request::query/all) + MyPaymentController (index/show; view_own_payments, ownership-scoped)
- [x] Phase 13: ScopeMiddleware +'payment' mode (resolvePaymentCentre via payments.id → assertCentreScope, 403 SCOPE_DENIED/payment not found); middleware route_defaults payments_view/payments_show(+scope)/payments_release(+scope)/payments_cancel(+scope)/payments_reverse(+scope)/payments_view_own/rates_manage([RoleMiddleware:70, PermissionMiddleware:rates.manage])/rates_view([PermissionMiddleware:crops.view])
- [x] Phase 13: Routes — GET /api/v1/crops/{cropId}/rates (rates_view), GET /api/v1/admin/crop-rates, POST /api/v1/admin/crop-rates (rates_manage), PUT /api/v1/admin/crop-rates/{id} (rates_manage), GET /api/v1/operator/payments (payments_view), GET /api/v1/operator/payments/{id} (payments_show), PUT /api/v1/operator/payments/{id}/release (payments_release), GET /api/v1/admin/payments (payments_view), GET /api/v1/admin/payments/{id} (payments_show), POST /api/v1/admin/payments/{id}/cancel (payments_cancel), POST /api/v1/admin/payments/{id}/reverse (payments_reverse), GET /api/v1/my/payments, GET /api/v1/my/payments/{id} (payments_view_own)
- [x] Phase 13: ApprovalService::approve now invokes PaymentService::createForProcurement after status → VERIFIED (audit new_value.payment includes payment id/amount/status); ProcurementService::fireVerificationPaymentHook likewise auto-creates payment on approval_required=0 submit path; fixes: PaymentValidator dropped invalid App\Core\Validator import (real base is App\Validators\Validator, same namespace); controllers use Request::getUser/query/all (userContext()/body() do not exist; getParam() only matches route params); storeRate uses Response::created (success() 2nd arg is meta, not status)
- [x] Phase 13: Verified full E2E 55/55 via app/console/phase13_e2e_verify.php (rate override 25.00 centre=c1 vs base 22.5 c2/w-o centre; admin create 201 + update 24.50/25.00 + list; booking1 → call-next → start → capture 80.5 → submit → approve VERIFIED → payment amount 2012.50 = 80.5×25.00 + rate 25.00 + unique; REFERENCE_REQUIRED 400 / METHOD_INVALID 400; operator policy-off release 403; manager release RELEASED with ref/by/at + idempotent same ref no dup + different ref 409 DUPLICATE_RELEASE; cancel RELEASED 409; mgr reverse 403 / DA reverse 200 REVERSED w/ reason/by; cancel REVERSED 409; operator override-granted release policy-off 403 CENTRE_MISMATCH / policy-on 200 RELEASED (booking3 → payment3 250.00); base-rate c2 payment 900.00 = 40×22.5; DA cancel PENDING 200 CANCELLED + release/reverse CANCELLED 409; farmer statement 2 items incl RELEASED+REVERSED + own show 200 with reversal_reason; cross-farmer 404 x2; operator list status/q + own-centre show 200 + other operator show 403; DA admin list w/ all district payments + admin show 200 + manager admin show c2 403; notification events payment_released/payment_reversed queued; also fixed `?? false &&` precedence + items-vs-plain-data list-envelope mismatches in the script so every check is strict)
- [x] Phase 17: Full-stack smoke test (scripts/smoke_test.php) 37/37 PASS — registration→OTP→2FA→login→centres/slots→multi-crop booking→queue call-next→procurement start/capture/submit→approval→payment release→notification read→RBAC 403→maintenance 503/bypass→password reset
- [x] Phase 17: Edge-case suite (scripts/edge_cases_test.php) 8 PASS/1 SKIP (release-concurrency verified in load test Phase 5); duplicates/full-slot/window/cancel-state/different-slot/empty-queue all 409-correct
- [x] Phase 17: Load test (scripts/load_test.php, 20 workers, ~4.4s) 6/6 phases PASS, 24 OK/0 Fail — Phase 2 concurrent booking capacity-3 slot: exactly 3 real OK (0 oversell) + 7 expected 409; Phase 3 call-next race: exactly one winner per 3 slots; Phase 5 cancel race: exactly one winner of 4; Phase 6 reconciliation clean (slot capacities, queue_entries→bookings linkage, no duplicate payments)
- [x] Phase 17: **Booking-number race defect fixed** — Booking::nextBookingSequence() previously used `SELECT COUNT(*)` (non-atomic under REPEATABLE READ → concurrent requests got identical sequence numbers → spurious 1062 on booking_number unique); rewritten to use new `booking_sequences` table with `INSERT ... ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq+1)`; new migration `database/migrations/20260912000054_add_booking_sequences_table.php` (batch 14) seeds per-day sequences from existing bookings so issued numbers are never reused
- [x] Phase 17: Load-test harness hardened — Phase 1 reset flushes rate_limit_logs + deletes test farmers' bookings across ALL slots (cascade payments/procurements/queue_entries via booking_id, recompute slot booked_count); complete-registration payload aligned with required profile fields (village/district_id/state/pincode); reconciliation queries rewritten against real schema (queue_entries/payments have no slot_id; bookings.slot_id; payments.procurement_id); expected-rejection semantics record 200-with-detail (assertions use okCount ≤ capacity); phase summary labels "Expected rej. (409/422)"
- [x] Phase 17: Security hardening — global headers in public/index.php (X-Content-Type-Options: nosniff, X-Frame-Options: SAMEORIGIN, Referrer-Policy: no-referrer, Permissions-Policy no geo/mic/camera); AuthController::webCsrf() now starts an HttpOnly/Lax (Secure in prod) strict-mode session cookie beside the js-readable csrf_token
- [x] Phase 17: Security scan (scripts/security_scan.php) 13/13 PASS — SQLi, XSS, CSRF, auth bypass (37 routes), role escalation (operator→maintenance/settings 403, farmer→staff/maintenance 403), IDOR, upload (.php blocked), rate limits (429), secret exposure, tokens-in-logs, session cookies, headers, maintenance bypass; probe fixes: operator default mobile → 9010000001 (9000000001 is a 2FA farmer), operator→maintenance probe method POST→PUT (route is PUT); full JSON in security_scan_results.json
- [x] Phase 17: Flutter farmer_app — `flutter analyze` 0 issues, `flutter test` 13/13 PASS (AuthProvider 2FA state machine 8, Booking model 3, login→2FA→OTP widget flow 2)
- [x] Phase 17: docs/TEST-REPORT.md written (smoke/edge/load/security/Flutter results + known limitations + artifacts)
- [x] Phase 18: Production route guard in config/routes.php — all `/test/` routes (`/api/v1/test/{echo,exception,audit,manage-slots,scoped-*}`, `/web/test/mutate`) removed at registration when `APP_ENV=production` (verified: 0 test routes in prod mode, 7 in non-prod); health endpoints unaffected
- [x] Phase 18: public/.htaccess hardened — http→https 301, deny `.env*`/`*.log`/config/app/database/storage/scripts in webroot, `Options -Indexes`, nosniff/SAMEORIGIN/Referrer-Policy/Permissions-Policy/CSP(+upgrade-insecure-requests) headers, HSTS commented until TLS verified, hidden-file + log-extension deny
- [x] Phase 18: ErrorHandler confirmed production-safe — uncaught Throwable → `{success:false,error:{code,message}}` (SERVER_ERROR, no stack trace), full details logged to storage/logs/application.log only (verified Phase 01 code)
- [x] Phase 18: deploy/ artifacts created — deploy/checklist.md (pre/post + rollback + human-only marks), deploy/sync.ps1 (stage → tar-over-ssh → migrate status/run → non-demo seed → perms tighten → storage writable → maintenance off → smoke; idempotent; dry-run; no destructive DB ops), deploy/.deployignore (dev-only exclusions), deploy/production.env.example (documented, no real keys)
- [x] Phase 18: .deployignore verified by staging test — app/console ships cron.php/migrate.php/seed.php only; scripts/, phase*/rbac_*/e2e/probe/reset/toggle/test scripts, .env*, *.log, *_context.json, smoke_run*.txt, .otp_log, sihproject.zip all excluded; storage logs/cache/temp recreated empty on host
- [x] Phase 18: Cron reality check — app/console/cron.php implements 6 jobs (generate-slots, expire-pending-bookings, expire-unarrived-bookings, queue-notify, send-pending-notifications, retry-notifications); docs/35-cron-jobs.md updated to document the 6 real jobs + flag the 6 never-implemented ones (backup/rotate-monitor/report/cleanup/archive/expiry) as post-launch
- [x] Phase 18: docs/OPS-RUNBOOK.md written (day-1 ops, env vars, maintenance, cron inventory, backup/retention, restore procedure, monitoring/alert paths, 8 incident runbooks, logs table, escalation) + docs/ONBOARDING.md written (farmer + staff guides with screenshot slots)

## Phase 18 Status (2026-09-11)

**Done (local, verifiable):**
1. Production config + guard (STEP A), `.htaccess` hardened (STEP A), local boot smoke re-passed.
2. Deploy artifacts (STEP B) incl. staging-verified ignore list & `sync.ps1` (dry-run tested, syntax-checked).
3. Migrate + non-demo seed documented with expected counts; idempotency verified (INSERT IGNORE / guard-clause upserts; `--demo`/`--reset` forbidden on prod) (STEP C).
4. Cron inventory + cPanel entries for the 6 REAL jobs; discrepancy documented (STEP D).
5. SSL/HTTPS/headers verification commands + HSTS comment guidance (STEP E).
6. Backup jobs (DB dump + file tar, retention, off-site) + test-restore procedure (STEP F).
7. Monitoring reality documented: `/health` live; alert paths = application.log + audit_logs(cron) + notification_logs; FLAG: docs/33 cron-check + monitoring_events/cron_runs tables are NOT implemented (STEP G).
8. Staff role matrix + farmer/staff onboarding guide (STEP H).
9. Flutter release APK build command with live-base + OneSignal live app (STEP I).
10. Final prod smoke + test-data-clean procedure (STEP J).

**BLOCKED — require [HUMAN] + real resources (domain/hosting/SSL/OneSignal LIVE/OTP LIVE/phones):**
- DNS + SSL install on the live domain; http→https 301 live check.
- Production `.env` creation on the host; migrate run + non-demo seed; row-count signs-off.
- cPanel cron creation for the 6 jobs + first-run verification.
- Live OTP SMS (register/2FA/reset/mobile_change) + OneSignal test-push on real phones.
- Backup → verified restore one (throwaway subdomain/local dir).
- Release APK on a real device hitting prod API + LIVE push.
- Production smoke (STEP J) + test-row cleanup.
- `docs/PROJECT-STATE.md` "Phase 18 complete" flip after the above are signed off.

## In-Progress Work
- Phase 18 live-host execution on **cPanel shared hosting, File Manager only (no SSH)**.
  The cPanel subdomain folder **itself** is the docroot: the whole app (app/, config/, storage/)
  plus web entry (index.php, .htaccess, portal/) deploy into **`testing.deepjyotimicrofinance.com/`**;
  the `.htaccess` deny-rules protect the app dirs that share the webroot (`index.php` uses a
  `__DIR__` fallback to find `app/` beside it). Deploy artifacts built locally
  (`deploy/fps_deploy_testing.zip`, `deploy/htaccess.production`, `.env.production`, `deploy/checklist.md`,
  `deploy/sync.sh`, `deploy/make_deploy_zip.ps1`) — execution tracked in `deploy/checklist.md` §§2–14.
- Remaining human actions (all [HUMAN]): subdomain+PHP version, DB create, PHP INI overrides, upload zip +
  move `public/*`→`public_html`, create host `.env` (LIVE keys), run migrate+non-demo seed (cron workaround
  or host support), add 6 cron jobs, issue SSL, run prod smoke with a throwaway phone, backups + first
  restore dry-run, optional release APK.

## Pending Work
- Phase 16: Staff/Admin Portal browser click-through verification (server-side behavior smoke-tested; needs a manual browser pass per role) + optional CENTRE_MANAGER/operator test account to verify role-based menu hiding
- Phase 18 (remaining, all [HUMAN]): as listed in §"Phase 18 Status" — execute against the live host per `deploy/checklist.md`, then flip Phase 18 → Complete
- Post-build runtime verification of Phases 14/15 against a real staging URL + OneSignal app id
- Post-launch follow-ups (out of Phase 18 scope, flagged): implement real `backup-database`/`backup-files`/`rotate-logs`/`monitor-health`/`cleanup-*`/`archive-*` cron jobs (docs/35 updated to 6 real jobs), cron_runs table + monitoring_events + cron-driven alert checks (docs/33), admin "monitoring" page

## Environment Matrix & Production URLs

| Env | APP_ENV | APP_DEBUG | Base URL | Key set | Purpose |
|-----|---------|-----------|----------|---------|---------|
| dev (local XAMPP/PHP built-in) | development | true | http://localhost:8000 | pilot placeholders | development |
| staging (subdomain) | staging | false | https://staging.<domain> | STAGING keys (separate OneSignal app id) | UAT |
| production | production | false | https://testing.deepjyotimicrofinance.com | LIVE keys in prod `.env` only (cPanel, `<USER>_fps_testing` DB) | live |

- Health: `GET /health` (DB+storage), `GET /health/maintenance` (public even in maintenance).
- Portal: `https://<domain>/portal/`.
- Secrets storage: prod `.env` on host (`chmod 600`, outside webroot) + password manager; `deploy/production.env.example` documents keys, never values.
- Cron inventory: 6 jobs, schedules + commands → `docs/35-cron-jobs.md` §"cPanel Cron Examples" + `deploy/checklist.md` §5.
- Backup schedule: DB nightly 02:00, files 02:30, retention 30d daily / 12 months monthly; off-site weekly [HUMAN].
- Monitoring channels: `/health`, application/api/security/notification/cron logs, audit_logs; alert drills per `docs/OPS-RUNBOOK.md` §8.
- Rollback procedure: `deploy/checklist.md` §12; restore: `docs/OPS-RUNBOOK.md` §7.
- Open risks: see "Open Decisions"/"Known Limitations"; + Phase 18 FLAGs (cron count, monitoring machinery missing).

## Config Keys Added (Phase 01)
- APP_NAME, APP_ENV, APP_DEBUG, APP_URL, APP_SECRET, APP_VERSION, APP_TIMEZONE
- DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_CHARSET
- JWT_SECRET, JWT_ACCESS_EXPIRY, JWT_REFRESH_EXPIRY
- SESSION_LIFETIME, REMEMBER_ME_EXPIRY
- ENCRYPTION_KEY
- RATE_LIMIT_LOGIN, RATE_LIMIT_OTP, RATE_LIMIT_BOOKING, RATE_LIMIT_SMS
- FILE_UPLOAD_MAX_SIZE
- NOTIFICATION_MAX_RETRIES, NOTIFICATION_RETRY_INTERVAL
- ONESIGNAL_APP_ID, ONESIGNAL_REST_API_KEY, OTP_API_KEY, OTP_SENDER_ID, OTP_TEMPLATE_ID

## Config Keys Added (Phase 03)
- RATE_LIMIT_API (general API rate limit, default 60/min, used by RateLimitMiddleware on api stack)
- CORS_ALLOWED_ORIGINS (comma-separated allowlist, default `*`; cors config block in config/config.php)

## Config Keys Added (Phase 04)
- JWT_ACCESS_EXPIRY (900s), JWT_REFRESH_EXPIRY (604800s) → config/config.php jwt block
- OTP_EXPIRY (300s), OTP_RESEND_COOLDOWN (60s), OTP_MAX_ATTEMPTS (5), OTP_MAX_RESENDS (3) → config/config.php otp block
- RATE_LIMIT_LOGIN (5/min), RATE_LIMIT_OTP (3/5min) wired into login/OTP rate limiting

## Config Keys Added (Phase 05)
- PERMISSION_NEGATIVE_PRIORITY (default `true`; a user_permissions row with `granted=0` beats the role-granted permission) → config/config.php rbac block
- SUPER_ADMIN_ROLE_ID (default `1`; role treated as global super admin regardless of users.is_super_admin flag)

## Config Keys Added (Phase 06)
- ENCRYPTION_KEY (must be exactly 64 hexadecimal chars = 256-bit AES-GCM master key; validated by SecretService; dev .env set + .env.example documented with `php -r "echo bin2hex(random_bytes(32));"` generator)

## Config Keys Added (Phase 07)
- DEFAULT_LOCALE (default `en`; used as final fallback in the locale detection chain → config/locales.php)
- ALLOWED_MIME_TYPES (comma-separated extension allowlist, default `jpg,png,webp,pdf,csv,xlsx` → config/files.php; `.env` already has FILE_UPLOAD_MAX_SIZE=5242880)

## Settings Registry (Phase 06)
- config/settings_defaults.php registers all 29 settings with value_type (STRING/INT/FLOAT/BOOL/JSON), is_public, is_sensitive, group (GENERAL/MAINTENANCE/NOTIFICATION/BOOKING/SECURITY/FILE/RATE_LIMIT), and description. System settings (system_settings) merge over defaults; maintenance_* keys read fresh from DB each request.

## Middleware Stacks (Phase 05)
| Stack | Order | Routes |
|-------|-------|--------|
| api | Maintenance → CORS → Auth (real JWT) → Locale → RateLimit | /api/v1/* |
| web | Maintenance → CORS → Auth (real session) → Locale → Csrf | /web/* |
| health | none | /health, /health/maintenance |
| admin (route-level, appended after api stack) | RoleMiddleware:30 → PermissionMiddleware:manage_staff → ScopeMiddleware | /api/v1/admin/* (role→permission→scope chain defined in config/middleware.php `route_defaults`) |
| phase06 admin settings/secrets/maintenance (route-level) | PermissionMiddleware:settings.view|settings.manage|settings.secret_manage|maintenance.manage | /api/v1/admin/settings, /api/v1/admin/secrets, /api/v1/admin/maintenance |
| phase07 files + admin translations (route-level) | PermissionMiddleware:translations.view / translations.manage / files.upload / files.download / files.delete / files.folders / files.manage_all | /api/v1/files/*, /api/v1/files/folders, /api/v1/admin/files*, /api/v1/admin/languages/*/translations, /api/v1/admin/translations* |
| phase08 centres + staff (route-level) | RoleMiddleware:70 + PermissionMiddleware:centres.manage / centres.status.manage / centres.view; PermissionMiddleware:staff.view / staff.manage + RoleMiddleware:70; ScopeMiddleware staff_centre | /api/v1/admin/centres*, /api/v1/admin/staff*, GET /api/v1/centres, GET /api/v1/districts |
| phase09 slots (route-level) | PermissionMiddleware:slots.view / slots.manage / slots.cancel + ScopeMiddleware (scoped); GET /api/v1/slots public (no guard) | GET /api/v1/slots, /api/v1/admin/slots*, POST /api/v1/admin/slots/generate |
| phase10 bookings + tokens + crops (route-level) | PermissionMiddleware:bookings.create / bookings.view_own / tokens.view_own / bookings.view_any + ScopeMiddleware (scoped) / bookings.cancel_any + ScopeMiddleware (scoped); GET /api/v1/crops public (no guard) | POST /api/v1/bookings, GET /api/v1/bookings, GET /api/v1/bookings/{id}, GET /api/v1/bookings/{id}/crops, POST /api/v1/bookings/{id}/cancel, GET /api/v1/my/token, /api/v1/admin/bookings*, GET /api/v1/crops |
| phase12 procurement + approval (route-level) | PermissionMiddleware:procurements.create + ScopeMiddleware:queue_entry (start); procurements.update + :procurement (capture/submit); procurements.reject + :procurement (reject); procurements.view_any list permission-only + :procurement show; procurements.approve list/show/approve/reject (approvals_show/approvals_manage + :procurement); procurements.view_own (farmer, service-side ownership) | POST /api/v1/operator/queue/{entryId}/start, PUT/POST /api/v1/operator/procurements/{id}[/(submit|reject)], GET /api/v1/operator/procurements{/{id}}, /api/v1/admin/approvals{/{id}}[/(approve|reject)], GET /api/v1/admin/procurements/{id}, GET /api/v1/my/procurements{/{id}} |
| phase13 payments + rates (route-level) | PermissionMiddleware:payments.view / payments.show(+ScopeMiddleware:payment) / payments.release(+scope) / payments.cancel(+scope) / payments.reverse(+scope); rates_manage = RoleMiddleware:70 + PermissionMiddleware:rates.manage; rates_view = PermissionMiddleware:crops.view (all roles); payments_view_own (farmer, service-side ownership) | GET /api/v1/crops/{cropId}/rates, GET/POST /api/v1/admin/crop-rates, PUT /api/v1/admin/crop-rates/{id}, GET /api/v1/operator/payments, GET /api/v1/operator/payments/{id}, PUT /api/v1/operator/payments/{id}/release, GET /api/v1/admin/payments, GET /api/v1/admin/payments/{id}, POST /api/v1/admin/payments/{id}/cancel|reverse, GET /api/v1/my/payments{/{id}} |

## API Implementation Status
- **Health Endpoints Live**: GET /health, GET /health/maintenance
- **Version**: v1 (Design complete)
- **Endpoints Defined**: ~85 endpoints
- **Authentication**: JWT (Flutter) + Session (Web) — implemented, live
- **Rate Limiting**: Implemented (base api stack 60/min + dedicated login/OTP/booking limits wire in Phase 04)
- **Documentation**: 07-api-reference.md complete

## Known Bugs
- None

## Known Limitations
- Documentation assumes shared hosting environment
- No CI/CD pipeline (manual deployment)
- No Git version control workflow
- **OneSignal push + in-app; OTP via SMS gateway (keys hardcoded for now)**
- AJAX polling for live queue (no WebSockets)
- Dev OTP is logged to storage/logs/notification.log instead of SMS (ONESIGNAL/OTP keys placeholder)
- Dev database contains Phase 04 test users: farmer `9368492209` (password `NewPass1x`), staff `admin6318` (password changed to `NewAdminPass1`)
- Phase 16 portal runtime verified via PHP built-in server + static checks; manual browser click-through per role pending before final sign-off

## Open Decisions
- [ ] Exact booking cancellation window (configurable, default 2 hours)
- [ ] Queue notification threshold (configurable, default 3 farmers ahead)
- [ ] Default slot capacity per centre (configurable)
- [ ] Working hours/days per centre (configurable per centre)
- [ ] Maximum retry attempts for push/SMS (configurable, default 3)
- [ ] Session timeout duration (configurable, default 30 min web, 7 days app)
- [ ] Remember-me token expiry (configurable, default 30 days)
- [ ] File upload size limits (configurable, default 5MB)
- [ ] Rate limit thresholds (configurable — RATE_LIMIT_API default wired)

## Architectural Decisions
| Decision | Rationale | Documented In |
|----------|-----------|---------------|
| PHP 8.x + MySQL on shared hosting | SIH constraints, team familiarity | 02-system-architecture.md |
| Flutter for farmer app | Android-first, offline capability | 05-flutter-architecture.md |
| Vanilla JS for admin portal | No build step, shared hosting friendly | 03-application-architecture.md |
| REST API with JSON | Simple, standard, Flutter-friendly | 06-api-architecture.md |
| Modular MVC-inspired backend | Maintainable, testable, no over-engineering | 04-backend-architecture.md |
| JWT for Flutter, Sessions for Web | Platform-appropriate auth | 11-authentication.md |
| Separate remember-me tokens | Security best practice | 12-session-management.md |
| Device/session tracking | Multi-device support, audit | 12-session-management.md |
| RBAC with resource scoping | Role hierarchy + data isolation | 13-roles-and-permissions.md |
| OneSignal push + in-app; OTP via SMS gateway (keys hardcoded initially) | SIH scope, push + OTP separation, future-proof secrets migration | 22-notification-system.md |
| AJAX polling for queue | Shared hosting compatible | 17-queue-workflow.md |
| Encrypted secrets in DB | Shared hosting constraint | 26-secret-management.md |
| Soft deletes for audit | Data integrity, compliance | 15-business-rules.md |
| English + Hindi default | SIH requirement | 23-language-system.md |
| File references not paths | Move/rename safety | 24-file-manager.md |
| DB-backed rate limiter (rate_limit_logs) | Shared-host friendly, no Redis, simple upsert | 29-rate-limiting.md |

## Database Version/State
- **Database Version**: 14 (batch 14 = `20260912000054_add_booking_sequences_table.php`)
- **Status**: Schema implemented, 38 tables live
- **Migration Tool**: Custom PHP migration runner (app/console/migrate.php)
- **Tables**: users, roles, permissions, role_permissions, user_permissions, user_sessions, remember_tokens, user_devices, login_history, farmers, districts, procurement_centres, centre_staff, slots, bookings, booking_crops, tokens, queue_entries, procurements, payments, crop_rates, notifications, notification_logs, languages, translations, files, file_folders, file_references, system_settings, system_secrets, audit_logs, error_logs, support_requests, otp_verifications, migrations, rate_limit_logs, crops, booking_sequences
- **Seed data**: 5 roles, 72 permissions, 219 role_permissions, 2 languages (en/hi), 181 translations, 48 system_settings, 6 system_secrets (placeholders, is_set=0), 5 districts (Maharashtra), 16 crops, 16 crop base rates
- **Phase 03 additions**: rate_limit_logs migration (batch 3); no new seed data
- **Phase 04 additions**: no schema changes (OTP/session tables already existed); dev test users created via API
- **Phase 05 additions**: no schema changes (user_permissions already seeded empty); Phase 05 test users/centres created via app/console/rbac_test_seed.php then removed post-verification (rbac_test_context.json deleted; DB state restored)
- **Phase 06 additions**: no schema changes; permissions seeded 28→32 (settings.view/settings.manage/settings.secret_manage/maintenance.manage); role_permissions 72→77 (SA +4, DA +1 settings.view); test users reset post-verification via phase06_e2e_reset.php (maintenance off, secrets is_set=0, settings restored); ENCRYPTION_KEY set to valid 64-hex
- **Phase 07 additions**: migration batch 4 (scope ENUM on files/file_folders); permissions 32→39 (+7: translations.view/translations.manage/files.upload/files.download/files.delete/files.folders/files.manage_all); role_permissions 77→109; translations seeded 181 (hi omits internal.admin.tooltip); test users/centre/files reset post-verification via phase07_e2e_reset.php (DB restored to 181 translations)
- **Phase 08 additions**: migration batch 5 (CLOSED on procurement_centres.status); permissions 39→45 (+6: centres.view/centres.manage/centres.status.manage/staff.view/staff.manage/districts.manage); role_permissions 109→125; Phase 08 E2E test data (P08 baseline centres + staff users) created per run with unique suffixes and remains for reference in storage/phase08_e2e_context.json
- **Phase 09 additions**: migration batch 6 (CANCELLED on slots.status + bookings slot_id/status composite index); permissions 45→48 (+3: slots.view/slots.manage/slots.cancel); role_permissions 125→136; settings 29→34 (+5: slot.horizon_days/slot.default_duration_minutes/slot.default_capacity/slot.min_capacity/slot.breaks); slots generated for all 18 ACTIVE centres via cron (1728 slots across 7-day horizon, weekends skipped) remain in DB as generated inventory
- **Phase 10 additions**: migration batch 7 (crops table + id UNIQUE crops.id; booking_crops +crop_id FK + status 'PENDING'/'CANCELLED' default PENDING; tokens +unique token_hash VARCHAR(64) + 'CANCELLED' status; bookings.status default → 'PENDING'); permissions 48→53 (+7: bookings.create/bookings.view_own/bookings.view_any/bookings.cancel_any/tokens.view_own/tokens.view_any/crops.view); role_permissions 136→149; settings 34→39 (+5: booking.horizon_days/booking.min_lead_hours/booking.cancel_lead_minutes/booking.max_active/auto_confirm_on_payment); +16 crops seeded via crop_seeder.php; Phase 10 E2E baseline (per-run unique centre + farmers) keeps generated booking/token rows per run as reference
- **Phase 11 additions**: migration batch 8 (queue_entries.status ENUM +'NO_SHOW', recall_eligible TINYINT(1) DEFAULT 0, recall_count INT UNSIGNED DEFAULT 0, recalled_at, no_show_at, no_show_by, INDEX idx_queue_centre_date_status(centre_id,date,status); notification_logs +event_ref VARCHAR(64) NULL + UNIQUE uq_nlog_event_ref); permissions 53→61 (+6: queue.view/queue.view_own/queue.call_next/queue.skip/queue.no_show/queue.stats); role_permissions 149→181; settings 39→44 (+5: queue.avg_minutes_per_token/queue.grace_no_show_minutes/queue.notify_threshold/queue.call_batch/queue.recall_limit); Phase 11 E2E baseline (per-run unique centres P11A*/P11B* + farmer/operator users) keeps generated bookings/tokens/queue_entries/notification_logs rows per run as reference
- **Phase 12 additions**: migration batch 9 (20260910000045_add_approval_columns_to_procurements.php); permissions 61→67 (+6: procurements.create/update/reject/approve/view_any/view_own); role_permissions 181→205 (SUPER_ADMIN +6, DISTRICT_ADMIN +6, CENTRE_MANAGER +6, CENTRE_OPERATOR +5, FARMER +1); settings 44→47 (+3: procurement.approval_required/max_photos/weight_precision); Phase 12 E2E baseline (per-run unique centres P12A*/P12B* + farmer/operator/manager users) keeps generated bookings/tokens/queue_entries/procurements/audit rows per run as reference
- **Phase 13 additions**: migration batches 10-11 (20260911000046_add_payment_enums_and_crop_rates.php + 20260912000047_add_payment_release_columns.php); permissions 67→72 (+5: payments.view/payments.release/payments.cancel/payments.reverse/rates.manage); role_permissions 205→219 (SUPER_ADMIN +5, DISTRICT_ADMIN +5, CENTRE_MANAGER +4, CENTRE_OPERATOR +1); settings 47→48 (+1: payment.allow_operator_release=0); +16 crop base rates seeded (crop_rate_seeder.php); Phase 13 E2E baseline (per-run unique centres P13A*/P13B* + farmer/operator/manager/district-admin users) keeps generated bookings/tokens/queue_entries/procurements/payments/crop_rates/notification_logs/audit rows per run as reference

## Session Note (2026-09-13) — login fix, admin→farmer create, Flutter APK
- **Root-cause of "login button not working"**: on the host no user accounts existed — the base (non-demo) seed creates no users; `demo` accounts only appear after `run-demo.php`. Local JSON-POST debugging also found a *test-harness* quirk (PowerShell stripped `"` from curl `--data` JSON, yielding invalid JSON → body empty → VALIDATION_ERROR). Actual `/web/login` + `/api/v1/auth/login` verified working locally (200 with user/permissions/token). No backend change needed.
- **Default super admin (testing host)**: `demo_super_admin` / `Admin@1234` via `run-demo.php`; password is changeable (portal top-right name menu → **Change Password**, uses `POST /web/password/change`). NEVER run demo on production.
- **NEW — Admin can create farmers with a password**:
  - Routes: `GET /POST /api/v1/admin/farmers`, `GET /api/v1/admin/farmers/{id}`, `PUT .../password`, `PUT .../status` (perms `view_farmers`/`manage_farmers`).
  - New: `app/Controllers/Admin/FarmerController.php`, `app/Services/FarmerService.php`, `app/Validators/FarmerValidator.php`, middleware route defaults `farmers_view`/`farmers_manage` in `config/middleware.php`.
  - Farmer created as ACTIVE/APPROVED with `password_hash`, `mobile_verified_at`, `created_by`; response returns `credentials` (shown once in portal). Farmer logs into the mobile app with mobile + password. Reset-password revokes sessions.
  - Verified locally: create → 201, farmer password login → 200, reset → 200, status → 200.
  - Portal: new **Farmers** page (`public/portal/js/pages/farmers.js`) — list/search/CSV, `+ Farmer` modal (name, mobile, village, district, password with Generate/Show), farmer detail, Password reset, Status change. Router nav + icon + `index.html` `<script>` added.
- **Flutter app `useitnow.apk`**: default API base changed in `farmer_app/lib/config/env.dart` + `lib/core/constants/api_constants.dart` → `https://testing.deepjyotimicrofinance.com`. Release APK built (`flutter analyze` clean) and copied to `D:\sihproject\useitnow.apk` (52.5 MB).
- **Deploy artifacts rebuilt**: `deploy/fps_deploy_testing.zip` (0.57 MB, 368 entries, leak-scan clean) via `deploy/make_deploy_zip.ps1`; `run-demo.php` + `deploy/checklist.md` §7.1 + README-DEPLOY updated with demo credentials, change-password, and Farmers-page instructions.

## Implementation Readiness
- ✅ Documentation complete
- ✅ Architecture defined
- ✅ Database schema designed
- ✅ API contracts defined
- ✅ Phase plans detailed
- ✅ Phase 01 (System Architecture & Foundation) implemented
- ✅ Phase 02 (Database Schema & Migrations) implemented
- ✅ Phase 03 (PHP Backend Foundation) implemented
- ✅ Phase 04 (Authentication + Sessions + Tokens) implemented
- ✅ Phase 05 (RBAC + Permission Manager) implemented
- ✅ Phase 06 (System Settings + Secrets + Maintenance) implemented
- ✅ Phase 07 (Language System + File Manager) implemented
- ✅ Phase 08 (Centre + Staff Management) implemented
- ✅ Phase 09 (Slots + Capacity Management) implemented
- ✅ Phase 10 (Bookings + Tokens) implemented
- ✅ Phase 11 (Queue Management) implemented
- ✅ Phase 12 (Procurement + Approval/Reversal) implemented
- ✅ Phase 13 (Payments) implemented
- ✅ Phase 14 (Notification System: OneSignal push + OTP gateway + 2FA) implemented (runtime delivery pending keys)
- ✅ Phase 15 (Flutter Farmer App) implemented (tests + release APK; runtime E2E vs staging pending)
- ✅ Phase 16 (Staff/Admin Portal) implemented (code + server smoke tests; browser click-through pending)
- ✅ Phase 17 (Integration + Testing + Security) implemented (smoke 37/37, edge 8/9, load 6/6 phases, security 13/13, Flutter analyze clean + tests pass; defects fixed)
- ✅ Phase 18 (Deployment + Production Readiness) — prep/deliverables DONE (config guards, .htaccess, deploy/ artifacts + docs, cron reality-check, runbooks); live-host execution pending (tracked in deploy/checklist.md)

## Next Action
Phase 18 live-host execution per `deploy/checklist.md` (DNS, SSL, prod `.env`, migrate + non-demo seed, cron entries, live OTP/OneSignal on real phones, backup restore dry-run, monitoring alert drills, release APK on device, production smoke + test-row cleanup), then Portal browser click-through (Phase 16) and staging E2E for Phases 14/15 once a staging URL + OneSignal keys exist.