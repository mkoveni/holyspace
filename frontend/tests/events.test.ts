import type { ChurchEvent, CreateEventPayload } from '@/types/event'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, it, vi } from 'vitest'
import { useApi } from '@/composables/useApi'
import { nextOccurrence } from '@/events/schedule'
import { useEventsStore } from '@/stores/events'

vi.mock('@/composables/useApi', () => ({
  useApi: vi.fn(),
  extractErrorMessage: vi.fn((error: Error) => error.message),
}))

beforeEach(() => setActivePinia(createPinia()))
const payload: CreateEventPayload = { title: 'Sunday service', type: 'service', description: null, timezone: 'Africa/Johannesburg', startsAt: '2026-10-11T09:00', endsAt: '2026-10-11T11:00', location: 'Church', recurrence: 'weekly', occurrenceCount: 4 }
const event: ChurchEvent = { id: 'event-1', title: payload.title, type: payload.type, description: null, timezone: payload.timezone, recurrence: 'weekly', createdAt: '2026-10-10', updatedAt: '2026-10-10', occurrences: [] }

it('sends local schedule inputs without converting them to browser UTC', async () => {
  const post = vi.fn().mockResolvedValue({ data: { id: event.id } })
  vi.mocked(useApi).mockReturnValue({ post } as unknown as ReturnType<typeof useApi>)
  const store = useEventsStore()
  expect(await store.create(payload)).toBe(event.id)
  expect(post).toHaveBeenCalledWith('/api/events', payload)
  expect(store.isSaving).toBe(false)
})

it('updates only the selected occurrence and reloads its parent event', async () => {
  const put = vi.fn().mockResolvedValue({})
  const get = vi.fn().mockResolvedValue({ data: event })
  vi.mocked(useApi).mockReturnValue({ put, get } as unknown as ReturnType<typeof useApi>)
  const store = useEventsStore()
  const change = { startsAt: '2026-10-11T10:00', endsAt: '2026-10-11T12:00', location: 'Hall', status: 'cancelled' as const }
  await store.updateOccurrence(event.id, 'occurrence-1', change)
  expect(put).toHaveBeenCalledWith('/api/events/event-1/occurrences/occurrence-1', change)
  expect(get).toHaveBeenCalledWith('/api/events/event-1')
  expect(store.current).toEqual(event)
  expect(store.isSaving).toBe(false)
})

it('shows errors and releases the saving state for retry', async () => {
  const post = vi.fn().mockRejectedValue(new Error('End time must be after start time.'))
  vi.mocked(useApi).mockReturnValue({ post } as unknown as ReturnType<typeof useApi>)
  const store = useEventsStore()
  await expect(store.create(payload)).rejects.toThrow('End time must be after start time.')
  expect(store.isSaving).toBe(false)
})

it('clears events and current details when the session is reset', async () => {
  const get = vi.fn().mockResolvedValue({ data: [event] })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const store = useEventsStore()
  await store.fetchAll()
  expect(store.events).toEqual([event])
  get.mockResolvedValue({ data: event })
  await store.fetchOne(event.id)
  store.reset()
  expect(store.events).toEqual([])
  expect(store.current).toBeNull()
})

it('finds the next scheduled occurrence by instant, excluding past and cancelled occurrences', () => {
  const occurrence = { eventId: event.id, startsAt: '2026-10-11T09:00:00+02:00', endsAt: '2026-10-11T11:00:00+02:00', location: 'Church', status: 'scheduled' as const, createdAt: '2026-10-10', updatedAt: '2026-10-10' }
  const data: ChurchEvent = { ...event, occurrences: [
    { ...occurrence, id: 'later', startsAt: '2026-10-18T09:00:00+02:00', endsAt: '2026-10-18T11:00:00+02:00' },
    { ...occurrence, id: 'past', startsAt: '2026-10-04T09:00:00+02:00', endsAt: '2026-10-04T11:00:00+02:00' },
    { ...occurrence, id: 'cancelled', status: 'cancelled' },
    { ...occurrence, id: 'next' },
  ] }
  expect(nextOccurrence(data, Date.parse('2026-10-10T12:00:00Z'))?.id).toBe('next')
  expect(nextOccurrence(data, Date.parse('2026-10-11T08:00:00Z'))?.id).toBe('next')
  expect(nextOccurrence(data, Date.parse('2026-10-19T12:00:00Z'))).toBeUndefined()
})
