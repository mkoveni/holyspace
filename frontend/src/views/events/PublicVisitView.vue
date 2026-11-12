<template>
  <main class="visit-page">
    <div class="visit-shell">
      <div class="visit-brand">
        <v-icon icon="mdi-church-outline" size="24" />
        <span>Church community</span>
      </div>

      <div v-if="loading" aria-label="Loading" role="status">
        <v-skeleton-loader type="article, actions" />
      </div>

      <section v-else-if="!invitation" class="visit-card text-center">
        <v-icon color="primary" icon="mdi-link-variant-off" size="40" />
        <h1>This check-in link isn't available</h1>

        <p role="alert">
          {{ error || 'Please ask someone on the welcome team for help.' }}
        </p>
      </section>

      <section
        v-else-if="welcome"
        ref="done"
        class="visit-card text-center"
        role="status"
        tabindex="-1"
      >
        <v-icon color="success" icon="mdi-check-circle-outline" size="48" />
        <h1>Welcome, we're glad you're here</h1>

        <p v-if="welcome.admitted">
          You're checked in{{ welcome.title ? ` for ${welcome.title}` : '' }}.
          Someone from the team may say hello after the service.
        </p>

        <p v-else>
          We've noted your visit. Please see the welcome team, who will help
          you find a seat.
        </p>

        <p class="visit-note">You can close this page.</p>
      </section>

      <section v-else class="visit-card">
        <span class="visit-eyebrow">FIRST TIME WITH US?</span>
        <h1>{{ invitation.title }}</h1>

        <p class="visit-note">
          {{ when }}<template v-if="invitation.venueName"> · {{ invitation.venueName }}</template>
        </p>

        <v-alert v-if="!invitation.open" class="mb-4" type="info" variant="tonal">
          Check-in opens two hours before the gathering starts and closes when
          it ends. Please try again then, or see the welcome team.
        </v-alert>

        <v-alert
          v-if="error"
          class="mb-4"
          role="alert"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <v-form v-if="invitation.open" @submit.prevent="submit">
          <v-text-field
            v-model="form.firstName"
            autocomplete="given-name"
            label="First name *"
            maxlength="100"
            :rules="[required]"
          />

          <v-text-field
            v-model="form.lastName"
            autocomplete="family-name"
            label="Surname *"
            maxlength="100"
            :rules="[required]"
          />

          <v-text-field
            v-model="form.mobile"
            autocomplete="tel"
            hint="Mobile number or email, at least one"
            inputmode="tel"
            label="Mobile number"
            maxlength="40"
            persistent-hint
          />

          <v-text-field
            v-model="form.email"
            autocomplete="email"
            class="mt-2"
            inputmode="email"
            label="Email"
            maxlength="254"
            type="email"
          />

          <v-select
            v-model="form.howHeard"
            clearable
            :items="sources"
            label="How did you hear about us? (optional)"
          />

          <v-select
            v-model="form.partyAdults"
            clearable
            :items="adults"
            label="Adults in your group (optional)"
          />

          <div class="visit-children">
            <h2 class="visit-children__title">Bringing children? (optional)</h2>

            <p class="visit-note">
              Tell us their first name and date of birth so Children's Ministry
              can welcome them. A team member will come and find you; nothing is
              signed up automatically.
            </p>

            <div v-for="(child, index) in form.children" :key="index" class="visit-child">
              <v-text-field
                v-model="child.firstName"
                density="comfortable"
                :label="`Child ${index + 1} first name`"
                maxlength="100"
              />

              <v-text-field
                v-model="child.birthDate"
                density="comfortable"
                :label="`Child ${index + 1} date of birth`"
                :max="today"
                :min="oldest"
                type="date"
              />

              <v-btn
                :aria-label="`Remove child ${index + 1}`"
                icon="mdi-close"
                size="small"
                variant="text"
                @click="form.children.splice(index, 1)"
              />
            </div>

            <v-btn
              v-if="form.children.length < MAX_CHILDREN"
              prepend-icon="mdi-plus"
              variant="tonal"
              @click="form.children.push({ firstName: '', birthDate: '' })"
            >
              Add a child
            </v-btn>
          </div>

          <!-- Real visitors never see this field; bots fill it in. -->
          <div aria-hidden="true" class="visit-trap">
            <label>Leave this empty <input v-model="form.website" autocomplete="off" tabindex="-1" type="text"></label>
          </div>

          <v-checkbox
            v-model="form.consent"
            class="mt-2"
            density="compact"
            hide-details
            :label="CONSENT"
          />

          <v-btn
            block
            class="mt-4"
            color="primary"
            :disabled="!ready"
            :loading="busy"
            size="large"
            type="submit"
          >
            Check me in
          </v-btn>
        </v-form>
      </section>
    </div>
  </main>
</template>

