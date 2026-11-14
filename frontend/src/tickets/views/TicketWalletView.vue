<template>
  <main class="tk-page">
    <div class="tk-shell tk-shell--narrow">
      <PublicBrand />

      <div v-if="loading" aria-label="Loading" role="status">
        <v-skeleton-loader type="article, article" />
      </div>

      <section v-else-if="!wallet" class="tk-card text-center">
        <v-icon color="primary" icon="mdi-ticket-outline" size="40" />
        <h1>We couldn't open these tickets</h1>
        <p role="alert">{{ error || 'Open the link from your tickets email again.' }}</p>
        <TicketResendForm class="text-left mt-4" />
      </section>

      <template v-else>
        <section class="tk-card">
          <h1>{{ wallet.event.title }}</h1>

          <p class="tk-muted">
            {{ formatWhen(wallet.event.startsAt, wallet.event.timeZone) }}<br>
            {{ wallet.event.venueName }}<span v-if="wallet.event.venueAddress">, {{ wallet.event.venueAddress }}</span>
          </p>

          <div v-if="wallet.totalMinor > 0" class="tk-muted">
            Tickets {{ formatRand(wallet.subtotalMinor) }} · Processing fee {{ formatRand(wallet.feeMinor) }} ·
            <strong>Total {{ formatRand(wallet.totalMinor) }}</strong>
          </div>

          <v-alert
            v-if="wallet.state !== 'PAID'"
            class="mt-3"
            type="warning"
            variant="tonal"
          >This order is not active ({{ wallet.state.toLowerCase().replace('_', ' ') }}), so its tickets can't be used.</v-alert>
        </section>

        <section v-for="(t, i) in wallet.tickets" :key="t.id" class="tk-card text-center">
          <strong>{{ t.type }}</strong> · Ticket {{ i + 1 }} of {{ wallet.tickets.length }}
          <div class="tk-muted">{{ t.holder }}</div>

          <img
            v-if="t.code && images[t.id]"
            :alt="`QR code for ${t.type} ticket ${i + 1}`"
            class="tk-qr"
            :src="images[t.id]"
          >

          <v-chip v-if="t.status === 'CHECKED_IN'" color="info">Already used</v-chip>
          <v-chip v-else-if="t.status === 'VOID'" color="error">Cancelled</v-chip>
        </section>

        <TicketDocuments v-if="wallet.documents" :docs="wallet.documents" />

        <p class="tk-muted text-center">Keep this page private: anyone with the link can see your tickets.</p>
      </template>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { Wallet } from '@/tickets/public-tickets'
  import QRCode from 'qrcode'
  import { onMounted, reactive, ref } from 'vue'
  import PublicBrand from '@/components/PublicBrand.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePageTitle } from '@/composables/usePageTitle'
  import TicketDocuments from '@/tickets/components/TicketDocuments.vue'
  import TicketResendForm from '@/tickets/components/TicketResendForm.vue'
  import { formatRand, formatWhen, publicTickets } from '@/tickets/public-tickets'
  import '@/tickets/tickets.css'

  usePageTitle('Your tickets')

  const wallet = ref<Wallet>()
  const loading = ref(true)
  const error = ref('')
  const images = reactive<Record<string, string>>({})

  onMounted(async () => {
    // The token is in the URL fragment: it is never sent to a server except in this request's body.
    const token = window.location.hash.slice(1)
    try {
      if (!token) throw new Error('This link is incomplete.')
      wallet.value = await publicTickets.wallet(token)
      await Promise.all(wallet.value.tickets.map(async t => {
        if (t.code && t.status === 'ISSUED') {
          images[t.id] = await QRCode.toDataURL(t.code, { margin: 1, width: 440, errorCorrectionLevel: 'M' })
        }
      }))
    } catch (error_) {
      error.value = extractErrorMessage(error_, '')
    } finally {
      loading.value = false
    }
  })
</script>
