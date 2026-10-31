<template>
  <section
    aria-labelledby="closeout-title"
    class="directory-surface children-section closeout-panel"
  >
    <div class="closeout-heading">
      <div>
        <span class="directory-eyebrow">A SAFE FINISH</span>
        <h2 id="closeout-title">Session closeout</h2>

        <p class="directory-muted">
          Account for every child before the team signs off.
        </p>
      </div>

      <v-btn
        :loading="report.loading.value"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="report.load"
      >
        Refresh closeout
      </v-btn>
    </div>

    <v-alert
      v-if="report.error.value"
      type="error"
      variant="tonal"
    >
      {{ report.error.value }}
    </v-alert>

    <template v-if="summary">
      <dl class="closeout-totals">
        <div>
          <dt>Children welcomed</dt>
          <dd>{{ summary.children }}</dd>
        </div>

        <div>
          <dt>Awaiting collection</dt>
          <dd>{{ summary.awaitingCollection }}</dd>
        </div>

        <div>
          <dt>Collected here</dt>
          <dd>{{ summary.collected }}</dd>
        </div>

        <div>
          <dt>Transferred out</dt>
          <dd>{{ summary.transferredOut }}</dd>
        </div>
      </dl>

      <p class="directory-muted">
        {{ summary.visits }} visits, including return visits. Transfers stay in
        this session’s record.
      </p>

      <v-alert
        class="my-4"
        :type="summary.awaitingCollection ? 'warning' : 'success'"
        variant="tonal"
      >
        {{
          summary.awaitingCollection
            ? 'Collection is still in progress. Every child must be collected or safely transferred before closure.'
            : terminal
              ? 'No children are awaiting collection. The session record is retained for review.'
              : 'Every child is accounted for. This session can be closed when the team has finished its review.'
        }}
      </v-alert>

      <p v-if="summary.labelsNeedingAttention">
        {{ summary.labelsNeedingAttention }}
        {{
          summary.labelsNeedingAttention === 1
            ? 'attending child needs'
            : 'attending children need'
        }}
        a new or replacement pickup label.
      </p>

      <p v-if="summary.failedHandoverAttempts">
        {{ summary.failedHandoverAttempts }}
        {{
          summary.failedHandoverAttempts === 1
            ? 'attending child has'
            : 'attending children have'
        }}
        unsuccessful code attempts to review before handover.
      </p>

      <p>
        {{ summary.supervisorCollections }}
        {{ summary.supervisorCollections === 1 ? 'collection' : 'collections' }}
        completed with a supervisor label exception. Identity and pickup
        authority checks still apply.
      </p>

      <v-alert
        v-if="summary.incidents?.unresolved"
        class="my-4"
        type="warning"
        variant="tonal"
      >
        {{ summary.incidents.unresolved }} unresolved incident follow-ups ·
        {{ summary.incidents.overdue }} reviews due ·
        {{ summary.incidents.unassigned }} without an owner. These continue
        after session closure.
        <v-btn
          :to="{
            path: '/children/incidents',
            query: { session: view.session.id },
          }"
          variant="text"
        >
          Review incident follow-up
        </v-btn>
      </v-alert>

      <div class="closeout-actions">
        <v-btn
          v-if="canStation && summary.awaitingCollection"
          variant="tonal"
          @click="emit('attending')"
        >
          Review attending children
        </v-btn>

        <v-btn
          v-if="canStation && summary.collected"
          variant="text"
          @click="emit('history')"
        >
          Review collections
        </v-btn>

        <v-btn
          v-if="!terminal"
          color="primary"
          :disabled="
            report.loading.value ||
              !!report.error.value ||
              summary.awaitingCollection > 0
          "
          variant="flat"
          @click="openReview"
        >
          Review & close session
        </v-btn>

        <v-chip
          v-else
          :color="view.session.status === 'CLOSED' ? 'success' : 'secondary'"
          variant="tonal"
        >
          {{
            view.session.status === 'CLOSED'
              ? 'Session closed'
              : 'Session cancelled'
          }}
        </v-chip>
      </div>

      <p class="directory-muted mt-3">
        Snapshot {{ timeLabel(summary.checkedAt, view.timeZone) }}. Closure
        checks attendance again.
      </p>
    </template>

    <MinistryDialog
      v-model="editor"
      save-label="Close session"
      :submit="close"
      :submit-disabled="
        !confirmed ||
          (!!summary?.incidents?.unresolved && !acknowledgeIncidents) ||
          !summary ||
          summary.awaitingCollection > 0 ||
          report.loading.value ||
          !!report.error.value
      "
      subtitle="Confirm the room is clear and record the team’s closeout note."
      title="Finish this session"
      @reload="reload"
      @saved="emit('reload')"
    >
      <v-checkbox
        v-if="summary?.incidents?.unresolved"
        v-model="acknowledgeIncidents"
        class="mb-4"
        hide-details
        label="I reviewed the outstanding incidents; follow-up will continue after closure."
      />

      <p>
        {{ summary?.children || 0 }} children welcomed ·
        {{ summary?.collected || 0 }} collections ·
        {{ summary?.transferredOut || 0 }} transfers out.
      </p>

      <v-alert
        v-if="summary?.awaitingCollection"
        type="warning"
        variant="tonal"
      >
        Children are still attending. Complete collection or a safe transfer
        first.
      </v-alert>

      <v-checkbox
        v-model="confirmed"
        label="I have checked the room and every child is accounted for."
        :rules="[(value) => !!value || 'Confirm the final room check.']"
      />

      <v-textarea
        v-model="reason"
        counter
        label="Closeout note *"
        maxlength="500"
        :rules="[required]"
      />
    </MinistryDialog>
  </section>
