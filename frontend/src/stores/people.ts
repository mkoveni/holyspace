import type { PersonSearchPerson } from '@/components/common/peopleSearch'
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
  const searchPeople = ref<Record<string, PersonSearchPerson>>({})

  function rememberSearchResults (items: PersonSearchPerson[]) {
    for (const person of items) {
      searchPeople.value[person.id] = person
    }
  }
  const current = ref<Person | null>(null)
  const relationships = ref<Relationship[]>([])
  const profile = ref<PersonProfile | null>(null)
  const families = ref<PersonFamily[]>([])
  const isLoading = ref(false)
  const isSaving = ref(false)

  function relationById (relatedId: string) {
    return relationships.value.find(relationship => relationship.relatedPersonId === relatedId)
  }

  function byId (id: string) {
    return people.value.find(person => person.id === id) ?? searchPeople.value[id]
  }

  let peopleGeneration = 0
  let peopleRequest: Promise<Person[]> | null = null
  const hasLoadedPeople = ref(false)

  function ensureLoaded () {
    return hasLoadedPeople.value ? Promise.resolve(people.value) : fetchAll()
  }

  function fetchAll (): Promise<Person[]> {
    if (peopleRequest) {
      return peopleRequest
    }
    const request = loadPeople().finally(() => {
      if (peopleRequest === request) {
        peopleRequest = null
      }
    })
    peopleRequest = request
    return request
  }

  async function loadPeople () {
    const generation = peopleGeneration
    isLoading.value = true
    try {
      const { data } = await useApi().get<Person[]>('/api/people')
      if (generation !== peopleGeneration) {
        return []
      }
      people.value = data
      hasLoadedPeople.value = true
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load people.'), { cause: error })
    } finally {
      if (generation === peopleGeneration) {
        isLoading.value = false
      }
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<Person>(`/api/people/${id}`)
      current.value = data
      const index = people.value.findIndex(person => person.id === id)
      if (index !== -1) {
        people.value[index] = data
      }
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

  function reset () {
    searchPeople.value = {}
    peopleGeneration++
    peopleRequest = null
    isLoading.value = false
    hasLoadedPeople.value = false
    people.value = []
    current.value = null
    relationships.value = []
    profile.value = null
    families.value = []
  }

  return {
    reset,
    rememberSearchResults,
    people,
    current,
    relationships,
    profile,
    families,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    ensureLoaded,
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
    relationById,
  }
})
