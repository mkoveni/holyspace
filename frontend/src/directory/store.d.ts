import type { PeopleMetadata } from './types';
export declare const useDirectoryStore: import("pinia").StoreDefinition<"javaDirectory", Pick<{
    metadata: import("vue").Ref<{
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null, PeopleMetadata | {
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null>;
    loadMetadata: () => Promise<PeopleMetadata>;
    reset: () => void;
}, "metadata">, Pick<{
    metadata: import("vue").Ref<{
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null, PeopleMetadata | {
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null>;
    loadMetadata: () => Promise<PeopleMetadata>;
    reset: () => void;
}, never>, Pick<{
    metadata: import("vue").Ref<{
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null, PeopleMetadata | {
        membershipTypes: {
            code: import("./types").MembershipType;
            label: string;
        }[];
        titles: {
            code: string;
            label: string;
        }[];
        genders: import("./types").Gender[];
        lifeStatuses: import("./types").LifeStatus[];
        relationshipTypes: import("./types").RelationshipType[];
        adultAge: number;
        timeZone: string;
    } | null>;
    loadMetadata: () => Promise<PeopleMetadata>;
    reset: () => void;
}, "loadMetadata" | "reset">>;
