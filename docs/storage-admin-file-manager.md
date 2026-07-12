# Storage Module — Admin File Manager (Boundary Notes)

This documents the admin file manager built on top of the existing `Storage`
module (`GET /storage`) and what changed to support it.

## Existing architecture (unchanged)

```
Presentation (StoragePageController / StorageApiController)
    ↓
Application (StoredFileDTO / StoreUploadedFileRequest)
    ↓
Domain contract (IStorageRepository / IStorageService)
    ↓
Infrastructure (StorageRepository → Doctrine DBAL, StorageService → Flysystem local adapter)
```

Controllers never touch Flysystem or the `uploads` table directly. All new
endpoints go through `IStorageService` / `IStorageRepository`, matching the
existing dependency direction.

## What the `uploads` table looked like before this change

One table (`uploads`) backs every file in the system. Files are **not**
scoped to an admin's own uploads — they're attached to an owning entity via
`owner_type` + `owner_uuid` (e.g. a Content page's media, an Assessment
video). There is no folder concept and no per-user "my files" view; the
admin file manager is therefore a manager over *all* stored files an admin
is permitted to see, filtered by MIME category, not by folder.

Known gaps found before building:
- No `uploaded_by` column — no way to show "uploaded by" in the UI.
- No image `width`/`height` — required for the details panel.
- No `checksum_sha256` — useful for the details panel / future dedup.
- No summary/aggregate endpoint — a naive UI would have summed every row
  in the browser, which the spec explicitly forbids.
- No file-metadata endpoint distinct from the binary `/view` route.
- No concept of "is this file still in use elsewhere" — needed to block
  destructive deletes safely.

## Schema changes

- `platform/Schema/Modules/Storage/StorageSchemaBuilder.php` (canonical,
  fresh installs) and `migrations/Version20260711030000_AddStorageUploadMetadata.php`
  (existing installs) both add: `width`, `height`, `checksum_sha256`,
  `uploaded_by` (+ index) to `uploads`.

## API additions (`modules/Storage/Presentation/routes.php`)

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/storage/summary` | Aggregate totals (files, bytes, per-category) for the summary cards. Single grouped SQL query, never a per-row scan. |
| GET | `/api/v1/storage/files/{uuid}` | File metadata (JSON), distinct from the binary `/view` route. |
| GET | `/api/v1/storage/files/{uuid}/usage` | Cross-module reference count, used to decide whether permanent delete is safe. |
| POST | `/api/v1/storage/files/{uuid}/restore` | Restore a trashed file. |
| DELETE | `/api/v1/storage/files/{uuid}` | **Unchanged** — soft delete (move to trash). |
| DELETE | `/api/v1/storage/files/{uuid}/permanent` | New — hard delete, blocked with `409 Conflict` if the file is still referenced elsewhere. |

`GET /files` gained `type`, `date_from`, `date_to`, `uploaded_by`, `sort`,
`direction`, `page`, `per_page` query params, all applied server-side
(`StorageRepository::where()` / `orderBy()`), never client-side.

The public `/api/v1/files/{uuid}/view` and `/download` routes are untouched.

## Permission-derived capability flags

`StorageApiController::present()` is the single place that decides
`canView` / `canDownload` / `canDelete` / `canCopyPublicLink` from the
caller's `UserContext` privileges. The frontend only ever renders what the
backend already told it it's allowed to do — it never re-derives
permissions itself. No physical path, Flysystem root, or storage
credentials are ever included in an API response.

## Usage-aware permanent delete

`StorageRepository::USAGE_PROBES` lists known cross-module pointers into
`uploads` (`content_media.storage_file_uuid`,
`assessment_videos.storage_file_uuid` and its thumbnail/pose/blurred
variants). `IStorageService::delete()` checks this before removing the row
or the underlying file, and throws `ConflictException` (→ HTTP 409) with a
clear message if the file is still referenced. Soft delete (move to trash)
is always safe and is the default UI action; permanent delete is a
separate, explicit action gated the same way (`storage.file.delete`).

**Extending this list:** any new module that stores a `storage_file_uuid`
pointer should add its `[table, column]` pair to `USAGE_PROBES` so it's
covered by the delete guard.

## Frontend

- View: `modules/Storage/Presentation/Views/admin_storage.php`
- CSS: `public/assets/css/modules/storage-file-manager.css`
- JS: `public/assets/js/modules/storage-file-manager.js`

Follows the existing Bootstrap/Sneat conventions (`App.api`, `App.notify`,
`App.ui`, `page_header.php` partial). No new frontend framework. Grid/table
view mode is persisted in `localStorage`. All filtering, sorting, and
pagination happen against the API — the client never holds more than one
page of records.

## Files touched

- `platform/Schema/Modules/Storage/StorageSchemaBuilder.php`
- `migrations/Version20260711030000_AddStorageUploadMetadata.php` (new)
- `modules/Storage/Application/DTOs/StoredFileDTO.php`
- `modules/Storage/Domain/Contracts/IStorageRepository.php`
- `modules/Storage/Domain/Contracts/IStorageService.php`
- `modules/Storage/Infrastructure/StorageRepository.php`
- `modules/Storage/Infrastructure/StorageService.php`
- `modules/Storage/Presentation/StorageApiController.php`
- `modules/Storage/Presentation/StoragePageController.php`
- `modules/Storage/Presentation/routes.php`
- `modules/Storage/Presentation/Views/admin_storage.php`
- `modules/Storage/ServiceProvider.php`
- `public/assets/css/modules/storage-file-manager.css` (new)
- `public/assets/js/modules/storage-file-manager.js` (new)
- `tests/Storage/StorageServiceFoundationTest.php` (new)

## Not done (out of scope for this pass)

- No new IAM permissions were added; restore reuses `storage.file.delete`.
- The usage-probe list is a small, explicit set — it is not a generic
  reflection-based scan of every module's schema. Extend it as new
  reference columns appear.
- Image thumbnail *generation* (resized variants) is not implemented; the
  grid/table views use the original image as its own thumbnail via
  `<img>` sizing. If original images are large, consider adding a real
  thumbnail pipeline as a follow-up.
