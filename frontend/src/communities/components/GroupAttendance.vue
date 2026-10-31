<template>
  <v-dialog
    v-model="open"
    max-width="1100"
    scrollable
  >
    <v-card
      v-if="meeting"
      class="directory-form"
    >
      <div class="directory-form__header">
        <div>
          <span class="directory-eyebrow">EVERY PERSON MATTERS</span>
          <h2>Meeting attendance</h2>

          <p>
            {{ dateLabel(meeting.date.startsAt, meeting.date.timeZone) }} ·
            {{ meeting.date.venue }}
          </p>
        </div>

        <v-btn
          aria-label="Close attendance"
          icon="mdi-close"
          variant="text"
          @click="open = false"
        />
      </div>

      <v-card-text>
        <v-alert
          v-if="error"
          class="mb-4"
          type="error"
          variant="tonal"
        >
          {{ error }}
        </v-alert>

        <v-alert
          v-if="!meeting.meeting.rosterFrozen"
          class="mb-4"
          type="info"
          variant="tonal"
        >
          Prepare the roster from two hours before the meeting starts. This
          preserves the expected members for this date.
        </v-alert>

        <v-alert
          v-if="closed"
          class="mb-4"
          type="info"
          variant="tonal"
        >
          This meeting is closed to new attendance. Previously recorded outcomes
          can be corrected with a reason.
        </v-alert>

        <div class="d-flex flex-wrap ga-2 mb-4">
          <v-btn
            v-if="!meeting.meeting.rosterFrozen && !closed"
            color="primary"
            :loading="busy"
            @click="prepare"
          >
            Prepare member roster
          </v-btn>

          <v-btn
            v-if="
              meeting.meeting.rosterFrozen &&
                !closed &&
                auth.hasPermission('people.read')
            "
            variant="tonal"
            @click="startGuest"
          >
            Add guest
          </v-btn>

          <v-btn
            v-if="!closed && meeting.meeting.rosterFrozen"
            variant="tonal"
            @click="startFinish('CLOSED')"
          >
            Close meeting
          </v-btn>

          <v-btn
            v-if="
              meeting.meeting.status === 'OPEN' &&
                auth.hasPermission('ministries.manage')
            "
            color="warning"
            variant="text"
            @click="startFinish('CANCELLED')"
          >
            Cancel meeting
          </v-btn>

          <v-btn
            prepend-icon="mdi-refresh"
            variant="text"
            @click="load"
          >
            Refresh roster
          </v-btn>
        </div>

        <p
          v-if="counts"
          class="directory-muted mb-4"
        >
          {{ counts.present }} present · {{ counts.absent }} absent ·
          {{ counts.excused }} excused · {{ counts.unrecorded }} unrecorded ·
          {{ counts.guests }} guests
        </p>

        <v-progress-linear
          v-if="loading"
          indeterminate
        />

        <div class="community-table community-roster">
          <v-table>
            <thead>
              <tr>
                <th>Person</th>
                <th>Participation</th>
                <th>Outcome</th>
                <th>Actions</th>
              </tr>
            </thead>

            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
              >
                <td>{{ row.name }}</td>
                <td>{{ row.guest ? 'Guest' : 'Member' }}</td>

                <td>
                  <v-chip
                    :color="row.status === 'PRESENT' ? 'success' : 'default'"
                    size="small"
                  >
                    {{ row.status }}
                  </v-chip>
                </td>

                <td>
                  <v-btn
                    v-if="!closed || row.version > 0"
                    size="small"
                    variant="tonal"
                    @click="edit(row)"
                  >
                    {{
                      row.version > 0
                        ? 'Correct attendance'
                        : 'Record attendance'
                    }}
                  </v-btn>
                </td>
              </tr>
            </tbody>
          </v-table>
        </div>

        <p
          v-if="!loading && rows.length === 0"
          class="directory-muted py-5"
        >
          No people on this roster yet.
        </p>

        <div class="community-footer">
          <span>{{ total }} people</span>

          <v-btn
            :disabled="offset === 0 || loading"
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
      </v-card-text>
    </v-card>

    <CommunityDialog
      v-model="editOpen"
      :submit="saveAttendance"
      subtitle="Group participation is recorded independently of event bookings and children's check-ins."
      :title="selected?.version ? 'Correct attendance' : 'Record attendance'"
      @reload="reloadEdit"
      @saved="changed"
    >
      <p class="mb-4">{{ selected?.name }}</p>

      <v-select
        v-model="status"
        :items="['PRESENT', 'ABSENT', 'EXCUSED', 'UNRECORDED']"
        label="Attendance outcome"
      />

      <v-textarea
        v-model="reason"
        label="Reason and evidence"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="guestOpen"
      :submit="saveGuest"
      subtitle="Add an existing People record to this meeting only. This does not create group membership."
      title="Welcome a guest"
      @reload="reloadGuest"
      @saved="changed"
    >
      <PersonPicker
        v-model="personId"
        label="Find a guest"
        :rules="[required]"
      />

      <v-textarea
        v-model="reason"
        label="Reason for adding guest"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>

    <CommunityDialog
      v-model="finishOpen"
      :submit="saveFinish"
      subtitle="Keep every recorded outcome. Unrecorded participants remain unrecorded; they are never silently marked absent."
      :title="
        finishStatus === 'CLOSED' ? 'Close this meeting' : 'Cancel this meeting'
      "
      @reload="reloadFinish"
      @saved="changed"
    >
      <v-textarea
        v-model="reason"
        label="Review note"
        maxlength="2000"
        :rules="[required]"
      />
    </CommunityDialog>
  </v-dialog>
