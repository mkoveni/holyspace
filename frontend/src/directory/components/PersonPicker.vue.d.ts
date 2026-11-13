import type { AgeCategory, PersonSummary } from '../types';
type __VLS_Props = {
    initialPerson?: PersonSummary;
    modelValue: string | null;
    label?: string;
    exclude?: string[];
    ageCategory?: AgeCategory;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "update:model-value": (value: string | null) => any;
    selected: (person: PersonSummary | null) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    "onUpdate:model-value"?: ((value: string | null) => any) | undefined;
    onSelected?: ((person: PersonSummary | null) => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
