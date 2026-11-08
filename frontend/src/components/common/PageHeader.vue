<template>
  <header class="page-header">
    <div class="page-header__copy">
      <div class="page-header__eyebrow">{{ eyebrow }}</div>
      <h1>{{ title }}</h1>
      <p v-if="subtitle">{{ subtitle }}</p>
    </div>

    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script lang="ts" setup>
  withDefaults(defineProps<{ title: string, subtitle?: string, eyebrow?: string }>(), { eyebrow: 'Divine Community Space workspace' })
</script>
