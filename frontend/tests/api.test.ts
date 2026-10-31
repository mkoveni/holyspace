import type { InternalAxiosRequestConfig } from 'axios'
import axios, { AxiosError } from 'axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { extractErrorMessage, useApi } from '@/composables/useApi'
import { useAuthStore } from '@/stores/auth'
import { usePeopleStore } from '@/stores/people'
import { useRolesStore } from '@/stores/roles'
import { useUserAccountsStore } from '@/stores/userAccounts'

vi.mock('@/router', () => ({ default: {
  currentRoute: { value: { name: 'people', fullPath: '/people', matched: [] } },
  push: vi.fn().mockResolvedValue(undefined),
} }))

const user = { id: 'user-1', username: 'admin', personId: null, status: 'enabled', roles: [], permissions: [], createdAt: '', updatedAt: '' }
const response = (config: InternalAxiosRequestConfig, data: unknown, status = 200) => ({ config, data, status, statusText: '', headers: {} })

beforeEach(() => {
  const storage = new Map<string, string>()
  vi.stubGlobal('localStorage', {
    getItem: (key: string) => storage.get(key) ?? null,
    setItem: (key: string, value: string) => storage.set(key, value),
    removeItem: (key: string) => storage.delete(key),
  })
  setActivePinia(createPinia())
})

describe('authentication and private data', () => {
  it('preserves the token during outages and verifies it on retry', async () => {
    const auth = useAuthStore()
    auth.setToken('valid-token')
    useApi().defaults.adapter = async config => {
      throw new AxiosError('offline', 'ERR_NETWORK', config)
    }
    await auth.bootstrap()
    expect(auth.token).toBe('valid-token')
    expect(auth.sessionError).toContain('Unable to reach the server')
    useApi().defaults.adapter = async config => response(config, user)
    await auth.bootstrap()
    expect(auth.user?.id).toBe('user-1')
    expect(auth.sessionError).toBe('')
  })

  it('clears the session and cached records on an authentication failure', async () => {
    const auth = useAuthStore()
    auth.setToken('expired-token')
    useRolesStore().roles.push({ id: 'role-1', name: 'admin' })
    useApi().defaults.adapter = async config => {
      throw new AxiosError('expired', 'ERR_BAD_REQUEST', config, undefined, response(config, {}, 401))
    }
    await auth.bootstrap()
    expect(auth.token).toBeNull()
    expect(localStorage.getItem('holyspace.auth.token')).toBeNull()
    expect(useRolesStore().roles).toEqual([])
  })

  it('discards successful responses from a previous session', async () => {
    const auth = useAuthStore()
    auth.setToken('first-token')
    let finish!: () => void
    let started!: () => void
    const dispatched = new Promise<void>(resolve => {
      started = resolve
    })
    useApi().defaults.adapter = config => new Promise(resolve => {
      finish = () => resolve(response(config, [{ id: 'private-person' }]))
      started()
    })
    const request = usePeopleStore().fetchAll()
    await dispatched
    auth.setToken('second-token')
    finish()
    await expect(request).rejects.toThrow('Session changed')
    expect(usePeopleStore().people).toEqual([])
    expect(auth.token).toBe('second-token')
  })

  it('does not clear a new session when an old request returns 401', async () => {
    const auth = useAuthStore()
    auth.setToken('first-token')
    useApi().defaults.adapter = async config => {
      auth.setToken('second-token')
      throw new AxiosError('expired', 'ERR_BAD_REQUEST', config, undefined, response(config, {}, 401))
    }
    await expect(useApi().get('/api/me')).rejects.toSatisfy(axios.isCancel)
    expect(auth.token).toBe('second-token')
  })
})

it('uses the backend users routes for account management and bearer authentication', async () => {
  useAuthStore().setToken('valid-token')
  const calls: string[] = []
  useApi().defaults.adapter = async config => {
    calls.push(`${config.method} ${config.url}`)
    expect(config.headers.get('Authorization')).toBe('Bearer valid-token')
    return response(config, config.url?.endsWith('/permissions') ? ['people.read'] : { ...user, id: 'user-1' })
  }
  const accounts = useUserAccountsStore()
  await accounts.create({ username: 'admin', password: 'password' })
  await accounts.fetchPermissions('user-1')
  await accounts.changePassword('user-1', { password: 'new-password' })
  await accounts.disable('user-1')
  await accounts.enable('user-1')
  await accounts.assignRole('user-1', 'role-1')
  await accounts.removeRole('user-1', 'role-1')
  expect(calls).toEqual([
    'post /api/users', 'get /api/users/user-1', 'get /api/users/user-1/permissions',
    'put /api/users/user-1/password', 'post /api/users/user-1/disable', 'get /api/users/user-1',
    'post /api/users/user-1/enable', 'get /api/users/user-1',
    'post /api/users/user-1/roles/role-1', 'delete /api/users/user-1/roles/role-1',
  ])
})

it('shows useful validation and authorization errors', () => {
  const config = { headers: new axios.AxiosHeaders() } as InternalAxiosRequestConfig
  const validation = new AxiosError('invalid', undefined, config, undefined, response(config, {
    violations: [{ propertyPath: 'firstName', title: 'This value should not be blank.' }],
  }, 422))
  expect(extractErrorMessage(validation)).toBe('firstName: This value should not be blank.')
  const forbidden = new AxiosError('forbidden', undefined, config, undefined, response(config, {}, 403))
  expect(extractErrorMessage(forbidden)).toContain('do not have permission')
})

it('allows operational navigation only for active accounts with the explicit permission', () => {
  const auth = useAuthStore()
  auth.user = { ...user, status: 'active', permissions: ['giving.manage'] }
  expect(auth.hasPermission('giving.manage')).toBe(true)
  expect(auth.hasPermission('children.manage')).toBe(false)
  auth.user.status = 'disabled'
  expect(auth.hasPermission('giving.manage')).toBe(false)
  auth.clearSession()
  expect(auth.hasPermission('giving.manage')).toBe(false)
})

it('allows management to include read access while read-only roles cannot manage', () => {
  const auth = useAuthStore()
  auth.user = { ...user, status: 'active', permissions: ['people.manage', 'events.read'] }
  expect(auth.hasPermission('people.read')).toBe(true)
  expect(auth.hasPermission('events.read')).toBe(true)
  expect(auth.hasPermission('events.manage')).toBe(false)
})
