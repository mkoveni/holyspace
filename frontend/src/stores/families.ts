import type { CreatedId } from '@/types/common'
import type { AddFamilyAddressPayload, AddFamilyMemberPayload, CreateFamilyPayload, Family, FamilyMember } from '@/types/family'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const useFamiliesStore = defineStore('families', () => {
  const families = ref<Family[]>([])
  const current = ref<Family | null>(null)
  const peopleByFamily = ref<Record<string, FamilyMember[]>>({})
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

  async function fetchFamilyPeople (familyId: string) {
    try {
      const { data } = await useApi().get<FamilyMember[]>(`/api/families/${familyId}/people`)
      peopleByFamily.value[familyId] = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load family members.'), { cause: error })
    }
  }

  async function addMember (familyId: string, payload: AddFamilyMemberPayload) {
    isSaving.value = true
    try {
      await useApi().post(`/api/families/${familyId}/members`, payload)
      await fetchFamilyPeople(familyId)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add family member.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addFamilyAddress (familyId: string, payload: AddFamilyAddressPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>(`/api/families/${familyId}/addresses`, payload)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add family address.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  return {
    families,
    current,
    peopleByFamily,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    fetchOne,
    create,
    fetchFamilyPeople,
    addMember,
    addFamilyAddress,
  }
})
