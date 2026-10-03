import type { CreatedId } from '@/types/common'
import type { CreatePermissionPayload, Permission } from '@/types/permission'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

/**
 * No `GET /api/permissions` list endpoint exists yet on the backend, so
 * permissions created during the session are cached locally here.
 */
export const usePermissionsStore = defineStore('permissions', () => {
  const permissions = ref<Permission[]>([])
  const isSaving = ref(false)

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

  return {
    permissions,
    isSaving,
    create,
  }
})
