<template>
  <section
    id="child-readiness"
    class="directory-surface children-section readiness-panel"
  >
    <div class="children-section__heading">
      <div>
        <span class="directory-eyebrow">ONE STEP CLOSER TO BELONGING</span>
        <h2 class="mt-3">Ready for their next visit</h2>
      </div>

      <v-icon
        :color="check.data.value?.ready ? 'success' : 'primary'"
        :icon="
          check.data.value?.ready
            ? 'mdi-check-decagram-outline'
            : 'mdi-list-status'
        "
        size="32"
      />
    </div>

    <p>
      Work through the essentials for this programme. Your progress updates as
      each step is saved.
    </p>

    <v-select
      v-if="programmes.length > 0"
      v-model="programmeId"
      class="mb-5"
      hide-details
      item-title="name"
      item-value="id"
      :items="programmes"
      label="Readiness programme"
    />

    <DirectoryEmpty
      v-else
      description="Create a programme before preparing this child for their first visit."
      title="Start with a programme"
    />

    <v-btn
      v-if="programmeId"
      class="mb-4"
      :loading="check.loading.value"
      prepend-icon="mdi-refresh"
      size="small"
      variant="text"
      @click="check.load"
    >Refresh readiness</v-btn>

    <v-alert
      v-if="check.error.value"
      type="error"
      variant="tonal"
    >
      {{ check.error.value }}
      <v-btn
        variant="text"
        @click="check.load"
      >
        Retry readiness check
      </v-btn>
    </v-alert>

    <v-skeleton-loader
      v-else-if="check.loading.value"
      type="list-item-two-line@3"
    />

    <template v-else-if="check.data.value">
      <div class="readiness-panel__summary">
        <div>
          <strong>
            {{ check.data.value.ready ? 'Ready to attend' : 'Needs attention' }}
          </strong>

          <span>
            {{ check.data.value.completed }} of
            {{ check.data.value.total }} essentials complete · Checked for
            {{ dateLabel(check.data.value.on) }}
          </span>
        </div>

        <v-progress-linear
          class="mt-4"
          :color="check.data.value.ready ? 'success' : 'primary'"
          height="6"
          :model-value="
            (check.data.value.completed / check.data.value.total) * 100
          "
          rounded
        />
      </div>

      <div
        v-for="step in steps"
        :key="step.code"
        class="readiness-panel__step"
      >
        <v-icon
          :color="step.complete ? 'success' : 'primary'"
          :icon="step.complete ? 'mdi-check-circle' : 'mdi-circle-outline'"
          size="24"
        />

        <div>
          <strong>{{ step.title }}</strong>
          <p>{{ step.complete ? step.done : step.description }}</p>

          <small v-if="!step.complete && !allowed(step)">
            {{ step.blocked }}
          </small>
        </div>

        <v-chip
          v-if="step.complete"
          color="success"
          size="small"
        >
          Complete
        </v-chip>

        <v-btn
          v-else-if="allowed(step)"
          size="small"
          :to="
            step.code === 'IDENTITY'
              ? `/people/${childId}`
              : step.code === 'POLICY'
                ? `/children/programmes/${programmeId}`
                : undefined
          "
          variant="tonal"
          @click="act(step)"
        >
          {{ step.action }}
        </v-btn>
      </div>

      <div
        v-if="
          auth.hasPermission('children.support') &&
            auth.hasPermission('children.manage')
        "
        class="readiness-panel__care"
      >
        <div>
          <strong>
            Care, with understanding
            <span class="directory-muted">· Optional</span>
          </strong>

          <p>
            Review allergies, care instructions or other needs when relevant.
            Care records do not determine readiness.
          </p>
        </div>

        <v-btn
          size="small"
          variant="text"
          @click="$emit('care')"
        >
          Review care information
        </v-btn>
      </div>

      <p class="children-form-note mt-5">
        This is a preparation check for today in
        {{ check.data.value.timeZone }}. Session check-in still verifies age,
        permissions through the session’s end, staffing and available places.
      </p>
    </template>
  </section>
