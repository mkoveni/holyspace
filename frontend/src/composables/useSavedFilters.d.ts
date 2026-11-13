import { type Ref } from 'vue';
export interface SavedView<T> {
    name: string;
    filters: T;
}
/**
 * Remembers the last filters used on a screen and lets people name and reuse filter sets.
 * Stored only in this browser.
 */
export declare function useSavedFilters<T extends object>(screen: string, current: Ref<T>, defaults: T): {
    views: Ref<SavedView<T>[], SavedView<T>[]>;
    save: (name: string) => void;
    apply: (view: SavedView<T>) => void;
    remove: (name: string) => void;
    reset: () => void;
};
