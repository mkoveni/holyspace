<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="CLARITY & ACCOUNTABILITY"
      subtitle="Prepare donor statements and export a complete filtered ledger."
      title="Generosity, clearly reported."
    />

    <GivingNavigation />

    <v-alert
      v-if="error"
      class="mb-4 giving-no-print"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <section class="directory-surface giving-section giving-no-print">
      <div class="giving-fields">
        <DateSelector
          v-model="from"
          label="Report from"
        />

        <DateSelector
          v-model="to"
          label="Report through"
        />
      </div>

      <h2 class="mb-4">Donor statement</h2>

      <PersonPicker
        v-if="auth.hasPermission('people.read') && !seededDonor"
        v-model="donorId"
        label="Statement donor"
      />

      <p
        v-else
        class="directory-muted mb-4"
      >
        {{
          donorId
            ? 'The donor was selected from the gift ledger.'
            : 'Open a named gift in the ledger and choose View donor statement to select a donor.'
        }}
      </p>

      <p
        v-if="seededDonor && auth.hasPermission('people.read')"
        class="directory-muted"
      >
        Donor selected from the gift ledger.
        <v-btn
          variant="text"
          @click="changeDonor"
        >
          Change donor
        </v-btn>
      </p>

      <div class="giving-footer">
        <v-btn
          color="primary"
          :disabled="!donorId || busy"
          variant="tonal"
          @click="generate"
        >
          Generate statement
        </v-btn>

        <v-btn
          :disabled="!report || busy"
          prepend-icon="mdi-printer-outline"
          @click="printStatement"
        >
          Print statement
        </v-btn>
      </div>

      <v-divider class="my-6" />
      <h2 class="mb-3">Ledger CSV</h2>

      <p class="directory-muted mb-4">
        Export gifts, reversals and refunds dated in this period. Includes every
        matching allocation line, with a limit of 5,000 lines. Narrow the
        filters for larger reports.
      </p>

      <div class="giving-fields">
        <v-select
          v-model="currency"
          clearable
          :items="currencies"
          label="Export currency"
        />

        <FundPicker
          v-model="fundId"
          archived
          label="Export fund"
        />

        <v-switch
          v-model="exportDonor"
          color="primary"
          :disabled="!donorId"
          label="Export only selected donor"
        />
      </div>

      <v-btn
        v-if="auth.hasPermission('giving.export')"
        :loading="busy"
        prepend-icon="mdi-download-outline"
        variant="tonal"
        @click="download"
      >
        Download ledger CSV
      </v-btn>

      <p
        v-else
        class="directory-muted"
      >
        Your account can view statements. Ledger export needs the Giving export
        permission.
      </p>

      <v-progress-linear
        v-if="busy"
        class="mt-4"
        indeterminate
      />
    </section>

    <section
      v-if="report"
      class="directory-surface giving-section giving-print"
    >
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">DONOR STATEMENT</span>
          <h2>{{ report.donorName }}</h2>
          <p>{{ dateLabel(report.from) }} – {{ dateLabel(report.to) }}</p>
        </div>

        <span class="directory-muted">
          Generated {{ new Date(report.generatedAt).toLocaleString() }}
        </span>
      </div>

      <div class="giving-totals">
        <article
          v-for="total in report.totals"
          :key="total.currency"
          class="giving-total"
        >
          <span>{{ total.currency }} · NET GIVING</span>
          <strong>{{ money(total.netMinor, total.currency) }}</strong>
          <span>Gifts {{ money(total.grossMinor, total.currency) }}</span>

          <span>
            Reversals / refunds {{ money(total.reversedMinor, total.currency) }}
          </span>
        </article>
      </div>

      <p
        v-if="report.lines.length === 0"
        class="giving-empty"
      >
        No gifts, reversals or refunds were recorded for this donor in the
        selected period.
      </p>

      <div class="giving-report-table">
        <table v-if="report.lines.length > 0">
          <thead>
            <tr>
              <th>Date</th>
              <th>Entry</th>
              <th>Fund</th>
              <th>Reference</th>
              <th>Amount</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="(line, index) in report.lines"
              :key="index"
            >
              <td data-label="Date">{{ dateLabel(line.date) }}</td>

              <td data-label="Entry">
                {{
                  line.kind === 'REVERSAL'
                    ? 'Reversal'
                    : line.kind === 'REFUND'
                      ? 'Refund'
                      : 'Gift'
                }}
                <p
                  v-if="line.reason"
                  class="directory-muted"
                >
                  {{ line.reason }}
                </p>
              </td>

              <td data-label="Fund">{{ line.fundName }}</td>
              <td data-label="Reference">{{ line.reference || '—' }}</td>

              <td data-label="Amount">
                {{ money(line.amountMinor, line.currency) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="directory-muted mt-5">
        This is a statement of current Giving records, generated at the time
        shown. Reversals use their recorded dates and may refer to gifts
        received before this period. Currency totals remain separate. This
        statement is not a tax certificate.
      </p>
    </section>
  </div>
</template>
<script setup lang="ts">
  import type { GivingReport } from '../api'
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  import FundPicker from '../components/FundPicker.vue'
  import GivingNavigation from '../components/GivingNavigation.vue'
  import { currencies, dateLabel, money, today } from '../money'
  import '../giving.css'
  const auth = useAuthStore(),
        route = useRoute(),
        router = useRouter()
  const from = ref(today().slice(0, 4) + '-01-01'),
        to = ref(today()),
        donorId = ref<string | null>(
          typeof route.query.donorId === 'string' ? route.query.donorId : null,
        ),
        currency = ref<string | null>(null),
        fundId = ref<string | null>(null),
        exportDonor = ref(false)
  const seededDonor = ref(Boolean(donorId.value))
  const report = ref<GivingReport | null>(null),
        busy = ref(false),
        error = ref('')
  let generation = 0
  watch([from, to, donorId], () => {
    generation++
    report.value = null
    busy.value = false
    error.value = ''
  })
  watch(
    () => route.query.donorId,
    value => {
      donorId.value = typeof value === 'string' ? value : null
      seededDonor.value = Boolean(donorId.value)
    },
  )
  function validPeriod () {
    if (from.value > to.value) {
      error.value = 'Choose an end date on or after the start.'
      return false
    }
    return true
  }
  async function generate () {
    if (!donorId.value || !validPeriod()) return
    const current = ++generation
    busy.value = true
    error.value = ''
    report.value = null
    try {
      const result = await givingApi.statement(
        donorId.value,
        from.value,
        to.value,
      )
      if (current === generation) report.value = result
    } catch (error_) {
      if (current === generation) error.value = extractErrorMessage(error_)
    } finally {
      if (current === generation) busy.value = false
    }
  }
  async function download () {
    if (!validPeriod()) return
    const current = ++generation
    busy.value = true
    error.value = ''
    try {
      const blob = await givingApi.ledgerCsv({
        from: from.value,
        to: to.value,
        ...(currency.value ? { currency: currency.value } : {}),
        ...(fundId.value ? { fundId: fundId.value } : {}),
        ...(exportDonor.value && donorId.value ? { donorId: donorId.value } : {}),
      })
      if (current === generation) {
        const url = URL.createObjectURL(blob),
              link = document.createElement('a')
        link.href = url
        link.download = `giving-ledger-${from.value}-${to.value}.csv`
        link.click()
        setTimeout(() => URL.revokeObjectURL(url), 1000)
      }
    } catch (error_) {
      if (current === generation)
        error.value = extractErrorMessage(error_, 'Unable to export the ledger.')
    } finally {
      if (current === generation) busy.value = false
    }
  }
  function changeDonor () {
    donorId.value = null
    seededDonor.value = false
    router.replace({ query: { ...route.query, donorId: undefined } })
  }
  function printStatement () {
    window.print()
  }
  onBeforeUnmount(() => {
    generation++
  })
</script>
