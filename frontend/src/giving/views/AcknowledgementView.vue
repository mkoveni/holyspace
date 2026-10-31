<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="GIVING ACKNOWLEDGEMENT"
      subtitle="Open the original acknowledgement and its current status."
      title="A lasting record of generosity."
    >
      <v-btn
        :disabled="!ack"
        prepend-icon="mdi-printer-outline"
        @click="printAcknowledgement"
      >
        Print acknowledgement
      </v-btn>
    </DirectoryHeading>

    <GivingNavigation />

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <section
      v-if="ack"
      class="directory-surface giving-section giving-print"
    >
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">GIFT ACKNOWLEDGEMENT</span>
          <h2>{{ ack.number }}</h2>
        </div>

        <v-chip
          :color="ack.voided ? 'error' : 'primary'"
          variant="tonal"
        >
          {{ ack.voided ? 'VOID' : 'Issued' }}
        </v-chip>
      </div>

      <v-alert
        v-if="ack.voided"
        class="my-5"
        type="warning"
        variant="tonal"
      >
        <h3>VOID · Gift fully reversed</h3>

        <p>
          {{ ack.reversedOn ? dateLabel(ack.reversedOn) : '' }} ·
          {{ ack.reversalReason }}
        </p>

        <p>This number and its original issue evidence are retained.</p>
      </v-alert>

      <h2>{{ ack.donorName }}</h2>

      <div class="giving-totals">
        <article class="giving-total">
          <span>Gift received</span>
          <strong>{{ money(ack.amountMinor, ack.currency) }}</strong>
          <span>{{ dateLabel(ack.receivedOn) }}</span>
          <span>Issued {{ dateLabel(ack.issuedOn) }}</span>
        </article>
      </div>

      <p class="mb-4">
        Payment method: {{ ack.method }}
        <span v-if="ack.reference">· Reference: {{ ack.reference }}</span>
      </p>

      <h3>Fund allocations</h3>

      <div
        v-for="line in ack.lines"
        :key="line.fundId"
        class="giving-section-heading giving-evidence"
      >
        <span>{{ line.fundName }}</span>
        <strong>{{ money(line.amountMinor, ack.currency) }}</strong>
      </div>

      <p class="directory-muted mt-6">
        This acknowledges a recorded gift. It is not a tax certificate and makes
        no claim of tax deductibility. Donor and fund names reflect the time of
        issue. The status shown reflects the current Giving record.
      </p>
    </section>
  </div>
</template>
<script setup lang="ts">
  import type { Acknowledgement } from '../api'
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import { extractErrorMessage } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { givingApi } from '../api'
  import GivingNavigation from '../components/GivingNavigation.vue'
  import { dateLabel, money } from '../money'
  import '../giving.css'
  const route = useRoute(),
        ack = ref<Acknowledgement | null>(null),
        loading = ref(false),
        error = ref('')
  let generation = 0
  async function load () {
    const current = ++generation
    ack.value = null
    loading.value = true
    error.value = ''
    try {
      const result = await givingApi.acknowledgement(String(route.params.id))
      if (current === generation) ack.value = result
    } catch (error_) {
      if (current === generation) error.value = extractErrorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function printAcknowledgement () {
    window.print()
  }
  watch(() => route.params.id, load, { immediate: true })
  onBeforeUnmount(() => {
    generation++
  })
</script>
