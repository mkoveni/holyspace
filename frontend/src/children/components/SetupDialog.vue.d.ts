import type { Location } from '../types';
import type { PersonSummary } from '@/directory/types';
type __VLS_Props = {
    mode: 'location' | 'room' | 'worker' | 'purge';
    locations: Location[];
    defaultLocation?: string;
    defaultPerson?: PersonSummary;
};
type __VLS_ModelProps = {
    modelValue: boolean;
};
type __VLS_PublicProps = __VLS_Props & __VLS_ModelProps;
declare const __VLS_export: import("vue").DefineComponent<__VLS_PublicProps, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "update:modelValue": (value: boolean) => any;
    saved: () => any;
    "location-created": (location: Location) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_PublicProps> & Readonly<{
    "onUpdate:modelValue"?: ((value: boolean) => any) | undefined;
    onSaved?: (() => any) | undefined;
    "onLocation-created"?: ((location: Location) => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
