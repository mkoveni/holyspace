<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="A THOUGHTFUL RESPONSE"
      subtitle="Record what happened, give every concern a responsible person, and keep the next step in view."
      title="Care that follows through."
    >
      <v-btn
        color="primary"
        @click="reporting = true"
      >
        Record incident
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="incidents" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-shield-alert-outline" />
        </span>

        <div>
          <strong>Private details. Clear responsibility.</strong>

          <p>
            Incident descriptions and notes are available only through audited
            access. Follow-up continues after a session closes.
          </p>
        </div>
      </div>
    </div>

    <template v-if="canManage">
      <section class="directory-surface children-section my-6">
        <div class="incident-filters">
          <v-select
            v-model="status"
            item-title="label"
            item-value="value"
            :items="statuses"
            label="Incident status"
          />

          <v-checkbox
            v-model="overdueOnly"
            hide-details
            label="Review due only"
          />

          <v-btn
            :loading="workspace.loading.value"
            variant="tonal"
            @click="apply"
          >
            Apply filters
          </v-btn>
        </div>

        <v-btn
          v-if="sessionId"
          variant="text"
          @click="clearSession"
        >
          Show all sessions
        </v-btn>
      </section>

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

      <dl class="incident-stats">
        <div class="directory-surface">
          <dt>Matching incidents</dt>
          <dd>{{ workspace.data.value?.total || 0 }}</dd>
        </div>

        <div class="directory-surface">
          <dt>Reviews due</dt>
          <dd>{{ workspace.data.value?.overdue || 0 }}</dd>
        </div>

        <div class="directory-surface">
          <dt>Need an owner</dt>
          <dd>{{ workspace.data.value?.unassigned || 0 }}</dd>
        </div>
      </dl>

      <section class="directory-surface">
        <div class="directory-toolbar">
          <h2>Incident follow-up</h2>
          <v-spacer />

          <v-btn
            aria-label="Refresh incidents"
            icon="mdi-refresh"
            variant="text"
            @click="load"
          />
        </div>

        <article
          v-for="row in workspace.data.value?.incidents || []"
          :key="row.incident.id"
          class="incident-row"
        >
          <div>
            <span class="directory-eyebrow">
              {{ row.incident.category }} · {{ row.incident.severity }}
            </span>

            <h3>{{ row.childName }}</h3>
            <p>{{ timeLabel(row.incident.occurredAt) }}</p>

            <small>
              Review {{ dateLabel(row.incident.reviewOn) }} ·
              {{
                row.incident.ownerId
                  ? 'Responsibility assigned'
                  : 'Needs an owner'
              }}
            </small>
          </div>

          <div>
            <v-chip
              :color="row.incident.status === 'CLOSED' ? 'success' : 'warning'"
            >
              {{ row.incident.status.replaceAll('_', ' ') }}
            </v-chip>

            <v-btn
              class="ml-3"
              variant="tonal"
              @click="access(row.incident.id)"
            >
              Open private details
            </v-btn>
          </div>
        </article>

        <DirectoryEmpty
          v-if="
            !workspace.loading.value &&
              !workspace.error.value &&
              !workspace.data.value?.incidents.length
          "
          description="No incidents match this view. Use the status filter to review closed records."
          icon="mdi-shield-check-outline"
          title="A clear view"
        />

        <footer class="incident-paging">
          <span>{{ workspace.data.value?.total || 0 }} matching incidents</span>

          <div>
            <v-btn
              :disabled="!offset || workspace.loading.value"
              variant="text"
              @click="page(-25)"
            >
              Previous
            </v-btn>

            <v-btn
              :disabled="
                offset + 25 >= (workspace.data.value?.total || 0) ||
                  workspace.loading.value
              "
              variant="text"
              @click="page(25)"
            >
              Next
            </v-btn>
          </div>
        </footer>
      </section>

      <v-btn
        class="mt-5"
        variant="text"
        @click="purging = true"
      >
        Remove expired private details
      </v-btn>
    </template>

    <v-alert
      v-else
      class="my-6"
      type="info"
      variant="tonal"
    >
      You can report incidents for assigned sessions. Follow-up and private
      details require incident-coordinator access.
    </v-alert>

    <IncidentReportDialog
      v-model="reporting"
      :session-id="sessionId || undefined"
      @saved="reported"
    />

    <IncidentDetailDialog
      v-if="canManage && detailsOpen"
      v-model="detailsOpen"
      :incident-id="incidentId"
      @saved="load"
    />

    <MinistryDialog
      v-model="purging"
      save-label="Remove expired details"
      :submit="purge"
      subtitle="Remove descriptions and private history notes past their retention date. Incident metadata and audit records remain."
      title="Remove expired private details"
      @saved="load"
    >
      <p>
        This removes up to 100 expired private records. It cannot be undone.
      </p>
    </MinistryDialog>

    <v-alert
      v-if="notice"
      class="mt-4"
      type="success"
      variant="tonal"
    >
      {{ notice }}
    </v-alert>
  </div>
