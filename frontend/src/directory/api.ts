import type {
  Address,
  Contact,
  Household,
  HouseholdMembership,
  HouseholdProfile,
  MembershipType,
  PeopleMetadata,
  PersonDetails,
  PersonProfile,
  PersonSearch,
  PersonSummary,
  Relationship,
  RelationshipType,
} from './types'
import { useApi } from '@/composables/useApi'

// This client owns the Java directory contract. PHP directory payloads remain separate.
export const directoryApi = {
  async metadata () {
    return (await useApi().get<PeopleMetadata>('/api/people/metadata')).data
  },
  async people (params: PersonSearch = {}, signal?: AbortSignal) {
    return (
      await useApi().get<PersonSummary[]>('/api/people', {
        params: { limit: 25, offset: 0, ...params },
        signal,
      })
    ).data
  },
  async person (id: string, signal?: AbortSignal) {
    return (await useApi().get<PersonProfile>(`/api/people/${id}`, { signal })).data
  },
  async savePerson (person: PersonDetails, membershipType: MembershipType, existing?: PersonSummary | null) {
    if (existing) {
      await useApi().put(`/api/people/${existing.id}`, {
        person,
        membershipType,
        expectedVersion: existing.version,
      })
      return existing.id
    }
    return (await useApi().post<{ id: string }>('/api/people', { person, membershipType })).data.id
  },
  async archive (person: PersonSummary) {
    await useApi().put(`/api/people/${person.id}/archive`, {
      expectedVersion: person.version,
      archived: !person.archivedAt,
    })
  },
  async addTitle (code: string, label: string) {
    await useApi().post('/api/people/titles', { code, label })
  },
  async addContact (person: PersonSummary, contact: Pick<Contact, 'kind' | 'value' | 'primary'>) {
    await useApi().post(`/api/people/${person.id}/contacts`, { ...contact, expectedVersion: person.version })
  },
  async endContact (person: PersonSummary, id: string) {
    await useApi().post(`/api/people/${person.id}/contacts/${id}/end`, { expectedVersion: person.version })
  },
  async addAddress (
    person: PersonSummary,
    address: Pick<Address, 'line1' | 'line2' | 'city' | 'region' | 'postalCode' | 'countryCode'>,
  ) {
    await useApi().post(`/api/people/${person.id}/addresses`, { ...address, expectedVersion: person.version })
  },
  async endAddress (person: PersonSummary, id: string) {
    await useApi().post(`/api/people/${person.id}/addresses/${id}/end`, { expectedVersion: person.version })
  },
  async households (search = '', offset = 0, signal?: AbortSignal) {
    return (
      await useApi().get<Household[]>('/api/households', { params: { search, limit: 25, offset }, signal })
    ).data
  },
  async household (id: string, signal?: AbortSignal) {
    return (await useApi().get<HouseholdProfile>(`/api/households/${id}`, { signal })).data
  },
  async personHouseholds (id: string, signal?: AbortSignal) {
    return (await useApi().get<HouseholdMembership[]>(`/api/people/${id}/households`, { signal })).data
  },
  async saveHousehold (name: string, existing?: Household | null) {
    if (existing) {
      await useApi().put(`/api/households/${existing.id}`, { name, expectedVersion: existing.version })
      return existing.id
    }
    return (await useApi().post<{ id: string }>('/api/households', { name })).data.id
  },
  async addMember (household: Household, personId: string, primary: boolean) {
    await useApi().post(`/api/households/${household.id}/members`, {
      personId,
      primary,
      expectedVersion: household.version,
    })
  },
  async endMembership (household: Household, membershipId: string) {
    await useApi().post(`/api/households/${household.id}/members/${membershipId}/end`, {
      expectedVersion: household.version,
    })
  },
  async setContact (household: Household, membershipId: string | null) {
    await useApi().put(`/api/households/${household.id}/primary-contact`, {
      membershipId,
      expectedVersion: household.version,
    })
  },
  async relationships (id: string, signal?: AbortSignal) {
    return (await useApi().get<Relationship[]>(`/api/people/${id}/relationships`, { signal })).data
  },
  async addRelationship (sourceId: string, targetId: string, type: RelationshipType) {
    await useApi().post('/api/relationships', { sourceId, targetId, type })
  },
  async endRelationship (relationship: Relationship) {
    await useApi().post(`/api/relationships/${relationship.id}/end`, {
      expectedVersion: relationship.version,
    })
  },
}
