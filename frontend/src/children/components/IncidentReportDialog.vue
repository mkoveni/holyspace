<template>
  <MinistryDialog
    v-model="open"
    save-label="Record incident"
    :submit="save"
    subtitle="A clear account helps the right people follow up. Private details stay within the incident team."
    title="Record what happened"
    @reload="loadVisits"
    @saved="emit('saved')"
  >
    <v-alert
      class="mb-5"
      type="info"
      variant="tonal"
    >
      Recording an incident does not contact a guardian or replace an urgent
      response.
    </v-alert>

    <v-autocomplete
      v-if="!sessionId"
      v-model="chosenSession"
      :error-messages="sessionError"
      item-title="label"
      item-value="id"
      :items="sessionItems"
      label="Session *"
      :rules="[required]"
    />

    <v-text-field
      v-model="search"
      label="Find a child who attended"
      prepend-inner-icon="mdi-magnify"
      @update:model-value="findVisits"
    />

    <v-select
      v-model="segmentId"
      :error-messages="visits.error.value"
      item-title="label"
      item-value="id"
      :items="visitItems"
      label="Child's attendance *"
      :loading="visits.loading.value"
      :rules="[required]"
    />

    <div class="d-flex ga-2 mb-4">
      <v-btn
        :disabled="!offset"
        variant="text"
        @click="page(-25)"
      >
        Previous children
      </v-btn>

      <v-btn
        :disabled="offset + 25 >= visits.data.value.total"
        variant="text"
        @click="page(25)"
      >
        More children
      </v-btn>
    </div>

    <v-select
      v-model="category"
      item-title="label"
      item-value="value"
      :items="categories"
      label="Incident type *"
    />

    <v-select
      v-model="severity"
      item-title="label"
      item-value="value"
      :items="severities"
      label="Severity *"
    />

    <DateTimeSelector
      v-model="occurredAt"
      label="When it happened *"
      :rules="[required]"
    />

    <v-textarea
      v-model="description"
      label="What happened *"
      maxlength="5000"
      :rules="[required]"
    />

    <v-textarea
      v-model="immediateAction"
      label="Immediate action taken *"
      maxlength="3000"
      :rules="[required]"
    />

    <DateSelector
      v-model="reviewOn"
      label="Review by *"
      :rules="[required]"
    />

    <DateSelector
      v-model="retainUntil"
      label="Keep private details until *"
      :rules="[required]"
    />
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type { IncidentVisit } from '../types'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { localDateTimeValue } from '@/components/common/date-time/value'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { churchToday } from '@/directory/presentation'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ sessionId?: string }>()
  const emit = defineEmits<{ saved: [] }>()
  const auth = useAuthStore()
  const chosenSession = ref(''),
        sessionItems = ref<{ id: string, label: string }[]>([]),
        sessionError = ref('')
  const activeSession = computed(() => props.sessionId || chosenSession.value)
  const search = ref(''),
        offset = ref(0),
        segmentId = ref('')
  const visits = useLoad(
    () =>
      activeSession.value
        ? childrenApi.incidentVisits(
          activeSession.value,
          search.value,
          offset.value,
        )
        : Promise.resolve({ visits: [] as IncidentVisit[], total: 0 }),
    { visits: [] as IncidentVisit[], total: 0 },
  )
  const visitItems = computed(() =>
    visits.data.value.visits.map(row => ({
      id: row.visit.segmentId,
      label: `${row.childName} · ${timeLabel(row.visit.arrivedAt)}`,
    })),
  )
  const categories = [
    { value: 'INJURY', label: 'Injury' },
    { value: 'ILLNESS', label: 'Illness' },
    { value: 'BEHAVIOUR', label: 'Behaviour' },
    { value: 'SAFEGUARDING', label: 'Safeguarding concern' },
    { value: 'OTHER', label: 'Other' },
  ]
  const severities = [
    { value: 'LOW', label: 'Low' },
    { value: 'MODERATE', label: 'Moderate' },
    { value: 'HIGH', label: 'High' },
    { value: 'CRITICAL', label: 'Critical' },
  ]
  const exactDefaultTime = ref('')
  const category = ref('INJURY'),
        severity = ref('LOW'),
        occurredAt = ref(''),
        description = ref(''),
        immediateAction = ref(''),
        reviewOn = ref(''),
        retainUntil = ref(''),
        requestId = ref('')
  let timer: ReturnType<typeof setTimeout> | undefined
  function loadVisits () {
    segmentId.value = ''
    void visits.load()
  }
  function findVisits () {
    clearTimeout(timer)
    timer = setTimeout(() => {
      offset.value = 0
      loadVisits()
    }, 250)
  }
  function page (delta: number) {
    offset.value += delta
    loadVisits()
  }
  watch(activeSession, () => {
    offset.value = 0
    loadVisits()
  })
  watch(open, async value => {
    if (!value) return
    requestId.value = crypto.randomUUID()
    search.value = ''
    offset.value = 0
    segmentId.value = ''
    description.value = ''
    immediateAction.value = ''
    category.value = 'INJURY'
    severity.value = 'LOW'
    exactDefaultTime.value = new Date().toISOString()
    occurredAt.value = localDateTimeValue(new Date(exactDefaultTime.value))
    reviewOn.value = churchToday('Africa/Johannesburg')
    const retention = new Date()
    retention.setUTCFullYear(retention.getUTCFullYear() + 1)
    retainUntil.value = retention.toISOString().slice(0, 10)
    loadVisits()
    if (
      !props.sessionId
      && (auth.hasPermission('children.read')
        || auth.hasPermission('children.station'))
    ) {
      try {
        const until = new Date(Date.now() + 86_400_000).toISOString(),
              from = new Date(Date.now() - 90 * 86_400_000).toISOString()
        sessionItems.value = (
          await childrenApi.sessions(
            from,
            until,
            0,
            !auth.hasPermission('children.read'),
          )
        ).map(row => ({
          id: row.session.id,
          label: `${row.className} · ${timeLabel(row.session.startsAt, row.timeZone)}`,
        }))
      } catch {
        sessionError.value = 'Open the relevant session to record its incident.'
      }
    }
  })
  async function save () {
    const selected = visits.data.value.visits.find(
      row => row.visit.segmentId === segmentId.value,
    )
    if (!selected)
      throw new Error('Select an attendance record from the current results.')
    await childrenApi.reportIncident({
      requestId: requestId.value,
      childId: selected.visit.childId,
      sessionId: activeSession.value,
      segmentId: segmentId.value,
      category: category.value,
      severity: severity.value,
      occurredAt:
        occurredAt.value
        === localDateTimeValue(new Date(exactDefaultTime.value))
          ? exactDefaultTime.value
          : new Date(occurredAt.value).toISOString(),
      description: description.value,
      immediateAction: immediateAction.value,
      reviewOn: reviewOn.value,
      retainUntil: retainUntil.value,
    })
  }
  onBeforeUnmount(() => clearTimeout(timer))
</script>
