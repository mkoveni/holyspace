<template>
  <GivingDialog
    v-model="open"
    note="Posted amounts stay unchanged. Corrections use a full reversal."
    save-label="Post gift"
    :submit="save"
    :submit-disabled="!balanced"
    subtitle="Record money already received and allocate the full amount."
    title="Record a gift"
    @saved="$emit('saved')"
  >
    <div class="giving-fields">
      <v-text-field
        v-model="amount"
        inputmode="decimal"
        label="Gift amount"
        placeholder="0.00"
        :rules="[
          (value) =>
            parseMinor(value) !== null ||
            'Enter a positive amount with at most two decimals.',
        ]"
      />

      <v-select
        v-model="currency"
        :items="currencies"
        label="Currency"
      />

      <DateSelector
        v-model="receivedOn"
        label="Received on"
      />

      <v-select
        v-model="method"
        :items="[
          { title: 'Cash', value: 'CASH' },
          { title: 'Bank transfer', value: 'BANK' },
          { title: 'Other', value: 'OTHER' },
        ]"
        label="Payment method"
      />
    </div>

    <v-text-field
      v-model="reference"
      hint="A bank or manual reference. References must be unique."
      label="External reference (optional)"
      maxlength="200"
      persistent-hint
    />

    <v-switch
      v-model="anonymous"
      color="primary"
      :disabled="!auth.hasPermission('people.read')"
      label="Anonymous gift"
    />

    <PersonPicker
      v-if="!anonymous && auth.hasPermission('people.read')"
      v-model="donorId"
      label="Donor"
    />

    <v-alert
      v-if="!anonymous && !donorId"
      class="mb-4"
      type="info"
      variant="tonal"
    >
      Choose the person who gave this gift.
    </v-alert>

    <p
      v-if="!auth.hasPermission('people.read')"
      class="directory-muted"
    >
      People access is needed to choose a named donor.
    </p>

    <div class="giving-section-heading">
      <h3>Fund allocations</h3>

      <v-btn
        :disabled="allocations.length >= 20"
        prepend-icon="mdi-plus"
        variant="text"
        @click="addAllocation"
      >
        Add allocation
      </v-btn>
    </div>

    <div
      v-for="(row, index) in allocations"
      :key="row.key"
      class="giving-allocation"
    >
      <FundPicker
        v-model="row.fundId"
        :label="`Fund ${index + 1}`"
        required
      />

      <v-text-field
        v-model="row.amount"
        inputmode="decimal"
        :label="`Allocation ${index + 1} amount`"
        :readonly="allocations.length === 1"
        :rules="[
          (value) => parseMinor(value) !== null || 'Enter a positive amount.',
        ]"
      />

      <v-btn
        v-if="allocations.length > 1"
        :aria-label="`Remove allocation ${index + 1}`"
        icon="mdi-close"
        variant="text"
        @click="allocations.splice(index, 1)"
      />
    </div>

    <v-alert
      class="mt-3"
      :type="balanced ? 'success' : 'info'"
      variant="tonal"
    >
      {{
        balanced
          ? 'The full gift is allocated.'
          : 'Choose distinct funds and allocate the full gift before posting.'
      }}
      {{ money(allocated, currency) }} allocated.
    </v-alert>
  </GivingDialog>
</template>
<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  import { currencies, money, parseMinor, today } from '../money'
  import FundPicker from './FundPicker.vue'
  import GivingDialog from './GivingDialog.vue'
  const open = defineModel<boolean>({ required: true })
  defineEmits<{ saved: [] }>()
  const auth = useAuthStore(),
        amount = ref(''),
        currency = ref('ZAR'),
        receivedOn = ref(today()),
        method = ref('BANK'),
        reference = ref(''),
        anonymous = ref(true),
        donorId = ref<string | null>(null)
  const allocations = ref([
    { key: crypto.randomUUID(), fundId: null as string | null, amount: '' },
  ])
  let requestId = crypto.randomUUID()
  const allocated = computed(() =>
    allocations.value.reduce(
      (sum, row) => sum + (parseMinor(row.amount) ?? 0),
      0,
    ),
  )
  const balanced = computed(() => {
    const total = parseMinor(amount.value),
          validDonor = anonymous.value || Boolean(donorId.value)
    const funds = allocations.value.map(row => row.fundId)
    return (
      total !== null
      && allocated.value === total
      && validDonor
      && funds.every(Boolean)
      && new Set(funds).size === funds.length
      && allocations.value.every(row => parseMinor(row.amount) !== null)
    )
  })
  watch([amount, () => allocations.value.length], () => {
    if (allocations.value.length === 1)
      allocations.value[0]!.amount = amount.value
  })
  watch(open, value => {
    if (value) {
      requestId = crypto.randomUUID()
      amount.value = ''
      currency.value = 'ZAR'
      receivedOn.value = today()
      method.value = 'BANK'
      reference.value = ''
      anonymous.value = true
      donorId.value = null
      allocations.value = [
        { key: crypto.randomUUID(), fundId: null, amount: '' },
      ]
    }
  })
  function addAllocation () {
    allocations.value.push({
      key: crypto.randomUUID(),
      fundId: null,
      amount: '',
    })
  }
  async function save () {
    if (!balanced.value)
      throw new Error('Allocate the entire gift before posting.')
    await givingApi.record({
      requestId,
      donorId: anonymous.value ? null : donorId.value,
      amountMinor: parseMinor(amount.value),
      currency: currency.value,
      receivedOn: receivedOn.value,
      method: method.value,
      externalReference: reference.value.trim() || null,
      allocations: allocations.value.map(row => ({
        fundId: row.fundId,
        amountMinor: parseMinor(row.amount),
      })),
    })
  }
</script>
