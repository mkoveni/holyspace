<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="COMMITMENT & GENEROSITY"
      subtitle="Track commitments through explicit gift assignments and a clear history."
      title="Turn intention into impact."
    >
      <v-btn
        v-if="manage && auth.hasPermission('people.read')"
        color="primary"
        prepend-icon="mdi-plus"
        @click="startCreate"
      >
        Create pledge
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
      <div class="giving-fields">
        <v-select
          v-model="status"
          clearable
          hide-details
          :items="[
            { title: 'Current commitments', value: 'OPEN' },
            { title: 'Cancelled', value: 'CANCELLED' },
          ]"
          label="Pledge status"
        />

        <v-btn
          :loading="loading"
          variant="tonal"
          @click="refresh"
        >
          Refresh pledges
        </v-btn>
      </div>

      <p class="directory-muted my-4">
        Gifts count only after assignment. Progress reflects current records and
        excludes reversed gifts.
      </p>

      <v-progress-linear
        v-if="loading"
        indeterminate
      />

      <div
        v-if="!loading && rows.length === 0"
        class="giving-empty"
      >
        <v-icon
          icon="mdi-hand-heart-outline"
          size="42"
        />

        <h2>No pledges in this view</h2>
        <p>Create a donor commitment to begin tracking progress.</p>
      </div>

      <article
        v-for="row in rows"
        :key="row.pledge.id"
        class="giving-pledge-card"
      >
        <div class="giving-section-heading">
          <div>
            <h2>{{ row.donorName }}</h2>

            <p>
              {{ row.fundName }} · {{ dateLabel(row.pledge.startsOn) }} –
              {{ dateLabel(row.pledge.endsOn) }}
            </p>
          </div>

          <v-chip>
            {{
              row.pledge.status === 'CANCELLED'
                ? 'Cancelled'
                : row.fulfilled
                  ? 'Fulfilled'
                  : 'Open'
            }}
          </v-chip>
        </div>

        <v-progress-linear
          bg-opacity="0.12"
          color="primary"
          height="8"
          :model-value="
            (row.creditedMinor / row.pledge.target.amountMinor) * 100
          "
          rounded
        />

        <div class="giving-section-heading mt-3">
          <span>
            {{ money(row.creditedMinor, row.pledge.target.currency) }} of
            {{
              money(row.pledge.target.amountMinor, row.pledge.target.currency)
            }}
          </span>

          <v-btn
            variant="tonal"
            @click="openPledge(row.pledge.id)"
          >
            Open pledge
          </v-btn>
        </div>
      </article>

      <div class="giving-footer">
        <span>{{ total }} commitments</span>

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
      save-label="Create pledge"
      :submit="create"
      :submit-disabled="!donorId || !fundId || parseMinor(amount) === null"
      subtitle="Record a donor’s commitment to a fund and period."
      title="Create a pledge"
      @saved="refresh"
    >
      <PersonPicker
        v-model="donorId"
        label="Pledge donor"
      />

      <FundPicker
        v-model="fundId"
        label="Pledge fund"
        required
      />

      <div class="giving-fields mt-4">
        <v-text-field
          v-model="amount"
          inputmode="decimal"
          label="Target amount"
          :rules="[validMoney]"
        />

        <v-select
          v-model="currency"
          :items="currencies"
          label="Pledge currency"
        />

        <DateSelector
          v-model="startsOn"
          label="Commitment starts"
        />

        <DateSelector
          v-model="endsOn"
          label="Commitment ends"
        />
      </div>
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
            <span class="directory-eyebrow">PLEDGE PROGRESS</span>
            <h2>{{ detail.summary.donorName }}</h2>
            <p>{{ detail.summary.fundName }}</p>
          </div>

          <v-btn
            aria-label="Close pledge details"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text>
          <div class="giving-totals">
            <article class="giving-total">
              <span>Assigned gifts</span>

              <strong>
                {{
                  money(
                    detail.summary.creditedMinor,
                    detail.summary.pledge.target.currency,
                  )
                }}
              </strong>

              <span>
                Remaining
                {{
                  money(
                    detail.summary.remainingMinor,
                    detail.summary.pledge.target.currency,
                  )
                }}
              </span>
            </article>
          </div>

          <p
            v-if="detail.summary.pledge.cancellationReason"
            class="my-3"
          >
            Cancelled: {{ detail.summary.pledge.cancellationReason }}
          </p>

          <div class="giving-section-heading">
            <h3>Gift assignments</h3>

            <v-btn
              v-if="
                manage &&
                  detail.summary.pledge.status === 'OPEN' &&
                  detail.summary.remainingMinor > 0
              "
              variant="tonal"
              @click="startAssign"
            >
              Assign a gift
            </v-btn>
          </div>

          <p
            v-if="detail.assignments.length === 0"
            class="directory-muted my-4"
          >
            No gifts have been assigned.
          </p>

          <div
            v-for="row in detail.assignments"
            :key="row.assignment.id"
            class="giving-ledger-row"
          >
            <div>
              <strong>
                {{
                  money(
                    row.assignment.amountMinor,
                    detail.summary.pledge.target.currency,
                  )
                }}
              </strong>

              <p>
                {{ dateLabel(row.receivedOn) }} ·
                {{
                  row.giftReversed
                    ? 'Gift reversed'
                    : row.assignment.status === 'CANCELLED'
                      ? 'Assignment cancelled'
                      : 'Assigned'
                }}
              </p>

              <p>{{ row.assignment.cancellationReason }}</p>
            </div>

            <v-btn
              v-if="manage && row.assignment.status === 'ACTIVE'"
              variant="text"
              @click="startCancelAssignment(row)"
            >
              Cancel assignment
            </v-btn>
          </div>

          <div class="giving-footer">
            <v-btn
              :disabled="assignmentOffset === 0"
              @click="assignmentPage(-25)"
            >
              Earlier page
            </v-btn>

            <v-btn
              :disabled="detail.assignments.length < 25"
              @click="assignmentPage(25)"
            >
              More assignments
            </v-btn>
          </div>

          <h3 class="mt-4">Recent history</h3>

          <div
            v-for="(entry, index) in detail.history"
            :key="index"
            class="giving-evidence"
          >
            <strong>{{ entry.action.replaceAll('_', ' ') }}</strong>
            <p>{{ entry.reason }}</p>
            <small>{{ new Date(entry.occurredAt).toLocaleString() }}</small>
          </div>
        </v-card-text>

        <v-card-actions class="directory-form__footer">
          <v-btn
            v-if="manage && detail.summary.pledge.status === 'OPEN'"
            color="error"
            variant="text"
            @click="startCancelPledge"
          >
            Cancel pledge
          </v-btn>

          <v-spacer />
          <v-btn @click="detailOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <GivingDialog
      v-model="assignOpen"
      save-label="Assign gift"
      :submit="assign"
      :submit-disabled="!candidate || parseMinor(assignmentAmount) === null"
      subtitle="Choose an eligible gift and the amount to count toward this pledge."
      title="Assign a gift"
      @reload="reloadAssignment"
      @saved="reloadDetail"
    >
      <v-alert
        v-if="candidateError"
        class="mb-3"
        type="error"
        variant="tonal"
      >
        {{ candidateError }}
      </v-alert>

      <v-select
        v-model="candidateId"
        :items="
          candidates.map((row) => ({
            value: row.giftId,
            title: `${dateLabel(row.receivedOn)} · ${money(row.availableMinor, detail?.summary.pledge.target.currency || 'ZAR')} available · ${row.reference || row.method}`,
          }))
        "
        label="Eligible gift"
        :loading="candidatesLoading"
      />

      <div class="giving-footer">
        <v-btn
          :disabled="candidateOffset === 0 || candidatesLoading"
          @click="candidatePage(-25)"
        >
          Previous gifts
        </v-btn>

        <v-btn
          :disabled="candidates.length < 25 || candidatesLoading"
          @click="candidatePage(25)"
        >
          More gifts
        </v-btn>
      </div>

      <p
        v-if="!candidatesLoading && candidates.length === 0"
        class="directory-muted"
      >
        No available gifts match this donor, fund, currency and commitment
        period.
      </p>

      <v-text-field
        v-model="assignmentAmount"
        inputmode="decimal"
        label="Amount to assign"
        :rules="[validMoney]"
      />
    </GivingDialog>

    <GivingDialog
      v-model="cancelOpen"
      :submit="cancel"
      subtitle="Keep the history and explain the correction."
      :title="cancelTarget ? 'Cancel assignment' : 'Cancel pledge'"
      @reload="cancelOpen = false"
      @saved="cancelled"
    >
      <v-textarea
        v-model="reason"
        label="Cancellation reason"
        maxlength="1000"
        rows="3"
        :rules="[required]"
      />
    </GivingDialog>
  </div>
