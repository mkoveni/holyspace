<template>
  <div>
    <PageHeader eyebrow="My church" subtitle="Forms you have been invited to answer" title="Forms and quizzes" />
    <v-alert v-if="error" class="mb-4" type="error" variant="tonal">{{ error }} <v-btn variant="text" @click="load">Reload</v-btn></v-alert>
    <v-progress-linear v-if="loading" indeterminate />

    <v-card v-else>
      <v-list v-if="items.length > 0">
        <v-list-item
          v-for="f in items"
          :key="f.id"
          :subtitle="f.description"
          :title="f.title"
          :to="`/member/forms/${f.id}`"
        >
          <template #append><v-chip size="small">{{ f.kind === 'QUIZ' ? 'Quiz' : 'Form' }}</v-chip></template>
        </v-list-item>
      </v-list>

      <v-card-text v-else>There are no forms for you to answer right now.</v-card-text>
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import type { MemberFormSummary } from '@/forms/api'
  import { onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { memberForms } from '@/forms/api'

  const items = ref<MemberFormSummary[]>([])
  const loading = ref(true)
  const error = ref('')

  async function load () {
    loading.value = true
    error.value = ''
    try {
      items.value = await memberForms.list()
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
</script>
