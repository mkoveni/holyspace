export const lifeEventTypes = ['birth', 'baptism', 'confirmation', 'salvation', 'dedication', 'membership', 'engagement', 'marriage', 'graduation', 'funeral', 'death', 'other'] as const
export type LifeEventType = typeof lifeEventTypes[number]
export type LifeEventStatus = 'active' | 'completed' | 'cancelled'

export const participantRoles = ['subject', 'spouse', 'proposer', 'recipient', 'child', 'parent', 'deceased', 'graduate', 'baptized', 'other'] as const
export type ParticipantRole = typeof participantRoles[number]
export interface LifeEventParticipant {
  personId: string
  role: ParticipantRole
}

export interface LifeEvent {
  id: string
  type: LifeEventType
  participants: LifeEventParticipant[]
  eventDate: string
  details: Record<string, unknown> | null
  notes: string | null
  status: LifeEventStatus
  createdAt: string
  updatedAt: string
}

export interface CreateLifeEventPayload {
  type: LifeEventType
  participants: LifeEventParticipant[]
  eventDate: string
  details?: Record<string, unknown>
  notes?: string
}

export type UpdateLifeEventPayload = CreateLifeEventPayload
