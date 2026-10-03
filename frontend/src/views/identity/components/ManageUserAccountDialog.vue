<template>
  <v-dialog v-model="model" max-width="560">
    <v-card v-if="account">
      <v-card-title class="d-flex align-center justify-space-between">
        {{ account.username }}
        <StatusChip :status="account.status" />
      </v-card-title>

      <v-divider />

      <v-tabs v-model="tab" color="primary">
        <v-tab value="roles">Roles</v-tab>
        <v-tab value="permissions">Permissions</v-tab>
        <v-tab value="security">Security</v-tab>
      </v-tabs>

      <v-divider />

      <v-window v-model="tab">
        <v-window-item value="roles">
          <v-card-text>
            <v-text-field v-model="roleId" density="comfortable" label="Role ID" />

            <div class="d-flex ga-2 mb-2">
              <v-btn color="primary" size="small" variant="tonal" @click="handleAssignRole">Assign</v-btn>
              <v-btn color="error" size="small" variant="tonal" @click="handleRemoveRole">Remove</v-btn>
            </div>

            <v-alert density="compact" type="info" variant="tonal">
              Enter the ID of a role created on the Roles page to assign or remove it from this account.
            </v-alert>
          </v-card-text>
        </v-window-item>

        <v-window-item value="permissions">
          <v-card-text>
            <v-btn class="mb-2" size="small" variant="tonal" @click="loadPermissions">Refresh Permissions</v-btn>

            <v-list v-if="userAccountsStore.permissions.length > 0" density="compact">
              <v-list-item v-for="permission in userAccountsStore.permissions" :key="permission" :title="permission">
                <template #prepend>
                  <v-icon color="primary" icon="mdi-key-outline" />
                </template>
              </v-list-item>
            </v-list>

            <div v-else class="text-medium-emphasis">No permissions loaded yet.</div>
          </v-card-text>
        </v-window-item>

        <v-window-item value="security">
          <v-card-text>
            <v-text-field v-model="newPassword" label="New password" type="password" />

            <v-btn
              class="mb-4"
              color="primary"
              size="small"
              variant="tonal"
              @click="handleChangePassword"
            >
              Update Password
            </v-btn>

            <v-divider class="mb-4" />

            <v-btn
              v-if="account.status === 'active'"
              block
              color="error"
              prepend-icon="mdi-account-off-outline"
              variant="tonal"
              @click="handleDisable"
            >
              Disable Account
            </v-btn>

            <v-btn
              v-else
              block
              color="success"
              prepend-icon="mdi-account-check-outline"
              variant="tonal"
              @click="handleEnable"
            >
              Enable Account
            </v-btn>
          </v-card-text>
        </v-window-item>
      </v-window>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script lang="ts" setup>
  import type { UserAccount } from '@/types/userAccount'
  import { computed, ref, watch } from 'vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'

  const props = defineProps<{ modelValue: boolean, account: UserAccount | null }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean] }>()

  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()

  const tab = ref('roles')
  const roleId = ref('')
  const newPassword = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const account = computed(() => props.account)

  watch(() => props.modelValue, value => {
    if (value) {
      tab.value = 'roles'
      roleId.value = ''
      newPassword.value = ''
    }
  })

  async function loadPermissions () {
    if (!account.value) return
    try {
      await userAccountsStore.fetchPermissions(account.value.id)
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to load permissions.')
    }
  }

  async function handleAssignRole () {
    if (!account.value || !roleId.value) return
    try {
      await userAccountsStore.assignRole(account.value.id, roleId.value)
      ui.notifySuccess('Role assigned.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to assign role.')
    }
  }

  async function handleRemoveRole () {
    if (!account.value || !roleId.value) return
    try {
      await userAccountsStore.removeRole(account.value.id, roleId.value)
      ui.notifySuccess('Role removed.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to remove role.')
    }
  }

  async function handleChangePassword () {
    if (!account.value || !newPassword.value) return
    try {
      await userAccountsStore.changePassword(account.value.id, { password: newPassword.value })
      ui.notifySuccess('Password updated.')
      newPassword.value = ''
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to update password.')
    }
  }

  async function handleDisable () {
    if (!account.value) return
    try {
      await userAccountsStore.disable(account.value.id)
      ui.notifySuccess('Account disabled.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to disable account.')
    }
  }

  async function handleEnable () {
    if (!account.value) return
    try {
      await userAccountsStore.enable(account.value.id)
      ui.notifySuccess('Account enabled.')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to enable account.')
    }
  }
</script>
