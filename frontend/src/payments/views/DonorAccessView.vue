<template>
  <div class="directory-workspace">
    <DirectoryHeading
      eyebrow="ADMINISTRATION · DONOR ACCESS"
      subtitle="Verify donor identities and choose the funds available for online giving."
      title="A trusted connection."
    >
      <v-btn
        color="primary"
        prepend-icon="mdi-account-check-outline"
        @click="newLink"
      >
        Verify donor access
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
          <h2>Donor account access</h2>

          <p class="directory-muted">
            One account, one person. Verify identity directly before granting
            access to financial records.
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
        No donor accounts have been verified yet.
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

    <section class="directory-surface giving-section">
      <span class="directory-eyebrow">ONLINE GIVING CATALOGUE</span>
      <h2>Choose where donors can give.</h2>

      <p class="directory-muted mb-4">
        Funds are offered online only when you enable them here. Withdrawing a
        fund does not change existing payments or monthly arrangements.
      </p>

      <p
        v-if="funds.length === 0"
        class="directory-muted py-4"
      >
        Create funds in Giving before offering them online.
      </p>

      <article
        v-for="fund in funds"
        :key="fund.id"
        class="access-row"
      >
        <div>
          <strong>{{ fund.name }}</strong>
          <p class="directory-muted">{{ fund.description }}</p>
          <small v-if="!fund.active">Inactive fund</small>
        </div>

        <v-switch
          color="primary"
          :disabled="working || (!fund.active && !fund.enabled)"
          hide-details
          :label="fund.enabled ? 'Offered online' : 'Not offered'"
          :model-value="fund.enabled"
          @update:model-value="toggleFund(fund)"
        />
      </article>
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
                ? 'Revoke donor access'
                : 'Restore verified access'
              : 'Verify donor identity'
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
                ? 'Access is removed immediately. Financial and verification history is retained.'
                : 'Confirm that this account belongs to this person. Matching email addresses or household membership are not proof of identity. This grants access to the person’s giving history.'
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
  import type { DonorLink, OnlineFund } from '../donor-api'
  import type { UserAccount } from '@/types/userAccount'
  import { onBeforeUnmount, onMounted, ref } from 'vue'
  import { extractErrorMessage, useApi } from '@/composables/useApi'
  import { directoryApi } from '@/directory/api'
  import DirectoryHeading from '@/directory/components/DirectoryHeading.vue'
  import { useAuthStore } from '@/stores/auth'
  import { donorApi } from '../donor-api'
  import '@/giving/giving.css'
  const auth = useAuthStore()
  const links = ref<DonorLink[]>([]),
        funds = ref<OnlineFund[]>([]),
        hasMore = ref(false),
        offset = ref(0)
  const loading = ref(false),
        working = ref(false),
        error = ref(''),
        success = ref(''),
        dialogError = ref(''),
        dialog = ref(false)
  const selected = ref<DonorLink | null>(null),
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
      const data = await donorApi.access(offset.value)
      if (run !== generation) return
      links.value = data.links
      funds.value = data.funds
      hasMore.value = data.hasMore
    } catch (error_) {
      if (run === generation) {
        links.value = []
        funds.value = []
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
            label: `${p.details.firstName} ${p.details.lastName}`,
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
  function changeLink (link: DonorLink) {
    reset()
    selected.value = link
    dialog.value = true
  }
  async function save () {
    if (working.value || !verified.value) return
    working.value = true
    dialogError.value = ''
    try {
      await donorApi.link(selected.value?.accountId || accountId.value!, {
        personId: selected.value?.personId || personId.value,
        active: !selected.value?.active,
        expectedVersion: selected.value?.version || 0,
        evidence: evidence.value.trim(),
      })
      dialog.value = false
      success.value = selected.value?.active
        ? 'Donor access revoked.'
        : 'Verified donor access saved.'
      await refresh()
    } catch (error_) {
      dialogError.value = extractErrorMessage(error_)
    } finally {
      working.value = false
    }
  }
  async function toggleFund (fund: OnlineFund) {
    if (working.value) return
    working.value = true
    success.value = ''
    error.value = ''
    try {
      await donorApi.fund(fund)
      await refresh()
      success.value = fund.enabled
        ? 'Fund withdrawn from online giving.'
        : 'Fund offered for online giving.'
    } catch (error_) {
      error.value = extractErrorMessage(error_)
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
