import type { FormPage, Leaderboard, SubmitResult } from '@/forms/api';
declare global {
    interface Window {
        turnstile?: {
            render: (el: HTMLElement, options: Record<string, unknown>) => string;
            reset: (id?: string) => void;
        };
    }
}
type __VLS_Props = {
    page: FormPage;
    /** Sends the answers; the public flavour adds the human check fields. */
    send: (body: Record<string, unknown>) => Promise<SubmitResult>;
    loadBoard: () => Promise<Leaderboard>;
    /** Show the Turnstile check (public forms only). */
    human?: boolean;
};
declare const __VLS_export: import("vue").DefineComponent<__VLS_Props, {}, {}, {}, {}, import("vue").ComponentOptionsMixin, import("vue").ComponentOptionsMixin, {}, string, import("vue").PublicProps, Readonly<__VLS_Props> & Readonly<{}>, {}, {}, {}, {}, string, import("vue").ComponentProvideOptions, false, {}, any>;
declare const _default: typeof __VLS_export;
export default _default;
