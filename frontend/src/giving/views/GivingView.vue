<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="STEWARDSHIP & TRUST"
      subtitle="A clear record of generosity, purpose and responsible stewardship."
      title="Every gift, accounted for."
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-plus"
        @click="recordOpen = true"
      >
        Record gift
      </v-btn>
    </DirectoryHeading>

    <GivingNavigation />

    <v-alert
      v-if="success"
      class="mb-4"
      closable
      type="success"
      variant="tonal"
      @click:close="success = ''"
    >
      {{ success }}
    </v-alert>

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-tabs
      v-model="tab"
      class="mb-5"
      color="primary"
    >
      <v-tab value="ledger">Gift ledger</v-tab>
      <v-tab value="funds">Funds</v-tab>
    </v-tabs>

    <section
      v-if="tab === 'ledger'"
      class="directory-surface giving-section"
    >
      <div class="giving-fields">
        <DateSelector
          v-model="from"
          label="From date"
        />

        <DateSelector
          v-model="to"
          label="To date"
        />

        <v-select
          v-model="currency"
          clearable
          :items="currencies"
          label="Currency filter"
        />

        <FundPicker
          v-model="fundId"
          archived
          label="Fund filter"
        />
      </div>

      <div class="giving-section-heading">
        <span class="directory-muted">
          Totals cover the entire selected period. Currencies stay separate.
        </span>

        <v-btn
          color="primary"
          :loading="loading"
          variant="tonal"
          @click="apply"
        >
          Apply filters
        </v-btn>
      </div>

      <v-progress-linear
        v-if="loading"
        indeterminate
      />

      <div class="giving-totals">
        <article
          v-for="total in totals"
          :key="total.currency"
          class="giving-total"
        >
          <span class="directory-eyebrow">
            {{ total.currency }} · NET GIVING
          </span>

          <strong>{{ money(total.netMinor, total.currency) }}</strong>
          <span>Received {{ money(total.grossMinor, total.currency) }}</span>

          <span>
            Reversed / refunded {{ money(total.reversedMinor, total.currency) }}
          </span>
        </article>
      </div>

      <p class="directory-muted mb-4">
        Received gifts use the received date; reversals use the reversal date. A
        period may have negative net giving. The ledger below lists gifts
        received in the period. When filtering a fund, totals use its
        allocations; each ledger row still shows the whole gift.
      </p>

      <div
        v-if="!loading && gifts.length === 0"
        class="giving-empty"
      >
        <v-icon
          icon="mdi-gift-outline"
          size="42"
        />

        <h2>No gifts in this period</h2>
        <p>Choose another period or record money already received.</p>
      </div>

      <div
        v-for="gift in gifts"
        :key="gift.id"
        class="giving-ledger-row"
      >
        <div>
          <strong>{{ gift.donorName }}</strong>

          <p class="directory-muted">
            {{ dateLabel(gift.receivedOn) }} · {{ methodLabel(gift.method) }}
          </p>

          <span v-if="gift.externalReference">
            {{ gift.externalReference }}
          </span>
        </div>

        <div class="giving-ledger-row__amount">
          <strong>{{ money(gift.amountMinor, gift.currency) }}</strong>

          <v-chip
            v-if="gift.reversed"
            size="small"
            variant="tonal"
          >
            Reversed
          </v-chip>

          <v-chip
            v-if="gift.refundedMinor"
            color="warning"
            size="small"
            variant="tonal"
          >
            Refunded {{ money(gift.refundedMinor, gift.currency) }}
          </v-chip>

          <v-btn
            :aria-label="`View gift from ${gift.donorName}`"
            variant="text"
            @click="showGift(gift.id)"
          >
            View details
          </v-btn>
        </div>
      </div>

      <div class="giving-footer">
        <span>
          {{ giftTotal }} gifts · page {{ Math.floor(offset / 25) + 1 }}
        </span>

        <v-btn
          :disabled="offset === 0 || loading"
          variant="text"
          @click="pageLedger(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="offset + 25 >= giftTotal || loading"
          variant="text"
          @click="pageLedger(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <section
      v-else
      class="directory-surface giving-section"
    >
      <div class="giving-section-heading">
        <div>
          <h2>Funds with purpose</h2>

          <p class="directory-muted">
            Archiving a fund preserves every historical allocation.
          </p>
        </div>

        <v-btn
          v-if="manage"
          color="primary"
          prepend-icon="mdi-plus"
          @click="editFund()"
        >
          Create fund
        </v-btn>
      </div>

      <div class="giving-fields">
        <v-text-field
          v-model="fundSearch"
          clearable
          label="Search funds"
          prepend-inner-icon="mdi-magnify"
          @keyup.enter="searchFunds"
        />

        <v-switch
          v-model="archived"
          color="primary"
          label="Include archived funds"
          @update:model-value="searchFunds"
        />

        <v-btn
          :loading="loading"
          variant="tonal"
          @click="searchFunds"
        >
          Search
        </v-btn>
      </div>

      <div
        v-if="funds.length === 0 && !loading"
        class="giving-empty"
      >
        <h3>No funds yet</h3>
        <p>Create a fund before recording gifts.</p>
      </div>

      <div class="giving-funds">
        <article
          v-for="fund in funds"
          :key="fund.id"
          class="giving-fund"
        >
          <div class="giving-section-heading">
            <h3>{{ fund.name }}</h3>

            <v-chip
              v-if="!fund.active"
              size="small"
            >
              Archived
            </v-chip>
          </div>

          <p>
            {{
              fund.description ||
                'A designated purpose for your church’s giving.'
            }}
          </p>

          <v-btn
            v-if="manage"
            :aria-label="`Manage ${fund.name}`"
            variant="text"
            @click="editFund(fund)"
          >
            Manage fund
          </v-btn>
        </article>
      </div>

      <div class="giving-footer">
        <span>{{ fundTotal }} funds</span>

        <v-btn
          :disabled="fundOffset === 0 || loading"
          variant="text"
          @click="pageFunds(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="fundOffset + 25 >= fundTotal || loading"
          variant="text"
          @click="pageFunds(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <RecordGift
      v-model="recordOpen"
      @saved="posted"
    />

    <GivingDialog
      v-model="fundOpen"
      :submit="saveFund"
      subtitle="Give every allocation a clear purpose."
      :title="editing ? 'Manage fund' : 'Create fund'"
      @reload="reloadFunds"
      @saved="fundSaved"
    >
      <v-text-field
        v-model="fundName"
        label="Fund name"
        maxlength="100"
        :rules="[required]"
      />

      <v-textarea
        v-model="fundDescription"
        label="Description"
        maxlength="1000"
        rows="3"
      />

      <template v-if="editing">
        <v-switch
          v-model="fundActive"
          color="primary"
          label="Accept new gifts"
        />

        <v-textarea
          v-model="fundReason"
          label="Change reason"
          maxlength="1000"
          rows="2"
          :rules="[required]"
        />
      </template>
    </GivingDialog>

    <v-dialog
      v-model="detailOpen"
      max-width="760"
      scrollable
    >
      <v-card
        v-if="detail"
        class="directory-form"
      >
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">GIFT EVIDENCE</span>
            <h2>{{ detail.donorName }}</h2>

            <p>
              {{
                money(detail.gift.money.amountMinor, detail.gift.money.currency)
              }}
              · {{ dateLabel(detail.gift.receivedOn) }}
            </p>
          </div>

          <v-btn
            aria-label="Close gift details"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text>
          <p class="mb-3">
            {{ methodLabel(detail.gift.method) }}
            <span v-if="detail.gift.externalReference">
              · {{ detail.gift.externalReference }}
            </span>
          </p>

          <h3>Fund allocations</h3>

          <div
            v-for="allocation in detail.gift.allocations"
            :key="allocation.fundId"
            class="giving-section-heading giving-evidence"
          >
            <span>{{ detail.fundNames[allocation.fundId] }}</span>

            <strong>
              {{ money(allocation.amountMinor, detail.gift.money.currency) }}
            </strong>
          </div>

          <v-alert
            v-if="detail.reversal"
            class="my-4"
            type="warning"
            variant="tonal"
          >
            <strong>
              Fully reversed · {{ dateLabel(detail.reversal.reversedOn) }}
            </strong>

            <p>{{ detail.reversal.reason }}</p>
            <p>The original gift remains in the ledger.</p>
          </v-alert>

          <v-alert
            v-if="detail.gift.method === 'ONLINE'"
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Verified online gift. Manage completed refunds in Online payments.
            <span v-if="detail.refundedMinor">
              Refunded
              {{ money(detail.refundedMinor, detail.gift.money.currency) }}.
            </span>
          </v-alert>

          <GiftExtras
            :donor-id="detail.gift.donorId"
            :gift-id="detail.gift.id"
            :reversed="Boolean(detail.reversal) || detail.refundedMinor > 0"
          />

          <h3 class="mt-4">Recorded history</h3>

          <div
            v-for="(entry, index) in detail.history"
            :key="index"
            class="giving-evidence"
          >
            <strong>{{ entry.action.replaceAll('_', ' ') }}</strong>
            <p>{{ new Date(entry.occurredAt).toLocaleString() }}</p>
            <p>{{ entry.reason }}</p>
            <small class="directory-muted">Account {{ entry.actorId }}</small>
          </div>
        </v-card-text>

        <v-card-actions class="directory-form__footer">
          <v-btn
            v-if="
              auth.hasPermission('giving.reverse') &&
                !detail.reversal &&
                detail.gift.method !== 'ONLINE'
            "
            color="error"
            variant="tonal"
            @click="startReversal"
          >
            Reverse gift
          </v-btn>

          <v-spacer />
          <v-btn @click="detailOpen = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <GivingDialog
      v-model="reversalOpen"
      note="This records a ledger correction. It does not send a refund."
      save-label="Confirm full reversal"
      :submit="reverse"
      subtitle="Record a full correction while preserving the original gift."
      title="Reverse this gift"
      @saved="reversed"
    >
      <v-alert
        class="mb-4"
        type="warning"
        variant="tonal"
      >
        {{
          detail
            ? money(detail.gift.money.amountMinor, detail.gift.money.currency)
            : ''
        }}
        will be reversed across all original fund allocations.
      </v-alert>

      <DateSelector
        v-model="reversedOn"
        label="Reversed on"
      />

      <v-textarea
        v-model="reversalReason"
        label="Reversal reason"
        maxlength="1000"
        rows="3"
        :rules="[required]"
      />
    </GivingDialog>
  </div>
