import type { ChurchEvent, CreateEventPayload, EventDetailsPayload, OccurrencePayload } from '@/types/event';
export declare const useEventsStore: import("pinia").StoreDefinition<"events", Pick<{
    reset: () => void;
    events: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[], ChurchEvent[] | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null, ChurchEvent | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    fetchAll: () => Promise<void>;
    fetchOne: (id: string) => Promise<void>;
    create: (payload: CreateEventPayload) => Promise<string>;
    update: (id: string, payload: EventDetailsPayload) => Promise<void>;
    updateOccurrence: (id: string, occurrenceId: string, payload: OccurrencePayload) => Promise<void>;
}, "events" | "current" | "isLoading" | "isSaving">, Pick<{
    reset: () => void;
    events: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[], ChurchEvent[] | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null, ChurchEvent | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    fetchAll: () => Promise<void>;
    fetchOne: (id: string) => Promise<void>;
    create: (payload: CreateEventPayload) => Promise<string>;
    update: (id: string, payload: EventDetailsPayload) => Promise<void>;
    updateOccurrence: (id: string, occurrenceId: string, payload: OccurrencePayload) => Promise<void>;
}, never>, Pick<{
    reset: () => void;
    events: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[], ChurchEvent[] | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    }[]>;
    current: import("vue").Ref<{
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null, ChurchEvent | {
        id: string;
        title: string;
        type: import("@/types/event").EventType;
        description: string | null;
        timezone: string;
        recurrence: "once" | "weekly";
        occurrences: {
            id: string;
            eventId: string;
            startsAt: string;
            endsAt: string;
            location: string;
            status: "scheduled" | "cancelled";
            createdAt: string;
            updatedAt: string;
        }[];
        createdAt: string;
        updatedAt: string;
    } | null>;
    isLoading: import("vue").Ref<boolean, boolean>;
    isSaving: import("vue").Ref<boolean, boolean>;
    fetchAll: () => Promise<void>;
    fetchOne: (id: string) => Promise<void>;
    create: (payload: CreateEventPayload) => Promise<string>;
    update: (id: string, payload: EventDetailsPayload) => Promise<void>;
    updateOccurrence: (id: string, occurrenceId: string, payload: OccurrencePayload) => Promise<void>;
}, "reset" | "fetchAll" | "fetchOne" | "create" | "update" | "updateOccurrence">>;
