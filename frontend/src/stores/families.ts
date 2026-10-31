import type { CreatedId } from '@/types/common'
import type { AddFamilyAddressPayload, AddFamilyMemberPayload, CreateFamilyPayload, Family, FamilyMember, FamilyOverview } from '@/types/family'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const useFamiliesStore = defineStore('families', () => {
  const overviews = ref<FamilyOverview[]>([])
  const overview = ref<FamilyOverview | null>(null)
  const isLoadingOverviews = ref(false)
  const savingContactId = ref<string | null>(null)
  const families = ref<Family[]>([])
  const current = ref<Family | null>(null)
  const peopleByFamily = ref<Record<string, FamilyMember[]>>({})
  const isLoading = ref(false)
  const isSaving = ref(false)

  async function fetchOverviews () {
    isLoadingOverviews.value = true
    try {
      const { data } = await useApi().get<FamilyOverview[]>('/api/family-overviews')
      overviews.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load family details.'), { cause: error })
    } finally {
      isLoadingOverviews.value = false
    }
  }

  async function fetchOverview (id: string) {
    try {
      const { data } = await useApi().get<FamilyOverview>(`/api/families/${id}/overview`)
      const index = overviews.value.findIndex(family => family.id === id)
      if (index !== -1) {
        overviews.value[index] = data
      }
      overview.value = data
      peopleByFamily.value[id] = data.members
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load family details.'), { cause: error })
    }
  }

  async function setPrimaryContact (familyId: string, personId: string | null) {
    if (savingContactId.value) {
      return
    }
    savingContactId.value = familyId
    try {
      await useApi().put(`/api/families/${familyId}/primary-contact`, { personId })
      await fetchOverview(familyId)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to update the primary contact.'), { cause: error })
    } finally {
      savingContactId.value = null
    }
  }

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
      await fetchOverview(familyId)
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
      await fetchOverview(familyId)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add family address.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  function reset () {
    overviews.value = []
    overview.value = null
    families.value = []
    current.value = null
    peopleByFamily.value = {}
  }

  return {
    overviews,
    overview,
    isLoadingOverviews,
    savingContactId,
    fetchOverviews,
    fetchOverview,
    setPrimaryContact,
    reset,
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
