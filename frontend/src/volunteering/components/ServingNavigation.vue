<template>
  <nav
    aria-label="Volunteer management"
    class="serving-navigation"
  >
    <router-link
      v-for="item in items"
      :key="item.to"
      :aria-current="route.path === item.to ? 'page' : undefined"
      :class="{ active: route.path === item.to }"
      :to="item.to"
    >
      <v-icon
        :icon="item.icon"
        size="18"
      />
      {{ item.label }}
    </router-link>
  </nav>
</template>
<script setup lang="ts">
  import { computed } from 'vue'
  import { useRoute } from 'vue-router'
  import { useAuthStore } from '@/stores/auth'
  const route = useRoute(),
        auth = useAuthStore()
  const items = computed(() => [
    ...(auth.hasPermission('volunteering.read')
      ? [
        {
          to: '/volunteering',
          label: 'Volunteers',
          icon: 'mdi-account-heart-outline',
        },
        {
          to: '/volunteering/teams',
          label: 'Teams & roles',
          icon: 'mdi-account-group-outline',
        },
        {
          to: '/volunteering/schedule',
          label: 'Schedule',
          icon: 'mdi-calendar-heart',
        },
      ]
      : []),
    ...(auth.hasPermission('volunteering.communications.send')
      ? [
        {
          to: '/volunteering/deliveries',
          label: 'Reminder deliveries',
          icon: 'mdi-email-check-outline',
        },
      ]
      : []),
    ...(auth.hasPermission('volunteering.serve')
      ? [
        {
          to: '/serve',
          label: 'My invitations',
          icon: 'mdi-inbox-arrow-down-outline',
        },
      ]
      : []),
  ])
</script>
<style scoped>
  .serving-navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 26px;
    padding-bottom: 14px;
    border-bottom: 1px solid #e2e8e0;
  }
  .serving-navigation a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 14px;
    border-radius: 12px;
    font-size: 13px;
    color: #7a8977;
    font-weight: 600;
  }
  .serving-navigation a.active {
    background: #e8efe4;
    color: #416044;
  }
  @media (max-width: 600px) {
    .serving-navigation {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 5px;
    }
    .serving-navigation a {
      font-size: 12px;
      padding: 10px;
    }
  }
</style>
