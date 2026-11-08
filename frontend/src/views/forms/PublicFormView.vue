<template>
  <main class="public-form-page">
    <div class="public-form-shell" :class="{ 'public-form-shell--general': !form?.isEventRegistration }">
      <div class="public-brand"><v-icon icon="mdi-church-outline" size="24" /><span>Church community</span><span class="public-brand__note">You're welcome here</span></div>
      <div v-if="loading" aria-label="Loading form" role="status"><v-skeleton-loader type="image, article, actions" /></div>

      <section v-else-if="!form" class="public-unavailable">
        <v-icon color="primary" icon="mdi-link-variant-off" size="40" />
        <h1>This form isn't available right now</h1>
        <p role="alert">{{ error || 'Contact the church for an updated link.' }}</p>
        <v-btn color="primary" variant="flat" @click="load">Try again</v-btn>
      </section>

      <template v-else>
        <header class="public-hero">
          <img
            v-if="bannerVisible"
            :key="bannerSource"
            :alt="`${form.event?.title ?? form.title} banner`"
            class="public-hero__image"
            :src="bannerSource"
            @error="bannerFailed"
          >

          <div class="public-hero__content">
            <span class="public-eyebrow">{{ form.isEventRegistration ? 'GATHER WITH US' : 'LET’S CONNECT' }}</span>
            <h1>{{ form.event?.title ?? form.title }}</h1>
            <p>{{ form.isEventRegistration ? 'Make a connection. Be part of the moment.' : 'A place to share, connect and take your next step.' }}</p>
          </div>
        </header>

        <div class="public-form-layout" :class="{ 'public-form-layout--single': !form.isEventRegistration }">
          <aside v-if="form.isEventRegistration" aria-label="Event details" class="event-overview">
            <div class="public-section-label"><v-icon icon="mdi-calendar-heart" size="20" /><h2>About the event</h2></div>

            <template v-if="form.event">
              <p v-if="form.event.description" class="event-description">{{ form.event.description }}</p>

              <dl class="event-facts">
                <div><v-icon icon="mdi-calendar-outline" /><div><dt>When</dt><dd>{{ eventDate }}</dd><dd class="event-facts__secondary">{{ eventTime }}</dd><dd class="event-facts__secondary">{{ form.event.timezone }}</dd></div></div>
                <div><v-icon icon="mdi-map-marker-outline" /><div><dt>Where</dt><dd>{{ form.event.location || 'Location to be confirmed' }}</dd></div></div>
              </dl>

              <v-alert
                v-if="form.event.status === 'cancelled'"
                class="mt-4"
                density="compact"
                type="warning"
                variant="tonal"
              >This event occurrence has been cancelled. Contact the church before making plans.</v-alert>
            </template>

            <p v-else class="event-description">Event details are currently unavailable. Contact the church to confirm the date and location.</p>
            <div class="review-note"><v-icon icon="mdi-account-check-outline" size="24" /><div><strong>A personal welcome</strong><p>The church team reviews each request. Submitting this form does not reserve or confirm a place.</p></div></div>
          </aside>

          <section :aria-label="received ? 'Response received' : 'Response form'" class="response-card">
            <div
              v-if="received"
              ref="confirmation"
              class="response-success"
              role="status"
              tabindex="-1"
            >
              <div class="response-success__icon"><v-icon icon="mdi-check" size="32" /></div>
              <span class="public-eyebrow">RESPONSE RECEIVED</span>
              <h2>{{ form.isEventRegistration ? 'Your request is with the team' : 'Thank you for reaching out' }}</h2>
              <p>{{ form.isEventRegistration ? 'Your registration request has been received for staff review. Your place is confirmed only after the church approves your request.' : 'Your response has been received for staff review. Thank you for taking a moment to connect with your church community.' }}</p>
              <div v-if="form.event" class="success-event"><v-icon icon="mdi-calendar-check-outline" /><span>{{ form.event.title }}<small>{{ eventDate }}</small></span></div>
              <p class="response-success__footnote">You can safely close this page.</p>
            </div>

            <template v-else>
              <div class="response-card__heading"><span class="public-eyebrow">{{ form.isEventRegistration ? 'YOUR REGISTRATION REQUEST' : 'YOUR RESPONSE' }}</span><h2>{{ form.isEventRegistration ? form.title : 'Share your response' }}</h2><p v-if="form.description">{{ form.description }}</p></div>
              <div class="response-card__legend"><v-icon icon="mdi-lock-outline" size="16" /><span>No account needed <span aria-hidden="true">·</span> * Required field</span></div>

              <v-alert
                v-if="error"
                class="mb-5"
                role="alert"
                type="error"
                variant="tonal"
              >{{ error }}</v-alert>

              <v-form ref="inputForm" @submit.prevent="submit">
                <div class="response-fields">
                  <div v-for="field in form.fields" :key="field.key" :class="{ 'response-fields__wide': ['textarea', 'checkbox', 'select'].includes(field.type) }">
                    <v-checkbox
                      v-if="field.type === 'checkbox'"
                      v-model="answers[field.key]"
                      color="primary"
                      :disabled="sending"
                      :label="fieldLabel(field)"
                      :rules="rules(field)"
                    />

                    <v-select
                      v-else-if="field.type === 'select'"
                      :disabled="sending"
                      :items="field.options"
                      :label="fieldLabel(field)"
                      :model-value="String(answers[field.key] ?? '')"
                      :rules="rules(field)"
                      variant="outlined"
                      @update:model-value="answers[field.key] = $event ?? ''"
                    />

                    <v-textarea
                      v-else-if="field.type === 'textarea'"
                      auto-grow
                      :disabled="sending"
                      :label="fieldLabel(field)"
                      maxlength="4000"
                      :model-value="String(answers[field.key] ?? '')"
                      rows="3"
                      :rules="rules(field)"
                      variant="outlined"
                      @update:model-value="answers[field.key] = $event"
                    />

                    <v-text-field
                      v-else
                      v-model="answers[field.key]"
                      :autocomplete="field.type === 'email' ? 'email' : undefined"
                      :disabled="sending"
                      :label="fieldLabel(field)"
                      maxlength="1000"
                      :rules="rules(field)"
                      :type="field.type === 'email' ? 'email' : 'text'"
                      variant="outlined"
                    />
                  </div>
                </div>

                <div aria-hidden="true" class="form-trap"><label>Website<input v-model="website" autocomplete="off" name="website" tabindex="-1"></label></div>

                <div class="response-submit"><v-btn
                                               append-icon="mdi-arrow-right"
                                               color="primary"
                                               :disabled="!ready"
                                               :loading="sending"
                                               size="large"
                                               type="submit"
                                               variant="flat"
                                             >{{ form.isEventRegistration ? 'Send registration request' : 'Send response' }}</v-btn>

                  <p>Your response goes to the church team for review.</p></div>
              </v-form>
            </template>
          </section>
        </div>

        <footer class="public-footer"><v-icon icon="mdi-heart-outline" size="16" /><span>Community begins with connection.</span></footer>
      </template>
    </div>
  </main>