</template>
<script setup lang="ts">
  import type { Filter, Fund, GiftDetail, GiftSummary, Total } from '../api'
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { required } from '@/events/api'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  import FundPicker from '../components/FundPicker.vue'
  import GiftExtras from '../components/GiftExtras.vue'
  import GivingDialog from '../components/GivingDialog.vue'
  import GivingNavigation from '../components/GivingNavigation.vue'
  import RecordGift from '../components/RecordGift.vue'
  import { currencies, dateLabel, money, today } from '../money'
  import '../giving.css'
  const route = useRoute()
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('giving.manage'))
  const tab = ref('ledger'),
        error = ref(''),
        success = ref(''),
        loading = ref(false),
        from = ref(today().slice(0, 7) + '-01'),
        to = ref(today()),
        currency = ref<string | null>(null),
        fundId = ref<string | null>(null)
  const gifts = ref<GiftSummary[]>([]),
        giftTotal = ref(0),
        totals = ref<Total[]>([]),
        offset = ref(0),
        recordOpen = ref(false)
  let applied: Filter = { from: from.value, to: to.value },
      generation = 0
  async function loadLedger () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const [page, summary] = await Promise.all([
        givingApi.gifts({ ...applied, offset: offset.value, limit: 25 }),
        givingApi.totals(applied),
      ])
      if (current === generation) {
        gifts.value = page.items
        giftTotal.value = page.total
        totals.value = summary
      }
    } catch (error_) {
      if (current === generation) {
        gifts.value = []
        totals.value = []
        giftTotal.value = 0
        error.value = extractErrorMessage(error_, 'Unable to load Giving.')
      }
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function apply () {
    if (from.value > to.value) {
      error.value = 'Choose an end date on or after the start.'
      return
    }
    offset.value = 0
    applied = {
      from: from.value,
      to: to.value,
      ...(currency.value ? { currency: currency.value } : {}),
      ...(fundId.value ? { fundId: fundId.value } : {}),
    }
    loadLedger()
  }
  const funds = ref<Fund[]>([]),
        fundTotal = ref(0),
        fundSearch = ref(''),
        archived = ref(false),
        fundOffset = ref(0),
        fundOpen = ref(false),
        editing = ref<Fund | null>(null),
        fundName = ref(''),
        fundDescription = ref(''),
        fundActive = ref(true),
        fundReason = ref('')
  let fundRequest = crypto.randomUUID()
  async function loadFunds () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const result = await givingApi.funds({
        archived: archived.value,
        search: fundSearch.value || '',
        limit: 25,
        offset: fundOffset.value,
      })
      if (current === generation) {
        funds.value = result.items
        fundTotal.value = result.total
      }
    } catch (error_) {
      if (current === generation) {
        funds.value = []
        fundTotal.value = 0
        error.value = extractErrorMessage(error_, 'Unable to load funds.')
      }
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function editFund (fund?: Fund) {
    editing.value = fund || null
    fundRequest = crypto.randomUUID()
    fundName.value = fund?.name || ''
    fundDescription.value = fund?.description || ''
    fundActive.value = fund?.active ?? true
    fundReason.value = ''
    fundOpen.value = true
  }
  async function saveFund () {
    await (editing.value
      ? givingApi.updateFund(editing.value.id, {
        expectedVersion: editing.value.version,
        name: fundName.value,
        description: fundDescription.value,
        active: fundActive.value,
        reason: fundReason.value,
      })
      : givingApi.createFund({
        requestId: fundRequest,
        name: fundName.value,
        description: fundDescription.value,
      }))
  }
  function fundSaved () {
    success.value = editing.value ? 'Fund updated.' : 'Fund created.'
    loadFunds()
  }
  function posted () {
    success.value = 'Gift posted to the ledger.'
    tab.value = 'ledger'
    loadLedger()
  }
  const detail = ref<GiftDetail | null>(null),
        detailOpen = ref(false),
        reversalOpen = ref(false),
        reversedOn = ref(today()),
        reversalReason = ref('')
  let detailGeneration = 0,
      reversalRequest = crypto.randomUUID()
  async function showGift (id: string) {
    const current = ++detailGeneration
    error.value = ''
    try {
      const result = await givingApi.gift(id)
      if (current === detailGeneration) {
        detail.value = result
        detailOpen.value = true
      }
    } catch (error_) {
      if (current === detailGeneration)
        error.value = extractErrorMessage(error_, 'Unable to load this gift.')
    }
  }
  function startReversal () {
    reversedOn.value = today()
    reversalReason.value = ''
    reversalRequest = crypto.randomUUID()
    reversalOpen.value = true
  }
  async function reverse () {
    if (detail.value)
      await givingApi.reverse(detail.value.gift.id, {
        requestId: reversalRequest,
        reversedOn: reversedOn.value,
        reason: reversalReason.value,
      })
  }
  async function reversed () {
    success.value = 'Full reversal recorded. Original evidence is preserved.'
    if (detail.value) await showGift(detail.value.gift.id)
    await loadLedger()
  }
  function methodLabel (method: string) {
    return (
      {
        CASH: 'Cash',
        BANK: 'Bank transfer',
        OTHER: 'Other',
        ONLINE: 'Online payment',
      }[method] || method
    )
  }
  watch(tab, () => (tab.value === 'funds' ? loadFunds() : loadLedger()))
  function pageLedger (change: number) {
    offset.value += change
    loadLedger()
  }
  function pageFunds (change: number) {
    fundOffset.value += change
    loadFunds()
  }
  function searchFunds () {
    fundOffset.value = 0
    loadFunds()
  }
  function reloadFunds () {
    fundOpen.value = false
    loadFunds()
  }
  useCreateAction(
    () => manage.value,
    () => {
      recordOpen.value = true
    },
  )
  onMounted(async () => {
    await loadLedger()
    if (typeof route.query.gift === 'string') await showGift(route.query.gift)
  })
  onBeforeUnmount(() => {
    generation++
    detailGeneration++
  })
</script>
