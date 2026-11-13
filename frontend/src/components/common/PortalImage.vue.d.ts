type __VLS_Props = {
    target: 'person' | 'family' | 'event' | 'community' | 'form';
    ownerId?: string;
    label: string;
    editable?: boolean;
    publicToken?: string;
    width?: number;
    height?: number;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