<script setup lang="ts">
  import { computed, nextTick, onMounted, reactive, ref } from 'vue'
  import { apiUrl } from '@/composables/useApi'

  /**
   * Placeholder wording. The church must supply the final text and privacy notice link; change CONSENT_VERSION
   * whenever the wording changes so each visitor's stored consent names the text they saw.
   */
  const CONSENT_VERSION = '2026-11-draft'
  const MAX_CHILDREN = 8
  const CONSENT = 'I agree that the church may contact me and keep these details to follow up on my visit.'

  interface Invitation {
    title: string
    venueName: string
    startsAt: string
    endsAt: string
    timeZone: string
    open: boolean
  }

  const props = defineProps<{ token: string }>()
  const loading = ref(true),
        busy = ref(false),
        error = ref(''),
        invitation = ref<Invitation>(),
        welcome = ref<{ title: string, admitted: boolean }>(),
        done = ref<HTMLElement>()
  const form = reactive({
    firstName: '',
    lastName: '',
    mobile: '',
    email: '',
    howHeard: null as string | null,
    partyAdults: null as number | null,
    children: [] as { firstName: string, birthDate: string }[],
    consent: false,
    website: '',
  })
  const sources = [
    { title: 'Friend or family', value: 'FRIEND_OR_FAMILY' },
    { title: 'Social media', value: 'SOCIAL_MEDIA' },
    { title: 'Website', value: 'WEBSITE' },
    { title: 'Passing by', value: 'PASSING_BY' },
    { title: 'An event', value: 'EVENT' },
    { title: 'Other', value: 'OTHER' },
  ]
  const adults = Array.from({ length: 10 }, (_, index) => index + 1)
  const required = (value: string) => Boolean(value.trim()) || 'Required'
  const isoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  const now = new Date(),
        today = isoDate(now),
        oldest = isoDate(new Date(now.getFullYear() - 18, now.getMonth(), now.getDate() + 1))
  // A child row left completely empty is ignored; a half-filled one blocks the button so nothing is silently dropped.
  const filledChildren = computed(() => form.children.filter(child => child.firstName.trim() || child.birthDate))
  const childrenValid = computed(() =>
    filledChildren.value.every(child => child.firstName.trim() && child.birthDate >= oldest && child.birthDate <= today),
  )
  const ready = computed(() =>
    Boolean(form.firstName.trim() && form.lastName.trim() && (form.mobile.trim() || form.email.trim()) && form.consent && childrenValid.value),
  )
  const when = computed(() => {
    if (!invitation.value) return ''
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'full',
      timeStyle: 'short',
      timeZone: invitation.value.timeZone,
    }).format(new Date(invitation.value.startsAt))
  })
  const endpoint = () => apiUrl(`/api/public/visits/${encodeURIComponent(props.token)}`)

  async function message (response: Response, fallback: string) {
    const body = await response.json().catch(() => ({})) as { message?: string }
    return body.message ?? fallback
  }

  async function load () {
    loading.value = true
    try {
      const response = await fetch(endpoint(), { cache: 'no-store', credentials: 'omit' })
      if (!response.ok) {
        error.value = response.status === 404 ? '' : await message(response, 'Unable to open this check-in link.')
        return
      }
      invitation.value = await response.json() as Invitation
    } catch {
      error.value = 'Unable to reach the church right now. Please try again.'
    } finally {
      loading.value = false
    }
  }

  async function submit () {
    if (busy.value || !ready.value) return
    busy.value = true
    error.value = ''
    try {
      const response = await fetch(endpoint(), {
        method: 'POST',
        credentials: 'omit',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          firstName: form.firstName.trim(),
          lastName: form.lastName.trim(),
          mobile: form.mobile.trim() || null,
          email: form.email.trim() || null,
          howHeard: form.howHeard,
          partyAdults: form.partyAdults,
          children: filledChildren.value.map(child => ({ firstName: child.firstName.trim(), birthDate: child.birthDate })),
          consent: form.consent,
          consentVersion: CONSENT_VERSION,
          website: form.website,
        }),
      })
      if (!response.ok) {
        error.value = await message(response, 'We could not check you in. Please see the welcome team.')
        return
      }
      welcome.value = await response.json() as { title: string, admitted: boolean }
      await nextTick()
      done.value?.focus()
    } catch {
      error.value = 'Unable to reach the church right now. Please try again.'
    } finally {
      busy.value = false
    }
  }

  onMounted(load)
</script>

<style scoped>
.visit-page {
  min-height: 100vh;
  padding: 16px;
  background: rgb(var(--v-theme-background));
}
.visit-shell {
  max-width: 520px;
  margin: 0 auto;
}
.visit-brand {
  display: flex;
  gap: 8px;
  align-items: center;
  padding: 8px 0 16px;
  font-weight: 600;
}
.visit-card {
  padding: 24px 20px;
  border-radius: 12px;
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.12);
}
.visit-card h1 {
  margin: 4px 0 8px;
  font-size: 1.5rem;
}
.visit-eyebrow {
  font-size: 0.75rem;
  letter-spacing: 0.08em;
  opacity: 0.7;
}
.visit-note {
  margin: 0 0 16px;
  opacity: 0.75;
}
.visit-children {
  margin-top: 8px;
}
.visit-children__title {
  margin: 8px 0 4px;
  font-size: 1rem;
}
.visit-child {
  display: grid;
  grid-template-columns: 1fr 1fr auto;
  gap: 8px;
  align-items: start;
}
@media (max-width: 480px) {
  .visit-child {
    grid-template-columns: 1fr auto;
  }
}
.visit-trap {
  position: absolute;
  left: -10000px;
  width: 1px;
  height: 1px;
  overflow: hidden;
}
</style>
