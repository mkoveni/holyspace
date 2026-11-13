<template>
  <main class="public-form-page">
    <div class="public-form-shell public-form-shell--general">
      <div class="public-brand"><v-icon icon="mdi-church-outline" size="24" /><span>Church community</span></div>
      <div v-if="loading" aria-label="Loading form" role="status"><v-skeleton-loader type="article, actions" /></div>

      <section v-else-if="!page" class="public-unavailable">
        <v-icon color="primary" icon="mdi-link-variant-off" size="40" />
        <h1>This form isn't available right now</h1>
        <p role="alert">Contact the church for an updated link.</p>
        <v-btn color="primary" variant="flat" @click="load">Try again</v-btn>
      </section>

      <v-card v-else class="pa-5">
        <FormTaker :human="true" :load-board="board" :page="page" :send="send" />
      </v-card>
    </div>
  </main>
</template>

<script lang="ts" setup>
  import type { FormPage } from '@/forms/api'
  import { onMounted, ref } from 'vue'
  import { publicForms } from '@/forms/api'
  import FormTaker from '@/forms/components/FormTaker.vue'

  const props = defineProps<{ token: string }>()
  const loading = ref(true)
  const page = ref<FormPage>()

  const board = () => publicForms.leaderboard(props.token)
  const send = (body: Record<string, unknown>) => publicForms.submit(props.token, body)

  async function load () {
    loading.value = true
    try {
      page.value = await publicForms.get(props.token)
    } catch {
      // A missing, unpublished or closed form all look the same on purpose.
      page.value = undefined
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
</script>

<style scoped>
.public-form-page { min-height: 100vh; padding: 24px 16px; }
.public-form-shell { max-width: 640px; margin: 0 auto; }
.public-brand { display: flex; align-items: center; gap: 8px; margin-bottom: 16px; font-weight: 600; }
.public-unavailable { text-align: center; padding: 48px 0; }
</style>
