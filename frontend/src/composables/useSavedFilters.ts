import { ref, type Ref, watch } from 'vue'

export interface SavedView<T> {
  name: string
  filters: T
}

function read<V> (key: string, fallback: V): V {
  try {
    const stored = localStorage.getItem(key)
    return stored ? (JSON.parse(stored) as V) : fallback
  } catch {
    return fallback
  }
}

function write (key: string, value: unknown) {
  try {
    localStorage.setItem(key, JSON.stringify(value))
  } catch {
    /* Saved views are a convenience; the screen works without them. */
  }
}

/**
 * Remembers the last filters used on a screen and lets people name and reuse filter sets.
 * Stored only in this browser.
 */
export function useSavedFilters<T extends object> (screen: string, current: Ref<T>, defaults: T) {
  const lastKey = `dcs.filters.${screen}.last`
  const viewsKey = `dcs.filters.${screen}.views`
  const views = ref(read<SavedView<T>[]>(viewsKey, [])) as Ref<SavedView<T>[]>

  current.value = { ...defaults, ...read<Partial<T>>(lastKey, {}) }
  watch(current, value => write(lastKey, value), { deep: true })

  function save (name: string) {
    const trimmed = name.trim()
    if (!trimmed) {
      return
    }
    views.value = [...views.value.filter(view => view.name !== trimmed), { name: trimmed, filters: { ...current.value } }]
    write(viewsKey, views.value)
  }
  function apply (view: SavedView<T>) {
    current.value = { ...defaults, ...view.filters }
  }
  function remove (name: string) {
    views.value = views.value.filter(view => view.name !== name)
    write(viewsKey, views.value)
  }
  function reset () {
    current.value = { ...defaults }
  }

  return { views, save, apply, remove, reset }
}
