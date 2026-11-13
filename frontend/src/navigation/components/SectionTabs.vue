<template>
  <nav
    v-if="area && area.items.length > 1"
    :aria-label="`${area.title} sections`"
    class="section-tabs"
  >
    <v-tabs
      color="primary"
      :model-value="current"
      show-arrows
    >
      <v-tab
        v-for="item in area.items"
        :key="item.to"
        :to="item.to"
        :value="item.to"
      >
        {{ item.title }}
      </v-tab>
    </v-tabs>
  </nav>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { activeAreaItem, type NavigationArea } from '@/navigation/areas'

  const props = defineProps<{ area?: NavigationArea }>()
  const route = useRoute()
  const current = computed(() => (props.area ? activeAreaItem(props.area, route.path) : ''))
</script>

<style scoped>
  .section-tabs {
    margin-bottom: 20px;
    border-bottom: 1px solid var(--workspace-border);
  }
  .section-tabs :deep(.v-tabs) {
    padding: 0;
    background: transparent;
  }
</style>
