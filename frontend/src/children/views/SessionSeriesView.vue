<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="A STEADY RHYTHM OF CARE"
      subtitle="Turn a familiar session into a thoughtful weekly rhythm, with space to review every date."
      title="Good weeks, already planned."
    >
      <v-btn
        prepend-icon="mdi-calendar-heart"
        to="/children/sessions"
        variant="tonal"
      >
        {{
          auth.hasPermission('children.manage')
            ? 'Choose a session to repeat'
            : 'Browse sessions'
        }}
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="series" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-calendar-sync-outline" />
        </span>

        <div>
          <strong>Plan once. Prepare each welcome.</strong>

          <p>
            Open any session and choose Repeat session to reuse its class, room,
            seats and assigned team.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">RECURRING SCHEDULES</span>
    </div>

    <v-alert
      v-if="workspace.error.value"
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

    <section class="directory-surface mt-6">
      <div class="directory-toolbar">
        <h2>Your recurring schedules</h2>
        <v-spacer />

        <v-btn
          aria-label="Refresh recurring schedules"
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

      <article
        v-for="series in workspace.data.value?.series || []"
        :key="series.id"
        class="series-list-row"
      >
        <div>
          <span class="directory-eyebrow">
            {{ series.className }} · {{ series.roomName }}
          </span>

          <h3>{{ series.name }}</h3>

          <p>
            {{ dateLabel(series.firstDate) }} · every
            {{
              series.intervalWeeks === 1
                ? 'week'
                : `${series.intervalWeeks} weeks`
            }}
            · {{ series.timeZone }}
          </p>

          <small>
            {{ series.totalSessions }} sessions ·
            {{ series.plannedSessions }} planned ·
            {{ series.cancelledSessions }} cancelled
          </small>

          <v-chip
            v-if="series.stopped"
            class="ml-3"
            size="small"
            variant="tonal"
          >
            Remaining dates cancelled
          </v-chip>
        </div>

        <v-btn
          variant="tonal"
          @click="show(series.id)"
        >
          View dates
          <v-icon
            end
            icon="mdi-arrow-top-right"
          />
        </v-btn>
      </article>

      <DirectoryEmpty
        v-if="
          !workspace.loading.value &&
            !workspace.error.value &&
            !workspace.data.value?.series.length
        "
        description="Plan a session, assign its team, then choose Repeat session to preview your weekly dates."
        icon="mdi-calendar-sync-outline"
        title="Make room for the weeks ahead"
      >
        <v-btn
          class="mt-4"
          to="/children/sessions"
          variant="tonal"
        >
          Browse sessions
        </v-btn>
      </DirectoryEmpty>

      <footer class="series-paging">
        <span>{{ workspace.data.value?.total || 0 }} recurring schedules</span>

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
              offset + 25 >= (workspace.data.value?.total || 0) ||
                workspace.loading.value
            "
            variant="text"
            @click="offset += 25"
          >
            Next
          </v-btn>
        </div>
      </footer>
    </section>

    <v-dialog
      v-model="detailOpen"
      max-width="820"
      scrollable
    >
      <v-card class="directory-form">
        <div class="directory-form__header">
          <div>
            <span class="directory-eyebrow">THE WEEKS AHEAD</span>

            <h2>
              {{ detail.data.value?.series.name || 'Recurring schedule' }}
            </h2>

            <p>
              Each session keeps its own team presence, readiness and attendance
              record.
            </p>
          </div>

          <v-btn
            aria-label="Close recurring schedule"
            icon="mdi-close"
            variant="text"
            @click="detailOpen = false"
          />
        </div>

        <v-card-text>
          <v-progress-linear
            v-if="detail.loading.value"
            color="primary"
            indeterminate
          />

          <v-alert
            v-if="detail.error.value"
            type="error"
            variant="tonal"
          >
            {{ detail.error.value }}
            <v-btn
              variant="text"
              @click="detail.load"
            >
              Retry
            </v-btn>
          </v-alert>

          <template v-if="detail.data.value">
            <v-btn
              v-if="
                auth.hasPermission('children.manage') &&
                  !detail.data.value.series.stopped &&
                  detail.data.value.series.plannedSessions
              "
              class="mb-5"
              color="error"
              variant="tonal"
              @click="openCancel"
            >
              Cancel future planned dates
            </v-btn>

            <article
              v-for="session in detail.data.value.sessions"
              :key="session.id"
              class="series-date-row"
            >
              <div>
                <strong>
                  {{
                    timeLabel(
                      session.startsAt,
                      detail.data.value.series.timeZone,
                    )
                  }}
                </strong>

                <small>{{ statusLabel(session.status) }}</small>
              </div>

              <v-btn
                :to="`/children/sessions/${session.id}`"
                variant="text"
              >
                Open session
                <v-icon
                  end
                  icon="mdi-arrow-top-right"
                />
              </v-btn>
            </article>
          </template>
        </v-card-text>
      </v-card>
    </v-dialog>

    <MinistryDialog
      v-model="cancelEditor"
      save-label="Cancel future planned sessions"
      :submit="cancel"
      subtitle="Cancel future planned dates together. Past, open and completed sessions keep their history."
      title="Leave room for a change"
      @reload="reload"
      @saved="reload"
    >
      <v-alert
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Only future sessions that are still planned will be cancelled. Any open
        or review sessions need individual attention.
      </v-alert>

      <v-textarea
        v-model="reason"
        label="Cancellation reason *"
        maxlength="500"
        :rules="[required]"
      />
    </MinistryDialog>
  </div>
