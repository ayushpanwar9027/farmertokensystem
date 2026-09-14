# Phase 07 — Language System + File Manager

## 1. Objective

Implement multi-language support (en/hi) with translations + fallback, and the file manager (upload, folders, references, size limits).

## 2. Prerequisites

- Phase 03-05 (validators, RBAC, services)
- Phase 06 (settings for locale defaults)

## 3. Features

### Language System ([23-language-system.md](23-language-system.md))
- Translation loading per locale with fallback to `en`
- Locale detection from `Accept-Language` + `X-Locale` header + user preference
- Translation CRUD (super_admin / language managers)
- Missing key logging (throttled) + fallback to en + key string
- UI strings for portal + flutter keys

### File Manager ([24-file-manager.md](24-file-manager.md))
- Upload endpoint: validate mime (allowlist), size (setting), extension, filename sanitization
- Store under private storage (outside web root) or authorized-delivery path
- Folders (file_folders) with scope (system/user/centre)
- file_references: track usage by entity
- Download endpoints enforce permission + scope
- Avatar/photo crop/resize optional (gd)

## 4. Files to Create

```
app/Services/LocalizationService.php
app/Services/TranslationService.php
app/Services/FileService.php
app/Models/Language.php
app/Models/Translation.php
app/Models/File.php
app/Models/FileFolder.php
app/Models/FileReference.php
app/Controllers/TranslationsController.php
app/Controllers/FileController.php
app/Controllers/Admin/FileManagerController.php
app/Middleware/LocaleMiddleware.php
config/locales.php
resources/strings/en.php
resources/strings/hi.php
```

## 5. Files to Modify

- `config/middleware.php` (LocaleMiddleware)
- `.env.example` (DEFAULT_LOCALE)

## 6. Database Changes

- Uses languages, translations, files, file_folders, file_references (Phase 02)

## 7. API Changes

- `GET /translations/{locale}` (public, cached)
- `GET /admin/translations` /  `PUT /admin/languages/{code}/translations` (bulk upsert)
- Translations CRUD/import-export (JSON)
- `POST /files/upload` (scope: user/centre/system)
- `GET /files/{id}` (serve/download)
- `DELETE /files/{id}` (if unreferenced; admin force with audit)
- `POST /files/folders`
- `GET /files?folder=&q=&page=`

## 8. Backend Logic

- LocalizationService: locale chain `[preferred, en, fallback]`
- Missing key → log throttled + return `key` for tech / `en` for fallback
- FileService: store to storage/app, record metadata, prevent traversal (`realpath` inside storage), size check per settings, uuid filenames
- Download: authorize via scope/purpose, stream with correct headers, no-force if inline image
- Reference counting protects deletes

## 9. Flutter Changes

- None (Phase 15): app consumes `GET /translations/hi` at startup

## 10. Staff/Admin Changes

- None (Phase 16): language switcher uses endpoint

## 11. Permissions

- `translations.view`, `translations.manage`
- `files.upload`, `files.download`, `files.delete`, `folders.manage`
- Scoped: user files own; centre files centre-scope; system files super_admin

## 12. Validation

- Upload: allowlist mime; max size (setting default 5MB); extension lowercased
- Folder names sanitized (no path separators)
- Translation keys: `[a-z0-9_.\-\/]`

## 13. Error Handling

- INVALID_MIME, FILE_TOO_LARGE, FILE_NOT_FOUND, STORAGE_ERROR
- Translation import malformed → 400 with line number
- Missing key logged (throttle 1/min/process)

## 14. Security

- Filenames → uuid + sanitized extension (never user input in path)
- Traversal blocked; realpath containment
- Mime sniffing (finfo) consulted, not just header
- Executable/script files always rejected (php/sh/htaccess...)
- Downloads force-revalidated per permission each request
- EXIF stripping on images optional (gd)

## 15. Logging/Audit

- audit: upload/delete/folder create/translation changes
- api.log: downloads (sanitized)

## 16. Notifications

- None (files/locale)

## 17. Configuration Changes

- DEFAULT_LOCALE, FILE_UPLOAD_MAX_SIZE, ALLOWED_MIME_TYPES

## 18. Dependencies

- openssl (uuid), fileinfo (finfo)

## 19. Completion Criteria

- [ ] `GET /translations/hi` returns hi pack with en fallback for missing
- [ ] Locale autodetection from header `X-Locale`/Accept-Language
- [ ] Upload valid file → metadata + stored blob
- [ ] Reject bad mime/oversized/executable
- [ ] Download authorized only
- [ ] Unreferenced delete allowed; referenced blocked
- [ ] Translation CRUD works, cache invalidated

## 20. Testing Checklist

- [ ] Request hi → hi strings; missing key → en string
- [ ] `X-Locale: hi` overrides
- [ ] Upload jpg 1MB → ok
- [ ] Upload .php → 400 INVALID_MIME
- [ ] Upload 6MB (limit 5MB) → FILE_TOO_LARGE
- [ ] `../` filename attempt → sanitized/blocked
- [ ] Delete referenced file → blocked; unreferenced → ok
- [ ] Translation update → next request reflects (cache)

## 21. What NOT to Implement

- No booking/queue/procurement/payments
- No notification sending
- No farmer profile file-category specific rules beyond scope

---

**Depends on**: Phase 03-06
**Feeds into**: Phase 08+