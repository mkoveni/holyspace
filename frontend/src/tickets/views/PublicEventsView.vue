<template>
  <main class="tk-page">
    <div class="tk-shell">
      <PublicBrand />

      <div v-if="loading" aria-label="Loading" role="status">
        <v-skeleton-loader type="article, article" />
      </div>

      <section v-else-if="events.length === 0" class="tk-card text-center">
        <v-icon color="primary" icon="mdi-ticket-outline" size="40" />
        <h1>No tickets on sale right now</h1>
        <p role="alert">{{ error || 'Please check back soon.' }}</p>
      </section>

      <template v-else>
        <div class="tk-intro">
          <h1>Upcoming events</h1>
          <p>Reserve your place. Tickets are sent to your email.</p>
        </div>

        <div class="tk-grid">
          <router-link
            v-for="e in events"
            :key="e.occurrenceId"
            class="tk-event"
            :to="{ name: 'public-tickets-event', params: { id: e.occurrenceId } }"
          >
            <span class="tk-media">
              <img v-if="e.coverImageUrl || e.bannerImageUrl" alt="" loading="lazy" :src="(e.coverImageUrl ?? e.bannerImageUrl)!">
              <span v-else class="tk-media__fallback"><v-icon icon="mdi-calendar-star" size="56" /></span>

              <span class="tk-badge">
                <strong>{{ day(e) }}</strong>
                <span>{{ month(e) }}</span>
              </span>
            </span>

            <span class="tk-event__body">
              <h2>{{ e.title }}</h2>
              <span class="tk-meta"><v-icon icon="mdi-clock-outline" size="18" />{{ formatWhen(e.startsAt, e.timeZone) }}</span>
              <span v-if="e.venueName" class="tk-meta"><v-icon icon="mdi-map-marker-outline" size="18" />{{ e.venueName }}</span>

              <span class="tk-event__foot">
                <span v-if="e.fromPriceMinor !== null">{{ e.fromPriceMinor === 0 ? 'Free' : `From ${formatRand(e.fromPriceMinor)}` }}</span>
                <span v-else />
                <span class="tk-pill" :class="{ 'tk-pill--off': !e.available }">{{ e.available ? 'Get tickets' : 'Sold out' }}</span>
              </span>
            </span>
          </router-link>
        </div>
      </template>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { PublicEventSummary } from '@/tickets/public-tickets'
  import { onMounted, ref } from 'vue'
  import PublicBrand from '@/components/PublicBrand.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePageTitle } from '@/composables/usePageTitle'
  import { formatRand, formatWhen, publicTickets } from '@/tickets/public-tickets'
  import '@/tickets/tickets.css'

  usePageTitle('Upcoming events')

  function part (e: PublicEventSummary, options: Intl.DateTimeFormatOptions) {
    return new Intl.DateTimeFormat('en-ZA', { timeZone: e.timeZone, ...options }).format(new Date(e.startsAt))
  }
  const day = (e: PublicEventSummary) => part(e, { day: 'numeric' })
  const month = (e: PublicEventSummary) => part(e, { month: 'short' })

  const events = ref<PublicEventSummary[]>([])
  const loading = ref(true)
  const error = ref('')

  onMounted(async () => {
    try {
      events.value = await publicTickets.events()
    } catch (error_) {
      error.value = extractErrorMessage(error_, '')
    } finally {
      loading.value = false
    }
  })
</script>
