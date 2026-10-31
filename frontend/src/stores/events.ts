import type { ChurchEvent, CreateEventPayload, EventDetailsPayload, OccurrencePayload } from '@/types/event'
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

export const useEventsStore = defineStore('events', () => {
  const events = ref<ChurchEvent[]>([])
  const current = ref<ChurchEvent | null>(null)
  const isLoading = ref(false)
  const isSaving = ref(false)

  async function fetchAll () {
    isLoading.value = true
    try {
      events.value = (await useApi().get<ChurchEvent[]>('/api/events')).data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load events.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }
  async function fetchOne (id: string) {
    isLoading.value = true
    current.value = null
    try {
      current.value = (await useApi().get<ChurchEvent>(`/api/events/${id}`)).data
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to load event.'), { cause: error })
    } finally {
      isLoading.value = false
    }
  }
  async function create (payload: CreateEventPayload) {
    isSaving.value = true
    try {
      return (await useApi().post<{ id: string }>('/api/events', payload)).data.id
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to create event.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }
  async function update (id: string, payload: EventDetailsPayload) {
    isSaving.value = true
    try {
      await useApi().put(`/api/events/${id}`, payload)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to update event.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }
  async function updateOccurrence (id: string, occurrenceId: string, payload: OccurrencePayload) {
    isSaving.value = true
    try {
      await useApi().put(`/api/events/${id}/occurrences/${occurrenceId}`, payload)
      await fetchOne(id)
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Unable to update occurrence.'), { cause: error })
    } finally {
      isSaving.value = false
    }
  }
  function reset () {
    events.value = []
    current.value = null
  }
  return { reset, events, current, isLoading, isSaving, fetchAll, fetchOne, create, update, updateOccurrence }
})
