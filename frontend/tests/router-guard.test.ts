import { beforeEach, describe, expect, it, vi } from 'vitest'

type TestUser = {
  status: string
  permissions: string[]
}

const auth = vi.hoisted(() => ({
  token: null as string | null,
  user: null as unknown,
  isAuthenticated: false,
  bootstrap: async () => {},
  hasPermission: (_permission: string) => false,
}))

vi.mock('vue-router', async importOriginal => {
  const actual = await importOriginal<Record<string, any>>()
  return { ...actual, createWebHistory: () => actual.createMemoryHistory() }
})
vi.mock('@/stores/auth', () => ({ useAuthStore: () => auth }))

function signIn (user: TestUser | null) {
  auth.token = user ? 'token' : null
  auth.isAuthenticated = Boolean(user)
  auth.user = user
  auth.hasPermission = permission =>
    Boolean(user && user.status === 'active' && user.permissions.includes(permission))
}

async function visit (path: string) {
  const { default: router } = await import('@/router')
  await router.replace('/forms/public/x').catch(() => {})
  await router.push(path).catch(() => {})
  return router.currentRoute.value
}

describe('router guard', () => {
  beforeEach(() => {
    vi.resetModules()
    signIn(null)
  })

  it('lets anonymous visitors reach public pages', async () => {
    expect((await visit('/give')).name).toBe('public-give')
    expect((await visit('/visit/abc')).name).toBe('public-visit')
  })

  it('sends anonymous visitors to login and keeps the destination', async () => {
    const route = await visit('/people')
    expect(route.name).toBe('login')
    expect(route.query.redirect).toBe('/people')
  })

  it('denies a signed-in account that lacks the route permission', async () => {
    signIn({ status: 'active', permissions: ['reports.read'] })
    expect((await visit('/people')).name).toBe('forbidden')
  })

  it('allows a signed-in account that has the route permission', async () => {
    signIn({ status: 'active', permissions: ['people.read'] })
    expect((await visit('/people')).name).toBe('people')
  })

  it('treats a suspended account as having no permissions', async () => {
    signIn({ status: 'suspended', permissions: ['people.read'] })
    expect((await visit('/people')).name).toBe('forbidden')
  })

  it('shows routes the Symfony API does not back as unavailable', async () => {
    signIn({ status: 'active', permissions: ['reports.read'] })
    const route = await visit('/reports')
    expect(route.name).toBe('not-found')
  })

  it('moves a signed-in user off the login page to their landing page', async () => {
    signIn({ status: 'active', permissions: ['people.read'] })
    expect((await visit('/login')).name).toBe('people')
  })

  it('lands accounts without staff access on My giving', async () => {
    signIn({ status: 'active', permissions: [] })
    expect((await visit('/')).name).toBe('my-giving')
  })

  it('shows not-found for unknown paths', async () => {
    expect((await visit('/no/such/page')).name).toBe('not-found')
  })
})
