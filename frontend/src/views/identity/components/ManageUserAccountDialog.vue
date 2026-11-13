<template>
  <v-dialog v-model="model" max-width="640" scrollable>
    <v-card v-if="account">
      <v-card-title class="d-flex align-center justify-space-between">
        {{ live.displayName || live.username }}
        <StatusChip :status="live.status" />
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
            <h3 class="section-label">
              Assigned roles ({{ (live.roles ?? []).length }})
            </h3>

            <div
              v-if="(live.roles ?? []).length > 0"
              class="chip-row mb-5"
            >
              <v-chip
                v-for="name in live.roles"
                :key="name"
                closable
                color="primary"
                @click:close="handleRemoveRole(name)"
              >
                {{ name }}
              </v-chip>
            </div>

            <p
              v-else
              class="text-medium-emphasis mb-5"
            >
              This account has no roles, so it cannot open any staff pages.
            </p>

            <div class="d-flex ga-2 align-start">
              <v-autocomplete
                v-model="roleId"
                class="flex-grow-1"
                hide-details
                item-title="name"
                item-value="id"
                :items="availableRoles"
                label="Add a role"
                no-data-text="Every role is already assigned"
              />

              <v-btn
                color="primary"
                :disabled="!roleId"
                height="48"
                variant="flat"
                @click="handleAssignRole"
              >
                Assign
              </v-btn>
            </div>
          </v-card-text>
        </v-window-item>

        <v-window-item value="permissions">
          <v-card-text>
            <h3 class="section-label">
              Effective permissions ({{ userAccountsStore.permissions.length }})
            </h3>

            <v-progress-linear
              v-if="permissionsLoading"
              class="mb-3"
              indeterminate
            />

            <div
              v-if="userAccountsStore.permissions.length > 0"
              class="chip-row"
            >
              <v-chip
                v-for="permission in userAccountsStore.permissions"
                :key="permission"
                prepend-icon="mdi-key-outline"
                size="small"
              >
                {{ permission }}
              </v-chip>
            </div>

            <p
              v-else-if="!permissionsLoading"
              class="text-medium-emphasis"
            >
              No permissions. Assign a role to grant access.
            </p>
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
  import { extractErrorMessage } from '@/composables/apiErrors'
  import { useAction } from '@/composables/useAction'
  import { useRolesStore } from '@/stores/roles'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'

  const props = defineProps<{ modelValue: boolean, account: UserAccount | null }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const rolesStore = useRolesStore()
  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()
  const action = useAction()

  const tab = ref('roles')
  const roleId = ref('')
  const newPassword = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const account = computed(() => props.account)
  // Prefer the freshly reloaded copy so assigned roles appear straight away.
  const live = computed(() => (props.account ? (userAccountsStore.byId(props.account.id) ?? props.account) : ({} as UserAccount)))
  const permissionsLoading = ref(false)
  const availableRoles = computed(() => {
    const held = new Set(live.value.roles)
    return rolesStore.roles.filter(role => !held.has(role.name))
  })

  watch(
    () => props.modelValue,
    value => {
      if (value) {
        void rolesStore
          .fetchAll()
          .catch(error => ui.notifyError(extractErrorMessage(error, 'Unable to load roles.')))
        tab.value = 'roles'
        roleId.value = ''
        void loadPermissions()
        newPassword.value = ''
      }
    },
  )

  async function loadPermissions () {
    if (!account.value) return
    permissionsLoading.value = true
    try {
      await userAccountsStore.fetchPermissions(account.value.id)
    } catch (error) {
      ui.notifyError(extractErrorMessage(error, 'Unable to load permissions.'))
    } finally {
      permissionsLoading.value = false
    }
  }

  async function handleAssignRole () {
    if (!account.value || !roleId.value) return
    const id = account.value.id,
          role = roleId.value
    const ok = await action.run(() => userAccountsStore.assignRole(id, role), {
      success: 'Role assigned.',
      failure: 'Unable to assign role.',
    })
    if (ok) {
      roleId.value = ''
      void loadPermissions()
      emit('saved')
    }
  }

  async function handleRemoveRole (name: string) {
    const role = rolesStore.roles.find(item => item.name === name)
    if (!account.value || !role) return
    const id = account.value.id
    const ok = await action.run(() => userAccountsStore.removeRole(id, role.id), {
      confirm: { title: `Remove the ${name} role?`, message: 'This account will lose the access the role gives.', confirmText: 'Remove', color: 'error' },
      success: `${name} role removed.`,
      failure: 'Unable to remove role.',
      undo: () => userAccountsStore.assignRole(id, role.id).then(loadPermissions),
    })
    if (ok) {
      void loadPermissions()
      emit('saved')
    }
  }

  async function handleChangePassword () {
    if (!account.value || !newPassword.value) return
    const id = account.value.id,
          password = newPassword.value
    const ok = await action.run(() => userAccountsStore.changePassword(id, { password }), {
      confirm: { title: 'Change this password?', message: 'The person is signed out everywhere and must use the new password.', confirmText: 'Change password' },
      success: 'Password updated.',
      failure: 'Unable to update password.',
    })
    if (ok) newPassword.value = ''
  }

  async function handleDisable () {
    if (!account.value) return
    const id = account.value.id
    await action.run(() => userAccountsStore.disable(id), {
      confirm: { title: 'Disable this account?', message: 'The person is signed out everywhere and cannot sign in until you enable the account again.', confirmText: 'Disable', color: 'error' },
      success: 'Account disabled.',
      failure: 'Unable to disable account.',
      undo: () => userAccountsStore.enable(id),
    })
  }

  async function handleEnable () {
    if (!account.value) return
    const id = account.value.id
    await action.run(() => userAccountsStore.enable(id), {
      success: 'Account enabled.',
      failure: 'Unable to enable account.',
    })
  }
</script>
