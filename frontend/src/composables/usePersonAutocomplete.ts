import type { PersonSearchPerson } from '@/components/common/peopleSearch'
import type { ComputedRef } from 'vue'
import { computed, onScopeDispose, ref, watch } from 'vue'
import { extractErrorMessage, useApi } from '@/composables/useApi'
import { usePeopleStore } from '@/stores/people'

interface SearchResult { items: PersonSearchPerson[], hasMore: boolean }

export function usePersonAutocomplete (selectedIds: ComputedRef<string[]>, excludedIds: ComputedRef<string[]>) {
  const store = usePeopleStore()
  const query = ref('')
  const results = ref<PersonSearchPerson[]>([])
  const selected = ref<PersonSearchPerson[]>(selectedIds.value.flatMap(id => store.byId(id) ? [store.byId(id)!] : []))
  const loading = ref(false)
  const error = ref('')
  const selectionError = ref('')
  const hasMore = ref(false)
  let generation = 0
  let selectionGeneration = 0
  let timer: ReturnType<typeof setTimeout> | undefined
  let request: AbortController | undefined
  let selectionRequest: AbortController | undefined

  const people = computed(() => {
    const entries = new Map(selected.value.map(person => [person.id, person]))
    for (const person of results.value) {
      entries.set(person.id, person)
    }
    return [...entries.values()].filter(person => !excludedIds.value.includes(person.id))
  })

  function remember (items: PersonSearchPerson[]) {
    store.rememberSearchResults(items)
    const known = new Map([...selected.value, ...items].map(person => [person.id, person]))
    selected.value = selectedIds.value.flatMap(id => known.has(id) ? [known.get(id)!] : [])
  }

  async function search () {
    const current = ++generation
    request?.abort()
    error.value = ''
    if (query.value.trim().length < 2) {
      results.value = []
      hasMore.value = false
      loading.value = false
      return
    }
    request = new AbortController()
    loading.value = true
    try {
      const { data } = await useApi().get<SearchResult>('/api/people/search', {
        params: { q: query.value.trim(), limit: 20, excludeIds: excludedIds.value }, signal: request.signal,
      })
      if (current !== generation) {
        return
      }
      results.value = data.items
      hasMore.value = data.hasMore
      remember(data.items)
    } catch (error_) {
      if (current === generation) {
        error.value = extractErrorMessage(error_, 'Unable to search people.')
      }
    } finally {
      if (current === generation) {
        loading.value = false
      }
    }
  }

  async function hydrate () {
    const current = ++selectionGeneration
    selectionRequest?.abort()
    selectionError.value = ''
    const known = new Map([...selectedIds.value.flatMap(id => store.byId(id) ? [store.byId(id)!] : []), ...selected.value, ...results.value].map(person => [person.id, person]))
    selected.value = selectedIds.value.flatMap(id => known.has(id) ? [known.get(id)!] : [])
    const missing = selectedIds.value.filter(id => !known.has(id))
    if (missing.length === 0) {
      return
    }
    selectionRequest = new AbortController()
    try {
      const found: PersonSearchPerson[] = []
      for (let start = 0; start < missing.length; start += 50) {
        const { data } = await useApi().get<SearchResult>('/api/people/search', {
          params: { ids: missing.slice(start, start + 50), limit: 50 }, signal: selectionRequest.signal,
        })
        if (current !== selectionGeneration) {
          return
        }
        found.push(...data.items)
      }
      remember(found)
      if (selected.value.length < selectedIds.value.length) {
        selectionError.value = 'Some selected people are unavailable. Check the selection.'
      }
    } catch (error_) {
      if (current === selectionGeneration) {
        selectionError.value = extractErrorMessage(error_, 'Unable to load selected people.')
      }
    }
  }

  watch([query, excludedIds], () => {
    clearTimeout(timer)
    ++generation
    request?.abort()
    results.value = []
    hasMore.value = false
    loading.value = false
    error.value = ''
    timer = setTimeout(search, 300)
  })
  watch(selectedIds, hydrate, { immediate: true })
  onScopeDispose(() => {
    clearTimeout(timer)
    ++generation
    ++selectionGeneration
    request?.abort()
    selectionRequest?.abort()
  })
  async function retry () {
    await Promise.all([search(), hydrate()])
  }
  return { query, people, loading, error: computed(() => error.value || selectionError.value), hasMore, retry }
}
