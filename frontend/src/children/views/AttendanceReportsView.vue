<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="CHILDREN & BELONGING"
      subtitle="Follow attendance across programmes, trace safe handovers and keep a clear record of care."
      title="Every visit tells a story."
    >
      <v-btn
        v-if="auth.hasPermission('children.manage')"
        color="primary"
        :disabled="
          !workspace.data.value ||
            workspace.loading.value ||
            workspace.data.value.summary.visits > 10000 ||
            dirty
        "
        :loading="exportTask.busy.value"
        prepend-icon="mdi-tray-arrow-down"
        @click="download"
      >
        Export CSV
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="reports" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-chart-timeline-variant" />
        </span>

        <div>
          <strong>A clear picture. A careful record.</strong>
          <p>One row per room visit. Transfers keep both rooms in the story.</p>
        </div>
      </div>

      <span class="directory-banner__caption">ATTENDANCE & REPORTING</span>
    </div>

    <TaskAlert
      :conflict="exportTask.conflict.value"
      :error="exportTask.error.value"
    />

    <section
      aria-label="Attendance report filters"
      class="directory-surface children-section report-filters"
    >
      <v-form @submit.prevent="apply">
        <div class="report-filter-grid">
          <DateSelector
            v-model="from"
            label="From date"
            :rules="[required]"
          />

          <DateSelector
            v-model="until"
            label="Through date"
            :rules="[required]"
          />

          <v-autocomplete
            v-model="programmeId"
            clearable
            item-title="name"
            item-value="id"
            :items="programmeItems"
            label="Programme"
            :loading="programmes.loading.value"
            no-filter
            @update:search="findProgrammes"
          />

          <v-autocomplete
            v-model="locationId"
            clearable
            item-title="name"
            item-value="id"
            :items="locations.data.value"
            label="Location"
            :loading="locations.loading.value"
          >
            <template #append-item>
              <v-btn
                v-if="moreLocations"
                block
                :loading="locations.loading.value"
                variant="text"
                @click="loadMoreLocations"
              >
                Load more locations
              </v-btn>
            </template>
          </v-autocomplete>

          <v-text-field
            v-model="search"
            clearable
            label="Child name"
            prepend-inner-icon="mdi-magnify"
          />

          <v-select
            v-model="outcome"
            item-title="label"
            item-value="value"
            :items="outcomes"
            label="Attendance outcome"
          />
        </div>

        <v-alert
          v-if="filterError"
          class="mb-3"
          type="error"
          variant="tonal"
        >
          {{ filterError }}
        </v-alert>

        <v-alert
          v-if="programmes.error.value || locations.error.value"
          class="mb-3"
          type="error"
          variant="tonal"
        >
          {{ programmes.error.value || locations.error.value }}
          <v-btn
            variant="text"
            @click="retryCatalogues"
          >
            Retry filters
          </v-btn>
        </v-alert>

        <div class="report-filter-actions">
          <p class="directory-muted">
            Inclusive dates ·
            {{ workspace.data.value?.timeZone || 'church time zone' }} · up to
            366 days
          </p>

          <v-btn
            variant="text"
            @click="reset"
          >
            Reset filters
          </v-btn>

          <v-btn
            color="primary"
            :loading="workspace.loading.value"
            type="submit"
            variant="tonal"
          >
            Apply filters
          </v-btn>
        </div>
      </v-form>

      <v-chip
        v-if="childId"
        class="mt-3"
        closable
        @click:close="clearChild"
      >
        One child’s complete attendance history for these dates
      </v-chip>

      <p
        v-if="dirty"
        class="directory-muted mt-3"
      >
        Apply your changes to refresh the results and export.
      </p>
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
        @click="workspace.load"
      >
        Retry report
      </v-btn>
    </v-alert>

    <template v-if="workspace.data.value">
      <dl class="report-stats">
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
        Totals cover every matching visit, across all pages. Outcomes reflect
        the current record. Updated
        {{
          timeLabel(
            workspace.data.value.checkedAt,
            workspace.data.value.timeZone,
          )
        }}.
      </p>

      <p
        v-if="workspace.data.value.summary.visits > 10000"
        class="children-notice"
      >
        Narrow your filters to 10,000 visits or fewer to export.
      </p>
    </template>

    <section
      :aria-busy="workspace.loading.value"
      aria-label="Attendance results"
      class="directory-surface report-results"
    >
      <div class="directory-toolbar">
        <h2>Attendance history</h2>
        <v-spacer />

        <v-btn
          aria-label="Refresh attendance report"
          icon="mdi-refresh"
          :loading="workspace.loading.value"
          variant="text"
          @click="workspace.load"
        />
      </div>

      <v-progress-linear
        v-if="workspace.loading.value"
        color="primary"
        indeterminate
      />

      <div
        v-if="workspace.data.value?.visits.length"
        class="report-list"
      >
        <article
          v-for="visit in workspace.data.value.visits"
          :key="visit.id"
          class="report-row"
        >
          <div class="report-child">
            <strong>{{ visit.childName }}</strong>
            <small>{{ visit.programme }} · {{ visit.ministryClass }}</small>
            <small>{{ visit.location }} · {{ visit.room }}</small>
          </div>

          <div>
            <span class="directory-eyebrow">ARRIVAL</span>
            <p>{{ timeLabel(visit.arrivedAt, visit.timeZone) }}</p>
            <small class="directory-muted">{{ visit.timeZone }}</small>
          </div>

          <div>
            <v-chip
              :color="
                visit.outcome === 'COLLECTED'
                  ? 'success'
                  : visit.outcome === 'TRANSFERRED'
                    ? 'secondary'
                    : 'primary'
              "
              size="small"
              variant="tonal"
            >
              {{ outcomeLabel(visit.outcome) }}
            </v-chip>

            <p v-if="visit.leftAt">
              {{ timeLabel(visit.leftAt, visit.timeZone) }}
            </p>

            <small
              v-if="visit.supervisorCollection"
              class="report-exception"
            >
              Supervisor label exception
            </small>
          </div>

          <v-btn
            :aria-label="`View journey for ${visit.childName}`"
            variant="text"
            @click="openJourney(visit)"
          >
            View journey
            <v-icon
              end
              icon="mdi-arrow-top-right"
            />
          </v-btn>
        </article>
      </div>

      <DirectoryEmpty
        v-else-if="!workspace.loading.value && !workspace.error.value"
        description="Try a different date range, child name or programme."
        icon="mdi-calendar-search"
        title="No visits in this view"
      />

      <div
        v-if="workspace.data.value"
        class="report-pagination"
      >
        <span class="directory-muted">
          {{ workspace.data.value.summary.visits ? offset + 1 : 0 }}–{{
            Math.min(offset + 25, workspace.data.value.summary.visits)
          }}
          of {{ workspace.data.value.summary.visits }} visits
        </span>

        <div>
          <v-btn
            :disabled="!offset || workspace.loading.value || dirty"
            variant="text"
            @click="changePage(-25)"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              offset + 25 >= workspace.data.value.summary.visits ||
                workspace.loading.value ||
                dirty
            "
            variant="text"
            @click="changePage(25)"
          >
            Next
          </v-btn>
        </div>
      </div>
    </section>

    <AttendanceJourney
      v-model="journey"
      :attendance-id="selected?.attendanceId || ''"
      :child-name="selected?.childName || ''"
    />

    <v-snackbar v-model="downloaded">Filtered attendance exported.</v-snackbar>
  </div>
