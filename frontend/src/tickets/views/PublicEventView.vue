<template>
  <main class="tk-page">
    <div class="tk-shell">
      <PublicBrand />

      <div v-if="loading" aria-label="Loading" role="status">
        <v-skeleton-loader type="article, actions" />
      </div>

      <section v-else-if="!event" class="tk-card text-center">
        <v-icon color="primary" icon="mdi-ticket-outline" size="40" />
        <h1>These tickets aren't on sale</h1>
        <p role="alert">{{ error || 'The event may be over or sold out.' }}</p>
        <v-btn color="primary" :to="{ name: 'public-tickets-events' }">See other events</v-btn>
      </section>

      <template v-else>
        <router-link class="tk-back" :to="{ name: 'public-tickets-events' }">
          <v-icon icon="mdi-arrow-left" size="18" />All events
        </router-link>

        <div v-if="heroImage" class="tk-hero"><img :alt="event.title" :src="heroImage"></div>

        <div class="tk-layout">
          <div>
            <h1 class="tk-title">{{ event.title }}</h1>

            <ul class="tk-facts">
              <li>
                <v-icon icon="mdi-calendar-clock-outline" />

                <div>
                  <strong>{{ formatWhen(event.startsAt, event.timeZone) }}</strong>
                </div>
              </li>

              <li v-if="event.venueName">
                <v-icon icon="mdi-map-marker-outline" />

                <div>
                  <strong>{{ event.venueName }}</strong>
                  <div v-if="event.venueAddress" class="tk-muted">{{ event.venueAddress }}</div>
                </div>
              </li>
            </ul>

            <div v-if="event.description" class="tk-about">{{ event.description }}</div>
          </div>

          <section class="tk-card tk-buy">
            <h2>Tickets</h2>

            <v-alert
              v-if="error"
              class="mb-4"
              role="alert"
              type="error"
              variant="tonal"
            >{{ error }}</v-alert>

            <v-form @submit.prevent="submit">
              <div v-for="t in event.ticketTypes" :key="t.id" class="tk-row">
                <div>
                  <strong>{{ t.name }}</strong> · {{ t.priceMinor === 0 ? 'Free' : formatRand(t.priceMinor) }}
                  <div v-if="t.description" class="tk-muted">{{ t.description }}</div>
                </div>

                <v-select
                  v-model="quantities[t.id]"
                  density="compact"
                  :disabled="!t.available"
                  hide-details
                  :items="options"
                  :label="t.available ? 'Qty' : 'Sold out'"
                  style="max-width: 96px"
                />
              </div>

              <div v-if="seats > 0" aria-live="polite" class="mt-3">
                <div class="tk-row"><span>Tickets</span><span>{{ formatRand(subtotal) }}</span></div>

                <div v-if="fee > 0" class="tk-row">
                  <span>Card processing fee (estimate)</span><span>{{ formatRand(fee) }}</span>
                </div>

                <div class="tk-row tk-total"><span>Total</span><span>{{ total === 0 ? 'Free' : formatRand(total) }}</span></div>
              </div>

              <v-text-field
                v-model="form.name"
                autocomplete="name"
                class="mt-3"
                label="Your name *"
                maxlength="100"
              />

              <v-text-field
                v-model="form.email"
                autocomplete="email"
                hint="Your tickets are sent here."
                label="Email *"
                persistent-hint
                type="email"
              />
              <!-- Honeypot: people never see this field. -->
              <input
                v-model="form.website"
                aria-hidden="true"
                autocomplete="off"
                style="position: absolute; left: -9999px"
                tabindex="-1"
                type="text"
              >

              <div ref="widget" class="my-3" />

              <v-btn
                block
                color="primary"
                :disabled="!ready"
                :loading="busy"
                size="large"
                type="submit"
              >{{ buttonLabel }}</v-btn>

              <p class="tk-muted mt-3">You'll pay on Yoco's secure page. No account needed.</p>
            </v-form>
          </section>
        </div>
      </template>
    </div>
  </main>
</template>

