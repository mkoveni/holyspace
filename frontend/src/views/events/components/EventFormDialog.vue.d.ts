import type { ChurchEvent } from '@/types/event';
type __VLS_Props = {
    modelValue: boolean;
    event?: ChurchEvent | null;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "update:model-value": (args_0: boolean) => any;
    saved: (args_0: string) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    "onUpdate:model-value"?: ((args_0: boolean) => any) | undefined;
    onSaved?: ((args_0: string) => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
