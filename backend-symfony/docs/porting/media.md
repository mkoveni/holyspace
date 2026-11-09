# Media context (stored images on the file system)

Status: **M1 (foundations), M2 (domain and application) M3 (infrastructure) and M4 (HTTP) M5 (owner adoption) and M6 (cutover) done 2026-11-09; the legacy tables are dropped by a follow-up release (see the M6 decision log).** Media is Symfony-only: Java has no equivalent, so there is
no Java specification to port. This file is the specification. Update the status board and the decision log at the end
of every session.

## Session prompt

> Continue the Media context: read `backend-symfony/AGENTS.md` and this file, then do the next unchecked slice.
> Finish with `make check` green and update this file.

## Purpose and scope

Media stores and serves image files for other contexts: profile photos, people photos, family photos and banners, event
images and banners, community banners. **Bytes live on the file system; the database holds only metadata and the
pointer to the current file.** Media owns files and image slots. It does not own what an image means: owners (People,
Events, Communities, IdentityAccess) keep their own permissions and existence rules.

Out of scope: documents, video, audio, SVG/HEIC/GIF, on-demand resizing, object storage (the `FileStore` port leaves
room for it).

## Current state this replaces

- Event and community covers are stored as BYTEA in `cover_images` (V37), with per-owner pointer tables
  `event_cover_images` and `community_cover_images`; validation, store and HTTP contract sit in `Shared`
  (`CoverImage`, `CoverImageStore`, `CoverImages`, `CoverImageHttp`, `DbalCoverImageStore`).
- Members read `coverImageUrl`, a same-origin `/api/public/cover-images/{id}` path.
- The admin frontend `PortalImage` already calls `/api/media/{person|family|event|community|form}/{id}` and the shared
  `ImageUploader` sends a cropped PNG at exact preset dimensions. That URL contract is kept.
- Nothing stores person or family pictures yet in Symfony.

## Design

### Layers

Flat context `src/Media/` (not modular). Dependencies and layering follow `AGENTS.md` and `deptrac.yaml`.

```
src/Media/
  Domain/         ImageSlot (aggregate), MediaFile, ImageKind, ImagePolicy, ImageSlotRepository, exceptions
  Application/    Contract/ (MediaImages read contract, ImageOwnership owner contract)
                  Port/ (ImageProcessor, FileStore)
                  Command/ChangeImage, Command/RemoveImage, Query/GetImage, Query/GetImageContent
  Infrastructure/ InterventionImageProcessor, LocalFileStore, DbalImageSlotRepository, DbalImageSlotReader
  Presentation/   MediaController (/api/media/...), PublicMediaController (/api/public/media/...)
```

Application depends on no vendor package (deptrac): Intervention and the filesystem sit behind `ImageProcessor` and
`FileStore`.

### Storage

- Root: `MEDIA_STORAGE_PATH`. Dev and test default to `var/media` in the project; production sets
  `/srv/onechurch-api/shared/media` (outside the release directory, which is replaced on every deploy, and outside
  `public/`, so nothing is served or executed by path).
- Layout: `{root}/{kind}/{aa}/{bb}/{fileId}.webp`, `aa` and `bb` the first hex pairs of the file UUID. Variants are
  siblings: `{fileId}@64.webp`.
- A fresh random UUID per upload; a stored file never changes, so its URL can be cached forever.
- Writes go to `{root}/tmp/{uuid}` and are `rename()`d into place (same filesystem), so a reader never sees a partial
  file.
- Paths are derived from UUIDs only. Client file names are never used.

### Consistency between database and disk

The filesystem is not transactional. Order of work in a change:

1. Process the upload, then write the new file (an orphan at worst).
2. In the command transaction: insert `media_files`, advance the `media_slots` pointer and version, write the audit row.
3. Delete the replaced file **after commit**, never inside the transaction (a rollback would otherwise destroy a file
   that is still referenced). Implemented as a `media_pending_deletions` row written in the transaction and drained by a
   scheduled task, so a crash cannot lose the deletion.
4. `app:media:gc` removes files with no `media_files` row older than 24 hours, and `media_files` rows no slot refers to.

