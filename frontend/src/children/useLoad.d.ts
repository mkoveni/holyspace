export declare function useLoad<T>(operation: () => Promise<T>, initial: T): {
    data: import("vue").Ref<any, any> extends T ? T extends T & import("vue").Ref<any, any> ? import("@vue/shared").IfAny<T, import("vue").ShallowRef<T, T>, T> : import("vue").ShallowRef<T, T> : import("vue").ShallowRef<T, T>;
    loading: import("vue").Ref<boolean, boolean>;
    error: import("vue").Ref<string, string>;
    load: () => Promise<void>;
};
