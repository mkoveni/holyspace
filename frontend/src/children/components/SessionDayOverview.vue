<template>
  <section
    aria-label="Session day overview"
    class="session-day-overview"
  >
    <div class="directory-surface">
      <span>With the team</span>
      <strong>{{ view.occupancy }}</strong>
      <small>Children currently attending</small>
    </div>

    <div class="directory-surface">
      <span>Places available</span>

      <strong>
        {{ Math.max(0, view.effectiveCapacity - view.occupancy) }}
      </strong>

      <small>
        {{ view.effectiveCapacity }} safe capacity ·
        {{ view.session.seatLimit }} session seats
      </small>
    </div>

    <div class="directory-surface">
      <span>Team present</span>

      <strong>
        {{
          view.staff.filter((member) => member.present && member.issues.length === 0)
            .length
        }}
      </strong>

      <small>
        {{
          view.staff.some(
            (member) =>
              member.present &&
              member.role === 'LEADER' &&
              member.issues.length === 0,
          )
            ? 'Qualified leader present'
            : 'Qualified leader needed'
        }}
      </small>
    </div>

    <div class="directory-surface">
      <span>
        {{ !canStation ? 'Preparation access' : expected ? 'Preparation on this page' : 'Matching visits' }}
      </span>

      <strong>{{ !canStation ? '—' : expected ? ready : total }}</strong>

      <small>
        {{
          !canStation ? 'Station permission is needed to review children.' : expected
            ? `${ready} prepared · ${blocked} need attention`
            : 'Use search to find a child across the session'
        }}
      </small>
    </div>

    <v-alert
      v-if="arrivalMessage"
      class="session-day-overview__message"
      :type="blocker ? 'warning' : 'info'"
      variant="tonal"
    >
      {{ arrivalMessage }}
    </v-alert>
  </section>
</template>
<script setup lang="ts">
  import type { SessionView } from '../types'
  import { computed } from 'vue'
  import { arrivalBlocker } from '../arrivalAvailability'
  const props = defineProps<{
    view: SessionView
    total: number
    ready: number
    blocked: number
    expected: boolean
    canStation: boolean
    now: number
  }>()
  const blocker = computed(() => arrivalBlocker(props.view, props.now))
  const arrivalMessage = computed(() => blocker.value || 'Welcome each child, create their pickup label, then verify the authorized collector at handover.')

</script>
<style scoped>
  .session-day-overview {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin: 24px 0;
  }
  .session-day-overview > div {
    padding: 20px;
  }
  .session-day-overview span,
  .session-day-overview small {
    display: block;
    color: #718087;
    font-size: 0.75rem;
  }
  .session-day-overview strong {
    display: block;
    color: #26364c;
    font-size: 2rem;
    font-weight: 600;
    margin: 10px 0;
  }
  .session-day-overview__message {
    grid-column: 1 / -1;
  }
  @media (max-width: 900px) {
    .session-day-overview {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