<script setup lang="ts">
  import type { PublicEventDetail } from '@/tickets/public-tickets'
  import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import PublicBrand from '@/components/PublicBrand.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { usePageTitle } from '@/composables/usePageTitle'
  import { formatRand, formatWhen, goToCheckout, publicTickets, tokenKey, totalFor } from '@/tickets/public-tickets'
  import '@/tickets/tickets.css'

  const route = useRoute()
  const router = useRouter()
  const siteKey = import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined
  const event = ref<PublicEventDetail>()
  usePageTitle(() => event.value?.title ?? 'Event tickets')
  const loading = ref(true)
  const busy = ref(false)
  const error = ref('')
  const widget = ref<HTMLElement>()
  const quantities = reactive<Record<string, number>>({})
  const form = reactive({ name: '', email: '', website: '' })
  let humanToken: string | null = null,
      widgetId: string | undefined,
      requestId: string = crypto.randomUUID()

  const heroImage = computed(() => event.value?.coverImageUrl ?? event.value?.bannerImageUrl ?? null)
  const options = computed(() => Array.from({ length: (event.value?.maxPerOrder ?? 10) + 1 }, (_, i) => i))
  const chosen = computed(() => Object.entries(quantities).filter(([, n]) => n > 0))
  const seats = computed(() => chosen.value.reduce((sum, [, n]) => sum + n, 0))
  const subtotal = computed(() => chosen.value.reduce((sum, [id, n]) => sum + n * (event.value?.ticketTypes.find(t => t.id === id)?.priceMinor ?? 0), 0))
  const total = computed(() => event.value ? totalFor(subtotal.value, event.value.fees) : 0)
  const fee = computed(() => total.value - subtotal.value)
  const emailOk = computed(() => {
    const email = form.email.trim(),
          at = email.indexOf('@')
    return at > 0 && email.indexOf('.', at) > at + 1 && !email.endsWith('.') && !/\s/.test(email)
  })
  const buttonLabel = computed(() => seats.value === 0 ? 'Choose your tickets' : (total.value === 0 ? 'Get free tickets' : 'Continue to payment'))
  const ready = computed(() => seats.value > 0 && seats.value <= (event.value?.maxPerOrder ?? 0) && form.name.trim() !== '' && emailOk.value)

  // A new request id for any change of order, so retrying the same order reuses the same one (idempotent).
  watch(() => [{ ...quantities }, form.name, form.email], () => {
    requestId = crypto.randomUUID()
  }, { deep: true })

  function loadCaptcha () {
    if (!siteKey) return
    const mount = () => {
      if (!widget.value || !window.turnstile) return
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
    if (window.turnstile) {
      mount()
      return
    }
    const script = document.createElement('script')
    script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    script.async = true
    script.addEventListener('load', mount)
    document.head.append(script)
  }

  async function submit () {
    if (busy.value || !ready.value || !event.value) return
    if (siteKey && !humanToken) {
      error.value = 'Please complete the check below to show you are a person.'
      return
    }
    busy.value = true
    error.value = ''
    try {
      const started = await publicTickets.order(event.value.occurrenceId, {
        requestId,
        quantities: Object.fromEntries(chosen.value),
        name: form.name.trim(),
        email: form.email.trim(),
        humanToken,
        website: form.website,
      })
      if (!started.accessToken) {
        error.value = 'We could not start your order. Please try again.'
        return
      }
      // The token lives only in this tab (never in a URL) so the thank-you page can show the outcome.
      try {
        sessionStorage.setItem(tokenKey(started.id), started.accessToken)
      } catch {
        // Private mode: the thank-you page then shows a general message.
      }
      if (started.checkout) {
        goToCheckout(started.checkout)
      } else {
        await router.push({ name: 'public-tickets-thanks', query: { order: started.id } })
      }
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'We could not start your order. Please try again.')
      humanToken = null
      if (widgetId) window.turnstile?.reset(widgetId)
    } finally {
      busy.value = false
    }
  }

  onMounted(async () => {
    try {
      event.value = await publicTickets.event(String(route.params.id))
    } catch (error_) {
      error.value = extractErrorMessage(error_, '')
    } finally {
      loading.value = false
    }
    await nextTick()
    loadCaptcha()
  })
</script>
