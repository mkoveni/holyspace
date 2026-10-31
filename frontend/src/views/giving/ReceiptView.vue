<template>
  <div>
    <PageHeader subtitle="Donation acknowledgement" title="Acknowledgement">
      <template #actions><v-btn to="/giving" variant="text">Back to Giving</v-btn><v-btn :disabled="!details" prepend-icon="mdi-printer" variant="flat" @click="printReceipt">Print</v-btn></template>
    </PageHeader>

    <v-alert v-if="error" type="error">{{ error }}</v-alert>

    <v-card v-if="details" class="pa-6">
      <v-alert v-if="details.receipt.void" class="mb-4" type="warning">VOID — this donation has been reversed.</v-alert>
      <h2 class="mb-4">Donation acknowledgement</h2>
      <p><strong>Number:</strong> {{ details.receipt.receiptNumber }}</p>
      <p><strong>Donor:</strong> {{ details.donor?.name ?? 'Anonymous' }}</p>
      <p><strong>Amount:</strong> {{ amount }}</p>
      <p><strong>Received:</strong> {{ details.gift.receivedOn }}</p>
      <p><strong>Issued:</strong> {{ details.receipt.issuedOn }}</p>
      <p class="mt-4 text-caption">{{ details.notice }}</p>
    </v-card>
  </div>
</template>
<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  const props = defineProps<{ id: string }>()
  const details = ref<{ receipt: { receiptNumber: string, amountMinor: number, currency: string, issuedOn: string, void: boolean }, gift: { receivedOn: string }, donor: { name: string } | null, notice: string } | null>(null)
  const error = ref('')
  const amount = computed(() => {
    if (!details.value) return ''
    const formatter = new Intl.NumberFormat(undefined, { style: 'currency', currency: details.value.receipt.currency })
    const digits = formatter.resolvedOptions().maximumFractionDigits ?? 2
    return formatter.format(details.value.receipt.amountMinor / 10 ** digits)
  })
  function printReceipt () {
    window.print()
  }
  watch(() => props.id, async id => {
    details.value = null
    error.value = ''
    try {
      details.value = (await useApi().get(`/api/giving/receipts/${id}`)).data
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Unable to load acknowledgement.')
    }
  }, { immediate: true })
</script>
<style>
@media print {
  .v-navigation-drawer, .v-app-bar, .v-btn, .page-header { display: none !important; }
  .v-main { padding: 0 !important; }
}
</style>
