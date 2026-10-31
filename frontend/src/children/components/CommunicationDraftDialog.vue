<template>
  <MinistryDialog
    v-model="open"
    save-label="Prepare message"
    :submit="save"
    subtitle="Prepare a consent-renewal request or session reminder for a verified guardian. Nothing is sent automatically."
    title="A thoughtful message home"
    @reload="loadGuardians"
    @saved="emit('saved')"
  >
    <PersonPicker
      v-model="childId"
      age-category="CHILD"
      :initial-person="initialChild || undefined"
      label="Child *"
    />

    <ProgrammePicker
      v-model="programmeId"
      label="Programme *"
    />

    <v-select
      v-model="type"
      item-title="label"
      item-value="value"
      :items="types"
      label="Message type *"
    />

    <v-select
      v-model="authorityId"
      :error-messages="guardians.error.value"
      item-title="name"
      item-value="id"
      :items="guardians.data.value"
      label="Verified guardian *"
      :loading="guardians.loading.value"
      :rules="[required]"
    />

    <template v-if="type === 'SESSION_REMINDER'">
      <v-select
        v-model="sessionId"
        :error-messages="sessionError"
        item-title="label"
        item-value="id"
        :items="sessionItems"
        label="Upcoming session *"
        :rules="[required]"
      />

      <v-btn
        v-if="moreSessions"
        variant="text"
        @click="loadMoreSessions"
      >
        More upcoming sessions
      </v-btn>
    </template>

    <DateSelector
      v-model="dueOn"
      label="Contact by *"
      :rules="[required]"
    />

    <p class="directory-muted">
      The message uses approved wording. Record contact only after you have
      spoken to the guardian or sent the message through your usual channel.
      Consent must still be recorded separately.
    </p>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { PersonSummary } from '@/directory/types'
  import { ref, watch } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { directoryApi } from '@/directory/api'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { churchToday, fullName } from '@/directory/presentation'
  import { childrenApi } from '../api'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  import MinistryDialog from './MinistryDialog.vue'
  import ProgrammePicker from './ProgrammePicker.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    defaultChild?: string
    defaultProgramme?: string
  }>()
  const emit = defineEmits<{ saved: [] }>()
  const initialChild = ref<PersonSummary | null>(null)
  const childId = ref<string | null>(null),
        programmeId = ref<string | null>(null),
        authorityId = ref<string | null>(null),
        sessionId = ref<string | null>(null)
  const type = ref('CONSENT_RENEWAL'),
        dueOn = ref(''),
        requestId = ref('')
  const types = [
    { value: 'CONSENT_RENEWAL', label: 'Consent renewal request' },
    { value: 'SESSION_REMINDER', label: 'Session reminder' },
  ]
  const guardians = useLoad(
    async () => {
      if (!childId.value) return []
      const facts = await childrenApi.safeguarding(childId.value)
      const now = Date.now()
      const current = facts.authorities.filter(
        a =>
          !a.revokedAt
          && Date.parse(a.validFrom) <= now
          && Date.parse(a.validUntil) > now,
      )
      return Promise.all(
        current.map(async a => ({
          id: a.id,
          name: fullName(await directoryApi.person(a.guardianId)),
        })),
      )
    },
    [] as { id: string, name: string }[],
  )
  function loadGuardians () {
    authorityId.value = null
    void guardians.load()
  }
  watch(childId, loadGuardians)
  const sessionItems = ref<{ id: string, label: string }[]>([]),
        sessionError = ref(''),
        moreSessions = ref(false),
        sessionOffset = ref(0)
  async function loadSessions () {
    sessionError.value = ''
    if (!programmeId.value) return
    try {
      const groups = await childrenApi.classes(programmeId.value)
      const rows = await childrenApi.sessions(
        new Date().toISOString(),
        new Date(Date.now() + 90 * 86_400_000).toISOString(),
        sessionOffset.value,
      )
      moreSessions.value = rows.length === 25
      const values = rows
        .filter(
          row =>
            groups.some(g => g.id === row.session.classId)
            && !['CLOSED', 'CANCELLED'].includes(row.session.status),
        )
        .map(row => ({
          id: row.session.id,
          label: `${row.className} · ${timeLabel(row.session.startsAt, row.timeZone)}`,
        }))
      sessionItems.value = sessionOffset.value
        ? [...sessionItems.value, ...values]
        : values
    } catch {
      sessionError.value
        = 'Unable to load upcoming sessions. Try the programme again.'
    }
  }
  function loadMoreSessions () {
    sessionOffset.value += 25
    void loadSessions()
  }
  watch([programmeId, type], () => {
    sessionId.value = null
    sessionOffset.value = 0
    sessionItems.value = []
    if (open.value && type.value === 'SESSION_REMINDER') void loadSessions()
  })
  watch(open, async value => {
    if (!value) return
    requestId.value = crypto.randomUUID()
    childId.value = props.defaultChild || null
    programmeId.value = props.defaultProgramme || null
    authorityId.value = null
    sessionId.value = null
    type.value = 'CONSENT_RENEWAL'
    dueOn.value = churchToday('Africa/Johannesburg')
    loadGuardians()
    initialChild.value = null
    if (childId.value) {
      try {
        initialChild.value = await directoryApi.person(childId.value)
      } catch {
        initialChild.value = null
      }
    }
  })
  async function save () {
    if (!childId.value || !programmeId.value)
      throw new Error('Choose a child and programme.')
    await childrenApi.prepareCommunication({
      requestId: requestId.value,
      childId: childId.value,
      authorityId: authorityId.value,
      programmeId: programmeId.value,
      sessionId: type.value === 'SESSION_REMINDER' ? sessionId.value : null,
      type: type.value,
      dueOn: dueOn.value,
    })
  }
</script>
