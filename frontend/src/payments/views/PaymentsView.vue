<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="PAYMENTS & TRUST"
      subtitle="Secure hosted giving, verified charges and a clear path from provider to ledger."
      title="Every payment, accounted for."
    >
      <v-btn
        v-if="
          auth.hasPermission('people.read') && auth.hasPermission('giving.read')
        "
        color="primary"
        prepend-icon="mdi-plus"
        @click="startNew"
      >
        New online gift
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

    <section class="directory-surface giving-section mb-4">
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">HOSTED CHECKOUT</span>
          <h2>Payment providers</h2>

          <p class="directory-muted">
            Card details stay with your provider. A return page never confirms
            payment.
          </p>
        </div>

        <v-btn
          :loading="busy"
          variant="text"
          @click="refresh"
        >
          Refresh payments
        </v-btn>
      </div>

      <div class="payment-providers">
        <article
          v-for="provider in providers"
          :key="provider.id"
          class="payment-provider"
        >
          <v-icon
            color="primary"
            icon="mdi-shield-check-outline"
          />

          <div>
            <strong>{{ provider.name }}</strong>

            <p class="directory-muted">
              {{
                provider.recurring
                  ? 'One-off & monthly giving'
                  : 'One-off giving'
              }}
            </p>
          </div>

          <v-chip :color="provider.available ? 'success' : undefined">
            {{
              provider.available
                ? provider.mode === 'test'
                  ? 'Test mode'
                  : 'Live'
                : 'Not configured'
            }}
          </v-chip>
        </article>
      </div>

      <v-alert
        v-if="providers.length > 0 && !providers.some((p) => p.available)"
        class="mt-4"
        type="info"
        variant="tonal"
      >
        An administrator needs to configure a provider before online giving can
        begin.
      </v-alert>
    </section>

    <section class="directory-surface giving-section">
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">PAYMENT REGISTER</span>
          <h2>Checkouts & confirmations</h2>
        </div>

        <v-switch
          v-model="reviewOnly"
          color="primary"
          hide-details
          label="Needs follow-up"
        />
      </div>

      <v-progress-linear
        v-if="busy"
        indeterminate
      />

      <div
        v-if="!busy && rows.length === 0"
        class="giving-empty"
      >
        <v-icon
          icon="mdi-credit-card-check-outline"
          size="44"
        />

        <h2>No payments in this view</h2>
        <p>New requests appear here before a gift is posted.</p>
      </div>

      <article
        v-for="payment in rows"
        :key="payment.id"
        class="payment-row"
      >
        <div>
          <strong>{{ payment.donorName }}</strong>

          <p class="directory-muted">
            {{ payment.provider === 'payfast' ? 'Payfast' : 'Yoco' }} ·
            {{ payment.recurring ? 'Monthly' : 'One-off' }} ·
            {{ new Date(payment.createdAt).toLocaleDateString() }}
          </p>

          <p
            v-if="payment.reviewReason"
            class="text-warning"
          >
            {{ payment.reviewReason }}
          </p>
        </div>

        <div class="payment-row__amount">
          <strong>{{ money(payment.amountMinor, 'ZAR') }}</strong>

          <div>
            <v-chip
              :color="payment.status === 'PAID' ? 'success' : undefined"
              size="small"
            >
              {{ paymentLabel(payment.status, payment.mode) }}
            </v-chip>

            <v-chip
              v-if="payment.mode === 'test'"
              class="ml-1"
              size="small"
            >
              Simulation
            </v-chip>

            <p
              v-if="
                payment.mode === 'live' &&
                  payment.givingStatus &&
                  payment.status === 'PAID'
              "
              class="directory-muted mt-2"
            >
              {{
                payment.givingStatus === 'POSTED'
                  ? 'Contribution recorded in Giving'
                  : 'Giving posting needs attention'
              }}
            </p>
          </div>
        </div>

        <v-btn
          variant="tonal"
          @click="openPayment(payment.id)"
        >
          View payment
        </v-btn>
      </article>

      <div class="giving-footer">
        <span>{{ total }} requests</span>
        <v-spacer />

        <v-btn
          :disabled="offset === 0 || busy"
          variant="text"
          @click="page(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="offset + 25 >= total || busy"
          variant="text"
          @click="page(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <PaymentReceiptDialog
      v-if="detail && receiptCharge"
      v-model="receiptOpen"
      :charge-id="receiptCharge"
      :order-id="detail.order.id"
      @changed="reloadDetail"
    />

    <GivingDialog
      v-model="createOpen"
      save-label="Create checkout request"
      :submit="create"
      :submit-disabled="
        !donorId ||
          !fundId ||
          !providerId ||
          !providers.some((p) => p.id === providerId && p.available)
      "
      subtitle="Create a request, then continue to the provider’s secure payment page."
      title="Start an online gift"
      @saved="created"
    >
      <div class="payment-form">
        <PersonPicker
          v-model="donorId"
          label="Donor"
        />

        <FundPicker
          v-model="fundId"
          required
        />

        <div class="giving-fields">
          <v-text-field
            v-model="amount"
            inputmode="decimal"
            label="Amount (ZAR)"
            :rules="[validAmount]"
          />

          <v-select
            v-model="providerId"
            item-title="name"
            item-value="id"
            :items="availableProviders"
            label="Payment provider"
          />
        </div>

        <v-checkbox
          v-model="monthly"
          :disabled="!selectedProvider?.recurring"
          label="Give this amount every month until cancelled"
        />

        <v-alert
          type="info"
          variant="tonal"
        >
          {{
            selectedProvider?.mode === 'test'
              ? 'Test payments do not post real gifts.'
              : 'A gift is posted only after the provider verifies payment.'
          }}
        </v-alert>
      </div>
    </GivingDialog>

    <v-dialog
      v-model="detailOpen"
      max-width="960"
      scrollable
    >
      <v-card
        v-if="detail"
        class="directory-form"
      >
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">VERIFIED EVIDENCE</span>
            <h2>{{ detail.order.donorName }}</h2>

            <p>
              {{ money(detail.order.amountMinor, 'ZAR') }} ·
              {{
                detail.order.recurring
                  ? 'Monthly arrangement'
                  : 'One-off request'
              }}
              · {{ detail.order.mode === 'test' ? 'Test mode' : 'Live' }}
            </p>
          </div>

          <v-btn
            aria-label="Close payment"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

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
            v-if="detail.order.reviewReason"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            {{ detail.order.reviewReason }}
          </v-alert>

          <div class="giving-section-heading mb-4">
            <div>
              <strong>
                {{ paymentLabel(detail.order.status, detail.order.mode) }}
              </strong>

              <p class="directory-muted">
                {{
                  detail.order.recurring
                    ? 'Subscription: ' + label(detail.order.subscriptionStatus)
                    : detail.order.status === 'PAID'
                      ? 'Payment confirmation and bank settlement are tracked separately.'
                      : 'Awaiting checkout is not a received gift.'
                }}
              </p>
            </div>

            <v-btn
              :loading="actionBusy"
              variant="text"
              @click="reloadDetail"
            >
              Refresh status
            </v-btn>
          </div>

          <div
            v-if="
              ['PENDING', 'READY'].includes(detail.order.status) &&
                !['CANCEL_REQUESTED', 'CANCELLED'].includes(
                  detail.order.subscriptionStatus,
                )
            "
            class="payment-checkout"
          >
            <h3>Continue securely</h3>
            <p>Use this same request when retrying an interrupted checkout.</p>

            <v-btn
              class="mt-3"
              color="primary"
              :loading="actionBusy"
              prepend-icon="mdi-lock-outline"
              @click="continueCheckout"
            >
              Continue to secure checkout
            </v-btn>
          </div>

          <div
            v-if="
              detail.order.recurring &&
                detail.order.subscriptionStatus !== 'CANCELLED'
            "
            class="payment-actions my-4"
          >
            <v-btn
              :loading="actionBusy"
              variant="tonal"
              @click="subscription(false)"
            >
              Check subscription
            </v-btn>

            <v-btn
              color="error"
              :loading="actionBusy"
              variant="text"
              @click="cancelOpen = true"
            >
              Cancel monthly giving
            </v-btn>
          </div>

          <h3 class="mt-5 mb-3">Verified charges</h3>

          <p
            v-if="detail.charges.length === 0"
            class="directory-muted"
          >
            No payment has been verified. Refresh after completing checkout.
          </p>

          <article
            v-for="row in detail.charges"
            :key="row.charge.id"
            class="payment-charge"
          >
            <div class="giving-section-heading">
              <div>
                <strong>{{ money(row.charge.amountMinor, 'ZAR') }}</strong>

                <p>
                  {{ dateLabel(row.charge.receivedOn) }} ·
                  {{ row.charge.reference }}
                </p>

                <p v-if="row.refundedMinor">
                  Refunded {{ money(row.refundedMinor, 'ZAR') }}
                </p>
              </div>

              <v-chip
                v-if="detail.order.mode === 'test'"
                color="info"
              >
                Simulation · no bank settlement
              </v-chip>

              <v-chip
                v-else
                :color="row.needsReview ? 'warning' : 'success'"
              >
                {{
                  row.needsReview
                    ? 'Settlement needs review'
                    : 'Settlement matched'
                }}
              </v-chip>
            </div>

            <p
              v-if="detail.order.mode === 'test'"
              class="directory-muted"
            >
              Sandbox evidence · no real gift posted.
            </p>

            <p
              v-else-if="!row.charge.giftId"
              class="text-warning"
            >
              Payment received · Giving posting needs attention. Retry posting
              safely without collecting another payment.
            </p>

            <p
              v-if="detail.order.mode === 'live' && row.charge.giftId"
              class="text-success"
            >
              Contribution recorded in Giving.
            </p>

            <p
              v-if="row.settlement"
              class="directory-muted"
            >
              {{ row.settlement.settlementReference }} · Bank
              {{ money(row.settlement.bankMinor, 'ZAR') }} · Fees
              {{ money(row.settlement.feeMinor, 'ZAR') }}
              <br>
              {{ row.settlement.note }}
            </p>

            <div class="payment-actions mt-3">
              <v-btn
                v-if="row.charge.giftId && auth.hasPermission('giving.read')"
                :to="{ path: '/giving', query: { gift: row.charge.giftId } }"
                variant="text"
              >
                Open gift ledger
              </v-btn>

              <v-btn
                v-if="detail.order.mode === 'live' && !row.charge.giftId"
                :loading="actionBusy"
                variant="tonal"
                @click="retryPosting"
              >
                Retry Giving posting
              </v-btn>

              <v-btn
                v-if="
                  detail.order.mode === 'live' &&
                    row.charge.giftId &&
                    auth.hasPermission('payments.receipts.send')
                "
                variant="tonal"
                @click="openReceipt(row.charge.id)"
              >
                Donor confirmation
              </v-btn>

              <v-btn
                v-if="
                  detail.order.mode === 'live' &&
                    auth.hasPermission('payments.reconcile')
                "
                variant="tonal"
                @click="startReview(row)"
              >
                Review settlement
              </v-btn>

              <v-btn
                v-if="
                  auth.hasPermission('payments.refund') &&
                    row.refundedMinor < row.charge.amountMinor
                "
                color="error"
                variant="text"
                @click="startRefund(row)"
              >
                Record completed refund
              </v-btn>
            </div>
          </article>

          <h3 class="mt-5 mb-3">Refund evidence</h3>

          <p
            v-if="detail.refunds.length === 0"
            class="directory-muted"
          >
            No refunds recorded.
          </p>

          <article
            v-for="refund in detail.refunds"
            :key="refund.id"
            class="payment-refund"
          >
            <strong>
              {{ money(refund.amountMinor, 'ZAR') }} · {{ refund.reference }}
            </strong>

            <p>
              {{ dateLabel(refund.refundedOn) }} ·
              {{
                refund.applied
                  ? 'Applied to ledger'
                  : 'Awaiting matching or posting'
              }}
            </p>

            <p class="directory-muted">{{ refund.reason }}</p>
          </article>

          <h3 class="mt-5 mb-3">Recent activity</h3>

          <div
            v-for="(event, index) in detail.history"
            :key="index"
            class="giving-evidence"
          >
            <strong>{{ label(event.action) }}</strong>
            <p>{{ event.note }}</p>

            <small class="directory-muted">
              {{ new Date(event.occurredAt).toLocaleString() }}
            </small>
          </div>
        </v-card-text>

        <v-card-actions class="directory-form__footer">
          <v-spacer />
          <v-btn @click="detailOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <GivingDialog
      v-model="refundOpen"
      save-label="Record completed refund"
      :submit="recordRefund"
      subtitle="Complete the refund in the provider dashboard first. This action records evidence and updates Giving."
      title="Record a completed refund"
      @reload="reloadDetail"
      @saved="changed"
    >
      <div class="payment-form">
        <v-alert
          type="warning"
          variant="tonal"
        >
          This does not send money. Enter the exact completed provider refund
          reference. Yoco refunds must first arrive through its authenticated
          notification.
        </v-alert>

        <v-text-field
          v-model="refundReference"
          label="Provider refund reference"
          maxlength="150"
          :rules="[required]"
        />

        <div class="giving-fields">
          <v-text-field
            v-model="refundAmount"
            inputmode="decimal"
            label="Refund amount (ZAR)"
            :rules="[validPositive]"
          />

          <DateSelector
            v-model="refundDate"
            label="Completed on"
            :max="today()"
          />
        </div>

        <v-textarea
          v-model="refundReason"
          label="Completion evidence"
          maxlength="1000"
          :rules="[required]"
        />
      </div>
    </GivingDialog>

    <GivingDialog
      v-model="reviewOpen"
      save-label="Save settlement review"
      :submit="recordReview"
      subtitle="Keep fees separate from the donor’s gift. New refunds will reopen this review."
      title="Match a provider settlement"
      @reload="reloadDetail"
      @saved="changed"
    >
      <div class="payment-form">
        <v-text-field
          v-model="settlementReference"
          label="Settlement or bank reference"
          maxlength="200"
          :rules="[required]"
        />

        <div class="giving-fields">
          <v-text-field
            v-model="feeAmount"
            inputmode="decimal"
            label="Provider fees (ZAR)"
            :rules="[validZero]"
          />

          <v-text-field
            v-model="bankAmount"
            inputmode="decimal"
            label="Bank amount (ZAR)"
            :rules="[validZero]"
          />
        </div>

        <v-alert
          type="info"
          variant="tonal"
        >
          Expected bank amount: {{ expectedBank }}. Differences remain visible
          for follow-up.
        </v-alert>

        <v-textarea
          v-model="reviewNote"
          label="Review evidence"
          maxlength="1000"
          :rules="[required]"
        />
      </div>
    </GivingDialog>

    <GivingDialog
      v-model="cancelOpen"
      save-label="Request cancellation"
      :submit="cancelSubscription"
      subtitle="Future charges stop only after the provider confirms cancellation. A charge already in progress may still arrive."
      title="Cancel monthly giving"
      @reload="reloadDetail"
      @saved="changed"
    >
      <p>Cancel the monthly arrangement for {{ detail?.order.donorName }}?</p>
    </GivingDialog>
  </div>
