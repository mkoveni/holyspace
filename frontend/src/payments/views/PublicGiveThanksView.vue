<template>
  <main class="give-page">
    <div class="give-shell">
      <div class="give-brand">
        <v-icon icon="mdi-church-outline" size="24" />
        <span>Church community</span>
      </div>

      <section ref="card" class="give-card text-center" role="status" tabindex="-1">
        <template v-if="view === 'paid'">
          <v-icon color="success" icon="mdi-check-circle-outline" size="48" />
          <h1>Thank you for your gift</h1>

          <p>
            We've received your gift of {{ amount }}.
            <template v-if="status?.confirmationQueued">A confirmation is on its way to your email.</template>
            <template v-else>Your confirmation email will arrive shortly.</template>
          </p>
        </template>

        <template v-else-if="view === 'test'">
          <v-icon color="info" icon="mdi-test-tube" size="48" />
          <h1>Test payment received</h1>
          <p>This was a sandbox payment. No gift was recorded.</p>
        </template>

        <template v-else-if="view === 'cancelled'">
          <v-icon color="warning" icon="mdi-close-circle-outline" size="48" />
          <h1>Your gift wasn't completed</h1>
          <p>You cancelled, so nothing was charged.</p>
          <v-btn class="mt-2" color="primary" to="/give">Try again</v-btn>
        </template>

        <template v-else-if="view === 'failed'">
          <v-icon color="error" icon="mdi-alert-circle-outline" size="48" />
          <h1>Your payment didn't go through</h1>
          <p>Nothing was charged. You can try again, or ask the church office for help.</p>
          <v-btn class="mt-2" color="primary" to="/give">Try again</v-btn>
        </template>

        <template v-else-if="view === 'unknown'">
          <v-icon color="primary" icon="mdi-email-outline" size="48" />
          <h1>Thank you</h1>

          <p>
            If your payment went through, you'll receive a confirmation by email. If you don't, please contact the church
            office rather than paying again.
          </p>
        </template>

        <template v-else>
          <v-progress-circular color="primary" indeterminate size="40" />
          <h1>Confirming your gift…</h1>
          <p>This usually takes a few seconds. Please keep this page open.</p>
        </template>

        <p v-if="view !== 'waiting'" class="give-note">You can close this page.</p>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { GuestStatus } from '@/payments/public-giving'
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { useRoute } from 'vue-router'
  import { formatRand, publicGiving, tokenKey } from '@/payments/public-giving'

  const POLL_MS = 3000
  const MAX_POLLS = 20
  const route = useRoute()
  const status = ref<GuestStatus>()
  const card = ref<HTMLElement>()
  const unknown = ref(false)
  const returned = computed(() => String(route.query.returned ?? ''))
  const amount = computed(() => (status.value ? formatRand(status.value.amountMinor) : ''))
  const view = computed(() => {
    if (returned.value === 'cancelled') return 'cancelled'
    if (returned.value === 'failed' || status.value?.state === 'FAILED') return 'failed'
    if (status.value?.state === 'PAID') return 'paid'
    if (status.value?.state === 'TEST_PAYMENT') return 'test'
    return unknown.value ? 'unknown' : 'waiting'
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
      status.value = await publicGiving.status(accessToken)
    } catch {
      // Keep waiting; a transient failure must not look like a failed gift.
    }
    const done = status.value && status.value.state !== 'AWAITING_PAYMENT' && (status.value.confirmationQueued || status.value.state !== 'PAID')
    if (done) {
      card.value?.focus()
      return
    }
    if (++polls >= MAX_POLLS) {
      unknown.value = !status.value || status.value.state === 'AWAITING_PAYMENT'
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

<style scoped>
.give-page {
  min-height: 100vh;
  padding: 16px;
  background: rgb(var(--v-theme-background));
}
.give-shell {
  max-width: 520px;
  margin: 0 auto;
}
.give-brand {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 8px 0 16px;
  font-weight: 600;
}
.give-card {
  padding: 24px 20px;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12);
}
.give-card h1 {
  margin: 4px 0 8px;
  font-size: 1.5rem;
}
.give-note {
  margin: 16px 0 0;
  opacity: 0.75;
}
</style>
