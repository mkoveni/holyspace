<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="THOUGHTFUL CARE, AHEAD OF TIME"
      subtitle="Follow up on missing permissions, expiring verification and upcoming care reviews before a busy ministry day."
      title="The details that keep care safe."
    />

    <MinistryTabs active="follow-up" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-shield-check-outline" />
        </span>

        <div>
          <strong>A little preparation. Lasting reassurance.</strong>

          <p>
            Current coverage for actively enrolled children, with a clear next
            step for each follow-up.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">SAFEGUARDING FOLLOW-UP</span>
    </div>

    <section
      aria-label="Safeguarding follow-up filters"
      class="directory-surface children-section followup-filters"
    >
      <v-form @submit.prevent="apply">
        <div class="followup-filter-grid">
          <ProgrammePicker
            v-if="auth.hasPermission('children.read')"
            v-model="programmeId"
          />

          <v-text-field
            v-model="search"
            clearable
            label="Child name"
            prepend-inner-icon="mdi-magnify"
          />

          <v-select
            v-model="days"
            :items="[7, 14, 30, 60, 90]"
            label="Days ahead"
          />

          <v-select
            v-model="kind"
            item-title="label"
            item-value="value"
            :items="kinds"
            label="Follow-up type"
          />

          <v-select
            v-model="state"
            item-title="label"
            item-value="value"
            :items="states"
            label="Follow-up status"
          />
        </div>

        <div class="followup-actions">
          <p class="directory-muted">
            Through
            {{
              workspace.data.value
                ? dateLabel(workspace.data.value.through)
                : 'the selected horizon'
            }}
            · {{ workspace.data.value?.timeZone || 'church time zone' }}
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
          Apply your changes to refresh the follow-up list.
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
        @click="workspace.load"
      >
        Retry
      </v-btn>
    </v-alert>

    <template v-if="workspace.data.value">
      <dl class="followup-stats">
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
        Totals cover all follow-up types and statuses for this programme, child
        search and horizon, across every page.
      </p>

      <v-alert
        v-if="!workspace.data.value.careIncluded"
        class="mb-4"
        type="info"
        variant="tonal"
      >
        Care-review dates require care-support access. Private care details are
        opened separately through their audited workflow.
      </v-alert>
    </template>

    <section
      aria-label="Safeguarding follow-up results"
      class="directory-surface"
    >
      <div class="directory-toolbar">
        <h2>Follow-up list</h2>
        <v-spacer />

        <v-btn
          aria-label="Refresh safeguarding follow-up"
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
        v-for="issue in workspace.data.value?.issues || []"
        :key="issue.id"
        class="followup-row"
      >
        <div>
          <span class="directory-eyebrow">
            {{ kindLabel(issue.kind) }} · {{ issue.programme }}
          </span>

          <h3>{{ issue.childName }}</h3>
          <p>{{ issue.message }}</p>

          <small v-if="issue.expiresAt">
            Coverage ends
            {{ timeLabel(issue.expiresAt, workspace.data.value?.timeZone) }}
          </small>

          <small v-if="issue.reviewOn">
            Review {{ dateLabel(issue.reviewOn) }}
          </small>
        </div>

        <div class="followup-row-actions">
          <v-chip
            :color="issue.state === 'EXPIRING' ? 'warning' : 'error'"
            variant="tonal"
          >
            {{ stateLabel(issue.state) }}
          </v-chip>

          <v-btn
            v-if="canOpen(issue)"
            :to="destination(issue)"
            variant="tonal"
          >
            {{ actionLabel(issue) }}
            <v-icon
              end
              icon="mdi-arrow-top-right"
            />
          </v-btn>

          <v-btn
            v-if="
              issue.kind === 'CONSENT' &&
                auth.hasPermission('children.read') &&
                auth.hasPermission('people.read')
            "
            :to="{
              path: '/children/communications',
              query: {
                child: issue.childId,
                programme: issue.programmeId || undefined,
              },
            }"
            variant="text"
          >
            Prepare renewal request
          </v-btn>

          <small
            v-if="!canOpen(issue)"
            class="directory-muted"
          >
            Ask a coordinator with profile access to complete this follow-up.
          </small>
        </div>
      </article>

      <DirectoryEmpty
        v-if="
          !workspace.loading.value &&
            !workspace.error.value &&
            !workspace.data.value?.issues.length
        "
        description="No follow-ups match these filters. Session-day checks still confirm each child's eligibility and pickup authority."
        icon="mdi-shield-check-outline"
        title="A reassuring view"
      />

      <footer class="followup-paging">
        <span>
          {{ offset + (workspace.data.value?.total ? 1 : 0) }}–{{
            Math.min(offset + 25, workspace.data.value?.total || 0)
          }}
          of {{ workspace.data.value?.total || 0 }} follow-ups
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
  </div>
