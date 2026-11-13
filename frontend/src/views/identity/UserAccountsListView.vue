<template>
  <div>
    <PageHeader
      eyebrow="Accounts & access"
      subtitle="Create and manage login credentials for staff and members"
      title="User Accounts"
    >
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="createDialog = true">
          Create Account
        </v-btn>
      </template>
    </PageHeader>

    <v-card>
      <v-card-text class="workspace-filter-panel">
        <div class="d-flex ga-3 align-center flex-wrap">
          <v-text-field
            v-model="search"
            clearable
            hide-details
            label="Find account by username"
            prepend-inner-icon="mdi-magnify"
            style="max-width: 420px"
            @keyup.enter="loadAccounts"
          />

          <v-btn
            :loading="userAccountsStore.isLoading"
            variant="tonal"
            @click="loadAccounts"
          >
            Search accounts
          </v-btn>

          <v-spacer />

          <v-text-field
            v-model="lookupId"
            hide-details
            label="Account ID"
            style="max-width: 280px"
            @keyup.enter="handleLookup"
          />

          <v-btn
            variant="text"
            @click="handleLookup"
          >
            Look up by ID
          </v-btn>
        </div>

        <p class="table-muted mt-3">
          Showing up to 50 matching accounts. Select a row to manage roles, permissions and security.
        </p>
      </v-card-text>

      <AppDataTable
        clickable
        empty-text="Try a different username, or create a new account."
        empty-title="No accounts found"
        :headers="headers"
        item-value="id"
        :items="userAccountsStore.accounts"
        :loading="userAccountsStore.isLoading"
        @row-click="openManageDialog"
      >
        <template #item.username="{ item }">
          <div class="table-identity">
            <span class="table-monogram">{{ (item.displayName || item.username).slice(0, 2).toUpperCase() }}</span>

            <span>
              <strong>{{ item.displayName || item.username }}</strong>
              <small>@{{ item.username }}{{ item.email ? ` · ${item.email}` : '' }}</small>
            </span>
          </div>
        </template>

        <template #item.roles="{ item }">
          <div
            v-if="(item.roles ?? []).length > 0"
            class="chip-row"
          >
            <v-chip
              v-for="role in item.roles"
              :key="role"
              color="primary"
              size="small"
            >
              {{ role }}
            </v-chip>
          </div>

          <span
            v-else
            class="table-muted"
          >No roles</span>
        </template>

        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>

        <template #item.createdAt="{ item }">
          {{ formatDate(item.createdAt) }}
        </template>

        <template #item.actions="{ item }">
          <v-btn
            :aria-label="`Manage ${item.username}`"
            icon="mdi-cog-outline"
            size="small"
            variant="text"
            @click.stop="openManageDialog(item)"
          />
        </template>

      </AppDataTable>
    </v-card>

    <UserAccountFormDialog v-model="createDialog" @saved="loadAccounts" />
    <ManageUserAccountDialog v-model="manageDialog" :account="selectedAccount" @saved="loadAccounts" />
  </div>
</template>

<script lang="ts" setup>
  import type { UserAccount } from '@/types/userAccount'
  import { onMounted, ref } from 'vue'
  import AppDataTable from '@/components/common/AppDataTable.vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'
  import { formatDate } from '@/utils/format'
  import ManageUserAccountDialog from './components/ManageUserAccountDialog.vue'
  import UserAccountFormDialog from './components/UserAccountFormDialog.vue'

  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()

  const createDialog = ref(false)
  const manageDialog = ref(false)
  const lookupId = ref('')
  const search = ref('')
  const selectedAccount = ref<UserAccount | null>(null)

  const headers = [
    { title: 'Account', key: 'username', sortable: false },
    { title: 'Roles', key: 'roles', sortable: false },
    { title: 'Status', key: 'status' },
    { title: 'Created', key: 'createdAt' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  async function loadAccounts () {
    try {
      await userAccountsStore.fetchAll(search.value)
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to load accounts.'))
    }
  }
  onMounted(loadAccounts)

  function openManageDialog (account: UserAccount) {
    selectedAccount.value = account
    manageDialog.value = true
  }

  async function handleLookup () {
    if (!lookupId.value) return
    try {
      const account = await userAccountsStore.fetchOne(lookupId.value)
      selectedAccount.value = account
      manageDialog.value = true
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'User account not found.'))
    }
  }
</script>
