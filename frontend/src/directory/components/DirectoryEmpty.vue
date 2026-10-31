<template>
  <div class="directory-empty">
    <div class="directory-empty__icon"><v-icon :icon="icon || 'mdi-account-group-outline'" size="32" /></div>
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>
    <slot />
  </div>
</template>
<script setup lang="ts">
  defineProps<{ title: string, description: string, icon?: string }>()
</script>
