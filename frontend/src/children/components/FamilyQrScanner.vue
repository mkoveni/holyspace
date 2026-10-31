<template>
  <v-btn
    prepend-icon="mdi-qrcode-scan"
    variant="tonal"
    @click="open = true"
  >
    {{
      kind === 'arrival'
        ? 'Scan family check-in reference'
        : 'Scan collection QR'
    }}
  </v-btn>

  <v-dialog
    v-model="open"
    max-width="560"
    scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-6">
        {{ kind === 'arrival' ? 'Welcome a child' : 'Read collection label' }}
      </v-card-title>

      <v-card-text>
        <p class="directory-muted mb-4">
          {{
            kind === 'arrival'
              ? 'A reference helps find the child. Review preparation and confirm arrival separately.'
              : 'Read the label, then choose the authorized collector and verify their identity in person.'
          }}
        </p>

        <v-alert
          v-if="error"
          class="mb-4"
          type="error"
        >
          {{ error }}
        </v-alert>

        <video
          v-show="camera"
          ref="video"
          aria-label="Family QR camera preview"
          autoplay
          muted
          playsinline
          style="width: 100%; border-radius: 16px"
        />

        <div class="d-flex ga-2 flex-wrap my-4">
          <v-btn
            v-if="!camera"
            variant="tonal"
            @click="startCamera"
          >
            Start camera
          </v-btn>

          <v-btn
            v-else
            variant="text"
            @click="stopCamera"
          >
            Stop camera
          </v-btn>

          <v-btn
            variant="text"
            @click="file?.click()"
          >
            Use QR photo
          </v-btn>

          <input
            ref="file"
            accept="image/*"
            hidden
            type="file"
            @change="readPhoto"
          >
        </div>

        <v-text-field
          v-model="value"
          autocomplete="off"
          hint="A connected scanner can fill this field. References stay in memory."
          label="Family QR reference"
          persistent-hint
          type="password"
        />
      </v-card-text>

      <v-card-actions class="pa-6">
        <v-btn @click="open = false">Close</v-btn>
        <v-spacer />

        <v-btn
          color="primary"
          :disabled="!value"
          @click="useReference"
        >
          Use reference
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import type { FamilyQr } from '../familyQr'
  import jsQR from 'jsqr'
  import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
  import { parseFamilyQr } from '../familyQr'
  const props = defineProps<{
    kind: 'arrival' | 'collection'
    resourceId: string
  }>()
  const emit = defineEmits<{ scanned: [reference: FamilyQr] }>()
  const open = ref(false),
        camera = ref(false),
        value = ref(''),
        error = ref(''),
        video = ref<HTMLVideoElement | null>(null),
        file = ref<HTMLInputElement | null>(null)
  let stream: MediaStream | null = null,
      timer: ReturnType<typeof setTimeout> | undefined,
      generation = 0
  function stopCamera () {
    generation++
    if (timer) clearTimeout(timer)
    for (const track of stream?.getTracks() ?? []) track.stop()
    stream = null
    camera.value = false
    if (video.value) video.value.srcObject = null
  }
  function useReference () {
    try {
      const reference = parseFamilyQr(value.value, props.kind, props.resourceId)
      stopCamera()
      value.value = ''
      open.value = false
      emit('scanned', reference)
    } catch {
      error.value
        = 'Use a family QR for this selected session or attendance. Other QR types cannot be used here.'
      value.value = ''
    }
  }
  function decode (image: CanvasImageSource, width: number, height: number) {
    const canvas = document.createElement('canvas'),
          scale = Math.min(1, 960 / Math.max(width, height))
    canvas.width = Math.round(width * scale)
    canvas.height = Math.round(height * scale)
    const context = canvas.getContext('2d', { willReadFrequently: true })
    if (!context) return null
    context.drawImage(image, 0, 0, canvas.width, canvas.height)
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height)
    return jsQR(pixels.data, pixels.width, pixels.height)?.data
  }
  function frame () {
    if (!camera.value || !open.value) return
    const preview = video.value
    if (preview?.videoWidth) {
      const code = decode(preview, preview.videoWidth, preview.videoHeight)
      if (code) {
        value.value = code
        stopCamera()
        return
      }
    }
    timer = setTimeout(frame, 250)
  }
  async function startCamera () {
    stopCamera()
    const run = generation
    error.value = ''
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value
        = 'Camera access requires HTTPS or localhost. Use a photo or connected scanner.'
      return
    }
    try {
      const incoming = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
        audio: false,
      })
      if (!open.value || run !== generation) {
        for (const track of incoming.getTracks()) track.stop()
        return
      }
      stream = incoming
      camera.value = true
      await nextTick()
      if (video.value) {
        video.value.srcObject = incoming
        await video.value.play()
        frame()
      }
    } catch {
      stopCamera()
      error.value = 'Unable to open the camera. Allow access or use a QR photo.'
    }
  }
  async function readPhoto (event: Event) {
    const input = event.target as HTMLInputElement,
          image = input.files?.[0],
          run = generation
    if (!image) return
    if (image.size > 10 * 1024 * 1024) {
      error.value = 'Choose a QR photo smaller than 10 MB.'
      input.value = ''
      return
    }
    const url = URL.createObjectURL(image)
    try {
      const photo = new Image()
      photo.src = url
      await photo.decode()
      if (!open.value || run !== generation) return
      const code = decode(photo, photo.naturalWidth, photo.naturalHeight)
      if (!code) {
        error.value = 'No readable QR found. Try a clear photo.'
        return
      }
      value.value = code
      stopCamera()
    } catch {
      error.value = 'Unable to read this QR photo.'
    } finally {
      URL.revokeObjectURL(url)
      input.value = ''
    }
  }
  watch(open, () => {
    stopCamera()
    value.value = ''
    error.value = ''
  })
  watch(
    () => props.resourceId,
    () => {
      open.value = false
      stopCamera()
      value.value = ''
    },
  )
  onBeforeUnmount(() => {
    stopCamera()
    value.value = ''
  })
</script>
