import type { AuthenticatedUser, LoginCredentials } from '@/types/auth'
import axios from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'

const TOKEN_STORAGE_KEY = 'holyspace.auth.token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_STORAGE_KEY))
  const user = ref<AuthenticatedUser | null>(null)
  const isBootstrapping = ref(false)

  const isAuthenticated = computed(() => Boolean(token.value))
  const roles = computed(() => user.value?.roles ?? [])
  const hasRole = computed(() => (role: string) => (user.value?.roles ?? []).includes(role))
  const displayName = computed(() => user.value?.username ?? 'Administrator')

  function setToken (value: string) {
    token.value = value
    localStorage.setItem(TOKEN_STORAGE_KEY, value)
  }

  function clearSession () {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_STORAGE_KEY)
  }

  function logout () {
    clearSession()
  }

  async function fetchCurrentUser () {
    try {
      const { data } = await useApi().get<AuthenticatedUser>('/api/me')
      user.value = data
    } catch (error) {
      clearSession()
      throw error
    }
  }

  async function login (credentials: LoginCredentials) {
    // The login endpoint must not attach a stale bearer token, so it is
    // called with a bare axios instance rather than useApi().
    const baseURL = import.meta.env.VITE_API_BASE_URL ?? '/'
    try {
      const { data } = await axios.post<{ token: string }>(
        `${baseURL.replace(/\/$/, '')}/api/login_check`,
        credentials,
      )
      setToken(data.token)
      await fetchCurrentUser()
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Invalid username or password.'), { cause: error })
    }
  }

  async function bootstrap () {
    if (!token.value || user.value) {
      return
    }
    isBootstrapping.value = true
    try {
      await fetchCurrentUser()
    } catch {
      // Session is invalid/expired, state already cleared.
    } finally {
      isBootstrapping.value = false
    }
  }

  return {
    token,
    user,
    isBootstrapping,
    isAuthenticated,
    roles,
    hasRole,
    displayName,
    login,
    fetchCurrentUser,
    bootstrap,
    setToken,
    clearSession,
    logout,
  }
})
