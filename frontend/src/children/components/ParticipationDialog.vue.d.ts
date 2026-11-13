import type { Enrolment, Programme, SafeguardingView, SupportRecord } from '../types';
export type ParticipationMode = 'enrol' | 'assign' | 'guardian' | 'consent' | 'pickup' | 'support' | 'end' | 'revoke';
type __VLS_Props = {
    mode: ParticipationMode;
    childId: string;
    childName: string;
    programmes: Programme[];
    enrolments: Enrolment[];
    safeguarding: SafeguardingView | null;
    names: Record<string, string>;
    defaultProgramme?: string;
    defaultEnrolment?: string;
    support?: SupportRecord | null;
    target?: {
        kind: 'guardian' | 'consent' | 'pickup' | 'enrolment' | 'assignment';
        id: string;
        version: number;
    } | null;
};
type __VLS_ModelProps = {
    modelValue: boolean;
};
type __VLS_PublicProps = __VLS_Props & __VLS_ModelProps;
declare const __VLS_export: import("vue").DefineComponent<__VLS_PublicProps, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "update:modelValue": (value: boolean) => any;
    reload: () => any;
    saved: () => any;
}, string, import("vue").PublicProps, Readonly<__VLS_PublicProps> & Readonly<{
    "onUpdate:modelValue"?: ((value: boolean) => any) | undefined;
    onReload?: (() => any) | undefined;
    onSaved?: (() => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
