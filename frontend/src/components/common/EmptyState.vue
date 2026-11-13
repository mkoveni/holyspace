<template>
  <div class="empty-state">
    <div class="empty-state__icon">
      <v-icon
        :icon="icon"
        size="28"
      />
    </div>

    <h3>{{ title }}</h3>
    <p v-if="text">{{ text }}</p>

    <div
      v-if="$slots.default"
      class="d-flex ga-2 flex-wrap justify-center"
    >
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
  withDefaults(defineProps<{ title: string, text?: string, icon?: string }>(), {
    icon: 'mdi-inbox-outline',
  })
</script>