</template>
<script setup lang="ts">
  import type { FormField, PublicForm } from '@/types/forms'
  import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
  import { apiUrl } from '@/composables/useApi'
  const props = defineProps<{ token: string }>()
  const form = ref<PublicForm | null>(null)
  const answers = reactive<Record<string, string | boolean>>({})
  const website = ref('')
  const inputForm = ref()
  const confirmation = ref<HTMLElement>()
  const loading = ref(false)
  const sending = ref(false)
  const received = ref(false)
  const ready = ref(false)
  const error = ref('')
  const bannerVisible = ref(true)
  const useFormBanner = ref(false)
  let submissionKey = ''
  let readyTimer: ReturnType<typeof setTimeout> | undefined
  let sequence = 0
  const base = () => apiUrl(`/api/public/forms/${encodeURIComponent(props.token)}`)
  const bannerSource = computed(() => `${base()}/${form.value?.event && !useFormBanner.value ? 'event-banner' : 'banner'}`)
  function bannerFailed () {
    if (form.value?.event && !useFormBanner.value) useFormBanner.value = true
    else bannerVisible.value = false
  }
  function formatEventDate (value: string, time = false) {
    try {
      return new Intl.DateTimeFormat(undefined, { timeZone: form.value?.event?.timezone, ...(time ? { hour: 'numeric', minute: '2-digit' } as const : { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } as const) }).format(new Date(value))
    } catch {
      return 'To be confirmed'
    }
  }
  const eventDate = computed(() => {
    const event = form.value?.event
    if (!event) return ''
    const start = formatEventDate(event.startsAt)
    const end = formatEventDate(event.endsAt)
    return start === end ? start : `${start} – ${end}`
  })
  const eventTime = computed(() => form.value?.event ? `${formatEventDate(form.value.event.startsAt, true)} – ${formatEventDate(form.value.event.endsAt, true)}` : '')
  const fieldLabel = (field: FormField) => `${field.label}${field.required ? ' *' : ''}`
  function rules (field: FormField) {
    const checks: Array<(value: unknown) => true | string> = []
    if (field.required) checks.push(value => (field.type === 'checkbox' ? value === true : typeof value === 'string' && Boolean(value.trim())) || 'This field is required.')
    if (field.type === 'email') checks.push(value => !value || /^[^\s@]+@[^\s@][^\s.@]*\.[^\s@]+$/.test(String(value)) || 'Enter a valid email address.')
    return checks
  }
  async function load () {
    const current = ++sequence
    loading.value = true
    error.value = ''
    ready.value = false
    received.value = false
    sending.value = false
    form.value = null
    bannerVisible.value = true
    useFormBanner.value = false
    clearTimeout(readyTimer)
    try {
      const response = await fetch(base(), { cache: 'no-store', credentials: 'omit' })
      if (!response.ok) throw new Error('This form is unavailable. Contact the church if you need help.')
      const data = await response.json() as PublicForm
      if (current !== sequence) return
      form.value = data
      for (const key of Object.keys(answers)) delete answers[key]
      for (const field of data.fields) answers[field.key] = field.type === 'checkbox' ? false : ''
      submissionKey = crypto.randomUUID()
      website.value = ''
      readyTimer = setTimeout(() => {
        if (current === sequence) ready.value = true
      }, 2100)
    } catch (error_) {
      if (current === sequence) error.value = error_ instanceof Error ? error_.message : 'Unable to load the form.'
    } finally {
      if (current === sequence) loading.value = false
    }
  }
  async function submit () {
    const current = sequence
    if (!form.value || sending.value || !ready.value || !(await inputForm.value.validate()).valid || current !== sequence || sending.value) return
    sending.value = true
    error.value = ''
    try {
      const response = await fetch(base(), { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ answers, formVersion: form.value.version, challenge: form.value.challenge, submissionKey, website: website.value }) })
      if (current !== sequence) return
      if (!response.ok) {
        const data = await response.json().catch(() => ({})) as { message?: string, detail?: string }
        throw new Error(data.message ?? data.detail ?? 'Unable to submit. Please try again.')
      }
      received.value = true
      await nextTick()
      confirmation.value?.focus()
    } catch (error_) {
      if (current === sequence) error.value = error_ instanceof Error ? error_.message : 'Unable to submit.'
    } finally {
      if (current === sequence) sending.value = false
    }
  }
  watch(() => props.token, () => {
    void load()
  }, { immediate: true })
  onBeforeUnmount(() => {
    ++sequence
    clearTimeout(readyTimer)
  })
