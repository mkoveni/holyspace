<template>
  <MinistryDialog
    v-model="open"
    save-label="Apply team plan"
    :submit="save"
    :submit-disabled="
      !fresh || !preview || preview.blockers.length > 0 || previewTask.busy.value
    "
    subtitle="Add or replace one worker across selected future planned sessions, with capacity and clashes reviewed first."
    title="Plan the people behind the welcome"
    @reload="emit('reload')"
    @saved="emit('saved')"
  >
    <p class="children-form-note mb-5">
      {{ sessions.length }} selected sessions. Planned assignments keep presence
      to be confirmed on the day.
    </p>

    <v-select
      v-model="mode"
      item-title="label"
      item-value="value"
      :items="[
        { value: 'assign', label: 'Assign or update a worker' },
        { value: 'replace', label: 'Replace an assigned worker' },
      ]"
      label="Change *"
    />

    <v-select
      v-if="mode === 'replace'"
      v-model="replaceWorkerId"
      hint="Choose someone assigned to every selected session."
      item-title="name"
      item-value="workerId"
      :items="commonWorkers"
      label="Person to replace *"
      persistent-hint
      :rules="[required]"
    />

    <v-autocomplete
      v-model="workerId"
      clearable
      :error-messages="workers.error.value"
      item-title="name"
      item-value="id"
      :items="workerItems"
      label="Registered worker *"
      :loading="workers.loading.value"
      no-filter
      :rules="[required]"
      @update:search="findWorkers"
    />

    <v-select
      v-model="role"
      item-title="label"
      item-value="value"
      :items="[
        { value: 'LEADER', label: 'Leader' },
        { value: 'ASSISTANT', label: 'Assistant' },
      ]"
      label="Planned role *"
    />

    <v-textarea
      v-model="reason"
      label="Planning reason *"
      maxlength="500"
      :rules="[required]"
    />

    <p class="directory-muted mb-4">
      Register workers and renew their clearance in Spaces & team. Clearance
      warnings allow advance planning; sessions still need qualified present
      staff before opening.
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
      @reload="emit('reload')"
    />

    <v-btn
      color="primary"
      :loading="previewTask.busy.value"
      variant="tonal"
      @click="review"
    >
      Preview team changes
    </v-btn>

    <template v-if="preview">
      <v-alert
        v-if="!fresh"
        class="mt-4"
        type="warning"
        variant="tonal"
      >
        The plan has changed. Preview it again before applying.
      </v-alert>

      <v-alert
        v-for="blocker in preview.blockers"
        :key="blocker"
        class="mt-4"
        type="error"
        variant="tonal"
      >
        {{ blocker }}
      </v-alert>

      <article
        v-for="row in preview.sessions"
        :key="row.session.id"
        class="team-plan-preview"
      >
        <h3>{{ row.ministryClass }} · {{ row.room }}</h3>
        <p>{{ timeLabel(row.session.startsAt, row.timeZone) }}</p>

        <strong>
          Expected capacity: {{ row.plannedCapacity }} /
          {{ row.session.seatLimit }} seats
        </strong>

        <ul v-if="row.issues.length > 0">
          <li
            v-for="issue in row.issues"
            :key="issue"
          >
            {{ issue }}
          </li>
        </ul>

        <p
          v-else
          class="text-success"
        >
          The planned team meets the staffing requirements.
        </p>
      </article>
    </template>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type {
    PlanningSession,
    PlanningWorker,
    TeamPlanDraft,
    TeamPlanPreview,
  } from '../types'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { childrenApi } from '../api'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  import MinistryDialog from './MinistryDialog.vue'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{ sessions: PlanningSession[] }>()
  const emit = defineEmits<{ saved: [], reload: [] }>()
  const mode = ref('assign')
  const workerId = ref<string | null>(null)
  const replaceWorkerId = ref<string | null>(null)
  const role = ref<'LEADER' | 'ASSISTANT'>('ASSISTANT')
  const reason = ref('')
  const workerSearch = ref('')
  const selectedWorker = ref<PlanningWorker | null>(null)
  const workers = useLoad(
    () => childrenApi.planningWorkers(workerSearch.value),
    { workers: [] as PlanningWorker[], total: 0 },
  )
  const workerItems = computed(() =>
    selectedWorker.value
    && !workers.data.value.workers.some(
      value => value.id === selectedWorker.value?.id,
    )
      ? [...workers.data.value.workers, selectedWorker.value]
      : workers.data.value.workers,
  )
  const commonWorkers = computed(
    () =>
      props.sessions[0]?.team.filter(member =>
        props.sessions.every(row =>
          row.team.some(value => value.workerId === member.workerId),
        ),
      ) || [],
  )
  const draft = computed<TeamPlanDraft>(() => ({
    targets: props.sessions.map(row => ({
      sessionId: row.session.id,
      version: row.session.version,
    })),
    workerId: workerId.value || '',
    replaceWorkerId: mode.value === 'replace' ? replaceWorkerId.value : null,
    role: role.value,
  }))
  const signature = computed(() => JSON.stringify(draft.value))
  const preview = ref<TeamPlanPreview | null>(null)
  const reviewed = ref('')
  const fresh = computed(() => reviewed.value === signature.value)
  const previewTask = useTask({ preserveConflictMessage: true })
  const localError = ref('')
  let timer: ReturnType<typeof setTimeout> | undefined
  function findWorkers (value: string) {
    clearTimeout(timer)
    workerSearch.value = value
    timer = setTimeout(() => {
      if (open.value) void workers.load()
    }, 250)
  }
  watch(workerId, id => {
    selectedWorker.value
      = workerItems.value.find(value => value.id === id) || null
  })
  watch(open, value => {
    if (!value) return
    mode.value = 'assign'
    workerId.value = null
    replaceWorkerId.value = null
    role.value = 'ASSISTANT'
    reason.value = ''
    preview.value = null
    reviewed.value = ''
    previewTask.clear()
    localError.value = ''
    workerSearch.value = ''
    void workers.load()
  })
  async function review () {
    if (
      !workerId.value
      || (mode.value === 'replace' && !replaceWorkerId.value)
    ) {
      localError.value
        = 'Choose a registered worker and the person to replace, when needed.'
      return
    }
    localError.value = ''
    previewTask.clear()
    const requested = signature.value
    const result = await previewTask.run(() =>
      childrenApi.previewTeamPlan(draft.value),
    )
    if (result && open.value && requested === signature.value) {
      preview.value = result.value
      reviewed.value = requested
    }
  }
  async function save () {
    if (!fresh.value || !preview.value || preview.value.blockers.length > 0)
      throw new Error('Review an available team plan before applying it.')
    await childrenApi.applyTeamPlan(draft.value, reason.value)
  }
  onBeforeUnmount(() => clearTimeout(timer))
</script>
<style scoped>
  .team-plan-preview {
    margin-top: 20px;
    padding: 20px 0;
    border-top: 1px solid var(--workspace-border);
  }
  .team-plan-preview p {
    margin: 6px 0;
    color: var(--workspace-muted);
  }
  .team-plan-preview ul {
    padding-left: 20px;
    margin-top: 12px;
    color: var(--workspace-muted);
  }
</style>
