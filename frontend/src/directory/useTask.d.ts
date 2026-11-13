export declare function useTask(options?: {
    preserveConflictMessage?: boolean;
}): {
    busy: import("vue").Ref<boolean, boolean>;
    error: import("vue").Ref<string, string>;
    conflict: import("vue").Ref<boolean, boolean>;
    clear: () => void;
    run: <T>(operation: () => Promise<T>) => Promise<{
        value: T;
    } | undefined>;
};
