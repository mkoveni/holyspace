<template>
  <section class="giving-evidence">
    <h3>Acknowledgement</h3>

    <v-alert
      v-if="error"
      class="my-3"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <p
      v-if="loading"
      class="directory-muted"
    >
      Loading acknowledgement…
    </p>

    <div
      v-else-if="ack"
      class="giving-section-heading mt-3"
    >
      <span>{{ ack.number }} · {{ ack.voided ? 'VOID' : 'Issued' }}</span>

      <v-btn
        :to="`/giving/receipts/${ack.id}`"
        variant="tonal"
      >
        Open acknowledgement
      </v-btn>
    </div>

    <p
      v-else
      class="directory-muted my-3"
    >
      No acknowledgement has been issued for this gift.
    </p>

    <v-btn
      v-if="
        !loading &&
          !ack &&
          !reversed &&
          auth.hasPermission('giving.acknowledge')
      "
      :loading="busy"
      variant="tonal"
      @click="issue"
    >
      Issue acknowledgement
    </v-btn>

    <v-btn
      v-if="donorId"
      :to="{ path: '/giving/reports', query: { donorId } }"
      variant="text"
    >
      View donor statement
    </v-btn>
  </section>
</template>
<script setup lang="ts">
  import type { Acknowledgement } from '../api'
  import { onBeforeUnmount, ref, watch } from 'vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import { useAuthStore } from '@/stores/auth'
  import { givingApi } from '../api'
  const props = defineProps<{
          giftId: string
          donorId: string | null
          reversed: boolean
        }>(),
        auth = useAuthStore()
  const ack = ref<Acknowledgement | null>(null),
        loading = ref(false),
        busy = ref(false),
        error = ref('')
  let generation = 0,
      requestId = crypto.randomUUID()
  async function load () {
    const current = ++generation
    ack.value = null
    loading.value = true
    error.value = ''
    try {
      const result = await givingApi.giftAcknowledgement(props.giftId)
      if (current === generation) ack.value = result
    } catch (error_) {
      if (current === generation)
        error.value = extractErrorMessage(
          error_,
          'Unable to load acknowledgement.',
        )
    } finally {
      if (current === generation) loading.value = false
    }
  }
  async function issue () {
    busy.value = true
    error.value = ''
    try {
      await givingApi.issueAcknowledgement(props.giftId, { requestId })
      await load()
    } catch (error_) {
      error.value = extractErrorMessage(
        error_,
        'Unable to issue acknowledgement.',
      )
    } finally {
      busy.value = false
    }
  }
  watch(
    () => props.giftId,
    () => {
      requestId = crypto.randomUUID()
      load()
    },
    { immediate: true },
  )
  watch(() => props.reversed, load)
  onBeforeUnmount(() => {
    generation++
  })
</script>
