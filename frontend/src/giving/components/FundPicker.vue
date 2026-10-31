<template>
  <v-autocomplete
    v-model:search="search"
    clearable
    :error-messages="error"
    hide-details="auto"
    item-title="name"
    item-value="id"
    :items="items"
    :label="label || 'Fund'"
    :loading="loading"
    :model-value="modelValue"
    no-filter
    :rules="required ? [(value) => Boolean(value) || 'Choose a fund.'] : []"
    @update:model-value="$emit('update:model-value', $event)"
  >
    <template #append-item>
      <div
        v-if="total > results.length"
        class="pa-3 text-caption"
      >
        Type a fund name to narrow {{ total }} results.
      </div>
    </template>
  </v-autocomplete>
</template>
<script setup lang="ts">
  import type { Fund } from '../api'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { givingApi } from '../api'
  const props = defineProps<{
    modelValue: string | null
    label?: string
    archived?: boolean
    required?: boolean
  }>()
  defineEmits<{ 'update:model-value': [value: string | null] }>()
  const search = ref(''),
        results = ref<Fund[]>([]),
        selected = ref<Fund | null>(null),
        loading = ref(false),
        error = ref(''),
        total = ref(0)
  const items = computed(() =>
    selected.value
    && !results.value.some(row => row.id === selected.value?.id)
      ? [...results.value, selected.value]
      : results.value,
  )
  let generation = 0,
      timer: ReturnType<typeof setTimeout> | undefined
  async function load () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const result = await givingApi.funds({
        search: search.value || '',
        archived: props.archived || false,
        limit: 25,
      })
      if (current === generation) {
        results.value = result.items
        total.value = result.total
      }
    } catch (error_) {
      if (current === generation)
        error.value = extractErrorMessage(error_, 'Unable to load funds.')
    } finally {
      if (current === generation) loading.value = false
    }
  }
  watch(
    search,
    () => {
      clearTimeout(timer)
      timer = setTimeout(load, 250)
    },
    { immediate: true },
  )
  watch(
    () => props.modelValue,
    id => {
      selected.value
        = results.value.find(row => row.id === id)
          ?? (selected.value?.id === id ? selected.value : null)
    },
  )
  onBeforeUnmount(() => {
    generation++
    clearTimeout(timer)
  })
</script>
