<template>
  <div class="directory-workspace donor-workspace">
    <DirectoryHeading
      eyebrow="Giving"
      subtitle="Give securely, follow your payments and see your contribution history."
      title="My giving"
    >
      <v-btn
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="refresh"
      >
        Refresh
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="route.query.order"
      class="mb-4"
      type="info"
      variant="tonal"
    >
      Welcome back. Your payment is confirmed only after the provider verifies
      it. Refresh to see the latest status.
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <section
      v-if="data && !data.donor"
      class="directory-surface giving-section donor-empty"
    >
      <v-icon
        color="primary"
        icon="mdi-account-check-outline"
        size="48"
      />

      <h2>Let’s connect your giving.</h2>

      <p class="directory-muted">
        Ask the church office to verify your account and connect it to your
        People record. Your giving history will appear here once access is
        approved.
      </p>

      <v-chip
        prepend-icon="mdi-lock-outline"
        variant="tonal"
      >
        Identity verification required
      </v-chip>
    </section>

    <template v-if="data?.donor">
      <section class="donor-hero directory-surface giving-section mb-5">
        <div>
          <span class="directory-eyebrow">YOUR PERSONAL GIVING SPACE</span>
          <h2>Thank you, {{ data.donor.name }}.</h2>
          <p>Every contribution helps your church community flourish.</p>
        </div>

        <v-icon
          icon="mdi-hand-heart-outline"
          size="56"
        />
      </section>

      <div class="donor-grid">
        <section class="directory-surface giving-section donor-form">
          <span class="directory-eyebrow">GIVE ONLINE</span>
          <h2>Make a difference.</h2>

          <p class="directory-muted mb-5">
            Choose a fund and continue to secure payment with your provider.
          </p>

          <v-alert
            v-if="data.funds.length === 0 || data.providers.length === 0"
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Online giving is not available yet. Please contact the church
            office.
          </v-alert>

          <v-select
            v-model="fundId"
            :disabled="working || frozen"
            item-title="name"
            item-value="id"
            :items="data.funds"
            label="Give towards"
          />

          <p
            v-if="selectedFund?.description"
            class="directory-muted mb-4"
          >
            {{ selectedFund.description }}
          </p>

          <v-text-field
            v-model="amount"
            :disabled="working || frozen"
            hint="From R5.00. Enter up to two decimal places."
            inputmode="decimal"
            label="Amount (ZAR)"
            persistent-hint
            placeholder="100.00"
            prefix="R"
          />

          <v-select
            v-model="providerId"
            class="mt-4"
            :disabled="working || frozen"
            item-title="name"
            item-value="id"
            :items="data.providers"
            label="Payment provider"
          />

          <v-alert
            v-if="selectedProvider?.mode === 'test'"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            Test mode: this is a simulation and will not become a real
            contribution.
          </v-alert>

          <v-checkbox
            v-model="reviewed"
            :disabled="working || frozen"
            hide-details
            label="I have checked the amount and selected fund."
          />

          <v-btn
            block
            class="mt-4"
            color="primary"
            :disabled="!canGive"
            :loading="working"
            size="large"
            @click="give"
          >
            {{ frozen ? 'Retry same gift request' : 'Continue to payment' }}
          </v-btn>

          <p class="directory-muted mt-4">
            <v-icon
              icon="mdi-shield-lock-outline"
              size="18"
            />
            Your payment details are entered with the provider.
          </p>
        </section>

        <div>
          <section class="directory-surface giving-section mb-5">
            <span class="directory-eyebrow">PAYMENT ACTIVITY</span>
            <h2>Your giving</h2>

            <p class="directory-muted mb-4">
              Track your checkout progress.
            </p>

            <p
              v-if="data.payments.items.length === 0"
              class="directory-muted py-5"
            >
              Your online gifts will appear here.
            </p>

            <button
              v-for="order in data.payments.items"
              :key="order.id"
              class="donor-row donor-order"
              @click="openPayment(order.id)"
            >
              <div>
                <strong>{{ money(order.amountMinor, 'ZAR') }}</strong>

                <p>
                  One-off ·
                  {{ providerName(order.provider) }}
                </p>

                <small>{{ dateLabel(order.createdAt.slice(0, 10)) }}</small>
              </div>

              <div class="donor-badges">
                <v-chip
                  :color="order.status === 'PAID' ? 'success' : undefined"
                  size="small"
                >
                  {{ order.mode === 'test' ? 'Test · ' : ''
                  }}{{ statusLabel(order.status) }}
                </v-chip>
              </div>
            </button>

            <div class="donor-pages">
              <v-btn
                :disabled="!paymentOffset || loading"
                variant="text"
                @click="paymentPage(-25)"
              >
                Previous
              </v-btn>

              <span>{{ data.payments.total }} requests</span>

              <v-btn
                :disabled="paymentOffset + 25 >= data.payments.total || loading"
                variant="text"
                @click="paymentPage(25)"
              >
                Next
              </v-btn>
            </div>
          </section>

          <section class="directory-surface giving-section">
            <span class="directory-eyebrow">YOUR CONTRIBUTIONS</span>
            <h2>Giving history</h2>

            <p class="directory-muted mb-4">
              Recorded contributions, including gifts made through the church
              office. Pending and test payments are excluded.
            </p>

            <p
              v-if="data.gifts.length === 0"
              class="directory-muted py-5"
            >
              Your recorded contributions will appear here.
            </p>

            <article
              v-for="gift in data.gifts"
              :key="gift.id"
              class="donor-row"
            >
              <div>
                <strong>{{ money(gift.amountMinor, gift.currency) }}</strong>

                <p>
                  {{ dateLabel(gift.receivedOn) }} ·
                  {{ gift.method === 'ONLINE' ? 'Online' : 'Church office' }}
                </p>
              </div>

              <div class="donor-badges">
                <v-chip
                  v-if="gift.reversed"
                  color="warning"
                  size="small"
                >
                  Reversed
                </v-chip>

                <v-chip
                  v-else-if="gift.refundedMinor"
                  color="warning"
                  size="small"
                >
                  {{ money(gift.refundedMinor, gift.currency) }} refunded
                </v-chip>

                <v-chip
                  v-else
                  color="success"
                  size="small"
                  variant="tonal"
                >
                  Recorded
                </v-chip>
              </div>
            </article>

            <div class="donor-pages">
              <v-btn
                :disabled="!giftOffset || loading"
                variant="text"
                @click="giftPage(-25)"
              >
                Previous
              </v-btn>

              <span>{{ data.giftTotal }} contributions</span>

              <v-btn
                :disabled="giftOffset + 25 >= data.giftTotal || loading"
                variant="text"
                @click="giftPage(25)"
              >
                Next
              </v-btn>
            </div>
          </section>
        </div>
      </div>
    </template>

    <v-dialog
      v-model="detailOpen"
      max-width="680"
      scrollable
    >
      <v-card
        v-if="detail"
        rounded="xl"
      >
        <v-card-title class="pa-6 donor-dialog-title">
          Your online gift
        </v-card-title>

        <v-card-text>
          <v-alert
            v-if="actionError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ actionError }}
          </v-alert>

          <v-alert
            v-if="detail.order.mode === 'test'"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            Test payment. No real contribution is recorded.
          </v-alert>

          <h2>{{ money(detail.order.amountMinor, 'ZAR') }}</h2>

          <p class="directory-muted mb-4">
            {{ providerName(detail.order.provider) }} ·
            {{ statusLabel(detail.order.status) }}
          </p>

          <article
            v-for="charge in detail.charges"
            :key="charge.id"
            class="donor-charge"
          >
            <strong>
              {{ money(charge.amountMinor, 'ZAR') }} ·
              {{ dateLabel(charge.receivedOn) }}
            </strong>

            <p>{{ givingLabel(charge.givingStatus) }}</p>

            <p v-if="charge.refundedMinor">
              Refunded: {{ money(charge.refundedMinor, 'ZAR') }}
            </p>

            <p class="directory-muted">
              Confirmation:
              {{
                charge.captured
                  ? 'Captured locally · not delivered'
                  : confirmationLabel(charge.confirmationStatus)
              }}
            </p>

            <v-expansion-panels
              v-if="charge.confirmation"
              class="mt-3"
              variant="accordion"
            >
              <v-expansion-panel title="View original payment confirmation">
                <v-expansion-panel-text>
                  <p class="mb-3">
                    <strong>{{ charge.confirmation.subject }}</strong>
                  </p>

                  <p class="directory-muted mb-3">
                    Original confirmation. Any later refunds are shown in this
                    payment’s history.
                  </p>

                  <EmailPreview
                    :html="charge.confirmation.html"
                    title="Your payment confirmation"
                  />
                </v-expansion-panel-text>
              </v-expansion-panel>
            </v-expansion-panels>
          </article>

          <article
            v-for="(refund, index) in detail.refunds"
            :key="index"
            class="donor-charge"
          >
            <strong>Refund · {{ money(refund.amountMinor, 'ZAR') }}</strong>

            <p>
              {{ dateLabel(refund.refundedOn) }} ·
              {{ refund.applied ? 'Recorded' : 'Being recorded' }}
            </p>
          </article>

        </v-card-text>

        <v-card-actions class="pa-5 donor-dialog-actions">
          <v-btn
            :disabled="working"
            variant="text"
            @click="closeDetail"
          >
            Close
          </v-btn>

          <v-btn
            :disabled="working"
            variant="text"
            @click="reloadDetail"
          >
            Refresh status
          </v-btn>

          <v-btn
            v-if="canCheckout"
            color="primary"
            :loading="working"
            @click="continueCheckout"
          >
            Open secure checkout
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import type { DonorDetail, MyGiving } from '../donor-api'
  import axios from 'axios'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import EmailPreview from '@/communications/components/EmailPreview.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { dateLabel, money } from '@/giving/money'
  import { useAuthStore } from '@/stores/auth'
  import { hostedCheckout } from '../checkout'
  import { donorApi } from '../donor-api'
  import { zarMinorAmount } from '../money'
  import '@/giving/giving.css'
  const auth = useAuthStore(),
        route = useRoute()
  const data = ref<MyGiving | null>(null),
        detail = ref<DonorDetail | null>(null)
  const loading = ref(false),
        working = ref(false),
        detailOpen = ref(false),
        error = ref(''),
        actionError = ref('')
  const fundId = ref(''),
        amount = ref(''),
        providerId = ref(''),
        reviewed = ref(false),
        frozen = ref(false)
  const paymentOffset = ref(0),
        giftOffset = ref(0)
  const requestKey = `divinecommunityspace.payment-request.donor.${auth.user?.id}`
  let requestId: string
    = sessionStorage.getItem(requestKey) || crypto.randomUUID()
  let overviewGeneration = 0,
      detailGeneration = 0
  const selectedProvider = computed(() =>
    data.value?.providers.find(p => p.id === providerId.value),
  )
  const selectedFund = computed(() =>
    data.value?.funds.find(f => f.id === fundId.value),
  )
  const canGive = computed(() => {
    if (!data.value?.donor || !fundId.value || !selectedProvider.value)
      return false
    return zarMinorAmount(amount.value) !== null && reviewed.value
  })
  const canCheckout = computed(
    () =>
      detail.value
      && ['PENDING', 'READY'].includes(detail.value.order.status),
  )
  function providerName (id: string) {
    return id === 'yoco' ? 'Yoco' : id
  }
  function statusLabel (status: string) {
    return (
      (
        {
          PENDING: 'Awaiting confirmation',
          READY: 'Ready for payment',
          PAID: 'Payment received',
          FAILED: 'Payment unsuccessful',
          ACTIVE: 'Active',
          PAUSED: 'Paused',
          LOCKED: 'Needs attention',
          CANCEL_REQUESTED: 'Cancellation requested',
          CANCELLED: 'Cancelled',
          NONE: 'One-off',
        } as Record<string, string>
      )[status] || status
    )
  }
  function givingLabel (status: string) {
    return (
      (
        {
          POSTED: 'Contribution recorded',
          POSTING_PENDING: 'Payment received · contribution is being recorded',
          SIMULATION: 'Simulation · no real contribution',
        } as Record<string, string>
      )[status] || status
    )
  }
  function confirmationLabel (status: string) {
    return (
      (
        {
          NOT_ISSUED: 'Not issued yet',
          QUEUED: 'Waiting to send',
          SENT: 'Submitted to mail server',
          DELIVERED: 'Delivered',
          UNKNOWN: 'Delivery uncertain',
          FAILED: 'Delivery failed',
          BLOCKED: 'Awaiting staff review',
        } as Record<string, string>
      )[status] || status
    )
  }
  async function refresh () {
    const run = ++overviewGeneration
    loading.value = true
    error.value = ''
    try {
      const result = await donorApi.overview(
        paymentOffset.value,
        giftOffset.value,
      )
      if (run !== overviewGeneration) return
      data.value = result
      if (!result.donor) {
        detailOpen.value = false
        detail.value = null
      }
      if (!providerId.value) providerId.value = result.providers[0]?.id || ''
    } catch (error_) {
      if (run === overviewGeneration) {
        data.value = null
        detail.value = null
        detailOpen.value = false
        error.value = extractErrorMessage(error_)
      }
    } finally {
      if (run === overviewGeneration) loading.value = false
    }
  }
  async function openPayment (id: string) {
    const run = ++detailGeneration
    actionError.value = ''
    try {
      const result = await donorApi.detail(id)
      if (run === detailGeneration) {
        detail.value = result
        detailOpen.value = true
      }
    } catch (error_) {
      if (run === detailGeneration) error.value = extractErrorMessage(error_)
    }
  }
  function closeDetail () {
    detailOpen.value = false
  }
  watch(detailOpen, open => {
    if (!open) {
      detailGeneration++
      detail.value = null
    }
  })
  async function give () {
    if (!canGive.value || working.value) return
    working.value = true
    error.value = ''
    frozen.value = true
    sessionStorage.setItem(requestKey, requestId)
    try {
      const created = await donorApi.start({
        requestId,
        fundId: fundId.value,
        amountMinor: zarMinorAmount(amount.value),
        provider: providerId.value,
        recurring: false,
      })
      sessionStorage.removeItem(requestKey)
      requestId = crypto.randomUUID()
      frozen.value = false
      reviewed.value = false
      await refresh()
      await openPayment(created.id)
    } catch (error_) {
      if (
        axios.isAxiosError(error_)
        && error_.response
        && error_.response.status < 500
      ) {
        frozen.value = false
        sessionStorage.removeItem(requestKey)
        requestId = crypto.randomUUID()
      }
      error.value = `${extractErrorMessage(error_)} Check payment activity before starting another gift. If the outcome is uncertain, retry this same request.`
    } finally {
      working.value = false
    }
  }
  async function reloadDetail () {
    if (detail.value) {
      await openPayment(detail.value.order.id)
      await refresh()
    }
  }
  async function continueCheckout () {
    if (!detail.value || working.value) return
    working.value = true
    actionError.value = ''
    try {
      const checkout = await donorApi.checkout(detail.value.order.id),
            target = hostedCheckout(checkout)
      if (target.post) {
        const form = document.createElement('form')
        form.method = 'POST'
        form.action = target.url
        for (const [name, value] of Object.entries(checkout.fields)) {
          const input = document.createElement('input')
          input.type = 'hidden'
          input.name = name
          input.value = value
          form.append(input)
        }
        document.body.append(form)
        form.submit()
        form.remove()
      } else {
        window.location.assign(target.url)
      }
    } catch (error_) {
      actionError.value = extractErrorMessage(error_)
    } finally {
      working.value = false
    }
  }
  function paymentPage (change: number) {
    paymentOffset.value += change
    refresh()
  }
  function giftPage (change: number) {
    giftOffset.value += change
    refresh()
  }
  onMounted(async () => {
    await refresh()
    const returned
      = typeof route.query.order === 'string'
        ? route.query.order
        : sessionStorage.getItem(requestKey)
    if (data.value?.donor && returned) {
      await openPayment(returned)
      if (detail.value) {
        sessionStorage.removeItem(requestKey)
        requestId = crypto.randomUUID()
      }
    }
  })
  onBeforeUnmount(() => {
    overviewGeneration++
    detailGeneration++
  })
