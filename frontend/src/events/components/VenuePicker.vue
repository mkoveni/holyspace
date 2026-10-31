<template>
  <v-autocomplete
    v-model="model"
    v-model:search="search"
    clearable
    item-title="name"
    item-value="id"
    :items="venues"
    label="Venue"
    :loading="loading"
    no-filter
    :rules="[required]"
  />

  <v-alert
    v-if="error"
    type="error"
    variant="tonal"
  >
    {{ error }}
  </v-alert>

  <p class="directory-muted mb-4">
    Missing a venue?
    <RouterLink to="/events/venues">Manage venues</RouterLink>
  </p>
</template>
<script setup lang="ts">
  import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { errorMessage, eventApi, required, type Venue } from '../api'
  const model = defineModel<string | null>({ required: true })
  const search = ref(''),
        venues = ref<Venue[]>([]),
        loading = ref(false),
        error = ref('')
  let timer: ReturnType<typeof setTimeout> | undefined
  let generation = 0
  async function load () {
    const current = ++generation
    loading.value = true
    try {
      const page = await eventApi.venues(search.value || '', 0, false, 100)
      if (current === generation) venues.value = page.venues
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
    generation++
    clearTimeout(timer)
  })
</script>
