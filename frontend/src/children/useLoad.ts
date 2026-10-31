import axios from 'axios'
import { onBeforeUnmount, ref, shallowRef } from 'vue'
import { extractErrorMessage } from '@/composables/useApi'
export function useLoad<T> (operation: () => Promise<T>, initial: T) {
  const data = shallowRef<T>(initial)
  const loading = ref(false)
  const error = ref('')
  let sequence = 0
  async function load () {
    const current = ++sequence
    loading.value = true
    error.value = ''
    try {
      const value = await operation()
      if (current === sequence) {
        data.value = value
      }
    } catch (error_) {
      if (current === sequence && !axios.isCancel(error_)) {
        data.value = initial
        error.value = extractErrorMessage(
          error_,
          'Unable to load this workspace.',
        )
      }
    } finally {
      if (current === sequence) {
        loading.value = false
      }
    }
  }
  onBeforeUnmount(() => {
    sequence++
  })
  return { data, loading, error, load }
}
