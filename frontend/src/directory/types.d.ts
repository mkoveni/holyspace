export type MembershipType = 'ACTIVE_MEMBER' | 'REGULAR_VISITOR' | 'VISITOR' | 'TRANSFERRED';
export type AgeCategory = 'CHILD' | 'ADULT' | 'UNKNOWN';
export type LifeStatus = 'LIVING' | 'DECEASED' | 'UNKNOWN';
export type Gender = 'MALE' | 'FEMALE' | 'UNKNOWN';
export type RelationshipType = 'PARENT_OF' | 'GUARDIAN_OF' | 'SPOUSE_OF' | 'SIBLING_OF' | 'OTHER';
export interface PersonDetails {
    titleCode: string | null;
    firstName: string;
    lastName: string;
    dateOfBirth: string | null;
    gender: Gender;
    lifeStatus: LifeStatus;
    dateOfDeath: string | null;
}
export interface PersonSummary {
    id: string;
    details: PersonDetails;
    membershipType: MembershipType;
    ageCategory: AgeCategory;
    ageAsOf: string;
    archivedAt: string | null;
    version: number;
}
export interface Contact {
    id: string;
    personId: string;
    kind: 'EMAIL' | 'PHONE';
    value: string;
    primary: boolean;
    createdAt: string;
    endedAt: string | null;
}
export interface Address {
    id: string;
    personId: string;
    line1: string;
    line2: string;
    city: string;
    region: string;
    postalCode: string;
    countryCode: string;
    createdAt: string;
    endedAt: string | null;
}
export interface PersonProfile extends PersonSummary {
    contacts: Contact[];
    addresses: Address[];
}
export interface Household {
    id: string;
    name: string;
    primaryContactMembershipId: string | null;
    version: number;
    createdAt: string;
    updatedAt: string;
}
export interface HouseholdMembership {
    id: string;
    householdId: string;
    personId: string;
    primary: boolean;
    validFrom: string;
    validTo: string | null;
}
export interface HouseholdProfile {
    household: Household;
    memberships: HouseholdMembership[];
}
export interface Relationship {
    id: string;
    relatedPersonId: string;
    type: RelationshipType | 'CHILD_OF' | 'WARD_OF';
    validFrom: string;
    validTo: string | null;
    version: number;
}
export interface PeopleMetadata {
    membershipTypes: {
        code: MembershipType;
        label: string;
    }[];
    titles: {
        code: string;
        label: string;
    }[];
    genders: Gender[];
    lifeStatuses: LifeStatus[];
    relationshipTypes: RelationshipType[];
    adultAge: number;
    timeZone: string;
}
export interface PersonSearch {
    search?: string;
    includeArchived?: boolean;
    membershipType?: MembershipType | null;
    ageCategory?: AgeCategory | null;
    asOf?: string;
    limit?: number;
    offset?: number;
}
