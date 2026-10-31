# Reusable image uploader

`frontend/src/components/common/ImageUploader.vue` is a presentation-only Vue
component. It accepts output dimensions, a label, an existing image URL, a disabled
state, a byte limit and an optional asynchronous upload function. It does not
know about people, events, authentication or API endpoints.

```vue
<ImageUploader
  label="Person photo"
  :width="250"
  :height="250"
  :upload="savePersonPhoto"
/>

<ImageUploader
  label="Event banner"
  :width="1200"
  :height="300"
  :upload="saveEventBanner"
/>
```

The upload function receives a PNG `File` with the exact configured dimensions.
Resolve after saving, or throw an error with a user-facing message. Failed uploads
keep the crop available for retry. Without an upload function, listen to `cropped`
to receive the prepared file. `cancel` reports an explicit cancellation.

Additional props:

| Prop | Default | Purpose |
| --- | --- | --- |
| `src` | empty | Display the existing image before choosing a replacement |
| `maxBytes` | 5 MB | Maximum input and prepared file size |
| `maxDimension` | 4096 | Maximum allowed output dimension per side |
| `disabled` | false | Prevent edits while the parent is loading or saving |
| `label` | Upload image | Accessible label and heading |

The crop always fills the requested aspect ratio without stretching. Users can
drag the preview, zoom from 1× to 4×, adjust position using keyboard-accessible
sliders, reset, change the source or cancel. Transparent areas remain transparent.
Browser image decoding applies camera orientation. PNG export strips source
metadata. The backend continues to independently validate, sanitize and authorize
uploads; client-side checks do not replace server checks.

`PortalImage.vue` owns authenticated persistence, optimistic version headers,
loading and removal. It supplies the presets from `image-uploader/crop.ts`:
250×250 for people, 1200×300 for events/forms and 1200×800 for families. Existing
call sites inherit these automatically. Its optional `width` and `height` props
override the presets. The standalone editor permits dimensions up to 4096 pixels
and 16 million output pixels; `PortalImage` sets `maxDimension` to 1920 to match the media API
and rejects larger output configurations instead of silently resizing them. Smaller source images are upscaled
when necessary and may appear less sharp.

Verification includes crop geometry tests, frontend type checking/build and
browser tests covering both requested sizes, export dimensions and upload retries.
Run the isolated browser tests (the component configuration starts its own Vite server):

```sh
npm run test:components
```
