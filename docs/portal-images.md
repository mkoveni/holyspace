# Portal photos and banners

Save a person, family, event or community before adding its picture. No separate storage account is needed.

| Picture | Where to upload | Permission to change | Stored as |
| --- | --- | --- | --- |
| Person | People → open the person | people.manage | 250 × 250 |
| Family | People → Households → open the family | people.manage | 1200 × 800 |
| Family banner | same page | people.manage | 1200 × 300 |
| Event | Events → open the event | events.manage | 1280 × 720 |
| Event banner | same page | events.manage | 1280 × 720 |
| Group or ministry | Ministries and groups → open it | ministries.manage | 1280 × 720 |
| Profile photo | the account itself, or an administrator | identity.manage for others | 250 × 250 |

Choose an image, review the preview, then select **Upload image**. To change a picture, choose another image and select **Replace image**. **Remove image** clears it. If someone else changed it meanwhile, select **Reload image**, review the latest picture, and choose your file again.

Use JPEG, PNG or WebP files of at most 5 MB. HEIC, SVG, GIF and documents are unsupported; export phone photos as JPEG first. The server never trusts the browser's crop: it corrects camera orientation, crops to the size in the table, and saves a fresh WebP, which drops the original file name, GPS position and other embedded data. A smaller copy is made at upload time for lists.

Person, family and profile pictures are personal data (children included) and are only served to signed-in people with the viewing permission. Event and community pictures are public by an unguessable address, because members' apps load them without a session. Replacing or removing a picture takes effect immediately; an image someone already saved cannot be recalled.

## Storage

Image bytes are files under `MEDIA_STORAGE_PATH` (production: `/srv/onechurch-api/shared/media`, outside the release directory). The database holds the metadata, a versioned slot per owner and kind, and the audit trail (who changed what, never the bytes). Replaced files are deleted a moment after the change commits; `app:media:gc` removes anything the database does not record. Back up the directory together with the database; see `backend-symfony/docs/porting/media.md` for the full design, the cutover runbook and the backup rule.

PHP needs the `gd` (JPEG, PNG, WebP) and `exif` extensions. `/actuator/health` reports DOWN when the storage directory cannot be written.

## API

* `GET /api/media/{target}/{ownerId}` returns `{version, image}`; `image` is null when there is none. The target is `profile`, `person`, `family`, `family_banner`, `event`, `event_banner` or `community`.
* `POST /api/media/{target}/{ownerId}` takes multipart field `image` and the header `X-Image-Version` (0 for a never-used slot). Uploads are limited to 30 per account per 10 minutes.
* `DELETE /api/media/{target}/{ownerId}` needs the same header.
* `GET /api/media/{target}/{ownerId}/content[?variant=N]` returns the bytes of a signed-in caller's allowed picture, `private, no-store`.
* `GET /api/public/media/{fileId}[/{width}]` serves event and community pictures without a session, cached for a year. Any other kind is a 404.
* `GET /api/public/cover-images/{id}` is the old cover address and redirects to the line above.

Errors: 400 invalid image or version header, 401/403 permission, 404 missing owner, 409 stale version, 429 too many uploads.
