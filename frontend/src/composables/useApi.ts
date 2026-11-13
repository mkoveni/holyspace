/**
 * composables/useApi.ts
 *
 * Centralised Axios instance used by every Pinia store to talk to the
 * Church Management Portal backend. Attaches the JWT bearer token
 * automatically and redirects to the login page on 401 responses.
 */
import axios, { type AxiosInstance } from 'axios'
import router from '@/router'
import { useAuthStore } from '@/stores/auth'

let instance: AxiosInstance | null = null

/**
 * Absolute or root-relative URL of an API path, for calls made without the
 * shared client (plain fetch). Production sets VITE_API_BASE_URL to the API host.
 */
export function apiUrl (path: string): string {
  return (import.meta.env.VITE_API_BASE_URL ?? '/').replace(/\/+$/, '') + path
}

function createApiClient (): AxiosInstance {
  const client = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
    timeout: 15_000,
    headers: {
      'Content-Type': 'application/json',
    },
  })

  client.interceptors.request.use(config => {
    const authStore = useAuthStore()
    if (authStore.token) {
      config.headers.set('Authorization', `Bearer ${authStore.token}`)
    }
    return config
  })

  client.interceptors.response.use(
    response => {
      const authStore = useAuthStore()
      const sentToken = response.config.headers.get('Authorization')
      if (sentToken && sentToken !== `Bearer ${authStore.token}`) {
        throw new axios.CanceledError('Session changed while the request was in progress.')
      }
      return response
    },
    async error => {
      const authStore = useAuthStore()
      const sentToken = error?.config?.headers?.get('Authorization')
      if (sentToken && sentToken !== `Bearer ${authStore.token}`) {
        throw new axios.CanceledError('Session changed while the request was in progress.')
      }
      if (error?.response?.status === 401) {
        authStore.clearSession()
        if (!router.currentRoute.value.matched.some(route => route.meta.public)) {
          await router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
        }
      }
      throw error
    },
  )

  return client
}

export function useApi (): AxiosInstance {
  if (!instance) {
    instance = createApiClient()
  }
  return instance
}

export { classifyError, extractErrorMessage, isConflict } from './apiErrors'
export type { ApiErrorInfo, ApiErrorKind } from './apiErrors'
