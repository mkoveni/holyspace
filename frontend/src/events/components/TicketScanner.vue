<template>
  <v-btn
    prepend-icon="mdi-qrcode-scan"
    variant="tonal"
    @click="open = true"
  >
    Scan tickets
  </v-btn>

  <v-dialog
    v-model="open"
    max-width="560"
    scrollable
  >
    <v-card rounded="xl">
      <v-card-title class="pa-6 d-flex align-center">
        Scan tickets
        <v-spacer />
        <v-chip v-if="counts" size="small">{{ counts.checkedIn }} in · {{ counts.issued }} to come</v-chip>
      </v-card-title>

      <v-card-text>
        <div
          v-if="outcome"
          aria-live="assertive"
          class="scan-result"
          :class="`scan-result--${outcome.result.toLowerCase()}`"
          role="status"
        >
          <v-icon :icon="icon" size="48" />
          <strong class="scan-result__title">{{ title }}</strong>
          <span v-if="outcome.ticketType">{{ outcome.ticketType }} · {{ outcome.holder }}</span>
          <span v-if="outcome.reason">{{ outcome.reason }}</span>
          <span v-if="outcome.checkedInAt">Scanned {{ new Date(outcome.checkedInAt).toLocaleTimeString('en-ZA') }}</span>
        </div>

        <v-alert
          v-if="error"
          class="mb-4"
          role="alert"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <video
          v-show="camera"
          ref="video"
          aria-label="Ticket QR camera preview"
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
        </div>

        <v-form @submit.prevent="submit(typed)">
          <v-text-field
            v-model="typed"
            autocomplete="off"
            :disabled="busy"
            hint="A hardware scanner can type the code here and press Enter."
            label="Ticket code"
            persistent-hint
          />
        </v-form>
      </v-card-text>

      <v-card-actions class="pa-6">
        <v-btn @click="open = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup lang="ts">
  import type { ScanCounts, ScanOutcome } from '@/tickets/staff-api'
  import jsQR from 'jsqr'
  import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { ticketStaffApi } from '@/tickets/staff-api'

  const props = defineProps<{ occurrenceId: string }>()
  const emit = defineEmits<{ scanned: [] }>()
  const open = ref(false)
  const camera = ref(false)
  const busy = ref(false)
  const typed = ref('')
  const error = ref('')
  const outcome = ref<ScanOutcome>()
  const counts = ref<ScanCounts>()
  const video = ref<HTMLVideoElement>()
  let stream: MediaStream | undefined
  let timer: ReturnType<typeof setTimeout> | undefined
  let lastCode = ''
  let lastAt = 0

  const icon = computed(() => ({ ADMITTED: 'mdi-check-circle', ALREADY_USED: 'mdi-alert-circle', REJECTED: 'mdi-close-circle' })[outcome.value?.result ?? 'REJECTED'])
  const title = computed(() => ({ ADMITTED: 'Welcome in', ALREADY_USED: 'Already scanned', REJECTED: 'Do not admit' })[outcome.value?.result ?? 'REJECTED'])

  function stopCamera () {
    if (timer) clearTimeout(timer)
    for (const track of stream?.getTracks() ?? []) track.stop()
    stream = undefined
    camera.value = false
  }

  async function submit (code: string) {
    const value = code.trim()
    if (!value || busy.value) return
    busy.value = true
    error.value = ''
    try {
      outcome.value = await ticketStaffApi.scan(props.occurrenceId, value)
      counts.value = outcome.value.counts
      typed.value = ''
      if (outcome.value.result === 'ADMITTED') emit('scanned')
    } catch (error_) {
      outcome.value = undefined
      error.value = extractErrorMessage(error_, 'Unable to check this ticket. Check the connection and scan again.')
    } finally {
      busy.value = false
    }
  }

  function frame () {
    if (!camera.value || !open.value) return
    const preview = video.value
    if (preview?.videoWidth) {
      const canvas = document.createElement('canvas')
      const scale = Math.min(1, 720 / Math.max(preview.videoWidth, preview.videoHeight))
      canvas.width = Math.round(preview.videoWidth * scale)
      canvas.height = Math.round(preview.videoHeight * scale)
      const context = canvas.getContext('2d', { willReadFrequently: true })
      if (context) {
        context.drawImage(preview, 0, 0, canvas.width, canvas.height)
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height)
        const code = jsQR(pixels.data, pixels.width, pixels.height)?.data
        // The same code in front of the camera is answered once every four seconds, not on every frame.
        if (code && (code !== lastCode || Date.now() - lastAt > 4000)) {
          lastCode = code
          lastAt = Date.now()
          void submit(code)
        }
      }
    }
    timer = setTimeout(frame, 250)
  }

  async function startCamera () {
    error.value = ''
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value = 'Camera access requires HTTPS or localhost. Type the code or use a connected scanner.'
      return
    }
    try {
      const incoming = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
      if (!open.value) {
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
      error.value = 'Unable to open the camera. Allow camera access or type the code.'
    }
  }

  watch(open, value => {
    if (!value) {
      stopCamera()
      outcome.value = undefined
    }
  })
  onBeforeUnmount(stopCamera)
</script>

<style scoped>
.scan-result {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: center;
  margin-bottom: 16px;
  padding: 20px;
  border-radius: 16px;
  color: #fff;
  text-align: center;
}
.scan-result__title { font-size: 1.5rem; }
.scan-result--admitted { background: #1b7f4b; }
.scan-result--already_used { background: #b26a00; }
.scan-result--rejected { background: #b3261e; }
</style>
