<template>
  <div>
    <PageHeader eyebrow="Forms and quizzes" :title="id ? 'Edit form' : 'Create form'" />
    <v-progress-linear v-if="loading" indeterminate />

    <v-alert
      v-if="error"
      class="mb-4"
      role="alert"
      type="error"
      variant="tonal"
    >{{ error }}</v-alert>

    <v-alert v-if="detail && detail.status !== 'DRAFT'" class="mb-4" type="info" variant="tonal">
      Saving changes a draft. Publish again to give respondents a new version; earlier responses keep the version they answered.
    </v-alert>

    <v-form v-if="!loading" @submit.prevent="save">
      <v-card class="mb-4 pa-4">
        <v-text-field v-model="f.title" label="Title" maxlength="160" variant="outlined" />

        <v-textarea
          v-model="f.description"
          auto-grow
          label="Introduction"
          maxlength="2000"
          rows="2"
          variant="outlined"
        />

        <v-row>
          <v-col cols="12" md="6"><v-select v-model="f.kind" :items="kinds" label="Type" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-select v-model="f.audience" :items="audiences" label="Who can answer" variant="outlined" /></v-col>
        </v-row>

        <v-radio-group v-if="f.audience !== 'PUBLIC'" v-model="f.identity" label="Responses">
          <v-radio label="Anonymous: nobody can see who answered" value="ANONYMOUS" />
          <v-radio label="Identified: record the person who answered" value="IDENTIFIED" />
        </v-radio-group>

        <v-alert
          v-if="f.identity === 'ANONYMOUS'"
          class="mb-3"
          density="compact"
          type="warning"
          variant="tonal"
        >
          In a small group, people may be recognised by what they write. Results stay hidden until enough people have answered.
        </v-alert>

        <v-text-field
          v-if="f.audience === 'GROUPS'"
          v-model="groupText"
          hint="Group (community) ids, separated by commas"
          label="Groups"
          persistent-hint
          variant="outlined"
        />

        <v-row>
          <v-col cols="12" md="4"><v-text-field v-model="f.opensAt" label="Opens" type="datetime-local" variant="outlined" /></v-col>
          <v-col cols="12" md="4"><v-text-field v-model="f.closesAt" label="Closes" type="datetime-local" variant="outlined" /></v-col>

          <v-col cols="12" md="4"><v-text-field
            v-model.number="f.maxResponses"
            clearable
            label="Maximum responses"
            type="number"
            variant="outlined"
          /></v-col>
        </v-row>

        <v-select v-model="f.retentionMonths" :items="[1, 2, 3]" label="Keep responses for (months)" variant="outlined" />

        <template v-if="f.kind === 'QUIZ'">
          <v-switch v-model="f.showScore" hide-details label="Show respondents their score" />
          <v-switch v-model="f.showAnswers" hide-details label="Show respondents the correct answers afterwards" />
          <v-switch v-model="f.leaderboardEnabled" hide-details label="Leaderboard (nicknames only)" />

          <v-text-field
            v-if="f.leaderboardEnabled"
            v-model.number="f.leaderboardSize"
            label="Leaderboard size"
            max="100"
            min="1"
            type="number"
            variant="outlined"
          />
        </template>
      </v-card>

      <v-card v-for="(q, i) in f.definition.questions" :key="q.id" class="mb-3 pa-4">
        <div class="d-flex ga-2 align-center mb-2">
          <strong>{{ noun }} {{ i + 1 }}</strong><v-spacer />

          <v-btn
            aria-label="Move up"
            :disabled="i === 0"
            icon="mdi-arrow-up"
            size="small"
            variant="text"
            @click="move(i, -1)"
          />

          <v-btn
            aria-label="Move down"
            :disabled="i === f.definition.questions.length - 1"
            icon="mdi-arrow-down"
            size="small"
            variant="text"
            @click="move(i, 1)"
          />

          <v-btn
            :aria-label="`Remove ${noun.toLowerCase()}`"
            icon="mdi-delete-outline"
            size="small"
            variant="text"
            @click="f.definition.questions.splice(i, 1)"
          />
        </div>

        <v-text-field v-model="q.label" :label="`${noun} label`" maxlength="300" variant="outlined" />

        <v-row>
          <v-col cols="12" md="6"><v-select
            v-model="q.type"
            :items="questionTypes"
            label="Answer type"
            variant="outlined"
            @update:model-value="typeChanged(q)"
          /></v-col>

          <v-col class="d-flex align-center" cols="12" md="6"><v-switch v-model="q.required" hide-details label="Required" /></v-col>
        </v-row>

        <template v-if="isChoice(q)">
          <div v-for="(o, oi) in q.options" :key="o.id + oi" class="d-flex ga-2 align-center">
            <v-checkbox
              v-if="f.kind === 'QUIZ'"
              v-model="q.correct"
              :aria-label="`Correct: ${o.label}`"
              density="compact"
              hide-details
              :value="o.id"
            />

            <v-text-field
              v-model="o.label"
              density="compact"
              :label="`Option ${oi + 1}`"
              maxlength="200"
              variant="outlined"
            />

            <v-btn
              aria-label="Remove option"
              :disabled="q.options.length <= 2"
              icon="mdi-close"
              size="small"
              variant="text"
              @click="q.options.splice(oi, 1)"
            />
          </div>

          <v-btn v-if="q.options.length < 20" size="small" variant="text" @click="addOption(q)">Add option</v-btn>
        </template>

        <v-text-field
          v-else-if="f.kind === 'QUIZ' && ['TEXT', 'NUMBER'].includes(q.type)"
          hint="Accepted answers, separated by commas. Leave empty for an unscored question."
          label="Correct answer"
          :model-value="(q.correct ?? []).join(', ')"
          persistent-hint
          variant="outlined"
          @update:model-value="v => q.correct = String(v).split(',').map(s => s.trim()).filter(Boolean)"
        />

        <v-select
          v-else-if="f.kind === 'QUIZ' && q.type === 'CHECKBOX'"
          :items="[{ title: 'Yes', value: 'true' }, { title: 'No', value: 'false' }]"
          label="Correct answer"
          :model-value="q.correct?.[0] ?? null"
          variant="outlined"
          @update:model-value="v => q.correct = v ? [v] : []"
        />

        <v-text-field
          v-if="f.kind === 'QUIZ' && q.correct && q.correct.length > 0"
          v-model.number="q.points"
          label="Points"
          max="1000"
          min="1"
          type="number"
          variant="outlined"
        />
      </v-card>

      <div class="d-flex ga-2 flex-wrap mb-4">
        <v-btn :disabled="f.definition.questions.length >= 50" prepend-icon="mdi-plus" variant="outlined" @click="f.definition.questions.push(blankQuestion())">Add {{ noun.toLowerCase() }}</v-btn>
        <v-spacer />
        <v-btn to="/forms" variant="text">Cancel</v-btn>
        <v-btn color="primary" :loading="busy" type="submit">Save draft</v-btn>
      </div>
    </v-form>
  </div>