</template>
<script setup lang="ts">
  import type { ChargeView, Detail, Payment, Provider } from '../api'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import FundPicker from '@/giving/components/FundPicker.vue'
  import GivingDialog from '@/giving/components/GivingDialog.vue'
  import { dateLabel, money, parseMinor, today } from '@/giving/money'
  import { useAuthStore } from '@/stores/auth'
  import { paymentsApi } from '../api'
  import { hostedCheckout } from '../checkout'
  import PaymentReceiptDialog from '../components/PaymentReceiptDialog.vue'
  import '@/giving/giving.css'
  const auth = useAuthStore(),
        route = useRoute()
  const providers = ref<Provider[]>([]),
        rows = ref<Payment[]>([]),
        total = ref(0),
        offset = ref(0),
        reviewOnly = ref(false),
        busy = ref(false),
        error = ref('')
  const detail = ref<Detail | null>(null),
        detailOpen = ref(false),
        actionBusy = ref(false),
        actionError = ref('')
  const receiptOpen = ref(false),
        receiptCharge = ref('')
  const createOpen = ref(false),
        donorId = ref<string | null>(null),
        fundId = ref<string | null>(null),
        providerId = ref(''),
        amount = ref(''),
        monthly = ref(false)
  const refundOpen = ref(false),
        reviewOpen = ref(false),
        cancelOpen = ref(false),
        selected = ref<ChargeView | null>(null)
  const refundReference = ref(''),
        refundAmount = ref(''),
        refundDate = ref(today()),
        refundReason = ref(''),
        settlementReference = ref(''),
        feeAmount = ref('0.00'),
        bankAmount = ref(''),
        reviewNote = ref('')
  let requestId = crypto.randomUUID(),
      reviewId = crypto.randomUUID(),
      createdId = '',
      generation = 0,
      detailGeneration = 0
  const availableProviders = computed(() =>
          providers.value.filter(p => p.available),
        ),
        selectedProvider = computed(() =>
          providers.value.find(p => p.id === providerId.value),
        )
  function required (value: string) {
    return Boolean(value?.trim()) || 'Complete this field.'
  }
  function minor (value: string, zero = false): number {
    if (zero && /^0(?:\.0{1,2})?$/.test(value.trim())) return 0
    const result = parseMinor(value, 100_000_000)
    if (result === null)
      throw new Error(
        'Enter a valid rand amount with at most two decimal places.',
      )
    return result
  }
  function validPositive (value: string) {
    try {
      minor(value)
      return true
    } catch {
      return 'Enter a positive amount with at most two decimal places.'
    }
  }
  function validZero (value: string) {
    try {
      minor(value, true)
      return true
    } catch {
      return 'Enter zero or a positive amount with at most two decimal places.'
    }
  }
  function validAmount (value: string) {
    try {
      return minor(value) >= 500 || 'The minimum gift is R5.00.'
    } catch {
      return 'Enter ZAR 5.00 to 1,000,000.00.'
    }
  }
  function paymentLabel (status: string, mode: string) {
    const value
      = (
        {
          PAID: 'Payment received',
          READY: 'Awaiting payment',
          PENDING: 'Checkout pending',
          FAILED: 'Payment failed',
        } as Record<string, string>
      )[status] || label(status)
    return mode === 'test' ? `Simulated · ${value.toLowerCase()}` : value
  }
  function openReceipt (chargeId: string) {
    receiptCharge.value = chargeId
    receiptOpen.value = true
  }
  async function retryPosting () {
    if (!detail.value || actionBusy.value) return
    actionBusy.value = true
    actionError.value = ''
    try {
      const result = await paymentsApi.retryPosting(
        detail.value.order.id,
        detail.value.order.version,
      )
      await reloadDetail()
      if (!result.complete) actionError.value = result.message
    } catch (error_) {
      actionError.value = extractErrorMessage(error_)
    } finally {
      actionBusy.value = false
    }
  }
  function label (value: string) {
    return value
      .toLowerCase()
      .replaceAll('_', ' ')
      .replace(/^./, v => v.toUpperCase())
  }
  const expectedBank = computed(() => {
    try {
      const gross = selected.value?.charge.amountMinor || 0
      const refunded = selected.value?.refundedMinor || 0
      return money(gross - refunded - minor(feeAmount.value, true), 'ZAR')
    } catch {
      return 'Enter the fee amount'
    }
  })
  async function refresh () {
    const run = ++generation
    busy.value = true
    error.value = ''
    try {
      const [p, page] = await Promise.all([
        paymentsApi.providers(),
        paymentsApi.list(offset.value, reviewOnly.value),
      ])
      if (run !== generation) return
      providers.value = p
      rows.value = page.items
      total.value = page.total
    } catch (error_) {
      if (run === generation) error.value = extractErrorMessage(error_)
    } finally {
      if (run === generation) busy.value = false
    }
  }
  async function openPayment (id: string) {
    const run = ++detailGeneration
    actionError.value = ''
    try {
      const loaded = await paymentsApi.detail(id)
      if (run !== detailGeneration) return
      detail.value = loaded
      detailOpen.value = true
    } catch (error_) {
      if (run === detailGeneration) error.value = extractErrorMessage(error_)
    }
  }
  async function reloadDetail () {
    if (!detail.value) return
    const id = detail.value.order.id
    await openPayment(id)
    if (selected.value)
      selected.value
        = detail.value?.charges.find(
          row => row.charge.id === selected.value?.charge.id,
        ) || null
    await refresh()
  }
  function page (change: number) {
    offset.value += change
    refresh()
  }
  watch(reviewOnly, () => {
    offset.value = 0
    refresh()
  })
  watch(
    () => selectedProvider.value?.recurring,
    available => {
      if (!available) monthly.value = false
    },
  )
  watch(providerId, value => {
    if (value !== 'payfast') monthly.value = false
  })
  watch(detailOpen, open => {
    if (!open) {
      detailGeneration++
      receiptOpen.value = false
      receiptCharge.value = ''
      detail.value = null
    }
  })
  function startNew () {
    requestId = crypto.randomUUID()
    donorId.value = null
    fundId.value = null
    amount.value = ''
    monthly.value = false
    providerId.value = availableProviders.value[0]?.id || ''
    createOpen.value = true
  }
  async function create () {
    const result = await paymentsApi.start({
      requestId,
      donorId: donorId.value,
      fundId: fundId.value,
      amountMinor: minor(amount.value),
      provider: providerId.value,
      recurring: monthly.value,
    })
    createdId = result.id
    return result
  }
  async function created () {
    await refresh()
    await openPayment(createdId)
  }
  async function continueCheckout () {
    if (!detail.value || actionBusy.value) return
    actionBusy.value = true
    actionError.value = ''
    try {
      const checkout = await paymentsApi.checkout(detail.value.order.id)
      const target = hostedCheckout(checkout)
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
      } else window.location.assign(target.url)
    } catch (error_) {
      actionError.value = extractErrorMessage(error_)
    } finally {
      actionBusy.value = false
    }
  }
  function startRefund (row: ChargeView) {
    selected.value = row
    refundReference.value = ''
    refundAmount.value = (
      (row.charge.amountMinor - row.refundedMinor)
      / 100
    ).toFixed(2)
    refundDate.value = today()
    refundReason.value = ''
    refundOpen.value = true
  }
  function startReview (row: ChargeView) {
    selected.value = row
    reviewId = crypto.randomUUID()
    settlementReference.value = row.settlement?.settlementReference || ''
    feeAmount.value = ((row.settlement?.feeMinor || 0) / 100).toFixed(2)
    bankAmount.value = (
      (row.charge.amountMinor
        - row.refundedMinor
        - (row.settlement?.feeMinor || 0))
      / 100
    ).toFixed(2)
    reviewNote.value = ''
    reviewOpen.value = true
  }
  async function recordRefund () {
    if (!detail.value || !selected.value)
      throw new Error('Refresh the payment first.')
    return paymentsApi.refund(detail.value.order.id, {
      chargeId: selected.value.charge.id,
      providerReference: refundReference.value,
      amountMinor: minor(refundAmount.value),
      refundedOn: refundDate.value,
      reason: refundReason.value,
    })
  }
  async function recordReview () {
    if (!detail.value || !selected.value)
      throw new Error('Refresh the payment first.')
    return paymentsApi.review(detail.value.order.id, {
      requestId: reviewId,
      chargeId: selected.value.charge.id,
      expectedVersion: selected.value.settlement?.version || 0,
      settlementReference: settlementReference.value,
      feeMinor: minor(feeAmount.value, true),
      bankMinor: minor(bankAmount.value, true),
      note: reviewNote.value,
    })
  }
  async function changed () {
    await reloadDetail()
  }
  async function subscription (cancel: boolean) {
    if (!detail.value || actionBusy.value) return
    actionBusy.value = true
    actionError.value = ''
    try {
      await paymentsApi.subscription(detail.value.order.id, {
        expectedVersion: detail.value.order.version,
        cancel,
      })
      await reloadDetail()
    } catch (error_) {
      actionError.value = extractErrorMessage(error_)
      await reloadDetail()
    } finally {
      actionBusy.value = false
    }
  }
  async function cancelSubscription () {
    if (!detail.value) throw new Error('Refresh the payment first.')
    return paymentsApi.subscription(detail.value.order.id, {
      expectedVersion: detail.value.order.version,
      cancel: true,
    })
  }
  onMounted(async () => {
    await refresh()
    if (typeof route.query.order === 'string')
      await openPayment(route.query.order)
  })
  onBeforeUnmount(() => {
    generation++
    detailGeneration++
  })
