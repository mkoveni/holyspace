<template>
  <v-autocomplete
    v-model:search="search"
    clearable
    :error-messages="error"
    hide-details="auto"
    item-title="label"
    item-value="id"
    :items="items"
    :label="label || 'Find a person'"
    :loading="loading"
    :model-value="modelValue"
    no-filter
    placeholder="Search by name"
    @update:model-value="$emit('update:model-value', $event)"
  />
</template>
<script setup lang="ts">
  import type { AgeCategory, PersonSummary } from '../types'
  import axios from 'axios'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { directoryApi } from '../api'
  import { fullName } from '../presentation'
  const props = defineProps<{
    initialPerson?: PersonSummary
    modelValue: string | null
    label?: string
    exclude?: string[]
    ageCategory?: AgeCategory
  }>()
  const emit = defineEmits<{
    'update:model-value': [value: string | null]
    'selected': [person: PersonSummary | null]
  }>()
  const search = ref('')
  const results = ref<PersonSummary[]>([])
  const selected = ref<PersonSummary | null>(null)
  watch(
    [() => props.initialPerson, () => props.modelValue],
    ([person]) => {
      if (person && person.id === props.modelValue) selected.value = person
    },
    { immediate: true },
  )
  const loading = ref(false)
  const error = ref('')
  const items = computed(() =>
    [
      ...results.value,
      ...(selected.value
        && !results.value.some(p => p.id === selected.value?.id)
        ? [selected.value]
        : []),
    ]
      .filter(p => !props.exclude?.includes(p.id))
      .map(p => ({ id: p.id, label: fullName(p) })),
  )
  let controller: AbortController | null = null
  let timer: ReturnType<typeof setTimeout> | undefined
  watch(
    search,
    value => {
      clearTimeout(timer)
      controller?.abort()
      error.value = ''
      controller = new AbortController()
      const signal = controller.signal
      timer = setTimeout(async () => {
        loading.value = true
        try {
          const people = await directoryApi.people(
            {
              search: value || '',
              limit: 20,
              ...(props.ageCategory ? { ageCategory: props.ageCategory } : {}),
            },
            signal,
          )
          if (!signal.aborted) results.value = people
        } catch (error_) {
          if (!axios.isCancel(error_))
            error.value = extractErrorMessage(
              error_,
              'Unable to search people.',
            )
        } finally {
          if (!signal.aborted) loading.value = false
        }
      }, 250)
    },
    { immediate: true },
  )
  watch(
    () => props.modelValue,
    id => {
      selected.value = id
        ? (results.value.find(person => person.id === id)
          ?? (selected.value?.id === id ? selected.value : null))
        : null
      emit('selected', selected.value)
    },
  )
  onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
  })
</script>
