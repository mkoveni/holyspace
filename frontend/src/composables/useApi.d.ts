/**
 * composables/useApi.ts
 *
 * Centralised Axios instance used by every Pinia store to talk to the
 * Church Management Portal backend. Attaches the JWT bearer token
 * automatically and redirects to the login page on 401 responses.
 */
import { type AxiosInstance } from 'axios';
/**
 * Absolute or root-relative URL of an API path, for calls made without the
 * shared client (plain fetch). Production sets VITE_API_BASE_URL to the API host.
 */
export declare function apiUrl(path: string): string;
export declare function useApi(): AxiosInstance;
export { classifyError, extractErrorMessage, isConflict } from './apiErrors';
export type { ApiErrorInfo, ApiErrorKind } from './apiErrors';
