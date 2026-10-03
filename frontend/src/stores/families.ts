import type { CreatedId } from '@/types/common'
import type { AddFamilyMemberPayload, CreateFamilyPayload, Family } from '@/types/family'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export interface LocalFamilyMember {
  personId: string
  role: string
  addedAt: string
}

export const useFamiliesStore = defineStore('families', () => {
  const families = ref<Family[]>([])
  const current = ref<Family | null>(null)
  /**
   * `GET /api/families/{id}` does not return members and there is no
   * endpoint to list a family's members, so additions made during this
   * session are tracked locally, keyed by family id.
   */
  const membersByFamily = ref<Record<string, LocalFamilyMember[]>>({})
  const isLoading = ref(false)
  const isSaving = ref(false)

  function byId (id: string) {
    return families.value.find(family => family.id === id)
  }

  async function fetchAll () {
    isLoading.value = true
    try {
      const { data } = await useApi().get<Family[]>('/api/families')
      families.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load families.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<Family>(`/api/families/${id}`)
      current.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Family not found.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function create (payload: CreateFamilyPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/families', payload)
      await fetchAll()
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create family.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addMember (familyId: string, payload: AddFamilyMemberPayload) {
    isSaving.value = true
    try {
      await useApi().post(`/api/families/${familyId}/members`, payload)
      const members = membersByFamily.value[familyId] ?? []
      members.push({ personId: payload.personId, role: payload.role ?? 'member', addedAt: new Date().toISOString() })
      membersByFamily.value[familyId] = members
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add family member.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  return {
    families,
    current,
    membersByFamily,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    fetchOne,
    create,
    addMember,
  }
})