</script>
<style scoped>
.payment-form {
  display: grid;
  gap: 20px;
}
.payment-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.payment-charge,
.payment-refund {
  overflow-wrap: anywhere;
}

.payment-providers {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-top: 20px;
}
.payment-provider {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  border-radius: 16px;
}
.payment-provider > div {
  flex: 1;
}
.payment-row {
  display: flex;
  gap: 20px;
  align-items: center;
  padding: 22px 0;
  border-top: 1px solid rgba(var(--v-theme-on-surface), 0.08);
}
.payment-row > div:first-child {
  flex: 1;
}
.payment-row__amount {
  text-align: right;
}
.payment-row__amount > strong {
  font-size: 1.12rem;
}
.payment-charge,
.payment-refund,
.payment-checkout {
  padding: 20px;
  margin: 12px 0;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.09);
  border-radius: 16px;
}
.payment-checkout {
  background: rgba(var(--v-theme-primary), 0.04);
}
@media (max-width: 600px) {
  .payment-providers {
    grid-template-columns: 1fr;
  }
  .payment-provider {
    flex-wrap: wrap;
  }
  .payment-row {
    flex-wrap: wrap;
    gap: 12px;
  }
  .payment-row > div:first-child {
    flex-basis: 65%;
  }
  .payment-row__amount {
    text-align: left;
  }
  .payment-row > .v-btn {
    width: 100%;
  }
}
</style>
