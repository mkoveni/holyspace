import type { ComputedRef } from 'vue';
export declare function usePersonAutocomplete(selectedIds: ComputedRef<string[]>, excludedIds: ComputedRef<string[]>): {
    query: import("vue").Ref<string, string>;
    people: ComputedRef<{
        id: string;
        firstName: string;
        lastName: string;
        email: string | null;
        phone: string | null;
        dateOfBirth: string | null;
    }[]>;
    loading: import("vue").Ref<boolean, boolean>;
    error: ComputedRef<string>;
    hasMore: import("vue").Ref<boolean, boolean>;
    retry: () => Promise<void>;
};
