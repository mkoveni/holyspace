<template>
  <v-btn
    :disabled="!admissionOpen"
    prepend-icon="mdi-qrcode-scan"
    variant="tonal"
    @click="open = true"
  >
    Scan member QR
  </v-btn>

  <v-dialog
    v-model="open"
    max-width="560"
    scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-6">Welcome a member</v-card-title>

      <v-card-text>
        <p class="directory-muted mb-4">
          Scan the member’s Divine Community Space event attendance QR. The server checks
          their booking for this gathering before recording an arrival.
          Children’s collection codes cannot be used here.
        </p>

        <v-alert
          v-if="error"
          class="mb-4"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <v-alert
          v-if="success"
          class="mb-4"
          type="success"
          variant="tonal"
        >
          Arrival recorded. The attendance roster has been refreshed.
        </v-alert>

        <video
          v-show="camera"
          ref="video"
          aria-label="Event QR camera preview"
          autoplay
          muted
          playsinline
          style="width: 100%; border-radius: 16px"
        />

        <div class="d-flex ga-2 flex-wrap my-4">
          <v-btn
            v-if="!camera"
            :disabled="busy"
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
            :disabled="busy"
            variant="text"
            @click="file?.click()"
          >
            Use QR photo
          </v-btn>

          <input
            ref="file"
            accept="image/*"
            aria-label="Upload event QR photo"
            capture="environment"
            hidden
            type="file"
            @change="readPhoto"
          >
        </div>

        <v-text-field
          v-model="credential"
          autocomplete="off"
          :disabled="busy"
          hint="A scanner can fill this field. The code stays private."
          label="Event attendance code"
          persistent-hint
          type="password"
          @update:model-value="newCode"
        />

        <p
          v-if="credential"
          class="directory-muted mt-3"
        >
          Code ready. Confirm the member is present before recording arrival.
        </p>
      </v-card-text>

      <v-card-actions class="pa-6">
        <v-btn
          :disabled="busy"
          @click="open = false"
        >
          Close
        </v-btn>

        <v-spacer />

        <v-btn
          color="primary"
          :disabled="!valid"
          :loading="busy"
          @click="record"
        >
          Record arrival
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import jsQR from 'jsqr'
  import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
  import { errorMessage, eventApi } from '../api'
  const props = defineProps<{ occurrenceId: string, admissionOpen: boolean }>()
  const emit = defineEmits<{ 'checked-in': [] }>()
  const open = ref(false),
        credential = ref(''),
        busy = ref(false),
        error = ref(''),
        success = ref(false),
        camera = ref(false)
  const video = ref<HTMLVideoElement>(),
        file = ref<HTMLInputElement>()
  const valid = computed(() =>
    /^divinecommunityspace:event:v1:[\w-]{20,200}$/.test(credential.value),
  )
  let stream: MediaStream | undefined,
      timer: ReturnType<typeof setTimeout> | undefined,
      requestId: string = crypto.randomUUID(),
      generation = 0
  function stopCamera () {
    if (timer) clearTimeout(timer)
    for (const track of stream?.getTracks() ?? []) track.stop()
    stream = undefined
    camera.value = false
  }
  function newCode () {
    requestId = crypto.randomUUID()
    success.value = false
  }
  function accept (value: string) {
    if (!/^divinecommunityspace:event:v1:[\w-]{20,200}$/.test(value)) {
      error.value = 'This is not a Divine Community Space event attendance QR.'
      return false
    }
    credential.value = value
    newCode()
    error.value = ''
    stopCamera()
    return true
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
      if (code && accept(code)) return
    }
    timer = setTimeout(frame, 250)
  }
  async function startCamera () {
    const run = ++generation
    error.value = ''
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value
        = 'Camera access requires HTTPS or localhost. Use a QR photo or a connected scanner.'
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
      error.value
        = 'Unable to open the camera. Allow camera access or use a QR photo.'
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
      const value = decode(photo, photo.naturalWidth, photo.naturalHeight)
      if (!value) {
        error.value = 'No readable QR found. Try a clear photo of the whole code.'
        return
      }
      accept(value)
    } catch {
      error.value = 'Unable to read this photo. Try another image.'
    } finally {
      URL.revokeObjectURL(url)
      input.value = ''
    }
  }
  async function record () {
    if (!valid.value || busy.value) return
    busy.value = true
    error.value = ''
    success.value = false
    stopCamera()
    try {
      await eventApi.scanMember(props.occurrenceId, requestId, credential.value)
      credential.value = ''
      success.value = true
      emit('checked-in')
    } catch (error_) {
      error.value
        = errorMessage(error_)
          + ' Refresh attendance before trying again if the outcome is uncertain.'
    } finally {
      busy.value = false
    }
  }
  watch(open, value => {
    generation++
    if (!value) {
      stopCamera()
      credential.value = ''
      error.value = ''
      success.value = false
      requestId = crypto.randomUUID()
    }
  })
  onBeforeUnmount(() => {
    generation++
    stopCamera()
    credential.value = ''
  })
</script>
