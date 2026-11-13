<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="THE WEEK AHEAD"
      subtitle="Upcoming sessions, team readiness and the follow-ups that need your attention, brought together in one place."
      title="A clear view. A caring welcome."
    >
      <v-btn
        :loading="workspace.loading.value"
        prepend-icon="mdi-refresh"
        variant="tonal"
        @click="workspace.load"
      >
        Refresh overview
      </v-btn>
    </DirectoryHeading>

    <MinistryTabs active="overview" />

    <div class="directory-banner directory-banner--sage">
      <div>
        <span class="directory-banner__icon">
          <v-icon icon="mdi-view-dashboard-outline" />
        </span>

        <div>
          <strong>The right next step, within reach.</strong>

          <p>
            Only the sections you have access to appear. Private incident and
            care details stay in their restricted workflows.
          </p>
        </div>
      </div>

      <span class="directory-banner__caption">CHILDREN MINISTRY OVERVIEW</span>
    </div>

    <v-alert
      v-if="workspace.error.value"
      class="my-6"
      type="error"
      variant="tonal"
    >
      {{ workspace.error.value }}
    </v-alert>

    <template v-if="workspace.data.value">
      <p class="directory-muted my-5">
        Updated
        {{
          timeLabel(
            workspace.data.value.checkedAt,
            workspace.data.value.timeZone,
          )
        }}
        · {{ workspace.data.value.timeZone }}
      </p>

      <div class="overview-grid">
        <section
          v-if="workspace.data.value.upcoming"
          class="directory-surface overview-section"
        >
          <span class="directory-eyebrow">NEXT SEVEN DAYS</span>

          <h2>
            {{ workspace.data.value.upcoming.total }} upcoming
            {{
              workspace.data.value.upcoming.total === 1 ? 'session' : 'sessions'
            }}
          </h2>

          <article
            v-for="session in workspace.data.value.upcoming.sessions"
            :key="session.id"
            class="overview-item"
          >
            <strong>{{ session.ministryClass }} · {{ session.room }}</strong>

            <p>
              {{ session.programme }} ·
              {{ timeLabel(session.startsAt, workspace.data.value.timeZone) }}
            </p>

            <v-btn
              :to="`/children/sessions/${session.id}`"
              variant="text"
            >
              Open session
            </v-btn>
          </article>

          <p
            v-if="workspace.data.value.upcoming.sessions.length === 0"
            class="directory-muted"
          >
            No upcoming sessions in this window.
          </p>

          <v-btn
            class="mt-4"
            to="/children/sessions"
            variant="tonal"
          >
            All sessions
          </v-btn>
        </section>

        <section
          v-if="workspace.data.value.teams || workspace.data.value.teamNotice"
          class="directory-surface overview-section"
        >
          <span class="directory-eyebrow">TEAM READINESS</span>

          <h2>
            {{
              workspace.data.value.teams
                ? `${workspace.data.value.teams.summary.attention} ${workspace.data.value.teams.summary.attention === 1 ? 'session needs' : 'sessions need'} attention`
                : 'Review the planning scope'
            }}
          </h2>

          <p v-if="workspace.data.value.teamNotice">
            {{ workspace.data.value.teamNotice }}
          </p>

          <template v-if="workspace.data.value.teams">
            <p>
              {{ workspace.data.value.teams.summary.missingLeaders }} need
              leaders ·
              {{ workspace.data.value.teams.summary.shortCapacity }} short on
              expected capacity ·
              {{ workspace.data.value.teams.summary.clashes }} with clashes.
            </p>

            <article
              v-for="row in workspace.data.value.teams.sessions"
              :key="row.session.id"
              class="overview-item"
            >
              <strong>{{ row.ministryClass }} · {{ row.room }}</strong>

              <p>
                {{ row.plannedCapacity }} / {{ row.session.seatLimit }} expected
                seats
              </p>
            </article>
          </template>

          <v-btn
            class="mt-4"
            to="/children/team-planning"
            variant="tonal"
          >
            Plan teams
          </v-btn>
        </section>

        <section
          v-if="
            workspace.data.value.safeguarding ||
              workspace.data.value.safeguardingNotice
          "
          class="directory-surface overview-section"
        >
          <span class="directory-eyebrow">SAFEGUARDING FOLLOW-UP</span>

          <h2>
            {{
              workspace.data.value.safeguarding
                ? `${workspace.data.value.safeguarding.total} ${workspace.data.value.safeguarding.total === 1 ? 'follow-up' : 'follow-ups'} in the next 30 days`
                : 'Choose a programme to review'
            }}
          </h2>

          <p v-if="workspace.data.value.safeguardingNotice">
            {{ workspace.data.value.safeguardingNotice }}
          </p>

          <article
            v-for="issue in workspace.data.value.safeguarding?.issues || []"
            :key="issue.id"
            class="overview-item"
          >
            <strong>{{ issue.childName }}</strong>
            <p>{{ issue.message }}</p>
          </article>

          <v-btn
            class="mt-4"
            to="/children/safeguarding-follow-up"
            variant="tonal"
          >
            Review safeguarding
          </v-btn>
        </section>

        <section
          v-if="workspace.data.value.incidents"
          class="directory-surface overview-section"
        >
          <span class="directory-eyebrow">RESTRICTED INCIDENT FOLLOW-UP</span>

          <h2>
            {{ workspace.data.value.incidents.total }} unresolved
            {{
              workspace.data.value.incidents.total === 1
                ? 'incident'
                : 'incidents'
            }}
          </h2>

          <p>
            {{ workspace.data.value.incidents.overdue }} reviews due ·
            {{ workspace.data.value.incidents.unassigned }} need an owner.
          </p>

          <p class="directory-muted mt-4">
            Descriptions and private notes are opened separately through audited
            access.
          </p>

          <v-btn
            class="mt-4"
            to="/children/incidents"
            variant="tonal"
          >
            Review incidents
          </v-btn>
        </section>

        <section
          v-if="workspace.data.value.communications"
          class="directory-surface overview-section"
        >
          <span class="directory-eyebrow">GUARDIAN EMAIL</span>

          <h2>
            {{ workspace.data.value.communications.failed }}
            {{
              workspace.data.value.communications.failed === 1
                ? 'delivery needs'
                : 'deliveries need'
            }}
            attention
          </h2>

          <p>
            {{ workspace.data.value.communications.waiting }} scheduled, queued
            or sending. Uncertain outcomes require reconciliation.
          </p>

          <v-btn
            class="mt-4"
            to="/children/email-deliveries"
            variant="tonal"
          >
            Review deliveries
          </v-btn>

          <v-btn
            class="mt-4"
            to="/children/communications"
            variant="text"
          >
            Prepare a message
          </v-btn>
        </section>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
  import type { MinistryOverview } from '../types'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { childrenApi } from '../api'
  import MinistryTabs from '../components/MinistryTabs.vue'
  import { timeLabel } from '../presentation'
  import { useLoad } from '../useLoad'
  const workspace = useLoad<MinistryOverview | null>(
    () => childrenApi.ministryOverview(),
    null,
  )
  void workspace.load()
</script>
<style scoped>
  .overview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    margin-bottom: 32px;
  }
  .overview-section {
    padding: 28px;
    overflow-wrap: anywhere;
  }
  .overview-section h2 {
    margin: 12px 0;
    font-size: 1.35rem;
  }
  .overview-section > p,
  .overview-item p {
    color: var(--workspace-muted);
    line-height: 1.7;
  }
  .overview-item {
    padding: 18px 0;
    border-bottom: 1px solid var(--workspace-border);
  }
  .overview-item p {
    margin-top: 6px;
  }
  @media (max-width: 850px) {
    .overview-grid {
      grid-template-columns: 1fr;
    }
  }
</style>
