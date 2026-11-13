import type { FamilyOverview } from '@/types/family';
type __VLS_Props = {
    family: FamilyOverview;
    busy?: boolean;
    canManage?: boolean;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    "add-member": () => any;
    "add-address": () => any;
    "primary-contact": (personId: string | null) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    "onAdd-member"?: (() => any) | undefined;
    "onAdd-address"?: (() => any) | undefined;
    "onPrimary-contact"?: ((personId: string | null) => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
