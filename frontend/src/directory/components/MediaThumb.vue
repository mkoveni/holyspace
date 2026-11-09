<template>
  <img v-if="source" :alt="alt" class="media-thumb" :src="source">
  <slot v-else />
</template>

<script setup lang="ts">
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'

  const props = defineProps<{ target: 'person' | 'family', ownerId: string, alt: string, variant: number }>()
  const auth = useAuthStore()
  const source = ref('')
  let generation = 0

  // Thumbnails are personal data: fetched with the session, shown from an object URL kept for the session.
  const cache = new Map<string, string>()

  async function fetchBlob (path: string, variant?: number) {
    const query = variant ? `?variant=${variant}` : ''
    return (await useApi().get<Blob>(`${path}/content${query}`, { responseType: 'blob' })).data
  }

  async function load () {
    const current = ++generation
    source.value = ''
    if (!auth.token || !props.ownerId) return
    const key = `${props.target}:${props.ownerId}:${props.variant}`
    const cached = cache.get(key)
    if (cached) {
      source.value = cached
      return
    }
    try {
      const path = `/api/media/${props.target}/${encodeURIComponent(props.ownerId)}`
      const { data } = await useApi().get<{ image: unknown }>(path)
      if (!data.image || current !== generation) return
      let blob: Blob
      try {
        blob = await fetchBlob(path, props.variant)
      } catch {
        blob = await fetchBlob(path)
      }
      if (current !== generation) return
      const url = URL.createObjectURL(blob)
      cache.set(key, url)
      source.value = url
    } catch {
      // No photo, or no permission to see it: the fallback shows.
    }
  }

  watch(() => [props.target, props.ownerId, props.variant, auth.token], () => void load(), { immediate: true })
  onBeforeUnmount(() => ++generation)
</script>

<style scoped>
.media-thumb { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
