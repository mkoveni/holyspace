<template>
  <section class="directory-surface event-toolbar">
    <DateSelector
      v-model="from"
      label="From"
    />

    <DateSelector
      v-model="until"
      label="Until (exclusive)"
    />

    <v-text-field
      v-model="search"
      clearable
      hide-details
      label="Search events"
      @keyup.enter="apply"
    />

    <v-select
      v-model="status"
      clearable
      hide-details
      :items="['SCHEDULED', 'CANCELLED']"
      label="Status"
    />

    <v-btn
      :loading="loading"
      variant="tonal"
      @click="apply"
    >
      Show dates
    </v-btn>
  </section>

  <v-alert
    v-if="error"
    class="mb-4"
    type="error"
    variant="tonal"
  >
    {{ error }}
  </v-alert>

  <v-progress-linear
    v-if="loading"
    aria-label="Loading occurrences"
    indeterminate
  />

  <div
    v-else
    class="event-grid"
  >
    <article
      v-for="item in entries"
      :key="item.occurrence.id"
      class="directory-surface event-card"
    >
      <div class="d-flex justify-space-between align-center">
        <v-icon
          color="primary"
          icon="mdi-calendar-star"
        />

        <v-chip
          :color="
            item.occurrence.status === 'CANCELLED' ? 'warning' : 'success'
          "
          size="small"
        >
          {{
            item.occurrence.status === 'CANCELLED' ? 'Cancelled' : 'Scheduled'
          }}
        </v-chip>
      </div>

      <h2>{{ item.title }}</h2>

      <p class="event-meta">
        <v-icon
          icon="mdi-clock-outline"
          size="18"
        />
        {{ dateLabel(item.occurrence.startsAt, item.occurrence.timeZone) }}
      </p>

      <p class="directory-muted">
        Until
        {{ dateLabel(item.occurrence.endsAt, item.occurrence.timeZone) }} ·
        {{ item.occurrence.timeZone }}
      </p>

      <p class="event-meta">
        <v-icon
          icon="mdi-map-marker-outline"
          size="18"
        />
        {{ item.occurrence.venueName }}
      </p>

      <v-chip
        v-if="item.occurrence.overridden"
        class="align-self-start"
        size="small"
        variant="outlined"
      >
        Individual change
      </v-chip>

      <div class="d-flex ga-2 mt-auto flex-wrap">
        <v-btn
          color="primary"
          :to="'/events/occurrences/' + item.occurrence.id"
          variant="tonal"
        >
          Open occurrence
        </v-btn>

        <v-btn
          v-if="!eventId"
          :to="'/events/' + item.occurrence.eventId"
          variant="text"
        >
          Event details
        </v-btn>
      </div>
    </article>
  </div>

  <div
    v-if="!loading && entries.length === 0"
    class="directory-surface event-section text-center"
  >
    <v-icon
      color="primary"
      icon="mdi-calendar-blank-outline"
      size="40"
    />

    <h2 class="mt-3">No dates in this view</h2>

    <p class="directory-muted">
      Try another date range or plan your next gathering.
    </p>
  </div>

  <div class="d-flex align-center justify-end ga-3 mt-5">
    <span class="directory-muted">{{ total }} occurrences</span>

    <v-btn
      :disabled="offset === 0 || loading"
      variant="text"
      @click="
        () => {
          offset -= 25
          load()
        }
      "
    >
      Previous
    </v-btn>

    <v-btn
      :disabled="offset + 25 >= total || loading"
      variant="text"
      @click="
        () => {
          offset += 25
          load()
        }
      "
    >
      Next
    </v-btn>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { type CalendarItem, dateLabel, errorMessage, eventApi } from '../api'
  const props = defineProps<{ eventId?: string }>()
  const from = ref(new Date().toISOString().slice(0, 10)),
        until = ref(
          new Date(Date.now() + 90 * 86_400_000).toISOString().slice(0, 10),
        ),
        search = ref(''),
        status = ref<string | null>(null),
        offset = ref(0),
        total = ref(0),
        entries = ref<CalendarItem[]>([]),
        loading = ref(false),
        error = ref('')
  let generation = 0
  async function load () {
    const current = ++generation
    loading.value = true
    error.value = ''
    try {
      const page = await eventApi.calendar({
        eventId: props.eventId,
        from: new Date(from.value + 'T00:00:00').toISOString(),
        until: new Date(until.value + 'T00:00:00').toISOString(),
        search: search.value || undefined,
        status: status.value || undefined,
        offset: offset.value,
      })
      if (current === generation) {
        entries.value = page.occurrences
        total.value = page.total
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function apply () {
    offset.value = 0
    load()
  }
  onMounted(load)
  defineExpose({ load })
</script>
