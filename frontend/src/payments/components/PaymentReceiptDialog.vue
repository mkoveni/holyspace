<template>
  <v-dialog
    v-model="open"
    max-width="780"
    :persistent="busy"
  >
    <v-card rounded="xl">
      <v-card-title class="pt-5 px-6 text-wrap">
        Donor payment confirmation
      </v-card-title>

      <v-card-text>
        <p class="directory-muted mb-4">
          A confirmation is available after a live contribution is recorded in
          Giving. Review the recipient and message before approval.
        </p>

        <v-alert
          v-if="error"
          class="mb-4"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <v-progress-linear
          v-if="busy"
          class="mb-4"
          indeterminate
        />

        <template v-if="data">
          <v-alert
            v-if="data.delivery"
            class="mb-4"
            :type="
              ['FAILED', 'BLOCKED', 'UNKNOWN'].includes(data.delivery.status)
                ? 'warning'
                : 'info'
            "
            variant="tonal"
          >
            {{ deliveryLabel(data.delivery.status, data.delivery.captured) }}
          </v-alert>

          <v-select
            v-if="!data.receiptId"
            v-model="contactId"
            :disabled="busy"
            item-title="value"
            item-value="id"
            :items="data.contacts"
            label="Donor email address"
          />

          <p
            v-if="!data.receiptId && data.contacts.length === 0"
            class="directory-muted mb-4"
          >
            Add a current email address to this donor's People record first.
          </p>

          <template v-if="data.preview">
            <p>
              <strong>To:</strong>
              {{ data.preview.recipient }}
            </p>

            <p class="mb-3">
              <strong>Subject:</strong>
              {{ data.preview.subject }}
            </p>

            <EmailPreview
              :html="data.preview.html"
              title="Donor confirmation preview"
            />
          </template>

          <template v-if="!data.receiptId && data.preview">
            <v-checkbox
              v-model="verified"
              hide-details
              label="I verified this email address belongs to the donor"
            />

            <v-textarea
              v-model="evidence"
              class="mt-3"
              label="Recipient verification evidence"
              maxlength="500"
            />
          </template>

          <v-textarea
            v-if="
              data.delivery &&
                ['FAILED', 'BLOCKED'].includes(data.delivery.status)
            "
            v-model="evidence"
            class="mt-3"
            label="Retry reason"
            maxlength="500"
          />
        </template>
      </v-card-text>

      <v-card-actions class="pa-5 receipt-actions">
        <v-btn
          :disabled="busy"
          variant="text"
          @click="load"
        >
          Refresh delivery
        </v-btn>

        <v-spacer />

        <v-btn
          :disabled="busy"
          @click="open = false"
        >
          Close
        </v-btn>

        <v-btn
          v-if="
            data?.delivery &&
              ['FAILED', 'BLOCKED'].includes(data.delivery.status)
          "
          color="primary"
          :disabled="!evidence.trim() || busy"
          variant="flat"
          @click="retry"
        >
          Retry confirmation
        </v-btn>

        <v-btn
          v-if="data && !data.receiptId"
          color="primary"
          :disabled="!data.preview || !verified || !evidence.trim() || busy"
          variant="flat"
          @click="approve"
        >
          Approve confirmation
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import EmailPreview from '@/communications/components/EmailPreview.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { paymentsApi, type ReceiptReview } from '../api'
  const props = defineProps<{
    modelValue: boolean
    orderId: string
    chargeId: string
  }>()
  const emit = defineEmits<{ 'update:model-value': [boolean], 'changed': [] }>()
  const open = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })
  const data = ref<ReceiptReview | null>(null),
        busy = ref(false),
        error = ref(''),
        contactId = ref<string | null>(null),
        verified = ref(false),
        evidence = ref('')
  let requestId = crypto.randomUUID(),
      generation = 0
  async function load () {
    if (!props.modelValue) return
    const run = ++generation
    busy.value = true
    error.value = ''
    try {
      const result = await paymentsApi.receipt(
        props.orderId,
        props.chargeId,
        contactId.value,
      )
      if (run === generation) data.value = result
    } catch (error_) {
      if (run === generation) error.value = extractErrorMessage(error_)
    } finally {
      if (run === generation) busy.value = false
    }
  }
  async function approve () {
    if (
      !data.value?.preview
      || !contactId.value
      || !verified.value
      || !evidence.value.trim()
      || busy.value
    )
      return
    busy.value = true
    error.value = ''
    try {
      await paymentsApi.approveReceipt(props.orderId, {
        requestId,
        chargeId: props.chargeId,
        contactId: contactId.value,
        reviewHash: data.value.preview.hash,
        evidence: evidence.value,
      })
      await load()
      emit('changed')
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  async function retry () {
    if (
      !data.value?.receiptId
      || !data.value.delivery
      || !evidence.value.trim()
      || busy.value
    )
      return
    busy.value = true
    error.value = ''
    try {
      await paymentsApi.retryReceipt(props.orderId, data.value.receiptId, {
        expectedVersion: data.value.delivery.version,
        reason: evidence.value,
      })
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  function deliveryLabel (status: string, captured: boolean) {
    if (captured)
      return 'Captured in the test inbox · not delivered to the donor.'
    return (
      (
        {
          QUEUED: 'Approved · waiting for delivery.',
          SCHEDULED: 'Scheduled for delivery.',
          SENT: 'Accepted by the mail server · delivery is not yet confirmed.',
          DELIVERED: 'Delivery confirmed.',
          UNKNOWN:
            'Delivery outcome is uncertain. Check provider evidence before taking further action.',
          FAILED: 'Delivery failed. Review the recipient before retrying.',
          BLOCKED:
            'Delivery blocked because payment, sender or recipient eligibility changed.',
        } as Record<string, string>
      )[status] || status
    )
  }
  watch(contactId, () => {
    verified.value = false
    data.value = data.value ? { ...data.value, preview: null } : null
    void load()
  })
  watch(
    () => props.modelValue,
    value => {
      generation++
      data.value = null
      error.value = ''
      contactId.value = null
      verified.value = false
      evidence.value = ''
      requestId = crypto.randomUUID()
      if (value) void load()
    },
    { immediate: true },
  )
</script>

<style scoped>
.receipt-actions {
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
</style>
