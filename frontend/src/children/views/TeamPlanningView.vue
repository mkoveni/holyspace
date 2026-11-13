<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="THE PEOPLE BEHIND EVERY WELCOME"
      subtitle="See the weeks ahead, strengthen your teams and make each session ready before the day arrives."
      title="Ready teams. Room to care."
    >
      <v-btn
        color="primary"
        :disabled="selected.length === 0 || dirty || workspace.loading.value"
        @click="editor = true"
      >
        Plan selected ({{ selected.length }})
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="team" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-account-group-outline" />
        </span>

        <div>
          <strong>A clear plan for every room.</strong>

          <p>
            Expected capacity uses qualified assigned staff. Presence is
            confirmed separately on session day.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">TEAM PLANNING & READINESS</span>
    </div>

    <section
      aria-label="Team planning filters"
      class="directory-surface children-section coordinator-filters"
    >
      <v-form @submit.prevent="apply">
        <div class="coordinator-filter-grid">
          <DateSelector
            v-model="from"
            label="From date"
          />

          <DateSelector
            v-model="until"
            label="Through date"
          />

          <ProgrammePicker v-model="programmeId" />

          <v-select
            v-model="locationId"
            clearable
            :error-messages="locations.error.value"
            item-title="name"
            item-value="id"
            :items="locations.data.value"
            label="Location"
          >
            <template #append-item>
              <v-btn
                v-if="moreLocations"
                block
                variant="text"
                @click="loadMoreLocations"
              >
                Load more locations
              </v-btn>
            </template>
          </v-select>

          <v-text-field
            v-model="search"
            clearable
            label="Class, room or team member"
            prepend-inner-icon="mdi-magnify"
          />

          <v-select
            v-model="state"
            item-title="label"
            item-value="value"
            :items="states"
            label="Readiness"
          />
        </div>

        <v-alert
          v-if="filterError"
          type="error"
          variant="tonal"
        >
          {{ filterError }}
        </v-alert>

        <div class="coordinator-actions">
          <p class="directory-muted">
            Up to 90 days ·
            {{ workspace.data.value?.timeZone || 'church time zone' }}
          </p>

          <v-btn
            color="primary"
            :loading="workspace.loading.value"
            type="submit"
            variant="tonal"
          >
            Apply filters
          </v-btn>
        </div>

        <p
          v-if="dirty"
          class="directory-muted mt-3"
        >
          Apply filters before selecting sessions from this view.
        </p>
      </v-form>
    </section>

    <v-alert
      v-if="workspace.error.value"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
      <v-btn
        variant="text"
        @click="reload"
      >
        Retry
      </v-btn>
    </v-alert>

    <TaskAlert
      :conflict="clearanceTask.conflict.value"
      :error="clearanceTask.error.value"
      @reload="reload"
    />

    <template v-if="workspace.data.value">
      <dl class="coordinator-stats">
        <div
          v-for="stat in stats"
          :key="stat.label"
          class="directory-surface"
        >
          <dt>{{ stat.label }}</dt>
          <dd>{{ stat.value }}</dd>
        </div>
      </dl>

      <p class="directory-muted mb-4">
        Totals cover the selected date, programme, location and search scope,
        across all readiness views and pages.
      </p>
    </template>

    <section
      aria-label="Upcoming team plans"
      class="directory-surface"
    >
      <div class="directory-toolbar">
        <h2>Upcoming sessions</h2>
        <v-spacer />
        <span class="directory-muted">{{ selected.length }} / 25 selected</span>

        <v-btn
          v-if="selected.length > 0"
          variant="text"
          @click="selected = []"
        >
          Clear selection
        </v-btn>

        <v-btn
          aria-label="Refresh team planning"
          icon="mdi-refresh"
          :loading="workspace.loading.value"
          variant="text"
          @click="reload"
        />
      </div>

      <v-progress-linear
        v-if="workspace.loading.value"
        color="primary"
        indeterminate
      />

      <article
        v-for="row in workspace.data.value?.sessions || []"
        :key="row.session.id"
        class="planning-row"
      >
        <header>
          <v-checkbox
            :aria-label="`Select ${row.ministryClass} on ${timeLabel(row.session.startsAt, row.timeZone)}`"
            :disabled="
              !row.editable ||
                dirty ||
                (selected.length >= 25 &&
                  !selected.some((value) => value.session.id === row.session.id))
            "
            hide-details
            :model-value="
              selected.some((value) => value.session.id === row.session.id)
            "
            @update:model-value="toggle(row, !!$event)"
          />

          <div>
            <span class="directory-eyebrow">
              {{ row.programme }} · {{ row.room }}
            </span>

            <h3>{{ row.ministryClass }}</h3>

            <p>
              {{ timeLabel(row.session.startsAt, row.timeZone) }} ·
              {{ row.timeZone }}
            </p>
          </div>

          <v-chip
            :color="row.ready ? 'success' : 'warning'"
            variant="tonal"
          >
            {{ row.ready ? 'Team prepared' : 'Needs attention' }}
          </v-chip>
        </header>

        <div class="planning-metrics">
          <span>
            <strong>
              {{ row.plannedCapacity }} / {{ row.session.seatLimit }}
            </strong>
            expected seats
          </span>

          <span>
            <strong>{{ row.qualifiedStaff }} / {{ row.requiredStaff }}</strong>
            qualified staff needed
          </span>

          <span>
            {{
              row.qualifiedLeader
                ? 'Qualified leader assigned'
                : 'Qualified leader needed'
            }}
          </span>
        </div>

        <ul
          v-if="row.issues.length > 0"
          class="planning-issues"
        >
          <li
            v-for="issue in row.issues"
            :key="issue"
          >
            {{ issue }}
          </li>
        </ul>

        <div
          v-if="row.team.length > 0"
          class="planning-team"
        >
          <div
            v-for="member in row.team"
            :key="member.workerId"
            class="planning-member"
          >
            <div>
              <strong>{{ member.name }}</strong>

              <small>
                {{ member.role === 'LEADER' ? 'Leader' : 'Assistant' }} ·
                {{
                  member.present
                    ? 'Presence confirmed'
                    : 'Assigned; presence to be confirmed'
                }}
              </small>

              <small v-if="member.screenedUntil">
                Screening: {{ timeLabel(member.screenedUntil, row.timeZone) }}
              </small>

              <small v-if="member.trainedUntil">
                Training: {{ timeLabel(member.trainedUntil, row.timeZone) }}
              </small>
            </div>

            <v-btn
              v-if="
                auth.hasPermission('children.safeguarding') &&
                  auth.hasPermission('people.read') &&
                  !member.qualified
              "
              :loading="clearanceTask.busy.value"
              variant="text"
              @click="openClearance(member.workerId)"
            >
              Review clearance
            </v-btn>
          </div>
        </div>

        <footer>
          <small class="directory-muted">
            {{
              row.editable
                ? 'Select to plan this team across future sessions.'
                : 'This session needs individual management.'
            }}
          </small>

          <v-btn
            :to="`/children/sessions/${row.session.id}`"
            variant="text"
          >
            Open session
            <v-icon
              end
              icon="mdi-arrow-top-right"
            />
          </v-btn>
        </footer>
      </article>

      <DirectoryEmpty
        v-if="
          !workspace.loading.value &&
            !workspace.error.value &&
            !workspace.data.value?.sessions.length
        "
        description="No upcoming sessions match this view. Try another date or readiness filter."
        icon="mdi-calendar-check-outline"
        title="A little breathing room"
      />

      <footer class="coordinator-paging">
        <span>
          {{ offset + (workspace.data.value?.total ? 1 : 0) }}–{{
            Math.min(offset + 25, workspace.data.value?.total || 0)
          }}
          of {{ workspace.data.value?.total || 0 }} sessions
        </span>

        <div>
          <v-btn
            :disabled="!offset || workspace.loading.value || dirty"
            variant="text"
            @click="page(-25)"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              offset + 25 >= (workspace.data.value?.total || 0) ||
                workspace.loading.value ||
                dirty
            "
            variant="text"
            @click="page(25)"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <TeamPlanDialog
      v-model="editor"
      :sessions="selected"
      @reload="reload"
      @saved="reload"
    />

    <SetupDialog
      v-model="clearanceEditor"
      :default-person="clearancePerson || undefined"
      :locations="locations.data.value"
      mode="worker"
      @saved="reload"
    />
  </div>
