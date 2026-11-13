type __VLS_Props = {
    width: number;
    height: number;
    label?: string;
    src?: string;
    disabled?: boolean;
    maxBytes?: number;
    maxDimension?: number;
    upload?: (file: File) => Promise<void>;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {
    cancel: () => any;
    cropped: (file: File) => any;
}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{
    onCancel?: (() => any) | undefined;
    onCropped?: ((file: File) => any) | undefined;
}>, {
    label: string;
    disabled: boolean;
    src: string;
    maxBytes: number;
    maxDimension: number;
}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
