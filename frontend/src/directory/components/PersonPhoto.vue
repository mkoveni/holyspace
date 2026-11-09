<template>
  <span class="directory-avatar directory-avatar--large person-photo">
    <img v-if="source" :alt="`Photo of ${name}`" class="person-photo__image" :src="source">
    <template v-else>{{ initials }}</template>
  </span>
</template>

<script setup lang="ts">
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  const props = defineProps<{ personId: string, initials: string, name: string }>()
  const auth = useAuthStore()
  const source = ref('')
  let generation = 0

  function clear () {
    if (source.value) URL.revokeObjectURL(source.value)
    source.value = ''
  }

  /** The photo is personal data: it is fetched with the session and shown from a local object URL. */
  async function load () {
    const current = ++generation
    clear()
    if (!auth.token) return
    try {
      const base = `/api/media/person/${encodeURIComponent(props.personId)}`
      const { data } = await useApi().get<{ image: unknown }>(base)
      if (!data.image || current !== generation) return
      const blob = (await useApi().get<Blob>(`${base}/content`, { responseType: 'blob' })).data
      if (current === generation) source.value = URL.createObjectURL(blob)
    } catch {
      // A missing photo or no permission to see it leaves the initials in place.
    }
  }

  watch(() => props.personId, () => void load(), { immediate: true })
  defineExpose({ reload: load })
  onBeforeUnmount(() => {
    ++generation
    clear()
  })
</script>

<style scoped>
.person-photo { overflow: hidden; }
.person-photo__image { width: 100%; height: 100%; object-fit: cover; }
</style>
