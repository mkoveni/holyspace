/**
 * composables/apiErrors.ts
 *
 * One place that turns a failed request into something a screen can act on.
 * Pure (no router or store imports) so it can be unit tested and used anywhere.
 */
import axios from 'axios'

export type ApiErrorKind
  = | 'cancelled'
    | 'network'
    | 'unauthorized'
    | 'forbidden'
    | 'not-found'
    | 'conflict'
    | 'validation'
    | 'rate-limited'
    | 'server'
    | 'unknown'

export interface ApiErrorInfo {
  kind: ApiErrorKind
  status: number | null
  message: string
}

interface ErrorBody {
  message?: string
  detail?: string
  violations?: { propertyPath?: string, title?: string, message?: string }[]
}

const DEFAULT_MESSAGE = 'Something went wrong. Please try again.'

function kindForStatus (status: number): ApiErrorKind {
  if (status === 401) {
    return 'unauthorized'
  }
  if (status === 403) {
    return 'forbidden'
  }
  if (status === 404) {
    return 'not-found'
  }
  if (status === 409) {
    return 'conflict'
  }
  if (status === 422 || status === 400) {
    return 'validation'
  }
  if (status === 429) {
    return 'rate-limited'
  }
  return status >= 500 ? 'server' : 'unknown'
}

/** The server's own wording wins; otherwise a plain-language message for the kind of failure. */
const FALLBACKS: Partial<Record<ApiErrorKind, string>> = {
  'network': 'Unable to reach the server. Check your connection and try again.',
  'unauthorized': 'Your session has ended. Please sign in again.',
  'forbidden': 'You do not have permission to perform this action.',
  'conflict': 'This was changed by someone else. Reload to see the latest version, then try again.',
  'rate-limited': 'Too many attempts. Wait a moment and try again.',
  'server': 'The server could not complete this request. Please try again.',
}

export function classifyError (error: unknown, fallback = DEFAULT_MESSAGE): ApiErrorInfo {
  if (axios.isCancel(error)) {
    return { kind: 'cancelled', status: null, message: error.message }
  }
  if (axios.isAxiosError(error)) {
    if (!error.response) {
      return { kind: 'network', status: null, message: FALLBACKS.network! }
    }
    const status = error.response.status
    const kind = kindForStatus(status)
    const data = error.response.data as ErrorBody | undefined
    const violations = data?.violations?.length
      ? data.violations
          .map(violation => [violation.propertyPath, violation.title ?? violation.message].filter(Boolean).join(': '))
          .join('; ')
      : ''
    const message = violations || data?.message || data?.detail || FALLBACKS[kind] || fallback
    return { kind, status, message }
  }
  if (error instanceof Error && error.message) {
    return { kind: 'unknown', status: null, message: error.message }
  }
  return { kind: 'unknown', status: null, message: fallback }
}

export function extractErrorMessage (error: unknown, fallback = DEFAULT_MESSAGE): string {
  return classifyError(error, fallback).message
}

/** True for a 409: the record changed under the user (stale expectedVersion or a duplicate). */
export function isConflict (error: unknown): boolean {
  return classifyError(error).kind === 'conflict'
}
