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

function createApiClient (): AxiosInstance {
  const client = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL ?? '/',
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
    response => response,
    async error => {
      if (error?.response?.status === 401) {
        const authStore = useAuthStore()
        authStore.clearSession()
        if (router.currentRoute.value.name !== 'login') {
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

export function extractErrorMessage (error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string } | undefined
    return data?.message ?? error.message ?? fallback
  }
  if (error instanceof Error) {
    return error.message
  }
  return fallback
}
