import type { Enrolment, Programme } from '../types';
type __VLS_Props = {
    childId: string;
    programmes: Programme[];
    defaultProgramme: string;
    revision: number;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    action: (value: {
        mode: "enrol" | "assign" | "guardian" | "consent" | "pickup";
        programmeId: string;
        enrolment: Enrolment | null;
    }) => any;
    "programme-change": (id: string) => any;
    care: () => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    onAction?: ((value: {
        mode: "enrol" | "assign" | "guardian" | "consent" | "pickup";
        programmeId: string;
        enrolment: Enrolment | null;
    }) => any) | undefined;
    "onProgramme-change"?: ((id: string) => any) | undefined;
    onCare?: (() => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
