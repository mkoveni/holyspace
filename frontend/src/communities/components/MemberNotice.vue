<template>
  <CommunityDialog
    v-model="open"
    :keep-open="reviewing"
    :save-label="review ? 'Approve and queue notice' : 'Review notice'"
    :submit="save"
    :submit-disabled="Boolean(review && (!verified || !evidence.trim()))"
    subtitle="Review the exact message and verify the recipient before queuing delivery."
    title="A thoughtful member notice"
    @reload="reload"
    @saved="emit('saved')"
  >
    <p class="mb-4">{{ member?.name }}</p>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-select
      v-model="contactId"
      item-title="value"
      item-value="id"
      :items="contacts"
      label="Current email address"
      :rules="[required]"
    />

    <v-textarea
      v-model="message"
      label="Message"
      maxlength="5000"
      :rules="[required]"
    />

    <template v-if="review">
      <p class="directory-muted">To: {{ review.recipient }}</p>
      <h3 class="my-3">{{ review.subject }}</h3>

      <iframe
        class="community-notice-preview"
        sandbox=""
        :srcdoc="review.html"
        title="Reviewed member notice"
      />

      <v-checkbox
        v-model="verified"
        label="I verified this recipient and their permission to receive this message"
      />

      <v-textarea
        v-model="evidence"
        label="Verification evidence"
        maxlength="500"
        :rules="[required]"
      />
    </template>
  </CommunityDialog>
</template>
<script setup lang="ts">
  import { ref, watch } from 'vue'
  import {
    communityApi,
    errorMessage,
    type Member,
    type NoticeReview,
    required,
  } from '../api'
  import CommunityDialog from './CommunityDialog.vue'
  const open = defineModel<boolean>({ required: true }),
        props = defineProps<{ member?: Member }>(),
        emit = defineEmits<{ saved: [] }>()
  const contacts = ref<{ id: string, value: string }[]>([]),
        contactId = ref<string | null>(null),
        message = ref(''),
        review = ref<NoticeReview>(),
        reviewing = ref(false),
        verified = ref(false),
        evidence = ref(''),
        error = ref('')
  let request = crypto.randomUUID(),
      generation = 0
  watch([contactId, message], () => {
    review.value = undefined
    verified.value = false
    evidence.value = ''
    request = crypto.randomUUID()
  })
  watch(open, async value => {
    const current = ++generation
    if (!value || !props.member) return
    contacts.value = []
    contactId.value = null
    message.value = ''
    review.value = undefined
    error.value = ''
    request = crypto.randomUUID()
    try {
      const result = await communityApi.contacts(props.member.membership.id)
      if (current === generation) contacts.value = result
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    }
  })
  async function save () {
    if (!props.member || !contactId.value) return
    if (!review.value) {
      reviewing.value = true
      review.value = await communityApi.reviewNotice(
        props.member.membership.id,
        { contactId: contactId.value, message: message.value },
      )
      return
    }
    reviewing.value = false
    await communityApi.approveNotice(props.member.membership.id, {
      requestId: request,
      contactId: contactId.value,
      message: message.value,
      reviewHash: review.value.hash,
      evidence: evidence.value,
    })
  }
  function reload () {
    open.value = false
  }
</script>
<style scoped>
  .community-notice-preview {
    width: 100%;
    min-height: 240px;
    border: 1px solid var(--workspace-border);
    border-radius: 12px;
    background: white;
  }
</style>
