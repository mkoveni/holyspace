<template>
  <div>
    <v-btn class="mb-3" prepend-icon="mdi-arrow-left" to="/member/forms" variant="text">All forms</v-btn>
    <v-progress-linear v-if="loading" indeterminate />
    <v-alert v-else-if="!page" type="error" variant="tonal">{{ error || 'This form is not available.' }}</v-alert>

    <v-card v-else class="pa-5" max-width="720">
      <FormTaker :load-board="board" :page="page" :send="send" />
    </v-card>
  </div>
</template>

<script lang="ts" setup>
  import type { FormPage } from '@/forms/api'
  import { onMounted, ref } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { memberForms } from '@/forms/api'
  import FormTaker from '@/forms/components/FormTaker.vue'

  const props = defineProps<{ id: string }>()
  const page = ref<FormPage>()
  const loading = ref(true)
  const error = ref('')

  const board = () => memberForms.leaderboard(props.id)

  function send (body: Record<string, unknown>) {
    return memberForms.submit(props.id, {
      answers: body.answers as Record<string, unknown>,
      formVersion: body.formVersion as number,
      submissionKey: body.submissionKey as string,
      nickname: body.nickname as string | undefined,
    })
  }

  onMounted(async () => {
    try {
      page.value = await memberForms.get(props.id)
    } catch (error_) {
      error.value = extractErrorMessage(error_)
    } finally {
      loading.value = false
    }
  })
</script>
