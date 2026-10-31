<template>
  <v-autocomplete
    v-model="model"
    clearable
    :error-messages="workspace.error.value"
    item-title="name"
    item-value="id"
    :items="items"
    :label="label || 'Programme'"
    :loading="workspace.loading.value"
    no-filter
    @update:search="find"
  />
</template>
<script setup lang="ts">
  import type { Programme } from '../types'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { childrenApi } from '../api'
  import { useLoad } from '../useLoad'
  defineProps<{ label?: string }>()
  const model = defineModel<string | null>({ required: true })
  const search = ref('')
  const selected = ref<Programme | null>(null)
  const workspace = useLoad(
    () => childrenApi.programmes(search.value),
    [] as Programme[],
  )
  const items = computed(() =>
    selected.value
    && !workspace.data.value.some(value => value.id === selected.value?.id)
      ? [...workspace.data.value, selected.value]
      : workspace.data.value,
  )
  let timer: ReturnType<typeof setTimeout> | undefined
  function find (value: string) {
    clearTimeout(timer)
    search.value = value
    timer = setTimeout(() => {
      void workspace.load()
    }, 250)
  }
  watch(model, id => {
    selected.value = items.value.find(value => value.id === id) || null
  })
  void workspace.load()
  onBeforeUnmount(() => clearTimeout(timer))
</script>
