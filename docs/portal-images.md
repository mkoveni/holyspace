# Portal photos and banners

Save a person, family, event or form before adding its picture. The church does not need a separate storage account or payment provider for images.

| Picture | Where to upload | Permission to change |
| --- | --- | --- |
| Person | People → open the person → Person picture | people.manage |
| Family | Families → open the family → Family picture | families.manage |
| Event | Events → open the event → Event banner | events.manage |
| Public form | Forms → Edit an existing form → Form banner; also available under Review responses | forms.manage |

Choose an image, review the preview, then select **Upload image**. To change an existing picture, choose another image and select **Replace image**. **Remove image** clears the current picture. If another staff member has changed it, select **Reload image**, review the latest picture, and choose your file again.

Use JPEG, PNG or WebP files of at most 5 MB and 16 million pixels; neither dimension may exceed 12,000 pixels. HEIC, SVG, GIF and documents are unsupported. Export phone HEIC photos as JPEG first. The system corrects JPEG camera orientation, scales large pictures to a maximum side of 1,920 pixels, preserves transparency, and saves a freshly encoded PNG without the original filename, GPS information or embedded metadata. Very complex images whose processed PNG exceeds 5 MB must be reduced before uploading. Pictures are displayed without cropping; wide images work best as banners.

People, family and event pictures require the corresponding viewing permission. Upload access also grants viewing access through existing permission rules. A public form's banner appears automatically on its shareable page while the form is published. Unpublishing the form prevents subsequent requests for that banner. Replacing or removing the banner affects subsequent requests immediately; an image someone has already viewed or saved cannot be recalled.

## Storage and operations

The Media module stores one versioned image slot per owner in `media_images`. Binary content and metadata commit together under the existing shared workflow lock. Replacements overwrite the previous blob; removals retain a small version tombstone to prevent stale requests from recreating an old image. Mutations append metadata-only audit entries under the owner's context with an independent UUID derived from the owner ID and image slot, so owner edits and image edits cannot share an audit version accidentally. No image bytes, filenames or EXIF data enter the audit journal.

Current owner APIs have no hard-delete operations. Image reads and writes always check that the owner still exists. Any future permanent owner deletion must delete its `media_images` slot in the same transaction. The `ImageStorage` port isolates the storage implementation for a future object-storage adapter.

Existing encrypted MySQL backups include the images (`mysqldump --hex-blob`). Restore the database to restore the pictures; no upload directory or extra volume is required. Database size and backup duration will grow with image usage. The restore verification compares image blob checksums as well as table readiness.

Apply migration `Version20261012070000` and rebuild PHP and web images. Both Docker PHP builds enable GD JPEG/WebP and EXIF support. PHP accepts 5 MB uploads with a 6 MB request limit; Nginx accepts 6 MB requests. The production CSP permits local blob previews. CORS allows `X-Image-Version` for authenticated split-origin uploads. Production readiness checks the image table.

## API

* `GET /api/media/{target}/{ownerId}` returns `{version, image}`; absent images return `image: null`. The target is `person`, `family`, `event` or `form`.
* `POST /api/media/{target}/{ownerId}` accepts multipart field `image` and header `X-Image-Version` containing the current version (zero for a never-used slot).
* `DELETE /api/media/{target}/{ownerId}` requires the same version header and removes the bytes.
* `GET /api/media/{target}/{ownerId}/content` returns authenticated PNG bytes with `private, no-store` and `nosniff`. No access tokens are embedded in image URLs.
* `GET /api/public/forms/{publicToken}/banner` returns only that published form's current banner. Unpublished, missing or invalid-token banners return 404. Successful public banner responses also use `no-store`.

Wrong permissions return 403, missing owners return 404, invalid images/version headers return 422, and stale versions return 409. Oversized transport requests may be rejected by Nginx or PHP before the controller. The shared Vue `PortalImage` component handles authenticated binary reads, public form reads, previews, upload/replacement/removal, and object URL cleanup on session changes and unmounting.
