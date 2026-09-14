# Phase 16 — Staff / Admin Portal (HTML/CSS/JS)

## 1. Objective

Build the single staff/admin web portal (HTML5/CSS3/Vanilla JS; no framework) with two login paths (web session-based, CSRF-protected) and role-based dashboards: operator, centre manager, district admin, super admin.

## 2. Prerequisites

- Backend phases 03-14 (APIs live + CORS + CSRF configured)
- Static hosting (public_html/portal)

## 3. Features

- Login page (mobile/password) → session cookie + CSRF; **2FA step if enabled (verify-2fa form)**
- Shell with sidebar: dashboard, queue (operator), centres, slots, staff, bookings, procurement, payments, approvals, rates, notifications, settings/secrets(admin), files, translation manager, maintenance, audit
- Role hides routes (via permission map fetched from `/auth/me`)
- Operator view: live queue panel (poll 5s), call-next button, skip/no-show, start procurement, weight/QC form per crop, release payment
- Manager/District view: bookings, slots mgmt, approvals, rates, staff (district scope), reports summary
- Super admin: all + settings (whitelisted), secrets (masked), maintenance, languages/translations, files, audit, monitoring
- Localization switcher (en/hi) stored in cookie/preference
- Bootstrap-like custom CSS (or minimal external allowed — keep vanilla CSS own)
- Modals for forms; confirm dialogs; toasts
- Table pagination/filters consistent with API
- 401 → redirect login; 503 → maintenance page

## 4. Files to Create (portal folder in public_html)

```
portal/index.html (shell + router)
portal/login.html (or route in index)
portal/css/app.css
portal/js/{api.js,auth.js,router.js,components.js,utils.js,locale.js}
portal/js/pages/{dashboard,queue,operator_procurement,centres,slots,staff,bookings,procurements,payments,approvals,rates,notifications,settings,secrets,translations,files,maintenance,audit,reports}.js
portal/assets/ (fonts, logos)
```

## 5. Files to Modify

- Backend: `public/` serve portal statics; ensure `.htaccess` allows portal folder; CSRF token endpoint (already Phase 03/04)

## 6. Database Changes

- None (consumes APIs)

## 7. API Changes

- Consumes `/staff/auth/*`, `/operator/*`, `/admin/*` (existing). May need small additions:
  - `GET /auth/me` returns `portal_permissions` array (already), `languages_available`
  - `GET /staff/auth/csrf` (token)

## 8. Backend Logic

- JS client with token auth (session cookie; CSRF header on mutations from cookie token)
- Router guards by permission key
- Polling util with backoff; single global 401 handler

## 9. Flutter Changes

- None

## 10. Staff/Admin Changes

- This whole phase

## 11. Permissions

- Every route/page requires a permission key from the matrix; menu hides non-permitted
- Server ALSO enforces (portal is convenience, not security boundary)

## 12. Validation

- Client mirrors backend validation messages
- Confirm dialogs for destructive ops (cancel booking, reject, call-next is intentional disruption)
- All mutations include CSRF header

## 13. Error Handling

- Global fetch wrapper → error toast + field errors
- 401 → login redirect (preserve intended URL)
- 429 → cooldown
- 503 → maintenance overlay
- Empty states + skeletons

## 14. Security

- No tokens in localStorage (cookie SessionID only; CSRF cookie non-readable JS)
- XSS-safe rendering: escape all interpolated strings (strict), no innerHTML with user data
- CSP meta in main HTML; https enforced
- Logout clears cookies

## 15. Logging/Audit

- Client console errors in dev only
- Server audit (Phase 4-13) captures everything meaningful; portal displays audit page from `/admin/audit`

## 16. Notifications

- In-portal inbox (super admin alerts) + toast on events (queue), relies on Phase 14 inbox endpoints
- Admin notifications page: list + filters + **test-push button (`POST /admin/notifications/test-push`)**, daily summary

## 17. Configuration Changes

- Portal `config.js`: apiBase, default_locale, polling intervals

## 18. Dependencies

- None (no framework/bundler). Optional `showdown`-free; no packages. Fonts: system + Noto (served locally).

## 19. Completion Criteria

- [ ] Login (web session) + CSRF works
- [ ] **2FA user login shows verify-2fa step; wrong OTP error; disabled user direct**
- [ ] Operation queue screen: live list, call-next, skip/no-show, start procurement, weight capture, payment release
- [ ] Manager/district views (bookings, approvals, rates) functional
- [ ] Super admin views (settings, secrets, maintenance, translations, files, audit) functional
- [ ] **Admin notifications: filters + test-push delivered**
- [ ] Role menu hiding + server-scope double enforcement verified
- [ ] Localization switcher works
- [ ] Mobile-ish responsive enough for operator tablet/PC

## 20. Testing Checklist

- [ ] Logout → revokes session, cannot reuse
- [ ] Operator without procure.permission sees no approval menu (server also 403)
- [ ] Queue call-next → confirms; page updates; audit logged
- [ ] Weight capture form → 400 shows field errors
- [ ] Open portal in another tab while logged out → redirects login
- [ ] XSS attempt in name/notes → rendered escaped
- [ ] CSRF missing on mutation → 400/403

## 21. What NOT to Implement

- No React/Angular/Vue (vanilla JS)
- No WebSockets; polling only
- No drag-drop/complex UI
- No offline portal
- No admin-in-Flutter

---

**Depends on**: Backend 03-14
**Feeds into**: Phase 17-18