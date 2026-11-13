export interface AttentionItem {
    id: string;
    icon: string;
    title: string;
    detail: string;
    to: string;
    tone: 'error' | 'warning' | 'info';
}
/** Things that need a person's attention, built from data the app already exposes. */
export declare function useAttention(): {
    items: import("vue").ComputedRef<AttentionItem[]>;
    loading: import("vue").Ref<boolean, boolean>;
    refresh: (force?: boolean) => Promise<void>;
    count: import("vue").ComputedRef<number>;
};
