<template>
  <section :aria-busy="working" :aria-label="label" class="image-uploader">
    <div class="image-uploader__heading">
      <div><span class="image-uploader__eyebrow">IMAGE STUDIO</span><h3>{{ label }}</h3></div>
      <span class="image-uploader__dimensions">{{ width }} × {{ height }} px</span>
    </div>

    <div
      class="image-uploader__dropzone"
      :class="{ 'is-dragging': dragging }"
      @dragleave.prevent="dragging = false"
      @dragover.prevent="dragging = !working && !disabled"
      @drop.prevent="drop"
    >
      <template v-if="!bitmap">
        <img
          v-if="src"
          :alt="label"
          class="image-uploader__existing"
          :src="src"
          :style="{ aspectRatio: `${width}/${height}` }"
        >

        <div v-else class="image-uploader__placeholder"><v-icon icon="mdi-image-plus-outline" size="36" /><strong>A perfect fit starts here</strong><span>Drop your image here or choose a file</span></div>

        <v-btn
          color="primary"
          :disabled="disabled || working"
          prepend-icon="mdi-upload"
          variant="tonal"
          @click="input?.click()"
        >{{ src ? 'Choose a replacement' : 'Choose an image' }}</v-btn>

        <p class="image-uploader__hint">JPEG, PNG or WebP · up to {{ Math.round(maxBytes / 1024 / 1024 * 10) / 10 }} MB</p>
      </template>

      <template v-else>
        <div class="image-uploader__crop" :style="{ aspectRatio: `${width}/${height}`, maxWidth: `${360 * width / height}px` }">
          <canvas
            ref="canvas"
            :aria-label="`Crop preview for ${label}`"
            :height="height"
            :width="width"
            @lostpointercapture="pointer = null"
            @pointercancel="endDrag"
            @pointerdown="startDrag"
            @pointermove="moveDrag"
            @pointerup="endDrag"
          />

          <div aria-hidden="true" class="image-uploader__grid" />
        </div>

        <div class="image-uploader__crop-note"><v-icon icon="mdi-cursor-move" size="16" /> Drag to frame your image, or use the controls below</div>
      </template>

      <input
        ref="input"
        accept="image/jpeg,image/png,image/webp"
        aria-label="Choose an image file"
        class="image-uploader__input"
        :disabled="disabled || working"
        type="file"
        @change="select"
      >
    </div>

    <div v-if="bitmap" class="image-uploader__editor">
      <div class="image-uploader__file"><v-icon icon="mdi-file-image-outline" /><span>{{ filename }}</span><v-btn :disabled="working || disabled" size="small" variant="text" @click="input?.click()">Change</v-btn></div>

      <v-slider
        v-model="crop.zoom"
        color="primary"
        :disabled="working || disabled"
        hide-details
        label="Zoom"
        :max="4"
        :min="1"
        name="Zoom"
        :step="0.01"
        thumb-label
      />

      <v-slider
        v-model="crop.x"
        color="primary"
        :disabled="working || disabled"
        hide-details
        label="Horizontal position"
        :max="100"
        :min="0"
        name="Horizontal position"
        :step="0.1"
      />

      <v-slider
        v-model="crop.y"
        color="primary"
        :disabled="working || disabled"
        hide-details
        label="Vertical position"
        :max="100"
        :min="0"
        name="Vertical position"
        :step="0.1"
      />

      <div class="image-uploader__actions">
        <v-btn :disabled="working || disabled" variant="text" @click="reset">Reset crop</v-btn>
        <v-spacer />
        <v-btn :disabled="working || disabled" variant="text" @click="cancel">Cancel</v-btn>

        <v-btn
          color="primary"
          :disabled="disabled"
          :loading="working"
          prepend-icon="mdi-check"
          @click="save"
        >{{ upload ? 'Save image' : 'Use image' }}</v-btn>
      </div>
    </div>

    <v-alert
      v-if="error"
      class="mt-3"
      density="compact"
      role="alert"
      type="error"
      variant="tonal"
    >{{ error }}</v-alert>
  </section>
