<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="EVERY ARRIVAL MATTERS"
      subtitle="Compare bookings with actual arrivals. Absence is counted only after a scheduled occurrence ends."
      title="Understand your gathering rhythm."
    >
      <v-btn
        :loading="exporting"
        prepend-icon="mdi-download-outline"
        variant="tonal"
        @click="download"
      >
        Export CSV
      </v-btn>
    </DirectoryHeading>

    <EventNavigation />

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
        label="Find an event"
        @keyup.enter="apply"
      />

      <v-btn
        color="primary"
        :loading="loading"
        @click="apply"
      >
        Show report
      </v-btn>
    </section>

    <v-alert
      v-if="error"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <div
      v-if="report"
      class="event-summary"
    >
      <div class="directory-surface">
        <span class="directory-eyebrow">GATHERINGS</span>
        <strong>{{ report.totals.occurrences }}</strong>
      </div>

      <div class="directory-surface">
        <span class="directory-eyebrow">ATTENDED</span>
        <strong>{{ report.totals.attended }}</strong>
      </div>

      <div class="directory-surface">
        <span class="directory-eyebrow">ABSENT AFTER END</span>
        <strong>{{ report.totals.absent }}</strong>
      </div>

      <div class="directory-surface">
        <span class="directory-eyebrow">WAITLISTED</span>
        <strong>{{ report.totals.waitlisted }}</strong>
      </div>
    </div>

    <section class="directory-surface event-section">
      <v-progress-linear
        v-if="loading"
        indeterminate
      />

      <div class="event-table">
        <v-table>
          <thead>
            <tr>
              <th>Gathering</th>
              <th>Date & venue</th>
              <th>Booking status</th>
              <th>Confirmed</th>
              <th>Waitlisted</th>
              <th>Cancelled bookings</th>
              <th>Attended</th>
              <th>Absent</th>
            </tr>
          </thead>

          <tbody>
            <tr
              v-for="row in report?.occurrences || []"
              :key="row.occurrenceId"
            >
              <td>
                <RouterLink :to="'/events/occurrences/' + row.occurrenceId">
                  {{ row.title }}
                </RouterLink>
              </td>

              <td>
                {{ dateLabel(row.startsAt, row.timeZone) }}
                <span class="d-block directory-muted">
                  {{ row.venue }} · {{ row.timeZone }}
                </span>
              </td>

              <td>{{ row.status }}</td>
              <td>{{ row.counts.confirmed }}</td>
              <td>{{ row.counts.waitlisted }}</td>
              <td>{{ row.counts.cancelled }}</td>
              <td>{{ row.counts.attended }}</td>
              <td>{{ row.counts.absent }}</td>
            </tr>
          </tbody>
        </v-table>
      </div>

      <p
        v-if="!loading && report && report.occurrences.length === 0"
        class="directory-muted pa-4"
      >
        No gatherings in this date range.
      </p>

      <div class="d-flex ga-3 align-center justify-end mt-5">
        <span>{{ report?.totals.occurrences || 0 }} occurrences</span>

        <v-btn
          :disabled="offset === 0 || loading"
          variant="text"
          @click="previous"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="
            offset + 25 >= (report?.totals.occurrences || 0) || loading
          "
          variant="text"
          @click="next"
        >
          Next
        </v-btn>
      </div>
    </section>

    <p class="directory-muted">
      Confirmed and cancelled are booking states. Historical arrivals remain
      visible when a gathering is later cancelled. Cancelled gatherings and
      waitlisted people are not counted as absent.
    </p>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import { useRoute } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { dateLabel, errorMessage, eventApi, type EventReport } from '../api'
  import EventNavigation from '../components/EventNavigation.vue'
  import '@/directory/directory.scss'
  import '../events.css'
  const route = useRoute(),
        eventId
          = typeof route.query.eventId === 'string' ? route.query.eventId : undefined
  const from = ref(
          new Date(Date.now() - 30 * 86_400_000).toISOString().slice(0, 10),
        ),
        until = ref(new Date(Date.now() + 86_400_000).toISOString().slice(0, 10)),
        search = ref(''),
        offset = ref(0),
        report = ref<EventReport>(),
        loading = ref(false),
        exporting = ref(false),
        error = ref('')
  let applied: object = {}
  function filters () {
    return {
      eventId,
      search: search.value || undefined,
      from: new Date(from.value + 'T00:00:00').toISOString(),
      until: new Date(until.value + 'T00:00:00').toISOString(),
    }
  }
  async function load () {
    loading.value = true
    try {
      report.value = await eventApi.report({ ...applied, offset: offset.value })
      error.value = ''
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  function apply () {
    try {
      applied = filters()
      offset.value = 0
      load()
    } catch (error_) {
      error.value = errorMessage(error_)
    }
  }
  function previous () {
    offset.value -= 25
    load()
  }
  function next () {
    offset.value += 25
    load()
  }
  async function download () {
    exporting.value = true
    try {
      const blob = await eventApi.exportReport(applied),
            url = URL.createObjectURL(blob),
            link = document.createElement('a')
      link.href = url
      link.download = 'event-attendance.csv'
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
      error.value = ''
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      exporting.value = false
    }
  }
  onMounted(apply)
</script>
