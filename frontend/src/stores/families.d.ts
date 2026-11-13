import type { AddFamilyAddressPayload, AddFamilyMemberPayload, CreateFamilyPayload, Family, FamilyMember, FamilyOverview } from '@/types/family';
export declare const useFamiliesStore: import("pinia").StoreDefinition<"families", Pick<{
    overviews: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], FamilyOverview[] | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    overview: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, FamilyOverview | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoadingOverviews: import("vue").Ref<boolean, boolean>;
    savingContactId: import("vue").Ref<string | null, string | null>;
    fetchOverviews: () => Promise<FamilyOverview[]>;
    fetchOverview: (id: string) => Promise<FamilyOverview>;
    setPrimaryContact: (familyId: string, personId: string | null) => Promise<void>;
    reset: () => void;
    families: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], Family[] | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, Family | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    peopleByFamily: import("vue").Ref<Record<string, FamilyMember[]>, Record<string, FamilyMember[]>>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: () => Promise<Family[]>;
    fetchOne: (id: string) => Promise<Family>;
    create: (payload: CreateFamilyPayload) => Promise<string>;
    fetchFamilyPeople: (familyId: string) => Promise<FamilyMember[]>;
    addMember: (familyId: string, payload: AddFamilyMemberPayload) => Promise<void>;
    addFamilyAddress: (familyId: string, payload: AddFamilyAddressPayload) => Promise<string>;
}, "current" | "isLoading" | "isSaving" | "families" | "overviews" | "overview" | "isLoadingOverviews" | "savingContactId" | "peopleByFamily">, Pick<{
    overviews: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], FamilyOverview[] | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    overview: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, FamilyOverview | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoadingOverviews: import("vue").Ref<boolean, boolean>;
    savingContactId: import("vue").Ref<string | null, string | null>;
    fetchOverviews: () => Promise<FamilyOverview[]>;
    fetchOverview: (id: string) => Promise<FamilyOverview>;
    setPrimaryContact: (familyId: string, personId: string | null) => Promise<void>;
    reset: () => void;
    families: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], Family[] | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, Family | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    peopleByFamily: import("vue").Ref<Record<string, FamilyMember[]>, Record<string, FamilyMember[]>>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: () => Promise<Family[]>;
    fetchOne: (id: string) => Promise<Family>;
    create: (payload: CreateFamilyPayload) => Promise<string>;
    fetchFamilyPeople: (familyId: string) => Promise<FamilyMember[]>;
    addMember: (familyId: string, payload: AddFamilyMemberPayload) => Promise<void>;
    addFamilyAddress: (familyId: string, payload: AddFamilyAddressPayload) => Promise<string>;
}, never>, Pick<{
    overviews: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], FamilyOverview[] | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    overview: import("vue").Ref<{
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, FamilyOverview | {
        members: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        }[];
        addresses: {
            id: string;
            type: string;
            line1: string;
            line2: string | null;
            city: string;
            province: string | null;
            postalCode: string | null;
            country: string;
        }[];
        primaryContact: {
            id: string;
            firstName: string;
            lastName: string;
            dateOfBirth: string | null;
            gender: string;
            email: string | null;
            phone: string | null;
            role: string;
            membershipCreatedAt: string;
        } | null;
        upcomingEvents: {
            id: string;
            type: string;
            eventDate: string;
            notes: string | null;
            status: string;
            memberIds: string[];
            isAnniversary?: boolean | undefined;
            originalEventDate?: string | null | undefined;
        }[];
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoadingOverviews: import("vue").Ref<boolean, boolean>;
    savingContactId: import("vue").Ref<string | null, string | null>;
    fetchOverviews: () => Promise<FamilyOverview[]>;
    fetchOverview: (id: string) => Promise<FamilyOverview>;
    setPrimaryContact: (familyId: string, personId: string | null) => Promise<void>;
    reset: () => void;
    families: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[], Family[] | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null, Family | {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | null>;
    peopleByFamily: import("vue").Ref<Record<string, FamilyMember[]>, Record<string, FamilyMember[]>>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        name: string;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: () => Promise<Family[]>;
    fetchOne: (id: string) => Promise<Family>;
    create: (payload: CreateFamilyPayload) => Promise<string>;
    fetchFamilyPeople: (familyId: string) => Promise<FamilyMember[]>;
    addMember: (familyId: string, payload: AddFamilyMemberPayload) => Promise<void>;
    addFamilyAddress: (familyId: string, payload: AddFamilyAddressPayload) => Promise<string>;
}, "reset" | "fetchAll" | "fetchOne" | "create" | "fetchOverviews" | "fetchOverview" | "setPrimaryContact" | "byId" | "fetchFamilyPeople" | "addMember" | "addFamilyAddress">>;
