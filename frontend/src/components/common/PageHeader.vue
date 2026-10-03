<template>
  <div class="d-flex flex-wrap align-center justify-space-between mb-6 page-header">
    <div>
      <h1 class="font-heading text-h5 font-weight-bold text-secondary">{{ title }}</h1>
      <p v-if="subtitle" class="text-body-2 text-medium-emphasis mb-0">{{ subtitle }}</p>
    </div>

    <div class="d-flex ga-2 mt-2 mt-sm-0">
      <slot name="actions" />
    </div>
  </div>
</template>

<script lang="ts" setup>
  defineProps<{
    title: string
    subtitle?: string
  }>()
</script>