### Data model (V38)

- `media_files(id, owner_type, owner_id, kind, path, mime, width, height, byte_size, sha256, created_at)`.
- `media_slots(owner_type, owner_id, kind, file_id NULL, version, updated_at)`, primary key
  `(owner_type, owner_id, kind)`. One versioned slot per owner and kind; removal keeps the row with a null file and a
  higher version, so a stale request cannot resurrect an old image.
- `media_pending_deletions(file_id, path, queued_at)`.

### Image processing (Intervention Image)

`intervention/image` ^4.4 (4.4.1 when M1 landed; API: `ImageManager::usingDriver(GdDriver::class)`, `decodeBinary()`) with the **GD driver** (the production Docker already builds GD with JPEG and WebP). Wrapped in
`InterventionImageProcessor implements ImageProcessor`.

1. Before decoding: byte cap, magic bytes (JPEG, PNG, WebP only), `getimagesizefromstring`, and a pixel cap so a small
   file cannot expand past `memory_limit`.
2. Decode and auto-orient from EXIF.
3. Fit to the kind's policy: `cover()` for fixed aspect kinds, `scaleDown()` for free aspect kinds. The client crop is
   never trusted.
4. Re-encode to WebP. Re-encoding drops EXIF/GPS and neutralises polyglot payloads.
5. Generate the named variants for the kind at upload time. No on-demand resizing (an unauthenticated write path).

| Kind | Owner types | Output | Public |
|---|---|---|---|
| `profile` | account | 250 x 250 | no |
| `person` | person | 250 x 250 | no |
| `family` | household | 1200 x 800 | no |
| `family_banner` | household | 1200 x 300 | no |
| `event` | event | 1280 x 720 | yes |
| `event_banner` | event | 1280 x 720 | yes |
| `community` | community | 1280 x 720 | yes |

### Owner integration

Media never imports another context. Media publishes `Application/Contract/ImageOwnership` (`exists`, `canView`,
`canManage` for an owner type); each owning context implements and tags it in its own Infrastructure (the pattern of
`ActivitySource` and `DeliveryEligibility` in `services.yaml`). Owners that put an image URL in a read model depend on
`Application/Contract/MediaImages` (batched, no N+1).

Change flow: owner permission, lock owner then slot, version check, process and store, commit, delete the old file.

### HTTP contract

Kept from `CoverImageHttp` so `PortalImage` works unchanged:

- `GET /api/media/{target}/{id}` returns `{version, image|null}`.
- `POST /api/media/{target}/{id}`: multipart `image`, `X-Image-Version` required.
- `DELETE /api/media/{target}/{id}`: `X-Image-Version` required.
- `GET /api/media/{target}/{id}/content`: authenticated bytes.
- `GET /api/public/media/{fileId}[/{variant}]`: only for public kinds; any other kind is a 404.

Bytes are served with the existing hardening headers (`nosniff`, `CSP: sandbox`, `CORP`, `X-Robots-Tag`). The public
route adds `Cache-Control: public, max-age=31536000, immutable`. Delivery is behind a `FileDelivery` strategy:
`BinaryFileResponse` first, `X-Accel-Redirect` later without touching callers.

Errors: 400 invalid input, 401/403 permission, 404 missing owner, 409 stale version.

### Privacy

Person, family and profile pictures are personal data and children's photos exist in this system. They are served only
through the authenticated `/content` route and never through the public route (`ImageKind::isPublic()`). Event and
community images stay public by unguessable id, as covers are today.

## Status board

