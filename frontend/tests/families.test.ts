import type { FamilyOverview } from '@/types/family'
import { renderToString } from '@vue/server-renderer'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, it, vi } from 'vitest'
import { createSSRApp, defineComponent, h } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'
import { useFamiliesStore } from '@/stores/families'
import FamilyOverviewCard from '@/views/families/components/FamilyOverviewCard.vue'

vi.mock('@/composables/useApi', () => ({
  useApi: vi.fn(),
  extractErrorMessage: vi.fn((error: Error) => error.message),
}))

const family: FamilyOverview = {
  id: 'family-1', name: 'Doe household', createdAt: '2026-01-01', updatedAt: '2026-01-01',
  members: [
    { id: 'p1', firstName: 'Jane', lastName: 'Doe', role: 'head', dateOfBirth: null, gender: 'female', email: 'jane@example.com', phone: '+27123456789', membershipCreatedAt: '2026-01-01' },
    { id: 'p2', firstName: 'John', lastName: 'Doe', role: 'child', dateOfBirth: null, gender: 'male', email: null, phone: null, membershipCreatedAt: '2026-01-01' },
  ],
  addresses: [{ id: 'a1', type: 'residential', line1: '12 Main Road', line2: null, city: 'Cape Town', province: 'Western Cape', postalCode: '8000', country: 'South Africa' }],
  primaryContact: null,
  upcomingEvents: [{ id: 'event-1', type: 'baptism', eventDate: '2026-11-01', notes: 'Sunday service', status: 'active', memberIds: ['p2'] }],
}
family.primaryContact = family.members[0]!

beforeEach(() => setActivePinia(createPinia()))

async function renderCard (data: FamilyOverview) {
  const app = createSSRApp(FamilyOverviewCard, { family: data })
  // Render content without a browser or UI-library layout dependencies.
  app.component('RouterLink', defineComponent({ props: ['to'], setup: (props, { slots }) => () => h('a', { href: props.to }, slots.default?.()) }))
  for (const name of ['VBtn', 'VIcon']) {
    app.component(name, defineComponent({ setup: (_, { slots }) => () => h('span', {}, slots.default?.()) }))
  }
  return renderToString(app)
}

it('renders every member, role, address, contact detail, and upcoming event', async () => {
  const html = await renderCard(family)
  for (const text of ['Jane Doe', 'John Doe', 'Head', 'Child', '12 Main Road', 'Cape Town', '8000', 'South Africa', 'jane@example.com', '+27123456789', 'Baptism', 'Sunday service']) {
    expect(html).toContain(text)
  }
  expect(html).toContain('href="/people/p1"')
  expect(html).toContain('href="/life-events/event-1"')
  expect(html).toContain('href="mailto:jane@example.com"')
})

it('renders honest empty states without inventing a primary contact', async () => {
  const html = await renderCard({ ...family, members: [], addresses: [], upcomingEvents: [], primaryContact: null })
  expect(html).toContain('No primary contact selected')
  expect(html).toContain('No family address recorded')
  expect(html).toContain('No upcoming events scheduled')
  expect(html).toContain('Add the first member')
})

it('refreshes the directory and detail after changing the primary contact', async () => {
  const get = vi.fn().mockResolvedValue({ data: family })
  const put = vi.fn().mockResolvedValue({})
  vi.mocked(useApi).mockReturnValue({ get, put } as unknown as ReturnType<typeof useApi>)
  const store = useFamiliesStore()
  store.overviews = [{ ...family, primaryContact: null }]
  await store.setPrimaryContact('family-1', 'p1')
  expect(put).toHaveBeenCalledWith('/api/families/family-1/primary-contact', { personId: 'p1' })
  expect(get).toHaveBeenCalledWith('/api/families/family-1/overview')
  expect(store.overviews[0]?.primaryContact?.id).toBe('p1')
  expect(store.overview?.primaryContact?.id).toBe('p1')
})

it('refreshes the overview after adding an address', async () => {
  const get = vi.fn().mockResolvedValue({ data: family })
  const post = vi.fn().mockResolvedValue({ data: { id: 'a1' } })
  vi.mocked(useApi).mockReturnValue({ get, post } as unknown as ReturnType<typeof useApi>)
  await useFamiliesStore().addFamilyAddress('family-1', { type: 'residential', line1: '12 Main Road', city: 'Cape Town', country: 'South Africa' })
  expect(get).toHaveBeenCalledWith('/api/families/family-1/overview')
  expect(useFamiliesStore().overview?.addresses[0]?.line1).toBe('12 Main Road')
  expect(extractErrorMessage).not.toHaveBeenCalled()
})

it('shows a compact family directory row with links to the full household and next event', async () => {
  const { default: FamilyDirectoryTable } = await import('@/views/families/components/FamilyDirectoryTable.vue')
  const app = createSSRApp(FamilyDirectoryTable, { families: [family], loading: false })
  app.component('RouterLink', defineComponent({ props: ['to'], setup: (props, { slots }) => () => h('a', { href: props.to }, slots.default?.()) }))
  app.component('VBtn', defineComponent({ props: ['to'], setup: (props, { slots }) => () => h('a', { href: props.to }, slots.default?.()) }))
  app.component('VIcon', defineComponent({ setup: (_, { slots }) => () => h('span', {}, slots.default?.()) }))
  app.component('VDataTable', defineComponent({
    props: ['headers', 'items'],
    setup: (props, { slots }) => () => {
      const body = props.items.map((item: FamilyOverview) => h('tr', props.headers.map((header: { key: string }) => h('td', slots[`item.${header.key}`]?.({ item })))))
      return h('table', [
        h('thead', props.headers.map((header: { title: string }) => h('th', header.title))),
        h('tbody', body),
      ])
    },
  }))
  const html = await renderToString(app)
  for (const value of ['Household', 'Members', 'Primary contact', 'Address', 'Upcoming', 'Doe household', '2 members', 'Jane Doe', '12 Main Road', 'baptism']) {
    expect(html).toContain(value)
  }
  expect(html).toContain('href="/families/family-1"')
  expect(html).toContain('href="/life-events/event-1"')
})

it('shows anniversary labels and the upcoming occurrence date', async () => {
  const data = {
    ...family,
    upcomingEvents: [
      { id: 'wedding', type: 'marriage', eventDate: '2026-10-10', originalEventDate: '2000-10-10', isAnniversary: true, notes: null, status: 'completed', memberIds: ['p1', 'p2'] },
      { id: 'birth', type: 'birth', eventDate: '2026-10-11', originalEventDate: '1990-10-11', isAnniversary: true, notes: null, status: 'active', memberIds: ['p1'] },
    ],
  }
  const html = await renderCard(data)
  expect(html).toContain('Marriage anniversary')
  expect(html).toContain('Birthday')
  expect(html).toContain('href="/life-events/wedding"')
  expect(html).toContain('Jane Doe')
  expect(html).toContain('John Doe')
})
