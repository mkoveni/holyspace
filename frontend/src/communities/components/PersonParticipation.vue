<template>
  <section class="directory-surface community-section">
    <div class="d-flex flex-wrap ga-2 align-center mb-4">
      <h2 class="mr-auto">Groups &amp; ministries</h2>

      <v-btn
        to="/groups"
        variant="text"
      >
        Community directory
      </v-btn>
    </div>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <article
      v-for="entry in entries"
      :key="entry.membershipId"
      class="community-record"
    >
      <RouterLink :to="'/communities/' + entry.communityId">
        {{ entry.name }}
      </RouterLink>

      <p class="directory-muted">
        {{ entry.kind === 'GROUP' ? 'Group' : 'Ministry' }} · Joined
        {{ dateLabel(entry.startedAt) }} ·
        {{
          entry.endedAt ? 'Ended ' + dateLabel(entry.endedAt) : 'Current member'
        }}
      </p>
    </article>

    <p
      v-if="!loading && entries.length === 0"
      class="directory-muted"
    >
      No community participation on this page.
    </p>

    <div class="community-footer">
      <v-btn
        :disabled="offset === 0 || loading"
        variant="text"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="entries.length < 25 || loading"
        variant="text"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>
  </section>
</template>
<script setup lang="ts">
  import { ref, watch } from 'vue'
  import { useApi } from '@/composables/useApi'
  import { dateLabel, errorMessage } from '../api'
  import '../communities.css'
  const props = defineProps<{ personId: string }>()
  const entries = ref<
          {
            communityId: string
            kind: string
            name: string
            membershipId: string
            startedAt: string
            endedAt: string | null
          }[]
        >([]),
        offset = ref(0),
        loading = ref(false),
        error = ref('')
  let generation = 0
  async function load () {
    const current = ++generation
    loading.value = true
    try {
      const result = (
        await useApi().get<typeof entries.value>(
          `/api/communities/people/${props.personId}`,
          { params: { limit: 25, offset: offset.value } },
        )
      ).data
      if (current === generation) {
        entries.value = result
        error.value = ''
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  watch(
    () => props.personId,
    () => {
      offset.value = 0
      load()
    },
    { immediate: true },
  )
</script>
