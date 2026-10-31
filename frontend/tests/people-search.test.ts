import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, expect, it, vi } from 'vitest'
import { matchesPerson, personSearchItem } from '@/components/common/peopleSearch'
import { useApi } from '@/composables/useApi'
import { usePeopleStore } from '@/stores/people'
import type { Person } from '@/types/person'

vi.mock('@/composables/useApi', () => ({ useApi: vi.fn(), extractErrorMessage: (error: Error) => error.message }))
beforeEach(() => setActivePinia(createPinia()))
const person = { id: 'person', firstName: 'José', lastName: 'Nkosi', email: 'jose@example.org', phone: '+27 82 123 4567', dateOfBirth: '1990-05-10' } as Person

it('finds people by accent-insensitive names, reordered words, email, and formatted phone numbers', () => {
  const item = personSearchItem(person)
  for (const query of ['jose', 'NKOSI jose', 'example.org', '821234567']) {
    expect(matchesPerson(item.title, query, { raw: item })).toBe(true)
  }
  expect(matchesPerson(item.title, 'another person', { raw: item })).toBe(false)
  expect(item.subtitle).toContain('jose@example.org')
  expect(item.subtitle).toContain('Born 1990-05-10')
})

it('shares concurrent loads and caches a successfully loaded empty directory', async () => {
  const get = vi.fn().mockResolvedValue({ data: [] })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const store = usePeopleStore()
  await Promise.all([store.ensureLoaded(), store.ensureLoaded(), store.fetchAll()])
  await store.ensureLoaded()
  expect(get).toHaveBeenCalledTimes(1)
})

it('allows retry after a failed load and fetches a fresh directory after logout', async () => {
  const get = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue({ data: [person] })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const store = usePeopleStore()
  await expect(store.ensureLoaded()).rejects.toThrow('Offline')
  await store.ensureLoaded()
  expect(store.people).toEqual([person])
  store.reset()
  expect(store.people).toEqual([])
  await store.ensureLoaded()
  expect(get).toHaveBeenCalledTimes(3)
})

it('does not restore a previous account directory when an old request finishes after logout', async () => {
  let finish!: (value: { data: Person[] }) => void
  const get = vi.fn().mockImplementationOnce(() => new Promise(resolve => { finish = resolve })).mockResolvedValue({ data: [] })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const store = usePeopleStore()
  const oldLoad = store.ensureLoaded()
  store.reset()
  await store.ensureLoaded()
  finish({ data: [person] })
  await oldLoad
  expect(store.people).toEqual([])
  await store.ensureLoaded()
  expect(get).toHaveBeenCalledTimes(2)
})

import { createSSRApp, defineComponent, h } from 'vue'
import { renderToString } from '@vue/server-renderer'
import PeopleSearch from '@/components/common/PeopleSearch.vue'

it('preserves single/multiple IDs, exclusions, disabled state, and form validation attributes', async () => {
  const get = vi.fn().mockResolvedValue({ data: [person, { ...person, id: 'other' }] })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  await usePeopleStore().ensureLoaded()
  let autocomplete: Record<string, any> = {}
  const updated = vi.fn()
  const required = (value: unknown) => Boolean(value) || 'Required'
  const app = createSSRApp(PeopleSearch, { modelValue: person.id, excludeIds: ['other'], disabled: true, rules: [required], 'onUpdate:modelValue': updated })
  app.component('v-autocomplete', defineComponent({ props: ['modelValue'], setup: (props, { attrs }) => () => { autocomplete = { ...attrs, ...props }; return h('picker') } }))
  app.component('v-btn', defineComponent({ setup: () => () => h('button') }))
  app.component('v-list-item', defineComponent({ setup: () => () => h('item') }))
  await renderToString(app)
  expect(autocomplete.items.map((item: { value: string }) => item.value)).toEqual([person.id])
  expect(autocomplete.modelValue).toBe(person.id)
  expect(autocomplete.disabled).toBe(true)
  expect(autocomplete.rules).toEqual([required])
  autocomplete['onUpdate:modelValue'](null)
  expect(updated).toHaveBeenCalledWith(null)

  const multi = createSSRApp(PeopleSearch, { modelValue: [person.id], multiple: true, 'onUpdate:modelValue': updated })
  multi.component('v-autocomplete', defineComponent({ props: ['modelValue'], setup: (props, { attrs }) => () => { autocomplete = { ...attrs, ...props }; return h('picker') } }))
  multi.component('v-list-item', defineComponent({ setup: () => () => h('item') }))
  multi.component('v-btn', defineComponent({ setup: () => () => h('button') }))
  await renderToString(multi)
  expect(autocomplete.multiple).toBe(true)
  expect(autocomplete.modelValue).toEqual([person.id])
  autocomplete['onUpdate:modelValue']([person.id, 'other'])
  expect(updated).toHaveBeenCalledWith([person.id, 'other'])
})

