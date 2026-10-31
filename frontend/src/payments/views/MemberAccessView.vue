<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="ADMINISTRATION · MEMBER ACCESS"
      subtitle="Verify member accounts before granting access to personal profiles."
      title="A place to belong."
    >
      <v-btn
        color="primary"
        prepend-icon="mdi-account-check-outline"
        @click="newLink"
      >
        Verify member access
      </v-btn>
    </DirectoryHeading>

    <v-alert
      v-if="error"
      class="mb-4"
      type="error"
      variant="tonal"
    >
      {{ error }}
    </v-alert>

    <v-alert
      v-if="success"
      class="mb-4"
      type="success"
      variant="tonal"
    >
      {{ success }}
    </v-alert>

    <v-progress-linear
      v-if="loading"
      indeterminate
    />

    <section class="directory-surface giving-section mb-5">
      <div class="giving-section-heading">
        <div>
          <span class="directory-eyebrow">VERIFIED IDENTITY</span>
          <h2>Member account access</h2>

          <p class="directory-muted">
            One account, one person. Verify identity directly before granting
            access to their personal profile.
          </p>
        </div>

        <v-btn
          :loading="loading"
          variant="text"
          @click="refresh"
        >
          Refresh
        </v-btn>
      </div>

      <p
        v-if="links.length === 0"
        class="directory-muted py-5"
      >
        No member accounts have been verified yet.
      </p>

      <article
        v-for="link in links"
        :key="link.accountId"
        class="access-row"
      >
        <div>
          <strong>{{ link.personName }}</strong>
          <p class="directory-muted">{{ link.accountName }}</p>
        </div>

        <div class="access-actions">
          <v-chip
            :color="link.active ? 'success' : undefined"
            size="small"
          >
            {{ link.active ? 'Verified access' : 'Revoked' }}
          </v-chip>

          <v-btn
            :disabled="working"
            variant="text"
            @click="changeLink(link)"
          >
            {{ link.active ? 'Revoke access' : 'Verify & restore' }}
          </v-btn>
        </div>
      </article>

      <div class="access-pages">
        <v-btn
          :disabled="!offset || loading"
          variant="text"
          @click="page(-25)"
        >
          Previous
        </v-btn>

        <v-btn
          :disabled="!hasMore || loading"
          variant="text"
          @click="page(25)"
        >
          Next
        </v-btn>
      </div>
    </section>

    <v-dialog
      v-model="dialog"
      max-width="640"
      persistent
    >
      <v-card rounded="xl">
        <v-card-title class="pa-6 access-title">
          {{
            selected
              ? selected.active
                ? 'Revoke member access'
                : 'Restore verified access'
              : 'Verify member identity'
          }}
        </v-card-title>

        <v-card-text>
          <v-alert
            v-if="dialogError"
            class="mb-4"
            type="error"
            variant="tonal"
          >
            {{ dialogError }}
          </v-alert>

          <template v-if="!selected">
            <v-autocomplete
              v-model="accountId"
              v-model:search="accountSearch"
              :disabled="working"
              item-title="label"
              item-value="id"
              :items="accounts"
              label="User account"
              :loading="searchingAccounts"
              no-filter
              @update:search="searchAccounts"
            />

            <v-autocomplete
              v-model="personId"
              v-model:search="personSearch"
              :disabled="working || !auth.hasPermission('people.read')"
              item-title="label"
              item-value="id"
              :items="people"
              label="People record"
              :loading="searchingPeople"
              no-filter
              @update:search="searchPeople"
            />

            <v-alert
              v-if="!auth.hasPermission('people.read')"
              type="info"
              variant="tonal"
            >
              People directory access is required to select a person.
            </v-alert>
          </template>

          <p
            v-else
            class="mb-4"
          >
            <strong>{{ selected.personName }}</strong>
            · {{ selected.accountName }}
          </p>

          <v-alert
            class="mb-4"
            type="info"
            variant="tonal"
          >
            {{
              selected?.active
                ? 'Access is removed immediately. Verification history is retained. Giving access and guardian permissions remain independent.'
                : 'Confirm that this account belongs to this person. Matching email addresses or household membership are not proof of identity. This grants access to the person’s own profile. It does not grant giving, household or child collection access.'
            }}
          </v-alert>

          <v-textarea
            v-model="evidence"
            counter="1000"
            :disabled="working"
            :label="
              selected?.active
                ? 'Revocation reason'
                : 'Identity verification evidence'
            "
            maxlength="1000"
          />

          <v-checkbox
            v-model="verified"
            :disabled="working"
            hide-details
            :label="
              selected?.active
                ? 'I confirm this access should be revoked.'
                : 'I verified this account holder’s identity and People record.'
            "
          />
        </v-card-text>

        <v-card-actions class="pa-5 access-actions">
          <v-btn
            :disabled="working"
            @click="dialog = false"
          >
            Cancel
          </v-btn>

          <v-btn
            :color="selected?.active ? 'error' : 'primary'"
            :disabled="
              !verified ||
                !evidence.trim() ||
                (!selected && (!accountId || !personId))
            "
            :loading="working"
            @click="save"
          >
            {{ selected?.active ? 'Revoke access' : 'Confirm verified access' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup lang="ts">
  import type { MemberLink } from '../member-access-api'
  import type { UserAccount } from '@/types/userAccount'
  import { onBeforeUnmount, onMounted, ref } from 'vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { directoryApi } from '@/directory/api'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { memberAccessApi } from '../member-access-api'
  import '@/giving/giving.css'
  const auth = useAuthStore()
  const links = ref<MemberLink[]>([]),
        hasMore = ref(false),
        offset = ref(0)
  const loading = ref(false),
        working = ref(false),
        error = ref(''),
        success = ref(''),
        dialogError = ref(''),
        dialog = ref(false)
  const selected = ref<MemberLink | null>(null),
        accountId = ref<string | null>(null),
        personId = ref<string | null>(null),
        evidence = ref(''),
        verified = ref(false)
  const accountSearch = ref(''),
        personSearch = ref(''),
        accounts = ref<{ id: string, label: string }[]>([]),
        people = ref<{ id: string, label: string }[]>([])
  const searchingAccounts = ref(false),
        searchingPeople = ref(false)
  let generation = 0,
      accountGeneration = 0,
      personGeneration = 0
  async function refresh () {
    const run = ++generation
    loading.value = true
    error.value = ''
    try {
      const data = await memberAccessApi.access(offset.value)
      if (run !== generation) return
      links.value = data.links
      hasMore.value = data.hasMore
    } catch (error_) {
      if (run === generation) {
        links.value = []
        error.value = extractErrorMessage(error_)
      }
    } finally {
      if (run === generation) loading.value = false
    }
  }
  async function searchAccounts (search: string) {
    if (!dialog.value || selected.value) return
    const run = ++accountGeneration
    searchingAccounts.value = true
    try {
      const data = (
        await useApi().get<{ items: UserAccount[] }>('/api/users', {
          params: { search, limit: 25 },
        })
      ).data
      if (run === accountGeneration)
        accounts.value = data.items
          .filter(a => a.status === 'active')
          .map(a => ({
            id: a.id,
            label: `${a.displayName || a.username} · ${a.email || a.username}`,
          }))
    } catch (error_) {
      if (run === accountGeneration)
        dialogError.value = extractErrorMessage(error_)
    } finally {
      if (run === accountGeneration) searchingAccounts.value = false
    }
  }
  async function searchPeople (search: string) {
    if (!dialog.value || selected.value || !auth.hasPermission('people.read'))
      return
    const run = ++personGeneration
    searchingPeople.value = true
    try {
      const data = await directoryApi.people({ search })
      if (run === personGeneration)
        people.value = data
          .filter(p => !p.archivedAt && p.details.lifeStatus !== 'DECEASED')
          .map(p => ({
            id: p.id,
            label: `${p.details.firstName} ${p.details.lastName}${p.details.dateOfBirth ? ` · Born ${p.details.dateOfBirth}` : ''}`,
          }))
    } catch (error_) {
      if (run === personGeneration)
        dialogError.value = extractErrorMessage(error_)
    } finally {
      if (run === personGeneration) searchingPeople.value = false
    }
  }
  function reset () {
    evidence.value = ''
    verified.value = false
    dialogError.value = ''
    success.value = ''
  }
  function newLink () {
    reset()
    selected.value = null
    accountId.value = null
    personId.value = null
    dialog.value = true
    searchAccounts('')
    searchPeople('')
  }
  function changeLink (link: MemberLink) {
    reset()
    selected.value = link
    dialog.value = true
  }
  async function save () {
    if (working.value || !verified.value) return
    working.value = true
    dialogError.value = ''
    try {
      await memberAccessApi.link(selected.value?.accountId || accountId.value!, {
        personId: selected.value?.personId || personId.value,
        active: !selected.value?.active,
        expectedVersion: selected.value?.version || 0,
        evidence: evidence.value.trim(),
      })
      dialog.value = false
      success.value = selected.value?.active
        ? 'Member access revoked.'
        : 'Verified member access saved.'
      await refresh()
    } catch (error_) {
      dialogError.value = extractErrorMessage(error_)
    } finally {
      working.value = false
    }
  }
  function page (change: number) {
    offset.value += change
    refresh()
  }
  onMounted(refresh)
  onBeforeUnmount(() => {
    generation++
    accountGeneration++
    personGeneration++
  })
</script>
<style scoped>
.access-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 18px 0;
  border-bottom: 1px solid rgba(var(--v-theme-on-surface), 0.1);
}
.access-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}
.access-pages {
  display: flex;
  justify-content: space-between;
  margin-top: 15px;
}
.access-title {
  white-space: normal;
}
@media (max-width: 600px) {
  .access-row {
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
  }
  .access-actions {
    justify-content: flex-start;
  }
}
</style>
