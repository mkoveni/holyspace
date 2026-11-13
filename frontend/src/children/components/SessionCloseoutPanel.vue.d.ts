import type { SessionView } from '../types';
type __VLS_Props = {
    view: SessionView;
    revision: string;
    canStation: boolean;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    reload: () => any;
    history: () => any;
    attending: () => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    onReload?: (() => any) | undefined;
    onHistory?: (() => any) | undefined;
    onAttending?: (() => any) | undefined;
}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