</template>
<script setup lang="ts">
  import type {
    Assignment,
    PledgeCandidate,
    PledgeDetail,
    PledgeSummary,
  } from '../api'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  import FundPicker from '../components/FundPicker.vue'
  import GivingDialog from '../components/GivingDialog.vue'
  import GivingNavigation from '../components/GivingNavigation.vue'
  import { currencies, dateLabel, money, parseMinor, today } from '../money'
  import '../giving.css'
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('giving.manage'))
  const rows = ref<PledgeSummary[]>([]),
        total = ref(0),
        offset = ref(0),
        status = ref<string | null>('OPEN'),
        loading = ref(false),
        error = ref('')
  let generation = 0
  const required = (v: unknown) => Boolean(v) || 'This field is required.'
  function validMoney (v: string) {
    return (
      parseMinor(v) !== null
      || 'Enter a positive amount with up to two decimals.'
    )
  }
  async function load () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const data = await givingApi.pledges({
        status: status.value || undefined,
        limit: 25,
        offset: offset.value,
      })
      if (current === generation) {
        rows.value = data.items
        total.value = data.total
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
  function refresh () {
    offset.value = 0
    load()
  }
  function page (change: number) {
    offset.value += change
    load()
  }
  const createOpen = ref(false),
        donorId = ref<string | null>(null),
        fundId = ref<string | null>(null),
        amount = ref(''),
        currency = ref('ZAR'),
        startsOn = ref(''),
        endsOn = ref('')
  let requestId = crypto.randomUUID()
  function startCreate () {
    requestId = crypto.randomUUID()
    donorId.value = null
    fundId.value = null
    amount.value = ''
    currency.value = 'ZAR'
    startsOn.value = today().slice(0, 4) + '-01-01'
    endsOn.value = today().slice(0, 4) + '-12-31'
    createOpen.value = true
  }
  async function create () {
    await givingApi.createPledge({
      requestId,
      donorId: donorId.value,
      fundId: fundId.value,
      amountMinor: parseMinor(amount.value),
      currency: currency.value,
      startsOn: startsOn.value,
      endsOn: endsOn.value,
    })
  }
  const detail = ref<PledgeDetail | null>(null),
        detailOpen = ref(false),
        assignmentOffset = ref(0)
  let detailGeneration = 0
  async function show (id: string) {
    const current = ++detailGeneration
    error.value = ''
    try {
      const result = await givingApi.pledge(id, {
        limit: 25,
        offset: assignmentOffset.value,
      })
      if (current === detailGeneration) {
        detail.value = result
        detailOpen.value = true
      }
    } catch (error_) {
      if (current === detailGeneration)
        error.value = extractErrorMessage(error_)
    }
  }
  function openPledge (id: string) {
    assignmentOffset.value = 0
    show(id)
  }
  async function reloadDetail () {
    if (detail.value) await show(detail.value.summary.pledge.id)
    await load()
  }
  function assignmentPage (change: number) {
    assignmentOffset.value += change
    if (detail.value) show(detail.value.summary.pledge.id)
  }
  const assignOpen = ref(false),
        candidates = ref<PledgeCandidate[]>([]),
        candidateId = ref<string | null>(null),
        candidateOffset = ref(0),
        assignmentAmount = ref(''),
        candidateError = ref(''),
        candidatesLoading = ref(false)
  let assignmentRequest = crypto.randomUUID(),
      candidateGeneration = 0
  const candidate = computed(() =>
    candidates.value.find(row => row.giftId === candidateId.value),
  )
  watch(candidate, selected => {
    if (selected && detail.value)
      assignmentAmount.value = (
        Math.min(selected.availableMinor, detail.value.summary.remainingMinor)
        / 100
      ).toFixed(2)
  })
  async function loadCandidates () {
    if (!detail.value) return
    const current = ++candidateGeneration
    candidateId.value = null
    candidatesLoading.value = true
    candidateError.value = ''
    try {
      const result = await givingApi.candidates(
        detail.value.summary.pledge.id,
        { limit: 25, offset: candidateOffset.value },
      )
      if (current === candidateGeneration) candidates.value = result
    } catch (error_) {
      if (current === candidateGeneration)
        candidateError.value = extractErrorMessage(error_)
    } finally {
      if (current === candidateGeneration) candidatesLoading.value = false
    }
  }
  function startAssign () {
    assignmentRequest = crypto.randomUUID()
    candidateOffset.value = 0
    assignmentAmount.value = ''
    assignOpen.value = true
    loadCandidates()
  }
  function candidatePage (change: number) {
    candidateOffset.value += change
    loadCandidates()
  }
  async function assign () {
    if (detail.value)
      await givingApi.assign(detail.value.summary.pledge.id, {
        requestId: assignmentRequest,
        giftId: candidateId.value,
        expectedVersion: detail.value.summary.pledge.version,
        amountMinor: parseMinor(assignmentAmount.value),
      })
  }
  async function reloadAssignment () {
    assignOpen.value = false
    await reloadDetail()
  }
  const cancelOpen = ref(false),
        cancelTarget = ref<Assignment | null>(null),
        reason = ref('')
  function startCancelAssignment (row: Assignment) {
    cancelTarget.value = row
    reason.value = ''
    cancelOpen.value = true
  }
  function startCancelPledge () {
    cancelTarget.value = null
    reason.value = ''
    cancelOpen.value = true
  }
  async function cancel () {
    if (cancelTarget.value)
      await givingApi.cancelAssignment(cancelTarget.value.assignment.id, {
        expectedVersion: cancelTarget.value.assignment.version,
        reason: reason.value,
      })
    else if (detail.value)
      await givingApi.cancelPledge(detail.value.summary.pledge.id, {
        expectedVersion: detail.value.summary.pledge.version,
        reason: reason.value,
      })
  }
  async function cancelled () {
    await reloadDetail()
  }
  onMounted(load)
  onBeforeUnmount(() => {
    generation++
    detailGeneration++
    candidateGeneration++
  })
</script>