</script>
<style scoped>
.public-form-page { min-height: 100vh; background: radial-gradient(ellipse at 10% 0, rgb(var(--v-theme-primary), .09), transparent 55%), #f5f6fa; padding: 32px 24px 24px; color: #172b4d; }
.public-form-shell { max-width: 1120px; margin-inline: auto; }
.public-form-shell--general { max-width: 780px; }
.public-brand { display: flex; align-items: center; gap: 10px; font-size: .85rem; font-weight: 650; margin-bottom: 28px; color: #314e85; }
.public-brand__note { margin-left: auto; font-size: .75rem; font-weight: 400; color: #66748a; }
.public-hero { background: white; border: 1px solid #e3e8f0; border-radius: 24px; overflow: hidden; margin-bottom: 24px; }
.public-hero__image { display: block; width: 100%; aspect-ratio: 4 / 1; object-fit: cover; background: #eef1f7; }
.public-hero__content { padding: 32px 36px; }
.public-eyebrow { color: #5266a2; font-size: .64rem; letter-spacing: .17em; font-weight: 750; }
.public-hero h1 { font-size: clamp(1.8rem, 3.5vw, 2.7rem); line-height: 1.15; letter-spacing: -.045em; margin: 10px 0; font-weight: 700; }
.public-hero__content > p { color: #66748a; font-size: .9rem; line-height: 1.7; }
.public-form-layout { display: grid; grid-template-columns: minmax(0, .8fr) minmax(0, 1.2fr); gap: 28px; align-items: start; }
.public-form-layout--single { grid-template-columns: minmax(0, 1fr); }
.event-overview { padding: 20px 8px; }
.public-section-label { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; color: #314e85; }
.public-section-label h2 { font-size: 1rem; font-weight: 650; }
.event-description { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; font-size: .9rem; color: #54637b; }
.event-facts { display: grid; gap: 24px; margin: 28px 0; }
.event-facts > div { display: flex; gap: 14px; align-items: flex-start; }
.event-facts > div > .v-icon { color: #5266a2; background: #e9edf6; padding: 20px; border-radius: 12px; }
.event-facts dt { color: #768399; font-size: .73rem; margin-bottom: 5px; }
.event-facts dd { margin: 0; font-size: .875rem; font-weight: 600; line-height: 1.7; overflow-wrap: anywhere; }
.event-facts dd.event-facts__secondary { font-weight: 400; color: #66748a; font-size: .8rem; }
.review-note { display: flex; align-items: flex-start; gap: 12px; border-top: 1px solid #dde3ee; padding-top: 24px; color: #5266a2; }
.review-note strong { font-size: .85rem; color: #314e85; }
.review-note p { margin-top: 5px; font-size: .8rem; line-height: 1.75; color: #66748a; }
.response-card { background: white; border: 1px solid #e3e8f0; box-shadow: 0 12px 40px #172b4d07; border-radius: 24px; padding: 32px; min-width: 0; }
.response-card__heading h2 { font-size: 1.35rem; letter-spacing: -.03em; margin: 8px 0 12px; }
.response-card__heading p { white-space: pre-wrap; overflow-wrap: anywhere; font-size: .875rem; line-height: 1.8; color: #66748a; }
.response-card__legend { display: flex; align-items: center; gap: 6px; font-size: .7rem; color: #768399; margin: 22px 0; }
.response-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2px 16px; }
.response-fields__wide { grid-column: 1 / -1; }
.response-submit { border-top: 1px solid #edf0f5; padding-top: 22px; }
.response-submit .v-btn { width: 100%; text-transform: none; font-weight: 650; letter-spacing: 0; }
.response-submit p { color: #768399; text-align: center; font-size: .7rem; margin-top: 12px; }
.response-success { text-align: center; padding: 20px 0; outline: none; }
.response-success__icon { display: grid; place-items: center; width: 72px; height: 72px; border-radius: 50%; background: #e9f5ef; color: #28835b; margin: 0 auto 24px; }
.response-success h2 { margin: 12px 0; font-size: 1.65rem; line-height: 1.25; letter-spacing: -.035em; }
.response-success > p { font-size: .9rem; line-height: 1.8; color: #66748a; }
.success-event { display: flex; text-align: left; align-items: center; gap: 12px; padding: 18px; border-radius: 14px; background: #f5f7fb; margin-top: 24px; font-size: .85rem; }
.success-event small { display: block; color: #768399; margin-top: 5px; }
.response-success p.response-success__footnote { font-size: .75rem; margin-top: 22px; }
.public-footer { display: flex; align-items: center; justify-content: center; gap: 8px; color: #8993a5; font-size: .75rem; padding: 28px 0 8px; }
.public-unavailable { padding: 64px 28px; text-align: center; background: white; border-radius: 24px; border: 1px solid #e3e8f0; }
.public-unavailable h1 { font-size: 1.75rem; margin: 20px 0 12px; }
.public-unavailable p { color: #66748a; margin-bottom: 24px; }
.form-trap { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
@media (max-width: 760px) { .public-form-page { padding: 20px 14px; } .public-brand__note { display: none; } .public-hero { border-radius: 18px; } .public-hero__image { aspect-ratio: 2.5 / 1; } .public-hero__content { padding: 24px; } .public-form-layout { grid-template-columns: minmax(0, 1fr); gap: 20px; } .event-overview { padding: 4px 10px; } .response-card { padding: 24px 20px; border-radius: 18px; } .response-fields { grid-template-columns: minmax(0, 1fr); } .event-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; } .event-facts > div { gap: 8px; } .event-facts > div > .v-icon { display: none; } }
</style>
