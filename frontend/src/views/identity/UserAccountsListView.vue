<template>
  <div>
    <PageHeader subtitle="Create and manage login credentials for staff and members" title="User Accounts">
      <template #actions>
        <v-btn color="primary" prepend-icon="mdi-plus" variant="flat" @click="createDialog = true">
          Create Account
        </v-btn>
      </template>
    </PageHeader>

    <v-alert class="mb-4" type="info" variant="tonal">
      A full account listing endpoint is not yet available on the backend, so this page tracks
      accounts created or looked up during your session. Use "Look up by ID" to load an existing account.
    </v-alert>

    <v-card class="mb-4" elevation="2">
      <v-card-text class="d-flex ga-2 align-center flex-wrap">
        <v-text-field
          v-model="lookupId"
          density="comfortable"
          hide-details
          label="User Account ID"
          style="max-width: 360px"
        />

        <v-btn color="primary" variant="tonal" @click="handleLookup">Look up by ID</v-btn>
      </v-card-text>
    </v-card>

    <v-card elevation="2">
      <v-data-table :headers="headers" item-value="id" :items="userAccountsStore.accounts">
        <template #item.status="{ item }">
          <StatusChip :status="item.status" />
        </template>

        <template #item.createdAt="{ item }">
          {{ formatDate(item.createdAt) }}
        </template>

        <template #item.actions="{ item }">
          <v-btn icon="mdi-cog-outline" size="small" variant="text" @click="openManageDialog(item)" />
        </template>
      </v-data-table>
    </v-card>

    <UserAccountFormDialog v-model="createDialog" />
    <ManageUserAccountDialog v-model="manageDialog" :account="selectedAccount" />
  </div>
</template>

<script lang="ts" setup>
  import type { UserAccount } from '@/types/userAccount'
  import { ref } from 'vue'
  import PageHeader from '@/components/common/PageHeader.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'
  import ManageUserAccountDialog from './components/ManageUserAccountDialog.vue'
  import UserAccountFormDialog from './components/UserAccountFormDialog.vue'

  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()

  const createDialog = ref(false)
  const manageDialog = ref(false)
  const lookupId = ref('')
  const selectedAccount = ref<UserAccount | null>(null)

  const headers = [
    { title: 'Username', key: 'username', sortable: false },
    { title: 'Person ID', key: 'personId', sortable: false },
    { title: 'Status', key: 'status' },
    { title: 'Created', key: 'createdAt' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  function formatDate (value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  }

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
      ui.notifyError(error instanceof Error ? error.message : 'User account not found.')
    }
  }
</script>
