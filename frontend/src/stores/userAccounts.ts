import type { CreatedId } from '@/types/common'
import type { ChangePasswordPayload, CreateUserAccountPayload, UserAccount } from '@/types/userAccount'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

/** Account directory and session-scoped management cache. */
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

  async function fetchAll (search = '') {
    isLoading.value = true
    try {
      const { data } = await useApi().get<{ items: UserAccount[] }>('/api/users', { params: { search, limit: 50 } })
      accounts.value = data.items
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load accounts.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function enableMemberAccess (id: string, personId: string, expectedPersonId: string | null) {
    isSaving.value = true
    try {
      await useApi().post('/api/users/' + id + '/member-access', { personId, expectedPersonId })
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to enable member access.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<UserAccount>(`/api/users/${id}`)
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
      const { data } = await useApi().post<CreatedId>('/api/users', payload)
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
      const { data } = await useApi().get<string[]>(`/api/users/${id}/permissions`)
      permissions.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load permissions.'), { cause: error })
    }
  }

  async function changePassword (id: string, payload: ChangePasswordPayload) {
    try {
      await useApi().put(`/api/users/${id}/password`, payload)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to change password.'), { cause: error })
    }
  }

  async function disable (id: string) {
    try {
      await useApi().post(`/api/users/${id}/disable`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to disable account.'), { cause: error })
    }
  }

  async function enable (id: string) {
    try {
      await useApi().post(`/api/users/${id}/enable`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to enable account.'), { cause: error })
    }
  }

  async function assignRole (id: string, roleId: string) {
    try {
      await useApi().post(`/api/users/${id}/roles/${roleId}`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to assign role.'), { cause: error })
    }
  }

  async function removeRole (id: string, roleId: string) {
    try {
      await useApi().delete(`/api/users/${id}/roles/${roleId}`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to remove role.'), { cause: error })
    }
  }

  function reset () {
    accounts.value = []
    current.value = null
    permissions.value = []
  }

  return {
    reset,
    accounts,
    current,
    permissions,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    enableMemberAccess,
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