</template>
<script setup lang="ts">
  import type { FollowUpIssue, FollowUpPage } from '../types'
  import { computed, ref } from 'vue'
  import DirectoryEmpty from '@/directory/components/DirectoryEmpty.vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import ProgrammePicker from '../components/ProgrammePicker.vue'
  import { dateLabel, timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const auth = useAuthStore()
  const programmeId = ref<string | null>(null)
  const search = ref<string | null>('')
  const days = ref(30)
  const kind = ref('ALL')
  const state = ref('ALL')
  const offset = ref(0)
  const kinds = computed(() => [
    { value: 'ALL', label: 'All follow-ups' },
    { value: 'GUARDIAN', label: 'Guardian verification' },
    { value: 'CONSENT', label: 'Current-policy consent' },
    { value: 'PICKUP', label: 'Pickup permission' },
    ...(auth.hasPermission('children.support')
      ? [{ value: 'CARE', label: 'Care review' }]
      : []),
  ])
  const states = [
    { value: 'ALL', label: 'All statuses' },
    { value: 'MISSING', label: 'Missing or unusable' },
    { value: 'EXPIRED', label: 'Expired' },
    { value: 'EXPIRING', label: 'Coming up' },
    { value: 'DUE', label: 'Review due' },
  ]
  function filter () {
    return {
      programmeId: programmeId.value || undefined,
      search: search.value || '',
      days: days.value,
      kind: kind.value,
      state: state.value,
    }
  }
  const applied = ref(filter())
  const dirty = computed(
    () => JSON.stringify(applied.value) !== JSON.stringify(filter()),
  )
  const workspace = useLoad<FollowUpPage | null>(
    () => childrenApi.safeguardingFollowUp(applied.value, offset.value),
    null,
  )
  const stats = computed(() => {
    const summary = workspace.data.value?.summary
    return [
      { label: 'Children to follow up', value: summary?.children || 0 },
      { label: 'Missing coverage', value: summary?.missing || 0 },
      { label: 'Expired coverage', value: summary?.expired || 0 },
      { label: 'Coming up', value: summary?.expiring || 0 },
      ...(workspace.data.value?.careIncluded
        ? [{ label: 'Care reviews due', value: summary?.careDue || 0 }]
        : []),
    ]
  })
  function apply () {
    applied.value = filter()
    reload()
  }
  function reload () {
    offset.value = 0
    void workspace.load()
  }
  function page (delta: number) {
    offset.value += delta
    void workspace.load()
  }
  function kindLabel (value: string) {
    return kinds.value.find(item => item.value === value)?.label || value
  }
  function stateLabel (value: string) {
    return states.find(item => item.value === value)?.label || value
  }
  function canOpen (issue: FollowUpIssue) {
    return (
      auth.hasPermission('children.read')
      && (issue.action === 'policy'
        ? auth.hasPermission('children.manage')
        : auth.hasPermission('people.read')
          && (issue.action !== 'care' || auth.hasPermission('children.manage')))
    )
  }
  function destination (issue: FollowUpIssue) {
    return issue.action === 'policy'
      ? { path: `/children/programmes/${issue.programmeId}` }
      : {
        path: `/children/participants/${issue.childId}`,
        query: {
          ...(issue.programmeId ? { programme: issue.programmeId } : {}),
          ...(issue.action === 'care' ? {} : { followup: issue.action }),
        },
        hash: issue.action === 'care' ? '#child-care' : '',
      }
  }
  function actionLabel (issue: FollowUpIssue) {
    const labels: Record<string, string> = {
      policy: 'Review policy',
      care: 'Open care review',
      guardian: 'Verify guardian',
      consent: 'Record consent',
      pickup: 'Review pickup permission',
    }
    return labels[issue.action] || 'Review permission'
  }
  void workspace.load()
</script>
<style scoped>
  .followup-filters {
    margin: 24px 0;
    padding: 24px;
  }
  .followup-filter-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px 20px;
  }
  .followup-actions,
  .followup-row,
  .followup-paging {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
  }
  .followup-stats {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 12px;
    margin: 24px 0 16px;
  }
  .followup-stats div {
    padding: 20px;
  }
  .followup-stats dt {
    color: #75818b;
    font-size: 0.8rem;
  }
  .followup-stats dd {
    font-size: 1.8rem;
    font-weight: 650;
    margin: 8px 0 0;
  }
  .followup-row {
    padding: 24px;
    border-top: 1px solid #e7ecef;
    align-items: flex-start;
    overflow-wrap: anywhere;
  }
  .followup-row > div:first-child {
    flex: 1;
    min-width: 180px;
  }
  .followup-row h3 {
    margin: 7px 0;
  }
  .followup-row p,
  .followup-row small {
    color: #75818b;
  }
  .followup-row-actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
    max-width: 250px;
  }
  .followup-paging {
    padding: 16px 24px;
    border-top: 1px solid #e7ecef;
  }
  @media (max-width: 1000px) {
    .followup-stats {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (max-width: 650px) {
    .followup-filter-grid {
      grid-template-columns: 1fr;
    }
    .followup-stats {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }
</style>
