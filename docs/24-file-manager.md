# File Manager

## Overview

Dedicated Super Admin file manager for images and documents. Uses stable file references (files table), not raw paths, so moves/renames don't break references.

## Features

- Upload (local files)
- Image upload
- Add image by URL
- Create folder
- Rename
- Move
- Copy
- Paste
- Search
- Preview
- Delete where safe

## Data Model

### files
```sql
CREATE TABLE files (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_name VARCHAR(190) NOT NULL,
    source_type ENUM('LOCAL','URL','EXTERNAL') DEFAULT 'LOCAL',
    path_or_url VARCHAR(500) NOT NULL,
    folder_id INT UNSIGNED NULL,           -- belongs to folder
    mime_type VARCHAR(100) NULL,
    size INT UNSIGNED DEFAULT 0,           -- bytes
    extension VARCHAR(20) NULL,
    checksum VARCHAR(64) NULL,
    created_by INT NULL,
    status ENUM('ACTIVE','INACTIVE','DELETED') DEFAULT 'ACTIVE',
    created_at, updated_at, deleted_at
);
```

### file_folders
```sql
CREATE TABLE file_folders (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    parent_id INT UNSIGNED NULL,           -- self-referencing hierarchy
    created_by INT NULL,
    created_at, updated_at, deleted_at
);
```

### file_references (where a file is used)
```sql
CREATE TABLE file_references (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    file_id INT UNSIGNED NOT NULL,
    source_type VARCHAR(50) NOT NULL,      -- centre_logo, user_avatar, etc.
    source_id INT UNSIGNED NULL,           -- related entity id
    created_at,
    UNIQUE KEY (file_id, source_type, source_id)
);
```

## Stable References (Not Raw Paths)

- System stores `file_id` references in entities (e.g., `centre.logo_file_id`)
- `files.id` → `files.path_or_url` is resolved at serve time
- Never store raw filesystem paths in entity columns
- This enables safe move/rename + future object-storage migration

### Resolution
```php
// Resolve file reference to a full URL/path
function resolveFile(int $fileId): string {
    $file = File::find($fileId);
    return $file['source_type'] === 'URL'
        ? $file['path_or_url']                          // external URL
        : '/files/' . $file['id'] . '/download';        // local via PHP
}
```

## Move / Rename with Reference Preservation

```
1. Move/Rename a file (or folder)
      ↓
2. Detect references (file_references for the file; for folders, all files within)
      ↓
3. Ask "Fix Locations?" (UI confirmation)
      ├── Yes → update references (path resolution changes, file_id intact)
      └── No  → keep references (may break direct path, but file_id still valid)
      ↓
4. Confirm
      ↓
5. Update references in DB
      ↓
6. Validate (no broken references; re-scan)
```

Since we use `file_id` (not path) in entities, moves only require updating the stored `path_or_url`; references via file_id remain valid. The "Fix Locations?" prompt mainly matters if any raw paths were stored anywhere or for cache/URL regeneration.

## No Broken Images

- Internal file movement updates `files.path_or_url`; references via file_id stay valid
- Fallback image used if a referenced file is missing/deleted
- If an external URL fails to load → fallback image rendered

## Fallback Image

```php
function imageUrl(int $fileId = null): string {
    if ($fileId && $file = File::findActive($fileId)) {
        return resolveFile($fileId);
    }
    return '/assets/images/placeholder.png';   // fallback
}
```

## Upload Security

- Validate MIME type against whitelist (configurable `allowed_file_types`)
- Validate size ≤ `file_upload_max_size_mb`
- Generate random storage filename (never trust user filename for storage)
- Store original name in `file_name`, safe random name in storage
- Reject executable/script types (php, sh, exe, etc.)
- Serve via PHP with `Content-Disposition` for download, or with correct mime headers for preview
- For local stored files, store OUTSIDE web root or in protected dir + serve via auth-checked PHP endpoint

## Upload Flow

```
POST /files/upload (multipart) [manage_files]
      ↓
Validate MIME + size + whitelist
      ↓
Generate safe storage path: storage/app/public/<uuid>.<ext>
      ↓
Move uploaded temp file → storage path
      ↓
Compute checksum
      ↓
INSERT files (source_type=LOCAL, path, mime, size, checksum)
      ↓
Return file object
```

## Add by URL

```
POST /files/url {url, folder_id}
      ↓
Validate URL (http/https, image type if image)
      ↓
INSERT files (source_type=URL, path_or_url=url)
      ↓
Return file object
```

## Folder Operations

- Create folder (nesting via parent_id)
- Rename folder
- Move folder (updates parent_id; children move with it)
- Delete folder (if empty or confirm cascade; references handled)

## Copy / Paste

- Copy a file: duplicate files row + duplicate physical file (new storage name)
- Copy a folder: recursive duplicate (new file copies, new folder structure)
- Paste into a folder: set folder_id on target

## Search

- By file_name (LIKE)
- By extension, type, folder
- Paginated

## Preview

- For images: render thumbnail (via /files/{id}/preview with auth)
- For other types: show metadata (name, size, type, uploader, date)

## Delete (Safe)

- Soft delete files/folders (deleted_at, status)
- If referenced (file_references exist) → block hard delete or warn; mark INACTIVE
- Keep audit record of deletion + who + when

## API Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | /files | List (folder filter, search, pagination) |
| POST | /files/upload | Upload file |
| POST | /files/url | Add by URL |
| POST | /files/folders | Create folder |
| PUT | /files/{id} | Rename |
| POST | /files/move | Move file(s)/folder(s) |
| POST | /files/copy | Copy |
| DELETE | /files/{id} | Delete (soft) |
| GET | /files/{id}/preview | Preview metadata/thumbnail |
| GET | /files/{id}/download | Download (auth) |

All require `manage_files` permission.

## Serving Local Files Securely

Private/protected local files served via:
```
GET /files/{id}/download   → validates auth + permission + scope
                             returns file with Content-Disposition
```

Never expose storage directory directly in web root for protected files.

## Future Object-Storage Migration

StorageService abstraction:
```php
interface StorageAdapter {
    public function store($file, string $name): string;   // returns reference
    public function url(string $reference): string;
    public function delete(string $reference): void;
}
// LocalStorageAdapter (now)
// S3StorageAdapter (future)
```
Entities reference `file_id`; adapters handle physical location. Switch adapter = update config.

## Audit

- File upload / add-by-url → audit
- Move / rename → audit
- Copy / delete → audit
- Reference fix → audit
- Folder create/rename/move/delete → audit

---

**Next**: [25-system-settings.md](25-system-settings.md) for configuration.