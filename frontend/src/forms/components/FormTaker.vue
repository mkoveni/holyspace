<template>
  <section class="form-taker">
    <header>
      <h1>{{ page.title }}</h1>
      <p v-if="page.description">{{ page.description }}</p>
      <v-chip v-if="page.kind === 'QUIZ'" color="primary" size="small">Quiz</v-chip>
    </header>

    <v-alert v-if="!page.open" class="mt-4" type="info" variant="tonal">This form is not taking answers right now.</v-alert>

    <template v-else-if="!result">
      <v-alert
        v-if="page.identity === 'ANONYMOUS'"
        class="my-4"
        density="compact"
        type="info"
        variant="tonal"
      >
        Your answers are anonymous and are deleted after {{ page.retentionMonths }} {{ page.retentionMonths === 1 ? 'month' : 'months' }}.
      </v-alert>

      <v-alert
        v-else
        class="my-4"
        density="compact"
        type="info"
        variant="tonal"
      >
        Your name is recorded with your answers. They are deleted after {{ page.retentionMonths }} {{ page.retentionMonths === 1 ? 'month' : 'months' }}.
      </v-alert>

      <v-alert
        v-if="error"
        class="mb-4"
        role="alert"
        type="error"
        variant="tonal"
      >{{ error }}</v-alert>

      <form novalidate @submit.prevent="submit">
        <QuestionFields v-model="answers" :questions="page.questions" />

        <v-text-field
          v-if="page.leaderboard"
          v-model="nickname"
          class="mb-4"
          hint="Shown to others. Do not use your full name."
          label="Nickname for the leaderboard"
          maxlength="40"
          persistent-hint
          variant="outlined"
        />

        <input
          v-model="website"
          aria-hidden="true"
          autocomplete="off"
          class="form-taker__hp"
          name="website"
          tabindex="-1"
          type="text"
        >

        <div v-if="siteKey" ref="widget" class="mb-4" />
        <v-btn block color="primary" :loading="busy" type="submit">Submit</v-btn>
      </form>
    </template>

    <section v-else aria-live="polite" class="mt-4">
      <v-icon color="success" icon="mdi-check-circle-outline" size="36" />
      <h2>Thank you, your answers were received.</h2>

      <p v-if="result.score !== null && result.maxScore !== null" class="text-h6">
        You scored {{ result.score }} out of {{ result.maxScore }}.
      </p>

      <ul v-if="result.answers" class="form-taker__key">
        <li v-for="q in page.questions" :key="q.id"><strong>{{ q.label }}</strong>: {{ keyText(q.id) }}</li>
      </ul>

      <template v-if="page.leaderboard">
        <h3 class="mt-4">Leaderboard</h3>

        <ol v-if="board.length > 0">
          <li v-for="e in board" :key="e.rank">{{ e.nickname }}: {{ e.score }} / {{ e.maxScore }}</li>
        </ol>

        <p v-else>No entries yet.</p>
      </template>
    </section>
  </section>
</template>

<script lang="ts" setup>
  import type { FormPage, Leaderboard, SubmitResult } from '@/forms/api'
  import { onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { cleanAnswers, missingRequired, newSubmissionKey } from '@/forms/api'
  import QuestionFields from './QuestionFields.vue'

  declare global {
    interface Window {
      turnstile?: {
        render: (el: HTMLElement, options: Record<string, unknown>) => string
        reset: (id?: string) => void
      }
    }
  }

  const props = defineProps<{
    page: FormPage
    /** Sends the answers; the public flavour adds the human check fields. */
    send: (body: Record<string, unknown>) => Promise<SubmitResult>
    loadBoard: () => Promise<Leaderboard>
    /** Show the Turnstile check (public forms only). */
    human?: boolean
  }>()

  const siteKey = props.human ? (import.meta.env.VITE_TURNSTILE_SITE_KEY as string | undefined) : undefined
  const answers = ref<Record<string, unknown>>({})
  const nickname = ref('')
  const website = ref('')
  const busy = ref(false)
  const error = ref('')
  const result = ref<SubmitResult>()
  const board = ref<Leaderboard['entries']>([])
  const widget = ref<HTMLElement>()
  // One key per attempt at the same answers: a retry after a dropped connection is not counted twice.
  const submissionKey = ref(newSubmissionKey())
  let humanToken: string | null = null
  let widgetId: string | undefined

  function mountCaptcha () {
    if (!siteKey || !widget.value || !window.turnstile) return
    widgetId = window.turnstile.render(widget.value, {
      'sitekey': siteKey,
      'callback': (t: string) => {
        humanToken = t
      },
      'expired-callback': () => {
        humanToken = null
      },
    })
  }

  onMounted(() => {
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
  })

  function keyText (id: string): string {
    const value = result.value?.answers?.[id]
    return Array.isArray(value) ? value.join(', ') : String(value ?? 'not scored')
  }

  async function submit () {
    if (busy.value) return
    const cleaned = cleanAnswers(answers.value)
    const missing = missingRequired(props.page.questions, cleaned)
    if (missing) {
      error.value = `Please answer: ${missing}`
      return
    }
    if (siteKey && !humanToken) {
      error.value = 'Please complete the check below to show you are a person.'
      return
    }
    busy.value = true
    error.value = ''
    try {
      result.value = await props.send({
        answers: cleaned,
        formVersion: props.page.version,
        submissionKey: submissionKey.value,
        nickname: nickname.value.trim() || undefined,
        challenge: props.page.challenge,
        humanToken: humanToken ?? undefined,
        website: website.value,
      })
      if (props.page.leaderboard) {
        try {
          board.value = (await props.loadBoard()).entries
        } catch {
          board.value = []
        }
      }
    } catch (error_) {
      error.value = extractErrorMessage(error_, 'We could not send your answers. Please try again.')
      humanToken = null
      if (widgetId) window.turnstile?.reset(widgetId)
    } finally {
      busy.value = false
    }
  }
</script>

<style scoped>
.form-taker__hp { position: absolute; left: -10000px; width: 1px; height: 1px; opacity: 0; }
.form-taker__key { padding-left: 20px; }
</style>
