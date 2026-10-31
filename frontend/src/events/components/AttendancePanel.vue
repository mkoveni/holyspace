<template>
  <section class="directory-surface event-section">
    <div class="d-flex ga-3 align-center flex-wrap mb-4">
      <div class="mr-auto">
        <span class="directory-eyebrow">WELCOME EVERY PERSON</span>
        <h2 class="mt-2 mb-0">Attendance desk</h2>
      </div>

      <MemberAttendanceScanner
        v-if="manage"
        :admission-open="Boolean(page?.admissionOpen)"
        :occurrence-id="props.occurrenceId"
        @checked-in="scanned"
      />

      <v-btn
        v-if="manage && peopleRead"
        color="primary"
        :disabled="!page?.admissionOpen"
        prepend-icon="mdi-account-plus-outline"
        @click="startWalkIn()"
      >
        Register walk-in
      </v-btn>

      <v-btn
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="load"
      >
        Refresh attendance
      </v-btn>
    </div>

    <p class="directory-muted mb-4">
      Check-in runs from two hours before the gathering until its end. A booking
      records a place; attendance records an arrival. Times are shown in
      {{ props.timeZone }}.
    </p>

    <v-alert
      v-if="page && !page.admissionOpen"
      class="mb-4"
      type="info"
      variant="tonal"
    >
      Arrival recording is currently closed. Existing attendance and its
      correction history remain available.
    </v-alert>

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="outcome"
      class="mb-4"
      :type="outcome.admitted ? 'success' : 'info'"
      variant="tonal"
    >
      {{
        outcome.admitted
          ? 'Walk-in registered and checked in.'
          : 'Added to the waitlist. This person has not been checked in.'
      }}
    </v-alert>

    <div
      v-if="page"
      class="event-summary"
    >
      <div>
        <span class="directory-eyebrow">ATTENDED</span>
        <strong>{{ page.counts.attended }}</strong>
      </div>

      <div>
        <span class="directory-eyebrow">CONFIRMED BOOKINGS</span>
        <strong>{{ page.counts.confirmed }}</strong>
      </div>

      <div>
        <span class="directory-eyebrow">WAITLISTED</span>
        <strong>{{ page.counts.waitlisted }}</strong>
      </div>

      <div>
        <span class="directory-eyebrow">ABSENT AFTER END</span>
        <strong>{{ page.counts.absent }}</strong>
      </div>
    </div>

    <div class="event-toolbar pa-0">
      <v-text-field
        v-model="search"
        clearable
        hide-details
        label="Find an attendee"
        @keyup.enter="apply"
      />

      <v-select
        v-model="status"
        clearable
        hide-details
        :items="['PRESENT', 'EXPECTED', 'ABSENT', 'WAITLISTED', 'CANCELLED']"
        label="Attendance status"
      />

      <v-btn
        variant="tonal"
        @click="apply"
      >
        Show attendees
      </v-btn>
    </div>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <div class="event-table event-attendance-table">
      <v-table>
        <thead>
          <tr>
            <th>Person</th>
            <th>Attendance</th>
            <th>Booking</th>
            <th>Arrival</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="entry in page?.attendees || []"
            :key="entry.registration.id"
          >
            <td>{{ entry.name }}</td>

            <td>
              <v-chip
                :color="
                  entry.status === 'PRESENT'
                    ? 'success'
                    : entry.status === 'ABSENT'
                      ? 'warning'
                      : undefined
                "
                size="small"
              >
                {{ entry.status }}
              </v-chip>

              <small
                v-if="entry.attendance?.status === 'VOIDED'"
                class="d-block directory-muted"
              >
                Arrival voided
              </small>
            </td>

            <td data-label="Booking">{{ entry.registration.status }}</td>

            <td data-label="Arrival">
              {{
                entry.attendance
                  ? dateLabel(entry.attendance.arrivedAt, props.timeZone)
                  : '—'
              }}
            </td>

            <td>
              <div class="d-flex ga-1 flex-wrap">
                <v-btn
                  v-if="
                    manage &&
                      !entry.attendance &&
                      entry.registration.status === 'CONFIRMED'
                  "
                  color="primary"
                  :disabled="!page?.admissionOpen || busy"
                  size="small"
                  variant="tonal"
                  @click="checkIn(entry)"
                >
                  Check in
                </v-btn>

                <v-btn
                  v-if="manage && entry.attendance"
                  size="small"
                  variant="text"
                  @click="startCorrection(entry)"
                >
                  Correct attendance
                </v-btn>

                <v-btn
                  v-if="entry.attendance"
                  size="small"
                  variant="text"
                  @click="showHistory(entry)"
                >
                  Attendance history
                </v-btn>

                <v-btn
                  v-if="
                    manage &&
                      peopleRead &&
                      entry.registration.status === 'CANCELLED' &&
                      !entry.attendance
                  "
                  :disabled="!page?.admissionOpen"
                  size="small"
                  variant="text"
                  @click="startWalkIn(entry)"
                >
                  Register again at door
                </v-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </v-table>
    </div>

    <p
      v-if="!loading && page && page.attendees.length === 0"
      class="directory-muted pa-4"
    >
      No attendees in this view.
    </p>

    <div class="d-flex ga-3 align-center justify-end mt-4">
      <span class="directory-muted">{{ page?.total || 0 }} people</span>

      <v-btn
        :disabled="offset === 0 || loading"
        variant="text"
        @click="previous"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= (page?.total || 0) || loading"
        variant="text"
        @click="next"
      >
        Next
      </v-btn>
    </div>

    <EventDialog
      v-model="walkInOpen"
      save-label="Register walk-in"
      :submit="saveWalkIn"
      subtitle="Registration must be open. Existing waitlisted people keep priority; a waitlisted walk-in is never admitted automatically."
      title="Welcome a walk-in"
      @reload="reload"
      @saved="changed"
    >
      <PersonPicker
        v-if="!rejoining"
        v-model="personId"
        label="Find a person"
        :rules="[required]"
      />

      <p v-else>
        {{ rejoining.name }} will receive a new place in queue order.
      </p>
    </EventDialog>

    <EventDialog
      v-model="correctionOpen"
      :submit="saveCorrection"
      subtitle="Corrections preserve the original arrival and record who changed it and why."
      title="Correct attendance"
      @reload="reload"
      @saved="changed"
    >
      <p class="mb-4">{{ selected?.name }}</p>

      <v-select
        v-model="correctionStatus"
        :items="[
          {
            title: 'Present — reinstate the recorded arrival',
            value: 'PRESENT',
          },
          {
            title: 'Voided — the arrival was recorded in error',
            value: 'VOIDED',
          },
        ]"
        label="Corrected attendance"
      />

      <v-textarea
        v-model="reason"
        label="Reason for correction"
        maxlength="1000"
        :rules="[required]"
      />
    </EventDialog>

    <v-dialog
      v-model="historyOpen"
      max-width="700"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">A CLEAR RECORD</span>
            <h2>Attendance history</h2>
          </div>

          <v-btn
            aria-label="Close attendance history"
            icon="mdi-close"
            variant="text"
            @click="historyOpen = false"
          />
        </div>

        <v-card-text>
          <v-alert
            v-if="historyError"
            type="error"
            variant="tonal"
          >
            {{ historyError }}
          </v-alert>

          <v-progress-linear
            v-if="historyLoading"
            indeterminate
          />

          <div
            v-for="entry in history"
            :key="entry.id"
            class="event-change"
          >
            <strong>{{ entry.action }}</strong>
            <p>{{ entry.reason }}</p>

            <p class="directory-muted">
              {{ dateLabel(entry.occurredAt, props.timeZone) }}
            </p>
          </div>
        </v-card-text>

        <v-card-actions>
          <v-btn
            :disabled="historyOffset === 0 || historyLoading"
            @click="previousHistory"
          >
            Previous
          </v-btn>

          <v-spacer />

          <v-btn
            :disabled="history.length < 25 || historyLoading"
            @click="nextHistory"
          >
            Next
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>
<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type AttendanceEntry,
    type AttendanceHistory,
    type AttendancePage,
    dateLabel,
    errorMessage,
    eventApi,
    required,
  } from '../api'
  import EventDialog from './EventDialog.vue'
  import MemberAttendanceScanner from './MemberAttendanceScanner.vue'
  function scanned () {
    load()
    emit('changed')
  }
  const props = defineProps<{ occurrenceId: string, timeZone: string }>()
  const emit = defineEmits<{ changed: [] }>()
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.attendance.manage')),
        peopleRead = computed(() => auth.hasPermission('people.read'))
  const page = ref<AttendancePage>(),
        search = ref(''),
        status = ref<string | null>(null),
        offset = ref(0),
        loading = ref(false),
        busy = ref(false),
        error = ref(''),
        outcome = ref<{ admitted: boolean }>()
  let generation = 0
  const applied = ref({ search: '', status: undefined as string | undefined })
  let timer: ReturnType<typeof setInterval> | undefined
  const checkRequests = new Map<string, string>()
  async function load () {
    const current = ++generation
    loading.value = true
    try {
      const result = await eventApi.attendance(
        props.occurrenceId,
        applied.value.search,
        applied.value.status,
        offset.value,
      )
      if (current === generation) {
        page.value = result
        error.value = ''
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function apply () {
    offset.value = 0
    applied.value = {
      search: search.value || '',
      status: status.value || undefined,
    }
    load()
  }
  function previous () {
    offset.value -= 25
    load()
  }
  function next () {
    offset.value += 25
    load()
  }
  function changed () {
    load()
    emit('changed')
  }
  async function checkIn (entry: AttendanceEntry) {
    if (busy.value) return
    busy.value = true
    try {
      const key = entry.registration.id + ':' + entry.registration.version
      let requestId = checkRequests.get(key)
      if (!requestId) {
        requestId = crypto.randomUUID()
        checkRequests.set(key, requestId)
      }
      await eventApi.checkIn(entry.registration.id, {
        requestId,
        expectedVersion: entry.registration.version,
      })
      changed()
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  const walkInOpen = ref(false),
        personId = ref<string | null>(null),
        rejoining = ref<AttendanceEntry>()
  let walkRequest = crypto.randomUUID()
  watch(personId, () => {
    walkRequest = crypto.randomUUID()
  })
  function startWalkIn (entry?: AttendanceEntry) {
    rejoining.value = entry
    personId.value = entry?.registration.personId || null
    walkRequest = crypto.randomUUID()
    outcome.value = undefined
    walkInOpen.value = true
  }
  async function saveWalkIn () {
    outcome.value = await eventApi.walkIn(props.occurrenceId, {
      requestId: walkRequest,
      personId: personId.value,
      expectedVersion: rejoining.value?.registration.version,
    })
  }
  const correctionOpen = ref(false),
        selected = ref<AttendanceEntry>(),
        correctionStatus = ref('VOIDED'),
        reason = ref('')
  function startCorrection (entry: AttendanceEntry) {
    selected.value = entry
    correctionStatus.value
      = entry.attendance?.status === 'PRESENT' ? 'VOIDED' : 'PRESENT'
    reason.value = ''
    correctionOpen.value = true
  }
  async function saveCorrection () {
    if (!selected.value?.attendance) return
    await eventApi.correctAttendance(selected.value.registration.id, {
      expectedVersion: selected.value.attendance.version,
      status: correctionStatus.value,
      reason: reason.value,
    })
  }
  function reload () {
    walkInOpen.value = false
    correctionOpen.value = false
    changed()
  }
  const historyOpen = ref(false),
        history = ref<AttendanceHistory[]>([]),
        historyTarget = ref(''),
        historyOffset = ref(0),
        historyLoading = ref(false),
        historyError = ref('')
  function showHistory (entry: AttendanceEntry) {
    historyTarget.value = entry.registration.id
    historyOffset.value = 0
    historyOpen.value = true
    loadHistory()
  }
  async function loadHistory () {
    historyLoading.value = true
    try {
      history.value = await eventApi.attendanceHistory(
        historyTarget.value,
        historyOffset.value,
      )
      historyError.value = ''
    } catch (error_) {
      historyError.value = errorMessage(error_)
    } finally {
      historyLoading.value = false
    }
  }
  function previousHistory () {
    historyOffset.value -= 25
    loadHistory()
  }
  function nextHistory () {
    historyOffset.value += 25
    loadHistory()
  }
  onMounted(() => {
    load()
    timer = setInterval(() => {
      if (
        !busy.value
        && !walkInOpen.value
        && !correctionOpen.value
        && !historyOpen.value
      )
        load()
    }, 30_000)
  })
  onBeforeUnmount(() => {
    generation++
    clearInterval(timer)
  })
</script>
