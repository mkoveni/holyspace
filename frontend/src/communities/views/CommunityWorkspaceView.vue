<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="ROOTED IN RELATIONSHIP"
      :subtitle="
        community?.description || 'People, purpose and gatherings in one place.'
      "
      :title="community?.name || 'Community workspace'"
    >
      <v-btn
        prepend-icon="mdi-arrow-left"
        :to="community?.kind === 'MINISTRY' ? '/ministries' : '/groups'"
        variant="text"
      >
        Back to directory
      </v-btn>

      <v-btn
        v-if="manage && community"
        variant="tonal"
        @click="startEdit"
      >
        Community details
      </v-btn>
    </DirectoryHeading>

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
      indeterminate
    />

    <template v-if="community">
      <PortalImage
        :editable="manage"
        label="Cover image"
        :owner-id="community.id"
        target="community"
      />

      <div class="d-flex flex-wrap ga-2 mb-5">
        <v-chip>{{ community.kind === 'GROUP' ? 'Group' : 'Ministry' }}</v-chip>

        <v-chip :color="community.active ? 'success' : 'default'">
          {{ community.active ? 'Active' : 'Archived' }}
        </v-chip>

        <v-chip
          v-if="community.campus"
          prepend-icon="mdi-map-marker-outline"
        >
          {{ community.campus }}
        </v-chip>

        <v-btn
          v-if="community.parentId"
          size="small"
          :to="'/communities/' + community.parentId"
          variant="text"
        >
          Parent ministry
        </v-btn>
      </div>

      <v-select
        v-if="canOperate"
        v-model="tab"
        class="d-sm-none"
        item-title="title"
        item-value="value"
        :items="tabs"
        label="Workspace section"
      />

      <nav
        v-if="canOperate"
        aria-label="Community workspace"
        class="community-navigation d-none d-sm-flex"
      >
        <v-btn
          v-for="item in tabs"
          :key="item.value"
          :variant="tab === item.value ? 'tonal' : 'text'"
          @click="tab = item.value"
        >
          {{ item.title }}
        </v-btn>
      </nav>

      <v-alert
        v-if="!canOperate"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        This directory view shows community details. Membership and meeting
        access requires management permission or a current leadership
        appointment.
      </v-alert>

      <section
        v-if="canOperate"
        class="directory-surface community-section"
      >
        <v-alert
          v-if="sectionError"
          class="mb-4"
          type="error"
          variant="tonal"
        >
          {{ sectionError }}
        </v-alert>

        <v-progress-linear
          v-if="sectionLoading"
          indeterminate
        />

        <template v-if="tab === 'members'">
          <div class="d-flex flex-wrap ga-2 align-center mb-4">
            <h2 class="mr-auto">People who belong</h2>

            <v-btn
              v-if="
                manage && community.active && auth.hasPermission('people.read')
              "
              color="primary"
              prepend-icon="mdi-account-plus-outline"
              @click="startJoin"
            >
              Add member
            </v-btn>
          </div>

          <v-switch
            v-model="includeEnded"
            color="primary"
            label="Include previous memberships"
          />

          <div class="community-table community-roster">
            <v-table>
              <thead>
                <tr>
                  <th>Person</th>
                  <th>Joined</th>
                  <th>Membership</th>
                  <th>Actions</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="item in members"
                  :key="item.membership.id"
                >
                  <td>
                    <RouterLink
                      v-if="auth.hasPermission('people.read')"
                      :to="'/people/' + item.membership.personId"
                    >
                      {{ item.name }}
                    </RouterLink>

                    <span v-else>{{ item.name }}</span>
                  </td>

                  <td>{{ dateLabel(item.membership.startedAt) }}</td>

                  <td>
                    {{
                      item.membership.endedAt
                        ? 'Ended ' + dateLabel(item.membership.endedAt)
                        : 'Current member'
                    }}
                  </td>

                  <td>
                    <div class="d-flex flex-wrap ga-1">
                      <v-btn
                        v-if="
                          manage && !item.membership.endedAt && community.active
                        "
                        size="small"
                        variant="text"
                        @click="startAppoint(item)"
                      >
                        Appoint leader
                      </v-btn>

                      <v-btn
                        v-if="manage && !item.membership.endedAt"
                        size="small"
                        variant="text"
                        @click="startLeave(item)"
                      >
                        End membership
                      </v-btn>

                      <v-btn
                        v-if="
                          canMessage &&
                            !item.membership.endedAt &&
                            community.active
                        "
                        size="small"
                        variant="tonal"
                        @click="startNotice(item)"
                      >
                        Prepare notice
                      </v-btn>

                      <v-btn
                        v-if="community.active"
                        size="small"
                        variant="text"
                        @click="startMemberFollowup(item)"
                      >
                        Plan follow-up
                      </v-btn>
                    </div>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </div>

          <p
            v-if="!sectionLoading && members.length === 0"
            class="directory-muted py-5"
          >
            No memberships in this view.
          </p>
        </template>

        <template v-if="tab === 'leadership'">
          <h2 class="mb-2">Leadership with clear responsibility</h2>

          <p class="directory-muted mb-4">
            Appointments are dated independently of membership. Ending an
            appointment revokes its workspace access.
          </p>

          <article
            v-for="item in leaders"
            :key="item.leadership.id"
            class="community-record"
          >
            <div class="d-flex flex-wrap ga-2 align-center">
              <strong class="mr-auto">
                {{ item.personName || item.accountName }} ·
                {{ item.leadership.role === 'LEADER' ? 'Leader' : 'Co-leader' }}
              </strong>

              <v-chip size="small">
                {{ item.leadership.endedAt ? 'Ended' : 'Current appointment' }}
              </v-chip>

              <v-btn
                v-if="manage && !item.leadership.endedAt"
                size="small"
                variant="text"
                @click="startEndLeadership(item.leadership)"
              >
                End appointment
              </v-btn>
            </div>

            <p class="directory-muted mt-2">
              {{ item.accountName }} · Started
              {{ dateLabel(item.leadership.startedAt) }}
              <span v-if="item.leadership.endedAt">
                · Ended {{ dateLabel(item.leadership.endedAt) }}
              </span>
            </p>
          </article>

          <p
            v-if="!sectionLoading && leaders.length === 0"
            class="directory-muted py-5"
          >
            No leadership appointments yet. Appoint a current member from the
            Members section.
          </p>
        </template>

        <template v-if="tab === 'meetings'">
          <div class="d-flex flex-wrap ga-2 align-center mb-4">
            <h2 class="mr-auto">Gatherings &amp; participation</h2>

            <v-btn
              v-if="
                manage &&
                  community.active &&
                  auth.hasPermission('events.manage')
              "
              color="primary"
              prepend-icon="mdi-calendar-plus"
              @click="startSchedule"
            >
              Schedule meetings
            </v-btn>

            <v-btn
              :disabled="meetings.length === 0"
              variant="tonal"
              @click="exportPage"
            >
              Export this page
            </v-btn>
          </div>

          <p class="directory-muted mb-5">
            Meetings use the shared Events calendar. Group attendance has its
            own expected roster, guests and reasoned corrections. Counts below
            describe this page.
          </p>

          <div class="community-summary mb-5">
            <div class="directory-surface">
              <span class="directory-eyebrow">PRESENT</span>
              <strong>{{ meetingTotals.present }}</strong>
            </div>

            <div class="directory-surface">
              <span class="directory-eyebrow">ABSENT</span>
              <strong>{{ meetingTotals.absent }}</strong>
            </div>

            <div class="directory-surface">
              <span class="directory-eyebrow">EXCUSED</span>
              <strong>{{ meetingTotals.excused }}</strong>
            </div>

            <div class="directory-surface">
              <span class="directory-eyebrow">UNRECORDED</span>
              <strong>{{ meetingTotals.unrecorded }}</strong>
            </div>
          </div>

          <article
            v-for="item in meetings"
            :key="item.meeting.id"
            class="community-record"
          >
            <div class="d-flex align-center flex-wrap ga-3">
              <div class="mr-auto">
                <strong>
                  {{ dateLabel(item.date.startsAt, item.date.timeZone) }}
                </strong>

                <p class="directory-muted">
                  {{ item.date.venue }} · {{ item.date.timeZone }}
                </p>
              </div>

              <v-chip size="small">
                {{
                  item.date.status === 'CANCELLED'
                    ? 'Occurrence cancelled'
                    : item.meeting.status
                }}
              </v-chip>

              <v-btn
                variant="tonal"
                @click="openAttendance(item)"
              >
                Open attendance
              </v-btn>

              <v-btn
                v-if="auth.hasPermission('events.read')"
                :to="'/events/occurrences/' + item.meeting.occurrenceId"
                variant="text"
              >
                Calendar details
              </v-btn>
            </div>

            <p class="directory-muted mt-3">
              {{ item.counts.present }} present ·
              {{ item.counts.absent }} absent ·
              {{ item.counts.excused }} excused ·
              {{ item.counts.unrecorded }} unrecorded ·
              {{ item.counts.guests }} guests
            </p>
          </article>

          <p
            v-if="!sectionLoading && meetings.length === 0"
            class="directory-muted py-5"
          >
            No meetings scheduled yet. Create one date or a recurring series
            here.
          </p>
        </template>

        <template v-if="tab === 'followups'">
          <div class="d-flex flex-wrap ga-2 align-center mb-4">
            <h2 class="mr-auto">Care through consistent follow-up</h2>

            <v-btn
              v-if="community.active && auth.hasPermission('people.read')"
              color="primary"
              @click="startFollowup"
            >
              Add follow-up
            </v-btn>
          </div>

          <p class="directory-muted mb-4">
            Record ordinary member contact and participation follow-up here.
            Keep safeguarding and sensitive care records in their dedicated
            workflows.
          </p>

          <v-switch
            v-model="includeCompleted"
            color="primary"
            label="Include completed follow-up"
          />

          <article
            v-for="item in followups"
            :key="item.id"
            class="community-record"
          >
            <div class="d-flex flex-wrap ga-2 align-center">
              <strong class="mr-auto">{{ item.name }}</strong>

              <v-chip size="small">
                {{ item.status === 'DONE' ? 'Completed' : 'Due ' + item.dueOn }}
              </v-chip>

              <v-btn
                v-if="item.status === 'OPEN'"
                size="small"
                variant="tonal"
                @click="startComplete(item)"
              >
                Complete follow-up
              </v-btn>
            </div>

            <p class="community-note mt-3">{{ item.note }}</p>
          </article>

          <p
            v-if="!sectionLoading && followups.length === 0"
            class="directory-muted py-5"
          >
            No follow-up in this view.
          </p>
        </template>

        <template v-if="tab === 'deliveries'">
          <h2 class="mb-4">Every member notice, accounted for</h2>

          <p class="directory-muted mb-4">
            Approved content stays frozen. Sending rechecks membership, contact
            and sender access. Captured messages remain in the local email
            inbox.
          </p>

          <article
            v-for="item in deliveryRows"
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

          <p
            v-if="!sectionLoading && deliveryRows.length === 0"
            class="directory-muted py-5"
          >
            No member notices on this page.
          </p>
        </template>

        <template v-if="tab === 'history'">
          <h2 class="mb-4">A clear record of changes</h2>

          <article
            v-for="entry in history"
            :key="entry.id"
            class="community-record"
          >
            <strong>{{ entry.action.replaceAll('_', ' ') }}</strong>
            <p class="community-note">{{ entry.reason }}</p>

            <p class="directory-muted mt-2">
              {{ dateLabel(entry.occurredAt) }}
            </p>
          </article>

          <p
            v-if="!sectionLoading && history.length === 0"
            class="directory-muted py-5"
          >
            No history on this page.
          </p>
        </template>

        <div class="community-footer">
          <span v-if="total !== null">{{ total }} records</span>

          <v-btn
            :disabled="offset === 0 || sectionLoading"
            variant="text"
            @click="page(-25)"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="!hasNext || sectionLoading"
            variant="text"
            @click="page(25)"
          >
            Next
          </v-btn>
        </div>
      </section>

      <v-alert
        v-if="noticeQueued"
        class="mb-4"
        type="success"
        variant="tonal"
      >
        Reviewed member notice queued. Its delivery eligibility will be checked
        again before sending.
      </v-alert>

      <GroupAttendance
        v-model="attendanceOpen"
        :meeting="selectedMeeting"
        @changed="loadSection"
      />

      <MemberNotice
        v-model="noticeOpen"
        :member="selectedMember"
        @saved="noticeQueued = true"
      />
    </template>

    <CommunityDialog
      v-model="editOpen"
      :submit="saveEdit"
      subtitle="Archive without deleting membership, leadership or attendance history."
      title="Community details"
      @reload="reloadAll"
      @saved="load"
    >
      <v-text-field
        v-model="editDraft.name"
        label="Name"
        maxlength="150"
        :rules="[required]"
      />

      <v-textarea
        v-model="editDraft.description"
        label="Purpose and description"
        maxlength="5000"
      />

      <v-text-field
        v-model="editDraft.campus"
        label="Campus or area"
        maxlength="150"
      />

      <v-switch
        v-model="editDraft.active"
        color="primary"
        label="Community active"
      />

      <v-textarea
        v-model="reason"
        label="Reason for change"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="joinOpen"
      :submit="saveJoin"
      subtitle="Membership starts now. A person can belong to several groups and ministries; joining grants no volunteer eligibility."
      title="Welcome a member"
      @reload="reloadAll"
      @saved="loadSection"
    >
      <PersonPicker
        v-model="personId"
        label="Find a person"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="actionOpen"
      :submit="saveAction"
      subtitle="The change takes effect now and retains its history."
      :title="
        action === 'leave'
          ? 'End this membership'
          : action === 'endLeader'
            ? 'End this leadership appointment'
            : 'Complete this follow-up'
      "
      @reload="reloadAll"
      @saved="loadSection"
    >
      <p class="mb-4">{{ selectedMember?.name }}</p>

      <v-textarea
        v-model="reason"
        label="Reason and outcome"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="appointOpen"
      :submit="saveAppoint"
      subtitle="An appointment is separate from membership. Verify any login link before granting workspace access."
      title="Appoint a leader"
      @reload="reloadAll"
      @saved="loadSection"
    >
      <p class="mb-4">{{ selectedMember?.name }}</p>

      <v-select
        v-model="role"
        :items="[
          { title: 'Leader', value: 'LEADER' },
          { title: 'Co-leader', value: 'CO_LEADER' },
        ]"
        label="Responsibility"
      />

      <AccountPicker
        v-if="auth.hasPermission('identity.manage')"
        v-model="accountId"
      />

      <p
        v-else
        class="directory-muted mb-4"
      >
        An identity administrator can create an appointment linked to a login
        account.
      </p>

      <v-textarea
        v-model="reason"
        label="Appointment reason and identity verification"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="scheduleOpen"
      :keep-open="reviewingSchedule"
      :save-label="
        scheduleReview ? 'Create reviewed meetings' : 'Review meeting dates'
      "
      :submit="saveSchedule"
      subtitle="Create the shared calendar dates and group meetings together, without leaving this workspace."
      title="Plan the next gathering"
      @reload="reloadAll"
      @saved="load"
    >
      <VenuePicker v-model="schedule.venueId" />

      <DateSelector
        v-model="schedule.firstDate"
        label="First meeting date"
        :rules="[required]"
      />

      <div class="community-fields">
        <v-text-field
          v-model="schedule.startsAt"
          label="Starts at"
          placeholder="18:00"
          :rules="[required]"
        />

        <v-text-field
          v-model="schedule.endsAt"
          label="Ends at"
          placeholder="20:00"
          :rules="[required]"
        />
      </div>

      <v-text-field
        v-model="schedule.timeZone"
        label="Time zone"
        :rules="[required]"
      />

      <v-select
        v-model="schedule.frequency"
        :items="['ONCE', 'DAILY', 'WEEKLY', 'MONTHLY']"
        label="Repeat"
      />

      <div
        v-if="schedule.frequency !== 'ONCE'"
        class="community-fields"
      >
        <v-number-input
          v-model="schedule.count"
          label="Number of meetings"
          :max="104"
          :min="1"
        />

        <v-number-input
          v-model="schedule.interval"
          label="Repeat interval"
          :max="52"
          :min="1"
        />
      </div>

      <v-number-input
        v-model="schedule.endDayOffset"
        label="Ends days after start"
        :max="7"
        :min="0"
      />

      <div
        v-if="scheduleReview"
        class="community-record"
      >
        <strong>{{ scheduleReview.dates.length }} reviewed meetings</strong>

        <p
          v-for="date in scheduleReview.dates"
          :key="date.startsAt"
        >
          {{ dateLabel(date.startsAt, date.timeZone) }} · {{ date.venue }}
        </p>
      </div>
    </CommunityDialog>

    <CommunityDialog
      v-model="followupOpen"
      :submit="saveFollowup"
      subtitle="Keep an ordinary contact task with the community responsible for it."
      title="Plan a member follow-up"
      @reload="reloadAll"
      @saved="loadSection"
    >
      <p
        v-if="selectedMember"
        class="mb-4"
      >
        {{ selectedMember.name }}
      </p>

      <PersonPicker
        v-else
        v-model="personId"
        label="Find a current member"
        :rules="[required]"
      />

      <DateSelector
        v-model="dueOn"
        label="Follow-up due date"
        :rules="[required]"
      />

      <v-textarea
        v-model="note"
        label="Follow-up action"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import PortalImage from '@/components/common/PortalImage.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import VenuePicker from '@/events/components/VenuePicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    type Community,
    communityApi,
    dateLabel,
    errorMessage,
    type FollowUp,
    type History,
    type Leadership,
    type Meeting,
    type Member,
    type MemberDelivery,
    newSchedule,
    required,
  } from '../api'
  import AccountPicker from '../components/AccountPicker.vue'
  import CommunityDialog from '../components/CommunityDialog.vue'
  import GroupAttendance from '../components/GroupAttendance.vue'
  import MemberNotice from '../components/MemberNotice.vue'
  import '@/directory/directory.scss'
  import '../communities.css'
  const route = useRoute()
  let id = String(route.params.id)
  const auth = useAuthStore(),
        manage = computed(() => auth.hasPermission('ministries.manage')),
        canMessage = computed(
          () =>
            auth.hasPermission('ministries.communications.send')
            && canOperate.value,
        )
  const canOperate = ref(false)
  const community = ref<Community>(),
        loading = ref(false),
        error = ref(''),
        sectionError = ref(''),
        sectionLoading = ref(false),
        tab = ref('members'),
        offset = ref(0),
        total = ref<number | null>(null),
        hasNext = ref(false)
  const tabs = computed(() => [
    { title: 'Members', value: 'members' },
    { title: 'Leadership', value: 'leadership' },
    ...(community.value?.kind === 'GROUP'
      ? [{ title: 'Meetings & reports', value: 'meetings' }]
      : []),
    { title: 'Follow-up', value: 'followups' },
    { title: 'History', value: 'history' },
    ...(canMessage.value
      ? [{ title: 'Email outcomes', value: 'deliveries' }]
      : []),
  ])
  const members = ref<Member[]>([]),
        leaders = ref<
          { leadership: Leadership, accountName: string, personName?: string }[]
        >([]),
        meetings = ref<Meeting[]>([]),
        followups = ref<FollowUp[]>([]),
        history = ref<History[]>([]),
        includeEnded = ref(false),
        includeCompleted = ref(false)
  const deliveryRows = ref<MemberDelivery[]>([])
  let generation = 0
  let loadGeneration = 0
  async function load () {
    const current = ++loadGeneration
    loading.value = true
    try {
      const details = await communityApi.detail(id)
      if (current !== loadGeneration) return
      community.value = details.community
      canOperate.value = details.canOperate
      error.value = ''
      if (canOperate.value) await loadSection()
    } catch (error_) {
      if (current === loadGeneration) error.value = errorMessage(error_)
    } finally {
      if (current === loadGeneration) loading.value = false
    }
  }
  async function loadSection () {
    if (!canOperate.value) return
    const current = ++generation
    sectionLoading.value = true
    try {
      let count: number | null = null,
          length = 0
      switch (tab.value) {
        case 'members': {
          const page = await communityApi.members(
            id,
            includeEnded.value,
            offset.value,
          )
          if (current !== generation) return
          members.value = page.members
          count = page.total
          length = page.members.length

          break
        }
        case 'leadership': {
          const page = await communityApi.leadership(id, offset.value)
          if (current !== generation) return
          leaders.value = page
          length = page.length

          break
        }
        case 'meetings': {
          const page = await communityApi.meetings(id, offset.value)
          if (current !== generation) return
          meetings.value = page.meetings
          count = page.total
          length = page.meetings.length

          break
        }
        case 'followups': {
          const page = await communityApi.followups(
            id,
            includeCompleted.value,
            offset.value,
          )
          if (current !== generation) return
          followups.value = page
          length = page.length

          break
        }
        case 'deliveries': {
          const page = await communityApi.deliveries(id, offset.value)
          if (current !== generation) return
          deliveryRows.value = page
          length = page.length
          break
        }
        default: {
          const page = await communityApi.history(id, offset.value)
          if (current !== generation) return
          history.value = page
          length = page.length
        }
      }
      total.value = count
      hasNext.value = count === null ? length === 25 : offset.value + 25 < count
      sectionError.value = ''
    } catch (error_) {
      if (current === generation) sectionError.value = errorMessage(error_)
    } finally {
      if (current === generation) sectionLoading.value = false
    }
  }
  watch([tab, includeEnded, includeCompleted], () => {
    offset.value = 0
    loadSection()
  })
  function page (delta: number) {
    offset.value += delta
    loadSection()
  }
  const editOpen = ref(false),
        joinOpen = ref(false),
        appointOpen = ref(false),
        actionOpen = ref(false),
        scheduleOpen = ref(false),
        followupOpen = ref(false),
        attendanceOpen = ref(false),
        noticeOpen = ref(false),
        noticeQueued = ref(false)
  const editDraft = ref({
          name: '',
          description: '',
          campus: '',
          active: true,
        }),
        reason = ref(''),
        personId = ref<string | null>(null),
        accountId = ref<string | null>(null),
        role = ref('LEADER'),
        action = ref(''),
        selectedMember = ref<Member>(),
        selectedLeader = ref<Leadership>(),
        selectedFollowUp = ref<FollowUp>(),
        selectedMeetingId = ref(''),
        dueOn = ref(''),
        note = ref('')
  let request = crypto.randomUUID()
  const selectedMeeting = computed(() =>
    meetings.value.find(m => m.meeting.id === selectedMeetingId.value),
  )
  function startEdit () {
    if (!community.value) return
    editDraft.value = {
      name: community.value.name,
      description: community.value.description,
      campus: community.value.campus,
      active: community.value.active,
    }
    reason.value = ''
    editOpen.value = true
  }
  async function saveEdit () {
    if (community.value)
      await communityApi.update(id, {
        expectedVersion: community.value.version,
        ...editDraft.value,
        parentId: community.value.parentId,
        reason: reason.value,
      })
  }
  function startJoin () {
    personId.value = null
    request = crypto.randomUUID()
    joinOpen.value = true
  }
  async function saveJoin () {
    if (personId.value) await communityApi.join(id, personId.value, request)
  }
  watch(personId, () => {
    request = crypto.randomUUID()
  })
  function startAppoint (member: Member) {
    selectedMember.value = member
    role.value = 'LEADER'
    accountId.value = null
    reason.value = ''
    request = crypto.randomUUID()
    appointOpen.value = true
  }
  async function saveAppoint () {
    if (selectedMember.value)
      await communityApi.appoint(selectedMember.value.membership.id, {
        requestId: request,
        expectedVersion: selectedMember.value.membership.version,
        role: role.value,
        accountId: accountId.value,
        reason: reason.value,
      })
  }
  function startLeave (member: Member) {
    selectedMember.value = member
    action.value = 'leave'
    reason.value = ''
    actionOpen.value = true
  }
  function startEndLeadership (leader: Leadership) {
    selectedMember.value = undefined
    selectedLeader.value = leader
    action.value = 'endLeader'
    reason.value = ''
    actionOpen.value = true
  }
  function startComplete (item: FollowUp) {
    selectedMember.value = undefined
    selectedFollowUp.value = item
    action.value = 'complete'
    reason.value = ''
    actionOpen.value = true
  }
  async function saveAction () {
    if (action.value === 'leave' && selectedMember.value)
      await communityApi.leave(selectedMember.value.membership.id, {
        expectedVersion: selectedMember.value.membership.version,
        reason: reason.value,
      })
    if (action.value === 'endLeader' && selectedLeader.value)
      await communityApi.endLeadership(selectedLeader.value.id, {
        expectedVersion: selectedLeader.value.version,
        reason: reason.value,
      })
    if (action.value === 'complete' && selectedFollowUp.value)
      await communityApi.complete(selectedFollowUp.value.id, {
        expectedVersion: selectedFollowUp.value.version,
        reason: reason.value,
      })
  }
  const schedule = ref(newSchedule()),
        scheduleReview = ref<{ hash: string, dates: Meeting['date'][] }>(),
        reviewingSchedule = ref(false)
  watch(
    schedule,
    () => {
      scheduleReview.value = undefined
    },
    { deep: true },
  )
  function startSchedule () {
    schedule.value = newSchedule()
    scheduleReview.value = undefined
    request = crypto.randomUUID()
    scheduleOpen.value = true
  }
  async function saveSchedule () {
    if (!community.value) return
    if (!scheduleReview.value) {
      reviewingSchedule.value = true
      scheduleReview.value = await communityApi.preview(id, schedule.value)
      return
    }
    reviewingSchedule.value = false
    await communityApi.schedule(id, {
      requestId: request,
      expectedVersion: community.value.version,
      schedule: schedule.value,
      reviewHash: scheduleReview.value.hash,
    })
  }
  function openAttendance (item: Meeting) {
    selectedMeetingId.value = item.meeting.id
    attendanceOpen.value = true
  }
  function startNotice (member: Member) {
    selectedMember.value = member
    noticeQueued.value = false
    noticeOpen.value = true
  }
  function startMemberFollowup (member: Member) {
    startFollowup()
    selectedMember.value = member
    personId.value = member.membership.personId
  }
  function startFollowup () {
    selectedMember.value = undefined
    personId.value = null
    note.value = ''
    dueOn.value = new Date().toISOString().slice(0, 10)
    request = crypto.randomUUID()
    followupOpen.value = true
  }
  async function saveFollowup () {
    await communityApi.followup(id, {
      requestId: request,
      personId: personId.value,
      note: note.value,
      dueOn: dueOn.value,
    })
  }
  function reloadAll () {
    editOpen.value = false
    joinOpen.value = false
    appointOpen.value = false
    actionOpen.value = false
    scheduleOpen.value = false
    followupOpen.value = false
    load()
  }
  const meetingTotals = computed(() =>
    meetings.value.reduce(
      (sum, item) => ({
        present: sum.present + item.counts.present,
        absent: sum.absent + item.counts.absent,
        excused: sum.excused + item.counts.excused,
        unrecorded: sum.unrecorded + item.counts.unrecorded,
      }),
      { present: 0, absent: 0, excused: 0, unrecorded: 0 },
    ),
  )
  function exportPage () {
    function cell (value: unknown) {
      const text = String(value ?? '')
        .replace(/^[=+@\-\t\r]/, '\'$&')
        .replaceAll('"', '""')
      return '"' + text + '"'
    }
    const lines = [
      [
        'Meeting',
        'Starts',
        'Time zone',
        'Venue',
        'Status',
        'Present',
        'Absent',
        'Excused',
        'Unrecorded',
        'Guests',
      ],
      ...meetings.value.map(m => [
        m.meeting.id,
        m.date.startsAt,
        m.date.timeZone,
        m.date.venue,
        m.meeting.status,
        m.counts.present,
        m.counts.absent,
        m.counts.excused,
        m.counts.unrecorded,
        m.counts.guests,
      ]),
    ]
    const url = URL.createObjectURL(
            new Blob(
              [
                lines
                  .map(row => row.map(value => cell(value)).join(','))
                  .join('\r\n'),
              ],
              {
                type: 'text/csv;charset=utf-8',
              },
            ),
          ),
          anchor = document.createElement('a')
    anchor.href = url
    anchor.download = 'group-participation-page.csv'
    anchor.click()
    setTimeout(() => URL.revokeObjectURL(url), 1000)
  }
  watch(
    () => route.params.id,
    value => {
      id = String(value)
      generation++
      community.value = undefined
      canOperate.value = false
      offset.value = 0
      tab.value = 'members'
      attendanceOpen.value = false
      noticeOpen.value = false
      noticeQueued.value = false
      reloadAll()
    },
  )
  onBeforeUnmount(() => {
    generation++
    loadGeneration++
  })
  onMounted(load)
</script>
