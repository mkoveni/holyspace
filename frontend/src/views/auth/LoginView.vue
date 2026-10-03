<template>
  <div>
    <div class="mb-8 d-flex d-md-none align-center ga-3">
      <div class="auth-mobile-mark">
        <v-icon color="secondary" icon="mdi-church" size="22" />
      </div>

      <span class="font-heading text-h6 font-weight-bold">Holyspace</span>
    </div>

    <div class="mb-8">
      <p class="text-overline text-primary font-weight-bold mb-1">Welcome back</p>
      <h2 class="font-heading text-h4 font-weight-bold mb-2">Sign in to your account</h2>

      <p class="text-body-2 text-medium-emphasis">
        Enter your credentials to access the administration console.
      </p>
    </div>

    <v-form @submit.prevent="handleSubmit">
      <div class="mb-1 text-caption font-weight-medium text-medium-emphasis">Username</div>

      <v-text-field
        v-model="form.username"
        autocomplete="username"
        autofocus
        class="mb-2"
        placeholder="you@yourchurch.org"
        prepend-inner-icon="mdi-account-outline"
        :rules="[required]"
      />

      <div class="mb-1 text-caption font-weight-medium text-medium-emphasis">Password</div>

      <v-text-field
        v-model="form.password"
        :append-inner-icon="showPassword ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"
        autocomplete="current-password"
        placeholder="••••••••"
        prepend-inner-icon="mdi-lock-outline"
        :rules="[required]"
        :type="showPassword ? 'text' : 'password'"
        @click:append-inner="showPassword = !showPassword"
      />

      <v-alert
        v-if="errorMessage"
        class="mb-4"
        closable
        density="comfortable"
        type="error"
        variant="tonal"
        @click:close="errorMessage = ''"
      >
        {{ errorMessage }}
      </v-alert>

      <v-btn
        block
        class="mt-2"
        color="primary"
        height="48"
        :loading="isSubmitting"
        size="large"
        type="submit"
      >
        Sign In
        <v-icon end icon="mdi-arrow-right" />
      </v-btn>
    </v-form>

    <p class="text-caption text-medium-emphasis text-center mt-8">
      Protected administration area · Authorized personnel only
    </p>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/auth'

  const router = useRouter()
  const route = useRoute()
  const authStore = useAuthStore()

  const form = reactive({ username: '', password: '' })
  const showPassword = ref(false)
  const isSubmitting = ref(false)
  const errorMessage = ref('')

  const required = (value: string) => Boolean(value) || 'This field is required.'

  async function handleSubmit () {
    errorMessage.value = ''
    isSubmitting.value = true
    try {
      await authStore.login({ username: form.username, password: form.password })
      const redirect = (route.query.redirect as string) || '/'
      await router.push(redirect)
    } catch (error) {
      errorMessage.value = error instanceof Error ? error.message : 'Unable to sign in.'
    } finally {
      isSubmitting.value = false
    }
  }
</script>

<style scoped>
.auth-mobile-mark {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background-color: #fce9b9;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
