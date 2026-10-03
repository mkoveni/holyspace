import type { CreatedId } from '@/types/common'
import type { PersonFamily } from '@/types/family'
import type { ChangePersonStatusPayload, CreatePersonPayload, Person, UpdatePersonPayload } from '@/types/person'
import type {
  AddAddressPayload,
  AddCommunicationOptionPayload,
  AddEducationPayload,
  AddEmploymentPayload,
  PersonProfile,
} from '@/types/profile'
import type { CreateRelationshipPayload, EndRelationshipPayload, Relationship } from '@/types/relationship'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const usePeopleStore = defineStore('people', () => {
  const people = ref<Person[]>([])
  const current = ref<Person | null>(null)
  const relationships = ref<Relationship[]>([])
  const profile = ref<PersonProfile | null>(null)
  const families = ref<PersonFamily[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)

  function byId (id: string) {
    return people.value.find(person => person.id === id)
  }

  async function fetchAll () {
    isLoading.value = true
    try {
      const { data } = await useApi().get<Person[]>('/api/people')
      people.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load people.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<Person>(`/api/people/${id}`)
      current.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Person not found.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function create (payload: CreatePersonPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/people', payload)
      await fetchAll()
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create person.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function update (id: string, payload: UpdatePersonPayload) {
    isSaving.value = true
    try {
      await useApi().put(`/api/people/${id}`, payload)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to update person.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function changeStatus (id: string, payload: ChangePersonStatusPayload) {
    try {
      await useApi().post(`/api/people/${id}/status`, payload)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to change membership status.'), { cause: error })
    }
  }

  async function fetchRelationships (personId: string) {
    try {
      const { data } = await useApi().get<Relationship[]>(`/api/people/${personId}/relationships`)
      relationships.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load relationships.'), { cause: error })
    }
  }

  async function createRelationship (personId: string, payload: CreateRelationshipPayload) {
    try {
      await useApi().post(`/api/people/${personId}/relationships`, payload)
      await fetchRelationships(personId)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create relationship.'), { cause: error })
    }
  }

  async function endRelationship (personId: string, relationshipId: string, payload: EndRelationshipPayload = {}) {
    try {
      await useApi().post(`/api/relationships/${relationshipId}/end`, payload)
      await fetchRelationships(personId)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to end relationship.'), { cause: error })
    }
  }

  async function fetchProfile (personId: string) {
    try {
      const { data } = await useApi().get<PersonProfile>(`/api/people/${personId}/profile`)
      profile.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load person profile.'), { cause: error })
    }
  }

  async function fetchFamilies (personId: string) {
    try {
      const { data } = await useApi().get<PersonFamily[]>(`/api/people/${personId}/families`)
      families.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load families for this person.'), { cause: error })
    }
  }

  async function addAddress (personId: string, payload: AddAddressPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>(`/api/people/${personId}/addresses`, payload)
      await fetchProfile(personId)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add address.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addCommunicationOption (personId: string, payload: AddCommunicationOptionPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>(`/api/people/${personId}/communication-options`, payload)
      await fetchProfile(personId)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add communication option.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addEducation (personId: string, payload: AddEducationPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>(`/api/people/${personId}/education`, payload)
      await fetchProfile(personId)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add education history.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function addEmployment (personId: string, payload: AddEmploymentPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>(`/api/people/${personId}/employment`, payload)
      await fetchProfile(personId)
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to add employment history.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  return {
    people,
    current,
    relationships,
    profile,
    families,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    fetchOne,
    create,
    update,
    changeStatus,
    fetchRelationships,
    createRelationship,
    endRelationship,
    fetchProfile,
    fetchFamilies,
    addAddress,
    addCommunicationOption,
    addEducation,
    addEmployment,
  }
})
