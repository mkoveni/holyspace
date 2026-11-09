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

    <v-card class="mb-4"><v-card-text class="d-flex ga-2 align-center"><v-text-field
                                                                         v-model="search"
                                                                         hide-details
                                                                         label="Find account by username"
                                                                         @keyup.enter="loadAccounts"
                                                                       />

      <v-btn
        :loading="userAccountsStore.isLoading"
        @click="loadAccounts"
      >Search accounts</v-btn></v-card-text></v-card>

    <p class="mb-4">Showing up to 50 matching accounts. Search by username to narrow the list.</p>

    <v-card class="mb-4">
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

    <v-card>
      <v-data-table :headers="headers" item-value="id" :items="userAccountsStore.accounts">
        <template #item.username="{ item }">
          <div class="table-identity">
            <span class="table-monogram">{{ item.username.slice(0, 2).toUpperCase() }}</span>

            <strong>{{ item.username }}</strong>
          </div>
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
            @click="openManageDialog(item)"
          />
        </template>
      </v-data-table>
    </v-card>

    <UserAccountFormDialog v-model="createDialog" @saved="loadAccounts" />
    <ManageUserAccountDialog v-model="manageDialog" :account="selectedAccount" @saved="loadAccounts" />
  </div>
</template>

<script lang="ts" setup>
  import type { UserAccount } from '@/types/userAccount'
  import { onMounted, ref } from 'vue'
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
  const search = ref('')
  const selectedAccount = ref<UserAccount | null>(null)

  const headers = [
    { title: 'Username', key: 'username', sortable: false },
    { title: 'Full name', key: 'displayName', sortable: false },
    { title: 'Email', key: 'email', sortable: false },
    { title: 'Status', key: 'status' },
    { title: 'Created', key: 'createdAt' },
    { title: 'Actions', key: 'actions', sortable: false, align: 'end' },
  ] as const

  async function loadAccounts () {
    try {
      await userAccountsStore.fetchAll(search.value)
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load accounts.')
    }
  }
  onMounted(loadAccounts)

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
