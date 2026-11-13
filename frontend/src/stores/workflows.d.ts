import type { OperationalRecord, WorkflowAuditPage } from '@/types/workflow';
export declare const useWorkflowsStore: import("pinia").StoreDefinition<"workflows", Pick<{
    data: import("vue").Ref<Record<string, Record<string, OperationalRecord[]>>, Record<string, Record<string, OperationalRecord[]>>>;
    isSaving: import("vue").Ref<boolean, boolean>;
    load: (context: string, params?: Record<string, unknown>) => Promise<void>;
    save: (path: string, payload: Record<string, unknown>, method?: "POST" | "PUT", version?: number) => Promise<any>;
    audit: (context: string, id: string, before?: number | null) => Promise<WorkflowAuditPage>;
    reset: () => void;
}, "isSaving" | "data">, Pick<{
    data: import("vue").Ref<Record<string, Record<string, OperationalRecord[]>>, Record<string, Record<string, OperationalRecord[]>>>;
    isSaving: import("vue").Ref<boolean, boolean>;
    load: (context: string, params?: Record<string, unknown>) => Promise<void>;
    save: (path: string, payload: Record<string, unknown>, method?: "POST" | "PUT", version?: number) => Promise<any>;
    audit: (context: string, id: string, before?: number | null) => Promise<WorkflowAuditPage>;
    reset: () => void;
}, never>, Pick<{
    data: import("vue").Ref<Record<string, Record<string, OperationalRecord[]>>, Record<string, Record<string, OperationalRecord[]>>>;
    isSaving: import("vue").Ref<boolean, boolean>;
    load: (context: string, params?: Record<string, unknown>) => Promise<void>;
    save: (path: string, payload: Record<string, unknown>, method?: "POST" | "PUT", version?: number) => Promise<any>;
    audit: (context: string, id: string, before?: number | null) => Promise<WorkflowAuditPage>;
    reset: () => void;
}, "reset" | "load" | "save" | "audit">>;