</script>
<style scoped>
.donor-grid {
  display: grid;
  grid-template-columns: minmax(280px, 0.8fr) minmax(0, 1.2fr);
  gap: 24px;
  align-items: start;
}
.donor-hero {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  align-items: center;
  background: linear-gradient(120deg, #14223a, #2c4d94);
  color: white;
}
.donor-hero .directory-eyebrow {
  color: #e3d3a8;
}
.donor-hero h2 {
  font-size: clamp(1.6rem, 3vw, 2.3rem);
  margin: 10px 0;
}
.donor-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  width: 100%;
  padding: 18px 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.09);
  text-align: left;
  color: inherit;
}
.donor-order {
  transition: background 0.15s;
  border-radius: 10px;
}
.donor-order:hover {
  background: rgba(var(--v-theme-primary), 0.05);
}
.donor-row strong {
  font-size: 1.05rem;
}
.donor-row p,
.donor-row small {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.85rem;
}
.donor-badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 6px;
  max-width: 55%;
}
.donor-pages {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
  margin-top: 16px;
  font-size: 0.8rem;
}
.donor-empty {
  text-align: center;
  max-width: 700px;
  margin: 40px auto;
  padding: 45px;
}
.donor-empty p {
  margin: 20px 0;
}
.donor-charge {
  padding: 16px 0;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.donor-dialog-actions {
  flex-wrap: wrap;
  gap: 8px;
}
.donor-dialog-title {
  white-space: normal;
}
.donor-form h2 {
  margin: 10px 0;
}
@media (max-width: 960px) {
  .donor-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 480px) {
  .donor-hero > .v-icon {
    display: none;
  }
  .donor-row {
    align-items: flex-start;
  }
  .donor-badges {
    max-width: 50%;
  }
  .donor-pages {
    flex-wrap: wrap;
  }
  .donor-empty {
    padding: 26px;
  }
}
</style>
