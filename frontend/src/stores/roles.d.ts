import type { CreateRolePayload, Role } from '@/types/role';
/**
 * Roles are loaded from the privileged backend catalogue.
 */
export declare const useRolesStore: import("pinia").StoreDefinition<"roles", Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    roles: import("vue").Ref<{
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[], Role[] | {
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreateRolePayload) => Promise<Role>;
    addPermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
    removePermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
}, "isSaving" | "roles">, Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    roles: import("vue").Ref<{
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[], Role[] | {
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreateRolePayload) => Promise<Role>;
    addPermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
    removePermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
}, never>, Pick<{
    fetchAll: () => Promise<void>;
    reset: () => void;
    roles: import("vue").Ref<{
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[], Role[] | {
        id: string;
        name: string;
        description?: string | undefined;
        permissions?: string[] | undefined;
    }[]>;
    isSaving: import("vue").Ref<boolean, boolean>;
    create: (payload: CreateRolePayload) => Promise<Role>;
    addPermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
    removePermission: (roleId: string, permissionId: string, code?: string) => Promise<void>;
}, "reset" | "fetchAll" | "create" | "addPermission" | "removePermission">>;