| # | Slice | Needs | Status |
|---|---|---|---|
| M1 | Foundations: GD/EXIF in the Docker images and host checks, `intervention/image`, `MEDIA_STORAGE_PATH` (config, env examples, deploy directory, systemd), V38 migration | - | [x] |
| M2 | Domain and application: `ImageSlot`, `ImagePolicy`, `ImageKind`, ports, change/remove/get handlers, `ImageOwnership`, in-memory fakes and handler tests | M1 | [x] |
| M3 | Infrastructure: `InterventionImageProcessor`, `LocalFileStore`, DBAL repository and reader, pending deletions, `app:media:delete-pending`, `app:media:gc`, storage health check | M2 | [x] |
| M4 | Presentation: `/api/media` and public routes, `security.yaml`, headers, upload rate limit, API tests | M3 | [x] |
| M5 | Owner adoption: People (person), households (family), Events and Communities (move off covers), accounts (profile) | M4 | [x] |
| M6 | Cutover: `app:media:import-covers`, redirect of `/api/public/cover-images/{id}`, drop old tables and `Shared` cover code, backup runbook, rewrite `docs/portal-images.md` | M5 | [x] (table drop deferred, see log) |

## Decision log

- 2026-11-09: Media is its own flat context, not an extension of `Shared`. `Shared` stays the kernel.
- 2026-11-09: Intervention Image resolved to the 4.x line (4.4.1), not 3.x; M2+ code targets the v4 API. GD driver, WebP output. Confirm mobile minimum OS renders WebP before M5.
- 2026-11-09: The old file is deleted after commit through a queued deletion, never in the transaction.
- 2026-11-09: Personal kinds are not public (see Privacy). To be confirmed with the product owner.
- 2026-11-09: Open: whether an account's profile photo and its linked person's photo are one slot or two. M1 to M4 do
  not depend on the answer.

- 2026-11-09 (M2): Variants are not stored as rows. A variant's path is derived from its file's path
  (`MediaFile::variantOf`) and the widths allowed are the kind's `ImagePolicy::variantWidths`, so the V38 schema needs no
  variant table. The deletion drainer (M3) derives the kind from the first path segment to find a file's variants.
- 2026-11-09 (M2): `media_audit` was added to V38 (amended before any deploy; dev databases that applied the first V38
  must drop the Media tables and the `schema_migrations` row for 38, since applied files are checksum-validated).
- 2026-11-09 (M2): `ImageOwnership` takes strings (`ownerType()` returns the `OwnerType` value) so owning contexts import
  only `Application\Contract` types. `exists()` must take a share lock on the owner row: lock order is owner, then slot.
  Access check order is owner permission first, then existence, so a caller without access learns nothing.
- 2026-11-09 (M2): The slot is locked and its version checked before the upload is processed, so a stale request costs no
  CPU. Empty and over-5 MB uploads are refused before the slot is locked.
- 2026-11-09 (M2): The Media handlers are excluded from autowiring in `config/services.yaml` (marked TEMPORARY) because
  their adapters arrive in M3; the container would not compile otherwise. `ImageOwners` and the `media.image_owners` tag
  are already wired.
- 2026-11-09 (M2): Ports: `ImageProcessor`, `FileStore`, `PendingDeletions`, `MediaAuditWriter` (in `Application/Port`);
  `ImageSlotRepository` (domain); `ImageViewReader` and `MediaImages` (in `Application/Contract`). The M3 DBAL class
  implements `ImageSlotRepository` and `ImageViewReader` together, so its non-locking read is named `view`.
  `MediaImages` (cross-context batched lookup) has no implementation yet; M3 adds it.

- 2026-11-09 (M3): The TEMPORARY handler exclusion is gone; every Media handler is wired to a real adapter.
- 2026-11-09 (M3): Slots are serialized by a transaction-scoped advisory lock on `media:{owner type}:{owner}:{kind}` (it
  also covers a slot with no row yet), then `save` checks the version it advanced from with `ON CONFLICT ... WHERE
  version = previous`, so a lost race is a 409, never an overwrite. `media_slots` rows are only ever written at version
  1 or higher, as V38's CHECK requires.
- 2026-11-09 (M3): `LocalFileStore` accepts only paths of the exact shape the domain builds (an allow-list regex, not a
  search for `..`), writes via `tmp/` plus `rename()`, and creates files 0640 and directories 0750.