</template>
<script setup lang="ts">
  import { computed, nextTick, onBeforeUnmount, reactive, ref, shallowRef, watch } from 'vue'
  import { cropRectangle, validateSize } from './image-uploader/crop'

  const props = withDefaults(defineProps<{
    width: number
    height: number
    label?: string
    src?: string
    disabled?: boolean
    maxBytes?: number
    maxDimension?: number
    upload?: (file: File) => Promise<void>
  }>(), { label: 'Upload image', src: '', disabled: false, maxBytes: 5 * 1024 * 1024, maxDimension: 4096 })
  const emit = defineEmits<{ cropped: [file: File], cancel: [] }>()
  const input = ref<HTMLInputElement>()
  const canvas = ref<HTMLCanvasElement>()
  const bitmap = shallowRef<ImageBitmap | null>(null)
  const filename = ref('')
  const crop = reactive({ zoom: 1, x: 50, y: 50 })
  const dragging = ref(false)
  const decoding = ref(false)
  const saving = ref(false)
  const working = computed(() => decoding.value || saving.value)
  const error = ref('')
  let generation = 0
  let pointer: { id: number, x: number, y: number } | null = null

  function reset () {
    Object.assign(crop, { zoom: 1, x: 50, y: 50 })
    pointer = null
  }
  function clearSelection () {
    ++generation
    bitmap.value?.close()
    bitmap.value = null
    decoding.value = false
    filename.value = ''
    error.value = ''
    if (input.value) input.value.value = ''
    reset()
  }
  function cancel () {
    clearSelection()
    emit('cancel')
  }
  function render () {
    if (!bitmap.value || !canvas.value) return
    try {
      const rect = cropRectangle(bitmap.value, props, crop)
      const context = canvas.value.getContext('2d')
      if (!context) throw new Error('Image editing is unavailable in this browser.')
      context.clearRect(0, 0, props.width, props.height)
      context.drawImage(bitmap.value, rect.x, rect.y, rect.width, rect.height, 0, 0, props.width, props.height)
    } catch (error_) {
      error.value = error_ instanceof Error ? error_.message : 'Unable to preview the crop.'
    }
  }
  async function choose (file?: File) {
    if (!file || working.value || props.disabled) return
    const current = ++generation
    error.value = ''
    decoding.value = true
    try {
      validateSize(props)
      if (props.width > props.maxDimension || props.height > props.maxDimension) throw new Error(`This upload supports output dimensions up to ${props.maxDimension} pixels per side.`)
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) throw new Error('Choose a JPEG, PNG or WebP image.')
      if (file.size === 0 || file.size > props.maxBytes) throw new Error('This file exceeds the upload size limit or is empty.')
      const decoded = await createImageBitmap(file, { imageOrientation: 'from-image' })
      if (current !== generation) {
        decoded.close()
        return
      }
      if (decoded.width > 12_000 || decoded.height > 12_000 || decoded.width * decoded.height > 16_000_000) {
        decoded.close()
        throw new Error('Choose an image with at most 16 million pixels and sides under 12,000 pixels.')
      }
      bitmap.value?.close()
      bitmap.value = decoded
      filename.value = file.name
      reset()
      await nextTick()
      render()
    } catch (error_) {
      if (current === generation) error.value = error_ instanceof Error ? error_.message : 'This image could not be opened. Try another file.'
    } finally {
      if (current === generation) decoding.value = false
    }
  }
  function select (event: Event) {
    const element = event.target as HTMLInputElement
    const selected = element.files?.[0]
    element.value = ''
    void choose(selected)
  }
  function drop (event: DragEvent) {
    dragging.value = false
    void choose(event.dataTransfer?.files[0])
  }
  function startDrag (event: PointerEvent) {
    if (working.value || props.disabled) return
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY }
    canvas.value?.setPointerCapture(event.pointerId)
  }
  function moveDrag (event: PointerEvent) {
    if (!pointer || pointer.id !== event.pointerId || !bitmap.value || !canvas.value || working.value || props.disabled) return
    const rect = cropRectangle(bitmap.value, props, crop)
    const bounds = canvas.value.getBoundingClientRect()
    const overflowX = bitmap.value.width - rect.width
    const overflowY = bitmap.value.height - rect.height
    if (overflowX > 0 && bounds.width > 0) crop.x = Math.min(100, Math.max(0, crop.x - (event.clientX - pointer.x) * rect.width / bounds.width / overflowX * 100))
    if (overflowY > 0 && bounds.height > 0) crop.y = Math.min(100, Math.max(0, crop.y - (event.clientY - pointer.y) * rect.height / bounds.height / overflowY * 100))
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY }
  }
  function endDrag (event: PointerEvent) {
    if (pointer?.id !== event.pointerId) return
    pointer = null
    if (canvas.value?.hasPointerCapture(event.pointerId)) canvas.value.releasePointerCapture(event.pointerId)
  }
  async function save () {
    if (!canvas.value || !bitmap.value || working.value || props.disabled) return
    saving.value = true
    error.value = ''
    const current = generation
    try {
      render()
      if (error.value) return
      const blob = await new Promise<Blob>((resolve, reject) => canvas.value!.toBlob(value => value ? resolve(value) : reject(new Error('Unable to prepare this image.')), 'image/png'))
      if (current !== generation) return
      if (blob.size > props.maxBytes) throw new Error('The cropped image exceeds the upload limit. Choose a smaller output size or a simpler image.')
      const file = new File([blob], `${filename.value.replace(/\.[^.]+$/, '') || 'image'}-${props.width}x${props.height}.png`, { type: 'image/png' })
      await props.upload?.(file)
      if (current !== generation) return
      emit('cropped', file)
      clearSelection()
    } catch (error_) {
      if (current === generation) error.value = error_ instanceof Error ? error_.message : 'Unable to save the image. Your crop is ready to retry.'
    } finally {
      saving.value = false
    }
  }
  watch(crop, render)
  watch(() => [props.width, props.height], () => {
    cancel()
  })
  onBeforeUnmount(() => {
    ++generation
    bitmap.value?.close()
  })
