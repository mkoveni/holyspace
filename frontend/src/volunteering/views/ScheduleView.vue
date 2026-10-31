<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="PREPARED PEOPLE. MEANINGFUL SERVICE."
      subtitle="Plan a duty once, bring your team together and keep readiness visible."
      title="A thoughtful rhythm of serving."
    >
      <v-btn
        v-if="manage"
        color="primary"
        prepend-icon="mdi-calendar-plus"
        @click="startCreate"
      >
        Plan shifts
      </v-btn>
    </DirectoryHeading>

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
      <DateSelector
        v-model="filterFrom"
        label="From"
      />

      <DateSelector
        v-model="filterUntil"
        label="Until (exclusive)"
      />

      <v-select
        v-model="filterTeam"
        clearable
        hide-details
        item-title="name"
        item-value="id"
        :items="teams"
        label="Team"
      />

      <v-btn
        :loading="loading"
        variant="tonal"
        @click="apply"
      >
        Show shifts
      </v-btn>
    </section>

    <div
      v-if="manage"
      class="d-flex justify-end mb-4"
    >
      <v-btn
        :loading="releasing"
        variant="text"
        @click="release"
      >
        Release expired invitation holds
      </v-btn>
    </div>

    <div class="serving-cards">
      <article
        v-for="entry in shifts"
        :key="entry.shift.id"
        class="directory-surface serving-card"
      >
        <div class="serving-card__heading">
          <span class="serving-icon"><v-icon icon="mdi-calendar-heart" /></span>

          <v-chip
            :color="entry.shift.status === 'OPEN' ? 'success' : undefined"
            size="small"
          >
            {{ entry.shift.status }}
          </v-chip>
        </div>

        <h2>{{ entry.team }}</h2>
        <strong>{{ entry.role }}</strong>

        <p class="directory-muted">
          {{ timeLabel(entry.shift.startsAt) }}
          <br>
          Until {{ timeLabel(entry.shift.endsAt) }}
        </p>

        <div class="serving-meta">
          <span>
            {{ entry.reserved }}/{{ entry.shift.positions }} positions reserved
          </span>

          <span>{{ entry.confirmed }} confirmed</span>
          <span>{{ entry.ready }} ready</span>
        </div>

        <v-chip
          v-if="entry.shift.sourceKind === 'CHILDREN_SESSION'"
          size="small"
        >
          Linked children session
        </v-chip>

        <v-btn v-if="entry.shift.sourceKind === 'EVENT_OCCURRENCE' && auth.hasPermission('events.read')" size="small" :to="'/events/occurrences/'+entry.shift.sourceId" variant="tonal">Linked event occurrence</v-btn>

        <v-btn
          append-icon="mdi-arrow-top-right"
          variant="text"
          @click="open(entry.shift.id)"
        >
          Open shift
        </v-btn>
      </article>

      <article
        v-if="!loading && shifts.length === 0"
        class="directory-surface serving-card"
      >
        <h2>A clear calendar</h2>

        <p class="directory-muted">
          Choose your dates or plan your first shift.
        </p>
      </article>
    </div>

    <div class="serving-pagination">
      <span class="directory-muted mr-auto">{{ total }} shifts</span>

      <v-btn
        :disabled="!offset || loading"
        variant="text"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= total || loading"
        variant="text"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>

    <ServingDialog
      v-model="createOpen"
      save-label="Create reviewed shifts"
      :submit="createShifts"
      :submit-disabled="preview.length === 0"
      subtitle="Choose the team and role once. Review every duty interval before creating your shifts."
      title="Make room for a rhythm"
      @saved="load"
    >
      <v-select
        v-model="teamId"
        item-title="name"
        item-value="id"
        :items="teams.filter((team) => team.active)"
        label="Team *"
        :rules="[required]"
      />

      <v-select
        v-model="roleId"
        item-title="name"
        item-value="id"
        :items="roles"
        label="Role *"
        :rules="[required]"
      />

      <v-select
        v-model="sourceKind"
        :items="[
          { title: 'Standalone / recurring dates', value: 'STANDALONE' },
          { title: 'Children Ministry session', value: 'CHILDREN_SESSION' },
          { title: 'Event occurrences', value: 'EVENT_OCCURRENCE' },
        ]"
        label="Activity"
      />

      <template v-if="sourceKind !== 'STANDALONE'">
        <p class="directory-muted mb-3">
          Choose dates in the selected calendar range. Select up to 25 event
          occurrences to plan the same team and role together.
        </p>

        <v-select
          v-if="sourceKind === 'CHILDREN_SESSION'"
          v-model="sourceId"
          item-title="label"
          item-value="id"
          :items="activities"
          label="Session *"
          :rules="[required]"
        />

        <template v-if="sourceKind === 'EVENT_OCCURRENCE'">
          <v-select
            v-model="selectedActivities"
            chips
            closable-chips
            item-title="label"
            item-value="id"
            :items="activities"
            label="Event occurrences"
            multiple
            :rules="[
              (value) =>
                (value.length > 0 && value.length <= 25) ||
                'Choose 1–25 occurrences.',
            ]"
          />

          <div class="serving-grid">
            <v-number-input
              v-model="beforeMinutes"
              label="Arrive minutes before"
              :max="1440"
              :min="0"
            />

            <v-number-input
              v-model="afterMinutes"
              label="Finish minutes after"
              :max="1440"
              :min="0"
            />
          </div>
        </template>

        <v-btn
          v-if="activitiesMore"
          class="mb-4"
          :loading="activitiesLoading"
          variant="text"
          @click="loadActivities(true)"
        >
          Load more activities
        </v-btn>

        <DateTimeSelector
          v-if="sourceKind === 'CHILDREN_SESSION'"
          v-model="dutyFrom"
          label="Duty starts *"
        />

        <DateTimeSelector
          v-if="sourceKind === 'CHILDREN_SESSION'"
          v-model="dutyUntil"
          label="Duty ends *"
        />
      </template>

      <template v-else>
        <div class="serving-grid">
          <DateSelector
            v-model="firstDate"
            label="First date *"
          />

          <v-text-field
            v-model.number="count"
            label="Number of shifts (1–25)"
            max="25"
            min="1"
            type="number"
          />

          <v-text-field
            v-model.number="intervalDays"
            label="Repeat every (days)"
            max="90"
            min="1"
            type="number"
          />

          <v-text-field
            v-model="zone"
            label="Time zone *"
            :rules="[required]"
          />

          <v-text-field
            v-model="startTime"
            label="Duty starts *"
            :rules="[required]"
            type="time"
          />

          <v-text-field
            v-model="endTime"
            label="Duty ends *"
            :rules="[required]"
            type="time"
          />
        </div>

        <p class="directory-muted mb-4">
          An end time before the start time finishes on the next day. Dates use
          the selected time zone.
        </p>
      </template>

      <v-text-field
        v-model.number="positions"
        label="Positions per shift *"
        max="100"
        min="1"
        :rules="[required]"
        type="number"
      />

      <v-btn
        :loading="previewing"
        variant="tonal"
        @click="review"
      >
        Review dates
      </v-btn>

      <v-alert
        v-if="previewError"
        class="mt-4"
        type="error"
        variant="tonal"
      >
        {{ previewError }}
      </v-alert>

      <section
        v-if="preview.length > 0"
        class="serving-form-section mt-4"
      >
        <h3>{{ preview.length }} shifts ready to create</h3>

        <p
          v-for="draft in preview"
          :key="draft.requestId"
          class="directory-muted mb-2"
        >
          {{ timeLabel(draft.startsAt) }} – {{ timeLabel(draft.endsAt) }}
        </p>
      </section>
    </ServingDialog>

    <v-dialog
      v-model="detailOpen"
      max-width="1040"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">SHIFT WORKSPACE</span>
            <h2>{{ detail?.team }} · {{ detail?.role }}</h2>

            <p v-if="detail">
              {{ timeLabel(detail.shift.startsAt) }} –
              {{ timeLabel(detail.shift.endsAt) }}
            </p>
          </div>

          <v-btn
            aria-label="Close shift"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text v-if="detail">
          <div class="d-flex flex-wrap ga-2 mb-5">
            <v-chip>
              {{ detail.reserved }}/{{ detail.shift.positions }} reserved
            </v-chip>

            <v-chip color="success">{{ detail.ready }} ready</v-chip>

            <v-btn
              v-if="manage && detail.shift.status === 'OPEN'"
              variant="tonal"
              @click="assignOpen = true"
            >
              Add volunteer
            </v-btn>

            <v-btn
              v-if="manage"
              variant="tonal"
              @click="editShift"
            >
              Edit shift
            </v-btn>
          </div>

          <v-alert
            class="mb-4"
            type="info"
            variant="tonal"
          >
            Confirmation records a commitment. Only volunteers meeting every
            requirement are shown as ready. Children Ministry also verifies its
            own worker clearance and presence.
          </v-alert>

          <article
            v-for="booking in detail.assignments"
            :key="booking.assignment.id"
            class="serving-form-section"
          >
            <div class="serving-card__heading">
              <strong>{{ booking.name }}</strong>
              <v-chip size="small">{{ booking.assignment.status }}</v-chip>
            </div>

            <v-chip
              class="my-3"
              :color="booking.readiness.ready ? 'success' : 'warning'"
              size="small"
            >
              {{ booking.readiness.ready ? 'Ready' : 'Preparation needed' }}
            </v-chip>

            <ul class="serving-issues">
              <li
                v-for="issue in booking.readiness.issues"
                :key="issue"
              >
                {{ issue }}
              </li>
            </ul>

            <p
              v-for="invitation in booking.invitations"
              :key="invitation.invitation.id"
              class="directory-muted mt-2"
            >
              Invitation {{ invitation.effectiveStatus }} · expires
              {{ timeLabel(invitation.invitation.expiresAt) }}
            </p>

            <div class="d-flex flex-wrap ga-2 mt-4">
              <template
                v-if="
                  manage &&
                    booking.assignment.status !== 'CANCELLED' &&
                    detail.shift.status === 'OPEN'
                "
              >
                <v-btn
                  size="small"
                  variant="tonal"
                  @click="startInvite(booking)"
                >
                  Invite
                </v-btn>

                <v-btn
                  size="small"
                  variant="text"
                  @click="startStatus(booking)"
                >
                  Change commitment
                </v-btn>
              </template>

              <v-btn
                v-if="
                  auth.hasPermission('volunteering.communications.send') &&
                    booking.assignment.status === 'CONFIRMED'
                "
                size="small"
                variant="text"
                @click="startReminder(booking)"
              >
                Send reminder
              </v-btn>

              <v-btn
                size="small"
                variant="text"
                @click="showHistory(booking)"
              >
                History
              </v-btn>
            </div>
          </article>

          <p
            v-if="detail.assignments.length === 0"
            class="directory-muted"
          >
            No volunteers assigned. Add someone to reserve a position.
          </p>
        </v-card-text>

        <v-card-actions>
          <v-btn @click="refresh">Refresh shift</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ServingDialog
      v-model="assignOpen"
      :submit="assign"
      subtitle="Reserve a position now. Readiness can be completed before the day."
      title="A place on the team"
      @reload="refresh"
      @saved="refresh"
    >
      <VolunteerPicker v-model="volunteer" />

      <v-select
        v-model="commitment"
        :items="[
          { title: 'Proposed · awaiting commitment', value: 'PROPOSED' },
          { title: 'Confirmed · agreed with volunteer', value: 'CONFIRMED' },
        ]"
        label="Commitment"
      />

      <v-btn
        :disabled="!volunteer"
        variant="text"
        @click="checkReadiness"
      >
        Check readiness
      </v-btn>

      <v-alert
        v-if="assessment"
        class="mt-3"
        :type="assessment.ready ? 'success' : 'warning'"
        variant="tonal"
      >
        <p v-if="assessment.ready">Ready for this duty.</p>

        <ul
          v-else
          class="pl-4"
        >
          <li
            v-for="issue in assessment.issues"
            :key="issue"
          >
            {{ issue }}
          </li>
        </ul>
      </v-alert>
    </ServingDialog>

    <ServingDialog
      v-model="inviteOpen"
      :submit="invite"
      subtitle="A new invitation replaces the previous pending one. The volunteer needs a linked account and serving access."
      title="Invite a response"
      @reload="refresh"
      @saved="refresh"
    >
      <p class="mb-4">{{ selected?.name }}</p>

      <DateTimeSelector
        v-model="expires"
        label="Respond before *"
      />
    </ServingDialog>

    <ServingDialog
      v-model="statusOpen"
      :submit="changeStatus"
      subtitle="Keep an explanation alongside this change."
      title="Update the commitment"
      @reload="refresh"
      @saved="refresh"
    >
      <v-select
        v-model="commitment"
        :items="['PROPOSED', 'CONFIRMED', 'CANCELLED']"
        label="Commitment"
      />

      <v-textarea
        v-model="reason"
        label="Reason *"
        maxlength="500"
        :rules="[required]"
      />
    </ServingDialog>

    <ServingDialog
      v-model="editOpen"
      :submit="saveShift"
      subtitle="Changing duty times returns confirmed commitments to proposed and withdraws pending invitations. Confirm revised details with your team."
      title="Adjust the duty"
      @reload="refresh"
      @saved="refresh"
    >
      <DateTimeSelector
        v-model="dutyFrom"
        label="Starts *"
      />

      <DateTimeSelector
        v-model="dutyUntil"
        label="Ends *"
      />

      <v-text-field
        v-model.number="positions"
        label="Positions"
        max="100"
        min="1"
        type="number"
      />

      <v-select
        v-model="shiftStatus"
        :items="['OPEN', 'CANCELLED']"
        label="Shift status"
      />
    </ServingDialog>

    <ServingDialog
      v-model="reminderOpen"
      save-label="Approve & queue reminder"
      :submit="sendReminder"
      :submit-disabled="!reminderReview || !recipientConfirmed"
      subtitle="Choose a current People email, review the published template and confirm recipient permission before sending."
      title="A thoughtful reminder"
      @reload="reviewReminder"
      @saved="refresh"
    >
      <v-alert
        v-if="reminderError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ reminderError }}
      </v-alert>

      <v-select
        v-model="reminderContact"
        item-title="value"
        item-value="id"
        :items="reminderContacts"
        label="Recipient email *"
        :rules="[required]"
      />

      <v-textarea
        v-model="reminderMessage"
        label="Personal message *"
        maxlength="2000"
        :rules="[required]"
      />

      <v-btn
        :loading="reminderBusy"
        variant="tonal"
        @click="reviewReminder"
      >
        Review reminder
      </v-btn>

      <section
        v-if="reminderReview"
        class="serving-form-section mt-4"
      >
        <h3>{{ reminderReview.subject }}</h3>
        <p class="directory-muted">To {{ reminderReview.recipient }}</p>

        <iframe
          sandbox=""
          :srcdoc="reminderReview.html"
          style="width: 100%; height: 240px; border: 0; margin-top: 16px"
          title="Reviewed volunteer reminder"
        />
      </section>

      <v-textarea
        v-model="recipientEvidence"
        class="mt-5"
        hint="Record how you confirmed this address belongs to the volunteer and that this reminder may be sent."
        label="Recipient verification and communication permission *"
        maxlength="500"
        persistent-hint
        :rules="[required]"
      />

      <v-checkbox
        v-model="recipientConfirmed"
        color="primary"
        label="I verified the recipient and permission to send this reminder."
      />
    </ServingDialog>

    <v-dialog
      v-model="historyOpen"
      max-width="700"
    >
      <v-card class="directory-form pa-6">
        <h2 class="mb-4">Assignment history</h2>

        <div
          v-for="event in history"
          :key="event.id"
          class="serving-row"
        >
          <div>
            <strong>{{ event.action }}</strong>
            <p>{{ event.reason }}</p>

            <small class="directory-muted">
              {{ timeLabel(event.occurredAt) }}
            </small>
          </div>
        </div>

        <v-btn
          class="mt-4"
          @click="historyOpen = false"
        >
          Close
        </v-btn>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import { instant, localInput } from '@/children/presentation'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { useApi } from '@/composables/useApi'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import {
    type Booking,
    type Readiness,
    required,
    type Role,
    servingApi,
    servingError,
    type ShiftView,
    type Team,
    timeLabel,
  } from '../api'
  import ServingDialog from '../components/ServingDialog.vue'
  import ServingNavigation from '../components/ServingNavigation.vue'
  import VolunteerPicker from '../components/VolunteerPicker.vue'
  import '@/directory/directory.scss'
  import '../serving.scss'
  interface ReminderReview {
    recipient: string
    subject: string
    html: string
    hash: string
  }
  const reminderOpen = ref(false),
        reminderContact = ref(''),
        reminderMessage = ref(
          'We look forward to serving with you. Please contact your coordinator if your availability changes.',
        ),
        recipientEvidence = ref(''),
        recipientConfirmed = ref(false),
        reminderBusy = ref(false),
        reminderError = ref(''),
        reminderReview = ref<ReminderReview | null>(null),
        reminderContacts = ref<{ id: string, value: string }[]>([]),
        reminderRequest = ref(crypto.randomUUID())
  watch([reminderContact, reminderMessage], () => {
    reminderReview.value = null
    recipientConfirmed.value = false
  })
  async function startReminder (booking: Booking) {
    selected.value = booking
    reminderOpen.value = true
    reminderContact.value = ''
    reminderReview.value = null
    recipientEvidence.value = ''
    recipientConfirmed.value = false
    reminderRequest.value = crypto.randomUUID()
    reminderError.value = ''
    try {
      reminderContacts.value = (
        await useApi().get<{ id: string, value: string }[]>(
          `/api/volunteering/assignments/${booking.assignment.id}/reminder-contacts`,
        )
      ).data
    } catch (error_) {
      reminderError.value = servingError(error_)
    }
  }
  async function reviewReminder () {
    if (!selected.value) return
    reminderBusy.value = true
    reminderError.value = ''
    try {
      reminderReview.value = (
        await useApi().post<ReminderReview>(
          `/api/volunteering/assignments/${selected.value.assignment.id}/reminder-review`,
          { contactId: reminderContact.value, message: reminderMessage.value },
        )
      ).data
    } catch (error_) {
      reminderError.value = servingError(error_)
    } finally {
      reminderBusy.value = false
    }
  }
  async function sendReminder () {
    if (!selected.value || !reminderReview.value || !recipientConfirmed.value)
      throw new Error('Review and verify the reminder first.')
    await useApi().post(
      `/api/volunteering/assignments/${selected.value.assignment.id}/reminders`,
      {
        requestId: reminderRequest.value,
        contactId: reminderContact.value,
        message: reminderMessage.value,
        reviewHash: reminderReview.value.hash,
        verificationEvidence: recipientEvidence.value,
      },
    )
  }
  interface Draft {
    requestId: string
    teamId: string
    roleId: string
    sourceKind: string
    sourceId: string | null
    startsAt: string
    endsAt: string
    positions: number
  }
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('volunteering.manage'))
  const today = new Date(),
        future = new Date(today.getTime() + 30 * 86_400_000)
  const filterFrom = ref(today.toISOString().slice(0, 10)),
        filterUntil = ref(future.toISOString().slice(0, 10)),
        filterTeam = ref<string | null>(null),
        applied = ref({
          from: filterFrom.value,
          until: filterUntil.value,
          team: '',
        })
  const shifts = ref<ShiftView[]>([]),
        teams = ref<Team[]>([]),
        roles = ref<Role[]>([]),
        total = ref(0),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        detail = ref<ShiftView | null>(null),
        releasing = ref(false)
  const createOpen = ref(false),
        detailOpen = ref(false),
        assignOpen = ref(false),
        inviteOpen = ref(false),
        statusOpen = ref(false),
        editOpen = ref(false),
        historyOpen = ref(false)
  const teamId = ref(''),
        roleId = ref(''),
        sourceKind = ref('STANDALONE'),
        sourceId = ref<string | null>(null),
        activities = ref<
          { id: string, label: string, startsAt: string, endsAt: string }[]
        >([]),
        firstDate = ref(filterFrom.value),
        count = ref(1),
        intervalDays = ref(7),
        zone = ref('Africa/Johannesburg'),
        startTime = ref('08:00'),
        endTime = ref('11:00'),
        positions = ref(1),
        dutyFrom = ref(''),
        dutyUntil = ref(''),
        shiftStatus = ref('OPEN'),
        preview = ref<Draft[]>([]),
        previewError = ref(''),
        previewing = ref(false),
        requestId = ref(crypto.randomUUID()),
        assignmentRequest = ref(crypto.randomUUID())
  const selectedActivities = ref<string[]>([]),
        beforeMinutes = ref(0),
        afterMinutes = ref(0),
        activitiesMore = ref(false),
        activitiesLoading = ref(false)
  let activitiesOffset = 0,
      activityGeneration = 0
  const activityRequests = new Map<string, string>()
  const volunteer = ref(''),
        commitment = ref('PROPOSED'),
        assessment = ref<Readiness | null>(null),
        selected = ref<Booking | null>(null),
        expires = ref(''),
        reason = ref(''),
        history = ref<
          { id: string, action: string, reason: string, occurredAt: string }[]
        >([])
  function bounds () {
    return {
      from: instant(applied.value.from + 'T00:00'),
      until: instant(applied.value.until + 'T00:00'),
    }
  }
  async function load () {
    loading.value = true
    error.value = ''
    try {
      const range = bounds()
      const result = await servingApi.shifts(
        range.from,
        range.until,
        applied.value.team,
        offset.value,
      )
      shifts.value = result.shifts
      total.value = result.total
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      loading.value = false
    }
  }
  function apply () {
    applied.value = {
      from: filterFrom.value,
      until: filterUntil.value,
      team: filterTeam.value || '',
    }
    offset.value = 0
    void load()
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  async function open (id: string) {
    try {
      detail.value = await servingApi.shift(id)
      detailOpen.value = true
    } catch (error_) {
      error.value = servingError(error_)
    }
  }
  async function refresh () {
    if (detail.value) await open(detail.value.shift.id)
    await load()
  }
  async function loadTeams () {
    const all: Team[] = []
    let page = 0
    while (page < 1000) {
      const result = await servingApi.teams(page)
      all.push(...result.teams)
      if (all.length >= result.total) break
      page += 25
    }
    teams.value = all
  }
  function startCreate () {
    preview.value = []
    selectedActivities.value = []
    activityRequests.clear()
    requestId.value = crypto.randomUUID()
    createOpen.value = true
    void loadActivities()
  }
  async function loadActivities (more = false) {
    const current = ++activityGeneration
    activitiesLoading.value = true
    if (!more) {
      activitiesOffset = 0
      activities.value = []
    }
    try {
      const range = bounds()
      const page = await servingApi.activities(
        range.from,
        range.until,
        sourceKind.value === 'STANDALONE'
          ? 'CHILDREN_SESSION'
          : sourceKind.value,
        activitiesOffset,
      )
      if (current !== activityGeneration) return
      activities.value = [
        ...activities.value,
        ...page.map(activity => ({
          ...activity,
          label: `${activity.label} · ${timeLabel(activity.startsAt)}${activity.venueLabel ? ' · ' + activity.venueLabel : ''}`,
        })),
      ]
      activitiesMore.value = page.length === 100
      activitiesOffset += 100
    } catch (error_) {
      previewError.value = servingError(error_)
    } finally {
      if (current === activityGeneration) activitiesLoading.value = false
    }
  }
  watch(sourceKind, () => {
    sourceId.value = null
    selectedActivities.value = []
    void loadActivities()
  })
  watch(teamId, async id => {
    roleId.value = ''
    roles.value = []
    if (id) {
      try {
        roles.value = (await servingApi.team(id)).roles.filter(
          role => role.active,
        )
      } catch (error_) {
        previewError.value = servingError(error_)
      }
    }
  })
  watch(sourceId, id => {
    const activity = activities.value.find(item => item.id === id)
    if (activity) {
      dutyFrom.value = localInput(new Date(activity.startsAt))
      dutyUntil.value = localInput(new Date(activity.endsAt))
    }
  })
  watch(
    [
      teamId,
      roleId,
      sourceKind,
      sourceId,
      selectedActivities,
      beforeMinutes,
      afterMinutes,
      firstDate,
      count,
      intervalDays,
      zone,
      startTime,
      endTime,
      positions,
      dutyFrom,
      dutyUntil,
    ],
    () => {
      preview.value = []
      activityRequests.clear()
      previewError.value = ''
    },
  )
  watch(assignOpen, open => {
    if (open) {
      volunteer.value = ''
      commitment.value = 'PROPOSED'
      assignmentRequest.value = crypto.randomUUID()
    }
  })
  watch(volunteer, () => {
    assessment.value = null
    assignmentRequest.value = crypto.randomUUID()
  })
  async function review () {
    previewing.value = true
    previewError.value = ''
    try {
      if (!teamId.value || !roleId.value)
        throw new Error('Choose a team and role first.')
      if (sourceKind.value === 'EVENT_OCCURRENCE') {
        if (
          selectedActivities.value.length === 0
          || selectedActivities.value.length > 25
        )
          throw new Error('Choose 1–25 occurrences.')
        if (
          !Number.isInteger(beforeMinutes.value)
          || !Number.isInteger(afterMinutes.value)
          || beforeMinutes.value < 0
          || afterMinutes.value < 0
          || beforeMinutes.value > 1440
          || afterMinutes.value > 1440
        )
          throw new Error(
            'Choose arrival and finish buffers from 0 to 1440 minutes.',
          )
        preview.value = selectedActivities.value.map(id => {
          const activity = activities.value.find(item => item.id === id)
          if (!activity) throw new Error('Reload the selected occurrences.')
          let key = activityRequests.get(id)
          if (!key) {
            key = crypto.randomUUID()
            activityRequests.set(id, key)
          }
          return {
            requestId: key,
            teamId: teamId.value,
            roleId: roleId.value,
            sourceKind: 'EVENT_OCCURRENCE',
            sourceId: id,
            startsAt: new Date(
              Date.parse(activity.startsAt) - beforeMinutes.value * 60_000,
            ).toISOString(),
            endsAt: new Date(
              Date.parse(activity.endsAt) + afterMinutes.value * 60_000,
            ).toISOString(),
            positions: positions.value,
          }
        })
      } else if (sourceKind.value === 'CHILDREN_SESSION') {
        if (!sourceId.value) throw new Error('Choose a session.')
        preview.value = [
          {
            requestId: requestId.value,
            teamId: teamId.value,
            roleId: roleId.value,
            sourceKind: sourceKind.value,
            sourceId: sourceId.value,
            startsAt: instant(dutyFrom.value),
            endsAt: instant(dutyUntil.value),
            positions: positions.value,
          },
        ]
      } else {
        if (
          !Number.isInteger(count.value)
          || count.value < 1
          || count.value > 25
          || !Number.isInteger(intervalDays.value)
          || intervalDays.value < 1
          || intervalDays.value > 90
        )
          throw new Error(
            'Choose 1–25 shifts and a repeat interval of 1–90 days.',
          )
        const base = new Date(firstDate.value + 'T12:00:00Z')
        const dates = Array.from({ length: count.value }, (_, index) =>
          new Date(base.getTime() + index * intervalDays.value * 86_400_000)
            .toISOString()
            .slice(0, 10),
        )
        preview.value = (
          await useApi().post<Draft[]>('/api/volunteering/shifts/preview', {
            requestId: requestId.value,
            teamId: teamId.value,
            roleId: roleId.value,
            dates,
            startsAt: startTime.value,
            endsAt: endTime.value,
            timeZone: zone.value,
            positions: positions.value,
          })
        ).data
      }
    } catch (error_) {
      previewError.value
        = error_ instanceof Error && !('response' in error_)
          ? error_.message
          : servingError(error_)
    } finally {
      previewing.value = false
    }
  }
  async function createShifts () {
    if (preview.value.length === 0) throw new Error('Review the dates first.')
    await servingApi.bulkShifts(preview.value)
  }
  async function assign () {
    if (detail.value)
      await servingApi.assign({
        requestId: assignmentRequest.value,
        shiftId: detail.value.shift.id,
        expectedVersion: detail.value.shift.version,
        volunteerId: volunteer.value,
        status: commitment.value,
      })
  }
  async function checkReadiness () {
    if (!detail.value) return
    try {
      assessment.value = await servingApi.readiness(
        volunteer.value,
        detail.value.shift.id,
      )
    } catch (error_) {
      error.value = servingError(error_)
    }
  }
  function startInvite (booking: Booking) {
    selected.value = booking
    expires.value = detail.value
      ? localInput(new Date(detail.value.shift.startsAt))
      : ''
    inviteOpen.value = true
  }
  async function invite () {
    if (selected.value)
      await servingApi.invite(selected.value.assignment.id, {
        expectedVersion: selected.value.assignment.version,
        expiresAt: instant(expires.value),
      })
  }
  function startStatus (booking: Booking) {
    selected.value = booking
    commitment.value = booking.assignment.status
    reason.value = ''
    statusOpen.value = true
  }
  async function changeStatus () {
    if (selected.value)
      await servingApi.assignment(selected.value.assignment.id, {
        expectedVersion: selected.value.assignment.version,
        status: commitment.value,
        reason: reason.value,
      })
  }
  function editShift () {
    if (!detail.value) return
    dutyFrom.value = localInput(new Date(detail.value.shift.startsAt))
    dutyUntil.value = localInput(new Date(detail.value.shift.endsAt))
    positions.value = detail.value.shift.positions
    shiftStatus.value = detail.value.shift.status
    editOpen.value = true
  }
  async function saveShift () {
    if (detail.value)
      await servingApi.updateShift(detail.value.shift.id, {
        expectedVersion: detail.value.shift.version,
        startsAt: instant(dutyFrom.value),
        endsAt: instant(dutyUntil.value),
        positions: positions.value,
        status: shiftStatus.value,
      })
  }
  async function release () {
    releasing.value = true
    try {
      await servingApi.expire()
      await refresh()
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      releasing.value = false
    }
  }
  async function showHistory (booking: Booking) {
    try {
      history.value = await servingApi.history(booking.assignment.id)
      historyOpen.value = true
    } catch (error_) {
      error.value = servingError(error_)
    }
  }
  useCreateAction(() => manage.value, startCreate)
  onMounted(async () => {
    try {
      await Promise.all([load(), loadTeams()])
    } catch (error_) {
      error.value = servingError(error_)
    }
  })
</script>