</template>
<script setup lang="ts">
  import type { Location, PlanningSession, TeamPlanningPage } from '../types'
  import type { PersonSummary } from '@/directory/types'
  import { computed, ref } from 'vue'
  import DateSelector from '@/components/common/DateSelector.vue'
  import { directoryApi } from '@/directory/api'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { churchToday } from '@/directory/presentation'
  import { useTask } from '@/directory/useTask'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import ProgrammePicker from '../components/ProgrammePicker.vue'
  import SetupDialog from '../components/SetupDialog.vue'
  import TeamPlanDialog from '../components/TeamPlanDialog.vue'
  import { timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore()
  const today = churchToday('Africa/Johannesburg')
  const from = ref(today)
  const end = new Date(today + 'T12:00:00Z')
  end.setUTCDate(end.getUTCDate() + 30)
  const until = ref(end.toISOString().slice(0, 10))
  const programmeId = ref<string | null>(null)
  const locationId = ref<string | null>(null)
  const search = ref<string | null>('')
  const state = ref('ALL')
  const states = [
    { value: 'ALL', label: 'All readiness' },
    { value: 'ATTENTION', label: 'Needs attention' },
    { value: 'READY', label: 'Team prepared' },
  ]
  const filterError = ref('')
  const offset = ref(0)
  function filter () {
    return {
      from: from.value,
      until: until.value,
      programmeId: programmeId.value || undefined,
      locationId: locationId.value || undefined,
      search: search.value || '',
      state: state.value,
    }
  }
  const applied = ref(filter())
  const dirty = computed(
    () => JSON.stringify(applied.value) !== JSON.stringify(filter()),
  )
  const workspace = useLoad<TeamPlanningPage | null>(
    () => childrenApi.teamPlanning(applied.value, offset.value),
    null,
  )
  const selected = ref<PlanningSession[]>([])
  const editor = ref(false)
  const stats = computed(() => {
    const summary = workspace.data.value?.summary
    return [
      { label: 'Upcoming sessions', value: summary?.sessions || 0 },
      { label: 'Team prepared', value: summary?.ready || 0 },
      { label: 'Needs attention', value: summary?.attention || 0 },
      { label: 'Missing leaders', value: summary?.missingLeaders || 0 },
      { label: 'Short capacity', value: summary?.shortCapacity || 0 },
      { label: 'Assignment clashes', value: summary?.clashes || 0 },
    ]
  })
  function apply () {
    const days = (Date.parse(until.value) - Date.parse(from.value)) / 86_400_000
    filterError.value
      = !Number.isFinite(days) || days < 0 || days > 89
        ? 'Choose a date range of up to 90 days.'
        : ''
    if (filterError.value) return
    applied.value = filter()
    reload()
  }
  function toggle (row: PlanningSession, include: boolean) {
    selected.value = include
      ? [
        ...selected.value.filter(
          value => value.session.id !== row.session.id,
        ),
        row,
      ]
      : selected.value.filter(value => value.session.id !== row.session.id)
  }
  function reload () {
    editor.value = false
    selected.value = []
    offset.value = 0
    void workspace.load()
  }
  function page (delta: number) {
    offset.value += delta
    void workspace.load()
  }
  const locationOffset = ref(0)
  const locationPages = ref<Location[]>([])
  const moreLocations = ref(false)
  const locations = useLoad(async () => {
    const values = await childrenApi.locations(locationOffset.value)
    moreLocations.value = values.length === 100
    locationPages.value = locationOffset.value
      ? [...locationPages.value, ...values]
      : values
    return locationPages.value
  }, [] as Location[])
  function loadMoreLocations () {
    locationOffset.value = locationPages.value.length
    void locations.load()
  }
  const clearanceEditor = ref(false)
  const clearancePerson = ref<PersonSummary | null>(null)
  const clearanceTask = useTask()
  async function openClearance (id: string) {
    const result = await clearanceTask.run(() => directoryApi.person(id))
    if (result) {
      clearancePerson.value = result.value
      clearanceEditor.value = true
    }
  }
  void locations.load()
  void workspace.load()
</script>
<style scoped>
  .coordinator-filters {
    margin: 24px 0;
    padding: 24px;
  }
  .coordinator-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px 20px;
  }
  .coordinator-actions,
  .coordinator-paging,
  .planning-row header,
  .planning-row footer,
  .planning-member {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    flex-wrap: wrap;
  }
  .coordinator-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin: 24px 0 16px;
  }
  .coordinator-stats div {
    padding: 18px;
  }
  .coordinator-stats dt {
    color: var(--workspace-muted);
    font-size: 0.8rem;
  }
  .coordinator-stats dd {
    font-size: 1.8rem;
    font-weight: 650;
    margin: 8px 0 0;
  }
  .planning-row {
    padding: 24px;
    border-top: 1px solid var(--workspace-border);
    overflow-wrap: anywhere;
  }
  .planning-row header > div:nth-child(2) {
    flex: 1;
    min-width: 160px;
  }
  .planning-row header > :first-child {
    flex: 0 0 44px;
  }
  .planning-row h3 {
    margin: 5px 0;
  }
  .planning-row p {
    color: var(--workspace-muted);
  }
  .planning-metrics {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    padding: 20px 0;
  }
  .planning-issues {
    padding: 12px 12px 12px 28px;
    background: var(--workspace-tint);
    border-radius: 12px;
    color: var(--workspace-muted);
  }
  .planning-team {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 20px 0;
  }
  .planning-member {
    padding: 14px;
    background: var(--workspace-soft);
    border-radius: 12px;
  }
  .planning-member small {
    display: block;
    color: var(--workspace-muted);
    margin-top: 4px;
  }
  .coordinator-paging {
    padding: 16px 24px;
    border-top: 1px solid var(--workspace-border);
  }
  @media (max-width: 1100px) {
    .coordinator-stats {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (max-width: 650px) {
    .coordinator-filter-grid,
    .planning-team {
      grid-template-columns: 1fr;
    }
    .coordinator-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
