<template>
  <v-chip :color="color" label size="small" variant="flat">
    {{ formatted }}
  </v-chip>
</template>

<script lang="ts" setup>
  import { computed } from 'vue'

  const props = defineProps<{
    status: string
  }>()

  const positiveStatuses = new Set(['active', 'enabled', 'approved'])
  const negativeStatuses = new Set(['inactive', 'disabled', 'cancelled', 'rejected', 'deceased'])
  const neutralStatuses = new Set(['visitor', 'pending'])

  const color = computed(() => {
    const value = props.status?.toLowerCase() ?? ''
    if (positiveStatuses.has(value)) return 'success'
    if (negativeStatuses.has(value)) return 'error'
    if (neutralStatuses.has(value)) return 'warning'
    return 'info'
  })

  const formatted = computed(() => {
    if (!props.status) return 'Unknown'
    return props.status.charAt(0).toUpperCase() + props.status.slice(1)
  })
</script>
