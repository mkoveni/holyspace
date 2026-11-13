interface ActionOptions {
    /** Toast shown after success. */
    success?: string;
    /** Fallback when the thrown error has no message. */
    failure?: string;
    /** Ask first; the action only runs after a yes. */
    confirm?: {
        title: string;
        message: string;
        confirmText?: string;
        color?: string;
    };
    /** Offers an Undo button on the success toast. */
    undo?: () => Promise<unknown> | void;
    /**
     * Runs when the server answers 409 (the record changed under the user), typically to reload it.
     * The error toast still appears so the user knows their change was not saved.
     */
    onConflict?: () => Promise<unknown> | void;
}
/**
 * Wraps a mutation with loading state, an optional confirmation, a success toast and
 * an error toast, replacing the hand-written try/catch/notify block in each screen.
 * Resolves true when the action ran and succeeded.
 */
export declare function useAction(): {
    busy: import("vue").Ref<boolean, boolean>;
    run: (task: () => Promise<unknown>, options?: ActionOptions) => Promise<boolean>;
};
export {};
