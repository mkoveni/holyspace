<template>
  <div>
    <h2 class="mb-4">Every member notice, accounted for</h2>

    <p class="directory-muted mb-4">
      Approved content stays frozen. Sending rechecks membership, contact
      and sender access. Captured messages remain in the local email
      inbox.
    </p>

    <article
      v-for="item in rows"
      :key="item.id"
      class="community-record"
    >
      <strong>{{ item.status }}</strong>

      <p class="directory-muted">
        {{ dateLabel(item.scheduledAt) }} · {{ item.attempts }} attempts
        <span v-if="item.captured">· Captured locally</span>
      </p>

      <p v-if="item.errorCode">{{ item.errorCode }}</p>
    </article>

    <EmptyState
      v-if="!loading && rows.length === 0"
      icon="mdi-email-check-outline"
      title="No member notices on this page"
    />
  </div>
</template>

<script setup lang="ts">
  import EmptyState from '@/components/common/EmptyState.vue'
  import { dateLabel, type MemberDelivery } from '../api'

  defineProps<{ rows: MemberDelivery[], loading: boolean }>()
</script>
