import { ref } from 'vue'
import { useTheme } from 'vuetify'

const STORAGE_KEY = 'dcs-theme'
const mode = ref<'light' | 'dark'>('light')

function apply (value: 'light' | 'dark') {
  mode.value = value
  document.documentElement.dataset.theme = value
}

function stored (): 'light' | 'dark' | null {
  try {
    const value = localStorage.getItem(STORAGE_KEY)
    return value === 'dark' || value === 'light' ? value : null
  } catch {
    return null
  }
}

/** Light/dark switch that keeps Vuetify and our CSS tokens in step. */
export function useAppTheme () {
  const theme = useTheme()
  function set (value: 'light' | 'dark') {
    apply(value)
    theme.change(value === 'dark' ? 'divineCommunitySpaceDark' : 'divineCommunitySpaceLight')
    try {
      localStorage.setItem(STORAGE_KEY, value)
    } catch {
      /* Preference is optional. */
    }
  }
  function init () {
    const initial = stored()
      ?? (globalThis.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
    set(initial)
  }
  return { mode, init, toggle: () => set(mode.value === 'dark' ? 'light' : 'dark') }
}