</script>
<style scoped>
.image-uploader { border: 1px solid rgb(var(--v-theme-on-surface), .1); border-radius: 20px; padding: 20px; background: rgb(var(--v-theme-surface)); }
.image-uploader__heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.image-uploader__heading h3 { font-size: 1rem; font-weight: 650; margin-top: 4px; }
.image-uploader__eyebrow { font-size: .62rem; letter-spacing: .18em; font-weight: 750; color: rgb(var(--v-theme-primary)); }
.image-uploader__dimensions { border-radius: 8px; padding: 6px 10px; font-size: .75rem; white-space: nowrap; background: rgb(var(--v-theme-primary), .08); color: rgb(var(--v-theme-primary)); }
.image-uploader__dropzone { padding: 20px; text-align: center; border: 1px dashed rgb(var(--v-theme-on-surface), .2); border-radius: 14px; background: rgb(var(--v-theme-primary), .025); transition: border-color .2s, background .2s; }
.image-uploader__dropzone.is-dragging { border-color: rgb(var(--v-theme-primary)); background: rgb(var(--v-theme-primary), .1); }
.image-uploader__placeholder { display: grid; justify-items: center; gap: 10px; padding: 18px 8px 22px; }
.image-uploader__placeholder span, .image-uploader__hint, .image-uploader__crop-note { font-size: .75rem; color: rgb(var(--v-theme-on-surface), .6); }
.image-uploader__hint { margin-top: 12px; }
.image-uploader__existing { display: block; width: 100%; max-height: 320px; object-fit: contain; margin-bottom: 18px; border-radius: 10px; }
.image-uploader__crop { margin-inline: auto; position: relative; overflow: hidden; border-radius: 10px; background: repeating-conic-gradient(#e4e7eb 0% 25%, #f5f6f8 0% 50%) 50% / 16px 16px; }
.image-uploader__crop canvas { width: 100%; height: 100%; display: block; cursor: grab; touch-action: none; }
.image-uploader__crop canvas:active { cursor: grabbing; }
.image-uploader__grid { position: absolute; inset: 0; pointer-events: none; background: linear-gradient(to right, transparent 33.1%, #ffffff66 33.1%, #ffffff66 33.4%, transparent 33.4%, transparent 66.4%, #ffffff66 66.4%, #ffffff66 66.7%, transparent 66.7%), linear-gradient(to bottom, transparent 33.1%, #ffffff66 33.1%, #ffffff66 33.4%, transparent 33.4%, transparent 66.4%, #ffffff66 66.4%, #ffffff66 66.7%, transparent 66.7%); }
.image-uploader__crop-note { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 12px; }
.image-uploader__editor { display: grid; gap: 12px; padding-top: 14px; }
.image-uploader__file { display: flex; align-items: center; gap: 8px; font-size: .8rem; }
.image-uploader__file span { flex: 1; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.image-uploader__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; border-top: 1px solid rgb(var(--v-theme-on-surface), .08); padding-top: 14px; }
.image-uploader__input { display: none; }
@media (max-width: 600px) { .image-uploader { padding: 14px; } .image-uploader__dropzone { padding: 12px; } .image-uploader__heading { gap: 8px; } }
</style>
