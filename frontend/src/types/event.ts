export const eventTypes = ['service', 'meeting', 'conference', 'outreach', 'class', 'other'] as const
export type EventType = typeof eventTypes[number]
export interface EventOccurrence {
  id: string
  eventId: string
  startsAt: string
  endsAt: string
  location: string
  status: 'scheduled' | 'cancelled'
  createdAt: string
  updatedAt: string
}
export interface ChurchEvent {
  id: string
  title: string
  type: EventType
  description: string | null
  timezone: string
  recurrence: 'once' | 'weekly'
  occurrences: EventOccurrence[]
  createdAt: string
  updatedAt: string
}
export interface EventDetailsPayload {
  title: string
  type: EventType
  description: string | null
}
export interface CreateEventPayload extends EventDetailsPayload {
  timezone: string
  startsAt: string
  endsAt: string
  location: string
  recurrence: 'once' | 'weekly'
  occurrenceCount: number
}
export type OccurrencePayload = Pick<EventOccurrence, 'startsAt' | 'endsAt' | 'location' | 'status'>
