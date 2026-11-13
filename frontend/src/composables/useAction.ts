import { ref } from 'vue'
import { classifyError } from '@/composables/apiErrors'
import { useUiStore } from '@/stores/ui'

interface ActionOptions {
  /** Toast shown after success. */
  success?: string
  /** Fallback when the thrown error has no message. */
  failure?: string
  /** Ask first; the action only runs after a yes. */
  confirm?: { title: string, message: string, confirmText?: string, color?: string }
  /** Offers an Undo button on the success toast. */
  undo?: () => Promise<unknown> | void
  /**
   * Runs when the server answers 409 (the record changed under the user), typically to reload it.
   * The error toast still appears so the user knows their change was not saved.
   */
  onConflict?: () => Promise<unknown> | void
}

/**
 * Wraps a mutation with loading state, an optional confirmation, a success toast and
 * an error toast, replacing the hand-written try/catch/notify block in each screen.
 * Resolves true when the action ran and succeeded.
 */
export function useAction () {
  const ui = useUiStore()
  const busy = ref(false)

  async function run (task: () => Promise<unknown>, options: ActionOptions = {}): Promise<boolean> {
    if (busy.value) {
      return false
    }
    if (options.confirm && !(await ui.confirmAction(options.confirm))) {
      return false
    }
    busy.value = true
    try {
      await task()
      if (options.success) {
        if (options.undo) {
          ui.notifyWithUndo(options.success, options.undo)
        } else {
          ui.notifySuccess(options.success)
        }
      }
      return true
    } catch (error) {
      const info = classifyError(error, options.failure ?? 'Something went wrong.')
      if (info.kind !== 'cancelled') {
        ui.notifyError(info.message)
      }
      if (info.kind === 'conflict' && options.onConflict) {
        try {
          await options.onConflict()
        } catch {
          // The reload failed too; the conflict toast above already tells the user to retry.
        }
      }
      return false
    } finally {
      busy.value = false
    }
  }

  return { busy, run }
}