</template>
<script setup lang="ts">
  import type { Enrolment, Programme, ReadinessStepCode } from '../types'
  import { computed, ref, watch } from 'vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import { dateLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = defineProps<{
    childId: string
    programmes: Programme[]
    defaultProgramme: string
    revision: number
  }>()
  const emit = defineEmits<{
    'action': [
      value: {
        mode: 'enrol' | 'assign' | 'guardian' | 'consent' | 'pickup'
        programmeId: string
        enrolment: Enrolment | null
      },
    ]
    'programme-change': [id: string]
    'care': []
  }>()
  const auth = useAuthStore()
  const programmeId = ref('')
  const check = useLoad(
    () =>
      programmeId.value
        ? childrenApi.readiness(programmeId.value, props.childId)
        : Promise.resolve(null),
    null,
  )
  const descriptions = {
    IDENTITY: {
      title: 'Confirm child details',
      description:
        'A living, available child with a known birth date under 18 is required.',
      done: 'Child details and age are eligible.',
      action: 'Review People profile',
      permission: 'people.manage',
      blocked:
        'Ask a People administrator to review the birth date and person status.',
    },
    ENROLMENT: {
      title: 'Enrol in this programme',
      description: 'Record an enrolment covering today.',
      done: 'A current enrolment is in place.',
      action: 'Enrol in programme',
      permission: 'children.manage',
      blocked: 'A programme coordinator can record enrolment.',
    },
    CLASS: {
      title: 'Find their class',
      description:
        'Choose a current class placement that fits their age and enrolment.',
      done: 'An age-appropriate class placement is current.',
      action: 'Place in class',
      permission: 'children.manage',
      blocked:
        'Confirm child details and current enrolment first. A coordinator can then choose a class.',
    },
    POLICY: {
      title: 'Publish the programme policy',
      description: 'Families need a published consent policy to agree to.',
      done: 'The programme has a published policy.',
      action: 'Open programme policy',
      permission: 'children.manage',
      blocked: 'A programme coordinator needs to publish the consent policy.',
    },
    GUARDIAN: {
      title: 'Verify a guardian',
      description: 'Record evidenced guardian authority that is valid today.',
      done: 'A verified guardian authority is current.',
      action: 'Verify guardian',
      permission: 'children.safeguarding',
      blocked: 'A safeguarding coordinator can verify guardian authority.',
    },
    CONSENT: {
      title: 'Record family consent',
      description:
        'Record consent to the current policy from a guardian with valid authority.',
      done: 'Current policy consent and guardian authority are valid.',
      action: 'Record consent',
      permission: 'children.safeguarding',
      blocked:
        'Publish a policy and verify a guardian first. A safeguarding coordinator can record consent.',
    },
    COLLECTOR: {
      title: 'Authorize a collector',
      description:
        'Record a current pickup authorization for an available adult, backed by valid guardian authority.',
      done: 'An authorized adult collector is current.',
      action: 'Authorize collector',
      permission: 'children.safeguarding',
      blocked:
        'Verify a guardian first. A safeguarding coordinator can authorize collection.',
    },
  }
  const steps = computed(() =>
    (check.data.value?.steps || []).map(step => ({
      ...step,
      ...descriptions[step.code],
    })),
  )
  type Step = (typeof steps.value)[number]
  function complete (code: ReadinessStepCode) {
    return (
      check.data.value?.steps.some(
        step => step.code === code && step.complete,
      ) ?? false
    )
  }
  function allowed (step: Step) {
    if (!auth.hasPermission(step.permission)) return false
    if (step.code === 'CLASS')
      return complete('ENROLMENT') && complete('IDENTITY')
    if (step.code === 'CONSENT')
      return complete('GUARDIAN') && complete('POLICY')
    if (step.code === 'COLLECTOR') return complete('GUARDIAN')
    return true
  }
  function act (step: Step) {
    const mode = {
      ENROLMENT: 'enrol',
      CLASS: 'assign',
      GUARDIAN: 'guardian',
      CONSENT: 'consent',
      COLLECTOR: 'pickup',
    } as const
    if (step.code === 'IDENTITY' || step.code === 'POLICY' || !allowed(step))
      return
    emit('action', {
      mode: mode[step.code],
      programmeId: programmeId.value,
      enrolment: check.data.value?.enrolment ?? null,
    })
  }
  watch(programmeId, id => {
    emit('programme-change', id)
    void check.load()
  })
  watch(
    [() => props.childId, () => props.programmes, () => props.defaultProgramme],
    () => {
      if (
        !props.programmes.some(
          programme => programme.id === programmeId.value,
        )
      ) {
        const preferred = props.programmes.find(
          programme => programme.id === props.defaultProgramme,
        )
        programmeId.value = preferred?.id || props.programmes[0]?.id || ''
      }
    },
    { immediate: true },
  )
  watch(
    () => props.defaultProgramme,
    (id, previous) => {
      if (
        id !== previous
        && props.programmes.some(programme => programme.id === id)
      )
        programmeId.value = id
    },
  )
  watch([() => props.revision, () => props.childId], check.load)
</script>
<style scoped>
  .readiness-panel__summary {
    padding: 20px;
    border-radius: 16px;
    background: var(--workspace-hover);
    margin-bottom: 12px;
  }
  .readiness-panel__summary strong {
    display: block;
    font-size: 1.15rem;
    color: var(--workspace-blue);
  }
  .readiness-panel__summary span {
    display: block;
    color: var(--workspace-muted);
    font-size: 0.8rem;
    margin-top: 5px;
  }
  .readiness-panel__step {
    display: flex;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    padding: 20px 0;
    border-bottom: 1px solid var(--workspace-border);
  }
  .readiness-panel__step > div {
    flex: 1 1 220px;
  }
  .readiness-panel__step strong {
    font-size: 0.9rem;
  }
  .readiness-panel__step p,
  .readiness-panel__care p {
    font-size: 0.8rem;
    color: var(--workspace-muted);
    line-height: 1.65;
    margin: 5px 0;
  }
  .readiness-panel__step small {
    color: var(--workspace-muted);
  }
  .readiness-panel__care {
    margin-top: 24px;
    padding: 18px;
    border: 1px dashed var(--workspace-border);
    border-radius: 16px;
  }
</style>
