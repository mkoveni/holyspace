<template>
  <MinistryDialog
    v-model="open"
    :save-label="`Create ${includedCount} ${includedCount === 1 ? 'session' : 'sessions'}`"
    :submit="save"
    :submit-disabled="!fresh || blocked || previewTask.busy.value || !preview"
    subtitle="Repeat a session’s class, room, seats and assigned team across a planned weekly series."
    title="A rhythm of belonging"
    @reload="refreshSource"
    @saved="saved"
  >
    <template v-if="source">
      <div class="children-form-note mb-5">
        <strong>{{ source.className }} · {{ source.roomName }}</strong>
        <p>{{ source.session.seatLimit }} seats · {{ source.timeZone }}</p>
      </div>

      <v-text-field
        v-model="name"
        counter
        label="Series name *"
        maxlength="120"
        :rules="[required]"
      />

      <DateTimeSelector
        v-model="startsLocal"
        label="First start (location time) *"
        :rules="[required]"
      />

      <DateTimeSelector
        v-model="endsLocal"
        label="First end (location time) *"
        :rules="[required]"
      />

      <div class="series-pattern">
        <v-select
          v-model="intervalWeeks"
          item-title="label"
          item-value="value"
          :items="intervals"
          label="Repeat every *"
        />

        <v-text-field
          v-model.number="occurrences"
          label="Number of dates *"
          max="52"
          min="1"
          :rules="[positive]"
          type="number"
        />
      </div>

      <p class="directory-muted mb-4">
        Every repeat uses the same local weekday and time in
        {{ source.timeZone }}. Plan up to 52 dates within 366 days.
      </p>

      <v-alert
        v-if="localError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ localError }}
      </v-alert>

      <TaskAlert
        :conflict="previewTask.conflict.value"
        :error="previewTask.error.value"
        @reload="refreshSource"
      />

      <v-btn
        class="mb-5"
        color="primary"
        :loading="previewTask.busy.value"
        prepend-icon="mdi-calendar-check-outline"
        variant="tonal"
        @click="review"
      >
        {{ preview ? 'Refresh preview' : 'Preview dates & team' }}
      </v-btn>

      <template v-if="preview">
        <v-alert
          class="mb-4"
          type="info"
          variant="tonal"
        >
          {{ preview.team.length }}
          {{ preview.team.length === 1 ? 'team member' : 'team members' }} will
          be assigned to each included session, with presence to be confirmed on
          the day. Opening still requires a qualified present leader and team.
        </v-alert>

        <p
          v-if="preview.team.length > 0"
          class="directory-muted mb-3"
        >
          {{
            preview.team
              .map((member) => `${member.name} (${member.role.toLowerCase()})`)
              .join(' · ')
          }}
        </p>

        <v-alert
          v-if="!fresh"
          class="mb-4"
          type="warning"
          variant="tonal"
        >
          The plan has changed. Refresh the preview before creating the series.
        </v-alert>

        <v-alert
          v-else-if="blocked"
          class="mb-4"
          type="warning"
          variant="tonal"
        >
          Resolve or skip conflicting dates, then refresh the preview. The
          series is saved together.
        </v-alert>

        <div
          aria-label="Recurring dates preview"
          class="series-preview"
        >
          <article
            v-for="occurrence in preview.occurrences"
            :key="occurrence.slot.date"
            :class="{
              'series-preview--skipped': skipped.includes(occurrence.slot.date),
            }"
          >
            <v-checkbox
              :aria-label="`Include ${occurrence.slot.date}`"
              hide-details
              :model-value="!skipped.includes(occurrence.slot.date)"
              @update:model-value="includeDate(occurrence.slot.date, !!$event)"
            />

            <div>
              <strong>{{ dateLabel(occurrence.slot.date) }}</strong>

              <p>
                {{ timeLabel(occurrence.slot.startsAt, preview.timeZone) }} –
                {{ clockLabel(occurrence.slot.endsAt, preview.timeZone) }}
              </p>

              <small v-if="skipped.includes(occurrence.slot.date)">
                Skipped
              </small>

              <template v-else>
                <p
                  v-for="issue in occurrence.conflicts"
                  :key="issue"
                  class="series-conflict"
                >
                  {{ issue }}
                </p>

                <p
                  v-for="warning in occurrence.staffingWarnings"
                  :key="warning"
                  class="series-warning"
                >
                  {{ warning }}
                </p>
              </template>
            </div>
          </article>
        </div>
      </template>
    </template>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type {
    SessionSeriesDraft,
    SessionSeriesPreview,
    SessionView,
  } from '../types'
  import { computed, ref, watch } from 'vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { childrenApi } from '../api'
  import { dateLabel, positive, required, timeLabel } from '../presentation'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ source: SessionView | null }>()
  const emit = defineEmits<{ created: [id: string], reload: [] }>()
  const name = ref('')
  const startsLocal = ref('')
  const endsLocal = ref('')
  const intervalWeeks = ref(1)
  const occurrences = ref(12)
  const skipped = ref<string[]>([])
  const preview = ref<SessionSeriesPreview | null>(null)
  const reviewed = ref('')
  const previewTask = useTask({ preserveConflictMessage: true })
  const localError = ref('')
  const requestId = ref('')
  const createdId = ref('')
  const intervals = [1, 2, 3, 4].map(value => ({
    value,
    label: value === 1 ? 'Week' : `${value} weeks`,
  }))
  const draft = computed<SessionSeriesDraft>(() => ({
    name: name.value,
    sourceSessionId: props.source?.session.id || '',
    sourceVersion: props.source?.session.version || 0,
    recurrence: {
      firstDate: startsLocal.value.slice(0, 10),
      startsAt: startsLocal.value.slice(11, 16),
      endsAt: endsLocal.value.slice(11, 16),
      intervalWeeks: intervalWeeks.value,
      occurrences: occurrences.value,
      excludedDates: skipped.value,
    },
  }))
  const signature = computed(() =>
    JSON.stringify({
      draft: draft.value,
      endDate: endsLocal.value.slice(0, 10),
    }),
  )
  const fresh = computed(
    () => !!preview.value && reviewed.value === signature.value,
  )
  const includedCount = computed(() => occurrences.value - skipped.value.length)
  const blocked = computed(
    () => !!preview.value?.occurrences.some(value => value.conflicts.length),
  )
  watch(signature, () => {
    requestId.value = crypto.randomUUID()
    localError.value = ''
  })
  watch(
    [() => startsLocal.value.slice(0, 10), intervalWeeks, occurrences],
    () => {
      skipped.value = []
    },
  )
  watch(startsLocal, value => {
    if (endsLocal.value)
      endsLocal.value = value.slice(0, 10) + endsLocal.value.slice(10)
  })
  function local (instant: string, zone: string) {
    return new Intl.DateTimeFormat('sv-SE', {
      timeZone: zone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hourCycle: 'h23',
    })
      .format(new Date(instant))
      .replace(' ', 'T')
  }
  function clockLabel (instant: string, zone: string) {
    return new Intl.DateTimeFormat(undefined, {
      timeZone: zone,
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(instant))
  }
  watch(open, value => {
    if (!value || !props.source) return
    const source = props.source
    name.value = `${source.className} weekly sessions`.slice(0, 120)
    const start = local(source.session.startsAt, source.timeZone)
    const end = local(source.session.endsAt, source.timeZone)
    const day = new Date(start.slice(0, 10) + 'T12:00:00Z')
    const today = local(new Date().toISOString(), source.timeZone).slice(0, 10)
    do {
      day.setUTCDate(day.getUTCDate() + 7)
    } while (day.toISOString().slice(0, 10) <= today)
    const first = day.toISOString().slice(0, 10)
    startsLocal.value = first + start.slice(10)
    endsLocal.value = first + end.slice(10)
    intervalWeeks.value = 1
    occurrences.value = 12
    skipped.value = []
    preview.value = null
    reviewed.value = ''
    localError.value = ''
    createdId.value = ''
    previewTask.clear()
    requestId.value = crypto.randomUUID()
  })
  function includeDate (date: string, include: boolean) {
    skipped.value = include
      ? skipped.value.filter(value => value !== date)
      : [...skipped.value, date]
  }
  function valid () {
    localError.value
      = !name.value.trim()
        || !startsLocal.value
        || !endsLocal.value
        || startsLocal.value.slice(0, 10) !== endsLocal.value.slice(0, 10)
        || draft.value.recurrence.endsAt <= draft.value.recurrence.startsAt
        || !Number.isInteger(occurrences.value)
        || occurrences.value < 1
        || occurrences.value > 52
        || (occurrences.value - 1) * intervalWeeks.value * 7 > 365
        || includedCount.value < 1
        ? 'Choose a named series with 1–52 dates within 366 days, keeping at least one session and an end time after the start on the same day.'
        : ''
    return !localError.value
  }
  async function review () {
    if (!valid()) return
    previewTask.clear()
    const requested = signature.value
    const result = await previewTask.run(() =>
      childrenApi.previewSeries(draft.value),
    )
    if (result && requested === signature.value && open.value) {
      preview.value = result.value
      reviewed.value = requested
    }
  }
  async function save () {
    if (!valid() || !fresh.value || blocked.value)
      throw new Error(
        'Refresh a conflict-free preview before creating the series.',
      )
    createdId.value = (
      await childrenApi.createSeries(requestId.value, draft.value)
    ).id
  }
  function saved () {
    emit('created', createdId.value)
  }
  function refreshSource () {
    previewTask.clear()
    reviewed.value = ''
    emit('reload')
  }
</script>
<style scoped>
  .series-pattern {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }
  .series-preview article {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 16px 0;
    border-top: 1px solid var(--workspace-border);
  }
  .series-preview article > :first-child {
    flex: 0 0 44px;
  }
  .series-preview article > div {
    min-width: 0;
  }
  .series-preview p {
    margin: 4px 0;
    overflow-wrap: anywhere;
  }
  .series-preview--skipped {
    opacity: 0.6;
  }
  .series-conflict {
    color: var(--workspace-muted);
  }
  .series-warning {
    color: var(--workspace-muted);
    font-size: 0.85rem;
  }
  @media (max-width: 500px) {
    .series-pattern {
      grid-template-columns: 1fr;
      gap: 0;
    }
  }
</style>