</template>
<script setup lang="ts">
  import type { SessionSeriesDetail, SessionSeriesPage } from '../types'
  import { ref, watch } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryDialog from '../components/MinistryDialog.vue'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { dateLabel, required, statusLabel, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore()
  const route = useRoute()
  const router = useRouter()
  const offset = ref(0)
  const workspace = useLoad<SessionSeriesPage | null>(
    () => childrenApi.sessionSeries(offset.value),
    null,
  )
  const selectedId = ref('')
  const detailOpen = ref(false)
  const cancelEditor = ref(false)
  const reason = ref('')
  const detail = useLoad<SessionSeriesDetail | null>(
    () => childrenApi.sessionSeriesDetail(selectedId.value),
    null,
  )
  function show (id: string) {
    selectedId.value = id
    detail.data.value = null
    detailOpen.value = true
    void detail.load()
  }
  function openCancel () {
    reason.value = ''
    cancelEditor.value = true
  }
  async function cancel () {
    if (!detail.data.value) return
    await childrenApi.cancelSeries(
      selectedId.value,
      detail.data.value.series.version,
      reason.value,
    )
  }
  async function reload () {
    await Promise.all([workspace.load(), detail.load()])
    if (detail.data.value?.series.stopped) cancelEditor.value = false
  }
  watch(offset, workspace.load)
  watch(
    () => route.query.series,
    id => {
      if (typeof id === 'string') show(id)
    },
    { immediate: true },
  )
  watch(selectedId, () => {
    cancelEditor.value = false
    reason.value = ''
  })
  watch(detailOpen, value => {
    if (!value && route.query.series) void router.replace({ query: {} })
  })
  void workspace.load()
</script>
<style scoped>
  .series-list-row,
  .series-date-row,
  .series-paging {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 22px 24px;
    border-top: 1px solid var(--workspace-border);
    flex-wrap: wrap;
  }
  .series-list-row h3 {
    margin: 7px 0;
  }
  .series-list-row p,
  .series-list-row small,
  .series-date-row small {
    color: var(--workspace-muted);
  }
  .series-date-row small {
    display: block;
    margin-top: 4px;
  }
  .series-list-row > div {
    min-width: 0;
    overflow-wrap: anywhere;
  }
  .series-paging {
    padding-top: 14px;
    padding-bottom: 14px;
  }
</style>
