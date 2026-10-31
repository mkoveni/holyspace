<template>
  <nav
    aria-label="Events workspace"
    class="event-nav"
  >
    <v-btn
      :active="$route.path === '/events'"
      prepend-icon="mdi-calendar-star"
      to="/events"
      variant="tonal"
    >
      Calendar & events
    </v-btn>

    <v-btn
      :active="$route.path === '/events/venues'"
      prepend-icon="mdi-map-marker-outline"
      to="/events/venues"
      variant="tonal"
    >
      Venues
    </v-btn>

    <v-btn
      v-if="auth.hasPermission('events.attendance.read')"
      :active="$route.path === '/events/reports'"
      prepend-icon="mdi-chart-timeline-variant"
      to="/events/reports"
      variant="tonal"
    >
      Attendance reports
    </v-btn>

    <v-btn
      v-if="auth.hasPermission('events.communications.send')"
      :active="$route.path === '/events/email-deliveries'"
      prepend-icon="mdi-email-check-outline"
      to="/events/email-deliveries"
      variant="tonal"
    >
      Email deliveries
    </v-btn>
  </nav>
</template>

<script setup lang="ts">
  import { useAuthStore } from '@/stores/auth'
  const auth = useAuthStore()
</script>