</template>
<script setup lang="ts">
  import type {
    AttendanceReport,
    AttendanceReportFilter,
    AttendanceVisit,
    Location,
    Programme,
  } from '../types'
  import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DateSelector from '@/components/common/DateSelector.vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import TaskAlert from '@/directory/components/TaskAlert.vue'
  import { useTask } from '@/directory/useTask'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import AttendanceJourney from '../components/AttendanceJourney.vue'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const route = useRoute()
  const router = useRouter()
  const auth = useAuthStore()
  const today = new Date().toLocaleDateString('en-CA')
  const monthStart = today.slice(0, 8) + '01'
  const from = ref(monthStart)
  const until = ref(today)
  const programmeId = ref<string | null>(null)
  const locationId = ref<string | null>(null)
  const childId = ref('')
  const search = ref<string | null>('')
  const outcome = ref('ALL')
  const offset = ref(0)
  const filterError = ref('')
  const outcomes = [
    { value: 'ALL', label: 'All outcomes' },
    { value: 'ATTENDING', label: 'Still attending' },
    { value: 'COLLECTED', label: 'Collected' },
    { value: 'TRANSFERRED', label: 'Transferred out' },
    { value: 'SUPERVISOR', label: 'Supervisor collections' },
  ]
  const applied = shallowRef<AttendanceReportFilter>({
    from: monthStart,
    until: today,
  })
  function filter (): AttendanceReportFilter {
    return {
      from: from.value,
      until: until.value,
      programmeId: programmeId.value || undefined,
      locationId: locationId.value || undefined,
      childId: childId.value || undefined,
      search: search.value || '',
      outcome: outcome.value,
    }
  }
  const dirty = computed(
    () => JSON.stringify(filter()) !== JSON.stringify(applied.value),
  )
  const workspace = useLoad<AttendanceReport | null>(
    () => childrenApi.attendanceReport(applied.value, offset.value),
    null,
  )
  const stats = computed(() => {
    const summary = workspace.data.value?.summary
    return [
      { label: 'Room visits', value: summary?.visits || 0 },
      { label: 'Children', value: summary?.children || 0 },
      { label: 'Still attending', value: summary?.attending || 0 },
      { label: 'Collected', value: summary?.collected || 0 },
      { label: 'Transfers out', value: summary?.transferred || 0 },
      {
        label: 'Supervisor collections',
        value: summary?.supervisorCollections || 0,
      },
    ]
  })
  const exportTask = useTask()
  const downloaded = ref(false)
  const journey = ref(false)
  const selected = ref<AttendanceVisit | null>(null)
  function outcomeLabel (value: string) {
    return outcomes.find(item => item.value === value)?.label || value
  }
  function openJourney (visit: AttendanceVisit) {
    selected.value = visit
    journey.value = true
  }
  function valid () {
    const start = Date.parse(from.value),
          end = Date.parse(until.value)
    filterError.value
      = !Number.isFinite(start)
        || !Number.isFinite(end)
        || end < start
        || end - start > 365 * 86_400_000
        ? 'Choose a date range of no more than 366 days.'
        : ''
    return !filterError.value
  }
  function apply () {
    if (!valid()) return
    exportTask.clear()
    offset.value = 0
    applied.value = filter()
    const query = Object.fromEntries(
      Object.entries(applied.value).filter(([, value]) => !!value),
    )
    if (JSON.stringify(route.query) === JSON.stringify(query))
      void workspace.load()
    else void router.replace({ query })
  }
  function reset () {
    from.value = monthStart
    until.value = today
    programmeId.value = null
    locationId.value = null
    childId.value = ''
    search.value = ''
    outcome.value = 'ALL'
    apply()
  }
  function clearChild () {
    childId.value = ''
    apply()
  }
  function changePage (delta: number) {
    offset.value += delta
    void workspace.load()
  }
  async function download () {
    if (dirty.value || filterError.value) return
    await exportTask.run(async () => {
      const csv = await childrenApi.exportAttendance(applied.value)
      const url = URL.createObjectURL(
        new Blob([csv], { type: 'text/csv;charset=utf-8' }),
      )
      const link = document.createElement('a')
      link.href = url
      link.download = 'children-attendance.csv'
      link.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
      downloaded.value = true
    })
  }
  const programmeSearch = ref('')
  const programmes = useLoad(
    () => childrenApi.programmes(programmeSearch.value),
    [] as Programme[],
  )
  const selectedProgramme = ref<Programme | null>(null)
  const programmeItems = computed(() =>
    selectedProgramme.value
    && !programmes.data.value.some(
      value => value.id === selectedProgramme.value?.id,
    )
      ? [...programmes.data.value, selectedProgramme.value]
      : programmes.data.value,
  )
  let programmeTimer: ReturnType<typeof setTimeout> | undefined
  function findProgrammes (value: string) {
    clearTimeout(programmeTimer)
    programmeSearch.value = value
    programmeTimer = setTimeout(() => {
      void programmes.load()
    }, 250)
  }
  watch(programmeId, id => {
    selectedProgramme.value
      = programmeItems.value.find(value => value.id === id) || null
  })
  const locationOffset = ref(0)
  const moreLocations = ref(false)
  const locationPages = ref<Location[]>([])
  const locations = useLoad(async () => {
    const page = await childrenApi.locations(locationOffset.value)
    moreLocations.value = page.length === 100
    locationPages.value = locationOffset.value
      ? [...locationPages.value, ...page]
      : page
    return locationPages.value
  }, [] as Location[])
  function loadMoreLocations () {
    locationOffset.value += 100
    void locations.load()
  }
  function retryCatalogues () {
    void programmes.load()
    void locations.load()
  }
  watch(
    () => route.query,
    query => {
      from.value = typeof query.from === 'string' ? query.from : monthStart
      until.value = typeof query.until === 'string' ? query.until : today
      programmeId.value
        = typeof query.programmeId === 'string' ? query.programmeId : null
      locationId.value
        = typeof query.locationId === 'string' ? query.locationId : null
      childId.value = typeof query.childId === 'string' ? query.childId : ''
      search.value = typeof query.search === 'string' ? query.search : ''
      outcome.value = typeof query.outcome === 'string' ? query.outcome : 'ALL'
      offset.value = 0
      applied.value = filter()
      if (valid()) void workspace.load()
    },
    { immediate: true },
  )
  retryCatalogues()
  onBeforeUnmount(() => clearTimeout(programmeTimer))
