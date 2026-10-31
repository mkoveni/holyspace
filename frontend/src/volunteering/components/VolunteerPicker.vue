<template>
  <v-autocomplete
    v-model="value"
    v-model:search="search"
    clearable
    :error-messages="error"
    item-title="name"
    item-value="id"
    :items="items"
    :label="label"
    :loading="loading"
    no-filter
    :rules="[required]"
  />
</template>
<script setup lang="ts">
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { required, servingApi, servingError } from '../api'
  const value = defineModel<string>({ required: true })
  withDefaults(defineProps<{ label?: string }>(), { label: 'Volunteer *' })
  const search = ref(''),
        items = ref<{ id: string, name: string }[]>([]),
        loading = ref(false),
        error = ref('')
  let timer: ReturnType<typeof setTimeout>,
      request = 0
  watch(
    search,
    text => {
      clearTimeout(timer)
      timer = setTimeout(() => load(text || ''), 250)
    },
    { immediate: true },
  )
  async function load (text: string) {
    const current = ++request
    loading.value = true
    error.value = ''
    try {
      const result = await servingApi.volunteers(text)
      if (current === request)
        items.value = result.volunteers
          .filter(p => p.volunteer.status === 'ACTIVE')
          .map(p => ({ id: p.volunteer.id, name: p.name }))
    } catch (error_) {
      if (current === request) error.value = servingError(error_)
    } finally {
      if (current === request) loading.value = false
    }
  }
  onBeforeUnmount(() => {
    request++
    clearTimeout(timer)
  })
</script>