</template>
<script setup lang="ts">
  import type { SessionCloseout, SessionView } from '../types'
  import { computed, ref, watch } from 'vue'
  import { childrenApi } from '../api'
  import { required, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  import MinistryDialog from './MinistryDialog.vue'

  const props = defineProps<{
    view: SessionView
    revision: string
    canStation: boolean
  }>()
  const emit = defineEmits<{ reload: [], attending: [], history: [] }>()
  const report = useLoad<SessionCloseout | null>(
    () => childrenApi.closeout(props.view.session.id),
    null,
  )
  const summary = computed(() => report.data.value)
  const terminal = computed(() =>
    ['CLOSED', 'CANCELLED'].includes(props.view.session.status),
  )
  const editor = ref(false)
  const confirmed = ref(false)
  const acknowledgeIncidents = ref(false)
  const reason = ref('')
  watch(
    () => [props.view, props.revision],
    () => {
      void report.load()
    },
    { immediate: true },
  )
  async function openReview () {
    acknowledgeIncidents.value = false
    confirmed.value = false
    reason.value = ''
    await report.load()
    if (summary.value && !summary.value.awaitingCollection) editor.value = true
  }
  async function close () {
    if (!confirmed.value) throw new Error('Confirm the final room check.')
    if (summary.value?.incidents?.unresolved && !acknowledgeIncidents.value)
      throw new Error('Acknowledge outstanding incident follow-up.')
    await childrenApi.status({
      sessionId: props.view.session.id,
      expectedVersion: props.view.session.version,
      status: 'CLOSED',
      reason: reason.value,
      ...(summary.value?.incidents?.unresolved
        ? { acknowledgeIncidents: acknowledgeIncidents.value }
        : {}),
    })
  }
  function reload () {
    acknowledgeIncidents.value = false
    confirmed.value = false
    void report.load()
    emit('reload')
  }
</script>
<style scoped>
  .closeout-panel {
    margin: 24px 0;
    padding: 24px;
  }
  .closeout-heading,
  .closeout-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
  }
  .closeout-totals {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin: 24px 0;
  }
  .closeout-totals dt {
    color: rgb(var(--v-theme-on-surface-variant));
    font-size: 0.85rem;
  }
  .closeout-totals dd {
    margin: 4px 0 0;
    font-size: 1.8rem;
    font-weight: 650;
  }
  @media (max-width: 700px) {
    .closeout-totals {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
