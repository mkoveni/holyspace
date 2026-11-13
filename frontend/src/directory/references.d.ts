import type { PersonProfile } from './types';
/** Bound parallel requests while resolving relationship/member names from the API. */
export declare function personReferences(ids: string[], signal: AbortSignal): Promise<Record<string, PersonProfile>>;
