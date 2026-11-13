type __VLS_Props = {
    clickable?: boolean;
    emptyTitle?: string;
    emptyText?: string;
    emptyIcon?: string;
};
declare var __VLS_12: string, __VLS_13: any;
type __VLS_Slots = {} & {
    [K in NonNullable<typeof __VLS_12>]?: (props: typeof __VLS_13) => any;
};
declare const __VLS_base: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "row-click": (item: any) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    "onRow-click"?: ((item: any) => any) | undefined;
}>, {
    emptyIcon: string;
    clickable: boolean;
    emptyTitle: string;
    emptyText: string;
}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const __VLS_export: __VLS_WithSlots<typeof __VLS_base, __VLS_Slots>;
declare const _default: typeof __VLS_export;
export default _default;
type __VLS_WithSlots<T, S> = T & {
    new (): {
        $slots: S;
    };
};
