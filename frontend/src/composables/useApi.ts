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

function createApiClient(): AxiosInstance {
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

export function useApi(): AxiosInstance {
  if (!instance) {
    instance = createApiClient()
  }
  return instance
}

export function extractErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (axios.isCancel(error)) {
    return error.message
  }
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string, detail?: string, violations?: { propertyPath?: string, title?: string, message?: string }[] } | undefined
    if (data?.violations?.length) {
      return data.violations.map(violation => [violation.propertyPath, violation.title ?? violation.message].filter(Boolean).join(': ')).join('; ')
    }
    if (data?.message || data?.detail) {
      return data.message ?? data.detail ?? fallback
    }
    if (!error.response) {
      return 'Unable to reach the server. Check your connection and try again.'
    }
    if (error.response.status === 403) {
      return 'You do not have permission to perform this action.'
    }
    if (error.response.status >= 500) {
      return 'The server could not complete this request. Please try again.'
    }
    return fallback
  }
  if (error instanceof Error) {
    return error.message
  }
  return fallback
}
