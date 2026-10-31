<template>
  <v-dialog v-model="model" max-width="560">
    <v-card v-if="account">
      <v-card-title class="d-flex align-center justify-space-between">
        {{ account.username }}
        <StatusChip :status="account.status" />
      </v-card-title>

      <v-divider />

      <v-tabs v-model="tab" color="primary">
        <v-tab v-if="!isJavaApi" value="member">Member access</v-tab><v-tab value="roles">Roles</v-tab>
        <v-tab value="permissions">Permissions</v-tab>
        <v-tab value="security">Security</v-tab>
      </v-tabs>

      <v-divider />

      <v-window v-model="tab">
        <v-window-item
          v-if="!isJavaApi"
          value="member"
        ><v-card-text>
          <v-alert
            class="mb-4"
            type="info"
          >My church requires a linked person, member access and authenticator setup. This grants only the
            member portal; staff permissions are kept as they are.</v-alert>

          <PeopleSearch v-model="memberPersonId" label="Person for this member account" />

          <v-checkbox
            v-model="identityConfirmed"
            label="I verified this account belongs to the selected person"
          />

          <v-btn
            color="primary"
            :disabled="!memberPersonId || !identityConfirmed || account.status !== 'active'"
            :loading="userAccountsStore.isSaving"
            @click="enableMemberAccess"
          >Enable My church</v-btn>

          <p class="mt-3">
            The member must sign in again and complete authenticator setup. Changing the linked person signs
            out existing sessions.
          </p>
        </v-card-text></v-window-item>

        <v-window-item value="roles">
          <v-card-text>
            <v-autocomplete
              v-model="roleId"
              item-title="name"
              item-value="id"
              :items="rolesStore.roles"
              label="Role"
            />

            <div class="d-flex ga-2 mb-2">
              <v-btn color="primary" size="small" variant="tonal" @click="handleAssignRole">Assign</v-btn>
              <v-btn color="error" size="small" variant="tonal" @click="handleRemoveRole">Remove</v-btn>
            </div>

            <v-alert density="compact" type="info" variant="tonal">
              Choose an existing church role to assign or remove.
            </v-alert>
          </v-card-text>
        </v-window-item>

        <v-window-item value="permissions">
          <v-card-text>
            <v-btn
              class="mb-2"
              size="small"
              variant="tonal"
              @click="loadPermissions"
            >Refresh Permissions</v-btn>

            <v-list v-if="userAccountsStore.permissions.length > 0" density="compact">
              <v-list-item
                v-for="permission in userAccountsStore.permissions"
                :key="permission"
                :title="permission"
              >
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
  import { useRouter } from 'vue-router'
  import PeopleSearch from '@/components/common/PeopleSearch.vue'
  import StatusChip from '@/components/common/StatusChip.vue'
  import { isJavaApi } from '@/directory/backend'
  import { useAuthStore } from '@/stores/auth'
  import { useRolesStore } from '@/stores/roles'
  import { useUiStore } from '@/stores/ui'
  import { useUserAccountsStore } from '@/stores/userAccounts'

  const props = defineProps<{ modelValue: boolean, account: UserAccount | null }>()
  const emit = defineEmits<{ 'update:model-value': [value: boolean], 'saved': [] }>()

  const rolesStore = useRolesStore()
  const userAccountsStore = useUserAccountsStore()
  const ui = useUiStore()

  const auth = useAuthStore()
  const router = useRouter()
  const memberPersonId = ref<string | null>(null)
  const identityConfirmed = ref(false)
  const tab = ref(isJavaApi ? 'roles' : 'member')
  const roleId = ref('')
  const newPassword = ref('')

  const model = computed({
    get: () => props.modelValue,
    set: value => emit('update:model-value', value),
  })

  const account = computed(() => props.account)

  watch(
    () => props.modelValue,
    value => {
      if (value) {
        void rolesStore
          .fetchAll()
          .catch(error => ui.notifyError(error instanceof Error ? error.message : 'Unable to load roles.'))
        tab.value = isJavaApi ? 'roles' : 'member'
        memberPersonId.value = props.account?.personId ?? null
        identityConfirmed.value = false
        roleId.value = ''
        newPassword.value = ''
      }
    },
  )

  async function enableMemberAccess () {
    if (!account.value || !memberPersonId.value || !identityConfirmed.value) return
    try {
      await userAccountsStore.enableMemberAccess(account.value.id, memberPersonId.value, account.value.personId)
      ui.notifySuccess('My church enabled. The member must sign in again and complete authenticator setup.')
      model.value = false
      if (account.value.id === auth.user?.id) {
        auth.logout()
        await router.replace('/login')
      } else emit('saved')
    } catch (error) {
      ui.notifyError(error instanceof Error ? error.message : 'Unable to enable member access.')
    }
  }

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
