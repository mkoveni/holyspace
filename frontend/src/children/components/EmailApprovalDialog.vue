<template>
  <MinistryDialog
    v-model="open"
    :save-label="scheduled ? 'Approve scheduled email' : 'Approve email'"
    :submit="queue"
    :submit-disabled="
      !approved ||
        !selected?.verificationId ||
        !review.data.value?.transport.enabled ||
        review.loading.value
    "
    subtitle="Confirm the guardian address and exact wording before approving one immediate or scheduled email."
    title="A reviewed message, ready to go"
    @reload="load"
    @saved="emit('saved')"
  >
    <v-alert
      v-if="review.error.value"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ review.error.value }}
    </v-alert>

    <template v-if="review.data.value">
      <v-alert
        class="mb-4"
        :type="review.data.value.transport.enabled ? 'info' : 'warning'"
        variant="tonal"
      >
        {{
          review.data.value.transport.capture
            ? 'Local test inbox: approved emails are captured for testing and do not reach real recipients.'
            : review.data.value.transport.enabled
              ? 'Email delivery is configured. SMTP acceptance is tracked separately from confirmed delivery.'
              : 'Email delivery is not configured. Ask an administrator to configure SMTP.'
        }}
      </v-alert>

      <v-select
        v-model="contactId"
        item-title="email"
        item-value="contactId"
        :items="review.data.value.recipients"
        label="Guardian email *"
        :rules="[required]"
      />

      <v-alert
        v-if="review.data.value.recipients.length === 0"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Add an email contact to the guardian's People profile before preparing
        email delivery.
      </v-alert>

      <template v-if="selected">
        <div class="email-verification mb-5">
          <p>
            {{
              selected.verificationId
                ? `Verified through ${timeLabel(selected.validUntil!)}`
                : 'This address needs verification with the guardian.'
            }}
          </p>

          <v-btn
            v-if="selected.verificationId"
            variant="text"
            @click="revokeOpen = true"
          >
            Revoke verification
          </v-btn>

          <template v-else>
            <v-textarea
              v-model="evidence"
              label="How was this address verified with the guardian? *"
              maxlength="500"
            />

            <DateSelector
              v-model="validUntil"
              label="Verification valid through"
            />

            <v-checkbox
              v-model="verifiedWithGuardian"
              hide-details
              label="I confirmed this address directly with the guardian."
            />

            <v-btn
              class="mt-3"
              :disabled="
                !evidence.trim() || !verifiedWithGuardian || !validUntil
              "
              :loading="task.busy.value"
              variant="tonal"
              @click="verify"
            >
              Record email verification
            </v-btn>
          </template>
        </div>
      </template>

      <TaskAlert
        :conflict="task.conflict.value"
        :error="task.error.value"
        @reload="load"
      />

      <h3>Message to be sent</h3>

      <p class="mb-3">
        <strong>Subject:</strong>
        {{ review.data.value.subject }}
      </p>

      <EmailPreview
        v-if="review.data.value.html"
        :html="review.data.value.html"
      />

      <p
        v-else
        class="email-wording"
      >
        {{ review.data.value.message }}
      </p>

      <v-checkbox
        v-model="scheduled"
        hide-details
        label="Schedule for later"
      />

      <DateTimeSelector
        v-if="scheduled"
        v-model="scheduledAt"
        label="Send at *"
        :rules="[required]"
      />

      <v-checkbox
        v-model="approved"
        label="I reviewed this recipient, message and send time and approve this email."
        :rules="[(value) => !!value || 'Review and approve this email.']"
      />
    </template>
  </MinistryDialog>

  <MinistryDialog
    v-model="revokeOpen"
    save-label="Revoke verification"
    :submit="revoke"
    subtitle="Scheduled messages using this verification will be blocked before dispatch."
    title="Revoke email verification"
    @saved="load"
  >
    <v-textarea
      v-model="revokeReason"
      label="Reason *"
      maxlength="500"
      :rules="[required]"
    />
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { EmailReview } from '../types'
  import { computed, ref, watch } from 'vue'
  import EmailPreview from '@/communications/components/EmailPreview.vue'
  import { localDateTimeValue } from '@/components/common/date-time/value'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { childrenApi } from '../api'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ communicationId: string }>()
  const emit = defineEmits<{ saved: [] }>()
  const review = useLoad<EmailReview | null>(
    () => childrenApi.emailReview(props.communicationId),
    null,
  )
  const contactId = ref<string | null>(null),
        evidence = ref(''),
        validUntil = ref(''),
        scheduledAt = ref(''),
        scheduled = ref(false),
        approved = ref(false),
        verifiedWithGuardian = ref(false),
        revokeOpen = ref(false),
        revokeReason = ref('')
  const selected = computed(() =>
    review.data.value?.recipients.find(r => r.contactId === contactId.value),
  )
  const task = useTask({ preserveConflictMessage: true })
  async function load () {
    approved.value = false
    task.clear()
    await review.load()
  }
  watch(open, value => {
    if (value) {
      contactId.value = null
      evidence.value = ''
      verifiedWithGuardian.value = false
      scheduled.value = false
      const later = new Date(Date.now() + 3_600_000)
      scheduledAt.value = localDateTimeValue(later)
      const expiry = new Date(Date.now() + 180 * 86_400_000)
      validUntil.value = expiry.toISOString().slice(0, 10)
      void load()
    } else {
      review.data.value = null
      task.clear()
    }
  })
  watch([contactId, scheduled, scheduledAt], () => {
    approved.value = false
  })
  async function verify () {
    if (!selected.value) return
    const id = selected.value.contactId
    const result = await task.run(() =>
      childrenApi.verifyEmail(props.communicationId, {
        contactId: id,
        evidence: evidence.value,
        validUntil: new Date(validUntil.value + 'T23:59:59').toISOString(),
      }),
    )
    if (result) await load()
  }
  async function revoke () {
    if (selected.value?.verificationId)
      await childrenApi.revokeEmail(
        selected.value.verificationId,
        revokeReason.value,
      )
  }
  async function queue () {
    const recipient = selected.value
    if (!recipient?.verificationId || !approved.value || !review.data.value)
      throw new Error('Review the message and a verified email address.')
    await childrenApi.queueEmail(props.communicationId, {
      expectedVersion: review.data.value.version,
      verificationId: recipient.verificationId,
      reviewHash: recipient.reviewHash,
      scheduledAt: scheduled.value
        ? new Date(scheduledAt.value).toISOString()
        : null,
    })
  }
</script>
<style scoped>
  .email-wording {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.8;
    margin: 14px 0 24px;
  }
  .email-verification {
    padding: 20px;
    background: var(--workspace-soft);
    border-radius: 14px;
  }
  .email-verification p {
    margin-bottom: 14px;
  }
</style>
