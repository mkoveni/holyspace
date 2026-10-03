export interface Relationship {
  id: string
  personId: string
  relatedPersonId: string
  type: string
  startDate: string | null
  endDate: string | null
}

export interface CreateRelationshipPayload {
  relatedPersonId: string
  type: string
}

export interface EndRelationshipPayload {
  endedAt?: string
}
