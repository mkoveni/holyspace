<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="ONE DATE. EVERY DETAIL."
      subtitle="Keep the gathering, its bookings and every change in one place."
      :title="details?.title || 'Occurrence'"
    >
      <v-btn
        v-if="details"
        prepend-icon="mdi-arrow-left"
        :to="'/events/' + details.occurrence.eventId"
        variant="text"
      >
        Event details
      </v-btn>
    </DirectoryHeading>

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

    <template v-if="details">
      <section class="directory-surface event-section">
        <div class="d-flex ga-3 flex-wrap mb-4">
          <v-chip :color="scheduled ? 'success' : 'warning'">
            {{ scheduled ? 'Scheduled' : 'Cancelled' }}
          </v-chip>

          <v-chip
            v-if="details.occurrence.overridden"
            variant="outlined"
          >
            Individual change
          </v-chip>
        </div>

        <h2>
          {{
            dateLabel(details.occurrence.startsAt, details.occurrence.timeZone)
          }}
        </h2>

        <p class="directory-muted">
          Until
          {{
            dateLabel(details.occurrence.endsAt, details.occurrence.timeZone)
          }}
          · {{ details.occurrence.timeZone }}
        </p>

        <p class="mt-4">
          <v-icon
            class="mr-2"
            icon="mdi-map-marker-outline"
          />
          {{ details.occurrence.venueName }}
        </p>

        <p class="directory-muted mt-1">
          {{ details.occurrence.venueAddress }}
        </p>

        <div
          v-if="manage"
          class="d-flex ga-2 flex-wrap mt-5"
        >
          <v-btn
            v-if="scheduled && upcoming"
            color="primary"
            variant="tonal"
            @click="startChange('RESCHEDULE')"
          >
            Reschedule
          </v-btn>

          <v-btn
            v-if="scheduled && notEnded"
            color="warning"
            variant="tonal"
            @click="startChange('CANCEL')"
          >
            Cancel occurrence
          </v-btn>

          <v-btn
            v-if="!scheduled && upcoming"
            color="primary"
            variant="tonal"
            @click="startChange('RESTORE')"
          >
            Restore occurrence
          </v-btn>
        </div>
      </section>

      <EventReadinessPanel
        v-if="manage"
        :occurrence-id="id"
        :revision="readinessRevision"
        :time-zone="details.occurrence.timeZone"
      />

      <VisitorCheckInPanel
        v-if="auth.hasPermission('events.attendance.manage') || auth.hasPermission('events.visitors.read')"
        :occurrence-id="id"
        @changed="load"
      />

      <TicketSalesPanel
        v-if="auth.hasPermission('events.tickets.read') || auth.hasPermission('events.tickets.manage')"
        :occurrence-id="id"
      />

      <AttendancePanel
        v-if="auth.hasPermission('events.attendance.read')"
        :occurrence-id="id"
        :time-zone="details.occurrence.timeZone"
        @changed="load"
      />

      <v-alert
        v-if="noticeQueued"
        class="mb-4"
        type="success"
        variant="tonal"
      >
        Reviewed notice queued.
        <RouterLink to="/events/email-deliveries">
          Track email delivery
        </RouterLink>
      </v-alert>

      <div
        v-if="readRegistrations"
        class="event-summary"
      >
        <div class="directory-surface">
          <span class="directory-eyebrow">CONFIRMED</span>
          <strong>{{ details.registrations.confirmed }}</strong>
        </div>

        <div class="directory-surface">
          <span class="directory-eyebrow">WAITLISTED</span>
          <strong>{{ details.registrations.waitlisted }}</strong>
        </div>

        <div class="directory-surface">
          <span class="directory-eyebrow">REGISTRATION</span>

          <strong>
            {{ details.registrationPolicy.open ? 'Open' : 'Closed' }}
          </strong>

          <span class="directory-muted">
            {{
              details.registrationPolicy.capacity === null
                ? 'No seat limit'
                : details.registrationPolicy.capacity + ' places'
            }}
            ·
            {{
              details.registrationPolicy.waitlistEnabled
                ? 'Waitlist enabled'
                : 'No waitlist'
            }}
          </span>
        </div>
      </div>

      <section
        v-if="readRegistrations"
        id="event-bookings"
        class="directory-surface event-section"
      >
        <div class="d-flex flex-wrap ga-2 align-center mb-5">
          <h2 class="mr-auto mb-0">Bookings</h2>

          <v-btn
            v-if="manageRegistrations && notEnded && scheduled"
            variant="tonal"
            @click="configure"
          >
            Registration settings
          </v-btn>

          <v-btn
            v-if="
              canRegister &&
                upcoming &&
                scheduled &&
                details.registrationPolicy.open
            "
            color="primary"
            @click="startRegister()"
          >
            Register person
          </v-btn>
        </div>

        <v-select
          v-model="registrationStatus"
          class="mb-2"
          clearable
          :items="['CONFIRMED', 'WAITLISTED', 'CANCELLED']"
          label="Registration status"
          @update:model-value="
            () => {
              registrationOffset = 0
              loadRegistrations()
            }
          "
        />

        <v-alert
          v-if="registrationError"
          type="error"
          variant="tonal"
        >
          {{ registrationError }}
        </v-alert>

        <v-progress-linear
          v-if="registrationsLoading"
          indeterminate
        />

        <div class="event-table">
          <v-table>
            <thead>
              <tr>
                <th>Person</th>
                <th>Status</th>
                <th>Queue order</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="item in registrations"
                :key="item.registration.id"
              >
                <td>{{ item.name }}</td>

                <td>
                  <v-chip
                    :color="
                      item.registration.status === 'CONFIRMED'
                        ? 'success'
                        : undefined
                    "
                    size="small"
                  >
                    {{ item.registration.status }}
                  </v-chip>
                </td>

                <td>{{ item.registration.position }}</td>

                <td>
                  <v-btn
                    v-if="auth.hasPermission('events.communications.send')"
                    size="small"
                    variant="text"
                    @click="startNotice(item)"
                  >
                    Prepare email notice
                  </v-btn>

                  <v-btn
                    size="small"
                    variant="text"
                    @click="showHistory('registrations', item.registration.id)"
                  >
                    History
                  </v-btn>

                  <v-btn
                    v-if="
                      manageRegistrations &&
                        notEnded &&
                        item.registration.status !== 'CANCELLED'
                    "
                    size="small"
                    variant="text"
                    @click="startCancel(item.registration)"
                  >
                    Cancel booking
                  </v-btn>

                  <v-btn
                    v-if="
                      canRegister &&
                        upcoming &&
                        scheduled &&
                        details.registrationPolicy.open &&
                        item.registration.status === 'CANCELLED'
                    "
                    size="small"
                    variant="text"
                    @click="startRegister(item.registration)"
                  >
                    Register again
                  </v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </div>

        <p
          v-if="!registrationsLoading && registrations.length === 0"
          class="directory-muted pa-4"
        >
          No registrations in this view.
        </p>

        <div class="d-flex justify-end align-center ga-2 mt-4">
          <span>{{ registrationTotal }} records</span>

          <v-btn
            :disabled="registrationOffset === 0 || registrationsLoading"
            variant="text"
            @click="
              () => {
                registrationOffset -= 25
                loadRegistrations()
              }
            "
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              registrationOffset + 25 >= registrationTotal ||
                registrationsLoading
            "
            variant="text"
            @click="
              () => {
                registrationOffset += 25
                loadRegistrations()
              }
            "
          >
            Next
          </v-btn>
        </div>
      </section>

      <section class="directory-surface event-section">
        <h2>A clear record of changes</h2>

        <p class="directory-muted mb-3">
          Dates keep the same identity when moved. Cancellation closes
          registration; restoration leaves previous bookings cancelled.
        </p>

        <v-btn
          prepend-icon="mdi-history"
          variant="tonal"
          @click="showHistory('occurrences', id)"
        >
          View occurrence history
        </v-btn>
      </section>

      <EventNoticeDialog
        v-model="noticeOpen"
        :booking-status="noticeTarget?.registration.status || ''"
        :name="noticeTarget?.name || ''"
        :registration-id="noticeTarget?.registration.id || ''"
        @saved="noticeSaved"
      />

      <EventDialog
        v-model="changeOpen"
        :keep-open="reviewingChange"
        :save-label="changePreview ? 'Apply reviewed change' : 'Preview change'"
        :submit="saveChange"
        :submit-disabled="!change"
        subtitle="Review the exact dates and affected registrations before applying this change."
        :title="
          action === 'CANCEL'
            ? 'Cancel gathering'
            : action === 'RESTORE'
              ? 'Restore gathering'
              : 'Reschedule gathering'
        "
        @reload="
          () => {
            changeOpen = false
            load()
          }
        "
        @saved="load"
      >
        <template v-if="change">
          <v-select
            v-if="action !== 'RESTORE'"
            v-model="change.scope"
            :items="[
              { title: 'This occurrence only', value: 'THIS' },
              {
                title: 'This and future dates in this series',
                value: 'FUTURE',
              },
            ]"
            label="Apply to"
          />

          <template v-if="action === 'RESCHEDULE'">
            <DateSelector
              v-if="change.scope === 'THIS'"
              v-model="change.date"
              label="New date"
              :rules="[required]"
            />

            <div class="event-grid-fields">
              <v-text-field
                v-model="change.startsAt"
                label="Starts at"
                :rules="[required]"
              />

              <v-text-field
                v-model="change.endsAt"
                label="Ends at"
                :rules="[required]"
              />
            </div>

            <v-number-input
              v-model="change.endDayOffset"
              label="Ends days after start"
              :max="7"
              :min="0"
            />

            <VenuePicker v-model="changeVenue" />

            <p class="directory-muted mb-3">
              {{ details.occurrence.timeZone }}. Future changes retain
              recurrence dates and preserve individual overrides.
            </p>
          </template>

          <v-alert
            v-if="action === 'CANCEL'"
            class="mb-4"
            type="warning"
            variant="tonal"
          >
            Cancellation includes individually edited future dates in the
            selected scope and cancels all active bookings. Recorded attendance
            remains in history.
          </v-alert>

          <v-alert
            v-if="action === 'RESTORE'"
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Restores this date only. Registration remains closed and previously
            cancelled bookings are not restored.
          </v-alert>

          <v-textarea
            v-model="reason"
            label="Reason for change"
            maxlength="1000"
            :rules="[required]"
          />

          <div
            v-if="changePreview"
            aria-label="Change preview"
            class="event-preview"
          >
            <strong>
              {{ changePreview.changes.length }} dates ·
              {{ changePreview.confirmedAffected }} confirmed ·
              {{ changePreview.waitlistedAffected }} waitlisted ·
              {{ changePreview.attendedAffected || 0 }} attended
            </strong>

            <p
              v-if="changePreview.skipped.length > 0"
              class="directory-muted mt-2"
            >
              {{ changePreview.skipped.length }} individually edited, cancelled
              or started dates preserved.
            </p>

            <div
              v-for="item in changePreview.changes"
              :key="item.before.id"
              class="event-change"
            >
              <div>
                {{ dateLabel(item.before.startsAt, item.before.timeZone) }} ·
                {{ item.before.venueName }}
              </div>

              <strong>
                →
                {{
                  item.after.status === 'CANCELLED'
                    ? 'Cancelled'
                    : dateLabel(item.after.startsAt, item.after.timeZone) +
                      ' · ' +
                      item.after.venueName
                }}
              </strong>
            </div>
          </div>
        </template>
      </EventDialog>

      <EventDialog
        v-model="policyOpen"
        :submit="savePolicy"
        subtitle="Set the seat limit and let a fair queue handle the next available place. Registration is free."
        title="Registration settings"
        @reload="
          () => {
            policyOpen = false
            load()
          }
        "
        @saved="load"
      >
        <v-switch
          v-model="policy.open"
          color="primary"
          label="Registration open"
        />

        <v-switch
          v-model="unlimited"
          color="primary"
          label="No seat limit"
        />

        <v-number-input
          v-if="!unlimited"
          v-model="policy.capacity"
          label="Places"
          :max="10000"
          :min="1"
        />

        <v-switch
          v-model="policy.waitlistEnabled"
          color="primary"
          label="Enable waitlist when full"
        />

        <v-textarea
          v-model="policyReason"
          label="Reason for change"
          maxlength="1000"
          :rules="[required]"
        />

        <p class="directory-muted">
          Increasing capacity promotes the longest-waiting people first.
        </p>
      </EventDialog>

      <EventDialog
        v-model="registerOpen"
        :submit="saveRegistration"
        subtitle="A full gathering uses its waitlist when enabled. Existing waitlisted people have priority."
        :title="rejoining ? 'Register person again' : 'Register a person'"
        @reload="
          () => {
            registerOpen = false
            load()
          }
        "
        @saved="load"
      >
        <PersonPicker
          v-if="!rejoining"
          v-model="personId"
          label="Find a person"
          :rules="[required]"
        />

        <p
          v-else
          class="directory-muted"
        >
          This creates a new place in queue order for the cancelled booking.
        </p>
      </EventDialog>

      <EventDialog
        v-model="cancelOpen"
        :submit="cancelBooking"
        subtitle="The next eligible waitlisted person receives the available place automatically."
        title="Cancel a booking"
        @reload="
          () => {
            cancelOpen = false
            load()
          }
        "
        @saved="load"
      >
        <v-textarea
          v-model="cancelReason"
          label="Reason for cancellation"
          maxlength="1000"
          :rules="[required]"
        />
      </EventDialog>

      <v-dialog
        v-model="historyOpen"
        max-width="760"
        scrollable
      >
        <v-card class="directory-form">
          <div class="directory-form__header">
            <h2>
              {{
                historyKind === 'occurrences'
                  ? 'Occurrence history'
                  : 'Registration history'
              }}
            </h2>

            <v-btn
              aria-label="Close history"
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

              <p
                v-if="entry.startsAt"
                class="directory-muted"
              >
                {{ dateLabel(entry.startsAt, details.occurrence.timeZone) }} ·
                {{ entry.venueName }}
              </p>

              <p class="directory-muted mt-2">
                {{ dateLabel(entry.occurredAt) }}
              </p>
            </div>

            <p v-if="!historyLoading && history.length === 0">
              No history on this page.
            </p>
          </v-card-text>

          <v-card-actions>
            <v-btn
              :disabled="historyOffset === 0 || historyLoading"
              @click="
                () => {
                  historyOffset -= 25
                  loadHistory()
                }
              "
            >
              Previous
            </v-btn>

            <v-spacer />

            <v-btn
              :disabled="history.length < 25 || historyLoading"
              @click="
                () => {
                  historyOffset += 25
                  loadHistory()
                }
              "
            >
              Next
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </template>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type Change,
    type ChangePreview,
    dateLabel,
    type Details,
    errorMessage,
    eventApi,
    type History,
    type Registration,
    required,
  } from '../api'
  import AttendancePanel from '../components/AttendancePanel.vue'
  import EventDialog from '../components/EventDialog.vue'
  import EventNoticeDialog from '../components/EventNoticeDialog.vue'
  import EventReadinessPanel from '../components/EventReadinessPanel.vue'
  import TicketSalesPanel from '../components/TicketSalesPanel.vue'
  import VenuePicker from '../components/VenuePicker.vue'
  import VisitorCheckInPanel from '../components/VisitorCheckInPanel.vue'
  import '@/directory/directory.scss'
  import '../events.css'
  const readinessRevision = ref(0)
  const noticeOpen = ref(false),
        noticeQueued = ref(false),
        noticeTarget = ref<{ registration: Registration, name: string }>()
  function noticeSaved () {
    noticeQueued.value = true
    readinessRevision.value++
  }
  function startNotice (item: { registration: Registration, name: string }) {
    noticeTarget.value = item
    noticeQueued.value = false
    noticeOpen.value = true
  }
  const id = String(useRoute().params.id),
        auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('events.manage')),
        readRegistrations = computed(() =>
          auth.hasPermission('events.registrations.read'),
        ),
        manageRegistrations = computed(() =>
          auth.hasPermission('events.registrations.manage'),
        ),
        canRegister = computed(
          () => manageRegistrations.value && auth.hasPermission('people.read'),
        )
  const details = ref<Details>(),
        loading = ref(false),
        error = ref(''),
        scheduled = computed(
          () => details.value?.occurrence.status === 'SCHEDULED',
        ),
        upcoming = computed(() => future(details.value?.occurrence.startsAt)),
        notEnded = computed(() => future(details.value?.occurrence.endsAt))
  function future (value?: string) {
    return Boolean(value && Date.parse(value) > Date.now())
  }
  const registrations = ref<{ registration: Registration, name: string }[]>([]),
        registrationStatus = ref<string | null>(null),
        registrationOffset = ref(0),
        registrationTotal = ref(0),
        registrationsLoading = ref(false),
        registrationError = ref('')
  async function loadRegistrations () {
    if (!readRegistrations.value) return
    registrationsLoading.value = true
    try {
      const page = await eventApi.registrations(
        id,
        registrationStatus.value || undefined,
        registrationOffset.value,
      )
      registrations.value = page.registrations
      registrationTotal.value = page.total
      registrationError.value = ''
    } catch (error_) {
      registrationError.value = errorMessage(error_)
    } finally {
      registrationsLoading.value = false
    }
  }
  async function load () {
    loading.value = true
    try {
      details.value = await eventApi.occurrence(id)
      readinessRevision.value++
      error.value = ''
      await loadRegistrations()
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      loading.value = false
    }
  }
  const changeOpen = ref(false),
        action = ref(''),
        change = ref<Change>(),
        changeVenue = ref<string | null>(null),
        changePreview = ref<ChangePreview>(),
        reason = ref(''),
        reviewingChange = ref(false)
  watch(
    [change, changeVenue],
    () => {
      changePreview.value = undefined
    },
    { deep: true },
  )
  function startChange (value: string) {
    if (!details.value) return
    const o = details.value.occurrence
    action.value = value
    change.value = {
      occurrenceId: id,
      expectedVersion: o.version,
      action: value,
      scope: 'THIS',
      date: o.originDate,
      startsAt: details.value.series.startsAt,
      endsAt: details.value.series.endsAt,
      endDayOffset: details.value.series.endDayOffset,
      venueId: o.venueId,
    }
    if (value === 'RESCHEDULE') {
      const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: o.timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
      }).formatToParts(new Date(o.startsAt))
      const part = (key: string) =>
        parts.find(p => p.type === key)?.value || ''
      change.value.date = part('year') + '-' + part('month') + '-' + part('day')
      change.value.startsAt = part('hour') + ':' + part('minute')
      const endParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: o.timeZone,
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
      }).formatToParts(new Date(o.endsAt))
      change.value.endsAt
        = (endParts.find(p => p.type === 'hour')?.value || '00')
          + ':'
          + (endParts.find(p => p.type === 'minute')?.value || '00')
    }
    changeVenue.value = o.venueId
    changePreview.value = undefined
    reason.value = ''
    changeOpen.value = true
  }
  async function saveChange () {
    if (!change.value) return
    const draft = {
      ...change.value,
      venueId: changeVenue.value || change.value.venueId,
    }
    if (!changePreview.value) {
      reviewingChange.value = true
      changePreview.value = await eventApi.previewChange(draft)
      return
    }
    reviewingChange.value = false
    await eventApi.change(action.value, {
      draft,
      reviewHash: changePreview.value.hash,
      reason: reason.value,
    })
  }
  const policyOpen = ref(false),
        policy = ref({ open: false, capacity: 1, waitlistEnabled: false }),
        unlimited = ref(true),
        policyReason = ref('')
  function configure () {
    if (!details.value) return
    const p = details.value.registrationPolicy
    policy.value = {
      open: p.open,
      capacity: p.capacity || 1,
      waitlistEnabled: p.waitlistEnabled,
    }
    unlimited.value = p.capacity === null
    policyReason.value = ''
    policyOpen.value = true
  }
  async function savePolicy () {
    if (!details.value) return
    await eventApi.policy(id, {
      expectedVersion: details.value.registrationPolicy.version,
      ...policy.value,
      capacity: unlimited.value ? null : policy.value.capacity,
      reason: policyReason.value,
    })
  }
  const registerOpen = ref(false),
        personId = ref<string | null>(null),
        rejoining = ref<Registration>()
  let registrationRequest = crypto.randomUUID()
  watch(personId, () => {
    registrationRequest = crypto.randomUUID()
  })
  function startRegister (registration?: Registration) {
    rejoining.value = registration
    personId.value = registration?.personId || null
    registrationRequest = crypto.randomUUID()
    registerOpen.value = true
  }
  async function saveRegistration () {
    await eventApi.register(id, {
      requestId: registrationRequest,
      personId: personId.value,
      expectedVersion: rejoining.value?.version,
    })
  }
  const cancelOpen = ref(false),
        cancelledRegistration = ref<Registration>(),
        cancelReason = ref('')
  function startCancel (registration: Registration) {
    cancelledRegistration.value = registration
    cancelReason.value = ''
    cancelOpen.value = true
  }
  async function cancelBooking () {
    if (!cancelledRegistration.value) return
    await eventApi.cancelRegistration(cancelledRegistration.value.id, {
      expectedVersion: cancelledRegistration.value.version,
      reason: cancelReason.value,
    })
  }
  const historyOpen = ref(false),
        historyKind = ref<'occurrences' | 'registrations'>('occurrences'),
        historyId = ref(''),
        history = ref<History[]>([]),
        historyOffset = ref(0),
        historyLoading = ref(false),
        historyError = ref('')
  function showHistory (kind: 'occurrences' | 'registrations', target: string) {
    historyKind.value = kind
    historyId.value = target
    historyOffset.value = 0
    history.value = []
    historyOpen.value = true
    loadHistory()
  }
  async function loadHistory () {
    historyLoading.value = true
    try {
      history.value = await eventApi.history(
        historyKind.value,
        historyId.value,
        historyOffset.value,
      )
      historyError.value = ''
    } catch (error_) {
      historyError.value = errorMessage(error_)
    } finally {
      historyLoading.value = false
    }
  }
  onMounted(load)
</script>
