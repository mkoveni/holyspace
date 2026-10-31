import type { CreatedId } from '@/types/common'
import type { CreateRolePayload, Role } from '@/types/role'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

/**
 * Roles are loaded from the privileged backend catalogue.
 */
export const useRolesStore = defineStore('roles', () => {
  const roles = ref<Role[]>([])
  const isSaving = ref(false)

  async function fetchAll () {
    try { roles.value = (await useApi().get<Role[]>('/api/roles')).data }
    catch (error) { throw new Error(extractErrorMessage(error, 'Unable to load roles.'), {cause:error}) }
  }
  async function create (payload: CreateRolePayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/roles', payload)
      const role: Role = { id: data.id, name: payload.name, description: payload.description }
      roles.value.unshift(role)
      return role
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create role.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addPermission (roleId: string, permissionId: string) {
    try {
      await useApi().post(`/api/roles/${roleId}/permissions/${permissionId}`)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add permission to role.'), { cause: error })
    }
  }

  async function removePermission (roleId: string, permissionId: string) {
    try {
      await useApi().delete(`/api/roles/${roleId}/permissions/${permissionId}`)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to remove permission from role.'), { cause: error })
    }
  }

  function reset () {
    roles.value = []
  }

  return {
    fetchAll,
    reset,
    roles,
    isSaving,
    create,
    addPermission,
    removePermission,
  }
})
