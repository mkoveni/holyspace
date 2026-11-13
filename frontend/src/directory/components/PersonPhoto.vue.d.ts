type __VLS_Props = {
    personId: string;
    initials: string;
    name: string;
};
/** The photo is personal data: it is fetched with the session and shown from a local object URL. */
declare function load(): Promise<void>;
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {
    reload: typeof load;
}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
