import type { Person } from '@/types/person'

export type PersonSearchPerson = Pick<Person, 'id' | 'firstName' | 'lastName' | 'email' | 'phone' | 'dateOfBirth'>

export function personSearchItem (person: PersonSearchPerson) {
  return {
    value: person.id,
    title: `${person.firstName} ${person.lastName}`,
    subtitle: [person.email, person.phone, person.dateOfBirth ? `Born ${person.dateOfBirth}` : null].filter(Boolean).join(' · '),
    search: `${person.firstName} ${person.lastName} ${person.email ?? ''} ${person.phone ?? ''}`,
  }
}

function normalise (value: string) {
  return value.normalize('NFD').replace(/[\u0300-\u036F]/g, '').toLocaleLowerCase().trim()
}

export function matchesPerson (value: string, query: string, item?: { raw?: { search?: string } }) {
  const text = normalise(item?.raw?.search ?? value)
  return normalise(query).split(/\s+/).every(term => text.includes(term)
    || (/^[\d+()-]+$/.test(term) && text.replace(/[^\d+]/g, '').includes(term.replace(/[^\d+]/g, ''))))
}
