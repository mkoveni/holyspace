import type { WorkflowField } from '@/types/workflow';
export declare function localInput(value: string): string;
export declare function fieldPayload(fields: WorkflowField[], values: Record<string, unknown>): Record<string, unknown>;
export declare function copyFormValue<T>(value: T): T;
