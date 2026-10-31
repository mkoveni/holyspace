<template>
  <MinistryDialog
    v-model="open"
    :submit="save"
    :subtitle="childName"
    :title="titles[mode]"
    @reload="$emit('reload')"
    @saved="$emit('saved')"
  >
    <v-select
      v-if="['enrol', 'consent', 'pickup'].includes(mode)"
      v-model="programmeId"
      item-title="name"
      item-value="id"
      :items="programmes"
      label="Programme *"
      :rules="[required]"
    />

    <template v-if="mode === 'enrol'">
      <div class="children-form-note">
        Programme enrolment comes first. After saving, place this child in an
        age-appropriate class.
      </div>

      <v-row>
        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model="startDay"
            label="First day *"
            :rules="[required]"
            type="date"
          />
        </v-col>

        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model="endDay"
            label="Last day *"
            :rules="[required]"
            type="date"
          />
        </v-col>
      </v-row>
    </template>

    <template v-if="mode === 'assign'">
      <v-select
        v-model="enrolmentId"
        item-title="label"
        item-value="id"
        :items="activeEnrolments"
        label="Enrolment *"
        :rules="[required]"
      />

      <v-select
        v-model="classId"
        item-title="name"
        item-value="id"
        :items="classes"
        label="Class *"
        :loading="classLoading"
        :rules="[required]"
      />

      <v-alert
        v-if="classError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ classError }}
      </v-alert>

      <v-row>
        <v-col cols="6">
          <v-text-field
            v-model="startDay"
            label="First day *"
            :rules="[required]"
            type="date"
          />
        </v-col>

        <v-col cols="6">
          <v-text-field
            v-model="endDay"
            label="Last day *"
            :rules="[required]"
            type="date"
          />
        </v-col>
      </v-row>
    </template>

    <template v-if="mode === 'guardian'">
      <PersonPicker
        v-model="adultId"
        age-category="ADULT"
        class="mb-5"
        :exclude="[childId]"
        label="Guardian *"
        :rules="[required]"
      />

      <div class="children-form-note">
        Verify the evidence of this adult’s authority to act for this child. A
        family connection alone does not grant permission.
      </div>
    </template>

    <v-select
      v-if="mode === 'consent' || mode === 'pickup'"
      v-model="authorityId"
      item-title="label"
      item-value="id"
      :items="authorities"
      label="Verified guardian authority *"
      no-data-text="Verify a guardian first"
      :rules="[required]"
    />

    <template v-if="mode === 'consent'">
      <v-alert
        v-if="policyLoading"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Loading current policy…
      </v-alert>

      <div
        v-if="policy"
        class="children-form-note"
      >
        Consent is recorded for
        <strong>{{ policy.label }}</strong>
        . Confirm that the guardian accepted this exact document.
      </div>

      <v-alert
        v-if="policyError"
        class="mb-4"
        type="error"
        variant="tonal"
      >
        {{ policyError }}
      </v-alert>

      <v-checkbox
        v-model="acceptedNow"
        color="primary"
        label="Consent was accepted now"
      />

      <DateTimeSelector
        v-if="!acceptedNow"
        v-model="acceptedAt"
        label="Consent accepted at (local time) *"
        :rules="[required]"
      />
    </template>

    <PersonPicker
      v-if="mode === 'pickup'"
      v-model="adultId"
      age-category="ADULT"
      class="mb-5"
      :exclude="[childId]"
      label="Authorized collector *"
      :rules="[required]"
    />

    <template v-if="['guardian', 'consent', 'pickup'].includes(mode)">
      <v-row>
        <v-col
          cols="12"
          sm="6"
        >
          <DateTimeSelector
            v-model="validFrom"
            label="Valid from (local time) *"
            :rules="[required]"
          />
        </v-col>

        <v-col
          cols="12"
          sm="6"
        >
          <DateTimeSelector
            v-model="validUntil"
            label="Valid until (local time) *"
            :rules="[required]"
          />
        </v-col>
      </v-row>

      <v-text-field
        v-model="evidence"
        hint="Where the signed consent or verified evidence is securely held."
        label="Evidence link or reference *"
        maxlength="255"
        persistent-hint
        :rules="[required]"
      />
    </template>

    <template v-if="mode === 'support'">
      <div class="children-form-note">
        Only authorized care staff can access this record. Keep notes relevant
        to safe care, and choose when they should be reviewed and removed.
      </div>

      <v-textarea
        v-model="allergies"
        label="Allergies and medical alerts"
        maxlength="2000"
        rows="2"
      />

      <v-textarea
        v-model="instructions"
        label="Care instructions"
        maxlength="2000"
        rows="3"
      />

      <v-textarea
        v-model="notes"
        label="Restricted care notes"
        maxlength="2000"
        rows="3"
      />

      <v-row>
        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model="reviewDay"
            label="Review on *"
            :rules="[required]"
            type="date"
          />
        </v-col>

        <v-col
          cols="12"
          sm="6"
        >
          <v-text-field
            v-model="retainDay"
            label="Keep until *"
            :rules="[required]"
            type="date"
          />
        </v-col>
      </v-row>
    </template>

    <template v-if="mode === 'end' || mode === 'revoke'">
      <v-alert
        class="mb-5"
        type="warning"
        variant="tonal"
      >
        {{
          mode === 'end'
            ? 'Ending enrolment also ends its class placements. History is retained.'
            : 'This permission stops being usable immediately. Its history is retained.'
        }}
      </v-alert>

      <v-textarea
        v-if="mode === 'revoke'"
        v-model="reason"
        label="Reason *"
        maxlength="500"
        rows="3"
        :rules="[required]"
      />
    </template>
  </MinistryDialog>
