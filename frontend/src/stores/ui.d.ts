export type SnackbarColor = 'success' | 'error' | 'warning' | 'info';
interface ConfirmOptions {
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    color?: string;
}
export declare const useUiStore: import("pinia").StoreDefinition<"ui", Pick<{
    snackbar: {
        show: boolean;
        text: string;
        color: SnackbarColor;
        timeout: number;
        undoLabel: string;
    };
    confirm: {
        show: boolean;
        title: string;
        message: string;
        confirmText: string;
        cancelText: string;
        color: string;
    };
    isLoading: import("vue").Ref<boolean, boolean>;
    notify: (text: string, color?: SnackbarColor, timeout?: number) => void;
    notifySuccess: (text: string) => void;
    notifyError: (text: string) => void;
    notifyWithUndo: (text: string, undo: () => Promise<unknown> | void) => void;
    runUndo: () => Promise<void>;
    confirmAction: (options: ConfirmOptions) => Promise<boolean>;
    resolveConfirm: (result: boolean) => void;
    setLoading: (value: boolean) => void;
}, "isLoading" | "snackbar" | "confirm">, Pick<{
    snackbar: {
        show: boolean;
        text: string;
        color: SnackbarColor;
        timeout: number;
        undoLabel: string;
    };
    confirm: {
        show: boolean;
        title: string;
        message: string;
        confirmText: string;
        cancelText: string;
        color: string;
    };
    isLoading: import("vue").Ref<boolean, boolean>;
    notify: (text: string, color?: SnackbarColor, timeout?: number) => void;
    notifySuccess: (text: string) => void;
    notifyError: (text: string) => void;
    notifyWithUndo: (text: string, undo: () => Promise<unknown> | void) => void;
    runUndo: () => Promise<void>;
    confirmAction: (options: ConfirmOptions) => Promise<boolean>;
    resolveConfirm: (result: boolean) => void;
    setLoading: (value: boolean) => void;
}, never>, Pick<{
    snackbar: {
        show: boolean;
        text: string;
        color: SnackbarColor;
        timeout: number;
        undoLabel: string;
    };
    confirm: {
        show: boolean;
        title: string;
        message: string;
        confirmText: string;
        cancelText: string;
        color: string;
    };
    isLoading: import("vue").Ref<boolean, boolean>;
    notify: (text: string, color?: SnackbarColor, timeout?: number) => void;
    notifySuccess: (text: string) => void;
    notifyError: (text: string) => void;
    notifyWithUndo: (text: string, undo: () => Promise<unknown> | void) => void;
    runUndo: () => Promise<void>;
    confirmAction: (options: ConfirmOptions) => Promise<boolean>;
    resolveConfirm: (result: boolean) => void;
    setLoading: (value: boolean) => void;
}, "notify" | "notifySuccess" | "notifyError" | "notifyWithUndo" | "runUndo" | "confirmAction" | "resolveConfirm" | "setLoading">>;
export {};
