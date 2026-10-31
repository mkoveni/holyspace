<template>
  <v-autocomplete
    v-model:search="query"
    v-bind="$attrs"
    :chips="multiple"
    :closable-chips="multiple && !disabled"
    :disabled="disabled"
    :error-messages="error || undefined"
    :hint="hasMore ? 'More matches available. Keep typing to narrow your search.' : 'Type at least 2 characters to search.'"
    item-title="title"
    item-value="value"
    :items="items"
    :label="label"
    :loading="loading"
    :model-value="selection"
    :multiple="multiple"
    no-filter
    persistent-hint
    placeholder="Search by name, email, or phone"
    @update:model-value="$emit('update:model-value', $event)"
  >
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps" :subtitle="item.raw.subtitle" />
    </template>

    <template v-if="error" #append-inner>
      <v-btn size="small" variant="text" @click.stop="retry">Retry</v-btn>
    </template>

    <template #no-data>
      <v-list-item :title="loading ? 'Loading people…' : error ? 'People could not be loaded.' : query.trim().length < 2 ? 'Type at least 2 characters to search.' : 'No matching people found.'">
        <template v-if="error" #append><v-btn size="small" variant="text" @click="retry">Retry</v-btn></template>
      </v-list-item>
    </template>
  </v-autocomplete>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { personSearchItem } from '@/components/common/peopleSearch'
  import { usePersonAutocomplete } from '@/composables/usePersonAutocomplete'

  defineOptions({ inheritAttrs: false })
  const props = withDefaults(defineProps<{
    modelValue?: unknown
    label?: string
    multiple?: boolean
    disabled?: boolean
    excludeIds?: string[]
  }>(), { label: 'Person', multiple: false, disabled: false, excludeIds: () => [] })
  defineEmits<{ 'update:model-value': [value: string | string[] | null] }>()
  const selection = computed(() => props.multiple
    ? (Array.isArray(props.modelValue) ? props.modelValue : [])
    : (typeof props.modelValue === 'string' ? props.modelValue : null))
  const selectedIds = computed(() => Array.isArray(selection.value) ? selection.value : (selection.value ? [selection.value] : []))
  const { query, people, loading, error, hasMore, retry } = usePersonAutocomplete(selectedIds, computed(() => props.excludeIds))
  const items = computed(() => people.value.map(person => personSearchItem(person)))
</script>
