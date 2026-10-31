<template>
  <div class="directory-workspace">
    <v-btn
      class="mb-3"
      prepend-icon="mdi-arrow-left"
      to="/children"
      variant="text"
    >
      Children Ministry
    </v-btn>

    <DirectoryHeading
      eyebrow="A CHILD’S MINISTRY JOURNEY"
      subtitle="Belonging, family permissions and thoughtful care, all in one place."
      :title="childName"
    >
      <v-btn
        v-if="auth.hasPermission('people.read')"
        :to="`/people/${id}`"
        variant="outlined"
      >
        People profile
      </v-btn>

      <v-btn
        v-if="canManage && workspace.data.value"
        color="primary"
        prepend-icon="mdi-plus"
        @click="edit('enrol')"
      >
        Enrol in programme
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="workspace.error.value"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
      <v-btn
        variant="text"
        @click="load"
      >
        Retry
      </v-btn>
    </v-alert>

    <v-skeleton-loader
      v-if="workspace.loading.value"
      type="article"
    />

    <template v-else-if="workspace.data.value">
      <div class="children-split">
        <div>
          <ChildReadinessPanel
            :key="id"
            :child-id="id"
            :default-programme="
              String(
                route.query.programme ||
                  workspace.data.value.enrolments[0]?.programmeId ||
                  '',
              )
            "
            :programmes="workspace.data.value.programmes"
            :revision="readinessRevision"
            @action="continueReadiness"
            @care="accessSupport"
            @programme-change="readinessProgramme = $event"
          />

          <section class="directory-surface children-section">
            <div class="children-section__heading">
              <h2>Programmes & classes</h2>

              <v-btn
                v-if="canManage"
                variant="text"
                @click="edit('assign')"
              >
                Place in class
              </v-btn>
            </div>

            <div
              v-for="enrolment in workspace.data.value.enrolments"
              :key="enrolment.id"
              class="children-list-row"
            >
              <div>
                <strong>{{ programmeName(enrolment.programmeId) }}</strong>

                <small>
                  {{ dateLabel(enrolment.validFrom) }} –
                  {{ dateLabel(lastDay(enrolment.validUntil)) }} ·
                  {{ enrolment.endedAt ? 'Ended' : 'Enrolled' }}
                </small>

                <small
                  v-for="assignment in workspace.data.value.assignments.filter(
                    (a) => a.enrolmentId === enrolment.id,
                  )"
                  :key="assignment.id"
                >
                  {{ classNames[assignment.classId] || 'Class placement' }} ·
                  {{ assignment.endedAt ? 'Ended' : 'Placed' }}
                  <v-btn
                    v-if="canManage && !assignment.endedAt"
                    size="x-small"
                    variant="text"
                    @click="end(assignment, 'assignment')"
                  >
                    End placement
                  </v-btn>
                </small>
              </div>

              <v-btn
                v-if="canManage && !enrolment.endedAt"
                size="small"
                variant="text"
                @click="end(enrolment, 'enrolment')"
              >
                End enrolment
              </v-btn>
            </div>

            <DirectoryEmpty
              v-if="workspace.data.value.enrolments.length === 0"
              description="Enrol this child in a programme, then choose their class."
              title="A place to begin"
            />

            <div class="d-flex justify-end mt-4">
              <v-btn
                :disabled="!offset"
                variant="text"
                @click="offset -= 25"
              >
                Previous
              </v-btn>

              <v-btn
                :disabled="workspace.data.value.enrolments.length < 25"
                variant="text"
                @click="offset += 25"
              >
                Next
              </v-btn>
            </div>
          </section>

          <section
            v-if="canSafeguard"
            class="directory-surface children-section"
          >
            <div class="children-section__heading">
              <h2>Family permissions</h2>

              <v-icon
                color="primary"
                icon="mdi-shield-check-outline"
              />
            </div>

            <p>
              Verify guardian authority first, then record programme consent and
              authorized collectors.
            </p>

            <div class="d-flex flex-wrap ga-2 mb-5">
              <v-btn
                variant="tonal"
                @click="edit('guardian')"
              >
                Verify guardian
              </v-btn>

              <v-btn
                variant="tonal"
                @click="edit('consent')"
              >
                Record consent
              </v-btn>

              <v-btn
                variant="tonal"
                @click="edit('pickup')"
              >
                Authorize collector
              </v-btn>
            </div>

            <v-tabs
              v-model="permissionTab"
              color="primary"
            >
              <v-tab value="guardians">Guardians</v-tab>

              <v-tab value="consents">Consent</v-tab>

              <v-tab value="pickup">Collectors</v-tab>
            </v-tabs>

            <div
              v-for="record in permissionRecords"
              :key="record.id"
              class="children-list-row"
            >
              <div>
                <strong>{{ record.label }}</strong>

                <small>{{ record.description }}</small>

                <small>
                  {{ timeLabel(record.validFrom) }} –
                  {{ timeLabel(record.validUntil) }}
                </small>

                <small v-if="record.revokedAt">
                  Revoked · {{ record.revocationReason }}
                </small>
              </div>

              <span
                class="directory-badge"
                :class="{
                  'directory-badge--active_member':
                    !record.revokedAt &&
                    Date.parse(record.validUntil) > Date.now(),
                }"
              >
                <i />
                {{
                  record.revokedAt
                    ? 'Revoked'
                    : Date.parse(record.validUntil) <= Date.now()
                      ? 'Expired'
                      : 'Recorded'
                }}
              </span>

              <v-btn
                v-if="!record.revokedAt"
                size="small"
                variant="text"
                @click="revoke(record)"
              >
                Revoke
              </v-btn>
            </div>

            <p
              v-if="permissionRecords.length === 0"
              class="children-notice py-5"
            >
              No
              {{
                permissionTab === 'pickup'
                  ? 'authorized collectors'
                  : permissionTab
              }}
              recorded yet.
            </p>
          </section>
        </div>

        <aside>
          <section
            v-if="auth.hasPermission('children.support') && canManage"
            class="directory-surface children-section"
          >
            <h2 id="child-care">Care, with understanding</h2>

            <p>
              Allergies, care instructions and restricted notes are kept
              separate from the public ministry profile.
            </p>

            <v-alert
              v-if="supportError"
              class="mb-4"
              type="error"
              variant="tonal"
            >
              {{ supportError }}
            </v-alert>

            <template v-if="support">
              <div class="children-form-note">
                <strong>Allergies</strong>
                <p>{{ support.allergies || 'None recorded' }}</p>
                <strong>Care instructions</strong>
                <p>{{ support.careInstructions || 'None recorded' }}</p>
                <strong>Restricted notes</strong>
                <p>{{ support.restrictedNotes || 'None recorded' }}</p>

                <small>
                  Review {{ dateLabel(support.reviewOn) }} · Keep until
                  {{ dateLabel(support.retainUntil) }}
                </small>
              </div>

              <v-btn
                variant="tonal"
                @click="edit('support')"
              >
                Edit care information
              </v-btn>

              <v-btn
                variant="text"
                @click="support = null"
              >
                Hide
              </v-btn>
            </template>

            <v-btn
              v-else
              :loading="supportLoading"
              variant="tonal"
              @click="accessSupport"
            >
              Open care information
            </v-btn>

            <p class="children-notice mt-4">
              Access is restricted and recorded.
            </p>
          </section>

          <section class="directory-surface children-section">
            <h2>Attendance history</h2>

            <v-btn
              prepend-icon="mdi-chart-timeline-variant"
              :to="{ path: '/children/reports', query: { childId: id } }"
              variant="text"
            >
              Attendance report
            </v-btn>

            <div
              v-for="attendance in history.data.value"
              :key="attendance.id"
              class="children-list-row"
            >
              <div>
                <strong>
                  {{ dateLabel(attendance.arrivedAt.slice(0, 10)) }}
                </strong>

                <small>
                  {{
                    attendance.checkedOutAt
                      ? 'Collected'
                      : 'Currently attending'
                  }}
                </small>
              </div>

              <v-btn
                aria-label="View attendance journey"
                icon="mdi-timeline-clock-outline"
                size="small"
                variant="text"
                @click="openAttendanceJourney(attendance.id)"
              />

              <v-btn
                aria-label="Open attendance session"
                icon="mdi-arrow-top-right"
                size="small"
                :to="`/children/sessions/${attendance.sessionId}`"
                variant="text"
              />
            </div>

            <p
              v-if="history.data.value.length === 0"
              class="children-notice"
            >
              Their attendance story will appear here.
            </p>

            <v-alert
              v-if="history.error.value"
              type="error"
              variant="tonal"
            >
              {{ history.error.value }}
            </v-alert>

            <div class="d-flex justify-end mt-4">
              <v-btn
                :disabled="!historyOffset"
                size="small"
                variant="text"
                @click="historyOffset -= 25"
              >
                Previous
              </v-btn>

              <v-btn
                :disabled="history.data.value.length < 25"
                size="small"
                variant="text"
                @click="historyOffset += 25"
              >
                Next
              </v-btn>
            </div>
          </section>
        </aside>
      </div>
    </template>

    <AttendanceJourney
      v-model="journeyOpen"
      :attendance-id="journeyAttendanceId"
      :child-name="childName"
    />

    <ParticipationDialog
      v-if="workspace.data.value"
      v-model="editor"
      :child-id="id"
      :child-name="childName"
      :default-enrolment="readinessEnrolment?.id"
      :default-programme="
        readinessProgramme || String(route.query.programme || '')
      "
      :enrolments="dialogEnrolments"
      :mode="mode"
      :names="names"
      :programmes="workspace.data.value.programmes"
      :safeguarding="workspace.data.value.safeguarding"
      :support="support"
      :target="target"
      @reload="reloadEditor"
      @saved="saved"
    />
  </div>