</template>
<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { useAuthStore } from '@/stores/auth'
  import {
    communityApi,
    type Counts,
    dateLabel,
    errorMessage,
    type Meeting,
    required,
    type Roster,
  } from '../api'
  import CommunityDialog from './CommunityDialog.vue'
  const open = defineModel<boolean>({ required: true }),
        props = defineProps<{ meeting?: Meeting }>(),
        emit = defineEmits<{ changed: [] }>(),
        auth = useAuthStore()
  const rows = ref<Roster[]>([]),
        counts = ref<Counts>(),
        total = ref(0),
        offset = ref(0),
        loading = ref(false),
        busy = ref(false),
        error = ref('')
  const closed = computed(
    () =>
      props.meeting?.meeting.status !== 'OPEN'
      || props.meeting?.date.status !== 'SCHEDULED',
  )
  let generation = 0
  async function load () {
    if (!props.meeting || !open.value) return
    const current = ++generation
    loading.value = true
    try {
      const result = await communityApi.roster(
        props.meeting.meeting.id,
        offset.value,
      )
      if (current === generation) {
        rows.value = result.roster
        counts.value = result.counts
        total.value = result.total
        error.value = ''
      }
    } catch (error_) {
      if (current === generation) error.value = errorMessage(error_)
    } finally {
      if (current === generation) loading.value = false
    }
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  function changed () {
    emit('changed')
    load()
  }
  async function prepare () {
    if (!props.meeting) return
    busy.value = true
    try {
      await communityApi.prepare(
        props.meeting.meeting.id,
        props.meeting.meeting.version,
      )
      changed()
    } catch (error_) {
      error.value = errorMessage(error_)
    } finally {
      busy.value = false
    }
  }
  const editOpen = ref(false),
        guestOpen = ref(false),
        finishOpen = ref(false),
        selected = ref<Roster>(),
        status = ref('PRESENT'),
        reason = ref(''),
        personId = ref<string | null>(null),
        finishStatus = ref('CLOSED')
  function edit (row: Roster) {
    selected.value = row
    status.value = row.status === 'UNRECORDED' ? 'PRESENT' : row.status
    reason.value = row.version === 0 ? 'Recorded at the group gathering.' : ''
    editOpen.value = true
  }
  async function saveAttendance () {
    if (selected.value)
      await communityApi.attendance(selected.value.id, {
        expectedVersion: selected.value.version,
        status: status.value,
        reason: reason.value,
      })
  }
  function startGuest () {
    personId.value = null
    reason.value = ''
    guestOpen.value = true
  }
  async function saveGuest () {
    if (props.meeting)
      await communityApi.guest(props.meeting.meeting.id, {
        expectedVersion: props.meeting.meeting.version,
        personId: personId.value,
        reason: reason.value,
      })
  }
  function startFinish (value: string) {
    finishStatus.value = value
    reason.value = ''
    finishOpen.value = true
  }
  async function saveFinish () {
    if (props.meeting)
      await communityApi.finish(props.meeting.meeting.id, {
        expectedVersion: props.meeting.meeting.version,
        status: finishStatus.value,
        reason: reason.value,
      })
  }
  function reloadEdit () {
    editOpen.value = false
    changed()
  }
  function reloadGuest () {
    guestOpen.value = false
    changed()
  }
  function reloadFinish () {
    finishOpen.value = false
    changed()
  }
  watch(open, value => {
    if (value) {
      offset.value = 0
      load()
    } else {
      generation++
    }
  })
  watch(() => props.meeting?.meeting.version, load)
</script>
