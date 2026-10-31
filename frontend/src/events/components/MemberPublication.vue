<template>
  <section class="directory-surface event-section mb-5">
    <span class="directory-eyebrow">MEMBER APP</span>
    <h2>Invite your community.</h2>

    <p class="directory-muted">
      Publish this event and its scheduled gatherings to verified members in
      Holyspace. Registration settings still control bookings. Withdrawn events
      remain visible to people who already booked.
    </p>

    <v-alert
      v-if="error"
      class="my-3"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <template v-if="offer">
      <v-chip
        class="my-3"
        :color="offer.enabled ? 'success' : undefined"
      >
        {{ offer.enabled ? 'Published to members' : 'Not published' }}
      </v-chip>

      <div>
        <v-btn
          :disabled="loading"
          :loading="saving"
          variant="tonal"
          @click="change"
        >
          {{
            offer.enabled ? 'Withdraw from member app' : 'Publish to member app'
          }}
        </v-btn>
      </div>
    </template>

    <v-btn
      v-else-if="!loading"
      variant="text"
      @click="load"
    >
      Retry publication settings
    </v-btn>
  </section>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import { errorMessage, eventApi } from '../api'
  const props = defineProps<{ eventId: string }>()
  const offer = ref<{ enabled: boolean, version: number }>(),
        loading = ref(false),
        saving = ref(false),
        error = ref('')
  async function load () {
    loading.value = true
    try {
      offer.value = await eventApi.memberOffer(props.eventId)
      error.value = ''
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  async function change () {
    if (!offer.value || saving.value) return
    saving.value = true
    error.value = ''
    try {
      await eventApi.publishToMembers(
        props.eventId,
        !offer.value.enabled,
        offer.value.version,
      )
      await load()
    } catch (error_) {
      const message = errorMessage(error_)
      await load()
      error.value = message
    } finally {
      saving.value = false
    }
  }
  onMounted(load)
</script>