</template>
<script setup lang="ts">
  import type { ParticipationMode } from '../components/ParticipationDialog.vue'
  import type { Enrolment, SupportRecord } from '../types'
  import axios from 'axios'
  import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import { extractErrorMessage } from '@/composables/useApi'
  import { directoryApi } from '@/directory/api'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { fullName } from '@/directory/presentation'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import AttendanceJourney from '../components/AttendanceJourney.vue'
  import ChildReadinessPanel from '../components/ChildReadinessPanel.vue'
  import ParticipationDialog from '../components/ParticipationDialog.vue'
  import { dateLabel, lastDay, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = defineProps<{ id: string }>()
  const auth = useAuthStore()
  const route = useRoute()
  const canManage = computed(() => auth.hasPermission('children.manage'))
  const canSafeguard = computed(() =>
    auth.hasPermission('children.safeguarding'),
  )
  const offset = ref(0)
  const historyOffset = ref(0)
  const journeyOpen = ref(false)
  const journeyAttendanceId = ref('')
  function openAttendanceJourney (attendanceId: string) {
    journeyAttendanceId.value = attendanceId
    journeyOpen.value = true
  }
  watch(
    () => props.id,
    () => {
      journeyOpen.value = false
      journeyAttendanceId.value = ''
    },
  )

  const editor = ref(false)
  const mode = ref<ParticipationMode>('enrol')
  const readinessRevision = ref(0)
  const readinessProgramme = ref('')
  const readinessEnrolment = ref<Enrolment | null>(null)
  const dialogEnrolments = computed(() => {
    const values = workspace.data.value?.enrolments || []
    const current = readinessEnrolment.value
    return current && !values.some(value => value.id === current.id)
      ? [...values, current]
      : values
  })
  function continueReadiness (value: {
    mode: ParticipationMode
    programmeId: string
    enrolment: Enrolment | null
  }) {
    readinessProgramme.value = value.programmeId
    readinessEnrolment.value = value.enrolment
    edit(value.mode)
  }
  const target = ref<{
    kind: 'guardian' | 'consent' | 'pickup' | 'enrolment' | 'assignment'
    id: string
    version: number
  } | null>(null)
  const names = ref<Record<string, string>>({})
  const classNames = ref<Record<string, string>>({})
  const permissionTab = ref('guardians')
  const support = ref<SupportRecord | null>(null)
  const supportLoading = ref(false)
  const supportError = ref('')
  let supportSequence = 0
  const workspace = useLoad(async () => {
    const [person, enrolments, assignments, programmes, safeguarding]
      = await Promise.all([
        directoryApi.person(props.id),
        childrenApi.enrolments(props.id, offset.value),
        childrenApi.assignments(props.id, offset.value),
        childrenApi.programmes(),
        canSafeguard.value ? childrenApi.safeguarding(props.id) : null,
      ])
    const missing = [
      ...new Set(
        [
          ...enrolments.map(e => e.programmeId),
          String(route.query.programme || ''),
        ].filter(id => id && !programmes.some(p => p.id === id)),
      ),
    ]
    programmes.push(
      ...(await Promise.all(missing.map(id => childrenApi.programme(id)))),
    )
    const groups = await Promise.all(
      programmes
        .filter(p => enrolments.some(e => e.programmeId === p.id))
        .map(p => childrenApi.classes(p.id)),
    )
    const relatedIds = [
      ...new Set([
        ...(safeguarding?.authorities.map(a => a.guardianId) || []),
        ...(safeguarding?.pickupAuthorizations.map(a => a.collectorId) || []),
      ]),
    ]
    const related = await Promise.all(
      relatedIds.map(async id => {
        try {
          return await directoryApi.person(id)
        } catch {
          return null
        }
      }),
    )
    return {
      person,
      enrolments,
      assignments,
      programmes,
      safeguarding,
      groups,
      related,
    }
  }, null)
  const childName = computed(() =>
    workspace.data.value
      ? fullName(workspace.data.value.person)
      : 'Ministry profile',
  )
  const history = useLoad(
    () => childrenApi.history(props.id, historyOffset.value),
    [],
  )
  const permissionRecords = computed(() => {
    const scope = workspace.data.value?.safeguarding
    if (!scope) return []
    if (permissionTab.value === 'guardians')
      return scope.authorities.map(v => ({
        ...v,
        kind: 'guardian' as const,
        label: names.value[v.guardianId] || 'Guardian',
        description: v.evidenceReference,
      }))
    if (permissionTab.value === 'consents')
      return scope.consents.map(v => ({
        ...v,
        kind: 'consent' as const,
        label: programmeName(v.programmeId),
        description: 'Consent evidence: ' + v.evidenceReference,
      }))
    return scope.pickupAuthorizations.map(v => ({
      ...v,
      kind: 'pickup' as const,
      label: names.value[v.collectorId] || 'Authorized collector',
      description: programmeName(v.programmeId),
    }))
  })
  function programmeName (id: string) {
    return (
      workspace.data.value?.programmes.find(p => p.id === id)?.name
      || 'Programme'
    )
  }
  async function load () {
    await workspace.load()
    names.value = Object.fromEntries(
      (workspace.data.value?.related || [])
        .filter(p => p !== null)
        .map(p => [p.id, fullName(p)]),
    )
    classNames.value = Object.fromEntries(
      (workspace.data.value?.groups.flat() || []).map(c => [c.id, c.name]),
    )
  }
  function edit (value: ParticipationMode) {
    mode.value = value
    editor.value = true
  }
  function end (
    value: { id: string, version: number },
    kind: 'enrolment' | 'assignment',
  ) {
    target.value = { ...value, kind }
    edit('end')
  }
  function revoke (value: {
    id: string
    version: number
    kind: 'guardian' | 'consent' | 'pickup'
  }) {
    target.value = value
    edit('revoke')
  }
  async function accessSupport () {
    const sequence = ++supportSequence
    const childId = props.id
    supportLoading.value = true
    supportError.value = ''
    try {
      const record = await childrenApi.support(childId, null)
      if (sequence === supportSequence && props.id === childId)
        support.value = record
    } catch (error) {
      if (sequence !== supportSequence || props.id !== childId) return
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        support.value = null
        edit('support')
      } else supportError.value = extractErrorMessage(error)
    } finally {
      if (sequence === supportSequence) supportLoading.value = false
    }
  }
  async function saved () {
    if (mode.value === 'support') {
      support.value = null
      await accessSupport()
    }
    await load()
    readinessEnrolment.value = null
    readinessRevision.value++
  }
  async function reloadEditor () {
    target.value = null
    editor.value = false
    support.value = null
    await load()
  }
  let handledFollowUp = ''
  watch(
    [() => workspace.data.value, () => route.query.followup, () => route.hash],
    async () => {
      if (!workspace.data.value) return
      if (route.hash === '#child-care') {
        await nextTick()
        document
          .querySelector('#child-care')
          ?.scrollIntoView({ block: 'start' })
      }
      const action = route.query.followup
      const key = `${props.id}:${action}:${route.query.programme || ''}`
      if (
        handledFollowUp === key
        || !auth.hasPermission('children.safeguarding')
        || typeof action !== 'string'
        || !['guardian', 'consent', 'pickup'].includes(action)
      )
        return
      handledFollowUp = key
      readinessProgramme.value = String(route.query.programme || '')
      target.value = null
      edit(action as ParticipationMode)
    },
  )
  watch(offset, load)
  watch(historyOffset, history.load)
  watch(
    () => props.id,
    () => {
      supportSequence++
      support.value = null
      supportError.value = ''
      editor.value = false
      offset.value = 0
      historyOffset.value = 0
      void load()
      void history.load()
    },
    { immediate: true },
  )
  watch(
    () => auth.token,
    () => {
      supportSequence++
      support.value = null
      editor.value = false
    },
  )
  onBeforeUnmount(() => {
    supportSequence++
    support.value = null
  })
</script>
