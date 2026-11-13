<template>
  <div>
    <PageHeader eyebrow="Forms and quizzes" :subtitle="subtitle" :title="form?.title ?? 'Results'">
      <template #actions>
        <v-btn
          v-if="form"
          :loading="exporting"
          prepend-icon="mdi-download"
          variant="outlined"
          @click="download"
        >Export CSV</v-btn>
      </template>
    </PageHeader>

    <v-alert
      v-if="error"
      class="mb-4"
      role="alert"
      type="error"
      variant="tonal"
    >{{ error }}</v-alert>

    <v-progress-linear v-if="loading" indeterminate />

    <template v-if="form && summary">
      <v-alert
        v-if="form.identity === 'ANONYMOUS'"
        class="mb-4"
        density="compact"
        type="info"
        variant="tonal"
      >
        Anonymous form: responses carry only a date. Responses are deleted
        {{ form.nextPurgeOn ? `from ${form.nextPurgeOn}` : `after ${form.retentionMonths} months` }}.
      </v-alert>

      <v-alert v-if="summary.hidden" class="mb-4" type="warning" variant="tonal">
        Results stay hidden until at least {{ summary.minimumForAnonymous }} people have answered, so no one can be recognised.
        ({{ summary.responses }} so far.)
      </v-alert>

      <h2 v-else class="text-h6 mb-2">{{ noun }} summary</h2>

      <v-row v-if="!summary.hidden">
        <v-col v-for="q in summary.questions" :key="String(q.id)" cols="12" md="6">
          <v-card class="pa-4 h-100">
            <strong>{{ q.label }}</strong>
            <div class="text-medium-emphasis mb-2">{{ q.answered }} answered</div>

            <template v-if="Array.isArray(q.options)">
              <div v-for="o in (q.options as { id: string, label: string, count: number }[])" :key="o.id" class="mb-1">
                {{ o.label }}: {{ o.count }}
                <v-progress-linear color="primary" height="6" :model-value="percent(o.count, Number(q.answered))" />
              </div>
            </template>

            <template v-else-if="'average' in q">Average {{ q.average ?? 'n/a' }} (min {{ q.min ?? 'n/a' }}, max {{ q.max ?? 'n/a' }})</template>
            <template v-else-if="'yes' in q">Yes {{ q.yes }}, no {{ q.no }}</template>
          </v-card>
        </v-col>
      </v-row>

      <v-card v-if="canRead" class="mt-4">
        <v-card-title>Responses ({{ page?.total ?? 0 }})</v-card-title>

        <v-data-table :headers="headers" hide-default-footer :items="page?.items ?? []" :items-per-page="-1">
          <template #item.submitted="{ item }">{{ item.submittedAt ? item.submittedAt.slice(0, 16).replace('T', ' ') : item.submittedOn }}</template>
          <template #item.score="{ item }">{{ item.score === null ? '' : `${item.score} / ${item.maxScore}` }}</template>
          <template #item.answers="{ item }"><code>{{ answerText(item.answers) }}</code></template>

          <template #item.actions="{ item }">
            <v-btn v-if="canModerate && item.nickname && !item.hidden" size="small" variant="text" @click="hide(item.id)">Hide from leaderboard</v-btn>
            <span v-else-if="item.hidden" class="text-medium-emphasis">hidden</span>
          </template>
        </v-data-table>

        <v-card-actions>
          <v-btn :disabled="offset === 0" @click="go(offset - pageSize)">Previous</v-btn>
          <v-btn :disabled="!page || offset + pageSize >= page.total" @click="go(offset + pageSize)">Next</v-btn>
        </v-card-actions>
      </v-card>
    </template>
  </div>
</template>

<script lang="ts" setup>
  import type { FormDetail, ResponsePage, Summary } from '@/forms/api'
  import { computed, onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { staffForms } from '@/forms/api'
  import { useAuthStore } from '@/stores/auth'

  const props = defineProps<{ id: string }>()
  const auth = useAuthStore()
  const canRead = computed(() => auth.hasPermission('forms.responses.read'))
  const canModerate = computed(() => auth.hasPermission('forms.responses.moderate'))
  const pageSize = 50
  const form = ref<FormDetail>()
  const summary = ref<Summary>()
  const page = ref<ResponsePage>()
  const offset = ref(0)
  const loading = ref(true)
  const exporting = ref(false)
  const error = ref('')
  const subtitle = computed(() => form.value ? `${form.value.responseCount} responses` : '')
  const headers = computed(() => [
    { title: 'Submitted', key: 'submitted', sortable: false },
    ...(page.value?.identified ? [{ title: 'Person', key: 'personId', sortable: false }] : []),
    { title: 'Nickname', key: 'nickname', sortable: false },
    { title: 'Score', key: 'score', sortable: false },
    { title: 'Answers', key: 'answers', sortable: false },
    { title: '', key: 'actions', sortable: false },
  ])

  const noun = computed(() => form.value?.kind === 'QUIZ' ? 'Questions' : 'Fields')
  const percent = (n: number, total: number) => total > 0 ? Math.round((n / total) * 100) : 0
  const answerText = (a: Record<string, unknown>) => JSON.stringify(a)

  async function go (to: number) {
    offset.value = Math.max(0, to)
    try {
      page.value = await staffForms.responses(props.id, pageSize, offset.value)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    }
  }

  async function hide (responseId: string) {
    try {
      await staffForms.hide(props.id, responseId)
      await go(offset.value)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    }
  }

  async function download () {
    exporting.value = true
    try {
      const csv = await staffForms.exportCsv(props.id)
      const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }))
      const a = document.createElement('a')
      a.href = url
      a.download = 'form-responses.csv'
      a.click()
      URL.revokeObjectURL(url)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      exporting.value = false
    }
  }

  onMounted(async () => {
    try {
      form.value = await staffForms.get(props.id)
      summary.value = await staffForms.summary(props.id)
      if (canRead.value) await go(0)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  })
</script>
