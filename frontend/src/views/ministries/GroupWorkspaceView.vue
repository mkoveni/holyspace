<template>
  <div>
    <PageHeader title="Group workspace" subtitle="Your group rosters, meetings and attendance" />
    <v-alert v-if="error" class="mb-4" type="error">{{ error }}<v-btn variant="text" @click="loadGroups">Reload groups</v-btn></v-alert>
    <v-select v-model="groupId" :items="groupOptions" label="Group" :disabled="busy" />
    <v-progress-linear v-if="loading" indeterminate />
    <p v-if="!loading && !groups.length">No groups are available. Your administrator must link your account to your person record and assign you as an active group leader.</p>
    <template v-if="overview">
      <v-card class="mb-4"><v-card-title>{{ overview.group.name }} · Roster</v-card-title><v-data-table :headers="memberHeaders" :items="overview.members"><template #item.active="{item}">{{ item.active ? 'Active' : 'Left' }}</template></v-data-table></v-card>
      <v-card v-if="overview.canManage" class="mb-4"><v-card-title>Link a group meeting</v-card-title><v-card-text>
        <p class="mb-3">Create the gathering in Events first, then link its scheduled occurrence. Use separate occurrences for different groups.</p>
        <v-form v-if="access.hasPermission('events.read') && overview.group.active" ref="meetingForm" @submit.prevent="addMeeting">
          <v-select v-model="eventId" :items="eventOptions" label="Meeting event" @update:model-value="occurrenceId = null" />
          <v-select v-model="occurrenceId" :items="occurrenceOptions" label="Meeting occurrence" :rules="[required]" />
          <v-btn color="primary" type="submit" :loading="busy">Link meeting</v-btn>
        </v-form><p v-else>Event read access and an active group are required to link meetings.</p>
      </v-card-text></v-card>
      <v-select v-model="meetingId" :items="meetingOptions" label="Group meeting" :disabled="busy" />
      <v-card v-if="meeting"><v-card-title>{{ meeting.occurrence.title }}</v-card-title><v-card-text>
        <p>{{ meeting.occurrence.startsAt }} · {{ meeting.occurrence.location }} · {{ meeting.meeting.status }}</p>
        <p class="my-3">{{ meeting.counts.present }} present · {{ meeting.counts.absent }} absent · {{ meeting.counts.excused }} excused · {{ meeting.counts.unrecorded }} unrecorded</p>
        <v-alert v-if="closed" class="mb-3" type="info" variant="tonal">This meeting is closed to new attendance. Existing entries retain their history and can be corrected.</v-alert>
        <v-btn v-if="overview.canManage && meeting.meeting.status === 'scheduled'" :disabled="busy" @click="cancelMeeting = true; reason = ''">Cancel group meeting</v-btn>
      </v-card-text><v-data-table :headers="attendanceHeaders" :items="meeting.roster">
        <template #item.status="{item}">{{ item.childPresent ? 'Present (child session)' : item.attendance?.status ?? 'Unrecorded' }}</template>
        <template #item.actions="{item}"><v-btn v-if="!closed || item.attendance" :disabled="busy" variant="text" @click="editAttendance(item)">{{ item.attendance ? 'Correct attendance' : 'Record attendance' }}</v-btn></template>
      </v-data-table></v-card>
    </template>
    <v-dialog :model-value="Boolean(editing) || cancelMeeting" max-width="550" persistent><v-card :title="cancelMeeting ? 'Cancel group meeting' : 'Meeting attendance'">
      <v-form ref="actionForm" @submit.prevent="saveAction"><v-card-text>
        <v-alert v-if="dialogError" class="mb-3" type="error">{{ dialogError }}</v-alert>
        <p v-if="editing">{{ editing.name }}</p>
        <v-select v-if="editing" v-model="status" :items="['present','absent','excused']" label="Attendance status" />
        <v-textarea v-if="cancelMeeting || editing?.attendance" v-model="reason" label="Reason for change" maxlength="1000" :rules="[required]" />
      </v-card-text><v-card-actions><v-btn :disabled="busy" @click="editing = null; cancelMeeting = false">Close</v-btn><v-btn color="primary" :loading="busy" type="submit">Save change</v-btn></v-card-actions></v-form>
    </v-card></v-dialog>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref, watch } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import { useAuthStore } from '@/stores/auth'
  import { useEventsStore } from '@/stores/events'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  interface Group {id: string, name: string, active: boolean}
  interface Attendance {id: string, status: string, version: number}
  interface Roster {personId: string, name: string, attendance: Attendance | null, childPresent: boolean}
  interface Meeting {meeting: {id: string, status: string, version: number}, occurrence: {title: string, startsAt: string, location: string, status: string}, roster: Roster[], counts: {present: number, absent: number, excused: number, unrecorded: number}}
  interface Overview {group: Group, members: {id: string, name: string, role: string, active: boolean, joinedOn: string, leftOn: string | null}[], meetings: Meeting[], canManage: boolean}
  const access = useAuthStore(); const events = useEventsStore()
  const groups = ref<Group[]>([]); const groupId = ref<string | null>(null); const overview = ref<Overview | null>(null)
  const meetingId = ref<string | null>(null); const eventId = ref<string | null>(null); const occurrenceId = ref<string | null>(null)
  const loading = ref(false); const busy = ref(false); const error = ref(''); const dialogError = ref('')
  const meetingForm = ref(); const actionForm = ref(); const editing = ref<Roster | null>(null); const cancelMeeting = ref(false)
  const reason = ref(''); const status = ref('present')
  const required = (value: unknown) => (typeof value === 'string' && Boolean(value.trim())) || 'This field is required.'
  const groupOptions = computed(() => groups.value.map(g => ({title: `${g.name}${g.active ? '' : ' · archived'}`, value:g.id})))
  const meetingOptions = computed(() => overview.value?.meetings.map(m => ({title:`${m.occurrence.startsAt} · ${m.occurrence.title}`, value:m.meeting.id})) ?? [])
  const meeting = computed(() => overview.value?.meetings.find(m => m.meeting.id === meetingId.value))
  const closed = computed(() => !overview.value?.group.active || meeting.value?.meeting.status === 'cancelled' || meeting.value?.occurrence.status === 'cancelled')
  const eventOptions = computed(() => events.events.map(e => ({title:e.title, value:e.id})))
  const occurrenceOptions = computed(() => events.events.find(e => e.id === eventId.value)?.occurrences.filter(o => o.status === 'scheduled').map(o => ({title:`${o.startsAt} · ${o.location}`, value:o.id})) ?? [])
  const memberHeaders = [{title:'Member', key:'name'}, {title:'Role', key:'role'}, {title:'Membership', key:'active'}, {title:'Joined', key:'joinedOn'}, {title:'Left', key:'leftOn'}]
  const attendanceHeaders = [{title:'Member', key:'name'}, {title:'Attendance', key:'status'}, {title:'', key:'actions', sortable:false}]
  let sequence = 0
  async function loadGroups () { error.value = ''; try {groups.value = (await useApi().get<{items:Group[]}>('/api/groups')).data.items; if (!groups.value.some(g => g.id === groupId.value)) groupId.value = groups.value[0]?.id ?? null; else await load()} catch (failure) {groups.value = []; overview.value = null; error.value = extractErrorMessage(failure, 'Unable to load your groups.')} }
  async function load () {
    const request = ++sequence
    if (!groupId.value) {overview.value = null; return}
    loading.value = true; error.value = ''
    try {const {data} = await useApi().get<Overview>(`/api/groups/${groupId.value}`); if (request !== sequence) return; overview.value = data; if (!data.meetings.some(m => m.meeting.id === meetingId.value)) meetingId.value = data.meetings[0]?.meeting.id ?? null}
    catch (failure) {if (request === sequence) {overview.value = null; error.value = extractErrorMessage(failure, 'Unable to load group.')}}
    finally {if (request === sequence) loading.value = false}
  }
  async function addMeeting () {if (busy.value || !(await meetingForm.value.validate()).valid) return; busy.value = true; error.value = ''; try {await useApi().post(`/api/groups/${groupId.value}/meetings`, {occurrenceId:occurrenceId.value}); await load()} catch (failure) {error.value = extractErrorMessage(failure, 'Unable to link meeting.')} finally {busy.value = false} }
  function editAttendance (row: Roster) {editing.value = row; status.value = row.childPresent ? 'present' : row.attendance?.status ?? 'present'; reason.value = ''; dialogError.value = ''}
  async function saveAction () {
    if (!meeting.value || busy.value || !(await actionForm.value.validate()).valid) return
    busy.value = true; dialogError.value = ''
    try {
      const base = `/api/groups/${groupId.value}/meetings/${meeting.value.meeting.id}`
      if (cancelMeeting.value) await useApi().post(`${base}/cancel`, {expectedVersion:meeting.value.meeting.version, reason:reason.value})
      else if (editing.value) await useApi().post(`${base}/attendance`, {personId:editing.value.personId, status:status.value, expectedVersion:editing.value.attendance?.version ?? null, reason:editing.value.attendance ? reason.value : null})
      editing.value = null; cancelMeeting.value = false; await load()
    } catch (failure) {dialogError.value = extractErrorMessage(failure, 'Unable to save change. Reload before retrying a conflict.')} finally {busy.value = false}
  }
  watch(groupId, () => {overview.value = null; meetingId.value = null; editing.value = null; cancelMeeting.value = false; void load()})
  onMounted(async () => {await loadGroups(); if (access.hasPermission('ministries.manage') && access.hasPermission('events.read')) try {await events.fetchAll()} catch (failure) {error.value = extractErrorMessage(failure, 'Unable to load meeting events.')} })
</script>