- 2026-11-09 (M3): Deletion is `media_pending_deletions` (written in the change's transaction) drained by
  `DeletionDrain` every minute: delete the main file and the kind's variants, then in one transaction drop the queue row
  and the `media_files` row unless a slot still holds it. `OrphanSweep` (`app:media:gc`, daily) removes files no
  `media_files` row records that are over 24 hours old, and temporary files over an hour old. A test proves a rolled-back
  upload leaves the live picture alone and the sweep removes its files.
- 2026-11-09 (M3): The schedule uses `every('1 day')` for the sweep: a cron expression would need the
  `dragonmantank/cron-expression` package. `Intervention` was added to deptrac's Vendor layer.
- 2026-11-09 (M3): Health: `/actuator/health` now also runs every `Shared\Application\HealthCheck` tagged
  `app.health_check`; Media's `StorageHealthCheck` reports DOWN (503) when the storage root cannot be created or written.
  There is no separate `/health/ready` route in the Symfony app.
- 2026-11-09 (M3): `ProcessedImage` is always WebP at quality 82 with metadata stripped, cropped with `cover()`; EXIF
  orientation is applied by Intervention's default auto-orientation (proved with a real oriented JPEG and a control).

- 2026-11-09 (M4): The route `{target}` is restricted to `profile|person|family|family_banner|event_banner`
  (`MediaHttp::TARGETS`, marked TEMPORARY) because Events' and Communities' cover controllers still own
  `/api/media/event/{id}` and `/api/media/community/{id}`. M5 moves those covers to Media and adds `event` and
  `community` to the list. A test pins which controller owns each path.
- 2026-11-09 (M4): No `#[IsGranted]`: the owning context's `ImageOwnership` decides, the firewall gives the 401. A caller
  without access gets 403 even for an owner that does not exist; 404 for a missing owner is only seen by someone who
  could have used it.
- 2026-11-09 (M4): JSON is the cover contract plus `public` and `variants` (`[{width, url}]`). `url` is the public path
  `/api/public/media/{fileId}` for public kinds and the authenticated `/api/media/{target}/{id}/content` for personal
  kinds; variants are `/{width}` or `?variant={width}`. Responses never contain the storage path. Errors follow the
  repository's mapping (400 invalid, 403, 404, 409 stale, 429, 503), not Java's 422.
- 2026-11-09 (M4): Public route: `public, max-age=31536000, immutable` with an ETag (304 on `If-None-Match`), plus the
  hardening headers. Authenticated content: `private, no-store`. `security.yaml` lets `GET|HEAD
  /api/public/media/{id}[/{variant}]` through without a session; anything that is not a stored public picture is a 404.
- 2026-11-09 (M4): Uploads are rate limited per account (sliding window, 30 per 10 minutes, `media_upload` in
  `framework.yaml`). The token is taken before validation, so rejected uploads count too.
- 2026-11-09 (M4): CORS now allows `X-Image-Version`; before, a cross-origin upload (the existing cover uploads
  included) would fail its preflight.
- 2026-11-09 (M4): API tests use `TestImageOwner` (tests/Support), registered for `person` and `event` in `when@test`.
  `_defaults` and `_instanceof` do not apply inside a `when@` block, so its autowiring and tag are explicit. M5 removes
  these stand-ins in favour of the real owners. Test pictures go to `/tmp/onechurch-media-test` (`.env.test`).

- 2026-11-09 (M5): Each owner implements `ImageOwnership` in its own Infrastructure and takes `FOR SHARE` on its row in `exists()`: `PersonImageOwner` (`people.read`/`people.manage`), `HouseholdImageOwner` (same codes; `family`, `family_banner`), `EventImageOwner` (`events.read`/`events.manage`), `CommunityImageOwner` (view: `ministries.read` or `ministries.lead`; manage: `ministries.manage`), `AccountImageOwner` (the account itself, or `identity.manage`). A leader may view any community picture here; the old controller hid other communities from leaders with a 404, which Media's contract (permission, then existence) does not express.
- 2026-11-09 (M5): The event and community cover controllers, handlers, readers and stores are deleted; `/api/media/event|community/{id}` is served by Media (`MediaHttp::TARGETS` now lists every kind) and the test stand-ins `TestImageOwner` are gone. Cover changes are audited in `media_audit` instead of `event_audit` / `community_history`.
- 2026-11-09 (M5): Member read models take the file id from `MediaImages::fileIds` (one query per page, no joins on Media tables); `coverImageUrl` is now `/api/public/media/{fileId}` (`Shared\Presentation\ImageUrl`). Event and community members read only Media: **covers uploaded before M5 live in `cover_images` and are not shown until M6's `app:media:import-covers` runs**, so deploy M5 and M6 together. `event_cover_images`, `community_cover_images`, `cover_images` and the `Shared` cover code remain for that import.
- 2026-11-09 (M5): Not done: a person's own self-service photo or an account's linked-person photo (the open one-slot-or-two question); no member-facing read model shows `person`/`family`/`profile` pictures yet, so none of them calls `MediaImages`.

- 2026-11-09 (M6): `app:media:import-covers` (`ImportCoverHandler`, one transaction per cover) copies each legacy cover through the normal processor (cropped to 1280 x 720 WebP, variants) and **keeps the legacy image id as the new file id**, so the old URL maps to the new one by id alone. It skips any slot Media has ever used (version above 0, including a picture staff removed), so re-running it can neither overwrite nor resurrect anything; it exits non-zero if any cover failed, listing each. `deploy/remote-install.sh` runs it after `app:migrate`, tolerating a failure with a warning so one corrupt image cannot abort a deploy.
- 2026-11-09 (M6): `GET /api/public/cover-images/{id}` is now `Media\Presentation\LegacyCoverRedirectController`: a 301 to `/api/public/media/{id}` (cacheable for a day). A never-imported id ends in a 404 at the target. The route stays public in `security.yaml`.
- 2026-11-09 (M6): The `Shared` cover code (`CoverImage`, `CoverSlot`, `CoverImages`, `CoverImageStore`, `DbalCoverImageStore`, `CoverImageController`, `CoverImageHttp`) is deleted. The importer reads the legacy tables through `DbalLegacyCoverSource` (marked TEMPORARY).
- 2026-11-09 (M6) **Deferred on purpose: dropping `cover_images`, `event_cover_images` and `community_cover_images`.** Deploys run `app:migrate` before the import, so a DROP in the same release would destroy the bytes before they are copied. Follow-up release, after the import has been verified in production: add `V39__drop_legacy_cover_images.sql` (`DROP TABLE event_cover_images, community_cover_images, cover_images;`), delete `DbalLegacyCoverSource`, `ImportCover*`, `ImportCoversConsoleCommand`, the deploy-script step, the `event_cover_images` entry in `EventPermissionsTest`, and `LegacyCoverImportTest`. Keep the redirect for as long as old URLs may be cached.
- 2026-11-09 (M6): Imported covers have no `media_audit` row: the table requires an actor and the import has none.

## Cutover runbook

1. Take a database backup (`pg_dump`) before deploying.
2. Deploy. The script migrates, then runs `app:media:import-covers`; read its `Imported N, skipped M, failed K.` line.
3. If `failed` is not 0, fix or re-upload those covers in the portal and re-run `php bin/console app:media:import-covers` (safe any number of times).
4. Check: `SELECT count(*) FROM media_slots WHERE kind IN ('event','community') AND file_id IS NOT NULL` equals the number of legacy rows with an image, and a member's `coverImageUrl` loads.
5. Only then ship the follow-up release that drops the legacy tables.

## Backups

From this release a restore needs both halves, taken at the same moment: `pg_dump` and a copy of `MEDIA_STORAGE_PATH` (`/srv/onechurch-api/shared/media`), for example `rsync -a --delete` to the backup host right after the dump. A database restored without the directory keeps its rows but every picture is a 404; a directory restored without the database leaves files the sweep deletes after 24 hours. After a restore run `app:media:gc` only once both are back. Files are immutable, so an incremental copy never has to re-read old ones.

## Operations

- Back up the media directory together with the database; `pg_dump` alone no longer restores the pictures.
- The directory must exist, be writable by the app user and survive releases (`shared/media`).
- PHP needs the `gd` (JPEG, PNG, WebP) and `exif` extensions on the host.
