<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="EVIDENCE & ACCOUNTABILITY"
      subtitle="Match recorded gifts to bank or cash evidence and keep discrepancies visible."
      title="Close the loop with confidence."
    >
      <v-btn
        v-if="canReconcile"
        color="primary"
        prepend-icon="mdi-plus"
        @click="startCreate"
      >
        Create batch
      </v-btn>
    </DirectoryHeading>

    <GivingNavigation />

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <section class="directory-surface giving-section">
      <div class="giving-section-heading">
        <h2>Reconciliation batches</h2>

        <v-btn
          :loading="loading"
          variant="text"
          @click="load"
        >
          Refresh batches
        </v-btn>
      </div>

      <v-progress-linear
        v-if="loading"
        indeterminate
      />

      <div
        v-if="rows.length === 0 && !loading"
        class="giving-empty"
      >
        <h3>No batches yet</h3>

        <p>
          Record the gifts first, then reconcile them against your bank or cash
          evidence.
        </p>
      </div>

      <article
        v-for="row in rows"
        :key="row.batch.id"
        class="giving-pledge-card"
      >
        <div class="giving-section-heading">
          <div>
            <h2>{{ row.batch.reference }}</h2>

            <p>
              {{ dateLabel(row.batch.reconciledOn) }} ·
              {{ row.batch.giftIds.length }} gifts
            </p>
          </div>

          <v-chip
            :color="row.needsReview ? 'warning' : 'primary'"
            variant="tonal"
          >
            {{
              row.needsReview
                ? 'Needs review'
                : row.review
                  ? 'Reviewed'
                  : 'Matched'
            }}
          </v-chip>
        </div>

        <p>
          Recorded {{ money(row.batch.actualMinor, row.batch.currency) }} ·
          Expected {{ money(row.batch.expectedMinor, row.batch.currency) }}
        </p>

        <p
          v-if="row.reversedMinor"
          class="my-2"
        >
          Later reversals / refunds:
          {{ money(row.reversedMinor, row.batch.currency) }}
        </p>

        <v-btn
          class="mt-3"
          variant="tonal"
          @click="show(row.batch.id)"
        >
          Open batch
        </v-btn>
      </article>

      <div class="giving-footer">
        <span>{{ total }} batches</span>

        <v-btn
          :disabled="offset === 0 || loading"
          @click="page(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="offset + 25 >= total || loading"
          @click="page(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <GivingDialog
      v-model="createOpen"
      save-label="Record batch"
      :submit="create"
      :submit-disabled="!selectedCount || expectedMinor === null"
      subtitle="Select distinct gifts in one currency and compare them with external evidence."
      title="Create reconciliation batch"
      @reload="reloadCreate"
      @saved="created"
    >
      <div class="giving-fields">
        <v-text-field
          v-model="reference"
          label="Bank or cash reference"
          maxlength="200"
          :rules="[required]"
        />

        <v-select
          v-model="currency"
          :items="currencies"
          label="Batch currency"
          @update:model-value="changeCurrency"
        />

        <DateSelector
          v-model="reconciledOn"
          label="Reconciled on"
        />
      </div>

      <v-divider class="my-4" />
      <h3>Choose up to 100 gifts</h3>

      <div class="giving-fields mt-3">
        <DateSelector
          v-model="from"
          label="Gifts received from"
        />

        <DateSelector
          v-model="to"
          label="Gifts received through"
        />

        <v-btn
          :loading="candidatesLoading"
          variant="tonal"
          @click="searchGifts"
        >
          Find gifts
        </v-btn>
      </div>

      <v-alert
        v-if="candidateError"
        class="my-3"
        type="error"
        variant="tonal"
      >
        {{ candidateError }}
      </v-alert>

      <div
        v-for="gift in candidates"
        :key="gift.id"
        class="giving-ledger-row"
      >
        <v-checkbox
          :disabled="
            gift.reversed ||
              gift.reconciled ||
              (!selected[gift.id] && selectedCount >= 100)
          "
          hide-details
          :label="`${gift.donorName} · ${money(gift.amountMinor, gift.currency)}`"
          :model-value="Boolean(selected[gift.id])"
          @update:model-value="toggle(gift)"
        >
          <template #label>
            <span>
              {{ gift.donorName }} ·
              {{ money(gift.amountMinor, gift.currency) }}
              <small class="d-block">
                {{ dateLabel(gift.receivedOn) }} ·
                {{ gift.externalReference || gift.method
                }}{{
                  gift.reversed
                    ? ' · Reversed'
                    : gift.reconciled
                      ? ' · Already reconciled'
                      : ''
                }}
              </small>
            </span>
          </template>
        </v-checkbox>
      </div>

      <div class="giving-footer">
        <v-btn
          :disabled="candidateOffset === 0 || candidatesLoading"
          @click="giftPage(-25)"
        >
          Previous gifts
        </v-btn>

        <v-btn
          :disabled="
            candidateOffset + 25 >= candidateTotal || candidatesLoading
          "
          @click="giftPage(25)"
        >
          More gifts
        </v-btn>
      </div>

      <p class="my-4">
        <strong>
          {{ selectedCount }} selected · {{ money(selectedAmount, currency) }}
        </strong>

        <v-btn
          v-if="selectedCount"
          variant="text"
          @click="clearSelection"
        >
          Clear selection
        </v-btn>
      </p>

      <v-text-field
        v-model="expected"
        inputmode="decimal"
        label="Expected amount from evidence"
        :rules="[validExpected]"
        @update:model-value="expectedManual = true"
      />

      <v-alert
        v-if="expectedMinor !== null && expectedMinor !== selectedAmount"
        class="mb-3"
        type="warning"
        variant="tonal"
      >
        This batch has a discrepancy of
        {{ money(expectedMinor - selectedAmount, currency) }} (expected minus
        recorded). An explanation is required and the batch will need review.
      </v-alert>

      <v-textarea
        v-model="reason"
        label="Batch evidence or discrepancy explanation"
        maxlength="1000"
        rows="3"
        :rules="expectedMinor !== selectedAmount ? [required] : []"
      />
    </GivingDialog>

    <v-dialog
      v-model="detailOpen"
      max-width="900"
      scrollable
    >
      <v-card
        v-if="detail"
        class="directory-form"
      >
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">RECONCILIATION EVIDENCE</span>
            <h2>{{ detail.summary.batch.reference }}</h2>
            <p>{{ dateLabel(detail.summary.batch.reconciledOn) }}</p>
          </div>

          <v-btn
            aria-label="Close batch details"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text>
          <v-alert
            class="mb-4"
            :type="detail.summary.needsReview ? 'warning' : 'success'"
            variant="tonal"
          >
            {{
              detail.summary.needsReview
                ? 'This batch needs review.'
                : 'The current batch evidence has been matched or explained.'
            }}
          </v-alert>

          <div class="giving-totals">
            <article class="giving-total">
              <span>Original recorded gifts</span>

              <strong>
                {{
                  money(
                    detail.summary.batch.actualMinor,
                    detail.summary.batch.currency,
                  )
                }}
              </strong>

              <span>
                Expected
                {{
                  money(
                    detail.summary.batch.expectedMinor,
                    detail.summary.batch.currency,
                  )
                }}
              </span>
            </article>

            <article class="giving-total">
              <span>Current net after reversals</span>

              <strong>
                {{
                  money(
                    detail.summary.currentNetMinor,
                    detail.summary.batch.currency,
                  )
                }}
              </strong>

              <span>
                Reversed
                {{
                  money(
                    detail.summary.reversedMinor,
                    detail.summary.batch.currency,
                  )
                }}
              </span>
            </article>
          </div>

          <p class="giving-evidence">{{ detail.summary.batch.reason }}</p>
          <h3 class="my-4">Selected gifts</h3>

          <div
            v-for="item in detail.items"
            :key="item.giftId"
            class="giving-ledger-row"
          >
            <div>
              <strong>{{ item.donorName }}</strong>

              <p>
                {{ dateLabel(item.receivedOn) }}
                <span v-if="item.reversed">
                  · Reversed after reconciliation
                </span>
              </p>
            </div>

            <strong>
              {{ money(item.amountMinor, detail.summary.batch.currency) }}
            </strong>
          </div>

          <div class="giving-section-heading mt-5">
            <h3>Review history</h3>

            <v-btn
              v-if="canReconcile"
              variant="tonal"
              @click="startReview"
            >
              Record review
            </v-btn>
          </div>

          <p
            v-if="detail.reviews.length === 0"
            class="directory-muted"
          >
            No review has been recorded.
          </p>

          <div
            v-for="review in detail.reviews"
            :key="review.id"
            class="giving-evidence"
          >
            <strong>
              {{
                review.outcome === 'EXPLAINED'
                  ? 'Explained'
                  : 'Follow-up needed'
              }}
              · revision {{ review.version }}
            </strong>

            <p>{{ review.note }}</p>

            <small>
              {{ new Date(review.createdAt).toLocaleString() }} · Account
              {{ review.createdBy }}
            </small>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn @click="detailOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <GivingDialog
      v-model="reviewOpen"
      save-label="Save review"
      :submit="review"
      subtitle="Explain the current discrepancy or record the follow-up still required."
      title="Record reconciliation review"
      @reload="reloadReview"
      @saved="reviewed"
    >
      <v-select
        v-model="outcome"
        :items="[
          { title: 'Follow-up needed', value: 'FOLLOW_UP' },
          { title: 'Explained with evidence', value: 'EXPLAINED' },
        ]"
        label="Review outcome"
      />

      <v-textarea
        v-model="note"
        label="Review evidence"
        maxlength="1000"
        rows="4"
        :rules="[required]"
      />

      <p class="directory-muted">
        The original batch stays unchanged. Later reversals can reopen the
        review flag.
      </p>
    </GivingDialog>
  </div>
