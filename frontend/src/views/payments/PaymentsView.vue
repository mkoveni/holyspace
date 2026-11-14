<template>
  <div>
    <PageHeader :eyebrow="member ? 'My church' : 'Payments & reconciliation'" :subtitle="member ? 'Give securely, manage monthly giving and book paid events.' : 'Review hosted payments, refunds and provider configuration.'" :title="member ? 'Giving and tickets' : 'Online payments'">
      <template #actions><v-btn v-if="member" to="/member" variant="outlined">My church</v-btn><v-btn :loading="busy" prepend-icon="mdi-refresh" @click="load">Refresh payments</v-btn></template>
    </PageHeader>

    <v-alert v-if="error" class="mb-4" type="error">{{ error }}</v-alert>
    <v-alert v-if="pendingRequest" class="mb-4" type="info">Your checkout request is awaiting confirmation. Retry this same request to avoid creating a duplicate payment.<div class="workspace-actions mt-3"><v-btn :loading="busy" @click="checkout(pendingRequest!)">Retry same payment request</v-btn></div></v-alert>

    <template v-if="data">
      <v-card class="workspace-panel">
        <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-shield-check-outline" /><div><h2>Secure hosted payments</h2><p>Payments are in South African rand. You enter payment details on the provider’s website.</p></div></div></div>
        <div class="workspace-form-row"><div v-for="gatewayOption in data.providers" :key="gatewayOption.id" class="workspace-insight"><h3>{{ gatewayOption.name }}</h3><v-chip :color="gatewayOption.available ? (gatewayOption.mode === 'live' ? 'success' : 'warning') : undefined">{{ gatewayOption.available ? (gatewayOption.mode === 'live' ? 'Live payments' : 'Sandbox · no real gifts or tickets') : 'Not configured' }}</v-chip><p class="mt-3">{{ gatewayOption.recurring ? 'One-off payments and monthly recurring giving.' : 'One-off giving and ticket payments.' }}</p></div></div>
        <p class="workspace-note">Returning from checkout does not confirm payment. Refresh to see the provider’s verified result. Cancelling a booking does not automatically refund its payment.</p>
      </v-card>

      <v-card v-if="!member && auth.hasPermission('giving.manage')" class="workspace-panel">
        <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-gift-outline" /><div><h2>Online giving funds</h2><p>Select the active funds members may support online.</p></div></div></div>

        <v-select
          v-model="fundIds"
          chips
          item-title="name"
          item-value="id"
          :items="data.funds"
          label="Funds available for online giving"
          multiple
        />

        <v-btn color="primary" :loading="busy" @click="saveFunds">Save online giving funds</v-btn>
      </v-card>

      <template v-if="member">
        <v-card class="workspace-panel">
          <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-hand-heart-outline" /><div><h2>Give online</h2><p>Choose a fund and an amount. Monthly giving is available through Payfast.</p></div></div></div>
          <p v-if="data.funds.length === 0" class="workspace-empty">Church staff have not enabled any online giving funds yet.</p>

          <template v-else><div class="workspace-form-row"><v-select
                                                             v-model="fundId"
                                                             item-title="name"
                                                             item-value="id"
                                                             :items="data.funds"
                                                             label="Giving fund"
                                                           />

                             <v-text-field
                               v-model="amount"
                               hint="Minimum R5.00"
                               inputmode="decimal"
                               label="Giving amount (ZAR)"
                               persistent-hint
                             /></div>

            <v-select
              v-model="provider"
              item-title="name"
              item-value="id"
              :items="availableProviders"
              label="Payment provider"
            />

            <v-btn color="primary" :disabled="Boolean(pendingRequest) || !fundId || !provider || minorAmount === null" :loading="busy" @click="startGiving">Continue to secure checkout</v-btn>
          </template>
        </v-card>

        <v-card class="workspace-panel">
          <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-ticket-confirmation-outline" /><div><h2>Paid events</h2><p>A place is reserved for 15 minutes while you complete payment.</p></div></div></div>
          <p v-if="!data.events?.length" class="workspace-empty">No paid events are open for booking.</p>

          <div v-for="event in data.events" :key="event.id" class="workspace-record"><div><strong>{{ event.title }}</strong><p>{{ dateTime(event.startsAt) }} · {{ event.location }}</p><p>{{ money(event.amountMinor) }} · {{ event.remaining === null ? 'Places available' : event.remaining + ' places available' }}</p></div>

            <div class="workspace-actions"><v-btn
              v-for="option in availableProviders"
              :key="option.id"
              :disabled="busy || Boolean(pendingRequest) || event.remaining === 0"
              variant="outlined"
              @click="startTicket(event, option.id)"
            >Book with {{ option.name }}</v-btn></div></div>
        </v-card>
      </template>

      <v-card class="workspace-panel">
        <div class="panel-heading"><div class="panel-heading__copy"><v-icon icon="mdi-receipt-text-outline" /><div><h2>{{ member ? 'My payment history' : 'Payment history and review' }}</h2><p>Verified charges and refunds are separate from pending checkout requests.</p></div></div></div>
        <p v-if="data.orders.length === 0" class="workspace-empty">No online payments recorded.</p>

        <div v-for="order in sortedOrders" :key="order.id" class="payment-record">
          <div class="panel-heading"><div><h3>{{ order.purpose === 'giving' ? 'One-off giving' : 'Event ticket' }} · {{ money(order.amountMinor) }}</h3><p>Yoco · {{ dateTime(order.createdAt) }} · {{ order.mode === 'test' ? 'Sandbox only' : 'Live' }}</p><p v-if="!member">{{ order.personName ?? order.personId }}</p></div><v-chip :color="order.status === 'review' ? 'warning' : order.status === 'paid' ? 'success' : undefined">{{ order.status }}</v-chip></div>
          <v-alert v-if="order.reviewReason" class="my-3" type="warning" variant="tonal">{{ order.reviewReason }}</v-alert>
          <p v-if="order.status === 'created' || order.status === 'ready'">Awaiting provider verification. {{ order.purpose === 'ticket' ? 'Your reserved place is held until' : 'You can resume this request until' }} {{ dateTime(order.expiresAt) }}. A late ticket payment requires staff refund review.</p>
          <p v-if="order.mode === 'test'">This is a sandbox payment. It does not appear in giving statements or confirm a real ticket.</p>
          <div v-for="charge in order.charges" :key="charge.id" class="workspace-record"><div><strong>Verified charge: {{ money(charge.amountMinor) }}</strong><p>Refunded: {{ money(charge.refundedMinor) }} · {{ charge.providerReference }}</p></div><v-btn v-if="!member && charge.refundedMinor < charge.amountMinor" :disabled="busy" variant="text" @click="openRefund(charge)">Record completed refund</v-btn></div>

          <v-alert
            v-for="item in order.refunds.filter(refund => !refund.applied)"
            :key="item.id"
            class="my-3"
            type="warning"
            variant="tonal"
          >Refund awaiting ledger matching: {{ money(item.amountMinor) }} · {{ item.providerReference }}. <span v-if="!member">Use Record completed refund on the correct charge and enter this provider refund ID.</span></v-alert>

          <div class="workspace-actions mt-4">
            <v-btn v-if="member && ['created','ready'].includes(order.status) && !expired(order)" :disabled="busy" variant="outlined" @click="retry(order)">Resume this checkout</v-btn>
            <v-btn v-if="!member && order.reviewReason" :disabled="busy" variant="outlined" @click="reviewing = order; note = ''">Record reconciliation</v-btn>
          </div>

          <p v-if="order.reconciliationNote" class="workspace-note">Reconciliation: {{ order.reconciliationNote }}</p>
        </div>
      </v-card>
    </template>

    <v-dialog max-width="600" :model-value="Boolean(reviewing)" persistent><v-card title="Record reconciliation"><v-card-text><p>Verify the provider’s transactions and refund records first. This records your review and retries posting verified gifts; it does not charge or refund the customer.</p><v-textarea v-model="note" label="Reconciliation evidence and outcome" maxlength="1000" /><v-alert v-if="error" type="error">{{ error }}</v-alert></v-card-text><v-card-actions><v-btn :disabled="busy" @click="reviewing = null">Close</v-btn><v-btn :disabled="!note.trim()" :loading="busy" @click="reconcile">Save reconciliation</v-btn></v-card-actions></v-card></v-dialog>
    <v-dialog max-width="600" :model-value="Boolean(refunding)" persistent><v-card title="Record completed provider refund"><v-card-text><p>Complete the refund in the provider dashboard first. Record its exact refund ID here. Yoco’s verified refund callbacks normally record this automatically.</p><v-text-field v-model="refundAmount" inputmode="decimal" label="Refund amount (ZAR)" /><v-text-field v-model="refundReference" label="Provider refund ID" maxlength="150" /><v-checkbox v-model="refundConfirmed" label="I verified that this refund completed in the provider dashboard" /><v-alert v-if="error" type="error">{{ error }}</v-alert></v-card-text><v-card-actions><v-btn :disabled="busy" @click="refunding = null">Close</v-btn><v-btn :disabled="!refundConfirmed || !refundReference.trim() || refundMinor === null" :loading="busy" @click="refund">Record refund</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { isAxiosError } from 'axios'
  import { computed, onMounted, ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { zarMinorAmount } from '@/payments/money'
  import { useAuthStore } from '@/stores/auth'
  interface Provider {
    id: string
    name: string
    available: boolean
    mode: string
    recurring: boolean
  }
  interface Charge {
    id: string
    amountMinor: number
    refundedMinor: number
    providerReference: string
    version: number
  }
  interface Order {
    id: string
    personId: string
    personName?: string
    provider: string
    purpose: string
    targetId: string
    requestKey: string
    amountMinor: number
    mode: string
    status: string
    recurring: boolean
    subscriptionStatus: string
    expiresAt: string
    createdAt: string
    reviewReason: string | null
    reconciliationNote: string | null
    version: number
    charges: Charge[]
    refunds: {
      id: string
      providerReference: string
      amountMinor: number
      applied: boolean
    }[]
  }
  interface EventOffer {
    id: string
    title: string
    startsAt: string
    location: string
    amountMinor: number
    remaining: number | null
  }
  interface Data {
    providers: Provider[]
    funds: {
      id: string
      name: string
    }[]
    events?: EventOffer[]
    orders: Order[]
    settings?: {
      fundIds: string[]
      version: number
    }
  }
  interface CheckoutRequest {
    provider: string
    purpose: string
    targetId: string
    amountMinor: number | null
    recurring: boolean
    requestKey: string
  }
  const props = withDefaults(defineProps<{
    member?: boolean
  }>(), { member: false })
  const auth = useAuthStore(), data = ref<Data | null>(null), error = ref(''), busy = ref(false), fundIds = ref<string[]>([]), fundId = ref<string | null>(null), amount = ref(''), provider = ref<string | null>(null)
  const requestStorageKey = 'divinecommunityspace.payment-request.' + (auth.user?.personId ?? 'unlinked')
  const pendingRequest = ref<CheckoutRequest | null>(null)
  try {
    const cached = sessionStorage.getItem(requestStorageKey)
    if (props.member && cached)
      pendingRequest.value = JSON.parse(cached)
  } catch {
    sessionStorage.removeItem(requestStorageKey)
  }
  const reviewing = ref<Order | null>(null), note = ref(''), refunding = ref<Charge | null>(null), refundAmount = ref(''), refundReference = ref(''), refundConfirmed = ref(false)
  const minorAmount = computed(() => zarMinorAmount(amount.value, 500)), refundMinor = computed(() => zarMinorAmount(refundAmount.value, 1))
  const availableProviders = computed(() => data.value?.providers.filter(p => p.available) ?? [])
  // eslint-disable-next-line unicorn/no-array-sort -- Sort a copy for the project's configured JavaScript library.
  const sortedOrders = computed(() => [...(data.value?.orders ?? [])].sort((a, b) => b.createdAt.localeCompare(a.createdAt)))
  function money (value: number) {
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(value / 100)
  }
  function dateTime (value: string) {
    return new Date(value).toLocaleString()
  }
  function expired (order: Order) {
    return new Date(order.expiresAt).getTime() <= Date.now()
  }
  async function action (operation: () => Promise<void>) {
    if (busy.value)
      return
    busy.value = true
    error.value = ''
    try {
      await operation()
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to complete this payment operation.')
    } finally {
      busy.value = false
    }
  }
  async function refresh () {
    data.value = (await useApi().get<Data>(props.member ? '/api/payments/member' : '/api/payments')).data
    fundIds.value = [...(data.value.settings?.fundIds ?? [])]
    if (!provider.value)
      provider.value = availableProviders.value[0]?.id ?? null
  }
  async function load () {
    await action(refresh)
  }
  async function saveFunds () {
    await action(async () => {
      await useApi().put('/api/payments/funds', { fundIds: fundIds.value, expectedVersion: data.value?.settings?.version ?? 0 })
      await refresh()
    })
  }
  async function checkout (payload: CheckoutRequest) {
    await action(async () => {
      pendingRequest.value = payload
      sessionStorage.setItem(requestStorageKey, JSON.stringify(payload))
      let response
      try {
        response = (await useApi().post('/api/payments/checkout', payload)).data
      } catch (error_) {
        if (isAxiosError(error_) && [400, 403, 404, 409, 422].includes(error_.response?.status ?? 0)) {
          pendingRequest.value = null
          sessionStorage.removeItem(requestStorageKey)
        }
        throw error_
      }
      pendingRequest.value = null
      sessionStorage.removeItem(requestStorageKey)
      await refresh()
      if (!response.checkout)
        return
      const target = response.checkout
      const url = new URL(target.url)
      if (url.protocol !== 'https:' || url.hostname !== 'c.yoco.com')
        throw new Error('Invalid hosted payment destination.')
      if (target.method === 'GET')
        window.location.assign(url.href)
      else {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = url.href
        for (const [name, value] of Object.entries(target.fields)) {
          const input = document.createElement('input')
          input.type = 'hidden'
          input.name = name
          input.value = String(value)
          form.append(input)
        }
        document.body.append(form)
        form.submit()
        form.remove()
      }
    })
  }
  function startGiving () {
    if (!provider.value || !fundId.value || minorAmount.value === null)
      return
    void checkout({ provider: provider.value, purpose: 'giving', targetId: fundId.value, amountMinor: minorAmount.value, recurring: false, requestKey: crypto.randomUUID() })
  }
  function startTicket (event: EventOffer, providerId: string) {
    void checkout({ provider: providerId, purpose: 'ticket', targetId: event.id, amountMinor: null, recurring: false, requestKey: crypto.randomUUID() })
  }
  function retry (order: Order) {
    void checkout({ provider: order.provider, purpose: order.purpose, targetId: order.targetId, amountMinor: order.purpose === 'giving' ? order.amountMinor : null, recurring: order.recurring, requestKey: order.requestKey })
  }
  async function reconcile () {
    await action(async () => {
      await useApi().post('/api/payments/' + reviewing.value?.id + '/reconcile', { expectedVersion: reviewing.value?.version, note: note.value })
      reviewing.value = null
      await refresh()
    })
  }
  function openRefund (charge: Charge) {
    refunding.value = charge
    refundAmount.value = ((charge.amountMinor - charge.refundedMinor) / 100).toFixed(2)
    refundReference.value = ''
    refundConfirmed.value = false
  }
  async function refund () {
    await action(async () => {
      await useApi().post('/api/payments/charges/' + refunding.value?.id + '/refund', { expectedVersion: refunding.value?.version, amountMinor: refundMinor.value, reference: refundReference.value, confirmed: refundConfirmed.value })
      refunding.value = null
      await refresh()
    })
  }
  onMounted(load)
</script>
<style scoped>.payment-record{padding:24px 0;border-bottom:1px solid var(--workspace-border)}.payment-record:last-child{border-bottom:0}.payment-record p{overflow-wrap:anywhere}</style>