</script>
<style scoped>
  .report-filters {
    margin: 24px 0;
    padding: 24px;
  }
  .report-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px 20px;
  }
  .report-filter-actions,
  .report-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
  }
  .report-stats {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
    margin: 24px 0 16px;
  }
  .report-stats div {
    padding: 20px;
  }
  .report-stats dt {
    color: #75818b;
    font-size: 0.8rem;
  }
  .report-stats dd {
    font-size: 1.8rem;
    font-weight: 650;
    margin: 8px 0 0;
  }
  .report-row {
    display: grid;
    grid-template-columns: 1.3fr 1fr 1fr auto;
    gap: 20px;
    padding: 22px 24px;
    align-items: center;
    border-top: 1px solid #edf0f3;
    overflow-wrap: anywhere;
  }
  .report-row small {
    display: block;
    margin-top: 5px;
  }
  .report-child strong {
    font-size: 1rem;
  }
  .report-child small,
  .report-row p {
    color: #75818b;
  }
  .report-exception {
    color: #9d6319;
  }
  .report-pagination {
    padding: 16px 24px;
    border-top: 1px solid #edf0f3;
  }
  @media (max-width: 1100px) {
    .report-stats {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .report-row {
      grid-template-columns: 1fr 1fr;
    }
  }
  @media (max-width: 600px) {
    .report-filter-grid {
      grid-template-columns: minmax(0, 1fr);
    }
    .report-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .report-row {
      grid-template-columns: minmax(0, 1fr);
      gap: 12px;
    }
  }
</style>
