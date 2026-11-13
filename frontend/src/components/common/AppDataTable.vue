<template>
  <v-data-table
    v-bind="$attrs"
    hover
    mobile-breakpoint="sm"
    :row-props="rowProps"
    @click:row="onRowClick"
  >
    <template
      v-for="(_, name) in $slots"
      #[name]="slotData"
    >
      <slot
        :name="name"
        v-bind="slotData ?? {}"
      />
    </template>

    <template
      v-if="!$slots['no-data']"
      #no-data
    >
      <EmptyState
        :icon="emptyIcon"
        :text="emptyText"
        :title="emptyTitle"
      />
    </template>
  </v-data-table>
</template>

<script setup lang="ts">
  import EmptyState from '@/components/common/EmptyState.vue'

  /**
   * The one table every list screen uses: hover rows, optional whole-row click,
   * a built-in empty state, and a stacked card layout on phones.
   */
  defineOptions({ inheritAttrs: false })
  const props = withDefaults(defineProps<{
    clickable?: boolean
    emptyTitle?: string
    emptyText?: string
    emptyIcon?: string
  }>(), {
    clickable: false,
    emptyTitle: 'Nothing to show yet',
    emptyText: '',
    emptyIcon: 'mdi-inbox-outline',
  })
  // Rows are whatever the parent passes as items, so the handler narrows the type itself.

  const emit = defineEmits<{ 'row-click': [item: any] }>()

  const rowProps = () => (props.clickable ? { class: 'row-clickable' } : {})

  function onRowClick (_event: Event, { item }: { item: unknown }) {
    if (props.clickable) emit('row-click', item)
  }
</script>
