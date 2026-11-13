export type OperationalRecord = {
    [key: string]: unknown;
    id: string;
};
export interface WorkflowField {
    key: string;
    label: string;
    type?: 'hidden' | 'money' | 'text' | 'textarea' | 'number' | 'boolean' | 'date' | 'datetime' | 'select' | 'multi' | 'tags' | 'intervals' | 'allocations';
    options?: string | {
        title: string;
        value: string;
    }[];
    nullable?: boolean;
    required?: boolean;
    requiredOnEdit?: boolean;
    default?: unknown;
    readonlyOnEdit?: boolean;
}
export interface WorkflowAction {
    label: string;
    path: (record: OperationalRecord) => string;
    payload: (record: OperationalRecord) => Record<string, unknown>;
    available?: (record: OperationalRecord) => boolean;
}
export interface WorkflowSection {
    key: string;
    title: string;
    fields: WorkflowField[];
    createLabel?: string;
    createPath?: string;
    editPath?: (record: OperationalRecord) => string;
    actions?: WorkflowAction[];
    columns?: {
        title: string;
        key: string;
    }[];
}
export interface WorkflowDefinition {
    context: string;
    title: string;
    subtitle: string;
    sections: WorkflowSection[];
    filters?: WorkflowField[];
    dependencies?: string[];
    occurrencePlanning?: boolean;
}
export interface WorkflowAuditEntry {
    id: string | number;
    recordId: string;
    kind: string;
    action: string;
    version: number | null;
    actorId: string | null;
    actorName?: string | null;
    occurredAt: string;
    source?: string;
    changes?: Record<string, {
        fromPresent: boolean;
        from: unknown;
        toPresent: boolean;
        to: unknown;
    }>;
}
export interface WorkflowAuditPage {
    entries: WorkflowAuditEntry[];
    nextBefore: number | null;
}
