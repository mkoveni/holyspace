<template>
  <v-menu
    location="bottom end"
    :offset="10"
    @update:model-value="open => open && refresh(true)"
  >
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        aria-label="Things that need your attention"
        class="ml-1"
        icon
        variant="text"
      >
        <v-badge
          color="error"
          :content="count"
          :model-value="count > 0"
        >
          <v-icon icon="mdi-bell-outline" />
        </v-badge>
      </v-btn>
    </template>

    <v-list
      class="attention-menu"
      min-width="320"
      rounded="xl"
    >
      <v-list-subheader>NEEDS YOUR ATTENTION</v-list-subheader>

      <v-list-item
        v-for="item in items"
        :key="item.id"
        :subtitle="item.detail"
        :title="item.title"
        :to="item.to"
      >
        <template #prepend>
          <v-icon
            :color="item.tone"
            :icon="item.icon"
          />
        </template>
      </v-list-item>

      <v-list-item
        v-if="items.length === 0"
        prepend-icon="mdi-check-circle-outline"
        subtitle="Nothing is waiting for you right now."
        title="You’re all caught up"
      />
    </v-list>
  </v-menu>
</template>

<script setup lang="ts">
  import { onMounted } from 'vue'
  import { useAttention } from '@/composables/useAttention'

  const { items, count, refresh } = useAttention()
  onMounted(() => refresh())
</script>

<style scoped>
  .attention-menu {
    padding: 8px;
  }
</style>
