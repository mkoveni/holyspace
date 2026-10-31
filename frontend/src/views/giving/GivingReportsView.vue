<template>
  <div>
    <PageHeader title="Giving statements and reports" subtitle="Donor statements, fund exports, and explicit pledge progress" class="report-controls"><template #actions><v-btn to="/giving">Back to Giving</v-btn></template></PageHeader>
    <v-alert v-if="error" type="error" class="mb-4 report-controls">{{ error }}</v-alert>
    <v-card class="mb-4 report-controls"><v-card-text><v-form ref="filtersForm" @submit.prevent="load">
      <v-row><v-col cols="12" sm="6"><v-text-field v-model="from" label="Period start" type="date" :rules="[required]" /></v-col><v-col cols="12" sm="6"><v-text-field v-model="to" label="Period end" type="date" :rules="[required]" /></v-col></v-row>
      <PeopleSearch v-if="access.hasPermission('people.read')" v-model="donorId" label="Statement donor (optional)" clearable />
      <v-select v-model="fundId" :items="fundOptions" label="Fund (optional)" clearable />
      <v-btn type="submit" color="primary" :loading="busy">Generate report</v-btn><v-btn class="ml-2" :disabled="!report || busy" @click="print">Print statement/report</v-btn><v-btn class="ml-2" :disabled="!report || busy" @click="download">Download CSV</v-btn>
    </v-form></v-card-text></v-card>
    <v-card v-if="report" class="pa-5 statement">
      <h2>{{ report.donor ? 'Donor giving statement' : 'Church giving report' }}</h2><p v-if="report.donor">Donor: {{ report.donor.name }}</p><p>{{ report.from }} to {{ report.to }}</p><p v-if="report.fundId">Fund: {{ fundOptions.find(item => item.value === report?.fundId)?.title }}</p>
      <p v-if="!report.entries.length" class="my-3">No giving entries in this period.</p>
      <v-table class="mt-4"><thead><tr><th>Currency</th><th>Gross gifts</th><th>Reversals</th><th>Net giving</th></tr></thead><tbody><tr v-for="total in report.totals" :key="total.currency"><td>{{ total.currency }}</td><td>{{ money(total.grossAmountMinor, total.currency) }}</td><td>{{ money(total.reversalAmountMinor, total.currency) }}</td><td>{{ money(total.netAmountMinor, total.currency) }}</td></tr></tbody></v-table>
      <v-table class="mt-4"><thead><tr><th>Fund</th><th>Currency</th><th>Gross</th><th>Reversals</th><th>Net</th></tr></thead><tbody><tr v-for="total in report.fundTotals" :key="`${total.fundId}:${total.currency}`"><td>{{ total.fundName }}</td><td>{{ total.currency }}</td><td>{{ money(total.grossAmountMinor, total.currency) }}</td><td>{{ money(total.reversalAmountMinor, total.currency) }}</td><td>{{ money(total.netAmountMinor, total.currency) }}</td></tr></tbody></v-table>
      <v-table class="mt-4"><thead><tr><th>Date</th><th>Entry</th><th>Donor</th><th>Fund</th><th>Amount</th></tr></thead><tbody><tr v-for="entry in report.entries" :key="entry.id"><td>{{ entry.date }}</td><td>{{ entry.type }}</td><td>{{ entry.donorName }}</td><td>{{ entry.fundName }}</td><td>{{ money(entry.amountMinor, entry.currency) }}</td></tr></tbody></v-table>
      <p class="mt-4 text-caption">{{ report.notice }}</p>
    </v-card>
    <v-card class="mt-5 report-controls"><v-card-title>Pledge progress</v-card-title><v-card-text>
      <v-text-field v-model="asOf" label="Progress through" type="date" /><v-btn :loading="busy" @click="loadProgress">Refresh progress</v-btn>
      <p class="my-3">{{ progress?.notice }}</p>
      <v-table v-if="progress"><thead><tr><th>Donor</th><th>Fund</th><th>Due</th><th>Status</th><th>Pledged</th><th>Applied</th><th>Remaining</th></tr></thead><tbody><tr v-for="item in progress.items" :key="item.id"><td>{{ item.donorName }}</td><td>{{ fundOptions.find(fund => fund.value === item.fundId)?.title }}</td><td>{{ item.dueOn }}{{ item.overdue ? ' · overdue' : '' }}</td><td>{{ item.status }}</td><td>{{ money(item.amountMinor, item.currency) }}</td><td>{{ money(item.appliedAmountMinor, item.currency) }}</td><td>{{ money(item.remainingAmountMinor, item.currency) }}</td></tr></tbody></v-table>
      <v-form ref="allocationForm" class="mt-4" @submit.prevent="allocate">
        <v-select v-model="pledgeId" :items="pledgeOptions" label="Pledge to credit" :rules="[required]" /><v-select v-model="giftId" :items="giftOptions" label="Gift to apply" :rules="[required]" />
        <v-text-field v-model="amount" label="Amount to apply" hint="Ordinary currency amount; the pledge determines currency" persistent-hint :rules="[required]" />
        <v-btn type="submit" :loading="busy" color="primary">Apply gift to pledge</v-btn>
      </v-form>
      <v-table v-if="progress" class="mt-4"><thead><tr><th>Assignment reference</th><th>Status</th><th>Amount</th><th>Correction</th></tr></thead><tbody><tr v-for="link in progress.allocations" :key="link.id"><td>{{ link.reference }}</td><td>{{ link.status }}</td><td>{{ money(link.amountMinor, link.currency) }}</td><td><v-btn v-if="link.status === 'active'" :disabled="busy" @click="cancelling = link; reason = ''">Cancel assignment</v-btn><span v-else>{{ link.cancellationReason }}</span></td></tr></tbody></v-table>
    </v-card-text></v-card>
    <v-dialog :model-value="Boolean(cancelling)" max-width="500" persistent><v-card title="Correct pledge assignment"><v-card-text><v-textarea v-model="reason" label="Correction reason" maxlength="1000" /></v-card-text><v-card-actions><v-btn :disabled="busy" @click="cancelling = null">Close</v-btn><v-btn :disabled="!reason.trim()" :loading="busy" @click="cancel">Confirm cancellation</v-btn></v-card-actions></v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { useWorkflowsStore } from '@/stores/workflows'
  import { amountMinor as toMinorUnits } from '@/workflows/money'
  interface Total {currency: string, grossAmountMinor: number, reversalAmountMinor: number, netAmountMinor: number}
  interface Entry {id: string, date: string, type: string, donorName: string, fundName: string, currency: string, amountMinor: number}
  interface Report {from: string, to: string, donor: {id: string, name: string} | null, fundId: string | null, entries: Entry[], totals: Total[], fundTotals: (Total & {fundId: string, fundName: string})[], notice: string}
  interface Link {id: string, reference: string, status: string, amountMinor: number, currency: string, version: number, cancellationReason: string | null}
  interface ProgressItem {id: string, donorId: string, donorName: string, fundId: string, dueOn: string, status: string, amountMinor: number, appliedAmountMinor: number, remainingAmountMinor: number, currency: string, overdue: boolean}
  interface Progress {items: ProgressItem[], allocations: Link[], notice: string}
  const access = useAuthStore(); const store = useWorkflowsStore()
  const today = new Date().toLocaleDateString('en-CA')
  const from = ref(`${today.slice(0, 4)}-01-01`); const to = ref(today); const asOf = ref(today)
  const donorId = ref<string | string[] | null>(null); const fundId = ref<string | null>(null)
  const report = ref<Report | null>(null); const progress = ref<Progress | null>(null)
  const error = ref(''); const busy = ref(false); const filtersForm = ref(); const allocationForm = ref()
  const pledgeId = ref<string | null>(null); const giftId = ref<string | null>(null); const amount = ref('')
  const cancelling = ref<Link | null>(null); const reason = ref('')
  let reference = crypto.randomUUID()
  const required = (value: unknown) => (typeof value === 'string' && Boolean(value.trim())) || 'This field is required.'
  const fundOptions = computed(() => (store.data.giving?.funds ?? []).map(item => ({title: String(item.name), value: item.id})))
  const pledgeOptions = computed(() => (progress.value?.items ?? []).filter(item => item.status === 'open').map(item => ({title: `${item.donorName} · ${fundOptions.value.find(fund => fund.value === item.fundId)?.title ?? 'Fund'} · ${money(item.amountMinor, item.currency)} · ${item.dueOn}`, value: item.id})))
  const giftOptions = computed(() => (store.data.giving?.gifts ?? []).filter(item => item.status === 'posted' && (!pledgeId.value || (item.donorId === progress.value?.items.find(pledge => pledge.id === pledgeId.value)?.donorId && item.currency === progress.value?.items.find(pledge => pledge.id === pledgeId.value)?.currency))).map(item => ({title: `${item.receivedOn} · ${money(Number(item.amountMinor), String(item.currency))} · ${item.id}`, value: item.id})))
  watch(pledgeId, () => { giftId.value = null })
  function money (minor: number, currency: string) { const formatter = new Intl.NumberFormat(undefined, {style:'currency', currency}); return formatter.format(minor / 10 ** (formatter.resolvedOptions().maximumFractionDigits ?? 2)) }
  async function load () {
    if (busy.value || !(await filtersForm.value.validate()).valid) return
    busy.value = true; error.value = ''; report.value = null
    try { report.value = (await useApi().get<Report>('/api/giving/reports', {params: {from: from.value, to: to.value, donorId: donorId.value || undefined, fundId: fundId.value || undefined}})).data }
    catch (failure) { error.value = extractErrorMessage(failure, 'Unable to generate report.') } finally {busy.value = false}
  }
  function print () { window.print() }
  async function download () {
    if (!report.value) return
    busy.value = true; error.value = ''
    try {
      const data = await useApi().get('/api/giving/reports/export', {params: {from: report.value.from, to: report.value.to, donorId: report.value.donor?.id, fundId: report.value.fundId || undefined}, responseType: 'blob'})
      const url = URL.createObjectURL(data.data); const anchor = document.createElement('a'); anchor.href = url; anchor.download = 'giving-report.csv'; anchor.click(); URL.revokeObjectURL(url)
    } catch (failure) {error.value = extractErrorMessage(failure, 'Unable to download report.')} finally {busy.value = false}
  }
  async function loadProgress () { try {progress.value = (await useApi().get<Progress>('/api/giving/pledge-progress', {params: {asOf: asOf.value}})).data} catch (failure) {error.value = extractErrorMessage(failure, 'Unable to load progress.')} }
  async function allocate () {
    if (busy.value || !(await allocationForm.value.validate()).valid) return
    busy.value = true; error.value = ''
    try {
      const pledge = progress.value?.items.find(item => item.id === pledgeId.value)
      if (!pledge) throw new Error('Choose a pledge.')
      await useApi().post('/api/giving/pledge-allocations', {pledgeId: pledgeId.value, giftId: giftId.value, amountMinor: toMinorUnits(amount.value, pledge.currency), reference})
      reference = crypto.randomUUID(); amount.value = ''; await loadProgress()
    } catch (failure) {error.value = extractErrorMessage(failure, 'Unable to apply gift.')} finally {busy.value = false}
  }
  async function cancel () {
    if (!cancelling.value || busy.value) return
    busy.value = true; error.value = ''
    try {await useApi().post(`/api/giving/pledge-allocations/${cancelling.value.id}/cancel`, {expectedVersion: cancelling.value.version, reason: reason.value}); cancelling.value = null; await loadProgress()}
    catch (failure) {error.value = extractErrorMessage(failure, 'Unable to cancel assignment.')} finally {busy.value = false}
  }
  onMounted(async () => {try {await store.load('giving'); await loadProgress()} catch (failure) {error.value = extractErrorMessage(failure, 'Unable to load giving.')} })
</script>
<style>
@media print { .report-controls, .v-navigation-drawer, .v-app-bar {display:none!important} .v-main {padding:0!important} .statement {box-shadow:none!important} .statement tr {break-inside:avoid} }
</style>
