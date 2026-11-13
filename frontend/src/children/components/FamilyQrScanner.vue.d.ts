import type { FamilyQr } from '../familyQr';
type __VLS_Props = {
    kind: 'arrival' | 'collection';
    resourceId: string;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    scanned: (reference: FamilyQr) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    onScanned?: ((reference: FamilyQr) => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
