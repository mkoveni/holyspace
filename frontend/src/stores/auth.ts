import type { AuthenticatedUser, LoginCredentials } from '@/types/auth'
import axios from 'axios'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'
import { isJavaApi } from '@/directory/backend'
import { useDirectoryStore } from '@/directory/store'
import { useEventsStore } from '@/stores/events'
import { useFamiliesStore } from '@/stores/families'
import { useLifeEventsStore } from '@/stores/lifeEvents'
import { usePeopleStore } from '@/stores/people'
import { usePermissionsStore } from '@/stores/permissions'
import { useRolesStore } from '@/stores/roles'
import { useUserAccountsStore } from '@/stores/userAccounts'
import { useWorkflowsStore } from '@/stores/workflows'

const TOKEN_STORAGE_KEY = isJavaApi ? 'divinecommunityspace.java.auth.token' : 'divinecommunityspace.auth.token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem(TOKEN_STORAGE_KEY))
  const user = ref<AuthenticatedUser | null>(null)
  const isBootstrapping = ref(false)
  const sessionError = ref('')
  let bootstrapPromise: Promise<void> | null = null

  const isAuthenticated = computed(() => Boolean(token.value))
  const roles = computed(() => user.value?.roles ?? [])
  const hasRole = computed(() => (role: string) => roles.value.includes(role))
  const hasPermission = (permission: string) =>
    user.value?.status === 'active'
    && Boolean(
      user.value.permissions?.includes(permission)
      || (permission === 'followups.work' && user.value.permissions?.includes('followups.manage'))
      || (permission === 'volunteering.serve' && user.value.permissions?.includes('volunteering.manage'))
      || (permission === 'ministries.lead' && user.value.permissions?.includes('ministries.manage'))
      || (permission.endsWith('.read')
        && user.value.permissions?.includes(`${permission.slice(0, -5)}.manage`)),
    )
  const displayName = computed(() => user.value?.displayName || user.value?.username || 'Signed-in user')

  function setToken (value: string) {
    clearSession()
    token.value = value
    localStorage.setItem(TOKEN_STORAGE_KEY, value)
  }

  function clearSession () {
    token.value = null
    user.value = null
    sessionError.value = ''
    localStorage.removeItem(TOKEN_STORAGE_KEY)
    if (typeof sessionStorage !== 'undefined') {
      for (const key of Object.keys(sessionStorage)) {
        if (key.startsWith('divinecommunityspace.payment-request.')) {
          sessionStorage.removeItem(key)
        }
      }
    }
    useDirectoryStore().reset()
    usePeopleStore().reset()
    useFamiliesStore().reset()
    useLifeEventsStore().reset()
    useEventsStore().reset()
    useWorkflowsStore().reset()
    useUserAccountsStore().reset()
    useRolesStore().reset()
    usePermissionsStore().reset()
  }

  async function logout () {
    try {
      if (isJavaApi && token.value) {
        await useApi().post('/api/logout')
      }
    } finally {
      clearSession()
    }
  }

  async function fetchCurrentUser () {
    try {
      const { data } = await useApi().get<AuthenticatedUser>('/api/me')
      user.value = data
      sessionError.value = ''
    } catch (error) {
      // The shared client clears invalid sessions on 401. Temporary outages
      // must leave the token available so the user can retry.
      if (token.value && !axios.isCancel(error)) {
        sessionError.value = extractErrorMessage(error, 'Unable to verify your session. Please retry.')
      }
      throw error
    }
  }

  async function login (credentials: LoginCredentials) {
    // Login must not attach a stale bearer token.
    const baseURL = import.meta.env.VITE_API_BASE_URL ?? '/'
    try {
      const { data } = await axios.post<{ token: string }>(
        `${baseURL.replace(/\/$/, '')}/api/login_check`,
        credentials,
        { timeout: 15_000 },
      )
      setToken(data.token)
      await fetchCurrentUser()
    } catch (error) {
      throw new Error(extractErrorMessage(error, 'Invalid username or password.'), { cause: error })
    }
  }

  function bootstrap (): Promise<void> {
    if (bootstrapPromise) {
      return bootstrapPromise
    }
    if (!token.value || user.value) {
      return Promise.resolve()
    }

    isBootstrapping.value = true
    bootstrapPromise = fetchCurrentUser()
      .catch(() => {
        /* Invalid sessions are cleared; temporary errors are shown with a retry action. */
      })
      .finally(() => {
        isBootstrapping.value = false
        bootstrapPromise = null
      })
    return bootstrapPromise
  }

  return {
    token,
    user,
    sessionError,
    isBootstrapping,
    isAuthenticated,
    roles,
    hasRole,
    hasPermission,
    displayName,
    login,
    fetchCurrentUser,
    bootstrap,
    setToken,
    clearSession,
    logout,
  }
})
