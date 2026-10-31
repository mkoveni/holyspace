<template>
  <section
    aria-label="Event readiness and closeout"
    class="directory-surface event-section"
  >
    <div class="d-flex align-center flex-wrap ga-3 mb-4">
      <div class="mr-auto">
        <span class="directory-eyebrow">FROM PLANNING TO REFLECTION</span>
        <h2 class="mb-1">Readiness &amp; closeout</h2>

        <p class="directory-muted">
          Resolve the details. Bring every gathering to a thoughtful close.
        </p>
      </div>

      <v-btn
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="load"
      >
        Refresh readiness
      </v-btn>
    </div>

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <template v-if="data">
      <v-alert
        v-if="data.review.occurrence.status === 'CANCELLED'"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        This gathering was cancelled. Review preserved attendance and
        outstanding notices before closing out.
      </v-alert>

      <div class="event-readiness-grid">
        <article
          v-for="check in data.checks"
          :key="check.key"
          class="event-readiness-check"
        >
          <div class="d-flex ga-2 align-center mb-2">
            <v-icon
              :color="check.status === 'READY' ? 'success' : 'warning'"
              :icon="
                check.status === 'READY'
                  ? 'mdi-check-circle-outline'
                  : 'mdi-alert-circle-outline'
              "
            />

            <h3>{{ check.title }}</h3>
          </div>

          <p class="directory-muted">{{ check.detail }}</p>

          <v-btn
            v-if="actionFor(check.key)"
            append-icon="mdi-arrow-right"
            class="mt-3"
            :href="actionFor(check.key)?.href"
            size="small"
            :to="actionFor(check.key)?.to"
            variant="text"
          >
            {{ actionFor(check.key)?.label }}
          </v-btn>
        </article>
      </div>

      <p class="directory-muted mt-4">
        {{ data.review.notices.submitted }} submitted to email provider ·
        {{ data.review.notices.delivered }} delivery confirmed ·
        {{ data.review.notices.cancelled }} notices cancelled
      </p>

      <p class="directory-muted mt-3 mb-5">
        Live assessment · {{ dateLabel(data.assessedAt, timeZone) }} ·
        {{ timeZone }}. Missing plans need coordinator review.
      </p>

      <div class="event-closeout-summary">
        <div class="d-flex flex-wrap align-center ga-3 mb-3">
          <h3 class="mr-auto">
            {{
              data.closeout.status === 'CLOSED'
                ? 'Gathering closed out'
                : 'Closeout review'
            }}
          </h3>

          <v-chip
            :color="data.closeout.status === 'CLOSED' ? 'success' : 'default'"
            size="small"
          >
            {{
              data.closeout.status === 'CLOSED' ? 'Closed' : 'Awaiting review'
            }}
          </v-chip>
        </div>

        <p class="directory-muted mb-3">
          {{ data.review.attendance.attended }} attended ·
          {{ data.review.attendance.absent }} absent ·
          {{ data.review.attendance.waitlisted }} waitlisted
        </p>

        <v-alert
          v-if="data.changedSinceCloseout"
          class="mb-4"
          type="warning"
          variant="tonal"
        >
          Attendance, bookings or notice outcomes changed after closeout. Reopen
          and review the updated record.
        </v-alert>

        <template v-if="data.closeout.status === 'CLOSED'">
          <p class="event-closeout-note">{{ data.closeout.note }}</p>

          <p
            v-if="data.closeout.followUp"
            class="event-closeout-note mt-2"
          >
            <strong>Follow-up:</strong>
            {{ data.closeout.followUp }}
          </p>

          <p
            v-if="data.closeout.updatedAt"
            class="directory-muted mt-2"
          >
            Closed {{ dateLabel(data.closeout.updatedAt, timeZone) }}. Original
            reviewed totals are retained in history.
          </p>
        </template>

        <p
          v-else-if="!data.review.canClose"
          class="directory-muted"
        >
          Closeout becomes available when the gathering ends or is cancelled.
          The notice summary must be complete.
        </p>

        <div class="d-flex flex-wrap ga-2 mt-4">
          <v-btn
            v-if="data.closeout.status !== 'CLOSED'"
            color="primary"
            :disabled="!data.review.canClose || loading"
            @click="startClose"
          >
            Review and close out
          </v-btn>

          <v-btn
            v-else
            variant="tonal"
            @click="startReopen"
          >
            Reopen for review
          </v-btn>

          <v-btn
            prepend-icon="mdi-history"
            variant="text"
            @click="openHistory"
          >
            Closeout history
          </v-btn>
        </div>
      </div>
    </template>

    <EventDialog
      v-model="closeOpen"
      save-label="Close gathering"
      :submit="saveClose"
      :submit-disabled="!attendanceReviewed || !followUpReviewed"
      subtitle="Review attendance and outstanding work. This records a dated review and preserves every booking and arrival."
      title="Close out this gathering"
      @reload="reloadClose"
      @saved="load"
    >
      <template v-if="snapshot">
        <v-alert
          class="mb-4"
          type="info"
          variant="tonal"
        >
          {{ snapshot.review.attendance.attended }} attended ·
          {{ snapshot.review.attendance.absent }} absent ·
          {{ snapshot.review.attendance.waitlisted }} waitlisted ·
          {{ unresolved(snapshot) }} notices requiring follow-up
        </v-alert>

        <v-checkbox
          v-model="attendanceReviewed"
          label="I reviewed and reconciled attendance"
        />

        <v-checkbox
          v-model="followUpReviewed"
          label="I reviewed waitlists and email outcomes"
        />

        <v-textarea
          v-model="note"
          label="Closeout note"
          maxlength="2000"
          :rules="[required]"
        />

        <v-textarea
          v-model="followUp"
          hint="Record the action, who will handle it and when. This note does not send messages or clear waitlists."
          :label="
            needsFollowUp ? 'Follow-up actions (required)' : 'Follow-up actions'
          "
          maxlength="2000"
          persistent-hint
          :rules="needsFollowUp ? [required] : []"
        />
      </template>
    </EventDialog>

    <EventDialog
      v-model="reopenOpen"
      save-label="Reopen review"
      :submit="saveReopen"
      subtitle="Keep the previous review and record why another review is needed."
      title="Reopen this closeout"
      @reload="reloadReopen"
      @saved="load"
    >
      <v-textarea
        v-model="reason"
        label="Reason for reopening"
        maxlength="2000"
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
          <h2>Closeout history</h2>

          <v-btn
            aria-label="Close closeout history"
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

          <article
            v-for="entry in history"
            :key="entry.id"
            class="event-change"
          >
            <strong>
              {{ entry.action === 'CLOSED' ? 'Closed' : 'Reopened' }} · Review
              {{ entry.version }}
            </strong>

            <p class="event-closeout-note">{{ entry.note }}</p>

            <p
              v-if="entry.followUp"
              class="event-closeout-note"
            >
              Follow-up: {{ entry.followUp }}
            </p>

            <p class="directory-muted mt-2">
              {{ entry.attended }} attended · {{ entry.absent }} absent ·
              {{ entry.waitlisted }} waitlisted ·
              {{ entry.unresolvedNotices }} notices unresolved
            </p>

            <p class="directory-muted">
              {{ dateLabel(entry.occurredAt, timeZone) }} · Coordinator
              {{ entry.actorName }}
            </p>
          </article>

          <p v-if="!historyLoading && history.length === 0">
            No closeout reviews recorded yet.
          </p>
        </v-card-text>

        <v-card-actions>
          <v-btn
            :disabled="historyOffset === 0 || historyLoading"
            @click="pageHistory(-25)"
          >
            Previous
          </v-btn>

          <v-spacer />

          <v-btn
            :disabled="history.length < 25 || historyLoading"
            @click="pageHistory(25)"
          >
            Next
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </section>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type CloseoutHistory,
    dateLabel,
    errorMessage,
    eventApi,
    type EventReadiness,
    required,
  } from '../api'
  import EventDialog from './EventDialog.vue'
  const props = defineProps<{
    occurrenceId: string
    timeZone: string
    revision: number
  }>()
  const auth = useAuthStore()
  const data = ref<EventReadiness>(),
        loading = ref(false),
        error = ref('')
  let generation = 0
  async function load () {
    const request = ++generation
    loading.value = true
    try {
      const result = await eventApi.readiness(props.occurrenceId)
      if (request === generation) {
        data.value = result
        error.value = ''
      }
    } catch (error_) {
      if (request === generation) error.value = errorMessage(error_)
    } finally {
      if (request === generation) loading.value = false
    }
  }
  function actionFor (key: string) {
    if (key === 'venue' && auth.hasPermission('events.manage'))
      return { to: '/events/venues', label: 'Review venues' }
    if (
      key === 'registration'
      && auth.hasPermission('events.registrations.read')
    )
      return { href: '#event-bookings', label: 'Review bookings' }
    if (key === 'staffing' && auth.hasPermission('volunteering.read'))
      return { to: '/volunteering/schedule', label: 'Plan the team' }
    if (
      key === 'reminders'
      && auth.hasPermission('events.registrations.read')
      && auth.hasPermission('events.communications.send')
    )
      return { href: '#event-bookings', label: 'Prepare participant notices' }
    if (
      key === 'deliveries'
      && auth.hasPermission('events.communications.send')
    )
      return { to: '/events/email-deliveries', label: 'Review email outcomes' }
    return undefined
  }
  const closeOpen = ref(false),
        reopenOpen = ref(false),
        note = ref(''),
        followUp = ref(''),
        reason = ref(''),
        attendanceReviewed = ref(false),
        followUpReviewed = ref(false),
        snapshot = ref<EventReadiness>()
  function unresolved (value: EventReadiness) {
    const n = value.review.notices
    return n.pending + n.failed + n.captured
  }
  const needsFollowUp = computed(() => {
    const value = snapshot.value
    if (!value) return false
    return value.review.attendance.waitlisted > 0 || unresolved(value) > 0
  })
  async function startClose () {
    await load()
    if (error.value || !data.value?.review.canClose) return
    snapshot.value = data.value
    note.value = ''
    followUp.value = ''
    attendanceReviewed.value = false
    followUpReviewed.value = false
    closeOpen.value = true
  }
  function startReopen () {
    snapshot.value = data.value
    reason.value = ''
    reopenOpen.value = true
  }
  function reloadClose () {
    closeOpen.value = false
    load()
  }
  function reloadReopen () {
    reopenOpen.value = false
    load()
  }
  function pageHistory (delta: number) {
    historyOffset.value += delta
    loadHistory()
  }
  async function saveClose () {
    if (!snapshot.value) return
    await eventApi.closeout(props.occurrenceId, {
      expectedVersion: snapshot.value.closeout.version,
      reviewHash: snapshot.value.review.hash,
      note: note.value,
      followUp: followUp.value,
      attendanceReviewed: attendanceReviewed.value,
      followUpReviewed: followUpReviewed.value,
    })
  }
  async function saveReopen () {
    if (!snapshot.value) return
    await eventApi.reopenCloseout(props.occurrenceId, {
      expectedVersion: snapshot.value.closeout.version,
      reason: reason.value,
    })
  }
  const historyOpen = ref(false),
        history = ref<CloseoutHistory[]>([]),
        historyOffset = ref(0),
        historyLoading = ref(false),
        historyError = ref('')
  async function openHistory () {
    historyOffset.value = 0
    history.value = []
    historyOpen.value = true
    await loadHistory()
  }
  async function loadHistory () {
    historyLoading.value = true
    try {
      history.value = await eventApi.closeoutHistory(
        props.occurrenceId,
        historyOffset.value,
      )
      historyError.value = ''
    } catch (error_) {
      historyError.value = errorMessage(error_)
    } finally {
      historyLoading.value = false
    }
  }
  watch(() => props.revision, load)
  onMounted(load)
</script>
<style scoped>
  .event-readiness-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
    gap: 16px;
  }
  .event-readiness-check {
    padding: 20px;
    border: 1px solid rgba(var(--v-theme-on-surface), 0.09);
    border-radius: 16px;
  }
  .event-readiness-check h3 {
    font-size: 1rem;
  }
  .event-closeout-summary {
    padding: 24px;
    border-radius: 16px;
    background: rgba(var(--v-theme-primary), 0.035);
  }
  .event-closeout-note {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
  }
  @media (max-width: 600px) {
    .event-closeout-summary {
      padding: 16px;
    }
  }
</style>
