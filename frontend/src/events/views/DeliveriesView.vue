<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="THOUGHTFUL FOLLOW-THROUGH"
      subtitle="Track queued, accepted and blocked notices. Delivery status never implies that a person attended."
      title="Every notice, accounted for."
    />

    <EventNavigation />

    <v-alert
      v-if="error"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <section class="directory-surface event-toolbar">
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
        class="event-delivery-row"
      >
        <div>
          <strong>Event notice</strong>

          <p class="directory-muted">
            {{ timeLabel(delivery.scheduledAt) }} ·
            {{ delivery.attempts }} attempt(s)
          </p>

          <small class="directory-muted">{{ delivery.id }}</small>

          <p
            v-if="delivery.errorCode"
            class="event-delivery-error"
          >
            {{ delivery.errorCode }}
          </p>

          <p
            v-if="delivery.status === 'BLOCKED'"
            class="directory-muted"
          >
            Booking, occurrence, recipient or sender eligibility changed. Review
            a new notice from the occurrence.
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
        No notices in this view. Send a reviewed notice from a booking on an
        occurrence.
      </p>
    </section>

    <div class="event-pagination">
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
  import { errorMessage as servingError, dateLabel as timeLabel } from '../api'
  import EventNavigation from '../components/EventNavigation.vue'
  import '@/directory/directory.scss'
  import '../events.css'
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
      }>('/api/events/deliveries', {
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