</template>
<script setup lang="ts">
  import type {
    ConsentPolicy,
    Enrolment,
    MinistryClass,
    Programme,
    SafeguardingView,
    SupportRecord,
  } from '../types'
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import DateTimeSelector from '@/components/common/DateTimeSelector.vue'
  import { extractErrorMessage } from '@/composables/useApi'
  import PersonPicker from '@/directory/components/PersonPicker.vue'
  import { churchToday } from '@/directory/presentation'
  import { childrenApi } from '../api'
  import {
    dayAfter,
    instant,
    lastDay,
    localInput,
    required,
    validPeriod,
  } from '../presentation'
  import MinistryDialog from './MinistryDialog.vue'
  export type ParticipationMode
    = | 'enrol'
      | 'assign'
      | 'guardian'
      | 'consent'
      | 'pickup'
      | 'support'
      | 'end'
      | 'revoke'
  const open = defineModel<boolean>({ required: true })
  const props = defineProps<{
    mode: ParticipationMode
    childId: string
    childName: string
    programmes: Programme[]
    enrolments: Enrolment[]
    safeguarding: SafeguardingView | null
    names: Record<string, string>
    defaultProgramme?: string
    defaultEnrolment?: string
    support?: SupportRecord | null
    target?: {
      kind: 'guardian' | 'consent' | 'pickup' | 'enrolment' | 'assignment'
      id: string
      version: number
    } | null
  }>()
  defineEmits<{ saved: [], reload: [] }>()
  const titles = {
    enrol: 'Welcome to a programme',
    assign: 'Find their class',
    guardian: 'Verify a guardian',
    consent: 'Record family consent',
    pickup: 'Authorize a collector',
    support: 'Care, with understanding',
    end: 'End participation',
    revoke: 'Revoke permission',
  }
  const programmeId = ref('')
  const enrolmentId = ref('')
  const classId = ref('')
  const adultId = ref<string | null>(null)
  const authorityId = ref('')
  const startDay = ref('')
  const endDay = ref('')
  const validFrom = ref('')
  const validUntil = ref('')
  const acceptedAt = ref('')
  const acceptedNow = ref(true)
  const evidence = ref('')
  const reason = ref('')
  const allergies = ref('')
  const instructions = ref('')
  const notes = ref('')
  const reviewDay = ref('')
  const retainDay = ref('')
  const classes = ref<MinistryClass[]>([])
  const classLoading = ref(false)
  const classError = ref('')
  const policy = ref<ConsentPolicy | null>(null)
  const policyError = ref('')
  const policyLoading = ref(false)
  const activeEnrolments = computed(() =>
    props.enrolments
      .filter(value => !value.endedAt)
      .map(value => ({
        ...value,
        label:
          props.programmes.find(p => p.id === value.programmeId)?.name
          || 'Programme enrolment',
      })),
  )
  const authorities = computed(() =>
    (props.safeguarding?.authorities || [])
      .filter(
        value => !value.revokedAt && new Date(value.validUntil) > new Date(),
      )
      .map(value => ({
        ...value,
        label: props.names[value.guardianId] || 'Verified guardian',
      })),
  )
  let classSequence = 0
  let policySequence = 0
  watch(open, value => {
    if (!value) {
      allergies.value = ''
      instructions.value = ''
      notes.value = ''
      return
    }
    programmeId.value = props.defaultProgramme || props.programmes[0]?.id || ''
    const preferredEnrolment = activeEnrolments.value.find(value => value.id === props.defaultEnrolment)
    const programmeEnrolment = activeEnrolments.value.find(value => value.programmeId === programmeId.value)
    enrolmentId.value = preferredEnrolment?.id || programmeEnrolment?.id || activeEnrolments.value[0]?.id || ''
    adultId.value = null
    authorityId.value = authorities.value[0]?.id || ''
    startDay.value = churchToday('Africa/Johannesburg')
    endDay.value = `${Number(startDay.value.slice(0, 4)) + 1}${startDay.value.slice(4)}`
    validFrom.value = localInput()
    validUntil.value = localInput(new Date(Date.now() + 365 * 86_400_000))
    acceptedAt.value = localInput()
    acceptedNow.value = true
    evidence.value = ''
    reason.value = ''
    allergies.value = props.support?.allergies || ''
    instructions.value = props.support?.careInstructions || ''
    notes.value = props.support?.restrictedNotes || ''
    reviewDay.value = props.support?.reviewOn || startDay.value
    retainDay.value = props.support?.retainUntil || endDay.value
    syncAuthority()
    void loadClasses()
    void loadPolicy()
  })
  function syncAuthority () {
    const authority = authorities.value.find(
      value => value.id === authorityId.value,
    )
    if (authority) {
      validFrom.value = localInput(
        new Date(Math.max(Date.now(), Date.parse(authority.validFrom))),
      )
      validUntil.value = localInput(new Date(authority.validUntil))
    }
  }
  watch(authorityId, syncAuthority)
  async function loadClasses () {
    const sequence = ++classSequence
    classes.value = []
    classId.value = ''
    classError.value = ''
    const enrolment = props.enrolments.find(
      value => value.id === enrolmentId.value,
    )
    if (!open.value || props.mode !== 'assign' || !enrolment) return
    startDay.value = enrolment.validFrom
    endDay.value = lastDay(enrolment.validUntil)
    classLoading.value = true
    try {
      const result = await childrenApi.classes(enrolment.programmeId)
      if (sequence === classSequence) classes.value = result
    } catch (error) {
      if (sequence === classSequence)
        classError.value = extractErrorMessage(error)
    } finally {
      if (sequence === classSequence) classLoading.value = false
    }
  }
  async function loadPolicy () {
    const sequence = ++policySequence
    policy.value = null
    policyError.value = ''
    if (!open.value || props.mode !== 'consent' || !programmeId.value) return
    policyLoading.value = true
    try {
      const programme = await childrenApi.programme(programmeId.value)
      if (!programme.currentPolicyId)
        throw new Error('Publish a consent policy for this programme first.')
      const result = await childrenApi.policy(programme.currentPolicyId)
      if (sequence === policySequence) policy.value = result
    } catch (error) {
      if (sequence === policySequence)
        policyError.value = extractErrorMessage(error)
    } finally {
      if (sequence === policySequence) policyLoading.value = false
    }
  }
  watch(enrolmentId, loadClasses)
  watch(programmeId, loadPolicy)
  onBeforeUnmount(() => {
    classSequence++
    policySequence++
  })
  async function save () {
    if (props.mode === 'enrol' || props.mode === 'assign') {
      validPeriod(startDay.value, dayAfter(endDay.value))
      const period = {
        validFrom: startDay.value,
        validUntil: dayAfter(endDay.value),
      }
      return props.mode === 'enrol'
        ? childrenApi.enrol({
          ...period,
          childId: props.childId,
          programmeId: programmeId.value,
        })
        : childrenApi.assignClass({
          ...period,
          enrolmentId: enrolmentId.value,
          classId: classId.value,
        })
    }
    if (['guardian', 'consent', 'pickup'].includes(props.mode)) {
      validPeriod(validFrom.value, validUntil.value)
      const period = {
        validFrom: instant(validFrom.value),
        validUntil: instant(validUntil.value),
        evidenceReference: evidence.value,
        childId: props.childId,
      }
      if (props.mode === 'guardian')
        return childrenApi.verifyGuardian({
          ...period,
          guardianId: adultId.value!,
        })
      if (props.mode === 'pickup')
        return childrenApi.authorizePickup({
          ...period,
          programmeId: programmeId.value,
          collectorId: adultId.value!,
          authorityId: authorityId.value,
        })
      if (!policy.value || policyLoading.value)
        throw new Error('Load the current consent policy first.')
      return childrenApi.recordConsent({
        ...period,
        programmeId: programmeId.value,
        authorityId: authorityId.value,
        policyId: policy.value.id,
        acceptedAt: acceptedNow.value
          ? new Date().toISOString()
          : instant(acceptedAt.value),
      })
    }
    if (props.mode === 'support')
      return childrenApi.saveSupport({
        childId: props.childId,
        expectedVersion: props.support?.version || 0,
        allergies: allergies.value,
        careInstructions: instructions.value,
        restrictedNotes: notes.value,
        reviewOn: reviewDay.value,
        retainUntil: retainDay.value,
      })
    if (!props.target) throw new Error('Choose a record first.')
    if (props.mode === 'end')
      return props.target.kind === 'enrolment'
        ? childrenApi.endEnrolment({
          enrolmentId: props.target.id,
          expectedVersion: props.target.version,
        })
        : childrenApi.endAssignment({
          assignmentId: props.target.id,
          expectedVersion: props.target.version,
        })
    const command = {
      id: props.target.id,
      expectedVersion: props.target.version,
      reason: reason.value,
    }
    return props.target.kind === 'guardian'
      ? childrenApi.revokeGuardian(command)
      : (props.target.kind === 'consent'
        ? childrenApi.revokeConsent(command)
        : childrenApi.revokePickup(command))
  }
</script>
