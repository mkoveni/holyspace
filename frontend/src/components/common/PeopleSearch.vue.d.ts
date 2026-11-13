type __VLS_Props = {
    modelValue?: unknown;
    label?: string;
    multiple?: boolean;
    disabled?: boolean;
    excludeIds?: string[];
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "update:model-value": (value: string | string[] | null) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    "onUpdate:model-value"?: ((value: string | string[] | null) => any) | undefined;
}>, {
    label: string;
    disabled: boolean;
    multiple: boolean;
    excludeIds: string[];
}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
