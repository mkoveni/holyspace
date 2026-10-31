<template>
  <v-autocomplete
    v-model="model"
    v-model:search="search"
    clearable
    item-title="displayName"
    item-value="id"
    :items="accounts"
    label="Workspace login (optional)"
    :loading="loading"
    no-filter
  />

  <p class="directory-muted mb-4">
    Link the appointed person's login only after verifying their identity.
    Leadership access also requires the group leader permission.
  </p>

  <v-alert
    v-if="error"
    type="error"
    variant="tonal"
  >
    {{ error }}
  </v-alert>
</template>
<script setup lang="ts">
  import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useApi } from '@/composables/useApi'
  import { errorMessage } from '../api'
  const model = defineModel<string | null>({ required: true })
  const search = ref(''),
        accounts = ref<{ id: string, displayName: string }[]>([]),
        loading = ref(false),
        error = ref('')
  let generation = 0,
      timer: ReturnType<typeof setTimeout> | undefined
  async function load () {
    const current = ++generation
    loading.value = true
    try {
      const result = (
        await useApi().get<{
          items: { id: string, displayName: string, status: string }[]
        }>('/api/users', { params: { search: search.value, limit: 25 } })
      ).data
      if (current === generation) {
        accounts.value = result.items.filter(a => a.status === 'ACTIVE')
        error.value = ''
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(load, 250)
  })
  onMounted(load)
  onBeforeUnmount(() => {
    clearTimeout(timer)
    generation++
  })
</script>
