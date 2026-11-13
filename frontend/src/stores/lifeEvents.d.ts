import type { CreateLifeEventPayload, LifeEvent, UpdateLifeEventPayload } from '@/types/lifeEvent';
export declare const useLifeEventsStore: import("pinia").StoreDefinition<"lifeEvents", Pick<{
    reset: () => void;
    lifeEvents: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[], LifeEvent[] | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null, LifeEvent | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: (type?: string, filters?: {
        month?: number;
        year?: number;
        anniversaries?: boolean;
    }) => Promise<LifeEvent[]>;
    fetchForPerson: (personId: string) => Promise<LifeEvent[]>;
    fetchOne: (id: string) => Promise<LifeEvent>;
    create: (payload: CreateLifeEventPayload) => Promise<string>;
    update: (id: string, payload: UpdateLifeEventPayload) => Promise<void>;
    remove: (id: string) => Promise<void>;
    cancel: (id: string) => Promise<void>;
    restore: (id: string) => Promise<void>;
}, "current" | "isLoading" | "isSaving" | "lifeEvents">, Pick<{
    reset: () => void;
    lifeEvents: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[], LifeEvent[] | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null, LifeEvent | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: (type?: string, filters?: {
        month?: number;
        year?: number;
        anniversaries?: boolean;
    }) => Promise<LifeEvent[]>;
    fetchForPerson: (personId: string) => Promise<LifeEvent[]>;
    fetchOne: (id: string) => Promise<LifeEvent>;
    create: (payload: CreateLifeEventPayload) => Promise<string>;
    update: (id: string, payload: UpdateLifeEventPayload) => Promise<void>;
    remove: (id: string) => Promise<void>;
    cancel: (id: string) => Promise<void>;
    restore: (id: string) => Promise<void>;
}, never>, Pick<{
    reset: () => void;
    lifeEvents: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[], LifeEvent[] | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null, LifeEvent | {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    byId: (id: string) => {
        id: string;
        type: import("@/types/lifeEvent").LifeEventType;
        participants: {
            personId: string;
            role: import("@/types/lifeEvent").ParticipantRole;
        }[];
        eventDate: string;
        details: Record<string, unknown> | null;
        notes: string | null;
        status: import("@/types/lifeEvent").LifeEventStatus;
        createdAt: string;
        updatedAt: string;
    } | undefined;
    fetchAll: (type?: string, filters?: {
        month?: number;
        year?: number;
        anniversaries?: boolean;
    }) => Promise<LifeEvent[]>;
    fetchForPerson: (personId: string) => Promise<LifeEvent[]>;
    fetchOne: (id: string) => Promise<LifeEvent>;
    create: (payload: CreateLifeEventPayload) => Promise<string>;
    update: (id: string, payload: UpdateLifeEventPayload) => Promise<void>;
    remove: (id: string) => Promise<void>;
    cancel: (id: string) => Promise<void>;
    restore: (id: string) => Promise<void>;
}, "reset" | "fetchAll" | "fetchOne" | "create" | "update" | "byId" | "fetchForPerson" | "remove" | "cancel" | "restore">>;
