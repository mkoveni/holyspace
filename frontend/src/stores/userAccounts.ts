import type { CreatedId } from '@/types/common'
import type { ChangePasswordPayload, CreateUserAccountPayload, UserAccount } from '@/types/userAccount'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

/**
 * The IdentityAccess bounded context does not yet expose a `GET /api/useraccounts`
 * list endpoint, so this store keeps a local, session-scoped cache of every
 * account that has been created or looked up, in addition to fetch-by-id.
 */
export const useUserAccountsStore = defineStore('userAccounts', () => {
  const accounts = ref<UserAccount[]>([])
  const current = ref<UserAccount | null>(null)
  const permissions = ref<string[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)

  function byId (id: string) {
    return accounts.value.find(account => account.id === id)
  }

  function upsertLocal (account: UserAccount) {
    const index = accounts.value.findIndex(item => item.id === account.id)
    if (index === -1) {
      accounts.value.unshift(account)
    } else {
      accounts.value[index] = account
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<UserAccount>(`/api/useraccounts/${id}`)
      current.value = data
      upsertLocal(data)
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'User account not found.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function create (payload: CreateUserAccountPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/useraccounts', payload)
      await fetchOne(data.id)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create user account.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function fetchPermissions (id: string) {
    try {
      const { data } = await useApi().get<string[]>(`/api/useraccounts/${id}/permissions`)
      permissions.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load permissions.'), { cause: error })
    }
  }

  async function changePassword (id: string, payload: ChangePasswordPayload) {
    try {
      await useApi().put(`/api/useraccounts/${id}/password`, payload)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to change password.'), { cause: error })
    }
  }

  async function disable (id: string) {
    try {
      await useApi().post(`/api/useraccounts/${id}/disable`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to disable account.'), { cause: error })
    }
  }

  async function enable (id: string) {
    try {
      await useApi().post(`/api/useraccounts/${id}/enable`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to enable account.'), { cause: error })
    }
  }

  async function assignRole (id: string, roleId: string) {
    try {
      await useApi().post(`/api/useraccounts/${id}/roles/${roleId}`)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to assign role.'), { cause: error })
    }
  }

  async function removeRole (id: string, roleId: string) {
    try {
      await useApi().delete(`/api/useraccounts/${id}/roles/${roleId}`)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to remove role.'), { cause: error })
    }
  }

  return {
    accounts,
    current,
    permissions,
    isLoading,
    isSaving,
    byId,
    fetchOne,
    create,
    fetchPermissions,
    changePassword,
    disable,
    enable,
    assignRole,
    removeRole,
  }
})
