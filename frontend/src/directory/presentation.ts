import type { PersonSummary } from './types'
export const membershipLabels = {
  ACTIVE_MEMBER: 'Active Member',
  REGULAR_VISITOR: 'Regular Visitor',
  VISITOR: 'Visitor',
  TRANSFERRED: 'Transferred',
}
export const ageLabels = { CHILD: 'Child', ADULT: 'Adult', UNKNOWN: 'Age not recorded' }
export const relationLabels: Record<string, string> = {
  PARENT_OF: 'Parent of',
  CHILD_OF: 'Child of',
  GUARDIAN_OF: 'Guardian of',
  WARD_OF: 'Under guardianship of',
  SPOUSE_OF: 'Spouse of',
  SIBLING_OF: 'Sibling of',
  OTHER: 'Related to',
}
export function fullName (person: PersonSummary) {
  return `${person.details.firstName} ${person.details.lastName}`
}
export function initials (person: PersonSummary) {
  return `${person.details.firstName.charAt(0)}${person.details.lastName.charAt(0)}`.toUpperCase()
}
export function dateLabel (value?: string | null) {
  return value
    ? new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
      }).format(new Date(value.length === 10 ? `${value}T12:00:00Z` : value))
    : 'Not recorded'
}
export function churchToday (timeZone = 'Africa/Johannesburg') {
  const parts = new Intl.DateTimeFormat('en', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    timeZone,
  }).formatToParts(new Date())
  return ['year', 'month', 'day'].map(type => parts.find(part => part.type === type)?.value).join('-')
}
