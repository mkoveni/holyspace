<template>
  <div>
    <h2 class="mb-4">A clear record of changes</h2>

    <article
      v-for="entry in entries"
      :key="entry.id"
      class="community-record"
    >
      <strong>{{ entry.action.replaceAll('_', ' ') }}</strong>
      <p class="community-note">{{ entry.reason }}</p>

      <p class="directory-muted mt-2">
        {{ dateLabel(entry.occurredAt) }}
      </p>
    </article>

    <EmptyState
      v-if="!loading && entries.length === 0"
      icon="mdi-history"
      title="No history on this page"
    />
  </div>
</template>

<script setup lang="ts">
  import EmptyState from '@/components/common/EmptyState.vue'
  import { dateLabel, type History } from '../api'

  defineProps<{ entries: History[], loading: boolean }>()
</script>
