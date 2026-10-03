export interface LifeEvent {
  id: string
  type: string
  participants: string[]
  eventDate: string
  details: Record<string, unknown> | null
  notes: string | null
  status: string
  createdAt: string
  updatedAt: string
}

export interface CreateLifeEventPayload {
  type: string
  participants: string[]
  eventDate: string
  details?: Record<string, unknown>
  notes?: string
}

export type UpdateLifeEventPayload = Partial<CreateLifeEventPayload>
