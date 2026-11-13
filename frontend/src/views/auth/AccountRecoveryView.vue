<template><div><h2 class="mb-4">Recover your account</h2><p class="mb-4">Use an offline recovery code saved when you enabled your authenticator. Recovery changes your password, revokes all sessions and previous codes, and requires authenticator enrolment again.</p><v-form @submit.prevent="recover"><v-text-field v-model="username" autocomplete="username" label="Username" /><v-text-field v-model="code" autocomplete="off" label="Offline recovery code" /><v-text-field v-model="password" autocomplete="new-password" label="New password (at least 12 characters)" type="password" /><v-alert v-if="error" type="error">{{ error }}</v-alert><v-alert v-if="message" type="success">{{ message }}</v-alert><v-btn class="my-4" :disabled="!username || !code || password.length < 12" :loading="busy" type="submit">Recover account</v-btn><v-btn to="/login" variant="text">Return to sign in</v-btn></v-form><p>If you have no recovery code, contact the church’s account administrator for identity verification. A password reset alone does not remove MFA.</p></div></template>
<script setup lang="ts">
  import axios from 'axios'
  import { ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  const username = ref(''), code = ref(''), password = ref(''), error = ref(''), message = ref(''), busy = ref(false)
  async function recover () {
    if (busy.value) return; busy.value = true; error.value = ''; message.value = ''; try {
      await axios.post((import.meta.env.VITE_API_BASE_URL ?? '/').replace(/\/$/, '') + '/api/account-recovery', { username: username.value, code: code.value, newPassword: password.value }); useAuthStore().logout(); code.value = ''; password.value = ''; message.value = 'Recovered. Sign in with your new password and enrol an authenticator again.'
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'Recovery details are invalid or no longer available.')
    } finally {
      busy.value = false
    }
  }
</script>
