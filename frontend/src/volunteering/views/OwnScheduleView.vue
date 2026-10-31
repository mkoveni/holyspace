<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="YOUR GIFTS. YOUR NEXT STEP."
      subtitle="Review your shift, see what still needs preparation and respond with confidence."
      title="Your invitation to serve."
    />

    <ServingNavigation />

    <v-alert
      v-if="error"
      class="my-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <div class="d-flex justify-end my-4">
      <v-btn
        :loading="loading"
        prepend-icon="mdi-refresh"
        variant="text"
        @click="load"
      >
        Refresh invitations
      </v-btn>
    </div>

    <div class="serving-cards">
      <article
        v-for="entry in invitations"
        :key="entry.invitation.id"
        class="directory-surface serving-card"
      >
        <div class="serving-card__heading">
          <span class="serving-icon"><v-icon icon="mdi-calendar-heart" /></span>
          <v-chip size="small">{{ entry.effectiveStatus }}</v-chip>
        </div>

        <h2>{{ entry.team }}</h2>
        <strong>{{ entry.role }}</strong>

        <p class="directory-muted">
          {{ timeLabel(entry.startsAt) }}
          <br>
          Until {{ timeLabel(entry.endsAt) }}
        </p>

        <v-chip
          :color="entry.readiness.ready ? 'success' : 'warning'"
          size="small"
        >
          {{ entry.readiness.ready ? 'Ready to serve' : 'Preparation needed' }}
        </v-chip>

        <ul
          v-if="entry.readiness.issues.length > 0"
          class="serving-issues"
        >
          <li
            v-for="issue in entry.readiness.issues"
            :key="issue"
          >
            {{ issue }}
          </li>
        </ul>

        <template v-if="entry.effectiveStatus === 'PENDING'">
          <p class="directory-muted">
            Respond before {{ timeLabel(entry.invitation.expiresAt) }}.
            Acceptance confirms your commitment; outstanding requirements still
            need to be completed.
          </p>

          <div class="d-flex ga-2">
            <v-btn
              color="primary"
              @click="choose(entry, 'ACCEPTED')"
            >
              Accept
            </v-btn>

            <v-btn
              variant="tonal"
              @click="choose(entry, 'DECLINED')"
            >
              Decline
            </v-btn>
          </div>
        </template>
      </article>

      <article
        v-if="!loading && invitations.length === 0"
        class="directory-surface serving-card"
      >
        <h2>Nothing waiting for you</h2>

        <p class="directory-muted">
          Your coordinator can link your volunteer profile to your account and
          send an invitation.
        </p>
      </article>
    </div>

    <div class="serving-pagination">
      <v-btn
        :disabled="!offset || loading"
        variant="text"
        @click="page(-25)"
      >
        Previous
      </v-btn>

      <v-btn
        :disabled="invitations.length < 25 || loading"
        variant="text"
        @click="page(25)"
      >
        Next
      </v-btn>
    </div>

    <ServingDialog
      v-model="respondOpen"
      :save-label="
        decision === 'ACCEPTED' ? 'Accept invitation' : 'Decline invitation'
      "
      :submit="respond"
      subtitle="Your response applies to this invitation and its current shift details."
      :title="
        decision === 'ACCEPTED' ? 'Count me in' : 'Let your coordinator know'
      "
      @reload="load"
      @saved="load"
    >
      <p>{{ selected?.team }} · {{ selected?.role }}</p>

      <p
        v-if="selected"
        class="directory-muted mt-3"
      >
        {{ timeLabel(selected.startsAt) }}
      </p>
    </ServingDialog>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import {
    type InvitationView,
    servingApi,
    servingError,
    timeLabel,
  } from '../api'
  import ServingDialog from '../components/ServingDialog.vue'
  import ServingNavigation from '../components/ServingNavigation.vue'
  import '@/directory/directory.scss'
  import '../serving.scss'
  const invitations = ref<InvitationView[]>([]),
        offset = ref(0),
        loading = ref(false),
        error = ref(''),
        respondOpen = ref(false),
        selected = ref<InvitationView | null>(null),
        decision = ref('ACCEPTED')
  async function load () {
    loading.value = true
    error.value = ''
    try {
      invitations.value = await servingApi.own(offset.value)
    } catch (error_) {
      error.value = servingError(error_)
    } finally {
      loading.value = false
    }
  }
  function page (amount: number) {
    offset.value += amount
    void load()
  }
  function choose (entry: InvitationView, value: string) {
    selected.value = entry
    decision.value = value
    respondOpen.value = true
  }
  async function respond () {
    if (selected.value)
      await servingApi.respond(selected.value.invitation.id, {
        expectedVersion: selected.value.invitation.version,
        decision: decision.value,
      })
  }
  onMounted(load)
</script>
