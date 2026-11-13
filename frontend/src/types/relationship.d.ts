export interface Relationship {
    id: string;
    personId: string;
    relatedPersonId: string;
    relatedFirstName: string;
    relatedLastName: string;
    type: string;
    endedAt: string | null;
    createdAt: string;
    updatedAt: string;
}
export interface CreateRelationshipPayload {
    relatedPersonId: string;
    type: string;
}
export interface EndRelationshipPayload {
    endedAt?: string;
}