</template>
<script setup lang="ts">
  import type { IncidentPage } from '../types'
  import { computed, ref } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import IncidentDetailDialog from '../components/IncidentDetailDialog.vue'
  import IncidentReportDialog from '../components/IncidentReportDialog.vue'
  import MinistryDialog from '../components/MinistryDialog.vue'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { dateLabel, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore(),
        route = useRoute(),
        router = useRouter()
  const canManage = computed(() =>
    auth.hasPermission('children.incidents.manage'),
  )
  const sessionId = ref(
    typeof route.query.session === 'string' ? route.query.session : '',
  )
  const status = ref('ACTIVE'),
        overdueOnly = ref(false),
        offset = ref(0),
        reporting = ref(false),
        detailsOpen = ref(false),
        incidentId = ref(''),
        purging = ref(false),
        notice = ref('')
  const statuses = [
    { value: 'ACTIVE', label: 'Open follow-ups' },
    { value: 'ALL', label: 'All records' },
    { value: 'OPEN', label: 'Untriaged' },
    { value: 'IN_REVIEW', label: 'In review' },
    { value: 'CLOSED', label: 'Closed' },
  ]
  const applied = ref({ status: status.value, overdueOnly: overdueOnly.value })
  const workspace = useLoad<IncidentPage | null>(
    () =>
      childrenApi.incidents(
        { ...applied.value, sessionId: sessionId.value || undefined },
        offset.value,
      ),
    null,
  )
  function load () {
    if (canManage.value) void workspace.load()
  }
  function apply () {
    applied.value = { status: status.value, overdueOnly: overdueOnly.value }
    offset.value = 0
    load()
  }
  function page (delta: number) {
    offset.value += delta
    load()
  }
  function clearSession () {
    sessionId.value = ''
    void router.replace({ query: {} })
    offset.value = 0
    load()
  }
  function access (id: string) {
    incidentId.value = id
    detailsOpen.value = true
  }
  function reported () {
    notice.value
      = 'Incident recorded. An authorized coordinator can now follow up.'
    load()
  }
  async function purge () {
    const result = await childrenApi.purgeIncidents()
    notice.value = `Removed ${result.purged} expired private records.`
  }
  load()
</script>
<style scoped>
  .incident-filters,
  .incident-row,
  .incident-paging {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }
  .incident-filters > :first-child {
    min-width: 220px;
  }
  .incident-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin: 24px 0;
  }
  .incident-stats div {
    padding: 22px;
  }
  .incident-stats dt,
  .incident-row p,
  .incident-row small {
    color: var(--workspace-muted);
  }
  .incident-stats dd {
    font-size: 1.8rem;
    font-weight: 650;
    margin-top: 8px;
  }
  .incident-row {
    padding: 24px;
    border-top: 1px solid var(--workspace-border);
    overflow-wrap: anywhere;
  }
  .incident-row h3 {
    margin: 7px 0;
  }
  .incident-paging {
    padding: 18px 24px;
    border-top: 1px solid var(--workspace-border);
  }
  @media (max-width: 650px) {
    .incident-stats {
      grid-template-columns: 1fr;
    }
    .incident-row > div:last-child {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .incident-row .ml-3 {
      margin-left: 0 !important;
    }
  }
</style>
