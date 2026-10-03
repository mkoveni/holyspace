import type { CreatedId } from '@/types/common'
import type { CreateLifeEventPayload, LifeEvent, UpdateLifeEventPayload } from '@/types/lifeEvent'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const useLifeEventsStore = defineStore('lifeEvents', () => {
  const lifeEvents = ref<LifeEvent[]>([])
  const current = ref<LifeEvent | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)

  function byId (id: string) {
    return lifeEvents.value.find(event => event.id === id)
  }

  async function fetchAll (type?: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<LifeEvent[]>('/api/life-events', {
        params: type ? { type } : undefined,
      })
      lifeEvents.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load life events.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function fetchForPerson (personId: string) {
    try {
      const { data } = await useApi().get<LifeEvent[]>(`/api/people/${personId}/life-events`)
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load life events for this person.'), { cause: error })
    }
  }

  async function fetchOne (id: string) {
    isLoading.value = true
    try {
      const { data } = await useApi().get<LifeEvent>(`/api/life-events/${id}`)
      current.value = data
      return data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Life event not found.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }

  async function create (payload: CreateLifeEventPayload) {
    isSaving.value = true
    try {
      const { data } = await useApi().post<CreatedId>('/api/life-events', payload)
      await fetchAll()
      return data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create life event.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function update (id: string, payload: UpdateLifeEventPayload) {
    isSaving.value = true
    try {
      await useApi().put(`/api/life-events/${id}`, payload)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to update life event.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }

  async function remove (id: string) {
    try {
      await useApi().delete(`/api/life-events/${id}`)
      lifeEvents.value = lifeEvents.value.filter(event => event.id !== id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to delete life event.'), { cause: error })
    }
  }

  async function cancel (id: string) {
    try {
      await useApi().post(`/api/life-events/${id}/cancel`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to cancel life event.'), { cause: error })
    }
  }

  async function restore (id: string) {
    try {
      await useApi().post(`/api/life-events/${id}/restore`)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to restore life event.'), { cause: error })
    }
  }

  return {
    lifeEvents,
    current,
    isLoading,
    isSaving,
    byId,
    fetchAll,
    fetchForPerson,
    fetchOne,
    create,
    update,
    remove,
    cancel,
    restore,
  }
})
