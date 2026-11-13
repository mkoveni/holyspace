export type ApiErrorKind = 'cancelled' | 'network' | 'unauthorized' | 'forbidden' | 'not-found' | 'conflict' | 'validation' | 'rate-limited' | 'server' | 'unknown';
export interface ApiErrorInfo {
    kind: ApiErrorKind;
    status: number | null;
    message: string;
}
export declare function classifyError(error: unknown, fallback?: string): ApiErrorInfo;
export declare function extractErrorMessage(error: unknown, fallback?: string): string;
/** True for a 409: the record changed under the user (stale expectedVersion or a duplicate). */
export declare function isConflict(error: unknown): boolean;
