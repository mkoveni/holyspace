/** Light/dark switch that keeps Vuetify and our CSS tokens in step. */
export declare function useAppTheme(): {
    mode: import("vue").Ref<"light" | "dark", "light" | "dark">;
    init: () => void;
    toggle: () => void;
};
