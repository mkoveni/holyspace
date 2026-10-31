<template>
  <section v-show="preview || editable || error || target === 'person'" :aria-label="label" class="portal-image" :class="{ 'portal-image--portrait': target === 'person' }">
    <div v-if="!editable && (preview || target === 'person')" class="portal-image__preview" :style="{ aspectRatio: `${dimensions.width}/${dimensions.height}` }">
      <img v-if="preview" :alt="label" :src="preview">
      <div v-else class="portal-image__empty"><v-icon icon="mdi-image-outline" size="40" /><span>{{ label }}</span></div>
      <v-progress-linear v-if="loading || busy" class="portal-image__progress" color="primary" indeterminate />
    </div>

    <div v-if="editable" class="portal-image__controls">
      <ImageUploader
        :key="`${target}:${ownerId}`"
        :disabled="busy || loading"
        :height="dimensions.height"
        :label="label"
        :max-dimension="1920"
        :src="source"
        :upload="upload"
        :width="dimensions.width"
      />

      <v-btn
        v-if="source"
        color="error"
        :disabled="busy || loading"
        size="small"
        variant="text"
        @click="remove"
      >Remove image</v-btn>
    </div>

    <v-alert
      v-if="error"
      class="mt-3"
      density="compact"
      type="error"
      variant="tonal"
    >{{ error }} <v-btn v-if="!busy" size="small" variant="text" @click="load">Reload image</v-btn></v-alert>
  </section>
</template>
<script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { imagePresets } from './image-uploader/crop'
  import ImageUploader from './ImageUploader.vue'
  const props = defineProps<{ target: 'person' | 'family' | 'event' | 'form', ownerId?: string, label: string, editable?: boolean, publicToken?: string, width?: number, height?: number }>()
  const dimensions = computed(() => ({ width: props.width ?? imagePresets[props.target].width, height: props.height ?? imagePresets[props.target].height }))
  const auth = useAuthStore()
  const source = ref('')
  const preview = computed(() => source.value)
  const version = ref(0)
  const busy = ref(false)
  const loading = ref(false)
  const error = ref('')
  let generation = 0
  const base = () => `/api/media/${props.target}/${encodeURIComponent(props.ownerId ?? '')}`
  function clearSource () {
    if (source.value) URL.revokeObjectURL(source.value)
    source.value = ''
  }
  async function load () {
    const current = ++generation
    clearSource()
    error.value = ''
    loading.value = true
    try {
      let blob: Blob
      if (props.publicToken) {
        const response = await fetch(`/api/public/forms/${encodeURIComponent(props.publicToken)}/banner`, { cache: 'no-store', credentials: 'omit' })
        if (response.status === 404) return
        if (!response.ok) throw new Error('Unable to load the form banner.')
        blob = await response.blob()
      } else {
        if (!props.ownerId || !auth.token) return
        const { data } = await useApi().get<{ version: number, image: unknown }>(base())
        if (current !== generation) return
        version.value = data.version
        if (!data.image) return
        blob = (await useApi().get<Blob>(`${base()}/content`, { responseType: 'blob' })).data
      }
      if (current === generation) source.value = URL.createObjectURL(blob)
    } catch (error_) {
      if (current === generation) error.value = extractErrorMessage(error_, 'Unable to load the image.')
    } finally {
      if (current === generation) loading.value = false
    }
  }
  async function change (selected?: File) {
    if (busy.value || loading.value) return
    const current = generation
    busy.value = true
    error.value = ''
    try {
      const headers = { 'X-Image-Version': String(version.value) }
      if (selected) {
        if (selected.size > 5 * 1024 * 1024) throw new Error('Choose an image of at most 5 MB.')
        const body = new FormData()
        body.append('image', selected)
        await useApi().post(base(), body, { headers: { ...headers, 'Content-Type': 'multipart/form-data' } })
      } else await useApi().delete(base(), { headers })
      if (current === generation) await load()
    } catch (error_) {
      if (selected) throw new Error(extractErrorMessage(error_, 'Unable to save the image.'))
      if (current === generation) error.value = extractErrorMessage(error_, 'Unable to save the image.')
    } finally {
      busy.value = false
    }
  }
  const upload = (file: File) => change(file)
  const remove = () => change()
  watch(() => [props.target, props.ownerId, props.publicToken, auth.token], () => {
    void load()
  }, { immediate: true })
  onBeforeUnmount(() => {
    ++generation
    clearSource()
  })
</script>
<style scoped>
.portal-image { margin-bottom: 24px; overflow: hidden; }
.portal-image__preview { position: relative; aspect-ratio: 16 / 9; max-height: 360px; overflow: hidden; border-radius: 16px; background: rgb(var(--v-theme-surface-variant), .15); }
.portal-image__preview img { display: block; width: 100%; height: 100%; object-fit: contain; }
.portal-image--portrait .portal-image__preview { aspect-ratio: 1; max-width: 240px; margin-inline: auto; border-radius: 24px; }
.portal-image__empty { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: rgb(var(--v-theme-on-surface), .55); }
.portal-image__controls { text-align: left; display: grid; gap: 16px; padding-top: 16px; }
.portal-image__progress { position: absolute; bottom: 0; }
</style>
