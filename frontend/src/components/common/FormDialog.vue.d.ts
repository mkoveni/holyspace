/**
 * The one layout every form dialog uses: title, scrolling body, and a right-aligned
 * Cancel / primary action footer. The parent keeps its own submit logic and can still
 * call `formRef.value.validate()`, which is forwarded to the inner form.
 */
type __VLS_Props = {
    title: string;
    subtitle?: string;
    submitLabel?: string;
    cancelLabel?: string;
    submitColor?: string;
    loading?: boolean;
    disabled?: boolean;
    error?: string;
    persistent?: boolean;
    size?: 'sm' | 'md' | 'lg';
};
type __VLS_ModelProps = {
    modelValue?: boolean;
};
type __VLS_PublicProps = __VLS_Props & __VLS_ModelProps;
declare var __VLS_46: {}, __VLS_54: {};
type __VLS_Slots = {} & {
    default?: (props: typeof __VLS_46) => any;
} & {
    'actions-start'?: (props: typeof __VLS_54) => any;
};
declare const __VLS_base: import("vue").DefineComponent<__VLS_PublicProps, {
    validate: () => any;
    resetValidation: () => any;
    reset: () => any;
}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    cancel: () => any;
    "update:modelValue": (value: boolean) => any;
    submit: () => any;
}, string, import("vue").PublicProps, Readonly<__VLS_PublicProps> & Readonly<{
    onCancel?: (() => any) | undefined;
    "onUpdate:modelValue"?: ((value: boolean) => any) | undefined;
    onSubmit?: (() => any) | undefined;
}>, {
    size: "sm" | "md" | "lg";
    persistent: boolean;
    submitLabel: string;
    cancelLabel: string;
    submitColor: string;
}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const __VLS_export: __VLS_WithSlots<typeof __VLS_base, __VLS_Slots>;
declare const _default: typeof __VLS_export;
export default _default;
type __VLS_WithSlots<T, S> = T & {
    new (): {
        $slots: S;
    };
};
