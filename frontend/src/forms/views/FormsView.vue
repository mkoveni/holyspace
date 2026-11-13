<template>
  <div>
    <PageHeader eyebrow="Connect with your church" subtitle="Surveys, sign-ups and quizzes for the public, members, staff or groups" title="Forms and quizzes">
      <template #actions><v-btn v-if="manage" color="primary" to="/forms/new">Create form</v-btn></template>
    </PageHeader>

    <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }} <v-btn variant="text" @click="load">Reload</v-btn></v-alert>
    <v-progress-linear v-if="loading" indeterminate />

    <v-card>
      <v-data-table :headers="headers" hide-default-footer :items="items" :items-per-page="-1">
        <template #item.title="{ item }"><router-link :to="`/forms/${item.id}/results`">{{ item.title }}</router-link></template>
        <template #item.kind="{ item }">{{ item.kind === 'QUIZ' ? 'Quiz' : 'Form' }}</template>
        <template #item.audience="{ item }">{{ audienceLabel[item.audience] }} · {{ item.identity === 'ANONYMOUS' ? 'anonymous' : 'identified' }}</template>

        <template #item.status="{ item }">
          <v-chip :color="item.status === 'PUBLISHED' ? 'success' : undefined" size="small">{{ item.status.toLowerCase() }}</v-chip>
        </template>

        <template #item.actions="{ item }">
          <template v-if="manage">
            <v-btn :disabled="busy" size="small" :to="`/forms/${item.id}`" variant="text">Edit</v-btn>

            <v-btn
              v-if="item.status !== 'PUBLISHED'"
              :disabled="busy"
              size="small"
              variant="text"
              @click="publish(item)"
            >Publish</v-btn>

            <v-btn
              v-if="item.status === 'PUBLISHED'"
              :disabled="busy"
              size="small"
              variant="text"
              @click="act(item, 'close')"
            >Close</v-btn>

            <v-btn
              v-if="item.status === 'CLOSED'"
              :disabled="busy"
              size="small"
              variant="text"
              @click="act(item, 'reopen')"
            >Reopen</v-btn>

            <v-btn
              v-if="item.status === 'PUBLISHED'"
              :disabled="busy"
              size="small"
              variant="text"
              @click="act(item, 'unpublish')"
            >Unpublish</v-btn>
          </template>

          <v-btn v-if="responsesRead" size="small" :to="`/forms/${item.id}/results`" variant="text">Results</v-btn>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="linkOpen" max-width="560">
      <v-card title="Public link">
        <v-card-text>
          <p class="mb-3">Copy this link now. It is shown only once; if you lose it, unpublish and publish again for a new one.</p>
          <v-text-field v-model="link" hide-details readonly variant="outlined" />
        </v-card-text>

        <v-card-actions><v-spacer /><v-btn @click="copy">Copy</v-btn><v-btn color="primary" @click="linkOpen = false">Done</v-btn></v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script lang="ts" setup>
  import type { FormSummaryRow } from '@/forms/api'
  import { computed, onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { staffForms } from '@/forms/api'
  import { useAuthStore } from '@/stores/auth'

  const auth = useAuthStore()
  const manage = computed(() => auth.hasPermission('forms.manage'))
  const responsesRead = computed(() => auth.hasPermission('forms.responses.read'))
  const items = ref<FormSummaryRow[]>([])
  const loading = ref(true)
  const busy = ref(false)
  const error = ref('')
  const link = ref('')
  const linkOpen = ref(false)
  const audienceLabel = { PUBLIC: 'Public', MEMBERS: 'Members', STAFF: 'Staff', GROUPS: 'Groups' }
  const headers = [
    { title: 'Title', key: 'title' },
    { title: 'Type', key: 'kind' },
    { title: 'Audience', key: 'audience' },
    { title: 'Status', key: 'status' },
    { title: 'Responses', key: 'responseCount' },
    { title: '', key: 'actions', sortable: false },
  ]

  async function load () {
    loading.value = true
    error.value = ''
    try {
      items.value = await staffForms.list(200)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  }

  async function run (work: () => Promise<void>) {
    busy.value = true
    error.value = ''
    try {
      await work()
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      busy.value = false
    }
  }

  function publish (f: FormSummaryRow) {
    return run(async () => {
      const { token } = await staffForms.publish(f.id, f.version)
      if (token) {
        link.value = `${globalThis.location.origin}/forms/public/${token}`
        linkOpen.value = true
      }
    })
  }

  function act (f: FormSummaryRow, action: 'close' | 'reopen' | 'unpublish') {
    return run(() => staffForms[action](f.id, f.version))
  }

  async function copy () {
    try {
      await navigator.clipboard.writeText(link.value)
    } catch {
      // The field is selectable, so the link can be copied by hand.
    }
  }

  onMounted(load)
</script>
