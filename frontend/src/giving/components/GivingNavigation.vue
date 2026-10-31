<template>
  <v-select
    class="d-sm-none mb-4 giving-no-print"
    hide-details
    :items="items"
    label="Giving workspace"
    :model-value="current"
    @update:model-value="navigate"
  />

  <nav
    aria-label="Giving sections"
    class="d-none d-sm-flex giving-navigation giving-no-print"
  >
    <v-btn
      v-for="item in items"
      :key="item.value"
      :to="item.value"
      :variant="current === item.value ? 'tonal' : 'text'"
    >
      {{ item.title }}
    </v-btn>
  </nav>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  const route = useRoute(),
        router = useRouter()
  const items = [
    { title: 'Gift ledger & funds', value: '/giving' },
    { title: 'Pledges', value: '/giving/pledges' },
    { title: 'Statements & exports', value: '/giving/reports' },
    { title: 'Reconciliation', value: '/giving/reconciliations' },
  ]
  const current = computed(
    () =>
      items.find(
        item => item.value !== '/giving' && route.path.startsWith(item.value),
      )?.value || '/giving',
  )
  function navigate (value: string) {
    router.push(value)
  }
</script>
