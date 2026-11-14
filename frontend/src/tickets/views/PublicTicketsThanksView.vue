<template>
  <main class="tk-page">
    <div class="tk-shell tk-shell--narrow">
      <PublicBrand />

      <section ref="card" class="tk-card text-center" role="status" tabindex="-1">
        <template v-if="view === 'paid'">
          <v-icon color="success" icon="mdi-check-circle-outline" size="48" />
          <h1>Your tickets are ready</h1>
          <p>We've emailed them to you too. Show the QR code on your phone at the door.</p>
          <v-btn color="primary" :href="walletLink">Open my tickets</v-btn>
        </template>

        <template v-else-if="view === 'test'">
          <v-icon color="info" icon="mdi-test-tube" size="48" />
          <h1>Test payment received</h1>
          <p>This was a sandbox payment. No tickets were issued.</p>
        </template>

        <template v-else-if="view === 'cancelled' || view === 'failed'">
          <v-icon color="warning" icon="mdi-close-circle-outline" size="48" />
          <h1>{{ view === 'failed' ? "Your payment didn't go through" : "Your order wasn't completed" }}</h1>
          <p>Nothing was charged. You can try again from the events page.</p>
          <v-btn class="mt-2" color="primary" :to="{ name: 'public-tickets-events' }">Back to events</v-btn>
        </template>

        <template v-else-if="view === 'expired' || view === 'review'">
          <v-icon color="warning" icon="mdi-alert-circle-outline" size="48" />
          <h1>We need to look at your order</h1>

          <p>
            Your seats were held for a limited time. If you were charged, the church office will refund you or sort it out;
            please don't pay again until you've heard from us.
          </p>
        </template>

        <template v-else-if="view === 'unknown'">
          <v-icon color="primary" icon="mdi-email-outline" size="48" />
          <h1>Thank you</h1>
          <p>If your payment went through, your tickets will arrive by email. If they don't, please contact the church office rather than paying again.</p>
        </template>

        <template v-else>
          <v-progress-circular color="primary" indeterminate size="40" />
          <h1>Confirming your payment…</h1>
          <p>This usually takes a few seconds. Please keep this page open.</p>
        </template>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { OrderStatus } from '@/tickets/public-tickets'
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { useRoute } from 'vue-router'
  import PublicBrand from '@/components/PublicBrand.vue'
  import { usePageTitle } from '@/composables/usePageTitle'
  import { publicTickets, tokenKey } from '@/tickets/public-tickets'
  import '@/tickets/tickets.css'

  usePageTitle('Thank you')

  const POLL_MS = 3000
  const MAX_POLLS = 20
  const route = useRoute()
  const status = ref<OrderStatus>()
  const card = ref<HTMLElement>()
  const unknown = ref(false)
  const returned = computed(() => String(route.query.returned ?? ''))
  // The wallet token goes in the URL fragment, so it never reaches server logs or a Referer header.
  const walletLink = computed(() => `/tickets#${status.value?.walletToken ?? ''}`)
  const view = computed(() => {
    if (returned.value === 'cancelled') return 'cancelled'
    if (returned.value === 'failed') return 'failed'
    switch (status.value?.state) {
      case 'PAID': { return 'paid' }
      case 'TEST_PAYMENT': { return 'test' }
      case 'EXPIRED': { return 'expired' }
      case 'IN_REVIEW': { return 'review' }
      case 'REFUNDED': { return 'review' }
      default: { return unknown.value ? 'unknown' : 'waiting' }
    }
  })
  let timer: ReturnType<typeof setTimeout> | undefined
  let polls = 0

  function token (): string | null {
    const order = String(route.query.order ?? '')
    if (!order) return null
    try {
      return sessionStorage.getItem(tokenKey(order))
    } catch {
      return null
    }
  }

  // A success return page never proves payment: the verified provider notification does. So poll the server's view.
  async function poll (accessToken: string) {
    try {
      status.value = await publicTickets.status(accessToken)
    } catch {
      // Keep waiting; a transient failure must not look like a failed order.
    }
    if (status.value && status.value.state !== 'AWAITING_PAYMENT') {
      card.value?.focus()
      return
    }
    if (++polls >= MAX_POLLS) {
      unknown.value = true
      return
    }
    timer = setTimeout(() => poll(accessToken), POLL_MS)
  }

  onMounted(() => {
    const accessToken = token()
    if (returned.value === 'cancelled' || returned.value === 'failed') return
    if (!accessToken) {
      unknown.value = true
      return
    }
    poll(accessToken)
  })
  onBeforeUnmount(() => clearTimeout(timer))
</script>
