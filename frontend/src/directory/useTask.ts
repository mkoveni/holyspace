import axios from 'axios'
import { ref } from 'vue'
import { extractErrorMessage } from '@/composables/useApi'
export function useTask (options: { preserveConflictMessage?: boolean } = {}) {
  const busy = ref(false)
  const error = ref('')
  const conflict = ref(false)
  function clear () {
    error.value = ''
    conflict.value = false
  }
  async function run<T> (operation: () => Promise<T>): Promise<{ value: T } | undefined> {
    if (busy.value || conflict.value) {
      return
    }
    clear()
    busy.value = true
    try {
      return { value: await operation() }
    } catch (error_) {
      if (axios.isCancel(error_)) {
        return
      }
      conflict.value = axios.isAxiosError(error_) && error_.response?.status === 409
      error.value = conflict.value && !options.preserveConflictMessage
        ? 'These details have changed or conflict with an existing record. Reload the latest details before trying again.'
        : extractErrorMessage(error_, 'Unable to save. Please try again.')
    } finally {
      busy.value = false
    }
  }
  return { busy, error, conflict, clear, run }
}
