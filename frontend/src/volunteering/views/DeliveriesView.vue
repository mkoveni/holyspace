<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="THOUGHTFUL FOLLOW-THROUGH"
      subtitle="Track queued, accepted and blocked reminders. Delivery status never implies that a volunteer is ready to serve."
      title="Every reminder, accounted for."
    />

    <ServingNavigation />

    <v-alert
      v-if="error"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <section class="directory-surface serving-controls">
      <v-select
        v-model="status"
        clearable
        hide-details
        :items="[
          'QUEUED',
          'SCHEDULED',
          'SENDING',
          'SENT',
          'FAILED',
          'BLOCKED',
          'UNKNOWN',
          'DELIVERED',
          'BOUNCED',
          'CANCELLED',
        ]"
        label="Status"
      />

      <v-btn
        :loading="loading"
        variant="tonal"
        @click="apply"
      >
        Show deliveries
      </v-btn>
    </section>

    <section class="directory-surface pa-6">
      <div
        v-for="delivery in deliveries"
        :key="delivery.id"
        class="serving-row"
      >
        <div>
          <strong>Volunteer reminder</strong>

          <p class="directory-muted">
            {{ timeLabel(delivery.scheduledAt) }} ·
            {{ delivery.attempts }} attempt(s)
          </p>

          <small class="directory-muted">{{ delivery.id }}</small>

          <p
            v-if="delivery.errorCode"
            class="serving-issues"
          >
            {{ delivery.errorCode }}
          </p>

          <p
            v-if="delivery.status === 'BLOCKED'"
            class="directory-muted"
          >
            Assignment, recipient or sender eligibility changed. Review a new
            reminder from the shift.
          </p>

          <p
            v-if="delivery.status === 'UNKNOWN'"
            class="directory-muted"
          >
            The transport outcome is uncertain. Confirm receipt before preparing
            another message.
          </p>
        </div>

        <v-chip
          :color="
            ['FAILED', 'BLOCKED', 'BOUNCED', 'UNKNOWN'].includes(
              delivery.status,
            )
              ? 'warning'
              : 'success'
          "
          size="small"
        >
          {{ delivery.status }}{{ delivery.captured ? ' · local inbox' : '' }}
        </v-chip>
      </div>

      <p
        v-if="!loading && deliveries.length === 0"
        class="directory-muted"
      >
        No reminders in this view. Send a reviewed reminder from a confirmed
        assignment.
      </p>
    </section>

    <div class="serving-pagination">
      <span class="directory-muted mr-auto">{{ total }} deliveries</span>

      <v-btn
        :disabled="!offset || loading"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= total || loading"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import { useApi } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { servingError, timeLabel } from '../api'
  import ServingNavigation from '../components/ServingNavigation.vue'
  import '@/directory/directory.scss'
  import '../serving.scss'
  interface Delivery {
    id: string
    status: string
    scheduledAt: string
    attempts: number
    errorCode: string | null
    captured: boolean
  }
  const deliveries = ref<Delivery[]>([]),
        total = ref(0),
        offset = ref(0),
        status = ref<string | null>(null),
        applied = ref<string | null>(null),
        loading = ref(false),
        error = ref('')
  async function load () {
    loading.value = true
    error.value = ''
    try {
      const response = await useApi().get<{
        deliveries: Delivery[]
        total: number
      }>('/api/volunteering/deliveries', {
        params: {
          status: applied.value || undefined,
          limit: 25,
          offset: offset.value,
        },
      })
      deliveries.value = response.data.deliveries
      total.value = response.data.total
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      loading.value = false
    }
  }
  function apply () {
    applied.value = status.value
    offset.value = 0
    void load()
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  onMounted(load)
</script>
