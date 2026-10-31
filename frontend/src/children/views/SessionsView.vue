<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="CHILDREN & BELONGING"
      :subtitle="
        station
          ? 'Choose your session. Welcome each child with confidence.'
          : 'Prepare the room, gather the team and make space for every child.'
      "
      :title="
        station
          ? 'Ready for a warm welcome.'
          : 'Good days start with good care.'
      "
    >
      <v-btn
        v-if="!station && auth.hasPermission('children.manage')"
        color="primary"
        prepend-icon="mdi-plus"
        size="large"
        @click="editor = true"
      >
        Plan session
      </v-btn>

      <v-btn
        v-if="!station"
        prepend-icon="mdi-calendar-sync-outline"
        to="/children/session-series"
        variant="tonal"
      >
        Recurring schedules
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs :active="station ? 'station' : 'sessions'" />

    <div
      class="directory-banner"
      :class="{ 'directory-banner--sage': station }"
    >
      <div>
        <span class="directory-banner__icon">
          <v-icon
            :icon="station ? 'mdi-hand-wave-outline' : 'mdi-calendar-heart'"
          />
        </span>

        <div>
          <strong>
            {{
              station
                ? 'A familiar face. A safe handover.'
                : 'The thoughtful details make the difference.'
            }}
          </strong>

          <p>
            {{
              station
                ? 'Your present team assignments appear here. Supervisors can open any session.'
                : 'Capacity reflects room size and the qualified team actually present.'
            }}
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">EVERY ARRIVAL MATTERS</span>
    </div>

    <section class="directory-surface">
      <div class="directory-toolbar">
        <v-text-field
          v-model="day"
          density="compact"
          hide-details
          label="Session date"
          type="date"
        />

        <v-btn
          prepend-icon="mdi-calendar-today"
          variant="text"
          @click="day = today"
        >
          Today
        </v-btn>

        <v-spacer />

        <v-btn
          aria-label="Refresh sessions"
          icon="mdi-refresh"
          variant="text"
          @click="workspace.load"
        />
      </div>

      <v-alert
        v-if="workspace.error.value"
        class="ma-5"
        type="error"
        variant="tonal"
      >
        {{ workspace.error.value }}
        <v-btn
          variant="text"
          @click="workspace.load"
        >
          Retry
        </v-btn>
      </v-alert>

      <v-skeleton-loader
        v-if="workspace.loading.value"
        type="card@2"
      />

      <div
        v-else-if="workspace.data.value.length > 0"
        class="children-grid pa-5"
      >
        <article
          v-for="view in workspace.data.value"
          :key="view.session.id"
          class="directory-surface children-card"
        >
          <div class="d-flex align-center justify-space-between ga-3">
            <span class="children-card__icon">
              <v-icon icon="mdi-home-heart" />
            </span>

            <span
              class="directory-badge"
              :class="{
                'directory-badge--active_member':
                  view.session.status === 'OPEN',
                'directory-badge--visitor': view.staffingNeedsReview,
              }"
            >
              <i />
              {{ statusLabel(view.session.status) }}
            </span>
          </div>

          <div>
            <h2>{{ view.className }}</h2>

            <p>
              {{ view.roomName }} ·
              {{ timeLabel(view.session.startsAt, view.timeZone) }}
              <br>
              {{ view.timeZone }}
            </p>
          </div>

          <div>
            <div class="d-flex justify-space-between children-notice">
              <span>{{ view.occupancy }} attending</span>

              <span>
                {{ Math.max(0, view.effectiveCapacity - view.occupancy) }}
                places available
              </span>
            </div>

            <div class="children-capacity">
              <i
                :style="{
                  width: `${Math.min(100, (view.occupancy / Math.max(1, view.effectiveCapacity)) * 100)}%`,
                }"
              />
            </div>

            <p
              v-if="view.staffingNeedsReview"
              class="children-notice"
            >
              Check staffing before welcoming new arrivals.
            </p>
          </div>

          <footer>
            <small class="children-notice">
              {{ view.staff.filter((s) => s.present).length }} team members
              present
            </small>

            <v-btn
              v-if="!station && auth.hasPermission('children.manage')"
              prepend-icon="mdi-calendar-sync-outline"
              variant="text"
              @click="repeat(view)"
            >
              Repeat session
            </v-btn>

            <v-btn
              append-icon="mdi-arrow-top-right"
              :to="{
                path: `/children/sessions/${view.session.id}`,
                query: station ? { station: '1' } : {},
              }"
              variant="text"
            >
              Open session
            </v-btn>
          </footer>
        </article>
      </div>

      <DirectoryEmpty
        v-else-if="!workspace.error.value"
        :description="
          station
            ? 'No sessions are assigned for this date. A ministry coordinator can assign you and mark your presence.'
            : 'There are no sessions for this date. Plan one to bring your programme to life.'
        "
        :title="
          station
            ? 'Your next welcome is on its way'
            : 'A day full of possibility'
        "
      >
        <v-btn
          v-if="!station && auth.hasPermission('children.manage')"
          color="primary"
          @click="editor = true"
        >
          Plan session
        </v-btn>
      </DirectoryEmpty>

      <footer class="directory-pagination">
        <span>25 sessions per page</span>

        <div>
          <v-btn
            :disabled="!offset || workspace.loading.value"
            variant="text"
            @click="offset -= 25"
          >
            Previous
          </v-btn>

          <v-btn
            :disabled="
              workspace.data.value.length < 25 || workspace.loading.value
            "
            variant="text"
            @click="offset += 25"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <RecurringSessionDialog
      v-model="repeatEditor"
      :source="repeatSource"
      @created="openSeries"
      @reload="refreshRepeatSource"
    />

    <SessionDialog
      v-model="editor"
      :default-class="String(route.query.class || '')"
      :default-programme="String(route.query.programme || '')"
      @saved="workspace.load"
    />
  </div>
</template>
<script setup lang="ts">
  import type { SessionView } from '../types'
  import { ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { churchToday } from '@/directory/presentation'
  import { useCreateAction } from '@/navigation/useCreateAction'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import RecurringSessionDialog from '../components/RecurringSessionDialog.vue'
  import SessionDialog from '../components/SessionDialog.vue'
  import { sessionRange, statusLabel, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const props = withDefaults(defineProps<{ station?: boolean }>(), {
    station: false,
  })
  const auth = useAuthStore()
  const route = useRoute()
  const router = useRouter()
  const repeatEditor = ref(false)
  const repeatSource = ref<SessionView | null>(null)
  function repeat (view: SessionView) {
    repeatSource.value = view
    repeatEditor.value = true
  }
  async function refreshRepeatSource () {
    await workspace.load()
    repeatSource.value
      = workspace.data.value.find(
        value => value.session.id === repeatSource.value?.session.id,
      ) || repeatSource.value
  }
  function openSeries (id: string) {
    void router.push({
      path: '/children/session-series',
      query: { series: id },
    })
  }
  const today = churchToday('Africa/Johannesburg')
  const day = ref(today)
  const offset = ref(0)
  const editor = ref(false)
  useCreateAction(
    () => !props.station && auth.hasPermission('children.manage'),
    () => {
      editor.value = true
    },
  )
  const workspace = useLoad(() => {
    const { from, until } = sessionRange(day.value || today)
    return childrenApi.sessions(from, until, offset.value, props.station)
  }, [])
  watch(
    [day, () => props.station],
    () => {
      offset.value = 0
      void workspace.load()
    },
    { immediate: true },
  )
  watch(offset, workspace.load)
</script>
