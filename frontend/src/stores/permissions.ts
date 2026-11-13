import type { CreatedId } from '@/types/common'
import type { CreatePermissionPayload, Permission } from '@/types/permission'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const usePermissionsStore = defineStore('permissions', () => {
  const permissions = ref<Permission[]>([])
  const isSaving = ref(false)

  async function fetchAll () {
    try {
      permissions.value = (await useApi().get<Permission[]>('/api/permissions')).data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load permissions.'), { cause: error })
    }
  }
  async function create (payload: CreatePermissionPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/permissions', payload)
      const permission: Permission = { id: data.id, code: payload.code, description: payload.description }
      permissions.value.unshift(permission)
      return permission
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create permission.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  function reset () {
    permissions.value = []
  }

  return {
    fetchAll,
    reset,
    permissions,
    isSaving,
    create,
  }
})
