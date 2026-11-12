<template>
  <main class="give-page">
    <div class="give-shell">
      <div class="give-brand">
        <v-icon icon="mdi-church-outline" size="24" />
        <span>Church community</span>
      </div>

      <div v-if="loading" aria-label="Loading" role="status">
        <v-skeleton-loader type="article, actions" />
      </div>

      <section v-else-if="!options || options.funds.length === 0 || options.providers.length === 0" class="give-card text-center">
        <v-icon color="primary" icon="mdi-hand-heart-outline" size="40" />
        <h1>Online giving isn't available right now</h1>
        <p role="alert">{{ error || 'Please try again later, or ask the church office how to give.' }}</p>
      </section>

      <section v-else class="give-card">
        <span class="give-eyebrow">GIVE ONLINE</span>
        <h1>Give a gift</h1>

        <p class="give-note">
          No account needed. You'll pay on your payment provider's secure page, and we'll email you a confirmation.
        </p>

        <v-alert
          v-if="error"
          class="mb-4"
          role="alert"
          type="error"
          variant="tonal"
        >{{ error }}</v-alert>

        <v-form @submit.prevent="submit">
          <v-select
            v-model="form.fundId"
            item-title="name"
            item-value="id"
            :items="options.funds"
            label="Give to *"
          />

          <v-text-field
            v-model="form.amount"
            autocomplete="off"
            :error-messages="amountMessage"
            inputmode="decimal"
            label="Amount (ZAR) *"
            prefix="R"
          />

          <v-select
            v-if="options.providers.length > 1"
            v-model="form.provider"
            item-title="name"
            item-value="id"
            :items="options.providers"
            label="Pay with *"
          />

          <v-text-field
            v-model="form.name"
            autocomplete="name"
            label="Your name (optional)"
            maxlength="100"
          />

          <v-text-field
            v-model="form.email"
            autocomplete="email"
            hint="Your confirmation is sent here"
            inputmode="email"
            label="Email *"
            maxlength="254"
            persistent-hint
            type="email"
          />

          <!-- Real people never see this field; bots fill it in. -->
          <div aria-hidden="true" class="give-trap">
            <label>Leave this empty <input v-model="form.website" autocomplete="off" tabindex="-1" type="text"></label>
          </div>

          <div v-if="siteKey" ref="widget" class="give-captcha" />

          <p class="give-note give-note--small">
            We use your email only to send your confirmation and to deal with any problem with this gift. This is a
            confirmation of payment, not a tax certificate.
          </p>

          <v-btn
            block
            class="mt-2"
            color="primary"
            :disabled="!ready"
            :loading="busy"
            size="large"
            type="submit"
          >
            Continue to secure payment
          </v-btn>
        </v-form>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { PublicGivingOptions } from '@/payments/public-giving'
  import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
  import { launchCheckout, publicGiving, randsToMinor, tokenKey } from '@/payments/public-giving'

  declare global {
    interface Window {
      turnstile?: {
        render: (el: HTMLElement, options: Record<string, unknown>) => string
        reset: (id?: string) => void
      }
    }
  }

  const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined
  const loading = ref(true),
        busy = ref(false),
        error = ref(''),
        options = ref<PublicGivingOptions>(),
        widget = ref<HTMLElement>()
  const form = reactive({ fundId: '', amount: '', provider: '', name: '', email: '', website: '' })
  let humanToken: string | null = null,
      widgetId: string | undefined,
      requestId: string = crypto.randomUUID()

  // A new request id for any change of details, so a retry of the same details reuses the same order (idempotent).
  watch(() => [form.fundId, form.amount, form.provider, form.name, form.email], () => {
    requestId = crypto.randomUUID()
  })

  const amountMinor = computed(() => randsToMinor(form.amount))
  const amountMessage = computed(() =>
    form.amount && amountMinor.value === null ? 'Enter an amount of at least R5.00, for example 250 or 250.50' : '',
  )
  const emailOk = computed(() => {
    const email = form.email.trim(),
          at = email.indexOf('@')
    return at > 0 && email.indexOf('.', at) > at + 1 && !email.endsWith('.') && !/\s/.test(email)
  })
  const ready = computed(() => Boolean(form.fundId && form.provider && amountMinor.value !== null && emailOk.value))

  function mountCaptcha () {
    if (!siteKey || !widget.value || !window.turnstile) return
    widgetId = window.turnstile.render(widget.value, {
      'sitekey': siteKey,
      'callback': (token: string) => {
        humanToken = token
      },
      'expired-callback': () => {
        humanToken = null
      },
    })
  }

  function loadCaptcha () {
    if (!siteKey) return
    if (window.turnstile) {
      mountCaptcha()
      return
    }
    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.addEventListener('load', mountCaptcha)
    document.head.append(script)
  }

  async function submit () {
    if (busy.value || !ready.value || amountMinor.value === null) return
    if (siteKey && !humanToken) {
      error.value = 'Please complete the check below to show you are a person.'
      return
    }
    busy.value = true
    error.value = ''
    try {
      const started = await publicGiving.start({
        requestId,
        fundId: form.fundId,
        amountMinor: amountMinor.value,
        provider: form.provider,
        email: form.email.trim(),
        name: form.name.trim(),
        humanToken,
        website: form.website,
      })
      if (!started.checkout || !started.accessToken) {
        error.value = 'We could not start your gift. Please try again.'
        return
      }
      // The token lives only in this tab (never in a URL) so the thank-you page can show the outcome.
      try {
        sessionStorage.setItem(tokenKey(started.id), started.accessToken)
      } catch {
        // Private mode: the thank-you page then shows a general message instead of this gift's status.
      }
      launchCheckout(started.checkout)
    } catch (error_) {
      error.value = error_ instanceof Error ? error_.message : 'We could not start your gift. Please try again.'
      humanToken = null
      if (widgetId) window.turnstile?.reset(widgetId)
    } finally {
      busy.value = false
    }
  }

  onMounted(async () => {
    try {
      options.value = await publicGiving.options()
      form.fundId = options.value.funds[0]?.id ?? ''
      form.provider = options.value.providers[0]?.id ?? ''
    } catch (error_) {
      error.value = error_ instanceof Error ? error_.message : ''
    } finally {
      loading.value = false
    }
    await nextTick()
    loadCaptcha()
  })
</script>

<style scoped>
.give-page {
  min-height: 100vh;
  padding: 16px;
  background: rgb(var(--v-theme-background));
}
.give-shell {
  max-width: 520px;
  margin: 0 auto;
}
.give-brand {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 8px 0 16px;
  font-weight: 600;
}
.give-card {
  padding: 24px 20px;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12);
}
.give-card h1 {
  margin: 4px 0 8px;
  font-size: 1.5rem;
}
.give-eyebrow {
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  opacity: 0.7;
}
.give-note {
  margin: 0 0 16px;
  opacity: 0.75;
}
.give-note--small {
  font-size: 0.8125rem;
}
.give-captcha {
  margin: 8px 0;
}
.give-trap {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
</style>