</template>
<script setup lang="ts">
  import type {
    GiftSummary,
    ReconciliationDetail,
    ReconciliationSummary,
  } from '../api'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  import GivingDialog from '../components/GivingDialog.vue'
  import GivingNavigation from '../components/GivingNavigation.vue'
  import { currencies, dateLabel, money, parseMinor, today } from '../money'
  import '../giving.css'
  const auth = useAuthStore(),
        canReconcile = computed(() => auth.hasPermission('giving.reconcile'))
  const rows = ref<ReconciliationSummary[]>([]),
        total = ref(0),
        offset = ref(0),
        loading = ref(false),
        error = ref('')
  let generation = 0
  function required (value: unknown) {
    return Boolean(value) || 'This field is required.'
  }
  async function load () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const result = await givingApi.reconciliations({
        limit: 25,
        offset: offset.value,
      })
      if (current === generation) {
        rows.value = result.items
        total.value = result.total
      }
    } catch (error_) {
      if (current === generation) {
        rows.value = []
        error.value = extractErrorMessage(error_)
      }
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function page (change: number) {
    offset.value += change
    load()
  }
  const createOpen = ref(false),
        reference = ref(''),
        currency = ref('ZAR'),
        reconciledOn = ref(today()),
        expected = ref(''),
        reason = ref(''),
        expectedManual = ref(false),
        from = ref(''),
        to = ref(today()),
        selected = ref<Record<string, GiftSummary>>({}),
        candidates = ref<GiftSummary[]>([]),
        candidateOffset = ref(0),
        candidateTotal = ref(0),
        candidatesLoading = ref(false),
        candidateError = ref('')
  let requestId = crypto.randomUUID(),
      candidateGeneration = 0
  const selectedCount = computed(() => Object.keys(selected.value).length),
        selectedAmount = computed(() =>
          Object.values(selected.value).reduce(
            (sum, gift) => sum + gift.amountMinor,
            0,
          ),
        ),
        expectedMinor = computed(() =>
          parseMinor(expected.value, 100_000_000_000_000),
        )
  function validExpected (value: string) {
    return (
      parseMinor(value, 100_000_000_000_000) !== null
      || 'Enter a positive amount with up to two decimals.'
    )
  }
  watch(selectedAmount, value => {
    if (!expectedManual.value)
      expected.value = value ? (value / 100).toFixed(2) : ''
  })
  function startCreate () {
    requestId = crypto.randomUUID()
    reference.value = ''
    currency.value = 'ZAR'
    reconciledOn.value = today()
    expectedManual.value = false
    selected.value = {}
    expected.value = ''
    reason.value = ''
    from.value = today().slice(0, 7) + '-01'
    to.value = today()
    candidateOffset.value = 0
    createOpen.value = true
    loadGifts()
  }
  async function loadGifts () {
    const current = ++candidateGeneration
    candidatesLoading.value = true
    candidateError.value = ''
    try {
      const result = await givingApi.gifts({
        from: from.value,
        to: to.value,
        currency: currency.value,
        limit: 25,
        offset: candidateOffset.value,
      })
      if (current === candidateGeneration) {
        candidates.value = result.items
        candidateTotal.value = result.total
      }
    } catch (error_) {
      if (current === candidateGeneration) {
        candidates.value = []
        candidateError.value = extractErrorMessage(error_)
      }
    } finally {
      if (current === candidateGeneration) candidatesLoading.value = false
    }
  }
  function changeCurrency () {
    clearSelection()
    candidateOffset.value = 0
    loadGifts()
  }
  function clearSelection () {
    selected.value = {}
    expectedManual.value = false
    expected.value = ''
  }
  function toggle (gift: GiftSummary) {
    const next = { ...selected.value }
    if (next[gift.id]) delete next[gift.id]
    else next[gift.id] = gift
    selected.value = next
  }
  function searchGifts () {
    candidateOffset.value = 0
    loadGifts()
  }
  function giftPage (change: number) {
    candidateOffset.value += change
    loadGifts()
  }
  async function create () {
    await givingApi.reconcile({
      requestId,
      reference: reference.value,
      expectedMinor: expectedMinor.value,
      currency: currency.value,
      reconciledOn: reconciledOn.value,
      reason: reason.value,
      giftIds: Object.keys(selected.value),
    })
  }
  async function created () {
    offset.value = 0
    await load()
  }
  async function reloadCreate () {
    createOpen.value = false
    await load()
  }
  const detail = ref<ReconciliationDetail | null>(null),
        detailOpen = ref(false)
  let detailGeneration = 0
  async function show (id: string) {
    const current = ++detailGeneration
    error.value = ''
    try {
      const result = await givingApi.reconciliation(id)
      if (current === detailGeneration) {
        detail.value = result
        detailOpen.value = true
      }
    } catch (error_) {
      if (current === detailGeneration) error.value = extractErrorMessage(error_)
    }
  }
  const reviewOpen = ref(false),
        outcome = ref('FOLLOW_UP'),
        note = ref('')
  let reviewRequest = crypto.randomUUID()
  function startReview () {
    reviewRequest = crypto.randomUUID()
    outcome.value = 'FOLLOW_UP'
    note.value = ''
    reviewOpen.value = true
  }
  async function review () {
    if (detail.value)
      await givingApi.reviewReconciliation(detail.value.summary.batch.id, {
        requestId: reviewRequest,
        expectedVersion: detail.value.summary.version,
        outcome: outcome.value,
        note: note.value,
      })
  }
  async function reviewed () {
    if (detail.value) await show(detail.value.summary.batch.id)
    await load()
  }
  async function reloadReview () {
    reviewOpen.value = false
    await reviewed()
  }
  onMounted(load)
  onBeforeUnmount(() => {
    generation++
    detailGeneration++
    candidateGeneration++
  })
</script>
