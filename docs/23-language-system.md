# Language & Translation System

## Overview

Multi-language support with English as default. Initial: English + Hindi. Extensible via Super Admin.

## Architecture

```
TranslationResolver
      │
      ├─ Resolve key for requested language
      ├─ If missing → fallback to English
      └─ If missing in English → show key (with fallback marker) or default label

DB: languages + translations tables
Flutter: bundled JSON (en, hi) + API fallback
Web: JS i18n with API-loaded translations
```

## Language Data Model

### languages
```sql
CREATE TABLE languages (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) UNIQUE NOT NULL,   -- en, hi
    name VARCHAR(50) NOT NULL,          -- English, Hindi
    native_name VARCHAR(50) NULL,       -- English, हिन्दी
    is_default TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    created_at, updated_at, deleted_at
);
```

### translations
```sql
CREATE TABLE translations (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    language_id INT UNSIGNED NOT NULL,
    translation_key VARCHAR(190) NOT NULL,   -- auth.login_title
    translated_value TEXT NOT NULL,
    updated_by INT NULL,
    created_at, updated_at,
    UNIQUE KEY (language_id, translation_key)
);
```

## Translation Keys

Dot-notation keys grouped by module:

```
common.loading
common.error
common.retry
auth.login
auth.login_title
auth.mobile
home.welcome              → "Welcome, {name}"
bookings.confirm
queue.title
queue.position
procurement.status
payment.paid
... etc
```

### Placeholders
Use `{name}` style placeholders:
```php
// "Welcome, {name}"
$message = str_replace('{name}', $farmer['name'], $translation);
```

## Fallback Logic

```
Requested language = hi
      │
      ▼
Look up key "home.welcome" in hi
   ├── found → use hi value
   └── missing → fallback
              │
              ▼
             Look up "home.welcome" in English (default)
               ├── found → use English value
               └── missing → return key or safe default (no blank)
```

**Never show blank labels.** If key truly missing in both → show the key itself (developer-facing) or a configurable placeholder.

## Super Admin Language Management

### Features
- Add language (code, name, native_name)
- Enable/disable language
- Set default language
- Edit translations (bulk + individual)
- Delete language safely (no active users / not default)

### API
| Method | Path | Purpose |
|--------|------|---------|
| GET | /languages | Public (for selection) |
| GET | /admin/languages | Admin list |
| POST | /admin/languages | Add |
| PUT | /admin/languages/{id} | Update |
| DELETE | /admin/languages/{id} | Delete (safe) |
| GET | /admin/languages/{id}/translations | List (paginated) |
| PUT | /admin/languages/{id}/translations | Bulk update |

All admin-language actions require `manage_languages` permission + audit.

## Farmer App Localization

### Bundled translations
- `assets/translations/en.json`, `hi.json`
- Loaded via AppLocalizations + LocaleProvider
- Language selection at first launch (Language Selection screen)
- Stored in SharedPreferences
- Default: en (or system locale if supported)

### Dynamic fallback
- UI uses `AppLocalizations.of(context)` which falls back to English bundled file
- Server messages returned localized via `Accept-Language` header
- If a key is missing in hi.json, English used (Dart fallback)

## Web Portal Localization

- `i18n.js` loads translations from API per staff's language (or default)
- Language toggle in portal header (if multi-language needed)
- Default English for staff portal; Hindi available

## Staff/Admin Portal

- Language selector (Super Admin can manage languages)
- Translations editable in admin UI
- Default English

## Never Show Blank

If a key is missing:
- Backend API: return key string as fallback (or English)
- Flutter: English value
- Web: English value
- UI never empties

## Adding a Language — Workflow

1. Super Admin creates language (POST /admin/languages)
2. System copies all English keys as empty (or same) placeholders
3. Super Admin edits translations for the new language
4. Enable language
5. Optionally set as default

## Audit

- Language add/update/delete → audit
- Translation edits → audit
- Default language change → audit

---

**Next**: [24-file-manager.md](24-file-manager.md) for the file management system.