<template>
  <EventDialog
    v-model="open"
    :keep-open="reviewing"
    :save-label="preview ? 'Approve and queue notice' : 'Review notice'"
    :submit="save"
    :submit-disabled="Boolean(preview) && (!verified || !evidence.trim())"
    :subtitle="
      'Review the recipient and wording for ' +
        name +
        '. Templates are managed in Communications.'
    "
    title="Prepare an event notice"
    @reload="
      () => {
        preview = undefined
        loadContacts()
      }
    "
    @saved="emit('saved')"
  >
    <v-alert
      v-if="contactError"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ contactError }}
    </v-alert>

    <v-select
      v-model="purpose"
      :items="[
        { title: 'Registration confirmation', value: 'CONFIRMATION' },
        { title: 'Upcoming event reminder', value: 'REMINDER' },
        { title: 'Cancellation notice', value: 'CANCELLATION' },
        { title: 'Rescheduling notice', value: 'RESCHEDULED' },
      ]"
      label="Notice type"
    />

    <v-select
      v-model="contactId"
      item-title="value"
      item-value="id"
      :items="contacts"
      label="Current email address"
      :loading="loading"
      :rules="[required]"
    />

    <p
      v-if="!loading && contacts.length === 0"
      class="directory-muted mb-4"
    >
      Add a current email address to this person's People record before
      preparing a notice.
    </p>

    <v-textarea
      v-model="message"
      label="Personal message"
      maxlength="2000"
      :rules="[required]"
    />

    <template v-if="preview">
      <div class="event-preview">
        <p>
          <strong>To:</strong>
          {{ preview.recipient }}
        </p>

        <p class="my-3">
          <strong>Subject:</strong>
          {{ preview.subject }}
        </p>

        <iframe
          class="event-email-preview"
          sandbox=""
          :srcdoc="preview.html"
          title="Event email preview"
        />
      </div>

      <v-checkbox
        v-model="verified"
        label="I verified this recipient's identity and permission to receive this message"
      />

      <v-textarea
        v-model="evidence"
        label="Verification evidence"
        maxlength="500"
        :rules="[required]"
      />

      <p class="directory-muted">
        Approval freezes this wording. Delivery rechecks the booking,
        occurrence, current email and sender permission.
      </p>
    </template>
  </EventDialog>
</template>
<script setup lang="ts">
  import { ref, watch } from 'vue'
  import { errorMessage, eventApi, type NoticeReview, required } from '../api'
  import EventDialog from './EventDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    registrationId: string
    name: string
    bookingStatus: string
  }>()
  const emit = defineEmits<{ saved: [] }>()
  const contacts = ref<{ id: string, value: string }[]>([]),
        contactId = ref<string | null>(null),
        purpose = ref('CONFIRMATION'),
        message = ref(''),
        preview = ref<NoticeReview>(),
        evidence = ref(''),
        verified = ref(false),
        reviewing = ref(false),
        loading = ref(false),
        contactError = ref('')
  let requestId = crypto.randomUUID(),
      generation = 0
  watch([contactId, purpose, message], () => {
    preview.value = undefined
    evidence.value = ''
    verified.value = false
    requestId = crypto.randomUUID()
  })
  watch(open, value => {
    if (value) {
      purpose.value
        = props.bookingStatus === 'CANCELLED' ? 'CANCELLATION' : 'CONFIRMATION'
      message.value = ''
      contactId.value = null
      preview.value = undefined
      evidence.value = ''
      verified.value = false
      requestId = crypto.randomUUID()
      loadContacts()
    } else generation++
  })
  async function loadContacts () {
    const current = ++generation
    loading.value = true
    try {
      const result = await eventApi.messageContacts(props.registrationId)
      if (current === generation) {
        contacts.value = result
        contactError.value = ''
      }
    } catch (error) {
      if (current === generation) contactError.value = errorMessage(error)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  async function save () {
    const body = {
      contactId: contactId.value,
      purpose: purpose.value,
      message: message.value,
    }
    if (!preview.value) {
      reviewing.value = true
      preview.value = await eventApi.reviewNotice(props.registrationId, body)
      return
    }
    reviewing.value = false
    await eventApi.approveNotice(props.registrationId, {
      ...body,
      requestId,
      reviewHash: preview.value.hash,
      verificationEvidence: evidence.value,
    })
  }
</script>
