import type { CreatePermissionPayload, Permission } from '@/types/permission';
export declare const usePermissionsStore: import("pinia").StoreDefinition<"permissions", Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    permissions: import("vue").Ref<{
        id: string;
        code: string;
        description?: string | undefined;
    }[], Permission[] | {
        id: string;
        code: string;
        description?: string | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreatePermissionPayload) => Promise<Permission>;
}, "isSaving" | "permissions">, Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    permissions: import("vue").Ref<{
        id: string;
        code: string;
        description?: string | undefined;
    }[], Permission[] | {
        id: string;
        code: string;
        description?: string | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreatePermissionPayload) => Promise<Permission>;
}, never>, Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    permissions: import("vue").Ref<{
        id: string;
        code: string;
        description?: string | undefined;
    }[], Permission[] | {
        id: string;
        code: string;
        description?: string | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreatePermissionPayload) => Promise<Permission>;
}, "reset" | "fetchAll" | "create">>;
