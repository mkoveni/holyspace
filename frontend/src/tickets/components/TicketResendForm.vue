<template>
  <v-form class="tk-resend" @submit.prevent="submit">
    <p class="tk-muted mb-2">Lost your tickets email? Enter the email address you bought with and we'll send it again.</p>

    <v-alert
      v-if="message"
      class="mb-3"
      role="status"
      type="success"
      variant="tonal"
    >{{ message }}</v-alert>

    <v-alert
      v-if="error"
      class="mb-3"
      role="alert"
      type="error"
      variant="tonal"
    >{{ error }}</v-alert>

    <v-text-field
      v-model="email"
      autocomplete="email"
      :disabled="busy"
      label="Email"
      type="email"
    />

    <v-btn
      block
      color="primary"
      :disabled="email.trim() === ''"
      :loading="busy"
      type="submit"
      variant="tonal"
    >Send my tickets again</v-btn>
  </v-form>
</template>

<script setup lang="ts">
  import { ref } from 'vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { publicTickets } from '@/tickets/public-tickets'

  const email = ref('')
  const busy = ref(false)
  const message = ref('')
  const error = ref('')

  async function submit () {
    if (busy.value) return
    busy.value = true
    message.value = ''
    error.value = ''
    try {
      message.value = (await publicTickets.resend(email.value.trim(), null)).message
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'We could not send your tickets. Please try again.')
    } finally {
      busy.value = false
    }
  }
</script>