</template>

<script lang="ts" setup>
  import type { Audience, EditQuestion, FormBody, FormDetail } from '@/forms/api'
  import { computed, onMounted, reactive, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { extractErrorMessage } from '@/composables/useApi'
  import { blankQuestion, questionTypes, staffForms } from '@/forms/api'

  const props = defineProps<{ id?: string }>()
  const router = useRouter()
  const kinds = [{ title: 'Form (collect answers)', value: 'DATA_COLLECTION' }, { title: 'Quiz (scored)', value: 'QUIZ' }]
  const audiences = [
    { title: 'Anyone with the link', value: 'PUBLIC' },
    { title: 'Signed-in members', value: 'MEMBERS' },
    { title: 'Staff', value: 'STAFF' },
    { title: 'Members of selected groups', value: 'GROUPS' },
  ]
  const loading = ref(true)
  const busy = ref(false)
  const error = ref('')
  const detail = ref<FormDetail>()
  const f = reactive<FormBody>({
    title: '', description: '', kind: 'DATA_COLLECTION', audience: 'PUBLIC', identity: 'ANONYMOUS',
    opensAt: null, closesAt: null, maxResponses: null, retentionMonths: 3,
    leaderboardEnabled: false, leaderboardSize: 10, showScore: false, showAnswers: false,
    groupIds: [], definition: { questions: [blankQuestion()] },
  })
  const groupText = computed({
    get: () => f.groupIds.join(', '),
    set: (v: string) => {
      f.groupIds = v.split(',').map(s => s.trim()).filter(Boolean)
    },
  })

  // A plain form collects fields; only a quiz asks questions.
  const noun = computed(() => f.kind === 'QUIZ' ? 'Question' : 'Field')

  const isChoice = (q: EditQuestion) => q.type === 'SINGLE_CHOICE' || q.type === 'MULTIPLE_CHOICE'

  function typeChanged (q: EditQuestion) {
    q.correct = undefined
    if (isChoice(q) && q.options.length < 2) q.options = [{ id: 'a', label: '' }, { id: 'b', label: '' }]
    if (!isChoice(q)) q.options = []
  }

  function addOption (q: EditQuestion) {
    const used = new Set(q.options.map(o => o.id))
    let n = q.options.length
    while (used.has('o' + n)) n++
    q.options.push({ id: 'o' + n, label: '' })
  }

  function move (i: number, by: number) {
    const list = f.definition.questions
    const [item] = list.splice(i, 1)
    list.splice(i + by, 0, item!)
  }

  // datetime-local has no zone; the API takes an instant, so send it as UTC ISO and show it back in local time.
  const toInput = (iso: string | null) => iso ? new Date(iso).toISOString().slice(0, 16) : null
  const fromInput = (v: string | null) => v ? new Date(v).toISOString() : null

  function body (): FormBody {
    const quiz = f.kind === 'QUIZ'
    return {
      ...f,
      opensAt: fromInput(f.opensAt),
      closesAt: fromInput(f.closesAt),
      maxResponses: f.maxResponses || null,
      identity: f.audience === 'PUBLIC' ? 'ANONYMOUS' : f.identity,
      groupIds: f.audience === 'GROUPS' ? f.groupIds : [],
      definition: {
        questions: f.definition.questions.map(q => {
          const out: EditQuestion = { id: q.id, type: q.type, label: q.label.trim(), required: q.required, options: isChoice(q) ? q.options.map(o => ({ id: o.id, label: o.label.trim() })) : [] }
          if (quiz && q.correct && q.correct.length > 0) {
            out.correct = q.correct
            out.points = q.points ?? 1
          }
          return out
        }),
      },
    }
  }

  async function save () {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
      await (props.id && detail.value ? staffForms.update(props.id, body(), detail.value.version) : staffForms.create(body()))
      await router.push('/forms')
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      busy.value = false
    }
  }

  onMounted(async () => {
    try {
      if (props.id) {
        detail.value = await staffForms.get(props.id)
        const d = detail.value
        Object.assign(f, {
          title: d.title, description: d.description, kind: d.kind, audience: d.audience as Audience, identity: d.identity,
          opensAt: toInput(d.opensAt), closesAt: toInput(d.closesAt), maxResponses: d.maxResponses, retentionMonths: d.retentionMonths,
          leaderboardEnabled: d.leaderboardEnabled, leaderboardSize: d.leaderboardSize, showScore: d.showScore, showAnswers: d.showAnswers,
          groupIds: d.groupIds, definition: { questions: d.definition.questions.length > 0 ? d.definition.questions : [blankQuestion()] },
        })
      }
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  })
</script>