import { computed, effectScope, ref } from 'vue'
import { usePersonAutocomplete } from '@/composables/usePersonAutocomplete'

it('debounces backend queries, bounds results, and retains selected people across searches', async () => {
  vi.useFakeTimers()
  const get = vi.fn().mockResolvedValue({ data: { items: [person], hasMore: true } })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const ids = ref<string[]>([])
  const scope = effectScope()
  const search = scope.run(() => usePersonAutocomplete(computed(() => ids.value), computed(() => ['excluded'])))!
  expect(get).not.toHaveBeenCalled()
  search.query.value = 'j'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  expect(get).not.toHaveBeenCalled()
  search.query.value = 'jan'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(150)
  search.query.value = 'jane'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  expect(get).toHaveBeenCalledTimes(1)
  expect(get.mock.calls[0]![0]).toBe('/api/people/search')
  expect(get.mock.calls[0]![1].params).toEqual({ q: 'jane', limit: 20, excludeIds: ['excluded'] })
  expect(search.hasMore.value).toBe(true)
  ids.value = [person.id]
  await Promise.resolve()
  search.query.value = ''
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  expect(search.people.value.map(item => item.id)).toEqual([person.id])
  scope.stop()
  vi.useRealTimers()
})

it('hydrates saved IDs and ignores outdated backend responses', async () => {
  vi.useFakeTimers()
  let finish!: (value: unknown) => void
  const get = vi.fn().mockResolvedValueOnce({ data: { items: [person], hasMore: false } })
    .mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    .mockResolvedValue({ data: { items: [{ ...person, id: 'latest' }], hasMore: false } })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const scope = effectScope()
  const search = scope.run(() => usePersonAutocomplete(computed(() => [person.id]), computed(() => [])))!
  await Promise.resolve()
  expect(get.mock.calls[0]![1].params).toEqual({ ids: [person.id], limit: 50 })
  search.query.value = 'old'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  search.query.value = 'new'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  finish({ data: { items: [{ ...person, id: 'stale' }], hasMore: false } })
  await Promise.resolve()
  expect(search.people.value.map(item => item.id)).toEqual([person.id, 'latest'])
  scope.stop()
  vi.useRealTimers()
})

it('shows search failures and retries against the backend', async () => {
  vi.useFakeTimers()
  const get = vi.fn().mockRejectedValueOnce(new Error('Offline')).mockResolvedValue({ data: { items: [person], hasMore: false } })
  vi.mocked(useApi).mockReturnValue({ get } as unknown as ReturnType<typeof useApi>)
  const scope = effectScope()
  const search = scope.run(() => usePersonAutocomplete(computed(() => []), computed(() => [])))!
  search.query.value = 'jane'
  await Promise.resolve()
  await vi.advanceTimersByTimeAsync(300)
  expect(search.error.value).toBe('Offline')
  await search.retry()
  expect(search.error.value).toBe('')
  expect(search.people.value).toEqual([person])
  scope.stop()
  vi.useRealTimers()
})
