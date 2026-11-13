<template>
  <v-menu
    v-if="actions.length > 0"
    location="bottom end"
    :max-width="380"
    :offset="10"
  >
    <template #activator="{ props }">
      <v-btn
        v-bind="props"
        aria-label="Quick actions"
        class="quick-actions-trigger"
        color="primary"
        :icon="compact"
        :prepend-icon="compact ? undefined : 'mdi-plus'"
        variant="flat"
      >
        <v-icon
          v-if="compact"
          icon="mdi-plus"
        />

        <template v-else>
          Quick actions
          <v-icon
            class="ml-2"
            icon="mdi-chevron-down"
            size="16"
          />
        </template>
      </v-btn>
    </template>

    <v-list
      aria-label="Quick actions"
      class="quick-actions-menu"
      elevation="8"
      rounded="xl"
    >
      <v-list-subheader>MAKE A START</v-list-subheader>

      <v-list-item
        v-for="action in actions"
        :key="action.to"
        :prepend-icon="action.icon"
        :subtitle="action.description"
        :title="action.title"
        :to="action.to"
      />
    </v-list>
  </v-menu>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { useDisplay } from 'vuetify'
  import { useAuthStore } from '@/stores/auth'
  import { visibleQuickActions } from '../quickActions'
  const auth = useAuthStore(),
        { smAndDown: compact } = useDisplay()
  const actions = computed(() =>
    visibleQuickActions(permission => auth.hasPermission(permission)),
  )
</script>
<style scoped>
  .quick-actions-trigger {
    border-radius: 12px;
    text-transform: none;
    letter-spacing: 0;
    font-weight: 600;
    box-shadow: none;
  }
  .quick-actions-menu {
    padding: 10px;
    border: 1px solid var(--workspace-border);
  }
  .quick-actions-menu :deep(.v-list-subheader) {
    min-height: 32px;
    font-size: 11px;
    letter-spacing: 1.6px;
    font-weight: 700;
    color: var(--workspace-muted);
  }
  .quick-actions-menu :deep(.v-list-item) {
    border-radius: 12px;
    min-height: 66px;
  }
  .quick-actions-menu :deep(.v-list-item-title) {
    font-size: 14px;
    font-weight: 600;
    color: var(--workspace-ink);
  }
  .quick-actions-menu :deep(.v-list-item-subtitle) {
    font-size: 12px;
    margin-top: 4px;
    white-space: normal;
  }
  .quick-actions-menu :deep(.v-icon) {
    color: var(--workspace-blue);
    font-size: 22px;
  }
</style>
