export interface RegistrationSettings {
  id: string
  occurrenceId: string
  open: boolean
  capacity: number | null
  ticketPriceMinor: number | null
  waitlistEnabled: boolean
  version: number
}
export interface EventRegistration {
  id: string
  occurrenceId: string
  personId: string
  personName: string
  status: 'confirmed' | 'waitlisted' | 'cancelled' | 'pending_payment'
  queueToken: string
  version: number
  createdAt: string
  history: { previousStatus: string, status: string, reason: string, changedAt: string }[]
}
export interface RegistrationOverview {
  settings: RegistrationSettings
  counts: Record<'confirmed' | 'waitlisted' | 'cancelled' | 'pending_payment', number>
  remaining: number | null
  acceptingRegistrations: boolean
  items: EventRegistration[]
  nextAfter: string | null
}
